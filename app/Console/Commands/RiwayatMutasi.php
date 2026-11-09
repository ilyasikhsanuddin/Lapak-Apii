<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Dompet;
use Illuminate\Console\Command;

final class RiwayatMutasi extends Command
{
    protected $signature = 'lapak:mutasi {kode : Kode dompet yang ingin dilihat mutasinya}';

    protected $description = 'Menampilkan riwayat mutasi sebuah dompet beserta saldo berjalan (Latihan Mandiri Dimas).';

    public function handle(): int
    {
        $kode = (string) $this->argument('kode');
        $dompet = Dompet::query()->where('kode', $kode)->first();

        if ($dompet === null) {
            $this->error("Dompet dengan kode '{$kode}' tidak ditemukan.");

            return self::FAILURE;
        }

        $mutasiList = $dompet->mutasi()->orderBy('id')->get();

        if ($mutasiList->isEmpty()) {
            $this->info("Belum ada mutasi untuk dompet '{$kode}'. Saldo saat ini: Rp ".number_format($dompet->saldo_rupiah, 0, ',', '.'));

            return self::SUCCESS;
        }

        $baris = [];
        $saldoBerjalan = 0;

        foreach ($mutasiList as $m) {
            $saldoBerjalan += $m->jumlah_rupiah;
            $baris[] = [
                $m->id,
                $m->created_at?->toIso8601String() ?? '-',
                $m->jenis->value,
                number_format($m->jumlah_rupiah, 0, ',', '.'),
                number_format($saldoBerjalan, 0, ',', '.'),
                $m->rujukan,
                $m->keterangan ?? '-',
            ];
        }

        $this->info("Riwayat Mutasi Dompet: {$kode} (Saldo Tersimpan: Rp ".number_format($dompet->saldo_rupiah, 0, ',', '.').')');
        $this->table(['ID', 'Waktu', 'Jenis', 'Jumlah (Rp)', 'Saldo Berjalan (Rp)', 'Rujukan', 'Keterangan'], $baris);

        if ($saldoBerjalan === $dompet->saldo_rupiah) {
            $this->info('Verifikasi saldo berjalan cocok dengan saldo_rupiah: Rp '.number_format($saldoBerjalan, 0, ',', '.'));
        } else {
            $this->error("Invarian terlanggar! Saldo berjalan (Rp {$saldoBerjalan}) != Saldo tersimpan (Rp {$dompet->saldo_rupiah}).");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
