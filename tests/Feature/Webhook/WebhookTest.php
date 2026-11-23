<?php

use App\Enums\JenisDompet;
use App\Enums\StatusPembayaran;
use App\Models\Checkout;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Services\TandaTanganGateway;

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

it('memvalidasi tanda tangan webhook: menolak tanda tangan tidak sah dengan 401', function () {
    $badan = ($this->kabar)();

    // Tanpa header
    $this->postJson('/api/v1/webhook/pembayaran', $badan)
        ->assertUnauthorized()
        ->assertJsonPath('galat.kode', 'TANDA_TANGAN_SALAH');

    // Tanda tangan salah
    ($this->gerbang)($badan, 't='.time().',v1=salah')
        ->assertUnauthorized()
        ->assertJsonPath('galat.kode', 'TANDA_TANGAN_SALAH');

    // Kedaluwarsa (lebih dari 5 menit)
    $tandaBasi = app(TandaTanganGateway::class)->buat(json_encode($badan), now()->subMinutes(6)->getTimestamp());
    ($this->gerbang)($badan, $tandaBasi)
        ->assertUnauthorized()
        ->assertJsonPath('galat.kode', 'TANDA_TANGAN_SALAH');
});

it('memvalidasi jumlah pembayaran dengan tagihan: selisih satu rupiah ditolak 422', function () {
    $kabarKurang = ($this->kabar)(['jumlah_rupiah' => 489_499]);

    ($this->gerbang)($kabarKurang)
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'JUMLAH_TIDAK_COCOK');

    expect($this->tagihan->refresh()->status)->toBe(StatusPembayaran::Menunggu)
        ->and(MutasiSaldo::count())->toBe(0);
});

it('menjamin idempotensi webhook: kabar kembar dijawab sudah_diproses tanpa efek ganda', function () {
    // Kiriman pertama: berhasil dilunasi
    ($this->gerbang)(($this->kabar)())
        ->assertOk()
        ->assertJsonPath('data.hasil', 'dilunasi');

    expect($this->tagihan->refresh()->status)->toBe(StatusPembayaran::Lunas)
        ->and(($this->saldo)(JenisDompet::Escrow))->toBe(489_500)
        ->and(MutasiSaldo::count())->toBe(4);

    // Kiriman kedua (kabar kembar): dijawab sudah_diproses, dana tidak dibukukan lagi
    ($this->gerbang)(($this->kabar)())
        ->assertOk()
        ->assertJsonPath('data.hasil', 'sudah_diproses');

    // Kiriman ketiga dengan id_peristiwa baru tetap diabaikan karena status sudah lunas
    ($this->gerbang)(($this->kabar)(['id_peristiwa' => 'evt_02']))
        ->assertOk()
        ->assertJsonPath('data.hasil', 'sudah_diproses');

    expect(($this->saldo)(JenisDompet::Escrow))->toBe(489_500)
        ->and(MutasiSaldo::count())->toBe(4);
});
