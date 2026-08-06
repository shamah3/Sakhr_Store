<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // 1. حساب عدد البائعين
        $vendorCount = User::role('vendor')->count();

        // 2. حساب عدد المتاجر النشطة
        $activeStoresCount = Store::where('is_active', true)->count();

        // ##### الكود المضاف #####
        // 3. حساب عدد العملاء (المستخدمين الذين لديهم دور عميل)
        $customerCount = User::role('customer')->count();

        // 4. Products Count
        $productCount = \App\Models\Product::count();



        // 6. Reviews Count
        $reviewCount = \App\Models\Review::count();

        // 7. Complaints Open Count
        $complaintOpenCount = \App\Models\Complaint::where('status', 'open')->count();

        // 8. Notifications Sent Count (Total in DB)
        $notificationCount = \Illuminate\Support\Facades\DB::table('notifications')->count();

        // 9. Financial Reports Count
        $financialReportsCount = \App\Models\Wallet::where('total_earnings', '>', 0)->count();

        // --- NEW STATISTICS ---
        // 10. Orders Count
        $ordersCount = \App\Models\Order::count();
        // 11. Returns Count
        $returnsCount = \App\Models\OrderReturn::count();
        // 12. Shipping Count (Orders shipped or delivered)
        $shippingCount = \App\Models\Order::whereIn('status', ['shipped', 'delivered'])->count();
        // 13. Advertisements Count
        $advertisementCount = \App\Models\Advertisement::count();

        // 16. Payment Management (Count of paid orders)
        $paymentCount = \App\Models\Order::where('payment_status', 'paid')->count();
        // 17. Customer Analysis (New Customers this month)
        $newCustomersCount = User::role('customer')->where('created_at', '>=', now()->subDays(30))->count();

        // 18. Categories Count (Root Categories only)
        $categoriesCount = \App\Models\Category::whereNull('parent_id')->count();

        // 19. Activity Log Count (New)
        $activityLogCount = \App\Models\ActivityLog::count();

        // 20. Shipping Addresses Count
        $shippingAddressCount = \Illuminate\Support\Facades\DB::table('addresses')->count();

        // 21. Wallet Transactions Count
        $walletTxCount = \App\Models\WalletTransaction::count();

        // 22. Discount Coupons Count
        $discountCouponCount = \Illuminate\Support\Facades\DB::table('discounts')->where('status', 'active')->count();

        // 23. Customer Activities Count (From App)
        $customerActivityCount = \App\Models\CustomerActivity::count();


        // --- NEW CHARTS DATA ---

        // 1. Users Growth Chart (Line Chart)
        $dates = collect();
        for ($i = 29; $i >= 0; $i--) {
            $dates->put(now()->subDays($i)->format('Y-m-d'), 0);
        }

        // Users Growth (All Users)
        $usersGrowthQuery = User::select(\Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'), \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->pluck('count', 'date');

        // Vendors Growth
        $vendorsGrowthQuery = User::role('vendor')
            ->select(\Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'), \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->pluck('count', 'date');

        $growthLabels = $dates->keys();
        $userGrowthValues = $dates->map(fn($val, $date) => $usersGrowthQuery[$date] ?? 0)->values();
        $vendorGrowthValues = $dates->map(fn($val, $date) => $vendorsGrowthQuery[$date] ?? 0)->values();

        // 2. Vendors Status Chart (Pie Chart)
        $activeVendors = User::role('vendor')->where('status', 'active')->whereHas('store', function ($q) {
            $q->where('is_active', true);
        })->count();

        $bannedVendors = User::role('vendor')->where('status', 'banned')->count();

        // Pending/Suspended: Active User but Inactive Store OR User with 'pending' status (if implemented)
        $pendingVendors = User::role('vendor')->where(function ($q) {
            $q->where('status', '!=', 'banned')->whereHas('store', function ($sq) {
                $sq->where('is_active', false);
            });
        })->orWhere('status', 'pending')->count();

        // Fallback calculation: Total Vendors - (Active + Banned) might be safer if statuses are messy, 
        // but explicit query is better. Let's stick to explicit.


        // --- CHART DATA ---

        // A. Sales Statistics (Last 30 Days) - Line Chart
        $salesData = \App\Models\Order::select(
            \Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'),
            \Illuminate\Support\Facades\DB::raw('SUM(total_amount) as total')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $salesLabels = $salesData->pluck('date');
        $salesValues = $salesData->pluck('total');

        // B. Top Categories - Pie Chart
        // Join: order_items -> products -> categories
        $topCategories = \App\Models\OrderItem::join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', \Illuminate\Support\Facades\DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->groupBy('categories.name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        $categoryLabels = $topCategories->pluck('name');
        $categoryValues = $topCategories->pluck('total_quantity');


        // 4. إرسال كل هذه البيانات إلى الواجهة
        return view('admin.dashboard', [
            'vendorCount' => $vendorCount,
            'activeStoresCount' => $activeStoresCount,
            'customerCount' => $customerCount,
            'productCount' => $productCount,

            'reviewCount' => $reviewCount,
            'complaintOpenCount' => $complaintOpenCount,
            'notificationCount' => $notificationCount,
            'financialReportsCount' => $financialReportsCount,
            'ordersCount' => $ordersCount,
            'returnsCount' => $returnsCount,
            'shippingCount' => $shippingCount,
            'advertisementCount' => $advertisementCount,

            'paymentCount' => $paymentCount,
            'newCustomersCount' => $newCustomersCount,
            'categoriesCount' => $categoriesCount,
            'activityLogCount'      => $activityLogCount,
            'shippingAddressCount'  => $shippingAddressCount,
            'walletTxCount'         => $walletTxCount,
            'discountCouponCount'   => $discountCouponCount,
            'customerActivityCount' => $customerActivityCount,
            // Chart Data
            'salesLabels' => $salesLabels,
            'salesValues' => $salesValues,
            'categoryLabels' => $categoryLabels,
            'categoryValues' => $categoryValues,
            // New Charts Data
            'growthLabels' => $growthLabels,
            'userGrowthValues' => $userGrowthValues,
            'vendorGrowthValues' => $vendorGrowthValues,
            'activeVendors' => $activeVendors,
            'bannedVendors' => $bannedVendors,
            'pendingVendors' => $pendingVendors,
        ]);
    }
}