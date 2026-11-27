<?php

use App\Domain\Audit\RantaiHash;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Services\PemeriksaRantaiAudit;
use App\Services\PencatatAudit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Setiap uji merusak log lewat DB::table, persis seperti orang yang memegang akses basis data
| (model menolak update dan delete), lalu menuntut pemeriksa menyebut barisnya.
*/

beforeEach(function () {
    $pencatat = app(PencatatAudit::class);
    $admin = Pengguna::factory()->admin()->create();

    $this->id = [];
    foreach (range(1, 5) as $n) {
        $this->id[] = $pencatat->catat(PeristiwaAudit::TokoDisetujui, $admin, "toko:{$n}", ['urutan' => $n])->id;
    }

    $this->pemeriksa = app(PemeriksaRantaiAudit::class);

    // Jangkar yang "dicatat di tempat lain" pada saat rantai masih sehat.
    $this->jangkar = $this->pemeriksa->periksa()->jangkar();
});

it('menyatakan rantai yang tidak disentuh utuh, dan jangkarnya berbentuk jumlah:hash', function () {
    $hasil = $this->pemeriksa->periksa();

    expect($hasil->utuh())->toBeTrue()
        ->and($hasil->jumlah)->toBe(5)
        ->and($hasil->jangkar())->toMatch('/^5:[0-9a-f]{64}$/')
        ->and($this->pemeriksa->periksa($this->jangkar)->utuh())->toBeTrue();
});

it('menemukan baris yang isinya diubah, dan menyebut barisnya', function () {
    DB::table('log_audit')->where('id', $this->id[2])->update(['sasaran' => 'toko:999']);

    $hasil = $this->pemeriksa->periksa();

    expect($hasil->utuh())->toBeFalse()
        ->and($hasil->temuan)->toHaveCount(1)
        ->and($hasil->temuan[0])->toContain("Baris {$this->id[2]} tidak cocok dengan hash-nya");
});

it('menemukan baris yang dihapus di tengah', function () {
    DB::table('log_audit')->where('id', $this->id[2])->delete();

    $hasil = $this->pemeriksa->periksa();

    expect($hasil->utuh())->toBeFalse()
        ->and(implode("\n", $hasil->temuan))
        ->toContain("Baris {$this->id[3]} tidak menyambung ke baris sebelumnya");
});

it('menemukan ekor yang dipotong, walau sambungan antarbaris masih utuh', function () {
    DB::table('log_audit')->where('id', $this->id[4])->delete();

    $hasil = $this->pemeriksa->periksa();

    expect($hasil->temuan)->toHaveCount(1)
        ->and($hasil->temuan[0])->toContain('Kepala rantai mencatat 5 baris, log berisi 4');
});

it('menemukan baris yang ditulis tanpa melalui PencatatAudit', function () {
    $ekor = LogAudit::query()->orderByDesc('id')->first();

    // Penyisip yang teliti: ia menyambung ke ekor dan menghitung hash-nya dengan benar.
    $palsu = new LogAudit;
    $palsu->forceFill([
        'peristiwa' => 'peran.diberikan', 'pelaku_id' => null, 'sasaran' => 'pengguna:66',
        'rincian' => ['peran' => 'admin'], 'dicatat_pada' => now()->startOfSecond(),
        'hash_sebelumnya' => $ekor->hash,
    ]);
    $palsu->hash = RantaiHash::hitung($ekor->hash, $palsu->isiTerlindung());
    $palsu->save();

    $hasil = $this->pemeriksa->periksa();

    expect($hasil->temuan)->toHaveCount(1)
        ->and($hasil->temuan[0])->toContain('Kepala rantai mencatat 5 baris, log berisi 6');
});

