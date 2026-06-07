<?php
/**
 * Log Helper
 * Fungsi untuk merekod tindakan pengguna ke dalam jadual system_logs.
 */

/**
 * Rekod tindakan pengguna ke dalam system_logs.
 *
 * @param mysqli $conn    Sambungan pangkalan data
 * @param string $action  Penerangan tindakan yang dilakukan
 * @param string $status  Status tindakan ('Success' atau 'Error')
 * @return bool True jika berjaya direkod, false jika gagal
 */
function logAction(mysqli $conn, string $action, string $status = 'Success'): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id    = $_SESSION['user_id'] ?? null;
    $username   = $_SESSION['username'] ?? 'Sistem';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $conn->prepare(
        "INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        error_log('logAction prepare error: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('issss', $user_id, $username, $action, $status, $ip_address);
    $result = $stmt->execute();

    if (!$result) {
        error_log('logAction execute error: ' . $stmt->error);
    }

    $stmt->close();
    return $result;
}
