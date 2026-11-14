<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('butir_keranjang', function (Blueprint $tabel) {
            $tabel->string('catatan', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('butir_keranjang', function (Blueprint $tabel) {
            $tabel->dropColumn('catatan');
        });
    }
};
