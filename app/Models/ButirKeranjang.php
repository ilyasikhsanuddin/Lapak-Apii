<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ButirKeranjang extends Model
{
    protected $table = 'butir_keranjang';

    // Harga tidak disimpan di keranjang.
    protected $fillable = [
        'produk_id',
        'kuantitas',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'kuantitas' => 'integer',
            'catatan' => 'string',
        ];
    }

    /** @return BelongsTo<Produk, $this> */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
