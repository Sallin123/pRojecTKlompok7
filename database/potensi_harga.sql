-- Jalankan satu kali pada database db_desa_pemulihan setelah backup.
-- Tambahkan kolom harga untuk tabel Potensi Desa yang sudah dibuat sebelumnya.

ALTER TABLE `potensi_desa`
    ADD COLUMN `harga` DECIMAL(15,0) UNSIGNED NULL AFTER `judul`;
