<?php
// parent_announcements.php
// Pengumuman Sekolah - Paparan untuk Ibu Bapa (Baca Sahaja)
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Dapatkan parent_id & kelas anak-anak parent
$sql_parent = "SELECT id FROM parents WHERE user_id = $user_id LIMIT 1";
$res_parent = $conn->query($sql_parent);
if (!$res_parent || $res_parent->num_rows == 0) {
    echo "<script>alert('Profil ibu bapa tidak dijumpai.'); window.location.href='home.php';</script>";
    exit();
}
$parent = $res_parent->fetch_assoc();
$parent_id = (int)$parent['id'];

// Dapatkan class_ids anak parent ini
$sql_classes = "SELECT DISTINCT sc.class_id 
                FROM student_classes sc 
                INNER JOIN students s ON sc.student_id = s.id 
                WHERE s.parent_id = $parent_id";
$res_classes = $conn->query($sql_classes);
$class_ids = array();
if ($res_classes) {
    while ($row = $res_classes->fetch_assoc()) {
        $class_ids[] = (int)$row['class_id'];
    }
}

// Ambil pengumuman: Global + pengumuman kelas anak sahaja
$class_filter = '';
if (count($class_ids) > 0) {
    $class_list = implode(',', $class_ids);
    $class_filter = "OR (a.scope = 'class' AND a.class_id IN ($class_list))";
}

$sql_ann = "SELECT a.*, u.username AS author_name, c.class_name
            FROM announcements a 
            LEFT JOIN users u ON a.author_id = u.id 
            LEFT JOIN classes c ON a.class_id = c.id 
            WHERE a.scope = 'global' $class_filter
            ORDER BY a.is_pinned DESC, a.created_at DESC
            LIMIT 50";
$announcements = $conn->query($sql_ann);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengumuman - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #ff9a76; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }

        .ann-card { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); transition: transform 0.2s; }
        .ann-card:hover { transform: translateY(-1px); }
        .ann-card.pinned { border-left: 5px solid #ff6961; }
        .ann-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .ann-title { font-size: 18px; font-weight: bold; color: #333; }
        .ann-pin { background: #ff6961; color: white; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .ann-meta { font-size: 12px; color: #888; margin-bottom: 12px; display: flex; gap: 15px; align-items: center; }
        .ann-scope { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; color: white; }
        .scope-global { background: #ffb347; }
        .scope-class { background: #77dd77; }
        .ann-content { color: #444; line-height: 1.7; font-size: 14px; }

        .empty-state { text-align: center; padding: 50px; color: #aaa; background: white; border-radius: 16px; }
    </style>
</head>
<body>
<?php include 'sidebar_parent.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <div class="page-header">
            <div>
                <h2>📢 Pengumuman</h2>
                <div class="subtitle">Pengumuman terkini dari pihak sekolah</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <?php if ($announcements && $announcements->num_rows > 0): ?>
            <?php while ($a = $announcements->fetch_assoc()): ?>
                <div class="ann-card <?php echo $a['is_pinned'] ? 'pinned' : ''; ?>">
                    <div class="ann-header">
                        <div class="ann-title">
                            <?php echo htmlspecialchars($a['title']); ?>
                        </div>
                        <?php if ($a['is_pinned']): ?>
                            <span class="ann-pin">📌 Penting</span>
                        <?php endif; ?>
                    </div>
                    <div class="ann-meta">
                        <span>📅 <?php echo date('d/m/Y, h:i A', strtotime($a['created_at'])); ?></span>
                        <span>👤 <?php echo htmlspecialchars($a['author_name'] ?? '-'); ?></span>
                        <?php if ($a['scope'] == 'global'): ?>
                            <span class="ann-scope scope-global">Semua</span>
                        <?php else: ?>
                            <span class="ann-scope scope-class">Kelas: <?php echo htmlspecialchars($a['class_name'] ?? '-'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ann-content">
                        <?php echo nl2br(htmlspecialchars($a['content'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 50px; margin-bottom: 15px;">📢</div>
                <h3>Tiada Pengumuman</h3>
                <p style="margin-top: 10px;">Tiada pengumuman buat masa ini.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</body>
</html>
