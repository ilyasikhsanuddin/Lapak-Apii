<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\PeristiwaAudit;
use App\Enums\StatusPembayaran;
use App\Exceptions\GalatDomain;
use App\Models\Checkout;
use App\Models\Pembayaran;
use Illuminate\Support\Str;

/**
 * Satu-satunya pintu perubahan tabel pembayaran.
 * buat() dan tandaiKedaluwarsa() dipanggil dari dalam transaksi pemanggil; terima() membuka
 * GembokCheckout sendiri.
 */
final class LayananPembayaran
{
    public function __construct(
        private readonly GembokCheckout $gembok,
        private readonly BukuBesar $buku,
        private readonly MesinStatusPesanan $mesin,
        private readonly PencatatAudit $audit,
    ) {}

    /** LP-7: tagihan sebesar total checkout, dengan batas bayar menurut LP-10. */
    public function buat(Checkout $checkout): Pembayaran
    {
        $pembayaran = new Pembayaran;
        $pembayaran->forceFill([
            'checkout_id' => $checkout->id,
            'kode' => 'BYR-'.Str::ulid(),
            // Jumlahnya disalin dari checkout, tidak pernah dari klien (LP-6).
            'jumlah_rupiah' => $checkout->total_rupiah,
            'status' => StatusPembayaran::Menunggu,
            'batas_bayar_pada' => now()->addHours(config('lapak.batas_bayar_jam')),
        ])->save();

        return $pembayaran;
    }

    public function untuk(Checkout $checkout): Pembayaran
    {
        return Pembayaran::query()->where('checkout_id', $checkout->id)->firstOrFail();
    }

