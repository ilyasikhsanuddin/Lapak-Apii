<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Checkout;
use Closure;
use Illuminate\Support\Facades\DB;

final class GembokCheckout
{
    /**
     * @template T
     *
     * @param  Closure(Checkout): T  $kerja
     * @return T
     */
    public function dalam(int $idCheckout, Closure $kerja): mixed
    {
        return DB::transaction(function () use ($idCheckout, $kerja) {
            $checkout = Checkout::query()
                ->lockForUpdate()
                ->findOrFail($idCheckout);

            return $kerja($checkout);
        });
    }
}
