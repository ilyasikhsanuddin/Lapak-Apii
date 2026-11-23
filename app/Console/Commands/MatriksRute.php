<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Mencetak setiap rute terlindung /api/v1: izin yang disyaratkannya dan peran yang memegang izin itu
 * MENURUT BASIS DATA (bukan menurut enum), sehingga berguna saat basis data dan kode dicurigai berbeda.
 */
final class MatriksRute extends Command
{
    protected $signature = 'lapak:matriks-rute';

    protected $description = 'Menampilkan setiap rute terlindung, izinnya, dan peran pemegang izin itu';

    public function handle(): int
    {
        $pemegang = $this->pemegangPerIzin();
        $baris = [];
        $tanpaPemegang = 0;

        foreach (Route::getRoutes() as $rute) {
            $middleware = $rute->gatherMiddleware();

            if (! str_starts_with($rute->uri(), 'api/v1') || ! in_array('auth:sesi', $middleware, true)) {
                continue;
            }

            $izin = $this->izinRute($middleware);

            foreach (array_diff($rute->methods(), ['HEAD']) as $metode) {
                if ($izin === null) {
                    $peran = 'siapa pun yang sudah masuk';
                } elseif (($pemegang[$izin] ?? []) === []) {
                    $peran = '!! TIDAK ADA PEMEGANG !!';
                    $tanpaPemegang++;
                } else {
                    $peran = implode(', ', $pemegang[$izin]);
                }

                $baris[] = [$metode, $rute->uri(), $izin ?? '*', $peran];
            }
        }

        usort($baris, fn (array $a, array $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

        $this->table(['metode', 'alamat', 'izin', 'peran pemegang'], $baris);
        $this->line(count($baris).' rute terlindung.');

        if ($tanpaPemegang > 0) {
            $this->warn("{$tanpaPemegang} rute memerlukan izin yang tidak dipegang peran mana pun (jalankan PeranIzinSeeder?).");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param  array<int, mixed>  $middleware */
    private function izinRute(array $middleware): ?string
    {
        foreach ($middleware as $nama) {
            if (is_string($nama) && str_starts_with($nama, 'izin:')) {
                return substr($nama, strlen('izin:'));
            }
        }

        return null;
    }

    /** @return array<string, list<string>> nama izin => nama peran, menurut tabel peran_izin */
    private function pemegangPerIzin(): array
    {
        $hasil = [];

        $data = DB::table('peran_izin')
            ->join('peran', 'peran.id', '=', 'peran_izin.peran_id')
            ->join('izin', 'izin.id', '=', 'peran_izin.izin_id')
            ->orderBy('peran.nama')
            ->get(['izin.nama as izin', 'peran.nama as peran']);

        foreach ($data as $baris) {
            $hasil[$baris->izin][] = $baris->peran;
        }

        return $hasil;
    }
}
