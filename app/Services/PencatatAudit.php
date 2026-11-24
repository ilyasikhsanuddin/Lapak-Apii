<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Audit\RantaiHash;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya pintu penulisan log audit (LP-17), seperti BukuBesar bagi saldo.
 *
 * Dua aturan pemakaian bagi seluruh domain:
 *   1. catat() dipanggil DI DALAM transaksi yang sama dengan tindakannya. Tindakan dan jejaknya
 *      tersimpan bersama atau batal bersama; tidak ada tindakan tanpa jejak, dan tidak ada jejak
 *      atas tindakan yang batal.
 *   2. catat() adalah langkah TERAKHIR sebelum transaksi selesai. Tidak ada kunci baris lain
 *      yang diambil sesudahnya (docs/PETA-SKEMA.md, anak tangga terakhir).
 */
final class PencatatAudit
{
    /** Nama kunci yang tidak boleh muncul pada rincian, di tingkat mana pun. */
    private const TERLARANG = ['kata_sandi', 'kunci', 'hash_kunci', 'rahasia', 'tanda_tangan'];

    /**
     * @param  Pengguna|null  $pelaku  null bila pelakunya sistem atau gerbang pembayaran
     * @param  string|null  $sasaran  "jenis:pengenal", misalnya "pesanan:LPK-20261027-000412"
     * @param  array<string, mixed>  $rincian  hanya teks, bilangan bulat, boolean, null, dan larik
     */
    public function catat(
        PeristiwaAudit $peristiwa,
        ?Pengguna $pelaku = null,
        ?string $sasaran = null,
        array $rincian = [],
    ): LogAudit {
        $this->tolakRahasia($rincian);

        return DB::transaction(function () use ($peristiwa, $pelaku, $sasaran, $rincian): LogAudit {
            // BELUM AMAN bila dua tindakan dicatat bersamaan: keduanya dapat membaca kepala
            // rantai yang sama. Indeks unik pada hash_sebelumnya menolak salah satunya dengan
            // galat, sehingga rantai tidak pernah bercabang diam-diam. Dikunci pada jendela B.
            $kepala = DB::table('rantai_audit')->where('id', 1)->first();

            if ($kepala === null) {
                throw new LogicException('Kepala rantai audit tidak ada. Jalankan migrasi.');
            }

            $log = new LogAudit;
            $log->forceFill([
                'peristiwa' => $peristiwa->value,
                'pelaku_id' => $pelaku?->id,
                'sasaran' => $sasaran,
                'rincian' => $rincian,
                'id_permintaan' => request()->attributes->get('id_permintaan'),
                // Tanpa pecahan detik: yang di-hash harus persis sama dengan yang tersimpan.
                'dicatat_pada' => now()->startOfSecond(),
                'hash_sebelumnya' => $kepala->hash_terakhir,
            ]);

            $log->hash = RantaiHash::hitung($kepala->hash_terakhir, $log->isiTerlindung());
            $log->save();

            // Kepala mencatat ujung rantai dan panjangnya. Tanpa catatan ini, menghapus baris
            // TERAKHIR tidak memutus sambungan apa pun dan tidak akan pernah ketahuan.
            DB::table('rantai_audit')->where('id', 1)->update([
                'hash_terakhir' => $log->hash,
                'jumlah' => $kepala->jumlah + 1,
            ]);

            return $log;
        });
    }

    /**
     * Log audit dibaca auditor dan disimpan bertahun-tahun. Rahasia yang telanjur masuk tidak
     * dapat dihapus tanpa memutus rantai, maka ditolak di pintu.
     *
     * @param  array<int|string, mixed>  $rincian
     */
    private function tolakRahasia(array $rincian): void
    {
        foreach ($rincian as $kunci => $nilai) {
            if (is_string($kunci) && in_array($kunci, self::TERLARANG, true)) {
                throw new LogicException("Rincian audit tidak boleh memuat \"{$kunci}\".");
            }

            if (is_array($nilai)) {
                $this->tolakRahasia($nilai);
            }
        }
    }
}
