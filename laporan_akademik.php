<?php
// laporan_akademik.php
// Academic Performance Reports for Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$sql = "SELECT rc.*, s.full_name, s.module 
        FROM report_cards rc
        JOIN students s ON rc.student_id = s.id
        ORDER BY rc.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Laporan Prestasi Akademik - Admin</title>
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
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>📈 Laporan Prestasi Akademik Pelajar</h2>
        <table>
            <thead>
                <tr>
                    <th>Pelajar & Modul</th>
                    <th>Penggal</th>
                    <th>Skor Penilaian</th>
                    <th>Komen Guru</th>
                    <th>Tarikh Dikeluarkan</th>
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
                            <td><?php echo htmlspecialchars($row['term']); ?></td>
                            <td style="font-size:13px;">
                                Membaca: <?php echo htmlspecialchars($row['reading_score'] ?? 'N/A'); ?><br>
                                Menulis: <?php echo htmlspecialchars($row['writing_score'] ?? 'N/A'); ?><br>
                                Tingkah Laku: <?php echo htmlspecialchars($row['behaviour_score'] ?? 'N/A'); ?><br>
                                Interaksi: <?php echo htmlspecialchars($row['interaction_score'] ?? 'N/A'); ?>
                            </td>
                            <td style="font-size:13px; font-style:italic;"><?php echo nl2br(htmlspecialchars($row['teacher_comment'] ?? 'Tiada Komen')); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px;">Tiada rekod prestasi akademik.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
