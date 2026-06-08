<?php
// admin_checkin_monitor.php
// Pemantauan Daftar Masuk/Keluar Pelajar - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$today = date('Y-m-d');
$filter_date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : $today;

// Ambil rekod check-in/check-out
$sql = "SELECT c.*, s.full_name, s.module, u_in.username as checkin_user, u_out.username as checkout_user
        FROM checkin_checkout c
        JOIN students s ON c.student_id = s.id
        LEFT JOIN users u_in ON c.checkin_by = u_in.id
        LEFT JOIN users u_out ON c.checkout_by = u_out.id
        WHERE c.date = '$filter_date'
        ORDER BY c.checkin_time DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pemantauan Daftar Masuk/Keluar - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #ff6f91; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        .filter-box { background: #fdfbf7; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f9edd9; display: flex; align-items: center; gap: 15px; }
        input[type="date"] { padding: 8px; border: 1px solid #ccc; border-radius: 5px; }
        button { background: #ff6f91; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #e65c7d; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-in { background: #4caf50; }
        .bg-out { background: #f44336; }
        .bg-pending { background: #ff9800; }
        
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>🔐 Pemantauan Daftar Masuk/Keluar (Check-in/Check-out)</h2>
        
        <div class="filter-box">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; margin: 0;">
                <label style="font-weight: bold; color: #555;">Pilih Tarikh:</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                <button type="submit">Cari</button>
                <?php if($filter_date !== $today): ?>
                    <a href="admin_checkin_monitor.php" style="text-decoration: none; color: #ff6f91; font-size: 13px; font-weight: bold;">Hari Ini</a>
                <?php endif; ?>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Pelajar & Kelas</th>
                    <th>Daftar Masuk (Check-in)</th>
                    <th>Daftar Keluar (Check-out)</th>
                    <th>Penjaga Terlibat</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                <span style="font-size:12px; color:#666;"><?php echo htmlspecialchars($row['module']); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($row['checkin_time'])): ?>
                                    <span class="badge bg-in">✔ <?php echo date('h:i A', strtotime($row['checkin_time'])); ?></span><br>
                                    <span style="font-size:11px; color:#888;">Oleh: <?php echo htmlspecialchars($row['checkin_user'] ?? '-'); ?></span>
                                <?php else: ?>
                                    <span style="color:#aaa; font-style:italic;">Belum Hadir</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['checkout_time'])): ?>
                                    <span class="badge bg-out">🏃 <?php echo date('h:i A', strtotime($row['checkout_time'])); ?></span><br>
                                    <span style="font-size:11px; color:#888;">Oleh: <?php echo htmlspecialchars($row['checkout_user'] ?? '-'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-pending">Masih di Premis</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['guardian_name'] ?? '-'); ?><br>
                                <?php if (!empty($row['notes'])): ?>
                                    <span style="font-size:11px; color:#888;">Nota: <?php echo htmlspecialchars($row['notes']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Tiada rekod daftar masuk/keluar untuk tarikh ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
