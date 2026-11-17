<?php

function mobil_siaga_allowed_statuses(): array
{
    return [
        'siap_beroperasi',
        'sedang_digunakan',
        'dalam_perawatan',
        'tidak_dapat_beroperasi',
    ];
}

function mobil_siaga_allowed_application_statuses(): array
{
    return [
        'menunggu_verifikasi',
        'diproses',
        'disetujui',
        'ditolak',
        'selesai',
    ];
}

function mobil_siaga_bind_values(mysqli_stmt $statement, string $types, array &$values): bool
{
    if ($types === '') {
        return $values === [];
    }

    if (strlen($types) !== count($values)) {
        throw new InvalidArgumentException('Jumlah parameter query Mobil Siaga tidak sesuai.');
    }

    $arguments = [$types];
    foreach ($values as &$value) {
        $arguments[] = &$value;
    }
    unset($value);

    return call_user_func_array([$statement, 'bind_param'], $arguments);
}

function mobil_siaga_execute(mysqli $connection, string $query, string $types = '', array $values = []): mysqli_stmt
{
    $statement = $connection->prepare($query);
    if (!$statement instanceof mysqli_stmt) {
        throw new RuntimeException('Query Mobil Siaga tidak dapat disiapkan.');
    }

    if (!mobil_siaga_bind_values($statement, $types, $values)) {
        $statement->close();
        throw new RuntimeException('Parameter query Mobil Siaga tidak dapat dipasang.');
    }

    if (!$statement->execute()) {
        $statement->close();
        throw new RuntimeException('Query Mobil Siaga gagal dijalankan.');
    }

    return $statement;
}

function mobil_siaga_fetch_all(mysqli_stmt $statement): array
{
    $result = $statement->get_result();
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('Hasil query Mobil Siaga tidak dapat dibaca.');
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function mobil_siaga_fetch_one(mysqli_stmt $statement): ?array
{
    $result = $statement->get_result();
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('Hasil query Mobil Siaga tidak dapat dibaca.');
    }

    $row = $result->fetch_assoc();
    return is_array($row) ? $row : null;
}

function mobil_siaga_with_transaction(mysqli $connection, callable $operation, int $maxRetries = 2)
{
    if ($maxRetries < 0 || $maxRetries > 3) {
        throw new InvalidArgumentException('Batas retry transaksi Mobil Siaga harus antara 0 dan 3.');
    }

    for ($attempt = 0; ; $attempt++) {
        if (!$connection->begin_transaction()) {
            throw new RuntimeException('Transaksi Mobil Siaga tidak dapat dimulai.');
        }

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            try {
                if (!$connection->rollback()) {
                    throw new RuntimeException('Rollback mengembalikan hasil gagal.');
                }
            } catch (Throwable $rollbackException) {
                try {
                    $connection->close();
                } catch (Throwable $closeException) {
                    error_log('Mobil Siaga unsafe connection close failed: ' . $closeException->getMessage());
                }
                throw new RuntimeException(
                    'Transaksi Mobil Siaga gagal dan rollback juga gagal; koneksi ditutup dan operasi tidak diulang. '
                    . 'Kesalahan operasi: ' . $exception->getMessage()
                    . ' Kesalahan rollback: ' . $rollbackException->getMessage(),
                    0,
                    $rollbackException
                );
            }

            $errorCode = (int) $exception->getCode();
            $canRetry = $exception instanceof mysqli_sql_exception
                && in_array($errorCode, [1205, 1213], true)
                && $attempt < $maxRetries;
            if (!$canRetry) {
                throw $exception;
            }

            usleep(random_int(10000, 50000) * ($attempt + 1));
            continue;
        }

        try {
            if (!$connection->commit()) {
                throw new RuntimeException('Commit transaksi Mobil Siaga mengembalikan hasil gagal.');
            }
        } catch (Throwable $exception) {
            try {
                $connection->close();
            } catch (Throwable $closeException) {
                error_log('Mobil Siaga connection close after uncertain commit failed: ' . $closeException->getMessage());
            }

            throw new RuntimeException(
                'Commit transaksi Mobil Siaga gagal atau hasilnya tidak dapat dipastikan; koneksi ditutup dan operasi tidak diulang. '
                . 'Kesalahan commit: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        return $result;
    }
}

function mobil_siaga_require_tables(mysqli $connection, array $tables = []): void
{
    $allowed = [
        'mobil_siaga_profil',
        'mobil_siaga_fasilitas',
        'mobil_siaga_petugas',
        'mobil_siaga_jadwal',
        'mobil_siaga_status',
        'mobil_siaga_pengajuan',
        'mobil_siaga_pengajuan_riwayat',
        'mobil_siaga_penggunaan',
    ];
    $requested = $tables === [] ? $allowed : array_values(array_unique($tables));

    foreach ($requested as $table) {
        if (!is_string($table) || !in_array($table, $allowed, true)) {
            throw new InvalidArgumentException('Nama tabel Mobil Siaga tidak valid.');
        }

        $statement = mobil_siaga_execute(
            $connection,
            'SELECT COUNT(*) AS jumlah FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = \'BASE TABLE\'',
            's',
            [$table]
        );
        $row = mobil_siaga_fetch_one($statement);
        $statement->close();

        if ((int) ($row['jumlah'] ?? 0) !== 1) {
            throw new RuntimeException('Struktur tabel Mobil Siaga belum lengkap: ' . $table . '.');
        }
    }
}

function mobil_siaga_validate_id($value, string $field = 'ID'): int
{
    if (is_string($value) && ctype_digit($value)) {
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    }

    if (!is_int($value) || $value < 1) {
        throw new InvalidArgumentException($field . ' Mobil Siaga tidak valid.');
    }

    return $value;
}

function mobil_siaga_validate_text($value, string $field, int $limit, bool $required = false, bool $nullable = false): ?string
{
    if ($value === null && $nullable) {
        return null;
    }
    if (!is_string($value)) {
        throw new InvalidArgumentException($field . ' tidak valid.');
    }

    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $limit) {
        throw new InvalidArgumentException($required
            ? $field . ' wajib diisi dan maksimal ' . $limit . ' karakter.'
            : $field . ' maksimal ' . $limit . ' karakter.');
    }

    return $value === '' && $nullable ? null : $value;
}

function mobil_siaga_validate_date($value): string
{
    if (!is_string($value)
        || preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $matches) !== 1
        || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
        throw new InvalidArgumentException('Tanggal Mobil Siaga tidak valid.');
    }

    return $value;
}

function mobil_siaga_validate_time($value): string
{
    if (!is_string($value) || preg_match('/\A([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?\z/', $value, $matches) !== 1) {
        throw new InvalidArgumentException('Waktu Mobil Siaga tidak valid.');
    }

    return $matches[1] . ':' . $matches[2] . ':' . ($matches[3] ?? '00');
}

function mobil_siaga_validate_datetime($value): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException('Tanggal dan waktu Mobil Siaga tidak valid.');
    }

    $value = str_replace('T', ' ', trim($value));
    if (preg_match('/\A(\d{4}-\d{2}-\d{2}) ([0-2]\d:[0-5]\d(?::[0-5]\d)?)\z/', $value, $matches) !== 1) {
        throw new InvalidArgumentException('Tanggal dan waktu Mobil Siaga harus menggunakan format yang valid.');
    }

    return mobil_siaga_validate_date($matches[1]) . ' ' . mobil_siaga_validate_time($matches[2]);
}

