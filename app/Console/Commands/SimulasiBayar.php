<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Balapan\PanggilHttp;
use App\Models\Pembayaran;
use App\Services\TandaTanganGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class SimulasiBayar extends Command
{
    use PanggilHttp;

    protected $signature = 'lapak:simulasi-bayar
                            {kode : Kode pembayaran}
                            {--jumlah= : Jumlah rupiah yang dibayar (bawaan: sesuai tagihan)}
                            {--ganda : Kirim kabar kembar dua kali}
                            {--rahasia= : Rahasia penanda tangan alternatif}';

    protected $description = 'Mensimulasikan gerbang pembayaran dengan mengirim webhook bertanda tangan';

    public function handle(TandaTanganGateway $tandaTangan): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini tidak boleh dijalankan di lingkungan produksi.');

            return self::FAILURE;
        }

        $kode = (string) $this->argument('kode');
        $tagihan = Pembayaran::query()->where('kode', $kode)->first();

        if ($tagihan === null) {
            $this->error("Tagihan dengan kode {$kode} tidak ditemukan.");

            return self::FAILURE;
        }

        $jumlahRupiah = $this->option('jumlah') !== null
            ? (int) $this->option('jumlah')
            : $tagihan->jumlah_rupiah;

        $rahasia = $this->option('rahasia') !== null
            ? (string) $this->option('rahasia')
            : null;

        $kabar = [
            'id_peristiwa' => 'evt_simulasi_'.Str::lower(Str::random(12)),
            'kode_pembayaran' => $kode,
            'jumlah_rupiah' => $jumlahRupiah,
        ];

        $badan = json_encode($kabar, JSON_THROW_ON_ERROR);
        $tanda = $tandaTangan->buat($badan, null, $rahasia);

        $kali = $this->option('ganda') ? 2 : 1;

        for ($i = 1; $i <= $kali; $i++) {
            [$status, $json] = $this->panggil(
                'POST',
                '/api/v1/webhook/pembayaran',
                $kabar,
                [TandaTanganGateway::HEADER => $tanda],
            );

            $this->line("Kiriman ke-{$i}: HTTP {$status}");
            $this->line(json_encode($json, JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
