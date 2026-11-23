<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->char('kode', 26)->unique();
            $tabel->foreignId('pembeli_id')->constrained('pengguna')->restrictOnDelete();
            $tabel->unsignedBigInteger('total_rupiah');
            $tabel->char('pengenal_sesi', 26);
            $tabel->timestamps();
        });

        Schema::create('pesanan', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('checkout_id')->constrained('checkout')->restrictOnDelete();
            $tabel->string('nomor', 24)->nullable()->unique();
            $tabel->foreignId('pembeli_id')->constrained('pengguna')->restrictOnDelete();
            $tabel->foreignId('toko_id')->constrained('toko')->restrictOnDelete();
            $tabel->string('status', 20)->index();
            $tabel->unsignedBigInteger('subtotal_rupiah');
            $tabel->unsignedBigInteger('ongkir_rupiah');
            $tabel->unsignedBigInteger('total_rupiah');
            $tabel->json('alamat_kirim');
            $tabel->timestamp('dibayar_pada')->nullable();
            $tabel->timestamp('dibatalkan_pada')->nullable();
            $tabel->string('alasan_batal', 120)->nullable();
            $tabel->timestamps();
            $tabel->index(['pembeli_id', 'id']);
        });

        Schema::create('butir_pesanan', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $tabel->foreignId('produk_id')->constrained('produk')->restrictOnDelete();
            $tabel->string('nama_produk', 120);
            $tabel->unsignedBigInteger('harga_rupiah');
            $tabel->unsignedInteger('kuantitas');
            $tabel->unsignedBigInteger('subtotal_rupiah');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('butir_pesanan');
        Schema::dropIfExists('pesanan');
        Schema::dropIfExists('checkout');
    }
};
