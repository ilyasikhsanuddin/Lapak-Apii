<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Di luar produksi, Eloquent menolak lazy loading, atribut yang belum dimuat,
        // dan pengisian kolom di luar $fillable. Kesalahan muncul saat pengembangan, bukan saat demo.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
