<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusPencairan;
use Database\Factories\PencairanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Pencairan extends Model
{
    /** @use HasFactory<PencairanFactory> */
    use HasFactory;

    protected $table = 'pencairan';

    // Pencairan hanya dibuat dan diubah oleh LayananPencairan.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => StatusPencairan::class,
            'jumlah_rupiah' => 'integer',
            'diputuskan_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengaju_id');
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diputuskan_oleh');
    }
}
