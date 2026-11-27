<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Services\PemeriksaRantaiAudit;
use App\Services\PencatatAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuditSerempak implements SkenarioBalapan
{
    public function __construct(
        private readonly PencatatAudit $pencatat,
        private readonly PemeriksaRantaiAudit $pemeriksa,
    ) {}

    public function keterangan(): string
    {
        return 'Dua belas jejak ditulis serentak: semuanya tercatat dan rantainya tidak bercabang';
    }

    public function proses(): int
    {
        return 12;
    }

    public function siapkan(): array
    {
        return [
            'sasaran' => 'balapan-'.Str::lower(Str::random(8)),
            'jumlah_awal' => (int) DB::table('rantai_audit')->where('id', 1)->value('jumlah'),
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $this->pencatat->catat(PeristiwaAudit::MasukGagal, null, $konteks['sasaran'], [
            'alasan' => 'uji_balapan', 'proses' => $nomor,
        ]);

        return 'tercatat';
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $tercatat = LogAudit::query()->where('sasaran', $konteks['sasaran'])->count();
        $jumlah = (int) DB::table('rantai_audit')->where('id', 1)->value('jumlah');
        $rantai = $this->pemeriksa->periksa();

        $pelanggaran = [];
        if ($tercatat !== count($hasil)) {
            $pelanggaran[] = "{$tercatat} jejak tersimpan dari ".count($hasil).' tindakan.';
        }
        if ($jumlah - $konteks['jumlah_awal'] !== count($hasil)) {
            $pelanggaran[] = 'Kepala rantai maju '.($jumlah - $konteks['jumlah_awal'])
                .' langkah, seharusnya '.count($hasil).'.';
        }
        foreach (array_slice($rantai->temuan, 0, 3) as $temuan) {
            $pelanggaran[] = $temuan;
        }

        return $pelanggaran;
    }
}
