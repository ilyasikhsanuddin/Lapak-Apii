<?php

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Enums\StatusPembayaran;
use App\Models\Checkout;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Services\TandaTanganGateway;

/*
| Keadaan awal: satu checkout Rp 489.500 berisi dua pesanan (382.000 dan 107.500) yang
| menunggu pembayaran.
*/
beforeEach(function () {
    config(['lapak.gateway.rahasia' => 'rahasia-uji', 'lapak.gateway.toleransi_detik' => 300]);
    $this->checkout = Checkout::factory()->create(['total_rupiah' => 489_500]);
    $this->tagihan = Pembayaran::factory()->for($this->checkout)
        ->create(['jumlah_rupiah' => 489_500]);
    $this->batik = Pesanan::factory()->for($this->checkout)->create(['total_rupiah' => 382_000]);
    $this->oleh = Pesanan::factory()->for($this->checkout)->create(['total_rupiah' => 107_500]);

    $this->kabar = fn (array $timpa = []) => $timpa + [
        'id_peristiwa' => 'evt_01',
        'kode_pembayaran' => $this->tagihan->kode,
        'jumlah_rupiah' => 489_500,
    ];

    // Mengirim webhook seperti gerbang: badan mentah ditandatangani, lalu dikirim apa adanya.
    $this->gerbang = function (array $kabar, ?string $tanda = null) {
        $badan = json_encode($kabar);
        $tanda ??= app(TandaTanganGateway::class)->buat($badan);

        return $this->call('POST', '/api/v1/webhook/pembayaran', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GATEWAY_SIGNATURE' => $tanda,
        ], $badan);
    };

    $this->saldo = fn (JenisDompet $jenis) => (int) Dompet::query()
        ->where('kode', $jenis->value)->value('saldo_rupiah');
});

