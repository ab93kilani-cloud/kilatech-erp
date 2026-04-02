<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    public function boot(): void
    {
        \App\Models\Product::observe(\App\Observers\AuditObserver::class);
        \App\Models\ProductVariant::observe(\App\Observers\AuditObserver::class);
        \App\Models\Supplier::observe(\App\Observers\AuditObserver::class);
        \App\Models\ProductSupplier::observe(\App\Observers\AuditObserver::class);
        \App\Models\PurchaseOrder::observe(\App\Observers\AuditObserver::class);
        \App\Models\PoLine::observe(\App\Observers\AuditObserver::class);
        \App\Models\Receipt::observe(\App\Observers\AuditObserver::class);
        \App\Models\ReceiptLine::observe(\App\Observers\AuditObserver::class);
        \App\Models\Lot::observe(\App\Observers\AuditObserver::class);
        \App\Models\StockUnit::observe(\App\Observers\AuditObserver::class);
        \App\Models\Quote::observe(\App\Observers\AuditObserver::class);
        \App\Models\QuoteLine::observe(\App\Observers\AuditObserver::class);
        \App\Models\Order::observe(\App\Observers\AuditObserver::class);
        \App\Models\OrderLine::observe(\App\Observers\AuditObserver::class);
        \App\Models\Invoice::observe(\App\Observers\AuditObserver::class);
        \App\Models\InvoicePayment::observe(\App\Observers\AuditObserver::class);
        \App\Models\Client::observe(\App\Observers\AuditObserver::class);
        \App\Models\User::observe(\App\Observers\AuditObserver::class);
        \App\Models\Role::observe(\App\Observers\AuditObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
