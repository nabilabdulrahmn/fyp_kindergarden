<?php
/**
 * CSRF Token Helper
 * Pengurusan token CSRF untuk keselamatan borang.
 */

/**
 * Janakan token CSRF dan simpan dalam sesi.
 * Jika token sudah wujud, kembalikan yang sedia ada.
 *
 * @return string Token CSRF (64 aksara hex)
 */
function generate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 64 hex chars
    }

    return $_SESSION['csrf_token'];
}

/**
 * Sahkan token CSRF yang diterima daripada borang.
 * Selepas pengesahan (berjaya atau gagal), token dijana semula
 * untuk mengelakkan serangan replay.
 *
 * @param string|null $token Token daripada input borang
 * @return bool True jika sah, false jika tidak
 */
function validate_csrf_token(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $session_token = $_SESSION['csrf_token'] ?? '';

    // Gunakan hash_equals untuk perbandingan masa-tetap (timing-safe)
    $valid = !empty($token) && !empty($session_token) && hash_equals($session_token, $token);

    // Jana semula token selepas pengesahan untuk keselamatan
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    return $valid;
}

/**
 * Hasilkan elemen input tersembunyi HTML dengan token CSRF.
 *
 * @return string HTML input hidden element
 */
function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
