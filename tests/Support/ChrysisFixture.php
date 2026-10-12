<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Minimal copy of the legacy chrysis_1 tables the app reads. */
class ChrysisFixture
{
    public static function create(): void
    {
        $s = Schema::connection('chrysis');

        $s->create('_specie', function (Blueprint $t) {
            $t->integer('IDSP')->primary();
            foreach (['SUBF', 'TRIBE', 'GEN', 'SP', 'SSP', 'AUTH'] as $c) {
                $t->string($c)->default('0');
            }
        });
        $s->create('match', function (Blueprint $t) {
            $t->integer('idsp')->primary();
            $t->string('gen')->nullable();
            $t->string('sp')->nullable();
            $t->string('ssp')->nullable();
            $t->integer('gbif_id')->nullable();
            $t->string('gbif_species')->nullable();
        });
        $s->create('occurrences', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('gbif_taxon_key');
            foreach (['scientific_name', 'country', 'locality', 'state_province', 'basis_of_record', 'dataset_name', 'recorded_by', 'collection_code', 'institution_code', 'identified_by'] as $c) {
                $t->string($c)->nullable();
            }
            $t->decimal('latitude', 5, 5)->nullable();
            $t->decimal('longitude', 5, 5)->nullable();
            $t->integer('month')->nullable();
            $t->integer('year')->nullable();
        });
        $s->create('accounts_api', function (Blueprint $t) {
            $t->increments('id');
            $t->string('username', 25);
            $t->char('password', 60);
            $t->string('first_name', 50)->nullable();
            $t->string('last_name', 50)->nullable();
            $t->string('email', 100)->nullable();
            $t->string('role')->default('SuperUser');
        });

        $db = DB::connection('chrysis');
        $db->table('_specie')->insert([
            ['IDSP' => 1, 'SUBF' => 'Cleptinae', 'TRIBE' => '0', 'GEN' => 'Cleptes', 'SP' => 'aerosus', 'SSP' => '0', 'AUTH' => 'Förster, 1853'],
            ['IDSP' => 2, 'SUBF' => 'Chrysidinae', 'TRIBE' => 'Chrysidini', 'GEN' => 'Chrysis', 'SP' => 'ignita', 'SSP' => '0', 'AUTH' => '(Linnaeus, 1758)'],
            ['IDSP' => 3, 'SUBF' => 'Chrysidinae', 'TRIBE' => 'Chrysidini', 'GEN' => 'Chrysis', 'SP' => 'mediadentata', 'SSP' => 'ssp1', 'AUTH' => 'Linsenmaier, 1951'],
        ]);
        $db->table('match')->insert([
            ['idsp' => 1, 'gen' => 'Cleptes', 'sp' => 'aerosus', 'ssp' => null, 'gbif_id' => 7792353, 'gbif_species' => 'Cleptes aerosus'],
            ['idsp' => 2, 'gen' => 'Chrysis', 'sp' => 'ignita', 'ssp' => null, 'gbif_id' => 0, 'gbif_species' => null],
        ]);
        $db->table('occurrences')->insert([
            ['gbif_taxon_key' => 7792353, 'scientific_name' => 'Cleptes aerosus Förster, 1853', 'country' => 'Croatia', 'locality' => 'Učka', 'year' => 2021, 'month' => 6, 'basis_of_record' => 'PRESERVED_SPECIMEN', 'institution_code' => 'NHMUK'],
            ['gbif_taxon_key' => 7792353, 'scientific_name' => 'Cleptes aerosus Förster, 1853', 'country' => 'Hungary', 'locality' => 'Budapest', 'year' => 1890, 'month' => null, 'basis_of_record' => 'HUMAN_OBSERVATION', 'institution_code' => 'HNHM'],
            ['gbif_taxon_key' => 1111111, 'scientific_name' => 'Other species', 'country' => 'Italy', 'locality' => 'Roma', 'year' => 2000, 'month' => 1, 'basis_of_record' => 'PRESERVED_SPECIMEN', 'institution_code' => 'X'],
        ]);
        $hash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 4]);
        $db->table('accounts_api')->insert([
            ['id' => 1, 'username' => 'puzzo', 'password' => $hash, 'email' => 'user1@example.test', 'role' => 'SuperUser', 'first_name' => 'Test', 'last_name' => 'One'],
            ['id' => 2, 'username' => 'admin', 'password' => $hash, 'email' => 'user2@example.test', 'role' => 'Admin', 'first_name' => null, 'last_name' => null],
            // bcryptjs-style hash ($2a$), as written by apisa
            ['id' => 3, 'username' => 'franco', 'password' => '$2a$'.substr(password_hash('secret-2a', PASSWORD_BCRYPT, ['cost' => 4]), 4), 'email' => 'user3@example.test', 'role' => 'SuperUser', 'first_name' => null, 'last_name' => null],
        ]);
    }
}
