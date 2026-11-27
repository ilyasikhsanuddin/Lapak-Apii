<?php

use App\Enums\PeristiwaAudit;

it('memiliki pencatat untuk setiap peristiwa audit', function () {
    $peristiwa = array_column(PeristiwaAudit::cases(), 'value', 'name');
    $ditemukan = [];
    $berkas = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($berkas as $satu) {
        if (! $satu->isFile()
            || $satu->getExtension() !== 'php'
            || $satu->getFilename() === 'PeristiwaAudit.php'
            || str_starts_with($satu->getPathname(), app_path('Balapan').DIRECTORY_SEPARATOR)) {
            continue;
        }

        preg_match_all('/PeristiwaAudit::(\w+)\b/', file_get_contents($satu->getPathname()), $cocok);

        foreach ($cocok[1] as $nama) {
            $ditemukan[$nama] = true;
        }
    }

    $tanpaPencatat = array_diff_key($peristiwa, $ditemukan);

    expect($tanpaPencatat)->toBeEmpty('Peristiwa tanpa pencatat: '.implode(', ', $tanpaPencatat));
});

it('menamai peristiwa audit dengan pola benda.kejadian dan nilai unik', function () {
    $nama = array_column(PeristiwaAudit::cases(), 'value');

    foreach ($nama as $satu) {
        expect($satu)->toMatch('/^[a-z]+\.[a-z_]+$/');
    }

    expect($nama)->toHaveCount(count(array_unique($nama)));
});
