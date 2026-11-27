<?php

use App\Enums\NamaPeran;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Services\PengelolaPeran;

beforeEach(function () {
    $this->sri = Pengguna::factory()->create([
        'surel' => 'sri@lapak.test', 'kata_sandi' => 'BatikSolo2026',
    ]);

    $this->jejak = fn (string $peristiwa) => LogAudit::query()->where('peristiwa', $peristiwa);
});

it('mencatat masuk yang gagal tanpa menyimpan kata sandi yang dicoba', function () {
    $this->postJson('/api/v1/masuk', [
        'surel' => 'sri@lapak.test', 'kata_sandi' => 'BatikSolo2025', 'nama_perangkat' => 'Laptop',
    ])->assertUnauthorized();

    $jejak = ($this->jejak)('masuk.gagal')->sole();

    // Tanpa pelaku: kata sandi yang salah tidak membuktikan siapa pun.
    expect($jejak->pelaku_id)->toBeNull()
        ->and($jejak->rincian)->toEqual([
            'alasan' => 'kredensial_salah', 'surel' => 'sri@lapak.test',
        ])
        ->and(json_encode(LogAudit::query()->get()))->not->toContain('BatikSolo2025');
});

it('tidak mencatat permintaan masuk yang gugur di validasi', function () {
    // Orang kadang mengetik kata sandi di kolom surel. Isian itu tidak boleh sampai ke log.
    $this->postJson('/api/v1/masuk', [
        'surel' => 'BatikSolo2026', 'kata_sandi' => 'BatikSolo2026', 'nama_perangkat' => 'Laptop',
    ])->assertUnprocessable();

    expect(LogAudit::query()->count())->toBe(0);
});

it('mencatat masuk yang berhasil dengan pelaku dan perangkatnya', function () {
    $this->postJson('/api/v1/masuk', [
        'surel' => 'sri@lapak.test', 'kata_sandi' => 'BatikSolo2026', 'nama_perangkat' => 'Laptop',
    ])->assertCreated();

    $jejak = ($this->jejak)(PeristiwaAudit::MasukBerhasil->value)->sole();

    expect($jejak->pelaku_id)->toBe($this->sri->id)
        ->and($jejak->sasaran)->toStartWith('sesi:')
        ->and($jejak->rincian)->toBe(['perangkat' => 'Laptop']);
});

it('mencatat masuk ke akun ditangguhkan dengan pelaku, karena kata sandinya sudah benar', function () {
    $this->sri->forceFill(['status' => 'ditangguhkan'])->save();

    $this->postJson('/api/v1/masuk', [
        'surel' => 'sri@lapak.test', 'kata_sandi' => 'BatikSolo2026', 'nama_perangkat' => 'Laptop',
    ])->assertForbidden();

    $jejak = ($this->jejak)(PeristiwaAudit::MasukGagal->value)->sole();

    expect($jejak->pelaku_id)->toBe($this->sri->id)
        ->and($jejak->rincian)->toEqual([
            'alasan' => 'akun_ditangguhkan', 'surel' => 'sri@lapak.test',
        ]);
});

it('mencatat pemberian dan pencabutan peran beserta pelakunya', function () {
    $admin = Pengguna::factory()->admin()->create();
    $pengelola = app(PengelolaPeran::class);

    $pengelola->beri($this->sri, NamaPeran::Auditor, $admin);
    $pengelola->cabut($this->sri, NamaPeran::Auditor, $admin);

    $diberikan = ($this->jejak)(PeristiwaAudit::PeranDiberikan->value)->sole();
    $dicabut = ($this->jejak)(PeristiwaAudit::PeranDicabut->value)->sole();

    expect($diberikan->pelaku_id)->toBe($admin->id)
        ->and($diberikan->sasaran)->toBe("pengguna:{$this->sri->id}")
        ->and($diberikan->rincian)->toBe(['peran' => 'auditor'])
        ->and($dicabut->pelaku_id)->toBe($admin->id)
        ->and($dicabut->rincian)->toBe(['peran' => 'auditor']);
});

it('tidak mencatat apa pun bila peran tidak berubah', function () {
    $pengelola = app(PengelolaPeran::class);

    $pengelola->beri($this->sri, NamaPeran::Auditor);
    $pengelola->beri($this->sri, NamaPeran::Auditor);   // sudah dipegang
    $pengelola->cabut($this->sri, NamaPeran::Admin);    // tidak dipegang

    expect(LogAudit::query()->count())->toBe(1);
});

it('membatalkan perubahan peran bila jejaknya gagal ditulis', function () {
    LogAudit::creating(fn () => throw new RuntimeException('Tabel log audit bermasalah.'));

    expect(fn () => app(PengelolaPeran::class)->beri($this->sri, NamaPeran::Auditor))
        ->toThrow(RuntimeException::class);

    // Tindakan dan jejaknya satu transaksi: tidak ada peran tanpa catatan.
    expect($this->sri->punyaPeran(NamaPeran::Auditor))->toBeFalse();
});
