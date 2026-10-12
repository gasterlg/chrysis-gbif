<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'chrysis';

    public function up(): void
    {
        $s = Schema::connection('chrysis');

        // One current GBIF match proposal per _specie row; `match` is only changed on approval.
        $s->create('gbif_match_proposals', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('idsp')->unique();
            $t->string('query_name');
            $t->unsignedBigInteger('current_gbif_id')->nullable();   // match.gbif_id when proposed
            $t->unsignedBigInteger('usage_key')->nullable();
            $t->string('usage_name')->nullable();
            $t->string('rank', 32)->nullable();
            $t->string('taxon_status', 32)->nullable();               // ACCEPTED / SYNONYM / DOUBTFUL…
            $t->unsignedBigInteger('accepted_key')->nullable();
            $t->string('accepted_name')->nullable();
            $t->string('match_type', 16);                             // EXACT / FUZZY / HIGHERRANK / NONE / VARIANT
            $t->unsignedTinyInteger('confidence')->nullable();
            $t->unsignedBigInteger('proposed_key')->nullable();       // what accepting would write to match.gbif_id
            $t->string('proposed_name')->nullable();
            $t->json('alternatives')->nullable();
            $t->string('note')->nullable();
            $t->string('status', 16)->default('pending')->index();   // pending / same / accepted / rejected / rolled_back
            $t->unsignedBigInteger('applied_key')->nullable();
            $t->unsignedInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->unsignedBigInteger('change_id')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->timestamps();
        });

        // Before/after snapshots of every write to a legacy table, for rollback.
        $s->create('gbif_changes', function (Blueprint $t) {
            $t->id();
            $t->string('entity', 32);                                 // match
            $t->string('ref', 64)->index();                           // idsp
            $t->json('before')->nullable();                           // null = row did not exist
            $t->json('after')->nullable();
            $t->string('reason')->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->string('username', 100)->nullable();
            $t->timestamp('rolled_back_at')->nullable();
            $t->unsignedInteger('rolled_back_by')->nullable();
            $t->timestamps();
        });

        $s->table('gbif_occurrences', function (Blueprint $t) {
            $t->string('change_type', 16)->default('new');            // new / changed / unchanged
            $t->json('flags')->nullable();                            // ["far_from_range","rare_country",…]
            $t->unsignedInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->index(['idsp', 'state']);
        });
    }

    public function down(): void
    {
        $s = Schema::connection('chrysis');
        $s->table('gbif_occurrences', function (Blueprint $t) {
            $t->dropIndex(['idsp', 'state']);
            $t->dropColumn(['change_type', 'flags', 'reviewed_by', 'reviewed_at']);
        });
        $s->dropIfExists('gbif_changes');
        $s->dropIfExists('gbif_match_proposals');
    }
};
