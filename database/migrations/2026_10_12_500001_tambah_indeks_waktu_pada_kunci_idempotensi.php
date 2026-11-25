<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemangkasan memilih catatan menurut umurnya. Indeks unik (pengguna_id, kunci) tidak
     * membantu kueri itu, sehingga tanpa indeks ini setiap pemangkasan memindai seluruh tabel.
     */
    public function up(): void
    {
        Schema::table('kunci_idempotensi', function (Blueprint $tabel) {
            $tabel->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('kunci_idempotensi', function (Blueprint $tabel) {
            $tabel->dropIndex(['created_at']);
        });
    }
};
