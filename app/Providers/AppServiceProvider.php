<?php

namespace App\Providers;

use App\Domain\Awards\Models\Award;
use App\Domain\Awards\Models\AwardLine;
use App\Domain\Bids\Models\Bid;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Bids\Policies\BidPolicy;
use App\Domain\Integrations\Models\AuditLog;
use App\Domain\Integrations\Models\IdempotencyRecord;
use App\Domain\Integrations\Models\InvitationAccessToken;
use App\Domain\Integrations\Models\OutboxEvent;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Organizations\Models\ApiClient;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\Policies\TenantRecordPolicy;
use App\Domain\Organizations\TenantContext;
use App\Domain\Shared\ActivityContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierCapability;
use App\Domain\Suppliers\Models\SupplierContact;
use App\Domain\Suppliers\Models\SupplierLocation;
use App\Domain\Suppliers\Policies\SupplierPolicy;
use App\Domain\Tenders\Models\RepairScope;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderAttachment;
use App\Domain\Tenders\Models\TenderInvitation;
use App\Domain\Tenders\Models\TenderItem;
use App\Domain\Tenders\Models\TenderStatusHistory;
use App\Domain\Tenders\Policies\TenderPolicy;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Models\WorkOrderLine;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActivityContext::class, fn () => new ActivityContext);
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
            Limit::perMinute(5)->by($request->ip().'|'.hash('sha256', strtolower((string) $request->input('email')))),
        ]);
        Paginator::useBootstrapFive();
        Date::use(CarbonImmutable::class);
        Model::preventLazyLoading(! $this->app->isProduction());
        Gate::policy(OrganizationMembership::class, TenantRecordPolicy::class);
        Gate::policy(ApiClient::class, TenantRecordPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(SupplierContact::class, SupplierPolicy::class);
        Gate::policy(SupplierLocation::class, SupplierPolicy::class);
        Gate::policy(SupplierCapability::class, SupplierPolicy::class);
        Gate::policy(OrganizationSupplier::class, TenantRecordPolicy::class);
        Gate::policy(Tender::class, TenderPolicy::class);
        Gate::policy(TenderItem::class, TenantRecordPolicy::class);
        Gate::policy(RepairScope::class, TenantRecordPolicy::class);
        Gate::policy(TenderAttachment::class, TenantRecordPolicy::class);
        Gate::policy(TenderInvitation::class, TenantRecordPolicy::class);
        Gate::policy(Bid::class, BidPolicy::class);
        Gate::policy(BidItem::class, TenantRecordPolicy::class);
        Gate::policy(Award::class, TenantRecordPolicy::class);
        Gate::policy(AwardLine::class, TenantRecordPolicy::class);
        Gate::policy(WorkOrderLine::class, TenantRecordPolicy::class);
        Gate::policy(WorkOrder::class, TenantRecordPolicy::class);
        Gate::policy(TenderStatusHistory::class, TenantRecordPolicy::class);
        Gate::policy(AuditLog::class, TenantRecordPolicy::class);
        Gate::policy(InvitationAccessToken::class, TenantRecordPolicy::class);
        Gate::policy(WebhookEndpoint::class, TenantRecordPolicy::class);
        Gate::policy(WebhookDelivery::class, TenantRecordPolicy::class);
        Gate::policy(IdempotencyRecord::class, TenantRecordPolicy::class);
        Gate::policy(OutboxEvent::class, TenantRecordPolicy::class);
    }
}
