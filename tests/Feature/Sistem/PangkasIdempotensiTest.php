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

it('memangkas catatan yang lebih tua dari masa simpan dan menyisakan yang baru (LP-11)', function () {
    $this->freezeTime();

    $hari = config('lapak.idempotensi.simpan_hari');
    ($this->catat)('kunci-lama-00000001', now()->subDays($hari + 1));
    ($this->catat)('kunci-lama-00000002', now()->subDays(30));
    ($this->catat)('kunci-baru-00000001', now()->subDays($hari - 1));
    ($this->catat)('kunci-baru-00000002', now()->subHour());

    $this->artisan('lapak:pangkas-idempotensi')
        ->expectsOutput('2 catatan idempotensi dipangkas.')
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

it('aman dijalankan berulang dan tidak menyentuh data lain', function () {
    $this->freezeTime();

    ($this->catat)('kunci-lama-00000001', now()->subDays(9));
    Kategori::create(['nama' => 'Batik', 'slug' => Str::random(12)]);

    $this->artisan('lapak:pangkas-idempotensi')->assertSuccessful();
    $this->artisan('lapak:pangkas-idempotensi')
        ->expectsOutput('0 catatan idempotensi dipangkas.')
        ->assertSuccessful();

    expect(KunciIdempotensi::count())->toBe(0)
        ->and(Kategori::count())->toBe(1);
});

it('memperlakukan pengulangan setelah pemangkasan sebagai permintaan baru', function () {
    Route::middleware(['api', 'auth:sesi', 'idempoten'])
        ->post('/api/_uji/pangkas-idempotensi', function (Request $permintaan) {
            $kategori = Kategori::create([
                'nama' => $permintaan->input('nama'),
                'slug' => Str::random(12),
            ]);

            return response()->json(['sukses' => true, 'data' => ['id' => $kategori->id]], 201);
        });

    $kirim = fn () => $this->postJson(
        '/api/_uji/pangkas-idempotensi',
        ['nama' => 'Batik'],
        ['Idempotency-Key' => 'kunci-uji-0123456789'],
    );

    $this->sebagai($this->andi);

    $kirim()->assertCreated()->assertHeaderMissing('Idempotency-Replayed');
    $kirim()->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
    expect(Kategori::count())->toBe(1);

    KunciIdempotensi::query()->update(['created_at' => now()->subDays(8)]);
    $this->artisan('lapak:pangkas-idempotensi')->assertSuccessful();

    // Kunci yang sama setelah dipangkas dianggap permintaan baru.
    $kirim()->assertCreated()->assertHeaderMissing('Idempotency-Replayed');
    expect(Kategori::count())->toBe(2);
});
