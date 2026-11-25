<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $tabel) {
            $tabel->timestamp('dikemas_pada')->nullable();
            $tabel->timestamp('dikirim_pada')->nullable();
            $tabel->string('nomor_resi')->nullable();
            $tabel->timestamp('selesai_pada')->nullable();
            $tabel->string('dibatalkan_oleh', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pesanan', function (Blueprint $tabel) {
            $tabel->dropColumn([
                'dikemas_pada',
                'dikirim_pada',
                'nomor_resi',
                'selesai_pada',
                'dibatalkan_oleh',
            ]);
        });
    }
};
