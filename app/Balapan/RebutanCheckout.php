<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Enums\NamaPeran;
use App\Models\Alamat;
use App\Models\Checkout;
use App\Models\Kategori;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananKeranjang;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Illuminate\Support\Str;

/**
 * rebutan-stok yang dijalankan lewat seluruh lapisan HTTP: dua belas pembeli berbeda, masing-masing
 * dengan sesi, keranjang, alamat, dan Idempotency-Key sendiri, berebut lima barang terakhir.
 *
 * Yang diperiksa bukan hanya stok: jumlah checkout, pesanan, dan tagihan harus sama dengan
 * jumlah jawaban 201, sehingga tidak ada checkout setengah jadi milik pembeli yang ditolak.
 */
final class RebutanCheckout implements SkenarioBalapan
{
    use PanggilHttp;

    private const STOK_AWAL = 5;

    public function keterangan(): string
    {
        return 'Dua belas pembeli checkout serentak lewat HTTP untuk '.self::STOK_AWAL.' barang';
    }

    public function proses(): int
    {
        return 12;
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));

        $toko = Toko::factory()->create(['slug' => "toko-{$akhiran}"]);
        $produk = Produk::factory()->for($toko)->for(Kategori::query()->firstOrFail())->create([
            'slug' => "produk-{$akhiran}",
            'stok' => self::STOK_AWAL,
        ]);

        $pembeli = [];

        foreach (range(1, $this->proses()) as $ke) {
            $akun = new Pengguna([
                'nama' => "Pembeli Rebutan {$ke}",
                'surel' => "rebutan-{$akhiran}-{$ke}@lapak.test",
                'kata_sandi' => Str::random(24),
            ]);
            $akun->save();
            app(PengelolaPeran::class)->beri($akun, NamaPeran::Pembeli);

            // Kunci sesi diterbitkan SETELAH peran diberikan: pemberian peran mencabut sesi lama.
            [, $kunciSesi] = app(PenerbitSesi::class)->terbitkan($akun, 'Uji balapan');

            $alamat = Alamat::factory()->for($akun, 'pengguna')->create();
            app(LayananKeranjang::class)->aturButir($akun, $produk->id, 1);

            $pembeli[] = ['id' => $akun->id, 'alamat' => $alamat->id, 'kunci' => $kunciSesi];
        }

        return ['produk' => $produk->id, 'pembeli' => $pembeli];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        $saya = $konteks['pembeli'][$nomor - 1];

        [$status, $json] = $this->panggil(
            'POST',
            '/api/v1/checkout',
            ['alamat_id' => $saya['alamat']],
            [
                'Authorization' => 'Bearer '.$saya['kunci'],
                'Idempotency-Key' => "rebutan-{$nomor}-".Str::random(16),
            ],
        );

        return $this->ringkas($status, $json);
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $berhasil = count(array_filter($hasil, fn (string $h) => $h === '201'));
        $ditolak = count(array_filter($hasil, fn (string $h) => $h === '409 LP-4'));

        $idPembeli = array_column($konteks['pembeli'], 'id');
        $checkout = Checkout::query()->whereIn('pembeli_id', $idPembeli)->count();
        $pesanan = Pesanan::query()->whereIn('pembeli_id', $idPembeli)->count();
        $tagihan = Pembayaran::query()
            ->whereHas('checkout', fn ($kueri) => $kueri->whereIn('pembeli_id', $idPembeli))
            ->count();
        $stok = Produk::query()->findOrFail($konteks['produk'])->stok;

        $awal = self::STOK_AWAL;
        $pelanggaran = [];

        if ($berhasil !== $awal) {
            $pelanggaran[] = "{$berhasil} checkout berhasil, seharusnya tepat {$awal}.";
        }

        if ($berhasil + $ditolak !== count($hasil)) {
            $pelanggaran[] = 'Ada jawaban selain 201 dan 409 LP-4, misalnya 500 karena kebuntuan.';
        }

        foreach (['checkout' => $checkout, 'pesanan' => $pesanan, 'tagihan' => $tagihan] as $nama => $jumlah) {
            if ($jumlah !== $berhasil) {
                $pelanggaran[] = "Tercatat {$jumlah} {$nama}, padahal {$berhasil} jawaban 201 (ada sisa checkout setengah jadi).";
            }
        }

        if ($stok !== 0) {
            $pelanggaran[] = "Stok akhir {$stok}, seharusnya 0.";
        }

        return $pelanggaran;
    }
}
