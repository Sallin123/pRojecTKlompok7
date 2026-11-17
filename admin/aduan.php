<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/request_helpers.php';

admin_request_run([
    'table' => 'aduan',
    'title' => 'Pengaduan Online',
    'search_hint' => 'Cari judul, kategori, nama, atau isi aduan',
    'search_columns' => ['judul', 'kategori', 'nama', 'isi'],
    'list_columns' => ['id', 'tanggal', 'judul', 'kategori', 'status'],
    'list_labels' => ['judul' => 'Judul Aduan', 'kategori' => 'Kategori'],
    'detail_columns' => ['id', 'tanggal', 'kategori', 'nama', 'no_hp', 'judul', 'isi', 'foto', 'status'],
    'detail_labels' => [
        'tanggal' => 'Tanggal Pengaduan',
        'kategori' => 'Kategori',
        'nama' => 'Nama Pelapor',
        'no_hp' => 'Nomor HP',
        'judul' => 'Judul Aduan',
        'isi' => 'Isi Aduan',
        'foto' => 'Lampiran Foto',
    ],
], $koneksi);
