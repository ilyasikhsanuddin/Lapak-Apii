<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi', function (Blueprint $tabel) {
            // Hash kunci yang digantikan rotasi, disimpan satu generasi (LP-14).
            $tabel->char('hash_kunci_lama', 64)->nullable()->after('hash_kunci');
        });
    }

    public function down(): void
    {
        Schema::table('sesi', function (Blueprint $tabel) {
            $tabel->dropColumn('hash_kunci_lama');
        });
    }
};