it('melunasi tagihan, menahan dana di escrow, dan menandai pesanan dibayar (LP-12)', function () {
    ($this->gerbang)(($this->kabar)())
        ->assertOk()
        ->assertJsonPath('data.hasil', 'dilunasi');

    expect($this->tagihan->refresh())
        ->status->toBe(StatusPembayaran::Lunas)
        ->id_peristiwa->toBe('evt_01')
        ->lunas_pada->not->toBeNull()
        ->and($this->batik->refresh()->status)->toBe(StatusPesanan::Dibayar)
        ->and($this->oleh->refresh()->status)->toBe(StatusPesanan::Dibayar)
        ->and(($this->saldo)(JenisDompet::Escrow))->toBe(489_500)
        ->and(($this->saldo)(JenisDompet::Eksternal))->toBe(-489_500);

    // Satu pemindahan per pesanan, dirujuk dengan nomor pesanannya.
    $mutasiBatik = MutasiSaldo::query()->where('rujukan', $this->batik->nomor);
    expect((int) (clone $mutasiBatik)->sum('jumlah_rupiah'))->toBe(0)
        ->and((clone $mutasiBatik)->count())->toBe(2)
        ->and(MutasiSaldo::count())->toBe(4);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('menerima kabar kembar tanpa membukukan uangnya dua kali', function () {
    ($this->gerbang)(($this->kabar)())->assertOk()->assertJsonPath('data.hasil', 'dilunasi');
    ($this->gerbang)(($this->kabar)())->assertOk()->assertJsonPath('data.hasil', 'sudah_diproses');

    // Gerbang mengulang dengan pengenal peristiwa baru: tetap tidak berefek.
    ($this->gerbang)(($this->kabar)(['id_peristiwa' => 'evt_02']))
        ->assertOk()
        ->assertJsonPath('data.hasil', 'sudah_diproses');

    expect(($this->saldo)(JenisDompet::Escrow))->toBe(489_500)
        ->and(MutasiSaldo::count())->toBe(4)
        ->and($this->tagihan->refresh()->id_peristiwa)->toBe('evt_01');
});

it('menolak webhook yang tanda tangannya tidak sah dengan satu jawaban yang sama', function (
    Closure $tanda,
) {
    $badan = json_encode(($this->kabar)());
    ($this->gerbang)(($this->kabar)(), $tanda($badan))
        ->assertUnauthorized()
        ->assertExactJson(['sukses' => false, 'galat' => [
            'kode' => 'TANDA_TANGAN_SALAH',
            'pesan' => 'Tanda tangan webhook tidak sah.',
            'rincian' => null,
        ]]);

    expect($this->tagihan->refresh()->status)->toBe(StatusPembayaran::Menunggu)
        ->and(MutasiSaldo::count())->toBe(0);
})->with([
    'tanpa header' => [fn (string $badan) => ''],
    'rahasia salah' => [
        fn (string $badan) => app(TandaTanganGateway::class)->buat($badan, rahasia: 'tebakan'),
    ],
    'ditandatangani untuk badan lain' => [
        fn (string $badan) => app(TandaTanganGateway::class)
            ->buat(str_replace('489500', '1', $badan)),
    ],
    'rekaman lama diputar ulang' => [
        fn (string $badan) => app(TandaTanganGateway::class)
            ->buat($badan, now()->subMinutes(6)->getTimestamp()),
    ],
    'waktu dari masa depan' => [
        fn (string $badan) => app(TandaTanganGateway::class)
            ->buat($badan, now()->addMinutes(6)->getTimestamp()),
    ],
    'bentuk header salah' => [fn (string $badan) => 'v1='.hash('sha256', $badan)],
]);

it('menolak semua webhook bila rahasia belum diisi', function () {
    config(['lapak.gateway.rahasia' => null]);
    $badan = json_encode(($this->kabar)());

    // Penyerang menandatangani dengan rahasia kosong, berharap server juga memakainya.
    $palsu = 't='.time().',v1='.hash_hmac('sha256', time().'.'.$badan, '');
    ($this->gerbang)(($this->kabar)(), $palsu)->assertUnauthorized();

    expect($this->tagihan->refresh()->status)->toBe(StatusPembayaran::Menunggu);
});

it('membatalkan seluruh pelunasan bila satu pesanan tidak dapat ditandai dibayar', function () {
    // Keadaan yang seharusnya tidak pernah terjadi: satu pesanan sudah batal, tagihannya menunggu.
    $this->oleh->forceFill(['status' => StatusPesanan::Dibatalkan])->save();

    ($this->gerbang)(($this->kabar)())->assertConflict()->assertJsonPath('galat.kode', 'LP-10');

    // Tagihan dan pesanan pertama sempat ditandai, lalu semuanya dibatalkan bersama.
    expect($this->tagihan->refresh()->status)->toBe(StatusPembayaran::Menunggu)
        ->and($this->batik->refresh()->status)->toBe(StatusPesanan::MenungguPembayaran)
        ->and(MutasiSaldo::count())->toBe(0);
});

it('tidak menyediakan pintu apa pun bagi pembeli untuk menandai tagihannya lunas', function () {
    $pembeli = $this->checkout->pembeli;
    $this->sebagai($pembeli);

    $this->getJson("/api/v1/pembayaran/{$this->tagihan->kode}")
        ->assertOk()
        ->assertJsonPath('data.pembayaran.status', 'menunggu')
        ->assertJsonPath('data.pesanan', [$this->batik->nomor, $this->oleh->nomor]);

    foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $metode) {
        $this->json($metode, "/api/v1/pembayaran/{$this->tagihan->kode}", ['status' => 'lunas'])
            ->assertStatus(405);
    }

    // Pembeli yang sah pun tidak dapat memanggil webhook tanpa rahasia gerbang.
    $this->postJson('/api/v1/webhook/pembayaran', ($this->kabar)())->assertUnauthorized();

    expect($this->tagihan->refresh()->status)->toBe(StatusPembayaran::Menunggu);
});
