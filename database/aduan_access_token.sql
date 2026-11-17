-- Jalankan satu kali pada database db_desa setelah backup.
-- Token akses hanya disimpan sebagai hash; aduan lama tetap tanpa token dan
-- tidak dapat dicari dari layanan publik sampai pemilik mengajukan aduan baru.

ALTER TABLE `aduan`
    ADD COLUMN `access_token_hash` CHAR(64) NULL,
    ADD INDEX `idx_aduan_no_hp_access_token` (`no_hp`, `access_token_hash`);
