<?php

use App\Http\Controllers\RegistrasiController;
use Illuminate\Support\Facades\Route;

// Login, logout, dan daftar sesi menyusul pada Sesi 2.
// throttle:5,1,registrasi = 5 permintaan per 1 menit per alamat IP.
// Parameter ketiga memberi awalan kunci, sehingga hitungannya tidak
// tercampur dengan rute lain yang kelak memakai throttle.
Route::post('registrasi', RegistrasiController::class)
    ->middleware('throttle:5,1,registrasi')
    ->name('registrasi');
