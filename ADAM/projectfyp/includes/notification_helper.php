<?php
/**
 * Notification Helper
 * Fungsi untuk menghantar notifikasi kepada pengguna, pentadbir, dan ibu bapa.
 */

/**
 * Hantar notifikasi kepada pengguna tertentu.
 *
 * @param mysqli      $conn    Sambungan pangkalan data
 * @param int         $user_id ID pengguna penerima
 * @param string      $title   Tajuk notifikasi
 * @param string      $message Mesej notifikasi
 * @param string      $type    Jenis notifikasi ('info', 'success', 'warning', 'danger')
 * @param string|null $link    Pautan berkaitan (pilihan)
 * @return bool True jika berjaya, false jika gagal
 */
function sendNotification(mysqli $conn, int $user_id, string $title, string $message, string $type = 'info', ?string $link = null): bool
{
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        error_log('sendNotification prepare error: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('issss', $user_id, $title, $message, $type, $link);
    $result = $stmt->execute();

    if (!$result) {
        error_log('sendNotification execute error: ' . $stmt->error);
    }

    $stmt->close();
    return $result;
}

/**
 * Hantar notifikasi kepada semua pentadbir (admin & pengetua).
 *
 * @param mysqli      $conn    Sambungan pangkalan data
 * @param string      $title   Tajuk notifikasi
 * @param string      $message Mesej notifikasi
 * @param string      $type    Jenis notifikasi
 * @param string|null $link    Pautan berkaitan (pilihan)
 * @return int Bilangan pentadbir yang berjaya dimaklumkan
 */
function notifyAdmins(mysqli $conn, string $title, string $message, string $type = 'info', ?string $link = null): int
{
    $stmt = $conn->prepare("SELECT id FROM users WHERE role IN ('admin', 'pengetua')");

    if (!$stmt) {
        error_log('notifyAdmins prepare error: ' . $conn->error);
        return 0;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $count  = 0;

    while ($row = $result->fetch_assoc()) {
        if (sendNotification($conn, (int) $row['id'], $title, $message, $type, $link)) {
            $count++;
        }
    }

    $stmt->close();
    return $count;
}

/**
 * Hantar notifikasi kepada ibu bapa berdasarkan ID ibu bapa.
 * Mendapatkan user_id daripada jadual parents, kemudian hantar notifikasi.
 *
 * @param mysqli      $conn      Sambungan pangkalan data
 * @param int         $parent_id ID ibu bapa dalam jadual parents
 * @param string      $title     Tajuk notifikasi
 * @param string      $message   Mesej notifikasi
 * @param string      $type      Jenis notifikasi
 * @param string|null $link      Pautan berkaitan (pilihan)
 * @return bool True jika berjaya, false jika gagal atau ibu bapa tidak dijumpai
 */
function notifyParent(mysqli $conn, int $parent_id, string $title, string $message, string $type = 'info', ?string $link = null): bool
{
    $stmt = $conn->prepare("SELECT user_id FROM parents WHERE id = ?");

    if (!$stmt) {
        error_log('notifyParent prepare error: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('i', $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $user_id = (int) $row['user_id'];
        $stmt->close();
        return sendNotification($conn, $user_id, $title, $message, $type, $link);
    }

    $stmt->close();
    error_log("notifyParent: Ibu bapa dengan ID {$parent_id} tidak dijumpai.");
    return false;
}
