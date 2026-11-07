<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_users', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->foreignUlid('supplier_id')->constrained()->restrictOnDelete();
            $t->index('supplier_id');
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('active')->default(true);
            $t->rememberToken();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_users');
    }
};
