<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function base(Blueprint $table, bool $tenant = true): void
    {
        $table->ulid('id')->primary();
        if ($tenant) {
            $table->foreignUlid('organization_id')->constrained()->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
        }
        $table->timestampsTz();
    }

    private function ref(Blueprint $table, array $columns, string $target, array $references): void
    {
        $table->index($columns);
        $table->foreign($columns)->references($references)->on($target)->restrictOnDelete();
    }

    private function tenantRef(Blueprint $table, string $column, string $target): void
    {
        $this->ref($table, ['organization_id', $column], $target, ['organization_id', 'id']);
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Bidflow requires PostgreSQL, including in tests.');
        }

        Schema::create('organizations', function (Blueprint $t) {
            $this->base($t, false);
            $t->string('name');
            $t->string('type');
            $t->string('status')->default('active');
        });
        Schema::create('organization_memberships', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $t->string('role');
            $t->unique(['organization_id', 'user_id']);
            $t->index('user_id');
        });
        Schema::create('api_clients', function (Blueprint $t) {
            $this->base($t);
            $t->string('name');
            $t->string('token_hash', 64)->unique();
            $t->jsonb('scopes');
            $t->timestampTz('expires_at')->nullable();
            $t->timestampTz('revoked_at')->nullable();
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $this->base($t, false);
            $t->string('type');
            $t->string('legal_name');
            $t->string('trade_name');
            $t->string('tax_id')->nullable();
            $t->string('status')->default('active');
            $t->boolean('is_demo')->default(false);
        });
        Schema::create('supplier_contacts', function (Blueprint $t) {
            $this->base($t, false);
            $t->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('email');
            $t->string('phone')->nullable();
            $t->index('supplier_id');
            $t->unique(['supplier_id', 'id']);
        });
        Schema::create('supplier_locations', function (Blueprint $t) {
            $this->base($t, false);
            $t->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $t->string('address');
            $t->string('city');
            $t->string('province');
            $t->char('country', 2)->default('AR');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->jsonb('coverage')->nullable();
            $t->string('availability')->nullable();
            $t->index('supplier_id');
        });
        Schema::create('supplier_capabilities', function (Blueprint $t) {
            $this->base($t, false);
            $t->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $t->string('capability');
            $t->unique(['supplier_id', 'capability']);
        });
        Schema::create('organization_suppliers', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('active');
            $t->text('notes')->nullable();
            $t->unique(['organization_id', 'supplier_id']);
            $t->index('supplier_id');
        });
        Schema::create('tenders', function (Blueprint $t) {
            $this->base($t);
            $t->string('type');
            $t->string('status')->default('draft');
            $t->string('title');
            $t->text('description')->nullable();
            $t->char('currency', 3)->default('ARS');
            $t->timestampTz('deadline_at');
            $t->jsonb('conditions')->nullable();
            $t->string('source_system')->nullable();
            $t->string('external_claim_id')->nullable();
            $t->string('external_reference')->nullable();
            $t->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $t->boolean('is_demo')->default(false);
            $t->index('created_by');
            $t->index(['organization_id', 'status', 'deadline_at']);
            $t->index(['organization_id', 'source_system', 'external_claim_id']);
            $t->unique(['organization_id', 'id', 'type']);
            $t->unique(['organization_id', 'id', 'currency']);
        });
        Schema::create('tender_items', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('tender_id');
            $t->unsignedInteger('position');
            $t->text('description');
            $t->decimal('quantity', 12, 3);
            $t->string('unit');
            $t->text('notes')->nullable();
            $t->string('reference_code')->nullable();
            $t->string('manufacturer')->nullable();
            $t->jsonb('attributes')->nullable();
            $this->tenantRef($t, 'tender_id', 'tenders');
            $t->unique(['tender_id', 'position']);
            $t->unique(['organization_id', 'tender_id', 'id']);
        });
        Schema::create('repair_scopes', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('tender_id')->unique();
            $t->string('tender_type')->default('repair');
            $t->jsonb('vehicle');
            $t->text('damage_description');
            $t->jsonb('required_tasks')->nullable();
            $t->jsonb('location');
            $t->text('notes')->nullable();
            $this->ref($t, ['organization_id', 'tender_id', 'tender_type'], 'tenders', ['organization_id', 'id', 'type']);
        });
        Schema::create('tender_attachments', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('tender_id');
            $t->ulid('tender_item_id')->nullable();
            $t->string('disk')->default('local');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime_type');
            $t->bigInteger('size');
            $this->tenantRef($t, 'tender_id', 'tenders');
            $this->ref($t, ['organization_id', 'tender_id', 'tender_item_id'], 'tender_items', ['organization_id', 'tender_id', 'id']);
        });
        Schema::create('tender_invitations', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('tender_id');
            $t->ulid('supplier_id');
            $t->ulid('contact_id')->nullable();
            $t->string('status')->default('pending');
            $t->timestampTz('expires_at');
            $t->timestampTz('sent_at')->nullable();
            $t->timestampTz('viewed_at')->nullable();
            $t->timestampTz('responded_at')->nullable();
            $this->tenantRef($t, 'tender_id', 'tenders');
            $this->ref($t, ['organization_id', 'supplier_id'], 'organization_suppliers', ['organization_id', 'supplier_id']);
            $this->ref($t, ['supplier_id', 'contact_id'], 'supplier_contacts', ['supplier_id', 'id']);
            $t->unique(['tender_id', 'supplier_id']);
            $t->unique(['organization_id', 'tender_id', 'supplier_id', 'id'], 'invitations_bid_identity');
        });
        Schema::create('bids', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('tender_id');
            $t->ulid('supplier_id');
            $t->ulid('invitation_id');
            $t->string('status')->default('draft');
            $t->char('currency', 3)->default('ARS');
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->integer('delivery_days')->nullable();
            $t->text('warranty')->nullable();
            $t->text('conditions')->nullable();
            $t->text('notes')->nullable();
            $t->timestampTz('submitted_at')->nullable();
            $this->ref($t, ['organization_id', 'tender_id', 'supplier_id', 'invitation_id'], 'tender_invitations', ['organization_id', 'tender_id', 'supplier_id', 'id']);
            $this->ref($t, ['organization_id', 'tender_id', 'currency'], 'tenders', ['organization_id', 'id', 'currency']);
            $t->unique(['tender_id', 'supplier_id']);
            $t->unique(['organization_id', 'tender_id', 'id']);
            $t->index('supplier_id');
            $t->index('invitation_id');
        });
        Schema::create('bid_items', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('tender_id');
            $t->ulid('bid_id');
            $t->ulid('tender_item_id');
            $t->text('description');
            $t->decimal('quantity', 12, 3);
            $t->decimal('unit_price', 15, 2);
            $t->decimal('subtotal', 15, 2);
            $t->text('notes')->nullable();
            $this->ref($t, ['organization_id', 'tender_id', 'bid_id'], 'bids', ['organization_id', 'tender_id', 'id']);
            $this->ref($t, ['organization_id', 'tender_id', 'tender_item_id'], 'tender_items', ['organization_id', 'tender_id', 'id']);
            $t->unique(['bid_id', 'tender_item_id']);
            $t->index('tender_item_id');
        });
        Schema::create('awards', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('tender_id')->unique();
            $t->ulid('bid_id')->unique();
            $t->foreignUlid('awarded_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('awarded_at');
            $t->text('reason');
            $t->jsonb('snapshot');
            $this->ref($t, ['organization_id', 'tender_id', 'bid_id'], 'bids', ['organization_id', 'tender_id', 'id']);
            $t->index('awarded_by');
        });
        Schema::create('work_orders', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('award_id')->unique();
            $t->string('number');
            $t->string('status')->default('issued');
            $t->timestampTz('expected_at')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->text('notes')->nullable();
            $this->tenantRef($t, 'award_id', 'awards');
            $t->unique(['organization_id', 'number']);
        });
        Schema::create('tender_status_histories', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('tender_id');
            $t->foreignUlid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('reason')->nullable();
            $t->jsonb('metadata')->nullable();
            $t->timestampTz('occurred_at');
            $this->tenantRef($t, 'tender_id', 'tenders');
            $t->index('actor_id');
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $this->base($t);
            $t->foreignUlid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->string('entity_type');
            $t->ulid('entity_id');
            $t->jsonb('before')->nullable();
            $t->jsonb('after')->nullable();
            $t->string('origin');
            $t->ipAddress('ip')->nullable();
            $t->timestampTz('occurred_at');
            $t->index(['organization_id', 'entity_type', 'entity_id']);
            $t->index('actor_id');
        });
        Schema::create('invitation_access_tokens', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('invitation_id');
            $t->string('token_hash', 64)->unique();
            $t->timestampTz('expires_at');
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampTz('last_used_at')->nullable();
            $this->tenantRef($t, 'invitation_id', 'tender_invitations');
        });
        Schema::create('webhook_endpoints', function (Blueprint $t) {
            $this->base($t);
            $t->text('url');
            $t->jsonb('events');
            $t->text('secret');
            $t->boolean('enabled')->default(true);
        });
        Schema::create('outbox_events', function (Blueprint $t) {
            $this->base($t);
            $t->string('event_type');
            $t->ulid('resource_id');
            $t->jsonb('payload');
            $t->timestampTz('occurred_at');
            $t->timestampTz('processed_at')->nullable()->index();
        });
        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('webhook_endpoint_id');
            $t->ulid('outbox_event_id');
            $t->integer('attempts')->default(0);
            $t->integer('response_status')->nullable();
            $t->text('response_body')->nullable();
            $t->timestampTz('next_attempt_at')->nullable()->index();
            $t->timestampTz('delivered_at')->nullable();
            $this->tenantRef($t, 'webhook_endpoint_id', 'webhook_endpoints');
            $this->tenantRef($t, 'outbox_event_id', 'outbox_events');
            $t->unique(['webhook_endpoint_id', 'outbox_event_id']);
        });
        Schema::create('idempotency_records', function (Blueprint $t) {
            $this->base($t);
            $t->ulid('api_client_id');
            $t->string('operation');
            $t->string('key');
            $t->string('request_hash', 64);
            $t->string('status')->default('processing');
            $t->integer('response_status')->nullable();
            $t->jsonb('response_body')->nullable();
            $t->timestampTz('expires_at')->index();
            $this->tenantRef($t, 'api_client_id', 'api_clients');
            $t->unique(['organization_id', 'api_client_id', 'operation', 'key'], 'idempotency_operation_key');
        });

        $checks = [
            'organizations' => ["type IN ('study', 'insurer')", "status IN ('active', 'inactive')"],
            'organization_memberships' => ["role IN ('owner', 'admin', 'operator', 'viewer')"],
            'suppliers' => ["type IN ('parts', 'workshop', 'mixed')", "status IN ('active', 'inactive')"],
            'organization_suppliers' => ["status IN ('active', 'inactive')"],
            'supplier_locations' => ['latitude BETWEEN -90 AND 90', 'longitude BETWEEN -180 AND 180'],
            'tenders' => ["type IN ('parts', 'repair')", "status IN ('draft', 'published', 'closed', 'awarded', 'completed', 'cancelled', 'expired')", "currency = 'ARS'"],
            'tender_items' => ['quantity > 0', 'position > 0'],
            'repair_scopes' => ["tender_type = 'repair'"],
            'tender_attachments' => ['size >= 0', "disk = 'local'"],
            'tender_invitations' => ["status IN ('pending', 'sent', 'viewed', 'responded', 'declined', 'expired', 'revoked')"],
            'bids' => ["status IN ('draft', 'submitted', 'accepted', 'rejected', 'withdrawn')", 'total_amount >= 0', 'delivery_days >= 0'],
            'bid_items' => ['quantity > 0', 'unit_price >= 0', 'subtotal >= 0', 'subtotal = round(quantity * unit_price, 2)'],
            'work_orders' => ["status IN ('issued', 'accepted', 'in_progress', 'completed', 'cancelled', 'disputed')"],
            'webhook_endpoints' => ["url ~ '^https://[^/[:space:]]+'"],
            'webhook_deliveries' => ['attempts >= 0', 'response_status BETWEEN 100 AND 599'],
            'idempotency_records' => ["status IN ('processing', 'completed')"],
        ];
        foreach ($checks as $table => $expressions) {
            foreach ($expressions as $index => $expression) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_check_{$index} CHECK ({$expression})");
            }
        }
    }

    public function down(): void
    {
        foreach (['idempotency_records', 'webhook_deliveries', 'outbox_events', 'webhook_endpoints', 'invitation_access_tokens', 'audit_logs', 'tender_status_histories', 'work_orders', 'awards', 'bid_items', 'bids', 'tender_invitations', 'tender_attachments', 'repair_scopes', 'tender_items', 'tenders', 'organization_suppliers', 'supplier_capabilities', 'supplier_locations', 'supplier_contacts', 'suppliers', 'api_clients', 'organization_memberships', 'organizations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
