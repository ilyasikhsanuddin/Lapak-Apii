<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pesanan\PenomorPesanan;
use App\Domain\Pesanan\StatusPesanan;
use Database\Factories\PesananFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Pesanan extends Model
{
    /** @use HasFactory<PesananFactory> */
    use HasFactory;

    protected $table = 'pesanan';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => StatusPesanan::class,
            'subtotal_rupiah' => 'integer',
            'ongkir_rupiah' => 'integer',
            'total_rupiah' => 'integer',
            'alamat_kirim' => 'array',
            'dibayar_pada' => 'datetime',
            'dikemas_pada' => 'datetime',
            'dikirim_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::created(function (Pesanan $pesanan): void {
            $pesanan->forceFill([
                'nomor' => PenomorPesanan::untuk(
                    $pesanan->id,
                    $pesanan->created_at,
                ),
            ])->saveQuietly();
        });
    }

    /** @return BelongsTo<Checkout, $this> */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class, 'checkout_id');
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pembeli(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pembeli_id');
    }

    /** @return BelongsTo<Toko, $this> */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class, 'toko_id');
    }

    /** @return HasMany<ButirPesanan, $this> */
    public function butir(): HasMany
    {
        return $this->hasMany(ButirPesanan::class, 'pesanan_id');
    }

    /**
     * @param  Builder<Pesanan>  $kueri
     */
    public function scopeMilikPembeli(Builder $kueri, Pengguna $pembeli): void
    {
        $kueri->where('pesanan.pembeli_id', $pembeli->id);
    }

    /**
     * @param  Builder<Pesanan>  $kueri
     */
    public function scopeMilikToko(Builder $kueri, Toko $toko): void
    {
        $kueri->where('pesanan.toko_id', $toko->id);
    }
}
