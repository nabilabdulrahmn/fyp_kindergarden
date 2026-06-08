<?php
// jadual_aktiviti.php
// Master Activity Schedule for Admin
session_start();
require 'db.php';

// Sahkan Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Ambil data aktiviti dari pangkalan data
$sql = "SELECT a.*, t.full_name AS teacher_name 
        FROM activity_schedules a
        LEFT JOIN teachers t ON a.teacher_id = t.id
        ORDER BY a.activity_date DESC, a.activity_time ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Jadual Aktiviti Master - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 30px; }
        .container { 
            background: white; padding: 30px; border-radius: 12px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); width: 100%; max-width: 1000px; margin: auto; border-top: 8px solid #77dd77; 
        }
        h2 { color: #333; margin-bottom: 25px; text-align: center; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; }
        .bg-pending { background-color: #ffb347; }
        .bg-completed { background-color: #77dd77; }

        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
        .btn-back:hover { color: #333; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>🗓️ Jadual Aktiviti Keseluruhan (Master Schedule)</h2>
        <table>
            <thead>
                <tr>
                    <th>Tarikh & Masa</th>
                    <th>Kelas</th>
                    <th>Aktiviti</th>
                    <th>Guru Terlibat</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('d M Y', strtotime($row['activity_date'])); ?></strong><br>
                                <span style="color: #666;"><?php echo htmlspecialchars($row['activity_time']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['class_group']); ?></td>
                            <td><?php echo htmlspecialchars($row['activity_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['teacher_name'] ?? 'Tiada Rekod'); ?></td>
                            <td>
                                <span class="badge <?php echo ($row['status'] == 'Completed') ? 'bg-completed' : 'bg-pending'; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px;">Tiada rekod jadual aktiviti.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
