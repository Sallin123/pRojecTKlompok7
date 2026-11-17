<?php
require_once __DIR__ . '/auth.php';
admin_require_authentication();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/request_helpers.php';

admin_request_run([
    'table' => 'surat',
    'title' => 'Surat Online',
    'search_hint' => 'Cari jenis surat, nama, atau keterangan',
    'search_columns' => ['jenis_surat', 'nama', 'keterangan'],
    'list_columns' => ['id', 'tanggal', 'jenis_surat', 'nama', 'status'],
    'list_labels' => ['jenis_surat' => 'Jenis Surat', 'nama' => 'Nama Pemohon'],
    'detail_columns' => ['id', 'tanggal', 'jenis_surat', 'nik', 'nama', 'no_hp', 'keterangan', 'status'],
    'detail_labels' => [
        'tanggal' => 'Tanggal Permohonan',
        'jenis_surat' => 'Jenis Surat',
        'nik' => 'NIK',
        'nama' => 'Nama Pemohon',
        'no_hp' => 'Nomor HP',
        'keterangan' => 'Keterangan Keperluan',
    ],
], $koneksi);
