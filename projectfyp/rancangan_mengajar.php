<?php
// rancangan_mengajar.php
// Lesson Plan Overview for Admin
session_start();
require 'db.php';

// Sahkan Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$sql = "SELECT lp.*, t.full_name AS teacher_name 
        FROM lesson_plans lp
        LEFT JOIN teachers t ON lp.teacher_id = t.id
        ORDER BY lp.teaching_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Gambaran Rancangan Mengajar - Admin</title>
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
        
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📚 Ringkasan Rancangan Mengajar Guru</h2>
        <table>
            <thead>
                <tr>
                    <th>Tarikh</th>
                    <th>Guru & Kelas</th>
                    <th>Subjek & Topik</th>
                    <th>Objektif Pembelajaran</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo date('d/m/Y', strtotime($row['teaching_date'])); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['teacher_name'] ?? '-'); ?></strong><br>
                                <span style="font-size:12px; color:#666;"><?php echo htmlspecialchars($row['class_group']); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['subject']); ?></strong><br>
                                <span style="font-size:12px; color:#555;">Topik: <?php echo htmlspecialchars($row['topic']); ?></span>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars($row['learning_objective'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:20px;">Tiada rekod rancangan mengajar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
