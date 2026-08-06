<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.url')) {
            \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
        }

        // إعطاء صلاحية كاملة للـ super-admin لتجاوز أي 403
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Model Observers
        \App\Models\Product::observe(\App\Observers\GeneralObserver::class);
        \App\Models\Order::observe(\App\Observers\GeneralObserver::class);
        \App\Models\Store::observe(\App\Observers\GeneralObserver::class);
        \App\Models\User::observe(\App\Observers\GeneralObserver::class);

        // Auth Event Subscriber
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\LogAuthActivity::class);
    }
}
