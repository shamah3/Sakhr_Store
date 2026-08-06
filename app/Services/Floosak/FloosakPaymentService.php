<?php

namespace App\Services\Floosak;

use App\Models\FloosakPaymentAttempt;
use App\Models\FloosakRefund;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FloosakPaymentService
{
    public function __construct(private readonly FloosakClient $client)
    {
    }

    public function initiate(Order $order, User $user): FloosakPaymentAttempt
    {
        if (config('floosak.bypass', false)) {
            return DB::transaction(function () use ($order, $user): FloosakPaymentAttempt {
                $existing = FloosakPaymentAttempt::query()
                    ->where('order_id', $order->id)
                    ->where('user_id', $user->id)
                    ->latest()
                    ->first();

                if ($existing && $existing->status === FloosakPaymentAttempt::STATUS_COMPLETED) {
                    return $existing;
                }

                $attempt = FloosakPaymentAttempt::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'request_id' => (string) Str::uuid(),
                    'gateway_transaction_id' => 'BYPASS-' . strtoupper(Str::random(12)),
                    'gateway_status_en' => 'Completed',
                    'gateway_status_ar' => 'مكتمل (تجاوز)',
                    'amount' => $order->total_amount,
                    'status' => FloosakPaymentAttempt::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                $this->markOrderPaid($attempt);

                return $attempt;
            });
        }

        $this->ensureConfigured();

        if ((int) $order->user_id !== (int) $user->id) {
            throw new FloosakPaymentException('لا يمكن الدفع لطلب لا يخص المستخدم الحالي.', 403);
        }

        if (!$user->phone) {
            throw new FloosakPaymentException('رقم هاتف العميل مطلوب لاستخدام محفظة فلوسك.', 422, [
                'phone' => ['Customer phone is required.'],
            ]);
        }

        if ($order->payment_method !== 'floosak_wallet') {
            throw new FloosakPaymentException('طريقة دفع الطلب ليست محفظة فلوسك.', 422, [
                'payment_method' => ['The order payment method must be floosak_wallet.'],
            ]);
        }

        if ($order->payment_status === 'paid') {
            throw new FloosakPaymentException('تم دفع هذا الطلب مسبقاً.', 409);
        }

        if ((float) $order->total_amount <= 0) {
            throw new FloosakPaymentException('قيمة الطلب غير صالحة للدفع.', 422, [
                'amount' => ['Order total must be positive.'],
            ]);
        }

        $attempt = DB::transaction(function () use ($order, $user): FloosakPaymentAttempt {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status === 'paid') {
                throw new FloosakPaymentException('تم دفع هذا الطلب مسبقاً.', 409);
            }

            $activeAttempt = FloosakPaymentAttempt::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', FloosakPaymentAttempt::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($activeAttempt) {
                return $activeAttempt;
            }

            return FloosakPaymentAttempt::create([
                'order_id' => $lockedOrder->id,
                'user_id' => $user->id,
                'request_id' => (string) Str::uuid(),
                'source_wallet_id' => (int) config('floosak.source_wallet_id'),
                'target_phone' => (string) $user->phone,
                'amount' => $lockedOrder->total_amount,
                'purpose' => "Order {$lockedOrder->order_number}",
                'status' => FloosakPaymentAttempt::STATUS_INITIATING,
            ]);
        });

        if ($attempt->gateway_purchase_id || $attempt->status !== FloosakPaymentAttempt::STATUS_INITIATING) {
            return $attempt->fresh();
        }



        return $this->sendAttempt($attempt);
    }

    public function confirm(FloosakPaymentAttempt $attempt, User $user, string $otp): FloosakPaymentAttempt
    {
        if (config('floosak.bypass', false)) {
            return DB::transaction(function () use ($attempt): FloosakPaymentAttempt {
                $locked = FloosakPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $locked->update([
                    'status' => FloosakPaymentAttempt::STATUS_COMPLETED,
                    'gateway_transaction_id' => $locked->gateway_transaction_id ?: 'BYPASS-C-' . strtoupper(Str::random(10)),
                    'gateway_status_en' => 'Completed',
                    'gateway_status_ar' => 'مكتمل (تجاوز)',
                    'completed_at' => now(),
                ]);
                $this->markOrderPaid($locked);

                return $locked->fresh();
            });
        }

        if ((int) $attempt->user_id !== (int) $user->id) {
            throw new FloosakPaymentException('لا يمكن تأكيد عملية دفع لا تخص المستخدم الحالي.', 403);
        }

        if ($attempt->isCompleted()) {
            return $attempt->fresh();
        }

        if (!$attempt->gateway_purchase_id) {
            throw new FloosakPaymentException('عملية الدفع لم تحصل على رقم شراء من بوابة فلوسك بعد.', 409);
        }

        $attempt = DB::transaction(function () use ($attempt): FloosakPaymentAttempt {
            $locked = FloosakPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (!$locked->isCompleted()) {
                $locked->update(['status' => FloosakPaymentAttempt::STATUS_CONFIRMING]);
            }

            return $locked;
        });



        try {
            $response = $this->client->confirmPayment($attempt->gateway_purchase_id, $otp);

            return DB::transaction(fn () => $this->applyGatewayTransaction(
                $attempt->fresh(),
                $response,
                'confirm'
            ));
        } catch (ConnectionException $exception) {
            return $this->markUnknown($attempt, FloosakPaymentAttempt::STATUS_CONFIRM_UNKNOWN, $exception->getMessage());
        } catch (FloosakApiException $exception) {
            if ($exception->retryable) {
                return $this->markUnknown($attempt, FloosakPaymentAttempt::STATUS_CONFIRM_UNKNOWN, $exception->getMessage(), $exception->payload);
            }

            if ($exception->httpStatus === 422) {
                $this->markConfirmValidationFailed($attempt, $exception->getMessage(), $exception->payload);

                $errors = data_get($exception->payload, 'errors');
                throw new FloosakPaymentException(
                    $exception->getMessage(),
                    422,
                    is_array($errors) ? $errors : []
                );
            }

            return $this->markFailed($attempt, FloosakPaymentAttempt::STATUS_FAILED, $exception->getMessage(), $exception->payload);
        }
    }

    public function reconcile(FloosakPaymentAttempt $attempt): FloosakPaymentAttempt
    {
        try {
            $response = $this->client->checkStatus($attempt->request_id);

            return DB::transaction(function () use ($attempt, $response): FloosakPaymentAttempt {
                $locked = FloosakPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $locked->increment('reconciliation_attempts');
                $locked->forceFill([
                    'last_reconciled_at' => now(),
                    'next_reconcile_at' => null,
                ])->save();

                return $this->applyGatewayTransaction($locked->fresh(), $response, 'status');
            });
        } catch (ConnectionException $exception) {
            return $this->recordReconciliationFailure($attempt, $exception->getMessage());
        } catch (FloosakApiException $exception) {
            if ($exception->retryable) {
                return $this->recordReconciliationFailure($attempt, $exception->getMessage(), $exception->payload);
            }

            return DB::transaction(function () use ($attempt, $exception): FloosakPaymentAttempt {
                $locked = FloosakPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $locked->increment('reconciliation_attempts');

                if ($locked->reconciliation_attempts >= (int) config('floosak.reconciliation.max_attempts', 12)) {
                    return $this->markFailed($locked, FloosakPaymentAttempt::STATUS_SEND_FAILED, $exception->getMessage(), $exception->payload);
                }

                return $this->scheduleNextReconciliation($locked, $exception->getMessage(), $exception->payload);
            });
        }
    }

    public function refund(FloosakPaymentAttempt $attempt, float|string $amount): FloosakRefund
    {
        $this->ensureConfigured();

        if (!$attempt->isCompleted() || !$attempt->gateway_transaction_id) {
            throw new FloosakPaymentException('Only completed Floosak transactions can be refunded.', 409);
        }

        $refundAmount = round((float) $amount, 2);
        if ($refundAmount <= 0) {
            throw new FloosakPaymentException('Refund amount must be positive.', 422);
        }

        $refund = DB::transaction(function () use ($attempt, $refundAmount): FloosakRefund {
            $locked = FloosakPaymentAttempt::query()
                ->with('refunds')
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyRefunded = (float) $locked->refunds()
                ->whereIn('status', [FloosakRefund::STATUS_INITIATING, FloosakRefund::STATUS_UNKNOWN, FloosakRefund::STATUS_COMPLETED])
                ->sum('amount');

            $available = round((float) ($locked->net ?? $locked->amount) - $alreadyRefunded, 2);

            if ($refundAmount > $available) {
                throw new FloosakPaymentException('Refund amount exceeds the original transaction net amount.', 422);
            }

            return FloosakRefund::create([
                'floosak_payment_attempt_id' => $locked->id,
                'request_id' => (string) Str::uuid(),
                'amount' => $refundAmount,
                'status' => FloosakRefund::STATUS_INITIATING,
            ]);
        });

        $payload = [
            'transaction_id' => $attempt->gateway_transaction_id,
            'request_id' => $refund->request_id,
            'amount' => $refundAmount,
        ];

        $refund->update(['refund_request_payload' => $payload]);

        try {
            $response = $this->client->refundPayment($attempt->gateway_transaction_id, $refund->request_id, $refundAmount);
            $data = $this->transactionPayload($response);

            $refund->update([
                'gateway_refund_transaction_id' => data_get($data, 'id'),
                'gateway_reference_id' => data_get($data, 'reference_id'),
                'gateway_status_en' => data_get($data, 'status.en'),
                'gateway_status_ar' => data_get($data, 'status.ar'),
                'status' => data_get($data, 'status.en') === 'Completed'
                    ? FloosakRefund::STATUS_COMPLETED
                    : FloosakRefund::STATUS_UNKNOWN,
                'refund_response_payload' => $response,
                'completed_at' => data_get($data, 'status.en') === 'Completed' ? now() : null,
            ]);
        } catch (ConnectionException $exception) {
            $refund->update([
                'status' => FloosakRefund::STATUS_UNKNOWN,
                'last_error_message' => $exception->getMessage(),
            ]);
        } catch (FloosakApiException $exception) {
            $refund->update([
                'status' => $exception->retryable ? FloosakRefund::STATUS_UNKNOWN : FloosakRefund::STATUS_FAILED,
                'last_error_message' => $exception->getMessage(),
                'last_error_payload' => $exception->payload,
                'failed_at' => $exception->retryable ? null : now(),
            ]);
        }

        return $refund->fresh();
    }

    private function sendAttempt(FloosakPaymentAttempt $attempt): FloosakPaymentAttempt
    {
        $payload = [
            'source_wallet_id' => (int) $attempt->source_wallet_id,
            'request_id' => $attempt->request_id,
            'target_phone' => $attempt->target_phone,
            'amount' => round((float) $attempt->amount, 2),
            'purpose' => $attempt->purpose,
        ];

        $attempt->update(['send_request_payload' => $payload]);

        try {
            $response = $this->client->sendPayment($payload);

            return DB::transaction(fn () => $this->applyGatewayTransaction(
                $attempt->fresh(),
                $response,
                'send'
            ));
        } catch (ConnectionException $exception) {
            return $this->markUnknown($attempt, FloosakPaymentAttempt::STATUS_SEND_UNKNOWN, $exception->getMessage());
        } catch (FloosakApiException $exception) {
            if ($exception->retryable) {
                return $this->markUnknown($attempt, FloosakPaymentAttempt::STATUS_SEND_UNKNOWN, $exception->getMessage(), $exception->payload);
            }

            return $this->markFailed($attempt, FloosakPaymentAttempt::STATUS_SEND_FAILED, $exception->getMessage(), $exception->payload);
        }
    }

    private function applyGatewayTransaction(FloosakPaymentAttempt $attempt, array $response, string $source): FloosakPaymentAttempt
    {
        $data = $this->transactionPayload($response);
        $statusEn = data_get($data, 'status.en');
        $statusAr = data_get($data, 'status.ar');

        $updates = [
            'gateway_reference_id' => data_get($data, 'reference_id', $attempt->gateway_reference_id),
            'gateway_status_en' => $statusEn,
            'gateway_status_ar' => $statusAr,
            'net' => data_get($data, 'net', $attempt->net),
            'fee' => data_get($data, 'fee', $attempt->fee),
            'gross' => data_get($data, 'gross', $attempt->gross),
            'balance' => data_get($data, 'balance', $attempt->balance),
            'last_error_code' => null,
            'last_error_message' => null,
            'last_error_payload' => null,
        ];

        if ($source === 'send') {
            $updates['send_response_payload'] = $response;
        }

        if ($source === 'confirm') {
            $updates['confirm_response_payload'] = $response;
        }

        if ($source === 'status') {
            $updates['status_response_payload'] = $response;
        }

        if ($statusEn === 'Completed') {
            $updates['status'] = FloosakPaymentAttempt::STATUS_COMPLETED;
            $updates['gateway_transaction_id'] = (string) data_get($data, 'id', $attempt->gateway_transaction_id);
            $updates['completed_at'] = $attempt->completed_at ?: now();
            $updates['next_reconcile_at'] = null;

            $attempt->update($updates);
            $this->markOrderPaid($attempt->fresh());

            return $attempt->fresh();
        }

        if ($statusEn === 'Pending') {
            $updates['status'] = FloosakPaymentAttempt::STATUS_PENDING;

            if (!$attempt->gateway_purchase_id) {
                $updates['gateway_purchase_id'] = (string) data_get($data, 'id');
            }

            $attempt->update($updates);

            return $attempt->fresh();
        }

        $updates['status'] = FloosakPaymentAttempt::STATUS_FAILED;
        $updates['failed_at'] = now();
        $attempt->update($updates);

        return $attempt->fresh();
    }

    private function markOrderPaid(FloosakPaymentAttempt $attempt): void
    {
        $order = Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();

        if ($order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'status' => $order->status === 'pending' ? 'processing' : $order->status,
            ]);
        }

        if ($attempt->gateway_transaction_id) {
            DB::table('transactions')->updateOrInsert(
                ['transaction_id' => $attempt->gateway_transaction_id],
                [
                    'user_id' => $attempt->user_id,
                    'order_id' => $attempt->order_id,
                    'provider' => 'floosak_wallet',
                    'amount' => $attempt->net ?? $attempt->amount,
                    'currency' => 'YER',
                    'status' => 'success',
                    'payload' => json_encode($attempt->confirm_response_payload ?: $attempt->status_response_payload),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function transactionPayload(array $response): array
    {
        $data = data_get($response, 'data');
        if (is_array($data)) {
            return $data;
        }

        $message = data_get($response, 'message');
        if (is_array($message)) {
            return $message;
        }

        return [];
    }

    private function markUnknown(FloosakPaymentAttempt $attempt, string $status, string $message, ?array $payload = null): FloosakPaymentAttempt
    {
        $attempt->update([
            'status' => $status,
            'last_error_message' => $message,
            'last_error_payload' => $payload,
            'next_reconcile_at' => now(),
        ]);

        return $attempt->fresh();
    }

    private function markFailed(FloosakPaymentAttempt $attempt, string $status, string $message, ?array $payload = null): FloosakPaymentAttempt
    {
        $attempt->update([
            'status' => $status,
            'last_error_message' => $message,
            'last_error_payload' => $payload,
            'failed_at' => now(),
            'next_reconcile_at' => null,
        ]);

        return $attempt->fresh();
    }

    private function markConfirmValidationFailed(FloosakPaymentAttempt $attempt, string $message, ?array $payload = null): FloosakPaymentAttempt
    {
        $attempt->update([
            'status' => FloosakPaymentAttempt::STATUS_PENDING,
            'last_error_message' => $message,
            'last_error_payload' => $payload,
            'next_reconcile_at' => null,
        ]);

        return $attempt->fresh();
    }

    private function scheduleNextReconciliation(FloosakPaymentAttempt $attempt, string $message, ?array $payload = null): FloosakPaymentAttempt
    {
        $attempt->update([
            'last_error_message' => $message,
            'last_error_payload' => $payload,
            'last_reconciled_at' => now(),
            'next_reconcile_at' => now()->addMinutes(5),
        ]);

        return $attempt->fresh();
    }

    private function recordReconciliationFailure(FloosakPaymentAttempt $attempt, string $message, ?array $payload = null): FloosakPaymentAttempt
    {
        return DB::transaction(function () use ($attempt, $message, $payload): FloosakPaymentAttempt {
            $locked = FloosakPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $locked->increment('reconciliation_attempts');

            return $this->scheduleNextReconciliation($locked, $message, $payload);
        });
    }

    private function ensureConfigured(): void
    {
        if (config('floosak.bypass', false)) {
            return;
        }

        if (!config('floosak.enabled')) {
            throw new FloosakPaymentException('بوابة فلوسك غير مفعلة حالياً.', 503);
        }

        foreach (['base_url', 'merchant_phone', 'merchant_password'] as $key) {
            if (!config("floosak.{$key}")) {
                throw new FloosakPaymentException("Floosak configuration value [{$key}] is missing.", 503);
            }
        }

        if (!is_numeric(config('floosak.source_wallet_id')) || (int) config('floosak.source_wallet_id') <= 0) {
            throw new FloosakPaymentException('Floosak configuration value [source_wallet_id] must be a positive integer.', 503);
        }
    }
}
