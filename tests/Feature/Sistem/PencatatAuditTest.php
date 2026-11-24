<?php

use App\Domain\Audit\RantaiHash;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Services\PencatatAudit;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->pencatat = app(PencatatAudit::class);
    $this->admin = Pengguna::factory()->admin()->create();
});

it('menghasilkan hash yang dapat dihitung ulang dari baris yang tersimpan', function () {
    $this->pencatat->catat(PeristiwaAudit::PencairanDisetujui, $this->admin, 'pencairan:3', [
        'jumlah_rupiah' => 80_000, 'pengaju' => 4, 'catatan' => 'Sesuai mutasi — lunas',
    ]);

    // Dibaca ulang dari basis data: urutan kunci JSON boleh berubah, hash harus tetap cocok.
    $tersimpan = LogAudit::query()->sole();

    expect(RantaiHash::hitung($tersimpan->hash_sebelumnya, $tersimpan->isiTerlindung()))
        ->toBe($tersimpan->hash);
});

it('menyambung baris pertama ke hash awal dan baris berikutnya ke hash sebelumnya', function () {
    $satu = $this->pencatat->catat(PeristiwaAudit::TokoDisetujui, $this->admin, 'toko:1');
    $dua = $this->pencatat->catat(PeristiwaAudit::TokoDitangguhkan, $this->admin, 'toko:1');

    expect($satu->hash_sebelumnya)->toBe(RantaiHash::awal())
        ->and($dua->hash_sebelumnya)->toBe($satu->hash);

    $kepala = DB::table('rantai_audit')->where('id', 1)->first();

    expect((int) $kepala->jumlah)->toBe(2)
        ->and($kepala->hash_terakhir)->toBe($dua->hash);
});

it('mengosongkan pelaku bila tindakannya dilakukan sistem', function () {
    $log = $this->pencatat->catat(PeristiwaAudit::DanaDilepas, null, 'pesanan:LPK-1');

    expect($log->fresh()->pelaku_id)->toBeNull();
});

it('menolak rincian yang memuat rahasia, di tingkat mana pun', function (array $rincian) {
    expect(fn () => $this->pencatat->catat(PeristiwaAudit::MasukGagal, null, null, $rincian))
        ->toThrow(LogicException::class, 'tidak boleh memuat');

    expect(LogAudit::count())->toBe(0);
})->with([
    'kata sandi' => [['surel' => 'a@b.c', 'kata_sandi' => 'rahasia123']],
    'kunci sesi' => [['kunci' => 'lpk_...']],
    'bersarang' => [['permintaan' => ['badan' => ['tanda_tangan' => 'abc']]]],
]);

it('tidak dapat diubah maupun dihapus lewat model', function () {
    $log = $this->pencatat->catat(PeristiwaAudit::TokoDisetujui, $this->admin, 'toko:4');

    expect(fn () => $log->forceFill(['sasaran' => 'toko:5'])->save())
        ->toThrow(LogicException::class, 'LP-17');
    expect(fn () => $log->delete())->toThrow(LogicException::class, 'LP-17');

    expect(LogAudit::sole()->sasaran)->toBe('toko:4');
});

it('ikut batal bila transaksi tindakannya batal', function () {
    try {
        DB::transaction(function () {
            $this->pencatat->catat(PeristiwaAudit::DanaDilepas, null, 'pesanan:LPK-1');

            throw new RuntimeException('tindakan gagal setelah dicatat');
        });
    } catch (RuntimeException) {
        // memang diharapkan
    }

    expect(LogAudit::count())->toBe(0);

    // Kepala rantai ikut kembali: panjangnya tidak maju untuk tindakan yang batal.
    expect((int) DB::table('rantai_audit')->where('id', 1)->value('jumlah'))->toBe(0);
});

it('menolak pecahan pada rincian', function () {
    expect(fn () => $this->pencatat->catat(PeristiwaAudit::PencairanDiajukan, $this->admin, 'pencairan:1', [
        'jumlah_rupiah' => 80000.5,
    ]))->toThrow(InvalidArgumentException::class);

    expect(LogAudit::count())->toBe(0);
});
