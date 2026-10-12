<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New gbif_* tables live next to the legacy tables in chrysis_1 (plan section 6.2).
 * Only CREATE: the legacy `occurrences` table is never touched.
 */
return new class extends Migration
{
    protected $connection = 'chrysis';

    public function up(): void
    {
        Schema::connection('chrysis')->create('gbif_occurrences', function (Blueprint $t) {
            $t->unsignedBigInteger('gbif_id')->primary();
            $t->unsignedInteger('idsp')->nullable()->index();          // _specie row the import ran for
            $t->unsignedBigInteger('taxon_key')->nullable()->index();
            $t->unsignedBigInteger('species_key')->nullable()->index();
            $t->unsignedBigInteger('accepted_taxon_key')->nullable();
            $t->char('dataset_key', 36)->nullable()->index();
            $t->string('dataset_name')->nullable();
            $t->string('scientific_name')->nullable();
            $t->char('country_code', 2)->nullable();
            $t->string('country', 100)->nullable();
            $t->string('state_province')->nullable();
            $t->text('locality')->nullable();
            $t->decimal('latitude', 9, 6)->nullable();
            $t->decimal('longitude', 9, 6)->nullable();
            $t->unsignedInteger('coord_uncertainty_m')->nullable();
            $t->string('event_date', 64)->nullable();                  // GBIF ISO date or interval
            $t->unsignedSmallInteger('year')->nullable();
            $t->unsignedTinyInteger('month')->nullable();
            $t->string('basis_of_record', 32)->nullable();
            $t->text('recorded_by')->nullable();
            $t->text('identified_by')->nullable();
            $t->string('institution_code', 100)->nullable();
            $t->string('collection_code', 100)->nullable();
            $t->string('catalog_number', 100)->nullable();
            $t->string('type_status', 100)->nullable();
            $t->string('sex', 32)->nullable();
            $t->string('license', 100)->nullable();
            $t->unsignedSmallInteger('media_count')->default(0);
            $t->json('media')->nullable();
            $t->json('issues')->nullable();
            $t->char('content_hash', 40);
            $t->string('state', 16)->default('pending');               // pending/approved/missing/retired
            $t->unsignedBigInteger('import_run_id')->nullable();
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->index(['latitude', 'longitude']);
        });

        Schema::connection('chrysis')->create('gbif_import_runs', function (Blueprint $t) {
            $t->id();
            $t->string('type', 32)->default('occurrences');
            $t->json('scope')->nullable();                              // {idsp: [...], limit: n}
            $t->string('status', 16)->default('queued');                // queued/running/finished/failed
            $t->unsignedInteger('species_total')->default(0);
            $t->unsignedInteger('species_done')->default(0);
            $t->unsignedInteger('fetched')->default(0);
            $t->unsignedInteger('inserted')->default(0);
            $t->unsignedInteger('updated')->default(0);
            $t->unsignedInteger('unchanged')->default(0);
            $t->unsignedInteger('with_coordinates')->default(0);
            $t->json('errors')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });

        Schema::connection('chrysis')->create('gbif_datasets', function (Blueprint $t) {
            $t->char('dataset_key', 36)->primary();
            $t->string('title')->nullable();
            $t->string('doi')->nullable();
            $t->string('license', 100)->nullable();
            $t->string('publisher')->nullable();
            $t->text('citation')->nullable();
            $t->timestamp('fetched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('chrysis')->dropIfExists('gbif_datasets');
        Schema::connection('chrysis')->dropIfExists('gbif_import_runs');
        Schema::connection('chrysis')->dropIfExists('gbif_occurrences');
    }
};
