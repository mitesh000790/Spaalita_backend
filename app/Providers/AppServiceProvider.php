<?php

namespace App\Providers;

use App\Http\Middleware\ParseFormBody;
use App\Models\GiftCard;
use App\Models\Product;
use App\Services\JwtService;
use App\Services\StripeService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JwtService::class, fn () => new JwtService(
            config('jwt.secret'),
            config('jwt.algorithm'),
            config('jwt.ttl'),
        ));

        $this->app->singleton(StripeService::class, fn () => new StripeService(
            config('services.stripe.secret'),
            config('services.stripe.webhook_secret'),
            config('services.stripe.api_base'),
        ));

        // Same instance for handle() and terminate(), which removes its temporary files.
        $this->app->singleton(ParseFormBody::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Coupon product references store "Product" / "GiftCard" as their type.
        Relation::enforceMorphMap([
            'Product' => Product::class,
            'GiftCard' => GiftCard::class,
        ]);

        JsonResource::withoutWrapping();

        /*
         * $table->objectId('column'): a 24-character ObjectId column (primary
         * keys and every column that references one). Stored as ASCII on
         * MySQL/MariaDB to keep keys and indexes compact.
         */
        Blueprint::macro('objectId', function (string $column): ColumnDefinition {
            /** @var Blueprint $this */
            $definition = $this->char($column, 24);

            if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $definition->charset('ascii')->collation('ascii_general_ci');
            }

            return $definition;
        });
    }
}
