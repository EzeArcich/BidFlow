<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenders DROP CONSTRAINT tenders_check_0');
        DB::statement("ALTER TABLE tenders ADD CONSTRAINT tenders_check_0 CHECK (type IN ('parts', 'repair', 'products', 'services', 'works'))");
        DB::statement('ALTER TABLE suppliers DROP CONSTRAINT suppliers_check_0');
        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_check_0 CHECK (type IN ('parts', 'workshop', 'mixed', 'vendor', 'contractor'))");
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT organizations_check_0');
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_check_0 CHECK (type IN ('study', 'insurer', 'company'))");

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id(); // Sanctum infrastructure; token owners use ULID.
            $table->ulidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        // PostgreSQL rejects rollback if generic records still exist, preserving data.
        DB::statement('ALTER TABLE tenders DROP CONSTRAINT tenders_check_0');
        DB::statement("ALTER TABLE tenders ADD CONSTRAINT tenders_check_0 CHECK (type IN ('parts', 'repair'))");
        DB::statement('ALTER TABLE suppliers DROP CONSTRAINT suppliers_check_0');
        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_check_0 CHECK (type IN ('parts', 'workshop', 'mixed'))");
        DB::statement('ALTER TABLE organizations DROP CONSTRAINT organizations_check_0');
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_check_0 CHECK (type IN ('study', 'insurer'))");
        Schema::dropIfExists('personal_access_tokens');
    }
};