    /**
     * Dipanggil pembatalan otomatis, di dalam GembokCheckout.
     *
     * @return bool false bila tagihan sudah lunas, sudah kedaluwarsa, atau belum lewat batas
     */
    public function tandaiKedaluwarsa(Checkout $checkout): bool
    {
        // Dibaca ulang dengan kunci: di antara penjadwal memilih tagihan ini dan gembok didapat,
        // pembayarannya bisa saja sudah masuk.
        $pembayaran = Pembayaran::query()
            ->where('checkout_id', $checkout->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($pembayaran->status !== StatusPembayaran::Menunggu
            || $pembayaran->batas_bayar_pada->isFuture()) {
            return false;
        }

        $pembayaran->forceFill(['status' => StatusPembayaran::Kedaluwarsa])->save();

        return true;
    }

    /**
     * Dipanggil saat pembeli membatalkan checkout yang belum dibayar, di dalam GembokCheckout.
     *
     * @return bool false bila tagihan sudah tidak menunggu, misalnya pembayarannya baru saja masuk
     */
    public function tandaiDibatalkan(Checkout $checkout): bool
    {
        $pembayaran = Pembayaran::query()
            ->where('checkout_id', $checkout->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($pembayaran->status !== StatusPembayaran::Menunggu) {
            return false;
        }

        $pembayaran->forceFill(['status' => StatusPembayaran::Dibatalkan])->save();

        return true;
    }

    /**
     * gerbang pembayaran mengirim ulang webhook yang jawabannya tidak sampai kepadanya.
     *
     * @return string "dilunasi" bila kabar ini yang melunasi, "sudah_diproses" bila sudah lunas,
     *                "dikembalikan" bila uangnya tiba setelah tagihan ditutup
     */
    public function terima(string $kode, int $jumlahRupiah, string $idPeristiwa): string
    {
        // Dicari TANPA kunci, hanya untuk mengetahui checkout mana yang harus digembok.
        $ditemukan = Pembayaran::query()->where('kode', $kode)->firstOrFail();

        return $this->gembok->dalam($ditemukan->checkout_id, function (Checkout $checkout) use (
            $ditemukan, $jumlahRupiah, $idPeristiwa,
        ): string {
            // Di bawah gembok, statusnya dibaca ulang dengan kunci. Dua webhook kembar antre di
            // gembok; yang kedua mendapati tagihannya sudah lunas.
            $pembayaran = Pembayaran::query()
                ->whereKey($ditemukan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pembayaran->status === StatusPembayaran::Lunas) {
                return 'sudah_diproses';
            }

            if ($pembayaran->status === StatusPembayaran::Dikembalikan) {
                return 'sudah_dikembalikan';
            }

            // Tagihan sudah ditutup, tetapi gerbang menyatakan uangnya masuk. Menjawab 409 tidak
            // mengembalikan uang siapa pun: uang itu nyata dan harus tercatat perjalanannya.
            if ($pembayaran->status !== StatusPembayaran::Menunggu) {
                return $this->kembalikanTerlambat($pembayaran, $jumlahRupiah, $idPeristiwa);
            }

            // Jumlah dibandingkan dengan tagihan di basis data. Kabar "sudah dibayar" untuk
            // jumlah yang lebih kecil tidak melunasi apa pun (LP-6).
            if ($pembayaran->jumlah_rupiah !== $jumlahRupiah) {
                throw new GalatDomain(
                    'JUMLAH_TIDAK_COCOK', 'Jumlah yang dibayar tidak sama dengan tagihan.', 422,
                );
            }

            $pembayaran->forceFill([
                'status' => StatusPembayaran::Lunas,
                'lunas_pada' => now(),
                'id_peristiwa' => $idPeristiwa,
            ])->save();

            $this->tahanDana($checkout, $pembayaran);

            $sasaran = "pembayaran:{$pembayaran->kode}";
            $this->audit->catat(PeristiwaAudit::PembayaranLunas, null, $sasaran, [
                'jumlah_rupiah' => $jumlahRupiah,
                'id_peristiwa' => $idPeristiwa,
                'pesanan' => $checkout->pesanan()->orderBy('id')->pluck('nomor')->all(),
            ]);

            return 'dilunasi';
        });
    }

    /**
     * Uang yang tiba terlambat dibukukan MASUK lalu KELUAR lagi, bukan diabaikan. Buku besar
     * mencatat apa yang terjadi pada uang, termasuk uang yang tidak jadi dipakai. Yang
     * dikembalikan adalah jumlah yang benar-benar diterima gerbang, berapa pun tagihannya.
     */
    private function kembalikanTerlambat(
        Pembayaran $pembayaran,
        int $jumlahRupiah,
        string $idPeristiwa,
    ): string {
        $asal = $pembayaran->status;
        $pembayaran->forceFill([
            'status' => StatusPembayaran::Dikembalikan,
            'id_peristiwa' => $idPeristiwa,
        ])->save();

        $luar = $this->buku->dompetSistem(JenisDompet::Eksternal);
        $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);
        $jumlah = Uang::dari($jumlahRupiah);

        $this->buku->pindahkan(
            $luar, $escrow, $jumlah, JenisMutasi::DanaMasuk, $pembayaran->kode,
            'Pembayaran tiba setelah tagihan ditutup',
        );
        $this->buku->pindahkan(
            $escrow, $luar, $jumlah, JenisMutasi::Pengembalian, $pembayaran->kode,
            'Pengembalian pembayaran terlambat',
        );

        $sasaran = "pembayaran:{$pembayaran->kode}";
        $this->audit->catat(PeristiwaAudit::PembayaranDikembalikan, null, $sasaran, [
            'jumlah_rupiah' => $jumlahRupiah,
            'id_peristiwa' => $idPeristiwa,
            'status_tagihan' => $asal->value,
        ]);

        return 'dikembalikan';
    }

    /** LP-12: uang pembeli masuk escrow, satu pemindahan per pesanan. Pesanan ditandai dibayar. */
    private function tahanDana(Checkout $checkout, Pembayaran $pembayaran): void
    {
        $luar = $this->buku->dompetSistem(JenisDompet::Eksternal);
        $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);
        $semua = $checkout->pesanan()->orderBy('id')->get();

        // Urutan kunci tim (docs/PETA-SKEMA.md): SELURUH pesanan dikunci dan ditandai lebih dulu,
        // barulah dompet. Hanya SISTEM yang boleh menandai pesanan dibayar; tidak ada pintu
        // pembeli untuk ini.
        foreach ($semua as $pesanan) {
            $this->mesin->pindahkan($pesanan, StatusPesanan::Dibayar, PelakuPesanan::Sistem);
        }

        foreach ($semua as $pesanan) {
            $this->buku->pindahkan(
                $luar,
                $escrow,
                Uang::dari($pesanan->total_rupiah),
                JenisMutasi::DanaMasuk,
                $pesanan->nomor,
                "Pembayaran {$pembayaran->kode}",
            );
        }
    }
}
