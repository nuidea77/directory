<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // N+1 query-г хөгжүүлэлтийн үед шууд илрүүлнэ
        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Зардал ихтэй/спамд өртөмтгий үйлдлүүдэд нарийн хязгаар
        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute(20)
            ->by(($request->user()?->id ?: $request->ip()).'|'.$request->path()));
    }
}
