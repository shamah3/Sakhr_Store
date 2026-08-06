<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ReturnController extends BaseController
{
    /**
     * Store product return request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'product_id' => 'required|exists:products,id',
            'reason' => 'required|string',
            'quantity' => 'nullable|integer|min:1',
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $order = Order::query()
            ->with('orderItems')
            ->whereKey($request->order_id)
            ->first();

        if (!$order || (int) $order->user_id !== (int) $user->id) {
            return $this->sendError('لا يمكن تقديم طلب إرجاع لطلب لا يخص المستخدم الحالي.', [], 403);
        }

        $orderItem = $order->orderItems
            ->firstWhere('product_id', (int) $request->product_id);

        if (!$orderItem) {
            return $this->sendError('المنتج المطلوب إرجاعه غير موجود ضمن هذا الطلب.', [
                'product_id' => ['The selected product does not belong to the specified order.'],
            ], 422);
        }

        $quantity = $request->filled('quantity')
            ? (int) $request->quantity
            : 1;

        if ($quantity > (int) $orderItem->quantity) {
            return $this->sendError('كمية الإرجاع تتجاوز الكمية الموجودة في الطلب.', [
                'quantity' => ['Return quantity exceeds ordered quantity.'],
            ], 422);
        }

        $orderReturn = OrderReturn::create([
            'order_id' => $request->order_id,
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'quantity' => $quantity,
            'reason' => $request->reason,
            'status' => $request->status ?? 'pending',
        ]);

        return $this->sendResponse(
            $orderReturn->load(['order', 'product', 'user']),
            'تم استقبال طلب الإرجاع بنجاح',
            201
        );
    }
}