function mobil_siaga_normalize_date_time_range($date, $startTime, $endTime): array
{
    $date = mobil_siaga_validate_date($date);
    $startTime = mobil_siaga_validate_time($startTime);
    $endTime = mobil_siaga_validate_time($endTime);
    if ($startTime === $endTime) {
        throw new InvalidArgumentException('Jam mulai dan selesai yang sama membuat durasi ambigu.');
    }

    $start = $date . ' ' . $startTime;
    $end = $date . ' ' . $endTime;
    $crossesMidnight = $endTime < $startTime;
    if ($crossesMidnight) {
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$endDate instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Tanggal Mobil Siaga tidak dapat diproses.');
        }
        $end = $endDate->modify('+1 day')->format('Y-m-d') . ' ' . $endTime;
    }

    return [
        'start' => $start,
        'end' => $end,
        'crosses_midnight' => $crossesMidnight,
    ];
}

function mobil_siaga_normalize_datetime_range($start, $end): array
{
    $start = mobil_siaga_validate_datetime($start);
    $end = mobil_siaga_validate_datetime($end);
    if ($end <= $start) {
        throw new InvalidArgumentException('Estimasi selesai harus setelah waktu mulai.');
    }

    $startDate = substr($start, 0, 10);
    $endDate = substr($end, 0, 10);
    $latestDate = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    if (!$latestDate instanceof DateTimeImmutable) {
        throw new InvalidArgumentException('Tanggal Mobil Siaga tidak dapat diproses.');
    }
    $latestDate = $latestDate->modify('+1 day')->format('Y-m-d');
    if ($endDate > $latestDate) {
        throw new InvalidArgumentException('Penggunaan hanya boleh melewati maksimal satu pergantian tanggal.');
    }

    return [
        'start' => $start,
        'end' => $end,
        'crosses_midnight' => $endDate !== $startDate,
    ];
}

function mobil_siaga_lock_vehicle(mysqli $connection): array
{
    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id, kapasitas_penumpang, status_operasional
         FROM mobil_siaga_profil WHERE id = 1 FOR UPDATE'
    );
    $profile = mobil_siaga_fetch_one($statement);
    $statement->close();

    if ($profile === null) {
        throw new RuntimeException('Profil kendaraan Mobil Siaga dengan ID 1 belum tersedia.');
    }

    return $profile;
}

function mobil_siaga_lock_staff(
    mysqli $connection,
    int $staffId,
    bool $requireActive = true,
    bool $mustExist = true
): ?array
{
    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id, status_tugas, aktif FROM mobil_siaga_petugas WHERE id = ? FOR UPDATE',
        'i',
        [$staffId]
    );
    $staff = mobil_siaga_fetch_one($statement);
    $statement->close();

    if ($staff === null) {
        if ($mustExist) {
            throw new InvalidArgumentException('Petugas Mobil Siaga tidak ditemukan.');
        }
        return null;
    }
    if ($requireActive && (int) ($staff['aktif'] ?? -1) !== 1) {
        throw new InvalidArgumentException('Petugas harus telah diverifikasi aktif sebelum ditugaskan.');
    }

    return $staff;
}

function mobil_siaga_lock_conflict_scope(
    mysqli $connection,
    array $staffIds = [],
    array $applicationIds = []
): void {
    $staffIds = array_values(array_unique(array_map(
        static function ($id): int {
            return mobil_siaga_validate_id($id, 'ID petugas');
        },
        $staffIds
    )));
    sort($staffIds, SORT_NUMERIC);

    if ($staffIds !== []) {
        $placeholders = implode(', ', array_fill(0, count($staffIds), '?'));
        $statement = mobil_siaga_execute(
            $connection,
            'SELECT id FROM mobil_siaga_jadwal
             WHERE petugas_id IN (' . $placeholders . ')
             ORDER BY petugas_id, tanggal, jam_mulai, id FOR UPDATE',
            str_repeat('i', count($staffIds)),
            $staffIds
        );
        mobil_siaga_fetch_all($statement);
        $statement->close();
    }

    $applicationIds = array_values(array_unique(array_map(
        static function ($id): int {
            return mobil_siaga_validate_id($id, 'ID pengajuan');
        },
        $applicationIds
    )));
    sort($applicationIds, SORT_NUMERIC);
    $applicationCondition = 'status = \'disetujui\'';
    $applicationTypes = '';
    $applicationValues = [];
    if ($applicationIds !== []) {
        $placeholders = implode(', ', array_fill(0, count($applicationIds), '?'));
        $applicationCondition .= ' OR id IN (' . $placeholders . ')';
        $applicationTypes = str_repeat('i', count($applicationIds));
        $applicationValues = $applicationIds;
    }
    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id FROM mobil_siaga_pengajuan
         WHERE ' . $applicationCondition . ' ORDER BY id FOR UPDATE',
        $applicationTypes,
        $applicationValues
    );
    mobil_siaga_fetch_all($statement);
    $statement->close();

    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id FROM mobil_siaga_penggunaan
         WHERE profil_id = 1 ORDER BY waktu_mulai, id FOR UPDATE'
    );
    mobil_siaga_fetch_all($statement);
    $statement->close();
}

function mobil_siaga_get_profile(mysqli $connection): ?array
{
    mobil_siaga_require_tables($connection, ['mobil_siaga_profil']);
    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id, nama_kendaraan, deskripsi, foto_file, kapasitas_penumpang,
                fungsi_layanan, cara_penggunaan, ketentuan_penggunaan,
                nama_kontak_darurat, kontak_darurat, status_operasional,
                created_at, updated_at
         FROM mobil_siaga_profil WHERE id = 1'
    );
    $profile = mobil_siaga_fetch_one($statement);
    $statement->close();

    return $profile;
}

function mobil_siaga_save_profile(mysqli $connection, array $input): void
{
    $allowed = [
        'nama_kendaraan',
        'deskripsi',
        'foto_file',
        'kapasitas_penumpang',
        'fungsi_layanan',
        'cara_penggunaan',
        'ketentuan_penggunaan',
        'nama_kontak_darurat',
        'kontak_darurat',
    ];
    if ($input === [] || array_diff(array_keys($input), $allowed) !== []) {
        throw new InvalidArgumentException('Kolom profil Mobil Siaga tidak valid.');
    }

    $values = [];
    foreach ($input as $field => $value) {
        if ($field === 'nama_kendaraan') {
            $values[$field] = mobil_siaga_validate_text($value, 'Nama kendaraan', 150, true);
        } elseif ($field === 'kapasitas_penumpang') {
            if ($value === null || $value === '') {
                $values[$field] = null;
            } elseif ((is_int($value) || (is_string($value) && ctype_digit($value)))
                && (int) $value >= 1 && (int) $value <= 65535) {
                $values[$field] = (int) $value;
            } else {
                throw new InvalidArgumentException('Kapasitas penumpang harus antara 1 dan 65.535 atau belum diisi.');
            }
        } elseif ($field === 'foto_file') {
            if ($value === null || $value === '') {
                $values[$field] = null;
            } elseif (is_string($value) && preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $value) === 1) {
                $values[$field] = $value;
            } else {
                throw new InvalidArgumentException('Nama file foto kendaraan tidak valid.');
            }
        } elseif ($field === 'kontak_darurat') {
            $values[$field] = mobil_siaga_validate_text($value, 'Kontak darurat', 30, false, true);
        } elseif ($field === 'nama_kontak_darurat') {
            $values[$field] = mobil_siaga_validate_text($value, 'Nama kontak darurat', 150, false, true);
        } else {
            $values[$field] = mobil_siaga_validate_text($value, 'Informasi profil', 10000, false, true);
        }
    }

    mobil_siaga_require_tables($connection, ['mobil_siaga_profil']);
    mobil_siaga_with_transaction($connection, function () use ($connection, $values): void {
        $statement = mobil_siaga_execute(
            $connection,
            'SELECT id FROM mobil_siaga_profil WHERE id = 1 FOR UPDATE'
        );
        $existing = mobil_siaga_fetch_one($statement);
        $statement->close();

        if ($existing === null) {
            if (!isset($values['nama_kendaraan'])) {
                throw new InvalidArgumentException('Nama kendaraan wajib diisi saat membuat profil pertama.');
            }
            $defaults = [
                'deskripsi' => null,
                'foto_file' => null,
                'kapasitas_penumpang' => null,
                'fungsi_layanan' => null,
                'cara_penggunaan' => null,
                'ketentuan_penggunaan' => null,
                'nama_kontak_darurat' => null,
                'kontak_darurat' => null,
            ];
            $insertValues = array_merge($defaults, $values);
            $fields = array_merge(['id'], array_keys($insertValues));
            $types = 'i';
            $boundValues = [1];
            foreach ($insertValues as $field => $value) {
                $types .= $field === 'kapasitas_penumpang' ? 'i' : 's';
                $boundValues[] = $value;
            }
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $sqlFields = implode(', ', array_map(static function (string $field): string {
                return '`' . $field . '`';
            }, $fields));
            $insert = mobil_siaga_execute(
                $connection,
                'INSERT INTO mobil_siaga_profil (' . $sqlFields . ') VALUES (' . $placeholders . ')',
                $types,
                $boundValues
            );
            $insert->close();
            return;
        }

        if ($values === []) {
            return;
        }
        $assignments = [];
        $types = '';
        $boundValues = [];
        foreach ($values as $field => $value) {
            $assignments[] = '`' . $field . '` = ?';
            $types .= $field === 'kapasitas_penumpang' ? 'i' : 's';
            $boundValues[] = $value;
        }
        $update = mobil_siaga_execute(
            $connection,
            'UPDATE mobil_siaga_profil SET ' . implode(', ', $assignments) . ' WHERE id = 1',
            $types,
            $boundValues
        );
        $update->close();
    });
}

