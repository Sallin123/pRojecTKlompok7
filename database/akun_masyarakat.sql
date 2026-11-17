-- Run once on the active application database after confirming this table does not exist.
-- This creates the community account review queue without changing existing records.

CREATE TABLE `akun_masyarakat` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(254) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `reviewed_by_admin_id` INT(11) NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_akun_masyarakat_email` (`email`),
    KEY `idx_akun_masyarakat_status_created` (`status`, `created_at`, `id`),
    CONSTRAINT `fk_akun_masyarakat_reviewer`
        FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admin` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
