<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'month');
        $customStart = $request->get('start_date');
        $customEnd = $request->get('end_date');

        if ($period === 'custom' && $customStart && $customEnd) {
            $startDate = Carbon::parse($customStart)->startOfDay();
            $endDate = Carbon::parse($customEnd)->endOfDay();
        } else {
            [$startDate, $endDate] = $this->getPeriodDates($period);
        }

        // إجمالي المبيعات (الطلبات المسلمة)
        $totalSales = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        // إجمالي عدد الطلبات
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();

        // الطلبات المسلمة
        $deliveredOrders = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // متوسط قيمة الطلب
        $averageOrderValue = $deliveredOrders > 0
            ? $totalSales / $deliveredOrders
            : 0;

        // إجمالي الكمية المباعة
        $totalQuantitySold = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->where('status', 'delivered')
                ->whereBetween('created_at', [$startDate, $endDate]);
        })->sum('quantity');

        // المبيعات اليومية (للرسم البياني)
        $dailySales = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // المبيعات حسب حالة الطلب
        $salesByStatus = Order::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // المبيعات حسب طريقة الدفع
        $salesByPayment = Order::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        // أفضل 10 منتجات حسب الإيرادات (عبر جميع المتاجر)
        $topProductsByRevenue = Product::leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', function ($join) use ($startDate, $endDate) {
                $join->on('order_items.order_id', '=', 'orders.id')
                    ->where('orders.status', 'delivered')
                    ->whereBetween('orders.created_at', [$startDate, $endDate]);
            })
            ->select(
                'products.*',
                DB::raw('COALESCE(SUM(order_items.total), 0) as revenue'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as quantity_sold')
            )
            ->groupBy(
                'products.id',
                'products.product_code',
                'products.sku',
                'products.name',
                'products.brand_id',
                'products.manual_brand',
                'products.description',
                'products.notes',
                'products.full_description',
                'products.price',
                'products.cost_price',
                'products.price_before',
                'products.stock',
                'products.min_stock',
                'products.status',
                'products.image',
                'products.three_d_model',
                'products.three_sixty_images',
                'products.store_id',
                'products.category_id',
                'products.category',
                'products.manual_category',
                'products.created_at',
                'products.updated_at',
                'products.warranty_duration',
                'products.warranty_unit',
                'products.currency',
                'products.size',
                'products.color',
                'products.region',
                'products.ai_status',
                'products.ai_notes'
            )
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // 1. حساب صافي أرباح المنصة (العمولة - افتراضياً 10%)
        $platformCommissionRate = 0.10; // 10%
        $platformNetProfit = $totalSales * $platformCommissionRate;

        // 2. قائمة أفضل المتاجر أداءً
        $topStoresByRevenue = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'store_id',
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(id) as orders_count')
            )
            ->groupBy('store_id')
            ->with('store') // لجلب اسم المتجر
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        // مقارنة مع الفترة السابقة
        $previousPeriod = $this->getPreviousPeriod($period, $startDate, $endDate);
        $previousSales = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$previousPeriod['start'], $previousPeriod['end']])
            ->sum('total_amount');

        $salesGrowth = $previousSales > 0
            ? (($totalSales - $previousSales) / $previousSales) * 100
            : 0;

        // إحصائيات المحفظة (مجمعة)
        $walletStats = [
            'balance' => Wallet::sum('balance'),
            'total_earnings' => Wallet::sum('total_earnings'),
            'withdrawn_amount' => Wallet::sum('withdrawn_amount'),
        ];

        // Compatibility variables for existing view
        $totalBalances = $walletStats['balance'];
        $totalEarnings = $walletStats['total_earnings'];
        $totalWithdrawals = $walletStats['withdrawn_amount'];

        // ملخص شامل
        $summary = [
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'delivered_orders' => $deliveredOrders,
            'average_order_value' => $averageOrderValue,
            'total_quantity_sold' => $totalQuantitySold,
            'sales_growth' => $salesGrowth,
            'period_days' => $startDate->diffInDays($endDate) + 1,
        ];

        return view('admin.reports.financial', compact(
            'walletStats',
            'summary',
            'dailySales',
            'salesByStatus',
            'salesByPayment',
            'topProductsByRevenue',
            'totalBalances',
            'totalEarnings',
            'totalWithdrawals',
            'period',
            'startDate',
            'endDate',
            'customStart',
            'customEnd',
            'platformNetProfit',
            'topStoresByRevenue'
        ));
    }

    private function getPeriodDates(string $period): array
    {
        return match ($period) {
            'week' => [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ],
            'month' => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ],
            'year' => [
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfYear(),
            ],
            'last_month' => [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ],
            'last_year' => [
                Carbon::now()->subYear()->startOfYear(),
                Carbon::now()->subYear()->endOfYear(),
            ],
            default => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ],
        };
    }

    private function getPreviousPeriod(string $period, Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate);

        return [
            'start' => $startDate->copy()->subDays($days + 1),
            'end' => $startDate->copy()->subDay(),
        ];
    }
}
