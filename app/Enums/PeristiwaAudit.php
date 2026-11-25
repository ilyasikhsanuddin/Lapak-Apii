<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * LP-17: daftar tindakan sensitif yang wajib meninggalkan jejak.
 *
 * Daftar ini disepakati tim sebelum ada yang menulis kode, dan hanya ditulis di sini.
 * Menambah peristiwa berarti menyunting berkas ini; uji penutup menolak peristiwa yang
 * tidak dicatat oleh satu baris kode pun.
 */
enum PeristiwaAudit: string
{
    // D1 Identitas dan Sesi
    case MasukBerhasil = 'masuk.berhasil';
    case MasukGagal = 'masuk.gagal';
    case SesiDicabut = 'sesi.dicabut';
    case KunciLamaDipakai = 'sesi.kunci_lama_dipakai';
    case PeranDiberikan = 'peran.diberikan';
    case PeranDicabut = 'peran.dicabut';

    // D2 Toko dan Katalog
    case TokoDisetujui = 'toko.disetujui';
    case TokoDitangguhkan = 'toko.ditangguhkan';
    case StokDisesuaikan = 'stok.disesuaikan';

    // D3 Keranjang dan Pesanan
    case CheckoutDibatalkan = 'checkout.dibatalkan';
    case PesananDibatalkan = 'pesanan.dibatalkan';

    // D4 Pembayaran dan Saldo
    case PembayaranLunas = 'pembayaran.lunas';
    case PembayaranDikembalikan = 'pembayaran.dikembalikan';
    case DanaDilepas = 'dana.dilepas';
    case DanaDikembalikan = 'dana.dikembalikan';
    case PencairanDiajukan = 'pencairan.diajukan';
    case PencairanDisetujui = 'pencairan.disetujui';
    case PencairanDitolak = 'pencairan.ditolak';
}
