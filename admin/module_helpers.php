<?php

function admin_module_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function admin_module_bind(mysqli_stmt $statement, string $types, array &$values): bool
{
    if ($types === '') {
        return true;
    }

    $references = [];
    foreach ($values as &$value) {
        $references[] = &$value;
    }
    unset($value);

    return call_user_func_array([$statement, 'bind_param'], array_merge([$types], $references));
}

function admin_module_execute(mysqli $connection, string $query, string $types = '', array $values = []): mysqli_stmt
{
    $statement = $connection->prepare($query);
    if (!$statement || !admin_module_bind($statement, $types, $values) || !$statement->execute()) {
        if ($statement instanceof mysqli_stmt) {
            $statement->close();
        }

        throw new RuntimeException('Query modul admin tidak berhasil dijalankan.');
    }

    return $statement;
}

function admin_module_missing_tables(mysqli $connection, array $tables): array
{
    $missing = [];
    foreach ($tables as $table) {
        $statement = admin_module_execute(
            $connection,
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = \'BASE TABLE\'',
            's',
            [$table]
        );
        $statement->bind_result($count);
        $statement->fetch();
        $statement->close();

        if ((int) $count !== 1) {
            $missing[] = $table;
        }
    }

    return $missing;
}

function admin_module_flash(string $key, string $type, string $message): void
{
    $_SESSION[$key] = ['type' => $type, 'message' => $message];
}

function admin_module_take_flash(string $key): ?array
{
    $flash = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);

    return is_array($flash) ? $flash : null;
}

function admin_module_store_old_input(string $key, array $input, array $limits): void
{
    $oldInput = [];
    foreach ($limits as $field => $limit) {
        $value = $input[$field] ?? '';
        if (is_string($value)) {
            $oldInput[$field] = mb_substr($value, 0, $limit, 'UTF-8');
        }
    }

    $_SESSION[$key] = $oldInput;
}

function admin_module_take_old_input(string $key): array
{
    $oldInput = $_SESSION[$key] ?? [];
    unset($_SESSION[$key]);

    return is_array($oldInput) ? $oldInput : [];
}

function admin_module_form_redirect(string $page, string $action, ?int $id = null, array $context = []): void
{
    $parameters = $context;
    if ($action === 'create' || $action === 'update') {
        $parameters['form'] = $action === 'create' ? 'tambah' : 'edit';
        if ($id !== null) {
            $parameters['id'] = $id;
        }
    }

    header('Location: ' . $page . ($parameters === [] ? '' : '?' . http_build_query($parameters)));
    exit;
}
