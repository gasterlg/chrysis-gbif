<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'chrysis';

    public function up(): void
    {
        Schema::connection('chrysis')->table('gbif_import_runs', function (Blueprint $t) {
            $t->string('download_key', 64)->nullable();
            $t->string('download_doi', 128)->nullable();
            $t->string('download_status', 32)->nullable();
            $t->string('download_link')->nullable();
            $t->unsignedBigInteger('download_records')->nullable();
        });
        Schema::connection('chrysis')->table('gbif_datasets', function (Blueprint $t) {
            $t->string('publisher_key', 36)->nullable();
            $t->string('url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('chrysis')->table('gbif_import_runs', fn (Blueprint $t) => $t->dropColumn(['download_key', 'download_doi', 'download_status', 'download_link', 'download_records']));
        Schema::connection('chrysis')->table('gbif_datasets', fn (Blueprint $t) => $t->dropColumn(['publisher_key', 'url']));
    }
};
