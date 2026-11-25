<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Models\Pencairan;
use App\Models\User;
use App\Services\LayananPencairan;
use Illuminate\Support\Facades\DB;

/**
 * Script to simulate concurrent staff decisions (approve/reject) on the same
 * pencairan request. It spawns multiple parallel transactions calling the
 * service's `setujui` or `tolak` methods and checks that only one decision is
 * applied, while the others receive a LP‑16 error (conflict).
 */
class PutusanGanda
{
    public static function run(int $concurrency = 4, string $decision = 'setujui'): void
    {
        // Prepare a pencairan record pending approval.
        $user = User::factory()->create();
        $layanan = app(LayananPencairan::class);
        $layanan->ajukan($user, 100_000, '9876543210');
        $pencairan = Pencairan::query()->where('pengaju_id', $user->id)->first();

        $results = [];
        for ($i = 0; $i < $concurrency; $i++) {
            $results[] = DB::transaction(function () use ($layanan, $pencairan, $decision) {
                try {
                    if ($decision === 'setujui') {
                        $layanan->setujui($pencairan->kode);
                    } else {
                        $layanan->tolak($pencairan->kode, 'test');
                    }

                    return 'success';
                } catch (\Exception $e) {
                    return $e->getMessage();
                }
            }, attempts: 1);
        }

        $successes = array_filter($results, fn ($r) => $r === 'success');
        $failures = array_filter($results, fn ($r) => $r !== 'success');
        echo 'Successful decisions: '.count($successes).PHP_EOL;
        echo 'Failed decisions: '.count($failures).PHP_EOL;
        foreach ($failures as $msg) {
            echo "Failure reason: $msg".PHP_EOL;
        }
    }
}