it('tidak membiarkan dua baris meneruskan baris yang sama', function () {
    // Baris ke-4 sudah meneruskan baris ke-3. Baris lain yang mengaku meneruskan baris ke-3
    // juga ditolak oleh indeks unik pada hash_sebelumnya, apa pun isinya.
    $induk = DB::table('log_audit')->where('id', $this->id[3])->value('hash_sebelumnya');

    expect(fn () => DB::table('log_audit')->insert([
        'peristiwa' => 'toko.disetujui', 'pelaku_id' => null, 'sasaran' => 'toko:7',
        'rincian' => '[]', 'id_permintaan' => null,
        'hash_sebelumnya' => $induk, 'hash' => str_repeat('a', 64),
        'dicatat_pada' => now()->startOfSecond()->format('Y-m-d H:i:s'),
    ]))->toThrow(QueryException::class);

    expect($this->pemeriksa->periksa()->utuh())->toBeTrue();
});

it('menemukan rantai yang dihitung ulang seluruhnya oleh pemegang basis data', function () {
    // Serangan terkuat: ubah satu baris, lalu hitung ulang SEMUA hash sesudahnya dan
    // perbarui kepala rantai. Dari dalam, log ini konsisten sempurna.
    DB::table('log_audit')->where('id', $this->id[1])->update(['sasaran' => 'pengguna:99']);

    $sebelumnya = DB::table('log_audit')->where('id', $this->id[0])->value('hash');
    foreach (array_slice($this->id, 1) as $id) {
        DB::table('log_audit')->where('id', $id)->update([
            'hash_sebelumnya' => "sementara-{$id}", 'hash' => "sementara-{$id}",
        ]);
    }
    foreach (array_slice($this->id, 1) as $id) {
        $log = LogAudit::query()->findOrFail($id);
        $hash = RantaiHash::hitung($sebelumnya, $log->isiTerlindung());
        DB::table('log_audit')->where('id', $id)->update([
            'hash_sebelumnya' => $sebelumnya, 'hash' => $hash,
        ]);
        $sebelumnya = $hash;
    }
    DB::table('rantai_audit')->update(['hash_terakhir' => $sebelumnya]);

    // Tanpa jangkar: lolos. Inilah batas rantai hash yang berdiri sendiri.
    expect($this->pemeriksa->periksa()->utuh())->toBeTrue();

    // Dengan jangkar yang disimpan di luar: ketahuan.
    $hasil = $this->pemeriksa->periksa($this->jangkar);

    expect($hasil->utuh())->toBeFalse()
        ->and($hasil->temuan[0])->toContain('Baris ke-5 tidak cocok dengan jangkar');
});

it('menolak jangkar yang bentuknya salah', function () {
    expect(fn () => $this->pemeriksa->periksa('bukan-jangkar'))
        ->toThrow(InvalidArgumentException::class, 'Jangkar harus berbentuk');
});

it('memeriksa jangkar yang dicatat sebelum baris pertama', function () {
    expect($this->pemeriksa->periksa('0:'.RantaiHash::awal())->utuh())->toBeTrue()
        ->and($this->pemeriksa->periksa('0:'.str_repeat('a', 64))->temuan)
        ->toBe(['Jangkar kosong tidak cocok dengan hash awal rantai.']);
});

it('melaporkan isi yang tidak bisa dihitung sebagai kerusakan rantai', function () {
    DB::table('log_audit')->where('id', $this->id[2])->update(['rincian' => '{"urutan":3.5}']);

    $this->artisan('lapak:verifikasi-audit')
        ->expectsOutputToContain("Baris {$this->id[2]} tidak cocok dengan hash-nya")
        ->assertExitCode(1);
});

it('mengeluarkan kode 1 dan menyebut baris yang rusak lewat perintah artisan', function () {
    DB::table('log_audit')->where('id', $this->id[2])->update(['sasaran' => 'toko:999']);

    $this->artisan('lapak:verifikasi-audit')
        ->expectsOutputToContain("Baris {$this->id[2]} tidak cocok dengan hash-nya")
        ->assertExitCode(1);
});

it('mengeluarkan kode 0 pada rantai utuh dan mencetak jangkarnya', function () {
    $this->artisan('lapak:verifikasi-audit')
        ->expectsOutputToContain('Rantai log audit utuh: 5 baris.')
        ->assertExitCode(0);
});