function mobil_siaga_list_facilities(mysqli $connection, bool $activeOnly = true): array
{
    mobil_siaga_require_tables($connection, ['mobil_siaga_fasilitas']);
    $sql = 'SELECT id, profil_id, nama_fasilitas, keterangan, urutan, aktif, created_at, updated_at
            FROM mobil_siaga_fasilitas WHERE profil_id = 1';
    if ($activeOnly) {
        $sql .= ' AND aktif = 1';
    }
    $sql .= ' ORDER BY urutan, id';
    $statement = mobil_siaga_execute($connection, $sql);
    $rows = mobil_siaga_fetch_all($statement);
    $statement->close();

    return $rows;
}

function mobil_siaga_save_facility(mysqli $connection, array $input, ?int $facilityId = null): int
{
    $name = mobil_siaga_validate_text($input['nama_fasilitas'] ?? null, 'Nama fasilitas', 150, true);
    $description = mobil_siaga_validate_text($input['keterangan'] ?? null, 'Keterangan fasilitas', 10000, false, true);
    $order = $input['urutan'] ?? 0;
    $active = $input['aktif'] ?? 1;
    if ((!is_int($order) && !(is_string($order) && ctype_digit($order))) || (int) $order > 65535) {
        throw new InvalidArgumentException('Urutan fasilitas harus antara 0 dan 65.535.');
    }
    if (!in_array($active, [0, 1, '0', '1'], true)) {
        throw new InvalidArgumentException('Status aktif fasilitas tidak valid.');
    }
    mobil_siaga_require_tables($connection, ['mobil_siaga_fasilitas']);

    if ($facilityId === null) {
        $statement = mobil_siaga_execute(
            $connection,
            'INSERT INTO mobil_siaga_fasilitas (profil_id, nama_fasilitas, keterangan, urutan, aktif)
             VALUES (1, ?, ?, ?, ?)',
            'ssii',
            [$name, $description, (int) $order, (int) $active]
        );
        $id = (int) $statement->insert_id;
        $statement->close();
        return $id;
    }

    $facilityId = mobil_siaga_validate_id($facilityId, 'ID fasilitas');
    $statement = mobil_siaga_execute(
        $connection,
        'UPDATE mobil_siaga_fasilitas
         SET nama_fasilitas = ?, keterangan = ?, urutan = ?, aktif = ?
         WHERE id = ? AND profil_id = 1',
        'ssiii',
        [$name, $description, (int) $order, (int) $active, $facilityId]
    );
    $affected = $statement->affected_rows;
    $statement->close();
    if ($affected === 0) {
        $check = mobil_siaga_execute(
            $connection,
            'SELECT id FROM mobil_siaga_fasilitas WHERE id = ? AND profil_id = 1',
            'i',
            [$facilityId]
        );
        $exists = mobil_siaga_fetch_one($check) !== null;
        $check->close();
        if (!$exists) {
            throw new InvalidArgumentException('Fasilitas Mobil Siaga tidak ditemukan.');
        }
    }

    return $facilityId;
}

function mobil_siaga_list_staff(mysqli $connection, bool $activeOnly = false): array
{
    mobil_siaga_require_tables($connection, ['mobil_siaga_petugas']);
    $sql = 'SELECT id, nama_petugas, jenis_petugas, jabatan, no_hp, foto_file,
                   status_tugas, aktif, created_at, updated_at
            FROM mobil_siaga_petugas';
    if ($activeOnly) {
        $sql .= ' WHERE aktif = 1';
    }
    $sql .= ' ORDER BY nama_petugas, id';
    $statement = mobil_siaga_execute($connection, $sql);
    $rows = mobil_siaga_fetch_all($statement);
    $statement->close();

    return $rows;
}

