<?php

declare(strict_types=1);

namespace Tests\Feature\Dompet;

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\NamaPeran;
use App\Enums\StatusPencairan;
use App\Http\Middleware\SesiSegar;
use App\Models\Pencairan;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use App\Services\PengelolaPeran;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PencairanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PeranIzinSeeder::class);
        $this->withoutMiddleware([SesiSegar::class]);
    }

    private function buatPenjual(): Pengguna
    {
        $user = Pengguna::factory()->create();
        app(PengelolaPeran::class)->beri($user, NamaPeran::Penjual);

        return $user;
    }

    private function buatStafKeuangan(): Pengguna
    {
        $user = Pengguna::factory()->create();
        app(PengelolaPeran::class)->beri($user, NamaPeran::Keuangan);

        return $user;
    }

    /** Isi dompet penjual lewat jalur resmi: dana masuk dari luar → escrow → penjual. */
    private function isiDompetPenjual(Pengguna $penjual, int $rupiah): void
    {
        $buku = app(BukuBesar::class);
        $luar = $buku->dompetSistem(JenisDompet::Eksternal);
        $escrow = $buku->dompetSistem(JenisDompet::Escrow);
        $jumlah = Uang::dari($rupiah);

        $buku->pindahkan($luar, $escrow, $jumlah, JenisMutasi::DanaMasuk, 'uji');
        $buku->pindahkan($escrow, $buku->dompetPenjual($penjual), $jumlah, JenisMutasi::LepasDana, 'uji');
    }

    /** @test */
    public function user_can_submit_pencairan_request(): void
    {
        $user = $this->buatPenjual();
        $this->isiDompetPenjual($user, 500_000);
        $this->sebagai($user);

        $response = $this->postJson(route('v1.pencairan.store'), [
            'jumlah_rupiah' => 200_000,
            'rekening_tujuan' => '1234567890',
        ], [
            'Idempotency-Key' => (string) Str::uuid(), // <-- TAMBAHKAN
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('pencairan', [
            'pengaju_id' => $user->id,
            'jumlah_rupiah' => 200_000,
            'status' => StatusPencairan::Menunggu->value,
        ]);
    }

    /** @test */
    public function two_person_rule_is_enforced(): void
    {
        $pengaju = $this->buatPenjual();
        $staff1 = $this->buatStafKeuangan();
        $staff2 = $this->buatStafKeuangan();

        $this->isiDompetPenjual($pengaju, 500_000);

        $this->sebagai($pengaju);
        $this->postJson(route('v1.pencairan.store'), [
            'jumlah_rupiah' => 100_000,
            'rekening_tujuan' => '111222333',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertStatus(201);

        $pencairan = Pencairan::first();

        // Petugas pertama menyetujui
        $this->sebagai($staff1);
        $this->postJson(route('v1.pencairan.staf.setujui', $pencairan->kode), [], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        // Petugas kedua mencoba menyetujui lagi; harus dapat 409
        $this->sebagai($staff2);
        $this->postJson(route('v1.pencairan.staf.setujui', $pencairan->kode), [], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])
            ->assertStatus(409);
    }

    /** @test */
    public function validation_returns_422_for_invalid_input(): void
    {
        $user = $this->buatPenjual();
        $this->sebagai($user);

        $response = $this->postJson(route('v1.pencairan.store'), [
            'jumlah_rupiah' => -10,
            'rekening_tujuan' => '',
        ]);
        $response->assertStatus(422);
    }
}
