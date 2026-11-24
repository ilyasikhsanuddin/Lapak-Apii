<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_audit', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('peristiwa', 60)->index();
            // Kosong bila pelakunya sistem atau gerbang pembayaran.
            $tabel->foreignId('pelaku_id')->nullable()->constrained('pengguna')->restrictOnDelete();
            // Yang dikenai tindakan, misalnya "pesanan:LPK-20261027-000412" atau "pengguna:8".
            $tabel->string('sasaran', 80)->nullable()->index();
            $tabel->json('rincian');
            // Pengenal permintaan dari middleware IdPermintaan, pencocok dengan log aplikasi.
            $tabel->char('id_permintaan', 36)->nullable();
            // Setiap baris menyebut hash baris sebelumnya. unique(): dua baris tidak dapat
            // mengaku meneruskan baris yang sama, sehingga rantai tidak dapat bercabang.
            $tabel->char('hash_sebelumnya', 64)->unique();
            $tabel->char('hash', 64)->unique();
            $tabel->dateTime('dicatat_pada');
        });

        // Kepala rantai: satu baris yang selalu ada. Dialah yang dikunci setiap kali log
        // bertambah, dan dialah yang membuat pemotongan ekor rantai ketahuan.
        Schema::create('rantai_audit', function (Blueprint $tabel) {
            $tabel->unsignedTinyInteger('id')->primary();
            $tabel->char('hash_terakhir', 64);
            $tabel->unsignedBigInteger('jumlah');
        });

        // Dibuat oleh migrasi, bukan seeder: tanpa baris ini tidak ada tindakan berjejak yang dapat
        // berjalan, dan "migrate" tanpa "fresh" pada basis data sesi-4 tetap menghasilkan kepala rantai.
        DB::table('rantai_audit')->insert([
            'id' => 1, 'hash_terakhir' => str_repeat('0', 64), 'jumlah' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rantai_audit');
        Schema::dropIfExists('log_audit');
    }
};
