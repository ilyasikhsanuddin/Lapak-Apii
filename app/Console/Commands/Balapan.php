<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Balapan\SkenarioBalapan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Process\Process;

/**
 * php artisan lapak:balapan <skenario>   atau   php artisan lapak:balapan --semua
 *
 * CATATAN: bagian awal berkas ini (signature, penolakan SQLite/produksi, muat(), daftar())
 * tidak tercetak pada modul; ada pada berkas pendamping HandsOn_Lapak_Sesi_4_Kode.zip.
 * Versi di bawah ditulis ulang mengikuti perilaku yang dijelaskan modul. Bila Anda punya
 * berkas aslinya dari zip pendamping, PAKAI ITU dan buang berkas ini.
 */
final class Balapan extends Command
{
    protected $signature = 'lapak:balapan
                            {skenario? : Nama skenario, mis. serempak atau rebutan-stok}
                            {--semua : Jalankan seluruh skenario}
                            {--proses= : Timpa jumlah proses anak}';

    protected $description = 'Melepas proses PHP sungguhan serentak untuk menguji aturan transaksi (butuh MySQL)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini menulis data percobaan dan tidak boleh berjalan di produksi.');

            return self::FAILURE;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->error(
                "Basis data saat ini SQLite, yang hanya mengizinkan satu penulis dan\n"
                .'mengabaikan lockForUpdate(). Hasilnya tidak berarti. Pakai MySQL.',
            );

            return self::FAILURE;
        }

        if ($this->option('semua')) {
            $lulus = true;
            foreach (self::daftar() as $nama) {
                // Satu skenario yang melanggar tidak menghentikan yang lain.
                $lulus = $this->jalankan($nama) && $lulus;
            }

            return $lulus ? self::SUCCESS : self::FAILURE;
        }

        $nama = (string) $this->argument('skenario');
        if ($nama === '') {
            $this->error('Sebutkan nama skenario, atau pakai --semua. Yang tersedia: '.implode(', ', self::daftar()));

            return self::FAILURE;
        }

        return $this->jalankan($nama) ? self::SUCCESS : self::FAILURE;
    }

    /** RebutanStok -> rebutan-stok */
    public static function muat(string $nama): SkenarioBalapan
    {
        $kelas = 'App\\Balapan\\'.Str::studly($nama);

        if (! class_exists($kelas) || ! is_subclass_of($kelas, SkenarioBalapan::class)) {
            throw new InvalidArgumentException("Skenario tidak dikenal: {$nama}");
        }

        // Lewat container, supaya skenario boleh meminta service pada konstruktornya.
        return app($kelas);
    }

    /** @return list<string> nama semua skenario pada app/Balapan, urut abjad */
    public static function daftar(): array
    {
        $nama = [];

        foreach (glob(app_path('Balapan/*.php')) ?: [] as $berkas) {
            $kelas = 'App\\Balapan\\'.basename($berkas, '.php');

            if (class_exists($kelas) && is_subclass_of($kelas, SkenarioBalapan::class)) {
                $nama[] = Str::kebab(class_basename($kelas));
            }
        }

        sort($nama);

        return $nama;
    }

    private function jalankan(string $nama): bool
    {
        $skenario = self::muat($nama);
        $jumlah = (int) ($this->option('proses') ?: $skenario->proses());

        $this->newLine();
        $this->line("<options=bold>{$nama}</>: {$skenario->keterangan()}");

        $konteks = $skenario->siapkan();

        // Setiap anak perlu waktu untuk menyalakan Laravel. Semuanya menunggu sampai detik ini,
        // lalu berangkat bersama. Tanpa garis mulai, anak pertama sudah selesai sebelum
        // anak terakhir lahir, dan tidak ada yang berlomba.
        $konteks['mulai'] = microtime(true) + max(2.0, $jumlah * 0.3);
        $muatan = base64_encode(json_encode($konteks, JSON_THROW_ON_ERROR));

        $anak = [];
        for ($nomor = 1; $nomor <= $jumlah; $nomor++) {
            $proses = new Process(
                [PHP_BINARY, 'artisan', 'lapak:balapan-anak', $nama, (string) $nomor, $muatan],
                base_path(),
            );
            $proses->setTimeout(120);
            $proses->start();
            $anak[] = $proses;
        }

        $hasil = [];
        foreach ($anak as $proses) {
            $proses->wait();
            $keluaran = trim($proses->getOutput());
            $hasil[] = $keluaran !== ''
                ? $keluaran
                : 'GALAT '.Str::limit(trim($proses->getErrorOutput()), 90);
        }

        $rekap = array_count_values($hasil);
        ksort($rekap);
        $this->table(
            ['Hasil', 'Jumlah proses'],
            array_map(null, array_keys($rekap), array_values($rekap)),
        );

        $pelanggaran = $skenario->periksa($konteks, $hasil);

        foreach ($pelanggaran as $pesan) {
            $this->error('PELANGGARAN: '.$pesan);
        }
        if ($pelanggaran === []) {
            $this->info("LULUS: {$jumlah} proses serentak, tidak ada aturan yang dilanggar.");
        }

        return $pelanggaran === [];
    }
}
