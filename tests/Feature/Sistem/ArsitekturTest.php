<?php

arch('tidak ada alat debug yang tertinggal')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('lapisan domain adalah PHP murni')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate\Http', 'Illuminate\Database', 'Illuminate\Support\Facades']);

it('tidak memakai tipe pecahan untuk kolom apa pun (LP-1)', function () {
    $pelanggar = [];

    foreach (glob(database_path('migrations/*.php')) as $berkas) {
        if (preg_match('/->(float|double|decimal)\(/', file_get_contents($berkas))) {
            $pelanggar[] = basename($berkas);
        }
    }

    expect($pelanggar)->toBeEmpty();
});

it('hanya mengubah peran pengguna lewat PengelolaPeran (LP-2)', function () {
    $pelanggar = [];
    $berkas = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($berkas as $satu) {
        if (! $satu->isFile() || $satu->getFilename() === 'PengelolaPeran.php') {
            continue;
        }

        $isi = file_get_contents($satu->getPathname());
        $lewatRelasi = '/->peran\(\)\s*->(attach|detach|sync\w*|toggle|updateExistingPivot)\(/';
        $lewatTabel = '/table\(\'pengguna_peran\'\)\s*->(insert\w*|update|delete|upsert)\(/';

        $menulis = preg_match($lewatRelasi, $isi) || preg_match($lewatTabel, $isi);

        if ($menulis) {
            $pelanggar[] = $satu->getFilename();
        }
    }

    expect($pelanggar)->toBeEmpty('Menulis peran di luar PengelolaPeran: '.implode(', ', $pelanggar));
});

it('hanya menulis log audit lewat PencatatAudit (LP-17)', function () {
    $pelanggar = [];
    $berkas = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($berkas as $satu) {
        if (! $satu->isFile() || $satu->getFilename() === 'PencatatAudit.php') {
            continue;
        }

        $isi = file_get_contents($satu->getPathname());
        $lewatModel = '/new LogAudit\b|LogAudit::(create|insert\w*|upsert|updateOrCreate)\(/';
        $tulis = '(insert\w*|update|delete|upsert|truncate|increment)';
        $tabel = 'table\(\'(log_audit|rantai_audit)\'\)';
        $lewatTabel = '/'.$tabel.'(\s*->\w+\([^)]*\))*\s*->'.$tulis.'\(/';

        if (preg_match($lewatModel, $isi) || preg_match($lewatTabel, $isi)) {
            $pelanggar[] = $satu->getFilename();
        }
    }

    expect($pelanggar)
        ->toBeEmpty('Menulis log audit di luar PencatatAudit: '.implode(', ', $pelanggar));
});
