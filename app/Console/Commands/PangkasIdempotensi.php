<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KunciIdempotensi;
use Illuminate\Console\Command;

/**
 * LP-11: catatan kunci idempotensi hanya berguna selama klien mungkin masih mengulang permintaan
 * yang sama karena jaringan putus. Setelah itu ia hanya memenuhi tabel.
 *
 * Catatan hanya di-commit setelah kode_status dan badan_respons terisi (lihat middleware Idempoten),
 * jadi setiap baris yang sudah tua pasti baris yang selesai. Tidak ada permintaan berjalan yang
 * dapat terhapus oleh perintah ini.
 *
 * Kunci: perintah ini hanya menyentuh tabel kunci_idempotensi, tanpa mengunci baris lain, sehingga
 * tidak ada anak tangga yang dilanggar pada docs/PETA-SKEMA.md. Penghapusan dilakukan per kelompok,
 * satu transaksi singkat per kelompok, agar tidak menahan permintaan checkout yang menulis tabel
 * yang sama.
 */
final class PangkasIdempotensi extends Command
{
    public const HARI_BAWAAN = 7;

    protected $signature = 'lapak:pangkas-idempotensi
        {--hari=7 : Catatan yang lebih tua dari jumlah hari ini dihapus}
        {--batch=1000 : Jumlah baris yang dihapus dalam satu pernyataan}';

    protected $description = 'Menghapus catatan Idempotency-Key yang berumur lebih dari tujuh hari (LP-11)';

    public function handle(): int
    {
        $hari = (int) $this->option('hari');
        $batch = (int) $this->option('batch');

        if ($hari < 1 || $batch < 1) {
            $this->error('--hari dan --batch harus berupa bilangan bulat positif.');

            return self::INVALID;
        }

        // Tepat tujuh hari masih disimpan: yang dihapus adalah yang "lebih dari" tujuh hari.
        $batas = now()->subDays($hari);
        $dihapus = 0;

        do {
            // Id dipilih lebih dulu lalu dihapus menurut id: portabel di MySQL dan SQLite,
            // dan tiap putaran hanya memegang kunci atas paling banyak $batch baris.
            $id = KunciIdempotensi::query()
                ->where('created_at', '<', $batas)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id');

            if ($id->isEmpty()) {
                break;
            }

            $dihapus += KunciIdempotensi::query()->whereKey($id->all())->delete();
        } while ($id->count() === $batch);

        $this->info("{$dihapus} catatan idempotensi dipangkas (lebih tua dari {$hari} hari).");

        return self::SUCCESS;
    }
}
