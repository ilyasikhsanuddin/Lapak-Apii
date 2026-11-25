<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pencairan', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('kode', 31)->unique();
            $tabel->foreignId('pengaju_id')->constrained('pengguna')->restrictOnDelete();
            $tabel->unsignedBigInteger('jumlah_rupiah');
            $tabel->string('rekening_tujuan', 80);
            $tabel->string('status', 20)->index();
            $tabel->foreignId('diputuskan_oleh')->nullable()->constrained('pengguna')->restrictOnDelete();
            $tabel->timestamp('diputuskan_pada')->nullable();
            $tabel->string('catatan', 200)->nullable();
            $tabel->timestamps();
            $tabel->index(['pengaju_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencairan');
    }
};
