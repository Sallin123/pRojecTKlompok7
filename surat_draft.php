<?php

const SURAT_DRAFT_TTL = 86400;

function surat_draft_expire(): void
{
    $draft = $_SESSION['surat_draft'] ?? null;
    if (!is_array($draft) || !isset($draft['created_at']) || !is_int($draft['created_at'])
        || time() - $draft['created_at'] > SURAT_DRAFT_TTL) {
        unset($_SESSION['surat_draft']);
    }
}

function surat_draft_get(): ?array
{
    surat_draft_expire();
    $draft = $_SESSION['surat_draft'] ?? null;
    if (!is_array($draft)
        || !isset($draft['token'], $draft['fields'])
        || !is_string($draft['token'])
        || preg_match('/\A[a-f0-9]{64}\z/', $draft['token']) !== 1
        || !is_array($draft['fields'])) {
        return null;
    }

    foreach (['jenis_surat', 'nik', 'nama', 'no_hp', 'keterangan'] as $field) {
        if (!isset($draft['fields'][$field]) || !is_string($draft['fields'][$field])) {
            unset($_SESSION['surat_draft']);
            return null;
        }
    }

    return $draft;
}

function surat_draft_store(array $fields): array
{
    $draft = [
        'created_at' => time(),
        'token' => bin2hex(random_bytes(32)),
        'fields' => $fields,
    ];
    $_SESSION['surat_draft'] = $draft;

    return $draft;
}

function surat_draft_clear(): void
{
    unset($_SESSION['surat_draft']);
}

function surat_form_store_old_input(array $fields): void
{
    $limits = [
        'jenis_surat' => 100,
        'nik' => 20,
        'nama' => 100,
        'no_hp' => 20,
        'keterangan' => 5000,
    ];
    $oldInput = [];
    foreach ($limits as $field => $limit) {
        $value = $fields[$field] ?? '';
        if (is_string($value)) {
            $oldInput[$field] = mb_substr($value, 0, $limit, 'UTF-8');
        }
    }
    $_SESSION['surat_form_old'] = $oldInput;
}

function surat_form_take_old_input(): array
{
    $oldInput = $_SESSION['surat_form_old'] ?? [];
    unset($_SESSION['surat_form_old']);

    return is_array($oldInput) ? $oldInput : [];
}