function mobil_siaga_save_staff(mysqli $connection, array $input, ?int $staffId = null): int
{
    $name = mobil_siaga_validate_text($input['nama_petugas'] ?? null, 'Nama petugas', 150, true);
    $type = $input['jenis_petugas'] ?? null;
    if (!is_string($type) || !in_array($type, ['pengemudi', 'pendamping'], true)) {
        throw new InvalidArgumentException('Jenis petugas Mobil Siaga tidak valid.');
    }
    $position = mobil_siaga_validate_text($input['jabatan'] ?? null, 'Jabatan', 100, false, true);
    $phone = mobil_siaga_validate_text($input['no_hp'] ?? null, 'Nomor HP petugas', 30, false, true);
    $photo = $input['foto_file'] ?? null;
    if ($photo !== null && $photo !== '') {
        if (!is_string($photo) || preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $photo) !== 1) {
            throw new InvalidArgumentException('Nama file foto petugas tidak valid.');
        }
    } else {
        $photo = null;
    }
    $dutyStatus = $input['status_tugas'] ?? null;
    if ($dutyStatus === '') {
        $dutyStatus = null;
    }
    if ($dutyStatus !== null && !in_array($dutyStatus, ['siaga', 'bertugas'], true)) {
        throw new InvalidArgumentException('Status tugas petugas tidak valid.');
    }
    $active = $input['aktif'] ?? null;
    if ($active === '') {
        $active = null;
    }
    if ($active !== null && !in_array($active, [0, 1, '0', '1'], true)) {
        throw new InvalidArgumentException('Status aktif petugas harus 0, 1, atau belum diverifikasi.');
    }
    $active = $active === null ? null : (int) $active;
    if ($dutyStatus !== null && $active !== 1) {
        throw new InvalidArgumentException('Status tugas hanya dapat diisi untuk petugas yang telah diverifikasi aktif.');
    }
    if ($staffId === null && $dutyStatus === 'bertugas') {
        throw new InvalidArgumentException('Petugas baru tidak dapat langsung ditandai sedang bertugas.');
    }
    mobil_siaga_require_tables($connection, ['mobil_siaga_petugas']);

    if ($staffId === null) {
        return mobil_siaga_with_transaction($connection, function () use (
            $connection,
            $name,
            $type,
            $position,
            $phone,
            $photo,
            $dutyStatus,
            $active
        ): int {
            mobil_siaga_lock_vehicle($connection);
            mobil_siaga_lock_conflict_scope($connection);
            $statement = mobil_siaga_execute(
                $connection,
                'INSERT INTO mobil_siaga_petugas
                    (nama_petugas, jenis_petugas, jabatan, no_hp, foto_file, status_tugas, aktif)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                'ssssssi',
                [$name, $type, $position, $phone, $photo, $dutyStatus, $active]
            );
            $id = (int) $statement->insert_id;
            $statement->close();
            return $id;
        });
    }

    $staffId = mobil_siaga_validate_id($staffId, 'ID petugas');
    return mobil_siaga_with_transaction($connection, function () use (
        $connection,
        $staffId,
        $name,
        $type,
        $position,
        $phone,
        $photo,
        $dutyStatus,
        $active
    ): int {
        mobil_siaga_lock_vehicle($connection);
        $current = mobil_siaga_lock_staff($connection, $staffId, false, false);
        if ($current === null) {
            throw new InvalidArgumentException('Petugas Mobil Siaga tidak ditemukan.');
        }
        mobil_siaga_lock_conflict_scope($connection, [$staffId]);
        if ($dutyStatus === 'bertugas' && $current['status_tugas'] !== 'bertugas') {
            throw new InvalidArgumentException('Status bertugas hanya dapat ditetapkan oleh alur mulai penggunaan yang belum tersedia pada skema saat ini.');
        }

        $dutyChanged = $current['status_tugas'] !== $dutyStatus;
        $activeChanged = $current['aktif'] === null
            ? $active !== null
            : ($active === null || (int) $current['aktif'] !== $active);
        if ($current['status_tugas'] === 'bertugas' && $dutyStatus !== 'bertugas') {
            throw new InvalidArgumentException('Status petugas yang sedang bertugas tidak dapat diubah melalui penyimpanan profil.');
        }
        if ($dutyChanged || $activeChanged) {
            $activeUsage = mobil_siaga_execute(
                $connection,
                'SELECT u.id, a.status
                 FROM mobil_siaga_penggunaan u
                 LEFT JOIN mobil_siaga_pengajuan a ON a.id = u.pengajuan_id
                 WHERE u.petugas_id = ?
                   AND (u.pengajuan_id IS NULL OR a.status IS NULL
                        OR a.status NOT IN (\'selesai\', \'ditolak\'))
                 ORDER BY u.id LIMIT 1',
                'i',
                [$staffId]
            );
            $hasUnresolvedUsage = mobil_siaga_fetch_one($activeUsage) !== null;
            $activeUsage->close();
            if ($hasUnresolvedUsage) {
                throw new InvalidArgumentException('Status aktif atau tugas petugas tidak dapat diubah selama penggunaan terkait belum berstatus selesai.');
            }
        }

        $statement = mobil_siaga_execute(
            $connection,
            'UPDATE mobil_siaga_petugas
             SET nama_petugas = ?, jenis_petugas = ?, jabatan = ?, no_hp = ?, foto_file = ?,
                 status_tugas = ?, aktif = ?
             WHERE id = ?',
            'ssssssii',
            [$name, $type, $position, $phone, $photo, $dutyStatus, $active, $staffId]
        );
        $statement->close();
        return $staffId;
    });
}

function mobil_siaga_check_schedule_conflict(
    mysqli $connection,
    int $staffId,
    string $start,
    string $end,
    ?int $excludeScheduleId = null,
    bool $lockRows = false
): array {
    $range = mobil_siaga_normalize_datetime_range($start, $end);
    $staffId = mobil_siaga_validate_id($staffId, 'ID petugas');
    $sql = 'SELECT id, tanggal, jam_mulai, jam_selesai
            FROM mobil_siaga_jadwal WHERE petugas_id = ?';
    $types = 'i';
    $values = [$staffId];
    if ($excludeScheduleId !== null) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $values[] = mobil_siaga_validate_id($excludeScheduleId, 'ID jadwal');
    }
    $sql .= ' ORDER BY tanggal, jam_mulai, id';
    if ($lockRows) {
        $sql .= ' FOR UPDATE';
    }
    $statement = mobil_siaga_execute($connection, $sql, $types, $values);
    $schedules = mobil_siaga_fetch_all($statement);
    $statement->close();
    $conflicts = [];
    foreach ($schedules as $schedule) {
        $existing = mobil_siaga_normalize_date_time_range(
            (string) $schedule['tanggal'],
            (string) $schedule['jam_mulai'],
            (string) $schedule['jam_selesai']
        );
        if ($existing['start'] < $range['end'] && $range['start'] < $existing['end']) {
            $conflicts[] = [
                'type' => 'jadwal',
                'id' => (int) $schedule['id'],
                'start' => $existing['start'],
                'end' => $existing['end'],
            ];
        }
    }

    return $conflicts;
}

