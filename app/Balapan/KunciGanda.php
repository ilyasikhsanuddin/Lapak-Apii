<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Enums\NamaPeran;
use App\Models\Alamat;
use App\Models\Checkout;
use App\Models\Kategori;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananKeranjang;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Illuminate\Support\Str;

/**
 * Satu pembeli menekan bayar dari banyak proses dengan Idempotency-Key yang SAMA.
 * Aturannya (LP-11): tepat satu checkout lahir, tepat satu proses merasa menjadi yang pertama,
 * dan keenamnya menerima checkout yang sama.
 */
final class KunciGanda implements SkenarioBalapan
{
    use PanggilHttp;

    private const STOK_AWAL = 50;

    private const KUANTITAS = 2;

    public function keterangan(): string
    {
        return 'Checkout yang sama diulang serentak dengan satu Idempotency-Key: efeknya satu kali';
    }

    public function proses(): int
    {
        return 6;
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));

        $pembeli = new Pengguna([
            'nama' => 'Pembeli Kunci Ganda',
            'surel' => "kunci-ganda-{$akhiran}@lapak.test",
            'kata_sandi' => Str::random(24),
        ]);
        $pembeli->save();
        app(PengelolaPeran::class)->beri($pembeli, NamaPeran::Pembeli);

        // Kunci sesi diterbitkan SETELAH peran diberikan: pemberian peran mencabut sesi lama.
        [, $kunciSesi] = app(PenerbitSesi::class)->terbitkan($pembeli, 'Uji balapan');

        $alamat = Alamat::factory()->for($pembeli, 'pengguna')->create();

        $toko = Toko::factory()->create(['slug' => "toko-{$akhiran}"]);
        $produk = Produk::factory()->for($toko)->for(Kategori::query()->firstOrFail())->create([
            'slug' => "produk-{$akhiran}",
            'stok' => self::STOK_AWAL,
        ]);

        app(LayananKeranjang::class)->aturButir($pembeli, $produk->id, self::KUANTITAS);

        return [
            'pembeli' => $pembeli->id,
            'alamat' => $alamat->id,
            'produk' => $produk->id,
            'kunci_sesi' => $kunciSesi,
            // Satu niat, satu kunci: seluruh proses memakai nilai yang sama.
            'kunci_idempotensi' => 'kunci-ganda-'.Str::random(20),
        ];
    }

    public function jalankan(array $konteks, int $nomor): string
    {
        [$status, $json, $header] = $this->panggil(
            'POST',
            '/api/v1/checkout',
            ['alamat_id' => $konteks['alamat']],
            [
                'Authorization' => 'Bearer '.$konteks['kunci_sesi'],
                'Idempotency-Key' => $konteks['kunci_idempotensi'],
            ],
        );

        if ($status !== 201) {
            return $this->ringkas($status, $json);
        }

        $jenis = isset($header['idempotency-replayed']) ? 'ulangan' : 'pertama';

        // Kode checkout ikut dicatat: semua proses harus menerima checkout YANG SAMA.
        return "201 {$jenis} ".substr($json['data']['kode'], -6);
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $checkout = Checkout::query()->where('pembeli_id', $konteks['pembeli'])->count();
        $stok = Produk::query()->findOrFail($konteks['produk'])->stok;

        $pertama = count(array_filter(
            $hasil,
            fn (string $h) => str_starts_with($h, '201 pertama'),
        ));
        $kode = array_unique(array_map(fn (string $h) => substr($h, -6), $hasil));

        $pelanggaran = [];

        if ($checkout !== 1) {
            $pelanggaran[] = "Lahir {$checkout} checkout dari satu Idempotency-Key, seharusnya 1.";
        }

        if ($pertama !== 1) {
            $pelanggaran[] = "{$pertama} proses merasa menjadi yang pertama, seharusnya tepat 1.";
        }

        if (count($kode) !== 1) {
            $pelanggaran[] = 'Proses yang berbeda menerima jawaban yang berbeda.';
        }

        if ($stok !== self::STOK_AWAL - self::KUANTITAS) {
            $terjual = self::STOK_AWAL - $stok;
            $pelanggaran[] = "Stok berkurang {$terjual}, padahal keranjang hanya berisi ".self::KUANTITAS.'.';
        }

        return $pelanggaran;
    }
}
