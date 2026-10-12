<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** accounts_api has no status column; the disable flag lives in the app DB (accounts_api is not altered). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_permissions', function (Blueprint $t) {
            $t->boolean('disabled')->default(false)->after('update_gbif');
        });
    }

    public function down(): void
    {
        Schema::table('account_permissions', fn (Blueprint $t) => $t->dropColumn('disabled'));
    }
};
