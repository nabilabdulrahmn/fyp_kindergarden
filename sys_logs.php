<?php
// sys_logs.php
// System Audit Logs - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$sql = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 200";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Log Sistem Audit - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #607d8b; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 20px; }
        th { background-color: #eceff1; padding: 12px; text-align: left; border-bottom: 2px solid #cfd8dc; color: #455a64; font-weight: bold;}
        td { padding: 10px 12px; border-bottom: 1px solid #eee; vertical-align: middle; color: #555; }
        
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-success { background: #4caf50; }
        .bg-failed { background: #f44336; }
        
        .timestamp { font-family: monospace; color: #888; font-size: 12px; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>🕵️ Log Audit Sistem (System Logs)</h2>
        <p style="color:#666; font-size:14px; margin-bottom:20px;">Memaparkan 200 rekod aktiviti terkini dalam sistem.</p>
        
        <table>
            <thead>
                <tr>
                    <th>Tarikh / Masa</th>
                    <th>Pengguna / IP</th>
                    <th>Tindakan (Action)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="timestamp"><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['username'] ?? 'Sistem'); ?></strong><br>
                                <span style="font-size:11px; color:#aaa;">IP: <?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['action']); ?></td>
                            <td>
                                <?php if($row['status'] == 'Success'): ?>
                                    <span class="badge bg-success">Success</span>
                                <?php else: ?>
                                    <span class="badge bg-failed">Failed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Tiada rekod log sistem dijumpai.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
