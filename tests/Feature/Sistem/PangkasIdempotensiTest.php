<?php

use App\Models\Kategori;
use App\Models\KunciIdempotensi;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
| Membuat catatan kunci dengan umur tertentu, tanpa melewati middleware.
*/
beforeEach(function () {
    $this->andi = Pengguna::factory()->create();

    $this->catat = function (string $kunci, DateTimeInterface $umur): KunciIdempotensi {
        $catatan = new KunciIdempotensi;
        $catatan->forceFill([
            'pengguna_id' => $this->andi->id,
            'kunci' => $kunci,
            'rute' => 'POST api/v1/checkout',
            'sidik' => hash('sha256', $kunci),
            'kode_status' => 201,
            'badan_respons' => '{"sukses":true}',
            'created_at' => $umur,
        ])->save();

        return $catatan;
    };
});

it('menghapus catatan yang lebih tua dari tujuh hari dan menyisakan yang lebih baru', function () {
    $this->freezeTime();

    ($this->catat)('kunci-lama-00000001', now()->subDays(8));
    ($this->catat)('kunci-lama-00000002', now()->subDays(30));
    ($this->catat)('kunci-baru-00000001', now()->subDays(6));
    ($this->catat)('kunci-baru-00000002', now()->subHour());

    $this->artisan('lapak:pangkas-idempotensi')
        ->expectsOutputToContain('2 catatan idempotensi dipangkas')
        ->assertSuccessful();

    expect(KunciIdempotensi::orderBy('id')->pluck('kunci')->all())
        ->toBe(['kunci-baru-00000001', 'kunci-baru-00000002']);
});

it('menyimpan catatan yang umurnya tepat tujuh hari', function () {
    $this->freezeTime();

    ($this->catat)('kunci-batas-0000001', now()->subDays(7));
    ($this->catat)('kunci-lewat-0000001', now()->subDays(7)->subSecond());

    $this->artisan('lapak:pangkas-idempotensi')->assertSuccessful();

    expect(KunciIdempotensi::pluck('kunci')->all())->toBe(['kunci-batas-0000001']);
});

it('menghapus seluruh catatan lama walau jumlahnya melebihi satu kelompok', function () {
    $this->freezeTime();

    foreach (range(1, 5) as $ke) {
        ($this->catat)(sprintf('kunci-lama-%08d', $ke), now()->subDays(10));
    }
    ($this->catat)('kunci-baru-00000001', now()->subDay());

    $this->artisan('lapak:pangkas-idempotensi --batch=2')
        ->expectsOutputToContain('5 catatan idempotensi dipangkas')
        ->assertSuccessful();

    expect(KunciIdempotensi::pluck('kunci')->all())->toBe(['kunci-baru-00000001']);
});

it('aman dijalankan berulang dan tidak menyentuh data lain', function () {
    $this->freezeTime();

    ($this->catat)('kunci-lama-00000001', now()->subDays(9));
    Kategori::create(['nama' => 'Batik', 'slug' => Str::random(12)]);

    $this->artisan('lapak:pangkas-idempotensi')->assertSuccessful();
    $this->artisan('lapak:pangkas-idempotensi')
        ->expectsOutputToContain('0 catatan idempotensi dipangkas')
        ->assertSuccessful();

    expect(KunciIdempotensi::count())->toBe(0)
        ->and(Kategori::count())->toBe(1);
});

it('menolak --hari dan --batch yang bukan bilangan positif', function (string $opsi) {
    $this->artisan("lapak:pangkas-idempotensi {$opsi}")->assertFailed();
})->with(['--hari=0', '--hari=-3', '--batch=0']);

it('memperlakukan pengulangan setelah pemangkasan sebagai permintaan baru', function () {
    // Pintu uji: setiap pemanggilan yang berhasil membuat satu kategori.
    Route::middleware(['api', 'auth:sesi', 'idempoten'])
        ->post('/api/_uji/pangkas', function (Request $permintaan) {
            $kategori = Kategori::create(['nama' => $permintaan->input('nama'), 'slug' => Str::random(12)]);

            return response()->json(['sukses' => true, 'data' => ['id' => $kategori->id]], 201);
        });

    $kirim = fn () => $this->postJson(
        '/api/_uji/pangkas', ['nama' => 'Batik'], ['Idempotency-Key' => 'kunci-uji-0123456789'],
    );

    $this->sebagai($this->andi);

    $kirim()->assertCreated()->assertHeaderMissing('Idempotency-Replayed');
    $kirim()->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
    expect(Kategori::count())->toBe(1);

    // Delapan hari berlalu, lalu pemangkasan berjalan. Waktu catatan digeser langsung agar sesi
    // uji tidak ikut kedaluwarsa.
    KunciIdempotensi::query()->update(['created_at' => now()->subDays(8)]);
    $this->artisan('lapak:pangkas-idempotensi')->assertSuccessful();

    // Kunci yang sama kini dianggap niat baru: efeknya terjadi lagi, tanpa header ulangan.
    $kirim()->assertCreated()->assertHeaderMissing('Idempotency-Replayed');
    expect(Kategori::count())->toBe(2);
});
