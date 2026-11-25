<?php

namespace Tests;

use App\Enums\NamaPeran;
use App\Models\Pengguna;
use App\Models\Sesi;
use App\Services\PenerbitSesi;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Peran dan izin adalah data acuan, bukan data contoh: tanpa keduanya aplikasi tidak dapat
     * memutuskan apa pun. Setiap uji yang memakai basis data memulainya dengan tabel itu terisi.
     */
    protected $seed = true;

    protected $seeder = PeranIzinSeeder::class;

    /** Sesi yang diterbitkan pemanggilan sebagai() terakhir. */
    protected ?Sesi $sesiUji = null;

    /**
     * Masuk sebagai seorang pengguna dengan kunci sesi SUNGGUHAN.
     * Uji tidak memakai jalan pintas actingAs: setiap permintaan melewati guard yang sama
     * dengan yang dilewati klien, sehingga guard ikut teruji di setiap uji.
     */
    protected function sebagai(Pengguna $pengguna, bool $segar = true): static
    {
        [$this->sesiUji, $kunci] = app(PenerbitSesi::class)->terbitkan($pengguna, 'Perangkat uji');

        if (! $segar) {
            $this->sesiUji->forceFill(['dikonfirmasi_pada' => now()->subHour()])->save();
        }

        return $this->denganKunci($kunci);
    }

    protected function denganKunci(string $kunci): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$kunci);
    }

    protected function tanpaKunci(): static
    {
        return $this->withoutHeader('Authorization');
    }

    /**
     * Gabungan izin bawaan beberapa peran, urut abjad seperti PemeriksaIzin::daftar().
     * Uji yang membandingkan daftar izin memakai ini, bukan menulis ulang daftarnya: menambah
     * izin pada sebuah peran tidak boleh memaksa uji domain lain ikut disunting.
     *
     * @return list<string>
     */
    protected function izinGabungan(NamaPeran ...$peran): array
    {
        $nama = [];

        foreach ($peran as $satu) {
            foreach ($satu->izinBawaan() as $izin) {
                $nama[$izin->value] = true;
            }
        }

        ksort($nama);

        return array_keys($nama);
    }

    /**
     * Di produksi setiap permintaan memulai aplikasi baru. Di dalam uji, satu aplikasi melayani
     * seluruh permintaan sebuah uji, dan guard mengingat pengguna dari permintaan sebelumnya.
     * Guard dilupakan sebelum setiap permintaan supaya kunci benar-benar diperiksa ulang.
     */
    public function call(
        $method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null,
    ) {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
