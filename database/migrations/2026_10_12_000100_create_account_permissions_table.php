<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id')->unique(); // chrysis_1.accounts_api.id (no FK: other DB)
            $table->boolean('update_gbif')->default(false);
            $table->timestamps();
        });

        Schema::create('gbif_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id')->nullable()->index();
            $table->string('username', 100)->nullable();
            $table->string('action', 64)->index();
            $table->string('target')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbif_audit_log');
        Schema::dropIfExists('account_permissions');
    }
};
