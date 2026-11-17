-- Jalankan satu kali pada database aktif setelah backup.
-- Kolom boleh NULL agar data penduduk lama tidak perlu diisi sekaligus.
ALTER TABLE `penduduk`
    ADD COLUMN `nik` CHAR(16) NULL AFTER `nama_lengkap`,
    ADD COLUMN `tanggal_lahir` DATE NULL AFTER `nik`,
    ADD UNIQUE KEY `uq_penduduk_nik` (`nik`);