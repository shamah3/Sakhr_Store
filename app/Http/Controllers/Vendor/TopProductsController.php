<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class TopProductsController extends Controller
{
    /**
     * عرض قائمة المنتجات الأكثر طلباً
     */
    public function index(Request $request): View
    {
        $store = auth()->user()->store;

        // تحديد فترة زمنية للفلترة (افتراضياً آخر 30 يوم)
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days)->startOfDay();

        // الحصول على أفضل المنتجات بناءً على إجمالي الكمية المباعة
        // مع مراعاة الطلبات المسلمة فقط أو جميع الطلبات
        $orderStatus = $request->get('order_status', 'all'); // all, delivered, paid

        $query = Product::where('products.store_id', $store->id)
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', function ($join) use ($startDate, $orderStatus) {
                $join->on('order_items.order_id', '=', 'orders.id')
                    ->where('orders.created_at', '>=', $startDate);

                if ($orderStatus === 'delivered') {
                    $join->where('orders.status', 'delivered');
                } elseif ($orderStatus === 'paid') {
                    $join->where('orders.payment_status', 'paid');
                }
            })
            ->select(
                'products.*',
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_quantity_sold'),
                DB::raw('COALESCE(SUM(order_items.total), 0) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as total_orders')
            )
            ->groupBy(
                'products.id',
                'products.product_code',
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
                'products.sku',
                'products.currency',
                'products.size',
                'products.color',
                'products.region',
                'products.ai_status',
                'products.ai_notes'
            )
            ->orderByDesc('total_quantity_sold')
            ->limit(50); // الحصول على أكثر من 5 للفلترة

        $products = $query->with('category')->get();

        // إحصائيات إضافية
        $stats = [
            'total_products_in_orders' => $products->where('total_quantity_sold', '>', 0)->count(),
            'total_quantity_sold' => $products->sum('total_quantity_sold'),
            'total_revenue' => $products->sum('total_revenue'),
            'period_days' => $days,
        ];

        // أفضل 5 منتجات للعرض في البطاقة
        $top5Products = $products->take(5);

        return view('vendor.top-products.index', compact('products', 'top5Products', 'stats', 'days', 'orderStatus'));
    }
}
