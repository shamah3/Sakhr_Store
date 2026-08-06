<?php

namespace App\AI\Tools;

use App\Models\Advertisement;
use App\Models\Discount;
use App\Models\Store;
use App\Models\Product;
use LaravelAIAgent\Attributes\AsAITool;

class AppSearchTools
{
    #[AsAITool(description: 'البحث عن الإعلانات النشطة في التطبيق')]
    public function searchAdvertisements(): string
    {
        $ads = Advertisement::where('status', 1)
            ->where(function($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->latest()
            ->limit(5)
            ->get(['title', 'description', 'target_url']);

        if ($ads->isEmpty()) {
            return "لا توجد إعلانات نشطة حالياً.";
        }

        $result = "إليك بعض الإعلانات المتوفرة حالياً:\n";
        foreach ($ads as $ad) {
            $result .= "- {$ad->title}: {$ad->description} (الرابط: {$ad->target_url})\n";
        }

        return $result;
    }

    #[AsAITool(description: 'البحث عن الخصومات والكوبونات المتوفرة')]
    public function searchDiscounts(): string
    {
        $discounts = Discount::where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->latest()
            ->limit(5)
            ->get(['title', 'code', 'type', 'value', 'min_order_amount']);

        if ($discounts->isEmpty()) {
            return "لا توجد خصومات مفعلة حالياً.";
        }

        $result = "إليك أقوى الخصومات المتوفرة:\n";
        foreach ($discounts as $discount) {
            $value = $discount->type === 'percentage' ? "{$discount->value}%" : "{$discount->value} ريال";
            $result .= "- {$discount->title}: كود الخصم [{$discount->code}] بقيمة {$value} (للمشتريات فوق {$discount->min_order_amount})\n";
        }

        return $result;
    }

    #[AsAITool(description: 'البحث عن المتاجر الموجودة في التطبيق')]
    public function searchStores(): string
    {
        $stores = Store::active()
            ->latest()
            ->limit(5)
            ->get(['name', 'slogan', 'description', 'address']);

        if ($stores->isEmpty()) {
            return "لا توجد متاجر نشطة حالياً.";
        }

        $result = "إليك بعض المتاجر المتوفرة في تطبيقنا:\n";
        foreach ($stores as $store) {
            $result .= "- متجر {$store->name}: {$store->slogan} - {$store->address}\n";
        }

        return $result;
    }

    #[AsAITool(description: 'البحث عن منتجات معينة ومواصفاتها')]
    public function searchProducts(string $query): string
    {
        $products = Product::active()
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->with(['brand', 'category'])
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            return "عذراً، لم أجد منتجات تطابق بحثك عن '{$query}'.";
        }

        $result = "وجدت المنتجات التالية:\n";
        foreach ($products as $product) {
            $brand = $product->brand ? $product->brand->name : 'غير محدد';
            $result .= "- {$product->name} (ماركة {$brand}): {$product->description} - السعر: {$product->price} ريال\n";
        }

        return $result;
    }
}
