<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KunciIdempotensi;
use Illuminate\Console\Command;

final class PangkasIdempotensi extends Command
{
    protected $signature = 'lapak:pangkas-idempotensi';

    protected $description = 'Memangkas catatan Idempotency-Key yang lebih tua dari masa simpan (LP-11)';

    public function handle(): int
    {
        $batas = now()->subDays(config('lapak.idempotensi.simpan_hari'));

        // Setelah catatannya dihapus, kunci yang sama diperlakukan sebagai permintaan BARU.
        // Masa simpan karenanya harus jauh lebih panjang daripada rentang klien mengulang
        // permintaan yang gagal, yang lazimnya hitungan menit.
        $terhapus = KunciIdempotensi::query()->where('created_at', '<', $batas)->delete();

        $this->info("{$terhapus} catatan idempotensi dipangkas.");

        return self::SUCCESS;
    }
}
