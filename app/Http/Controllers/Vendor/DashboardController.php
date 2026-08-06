<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Product;
use App\Models\Order;
use App\Models\Advertisement;
use App\Models\Discount;
use App\Models\Review;
use App\Models\OrderItem;
use App\Models\Wallet;
use App\Models\StoreMessage;
use App\Models\ChatbotRule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Vendor\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * عرض الصفحة الرئيسية للوحة تحكم التاجر
     */
    public function index(): View
    {
        $store = auth()->user()->store;
        if (!$store) {
            abort(403, 'عذراً، حسابك كبائع لا يمتلك متجراً مرتبطاً به حالياً.');
        }

        $storeId = $store->id;

        // 1. جلب الإحصائيات الأساسية والمتقدمة
        $stats = $this->getStats($storeId);
        
        // 2. جلب أفضل 5 منتجات مبيعاً
        $top5Products = $this->getTop5Products($storeId);
        $stats['top_products_count'] = $top5Products->count();
        $stats['top_products_total_sold'] = $top5Products->sum('total_quantity_sold');

        // 3. بيانات الشارت (مبيعات آخر 7 أيام)
        $chart = $this->getSalesChartData($storeId);
        
        // 4. إحصائيات التقييمات التفصيلية
        $ratingStats = $this->getRatingStats($storeId, $stats['average_rating'], $stats['total_reviews']);

        // 5. قوائم الجداول
        $lowStockProducts = $this->getLowStockProducts($storeId);
        $latestPendingOrders = $this->getLatestPendingOrders($storeId);

        return view('vendor.dashboard', [
            'stats' => $stats,
            'top5Products' => $top5Products,
            'salesChartLabels' => $chart['labels'],
            'salesChartData' => $chart['data'],
            'ratingStats' => $ratingStats,
            'lowStockProducts' => $lowStockProducts,
            'latestPendingOrders' => $latestPendingOrders,
        ]);
    }

    /**
     * حساب الإحصائيات الأساسية للمتجر
     */
    private function getStats(int $storeId): array
    {
        $wallet = Wallet::where('user_id', auth()->id())->first();

        return [
            'total_products' => Product::where('store_id', $storeId)->count(),
            'active_products' => Product::where('store_id', $storeId)->where('status', 'active')->count(),
            'inactive_products' => Product::where('store_id', $storeId)->where('status', 'inactive')->count(),
            'total_orders' => Order::where('store_id', $storeId)->count(),
            'pending_orders' => Order::where('store_id', $storeId)->where('status', 'pending')->count(),
            'processing_orders' => Order::where('store_id', $storeId)->where('status', 'processing')->count(),
            'shipped_orders' => Order::where('store_id', $storeId)->where('status', 'shipped')->count(),
            'delivered_orders' => Order::where('store_id', $storeId)->where('status', 'delivered')->count(),
            'total_sales' => Order::where('store_id', $storeId)->where('status', 'delivered')->sum('total_amount'),
            'low_stock_products' => Product::where('store_id', $storeId)->whereRaw('stock <= min_stock')->count(),
            'active_advertisements' => Advertisement::where('store_id', $storeId)->where('status', 1)->count(),
            'active_discounts' => Discount::where('store_id', $storeId)->where('status', 'active')->count(),
            'total_reviews' => Review::whereHas('product', fn($q) => $q->where('store_id', $storeId))->count(),
            'pending_reviews' => Review::whereHas('product', fn($q) => $q->where('store_id', $storeId))->where('status', 'pending')->count(),
            'average_rating' => Review::whereHas('product', fn($q) => $q->where('store_id', $storeId))->where('status', 'approved')->avg('rating') ?? 0,
            'wallet_balance' => $wallet ? $wallet->balance : 0,
            'total_earnings' => $wallet ? $wallet->total_earnings : 0,
        ];
    }

    /**
     * جلب أفضل 5 منتجات مبيعاً في آخر 30 يوم
     */
    private function getTop5Products(int $storeId)
    {
        $startDate = now()->subDays(30)->startOfDay();

        return Product::where('products.store_id', $storeId)
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', function ($join) use ($startDate) {
                $join->on('order_items.order_id', '=', 'orders.id')
                    ->where('orders.created_at', '>=', $startDate)
                    ->where('orders.status', 'delivered');
            })
            ->select('products.*', DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_quantity_sold'))
            ->groupBy(
                'products.id', 'products.product_code', 'products.sku', 'products.name',
                'products.brand_id', 'products.description', 'products.notes',
                'products.full_description', 'products.price', 'products.cost_price',
                'products.price_before', 'products.stock', 'products.min_stock', 'products.status',
                'products.image', 'products.three_d_model', 'products.three_sixty_images',
                'products.store_id', 'products.category_id', 'products.category', 'products.manual_category', 'products.manual_brand', 'products.warranty_duration',
                'products.warranty_unit', 'products.created_at', 'products.updated_at',
                'products.currency', 'products.size', 'products.color', 'products.region',
                'products.ai_status', 'products.ai_notes'
            )
            ->orderByDesc('total_quantity_sold')
            ->limit(5)
            ->get();
    }

    /**
     * تجهيز بيانات مخطط المبيعات لآخر 7 أيام
     */
    private function getSalesChartData(int $storeId): array
    {
        $sales = Order::where('store_id', $storeId)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as total_sales'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $data = [];
        $labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->locale('ar')->dayName;
            $data[] = $sales->has($date) ? (float) $sales[$date]->total_sales : 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * إحصائيات التقييمات حسب النجوم
     */
    private function getRatingStats(int $storeId, float $average, int $count): array
    {
        $stars = [];
        for ($i = 5; $i >= 1; $i--) {
            $stars[$i] = Review::whereHas('product', fn($q) => $q->where('store_id', $storeId))
                ->where('rating', $i)
                ->count();
        }

        return [
            'average' => $average,
            'count' => $count,
            'stars' => $stars
        ];
    }

    /**
     * المنتجات التي أوشكت على النفاد
     */
    private function getLowStockProducts(int $storeId)
    {
        return Product::where('store_id', $storeId)
            ->where('stock', '<', 10)
            ->orderBy('stock', 'asc')
            ->limit(5)
            ->get();
    }

    /**
     * أحدث الطلبات في حالة الانتظار
     */
    private function getLatestPendingOrders(int $storeId)
    {
        return Order::where('store_id', $storeId)
            ->where('status', 'pending')
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function editStore(): View
    {
        $store = auth()->user()->store;
        if (!$store) {
            abort(403, 'عذراً، حسابك لا يمتلك متجراً مرتبطاً به حالياً.');
        }
        return view('vendor.store.edit', compact('store'));
    }

    public function updateStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'commercial_registration' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'logo_path' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $store = auth()->user()->store;
        $data = $request->except('logo_path');

        if ($request->hasFile('logo_path')) {
            if ($store->logo_path) {
                \Storage::disk('public')->delete($store->logo_path);
            }
            $data['logo_path'] = $request->file('logo_path')->store('logos', 'public');
        }

        $store->update($data);

        return redirect()->route('vendor.dashboard')->with('success', 'تم تحديث بيانات المتجر بنجاح');
    }

    public function generateToken()
    {
        $user = auth()->user();
        $user->tokens()->where('name', 'desktop-sync-token')->delete();
        $token = $user->createToken('desktop-sync-token');

        return redirect()->route('vendor.store.edit')
            ->with('api_token', $token->plainTextToken)
            ->with('success', 'تم إصدار مفتاح المزامنة بنجاح.');
    }
}