function mobil_siaga_check_staff_conflict(
    mysqli $connection,
    int $staffId,
    string $start,
    string $end,
    ?int $excludeUsageId = null,
    bool $includeSchedules = true
): array {
    $range = mobil_siaga_normalize_datetime_range($start, $end);
    $staffId = mobil_siaga_validate_id($staffId, 'ID petugas');
    $conflicts = [];

    if ($includeSchedules) {
        $statement = mobil_siaga_execute(
            $connection,
            'SELECT id, tanggal, jam_mulai, jam_selesai
             FROM mobil_siaga_jadwal WHERE petugas_id = ?
             ORDER BY tanggal, jam_mulai, id',
            'i',
            [$staffId]
        );
        $schedules = mobil_siaga_fetch_all($statement);
        $statement->close();
        foreach ($schedules as $schedule) {
            $existing = mobil_siaga_normalize_date_time_range(
                (string) $schedule['tanggal'],
                (string) $schedule['jam_mulai'],
                (string) $schedule['jam_selesai']
            );
            if ($existing['start'] < $range['end'] && $range['start'] < $existing['end']) {
                $conflicts[] = [
                    'type' => 'jadwal_petugas',
                    'id' => (int) $schedule['id'],
                    'start' => $existing['start'],
                    'end' => $existing['end'],
                ];
            }
        }
    }

    $sql = 'SELECT u.id, u.waktu_mulai, u.waktu_selesai
            FROM mobil_siaga_penggunaan u
            LEFT JOIN mobil_siaga_pengajuan a ON a.id = u.pengajuan_id
            WHERE u.petugas_id = ?
              AND (a.id IS NULL OR a.status NOT IN (\'selesai\', \'ditolak\'))';
    $types = 'i';
    $values = [$staffId];
    if ($excludeUsageId !== null) {
        $sql .= ' AND u.id <> ?';
        $types .= 'i';
        $values[] = mobil_siaga_validate_id($excludeUsageId, 'ID penggunaan');
    }
    $sql .= ' ORDER BY u.waktu_mulai, u.id';
    $statement = mobil_siaga_execute($connection, $sql, $types, $values);
    $usages = mobil_siaga_fetch_all($statement);
    $statement->close();
    foreach ($usages as $usage) {
        $existingStart = mobil_siaga_validate_datetime((string) $usage['waktu_mulai']);
        $existingEnd = $usage['waktu_selesai'] === null
            ? null
            : mobil_siaga_validate_datetime((string) $usage['waktu_selesai']);
        if ($existingEnd === null
            ? $existingStart < $range['end']
            : ($existingStart < $range['end'] && $range['start'] < $existingEnd)) {
            $conflicts[] = [
                'type' => 'penggunaan_petugas',
                'id' => (int) $usage['id'],
                'start' => $existingStart,
                'end' => $existingEnd,
            ];
        }
    }

    return $conflicts;
}

function mobil_siaga_list_schedules(mysqli $connection, ?int $staffId = null, ?string $date = null): array
{
    mobil_siaga_require_tables($connection, ['mobil_siaga_jadwal', 'mobil_siaga_petugas']);
    $conditions = [];
    $types = '';
    $values = [];
    if ($staffId !== null) {
        $conditions[] = 'j.petugas_id = ?';
        $types .= 'i';
        $values[] = mobil_siaga_validate_id($staffId, 'ID petugas');
    }
    if ($date !== null) {
        $conditions[] = 'j.tanggal = ?';
        $types .= 's';
        $values[] = mobil_siaga_validate_date($date);
    }
    $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    $statement = mobil_siaga_execute(
        $connection,
        'SELECT j.id, j.petugas_id, p.nama_petugas, p.jenis_petugas,
                j.tanggal, j.jam_mulai, j.jam_selesai, j.catatan, j.created_at
         FROM mobil_siaga_jadwal j
         INNER JOIN mobil_siaga_petugas p ON p.id = j.petugas_id'
            . $where . ' ORDER BY j.tanggal, j.jam_mulai, j.id',
        $types,
        $values
    );
    $rows = mobil_siaga_fetch_all($statement);
    $statement->close();

    return $rows;
}

function mobil_siaga_save_schedule(mysqli $connection, array $input, ?int $scheduleId = null): int
{
    $staffId = mobil_siaga_validate_id($input['petugas_id'] ?? null, 'ID petugas');
    $date = mobil_siaga_validate_date($input['tanggal'] ?? null);
    $startTime = mobil_siaga_validate_time($input['jam_mulai'] ?? null);
    $endTime = mobil_siaga_validate_time($input['jam_selesai'] ?? null);
    $note = mobil_siaga_validate_text($input['catatan'] ?? null, 'Catatan jadwal', 255, false, true);
    $range = mobil_siaga_normalize_date_time_range($date, $startTime, $endTime);
    mobil_siaga_require_tables($connection, [
        'mobil_siaga_profil',
        'mobil_siaga_petugas',
        'mobil_siaga_jadwal',
        'mobil_siaga_pengajuan',
        'mobil_siaga_penggunaan',
    ]);
    if ($scheduleId !== null) {
        $scheduleId = mobil_siaga_validate_id($scheduleId, 'ID jadwal');
    }

    return mobil_siaga_with_transaction($connection, function () use (
        $connection,
        $staffId,
        $date,
        $startTime,
        $endTime,
        $note,
        $range,
        $scheduleId
    ): int {
        mobil_siaga_lock_vehicle($connection);
        $previousStaffId = null;
        if ($scheduleId !== null) {
            $existingSchedule = mobil_siaga_execute(
                $connection,
                'SELECT id, petugas_id FROM mobil_siaga_jadwal WHERE id = ?',
                'i',
                [$scheduleId]
            );
            $existing = mobil_siaga_fetch_one($existingSchedule);
            $existingSchedule->close();
            if ($existing === null) {
                throw new InvalidArgumentException('Jadwal petugas tidak ditemukan.');
            }
            $previousStaffId = (int) $existing['petugas_id'];
        }

        $staffIds = array_values(array_unique(array_filter(
            [$previousStaffId, $staffId],
            static function ($id): bool {
                return $id !== null;
            }
        )));
        sort($staffIds, SORT_NUMERIC);
        foreach ($staffIds as $lockedStaffId) {
            mobil_siaga_lock_staff(
                $connection,
                $lockedStaffId,
                $lockedStaffId === $staffId
            );
        }
        mobil_siaga_lock_conflict_scope($connection, $staffIds);
        $conflicts = mobil_siaga_check_schedule_conflict(
            $connection,
            $staffId,
            $range['start'],
            $range['end'],
            $scheduleId,
            true
        );
        if ($conflicts !== []) {
            throw new InvalidArgumentException('Jadwal petugas bertabrakan dengan jadwal lain.');
        }
        if (mobil_siaga_check_staff_conflict(
            $connection,
            $staffId,
            $range['start'],
            $range['end'],
            null,
            false
        ) !== []) {
            throw new InvalidArgumentException('Jadwal petugas bertabrakan dengan penggunaan kendaraan.');
        }

        if ($scheduleId === null) {
            $statement = mobil_siaga_execute(
                $connection,
                'INSERT INTO mobil_siaga_jadwal (petugas_id, tanggal, jam_mulai, jam_selesai, catatan)
                 VALUES (?, ?, ?, ?, ?)',
                'issss',
                [$staffId, $date, $startTime, $endTime, $note]
            );
            $id = (int) $statement->insert_id;
            $statement->close();
            return $id;
        }

        $statement = mobil_siaga_execute(
            $connection,
            'UPDATE mobil_siaga_jadwal
             SET petugas_id = ?, tanggal = ?, jam_mulai = ?, jam_selesai = ?, catatan = ?
             WHERE id = ?',
            'issssi',
            [$staffId, $date, $startTime, $endTime, $note, $scheduleId]
        );
        $affected = $statement->affected_rows;
        $statement->close();
        if ($affected === 0) {
            $check = mobil_siaga_execute($connection, 'SELECT id FROM mobil_siaga_jadwal WHERE id = ?', 'i', [$scheduleId]);
            $exists = mobil_siaga_fetch_one($check) !== null;
            $check->close();
            if (!$exists) {
                throw new InvalidArgumentException('Jadwal petugas tidak ditemukan.');
            }
        }

        return $scheduleId;
    });
}

function mobil_siaga_get_vehicle_status(mysqli $connection): ?string
{
    mobil_siaga_require_tables($connection, ['mobil_siaga_profil']);
    $statement = mobil_siaga_execute($connection, 'SELECT status_operasional FROM mobil_siaga_profil WHERE id = 1');
    $row = mobil_siaga_fetch_one($statement);
    $statement->close();

    return $row === null || $row['status_operasional'] === null
        ? null
        : (string) $row['status_operasional'];
}

function mobil_siaga_set_vehicle_status(
    mysqli $connection,
    string $status,
    ?string $note = null,
    ?string $changedBy = null
): bool {
    if (!in_array($status, mobil_siaga_allowed_statuses(), true)) {
        throw new InvalidArgumentException('Status operasional Mobil Siaga tidak valid.');
    }
    $note = mobil_siaga_validate_text($note, 'Catatan status', 10000, false, true);
    $changedBy = mobil_siaga_validate_text($changedBy, 'Nama pengubah', 100, false, true);
    mobil_siaga_require_tables($connection, [
        'mobil_siaga_profil',
        'mobil_siaga_status',
        'mobil_siaga_pengajuan',
        'mobil_siaga_penggunaan',
    ]);

    return mobil_siaga_with_transaction($connection, function () use ($connection, $status, $note, $changedBy): bool {
        $profile = mobil_siaga_lock_vehicle($connection);
        mobil_siaga_lock_conflict_scope($connection);
        if ($profile['status_operasional'] === $status) {
            return false;
        }
        if ($profile['status_operasional'] === 'sedang_digunakan') {
            throw new InvalidArgumentException('Status kendaraan yang sedang digunakan tidak dapat diubah sebelum tersedia bukti penyelesaian penggunaan.');
        }
        if ($status === 'sedang_digunakan') {
            throw new InvalidArgumentException('Status sedang digunakan hanya dapat diubah melalui alur mulai penggunaan yang belum tersedia pada skema saat ini.');
        }
        $manualUsage = mobil_siaga_execute(
            $connection,
            'SELECT id FROM mobil_siaga_penggunaan
             WHERE profil_id = 1 AND pengajuan_id IS NULL
             ORDER BY waktu_mulai, id LIMIT 1'
        );
        $hasUnresolvedManualUsage = mobil_siaga_fetch_one($manualUsage) !== null;
        $manualUsage->close();
        if ($hasUnresolvedManualUsage) {
            throw new InvalidArgumentException('Status kendaraan tidak dapat diubah karena terdapat penggunaan manual yang belum dapat dipastikan selesai.');
        }
        if (in_array($status, ['dalam_perawatan', 'tidak_dapat_beroperasi'], true)) {
            $approved = mobil_siaga_execute(
                $connection,
                'SELECT id FROM mobil_siaga_pengajuan WHERE status = \'disetujui\' ORDER BY id LIMIT 1'
            );
            $hasApprovedApplication = mobil_siaga_fetch_one($approved) !== null;
            $approved->close();
            if ($hasApprovedApplication) {
                throw new InvalidArgumentException('Status kendaraan tidak dapat diubah karena masih ada pengajuan yang disetujui dan belum dapat dipastikan selesai.');
            }
        }

        $update = mobil_siaga_execute(
            $connection,
            'UPDATE mobil_siaga_profil SET status_operasional = ? WHERE id = 1',
            's',
            [$status]
        );
        $update->close();
        $history = mobil_siaga_execute(
            $connection,
            'INSERT INTO mobil_siaga_status (profil_id, status, catatan, diubah_oleh)
             VALUES (1, ?, ?, ?)',
            'sss',
            [$status, $note, $changedBy]
        );
        $history->close();

        return true;
    });
}

function mobil_siaga_list_vehicle_status_history(mysqli $connection, int $limit = 100): array
{
    if ($limit < 1 || $limit > 500) {
        throw new InvalidArgumentException('Batas riwayat status harus antara 1 dan 500.');
    }
    mobil_siaga_require_tables($connection, ['mobil_siaga_status']);
    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id, profil_id, status, catatan, diubah_oleh, dibuat_at
         FROM mobil_siaga_status WHERE profil_id = 1 ORDER BY dibuat_at DESC, id DESC LIMIT ?',
        'i',
        [$limit]
    );
    $rows = mobil_siaga_fetch_all($statement);
    $statement->close();

    return $rows;
}

function mobil_siaga_create_application(mysqli $connection, array $input): int
{
    $name = mobil_siaga_validate_text($input['nama_pemohon'] ?? null, 'Nama pemohon', 150, true);
    $phone = mobil_siaga_validate_text($input['no_hp_pemohon'] ?? null, 'Nomor HP pemohon', 30, true);
    $address = mobil_siaga_validate_text($input['alamat_pemohon'] ?? null, 'Alamat pemohon', 10000, false, true);
    $purpose = mobil_siaga_validate_text($input['tujuan_penggunaan'] ?? null, 'Tujuan penggunaan', 255, true);
    $date = mobil_siaga_validate_date($input['tanggal_penggunaan'] ?? null);
    $startTime = mobil_siaga_validate_time($input['jam_mulai'] ?? null);
    $endTimeValue = $input['jam_selesai'] ?? null;
    $endTime = ($endTimeValue === null || $endTimeValue === '')
        ? null
        : mobil_siaga_validate_time($endTimeValue);
    if ($endTime !== null) {
        mobil_siaga_normalize_date_time_range($date, $startTime, $endTime);
    }
    $passengers = $input['jumlah_penumpang'] ?? 1;
    if ((!is_int($passengers) && !(is_string($passengers) && ctype_digit($passengers)))
        || (int) $passengers < 1 || (int) $passengers > 65535) {
        throw new InvalidArgumentException('Jumlah penumpang harus antara 1 dan 65.535.');
    }
    $note = mobil_siaga_validate_text($input['catatan'] ?? null, 'Catatan pengajuan', 10000, false, true);
    mobil_siaga_require_tables($connection, ['mobil_siaga_pengajuan', 'mobil_siaga_pengajuan_riwayat']);

    return mobil_siaga_with_transaction($connection, function () use (
        $connection,
        $name,
        $phone,
        $address,
        $purpose,
        $date,
        $startTime,
        $endTime,
        $passengers,
        $note
    ): int {
        $code = 'MS-' . strtoupper(bin2hex(random_bytes(6)));
        $statement = mobil_siaga_execute(
            $connection,
            'INSERT INTO mobil_siaga_pengajuan
                (kode_pengajuan, nama_pemohon, no_hp_pemohon, alamat_pemohon,
                 tujuan_penggunaan, tanggal_penggunaan, jam_mulai, jam_selesai,
                 jumlah_penumpang, catatan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'ssssssssis',
            [$code, $name, $phone, $address, $purpose, $date, $startTime, $endTime, (int) $passengers, $note]
        );
        $id = (int) $statement->insert_id;
        $statement->close();
        $history = mobil_siaga_execute(
            $connection,
            'INSERT INTO mobil_siaga_pengajuan_riwayat (pengajuan_id, status, pesan, diubah_oleh)
             VALUES (?, \'menunggu_verifikasi\', ?, NULL)',
            'is',
            [$id, $note]
        );
        $history->close();

        return $id;
    });
}

function mobil_siaga_get_application(mysqli $connection, int $applicationId): ?array
{
    $applicationId = mobil_siaga_validate_id($applicationId, 'ID pengajuan');
    mobil_siaga_require_tables($connection, ['mobil_siaga_pengajuan']);
    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id, kode_pengajuan, nama_pemohon, no_hp_pemohon, alamat_pemohon,
                tujuan_penggunaan, tanggal_penggunaan, jam_mulai, jam_selesai,
                jumlah_penumpang, catatan, status, alasan_penolakan,
                diproses_oleh, diproses_pada, created_at, updated_at
         FROM mobil_siaga_pengajuan WHERE id = ?',
        'i',
        [$applicationId]
    );
    $row = mobil_siaga_fetch_one($statement);
    $statement->close();

    return $row;
}

function mobil_siaga_list_applications(
    mysqli $connection,
    ?string $status = null,
    int $limit = 100,
    int $offset = 0
): array {
    if ($status !== null && !in_array($status, mobil_siaga_allowed_application_statuses(), true)) {
        throw new InvalidArgumentException('Filter status pengajuan tidak valid.');
    }
    if ($limit < 1 || $limit > 500 || $offset < 0) {
        throw new InvalidArgumentException('Paginasi pengajuan tidak valid.');
    }
    mobil_siaga_require_tables($connection, ['mobil_siaga_pengajuan']);
    $sql = 'SELECT id, kode_pengajuan, nama_pemohon, no_hp_pemohon,
                   tujuan_penggunaan, tanggal_penggunaan, jam_mulai, jam_selesai,
                   jumlah_penumpang, status, diproses_oleh, diproses_pada, created_at
            FROM mobil_siaga_pengajuan';
    $types = '';
    $values = [];
    if ($status !== null) {
        $sql .= ' WHERE status = ?';
        $types = 's';
        $values[] = $status;
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?';
    $types .= 'ii';
    $values[] = $limit;
    $values[] = $offset;
    $statement = mobil_siaga_execute($connection, $sql, $types, $values);
    $rows = mobil_siaga_fetch_all($statement);
    $statement->close();

    return $rows;
}

function mobil_siaga_list_application_history(mysqli $connection, int $applicationId): array
{
    $applicationId = mobil_siaga_validate_id($applicationId, 'ID pengajuan');
    mobil_siaga_require_tables($connection, ['mobil_siaga_pengajuan_riwayat']);
    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id, pengajuan_id, status, pesan, diubah_oleh, dibuat_at
         FROM mobil_siaga_pengajuan_riwayat
         WHERE pengajuan_id = ? ORDER BY dibuat_at, id',
        'i',
        [$applicationId]
    );
    $rows = mobil_siaga_fetch_all($statement);
    $statement->close();

    return $rows;
}

function mobil_siaga_check_vehicle_conflict(
    mysqli $connection,
    string $start,
    string $end,
    ?int $excludeUsageId = null,
    ?int $excludeApplicationId = null
): array {
    $range = mobil_siaga_normalize_datetime_range($start, $end);
    $sql = 'SELECT u.id, u.pengajuan_id, u.waktu_mulai, u.waktu_selesai
            FROM mobil_siaga_penggunaan u
            LEFT JOIN mobil_siaga_pengajuan a ON a.id = u.pengajuan_id
            WHERE u.profil_id = 1
              AND (u.pengajuan_id IS NULL OR a.id IS NULL
                   OR a.status NOT IN (\'selesai\', \'ditolak\'))';
    $types = '';
    $values = [];
    if ($excludeUsageId !== null) {
        $sql .= ' AND u.id <> ?';
        $types .= 'i';
        $values[] = mobil_siaga_validate_id($excludeUsageId, 'ID penggunaan');
    }
    $sql .= ' ORDER BY u.waktu_mulai, u.id';
    $statement = mobil_siaga_execute($connection, $sql, $types, $values);
    $usages = mobil_siaga_fetch_all($statement);
    $statement->close();

    $conflicts = [];
    $usageApplicationIds = [];
    foreach ($usages as $usage) {
        if ($usage['pengajuan_id'] !== null) {
            $usageApplicationIds[(int) $usage['pengajuan_id']] = true;
        }
        $existingStart = mobil_siaga_validate_datetime((string) $usage['waktu_mulai']);
        $existingEnd = $usage['waktu_selesai'] === null
            ? null
            : mobil_siaga_validate_datetime((string) $usage['waktu_selesai']);
        if ($existingEnd === null
            ? $existingStart < $range['end']
            : ($existingStart < $range['end'] && $range['start'] < $existingEnd)) {
            $conflicts[] = [
                'type' => 'penggunaan',
                'id' => (int) $usage['id'],
                'application_id' => $usage['pengajuan_id'] === null ? null : (int) $usage['pengajuan_id'],
                'start' => $existingStart,
                'end' => $existingEnd,
            ];
        }
    }

    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id, tanggal_penggunaan, jam_mulai, jam_selesai
         FROM mobil_siaga_pengajuan
         WHERE status = \'disetujui\' ORDER BY tanggal_penggunaan, jam_mulai, id'
    );
    $approvedApplications = mobil_siaga_fetch_all($statement);
    $statement->close();
    foreach ($approvedApplications as $application) {
        $applicationId = (int) $application['id'];
        if ($applicationId === $excludeApplicationId
            || isset($usageApplicationIds[$applicationId])) {
            continue;
        }
        if ($application['jam_selesai'] === null) {
            $appRangeStart = mobil_siaga_validate_date((string) $application['tanggal_penggunaan'])
                . ' ' . mobil_siaga_validate_time((string) $application['jam_mulai']);
            if ($appRangeStart < $range['end']) {
                $conflicts[] = [
                    'type' => 'pengajuan_tanpa_penggunaan_dan_estimasi',
                    'id' => $applicationId,
                    'application_id' => $applicationId,
                    'start' => $appRangeStart,
                    'end' => null,
                ];
            }
            continue;
        }
        $existing = mobil_siaga_normalize_date_time_range(
            (string) $application['tanggal_penggunaan'],
            (string) $application['jam_mulai'],
            (string) $application['jam_selesai']
        );
        if ($existing['start'] < $range['end'] && $range['start'] < $existing['end']) {
            $conflicts[] = [
                'type' => 'pengajuan_tanpa_catatan_penggunaan',
                'id' => $applicationId,
                'application_id' => $applicationId,
                'start' => $existing['start'],
                'end' => $existing['end'],
            ];
        }
    }

    return $conflicts;
}

function mobil_siaga_approve_application(
    mysqli $connection,
    int $applicationId,
    string $processedBy,
    ?int $staffId = null,
    ?string $message = null
): int {
    throw new RuntimeException(
        'Persetujuan yang membuat catatan penggunaan dinonaktifkan. Gunakan mobil_siaga_review_application() untuk mengubah status saja.'
    );
}

function mobil_siaga_transition_application(
    mysqli $connection,
    int $applicationId,
    string $newStatus,
    string $changedBy,
    ?string $message = null
): void {
    $applicationId = mobil_siaga_validate_id($applicationId, 'ID pengajuan');
    $changedBy = mobil_siaga_validate_text($changedBy, 'Nama pemroses', 100, true);
    $message = mobil_siaga_validate_text($message, 'Pesan riwayat', 10000, false, true);
    if (!in_array($newStatus, ['diproses', 'ditolak'], true)) {
        throw new InvalidArgumentException('Perubahan status ini harus menggunakan alur khusus.');
    }
    mobil_siaga_require_tables($connection, [
        'mobil_siaga_profil',
        'mobil_siaga_pengajuan',
        'mobil_siaga_pengajuan_riwayat',
        'mobil_siaga_penggunaan',
    ]);

    mobil_siaga_with_transaction($connection, function () use (
        $connection,
        $applicationId,
        $newStatus,
        $changedBy,
        $message
    ): void {
        mobil_siaga_lock_vehicle($connection);
        mobil_siaga_lock_conflict_scope($connection, [], [$applicationId]);
        $statement = mobil_siaga_execute(
            $connection,
            'SELECT id, status FROM mobil_siaga_pengajuan WHERE id = ? FOR UPDATE',
            'i',
            [$applicationId]
        );
        $application = mobil_siaga_fetch_one($statement);
        $statement->close();
        if ($application === null) {
            throw new InvalidArgumentException('Pengajuan Mobil Siaga tidak ditemukan.');
        }

        $allowedTransitions = [
            'menunggu_verifikasi' => ['diproses', 'ditolak'],
            'diproses' => ['ditolak'],
        ];
        if (!in_array($newStatus, $allowedTransitions[$application['status']] ?? [], true)) {
            throw new InvalidArgumentException('Transisi status pengajuan tidak diizinkan.');
        }
        $usageStatement = mobil_siaga_execute(
            $connection,
            'SELECT id FROM mobil_siaga_penggunaan WHERE pengajuan_id = ? ORDER BY id LIMIT 1',
            'i',
            [$applicationId]
        );
        $hasLinkedUsage = mobil_siaga_fetch_one($usageStatement) !== null;
        $usageStatement->close();
        if ($hasLinkedUsage) {
            throw new RuntimeException('Pengajuan nonaktif yang masih memiliki catatan penggunaan tidak dapat diubah melalui alur status umum.');
        }

        $rejectionReason = $newStatus === 'ditolak' ? $message : null;
        $update = mobil_siaga_execute(
            $connection,
            'UPDATE mobil_siaga_pengajuan
             SET status = ?, alasan_penolakan = ?, diproses_oleh = ?, diproses_pada = NOW()
             WHERE id = ?',
            'sssi',
            [$newStatus, $rejectionReason, $changedBy, $applicationId]
        );
        $update->close();
        $history = mobil_siaga_execute(
            $connection,
            'INSERT INTO mobil_siaga_pengajuan_riwayat (pengajuan_id, status, pesan, diubah_oleh)
             VALUES (?, ?, ?, ?)',
            'isss',
            [$applicationId, $newStatus, $message, $changedBy]
        );
        $history->close();
    });
}

function mobil_siaga_review_application(
    mysqli $connection,
    int $applicationId,
    string $newStatus,
    string $changedBy,
    ?string $message = null
): bool {
    $applicationId = mobil_siaga_validate_id($applicationId, 'ID pengajuan');
    $changedBy = mobil_siaga_validate_text($changedBy, 'Nama pemroses', 100, true);
    $message = mobil_siaga_validate_text($message, 'Pesan riwayat', 10000, false, true);
    if (!in_array($newStatus, ['disetujui', 'ditolak'], true)) {
        throw new InvalidArgumentException('Keputusan permohonan tidak valid.');
    }
    mobil_siaga_require_tables($connection, [
        'mobil_siaga_pengajuan',
        'mobil_siaga_pengajuan_riwayat',
        'mobil_siaga_penggunaan',
    ]);

    return mobil_siaga_with_transaction($connection, function () use (
        $connection,
        $applicationId,
        $newStatus,
        $changedBy,
        $message
    ): bool {
        mobil_siaga_lock_conflict_scope($connection, [], [$applicationId]);
        $statement = mobil_siaga_execute(
            $connection,
            'SELECT id, status FROM mobil_siaga_pengajuan WHERE id = ? FOR UPDATE',
            'i',
            [$applicationId]
        );
        $application = mobil_siaga_fetch_one($statement);
        $statement->close();
        if ($application === null) {
            throw new InvalidArgumentException('Permohonan Mobil Siaga tidak ditemukan.');
        }

        if ($application['status'] === $newStatus) {
            return false;
        }
        if (!in_array($application['status'], ['menunggu_verifikasi', 'diproses'], true)) {
            throw new InvalidArgumentException('Status permohonan ini tidak dapat diubah menjadi keputusan tersebut.');
        }

        $usageStatement = mobil_siaga_execute(
            $connection,
            'SELECT id FROM mobil_siaga_penggunaan WHERE pengajuan_id = ? ORDER BY id LIMIT 1',
            'i',
            [$applicationId]
        );
        $hasLinkedUsage = mobil_siaga_fetch_one($usageStatement) !== null;
        $usageStatement->close();
        if ($hasLinkedUsage) {
            throw new RuntimeException('Permohonan memiliki catatan penggunaan terkait dan tidak dapat diproses melalui alur keputusan sederhana.');
        }

        $rejectionReason = $newStatus === 'ditolak' ? $message : null;
        $update = mobil_siaga_execute(
            $connection,
            'UPDATE mobil_siaga_pengajuan
             SET status = ?, alasan_penolakan = ?, diproses_oleh = ?, diproses_pada = NOW()
             WHERE id = ?',
            'sssi',
            [$newStatus, $rejectionReason, $changedBy, $applicationId]
        );
        $update->close();
        $history = mobil_siaga_execute(
            $connection,
            'INSERT INTO mobil_siaga_pengajuan_riwayat (pengajuan_id, status, pesan, diubah_oleh)
             VALUES (?, ?, ?, ?)',
            'isss',
            [$applicationId, $newStatus, $message, $changedBy]
        );
        $history->close();

        return true;
    });
}

function mobil_siaga_list_usages(mysqli $connection, int $limit = 100, int $offset = 0): array
{
    if ($limit < 1 || $limit > 500 || $offset < 0) {
        throw new InvalidArgumentException('Paginasi penggunaan tidak valid.');
    }
    mobil_siaga_require_tables($connection, ['mobil_siaga_penggunaan']);
    $statement = mobil_siaga_execute(
        $connection,
        'SELECT id, profil_id, pengajuan_id, petugas_id, tujuan, jumlah_penumpang,
                waktu_mulai, waktu_selesai, catatan, dicatat_oleh, created_at
         FROM mobil_siaga_penggunaan
         WHERE profil_id = 1 ORDER BY waktu_mulai DESC, id DESC LIMIT ? OFFSET ?',
        'ii',
        [$limit, $offset]
    );
    $rows = mobil_siaga_fetch_all($statement);
    $statement->close();

    return $rows;
}

function mobil_siaga_start_usage(
    mysqli $connection,
    int $usageId,
    string $startedAt,
    string $changedBy,
    ?int $staffId = null
): int {
    throw new RuntimeException('Mulai penggunaan dinonaktifkan sampai skema dapat membuktikan status setiap catatan penggunaan.');
}

function mobil_siaga_update_usage_estimate(
    mysqli $connection,
    int $usageId,
    string $estimatedEnd,
    string $changedBy
): int {
    $usageId = mobil_siaga_validate_id($usageId, 'ID penggunaan');
    $estimatedEnd = mobil_siaga_validate_datetime($estimatedEnd);
    $changedBy = mobil_siaga_validate_text($changedBy, 'Nama pengubah', 100, true);
    mobil_siaga_require_tables($connection, [
        'mobil_siaga_profil',
        'mobil_siaga_penggunaan',
        'mobil_siaga_petugas',
        'mobil_siaga_pengajuan',
        'mobil_siaga_jadwal',
    ]);

    return mobil_siaga_with_transaction($connection, function () use (
        $connection,
        $usageId,
        $estimatedEnd,
        $changedBy
    ): int {
        mobil_siaga_lock_vehicle($connection);
        $statement = mobil_siaga_execute(
            $connection,
            'SELECT id, pengajuan_id, petugas_id
             FROM mobil_siaga_penggunaan WHERE id = ? AND profil_id = 1',
            'i',
            [$usageId]
        );
        $reference = mobil_siaga_fetch_one($statement);
        $statement->close();
        if ($reference === null) {
            throw new InvalidArgumentException('Catatan penggunaan kendaraan tidak ditemukan.');
        }
        $staffId = $reference['petugas_id'] === null ? null : (int) $reference['petugas_id'];
        $applicationId = $reference['pengajuan_id'] === null
            ? null
            : (int) $reference['pengajuan_id'];
        if ($staffId !== null) {
            mobil_siaga_lock_staff($connection, $staffId, false);
        }
        mobil_siaga_lock_conflict_scope(
            $connection,
            $staffId === null ? [] : [$staffId],
            $applicationId === null ? [] : [$applicationId]
        );

        $statement = mobil_siaga_execute(
            $connection,
            'SELECT id, pengajuan_id, petugas_id, waktu_mulai
             FROM mobil_siaga_penggunaan WHERE id = ? AND profil_id = 1 FOR UPDATE',
            'i',
            [$usageId]
        );
        $usage = mobil_siaga_fetch_one($statement);
        $statement->close();
        if ($usage === null) {
            throw new InvalidArgumentException('Catatan penggunaan kendaraan tidak ditemukan.');
        }
        $lockedStaffId = $usage['petugas_id'] === null ? null : (int) $usage['petugas_id'];
        $lockedApplicationId = $usage['pengajuan_id'] === null
            ? null
            : (int) $usage['pengajuan_id'];
        if ($lockedStaffId !== $staffId || $lockedApplicationId !== $applicationId) {
            throw new RuntimeException('Relasi catatan penggunaan berubah saat proses penguncian; ulangi operasi.');
        }
        if ($usage['pengajuan_id'] === null) {
            throw new InvalidArgumentException('Estimasi hanya dapat diperbarui untuk penggunaan yang terhubung dengan pengajuan yang disetujui.');
        }
        $application = mobil_siaga_execute(
            $connection,
            'SELECT status FROM mobil_siaga_pengajuan WHERE id = ?',
            'i',
            [(int) $usage['pengajuan_id']]
        );
        $applicationRow = mobil_siaga_fetch_one($application);
        $application->close();
        if ($applicationRow === null || $applicationRow['status'] !== 'disetujui') {
            throw new InvalidArgumentException('Estimasi hanya dapat diperbarui selama pengajuan masih berstatus disetujui.');
        }
        $range = mobil_siaga_normalize_datetime_range((string) $usage['waktu_mulai'], $estimatedEnd);
        if (mobil_siaga_check_vehicle_conflict(
            $connection,
            $range['start'],
            $range['end'],
            $usageId,
            $usage['pengajuan_id'] === null ? null : (int) $usage['pengajuan_id']
        ) !== []) {
            throw new InvalidArgumentException('Estimasi selesai baru bertabrakan dengan penggunaan kendaraan lain.');
        }

        if ($usage['petugas_id'] !== null) {
            $staffId = (int) $usage['petugas_id'];
            if (mobil_siaga_check_staff_conflict(
                $connection,
                $staffId,
                $range['start'],
                $range['end'],
                $usageId
            ) !== []) {
                throw new InvalidArgumentException('Estimasi baru bertabrakan dengan penggunaan petugas lain.');
            }
        }

        $update = mobil_siaga_execute(
            $connection,
            'UPDATE mobil_siaga_penggunaan SET waktu_selesai = ?, dicatat_oleh = ? WHERE id = ?',
            'ssi',
            [$range['end'], $changedBy, $usageId]
        );
        $update->close();

        return $usageId;
    });
}
