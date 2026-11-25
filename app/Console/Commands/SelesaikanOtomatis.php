<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PenyelesaianOtomatis;
use Illuminate\Console\Command;

final class SelesaikanOtomatis extends Command
{
    protected $signature = 'lapak:selesaikan-otomatis';

    protected $description = 'Menyelesaikan pesanan yang melewati masa penahanan dana.';

    public function handle(PenyelesaianOtomatis $penyelesaian): int
    {
        $jumlah = $penyelesaian->jalankan();

        $this->info("{$jumlah} pesanan diselesaikan.");

        return self::SUCCESS;
    }
}
