<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusAkun;
use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan status akun.
 *
 * Menangguhkan akun diikuti pencabutan seluruh sesinya. PemeriksaSesi memang sudah menolak akun
 * ditangguhkan, tetapi sesi yang dibiarkan hidup akan berlaku lagi begitu akun dipulihkan.
 */
final class PengelolaAkun
{
    public function __construct(private readonly PenerbitSesi $sesi) {}

    /**
     * @return bool false bila akun sudah ditangguhkan, sehingga tidak ada yang berubah
     */
    public function tangguhkan(Pengguna $pengguna): bool
    {
        if ($pengguna->status === StatusAkun::Ditangguhkan) {
            return false;
        }

        DB::transaction(function () use ($pengguna): void {
            // status tidak masuk $fillable: klien tidak boleh menentukannya, kode ini boleh.
            $pengguna->forceFill(['status' => StatusAkun::Ditangguhkan])->save();
            $this->sesi->cabutSemua($pengguna);
        });

        return true;
    }

    /**
     * @return bool false bila akun memang sudah aktif
     */
    public function pulihkan(Pengguna $pengguna): bool
    {
        if ($pengguna->status === StatusAkun::Aktif) {
            return false;
        }

        $pengguna->forceFill(['status' => StatusAkun::Aktif])->save();

        return true;
    }
}
