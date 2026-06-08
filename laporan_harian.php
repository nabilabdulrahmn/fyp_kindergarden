<?php
// laporan_harian.php
// Laporan Aktiviti Harian - Paparan untuk Ibu Bapa sahaja
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Dapatkan parent_id
$sql_parent = "SELECT id FROM parents WHERE user_id = $user_id LIMIT 1";
$res_parent = $conn->query($sql_parent);
if (!$res_parent || $res_parent->num_rows == 0) {
    echo "<script>alert('Profil ibu bapa tidak dijumpai.'); window.location.href='home.php';</script>";
    exit();
}
$parent = $res_parent->fetch_assoc();
$parent_id = (int)$parent['id'];

// Dapatkan senarai class_id anak parent ini
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

// Ambil laporan harian untuk kelas anak-anak sahaja
$reports = array();
if (count($class_ids) > 0) {
    $class_list = implode(',', $class_ids);
    $selected_date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : date('Y-m-d');
    
    $sql_reports = "SELECT dr.*, c.class_name, t.full_name AS teacher_name 
                    FROM daily_reports dr 
                    INNER JOIN classes c ON dr.class_id = c.id 
                    LEFT JOIN teachers t ON dr.teacher_id = t.id 
                    WHERE dr.class_id IN ($class_list)
                    AND dr.report_date = '$selected_date'
                    ORDER BY dr.report_date DESC";
    $res_reports = $conn->query($sql_reports);
    if ($res_reports) {
        while ($row = $res_reports->fetch_assoc()) {
            $reports[] = $row;
        }
    }
} else {
    $selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Aktiviti Harian - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #84b6f4; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }

        .filter-bar { background: white; border-radius: 16px; padding: 20px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); display: flex; gap: 15px; align-items: end; }
        .filter-group label { display: block; font-weight: bold; color: #555; font-size: 13px; margin-bottom: 5px; }
        .filter-group input { padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn-filter { background: #84b6f4; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-filter:hover { background: #6a9bd8; }

        .report-card { background: white; border-radius: 16px; padding: 30px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .report-class { font-size: 18px; font-weight: bold; color: #333; }
        .report-teacher { font-size: 13px; color: #888; }
        .report-date { background: #e8f4fd; padding: 6px 14px; border-radius: 20px; font-size: 13px; color: #84b6f4; font-weight: bold; }

        .report-section { margin-bottom: 18px; }
        .report-section-title { font-size: 14px; font-weight: bold; color: #555; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .report-section-content { background: #f8f9fa; padding: 15px; border-radius: 10px; color: #444; line-height: 1.6; font-size: 14px; }

        .empty-state { text-align: center; padding: 50px; color: #aaa; }
        .empty-state .icon { font-size: 50px; margin-bottom: 15px; }
    </style>
</head>
<body>
<?php include 'sidebar_parent.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <div class="page-header">
            <div>
                <h2>📝 Laporan Aktiviti Harian</h2>
                <div class="subtitle">Laporan harian guru untuk kelas anak anda</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Pilih Tarikh</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
            </div>
            <button type="submit" class="btn-filter">🔍 Lihat Laporan</button>
        </form>

        <?php if (count($reports) > 0): ?>
            <?php for ($i = 0; $i < count($reports); $i++): ?>
                <?php $r = $reports[$i]; ?>
                <div class="report-card">
                    <div class="report-header">
                        <div>
                            <div class="report-class">📚 <?php echo htmlspecialchars($r['class_name']); ?></div>
                            <div class="report-teacher">Guru: <?php echo htmlspecialchars($r['teacher_name'] ?? '-'); ?></div>
                        </div>
                        <div class="report-date"><?php echo date('d/m/Y', strtotime($r['report_date'])); ?></div>
                    </div>

                    <div class="report-section">
                        <div class="report-section-title">🎨 Aktiviti Hari Ini</div>
                        <div class="report-section-content"><?php echo nl2br(htmlspecialchars($r['activities'])); ?></div>
                    </div>

                    <?php if (!empty($r['meals'])): ?>
                    <div class="report-section">
                        <div class="report-section-title">🍽️ Makanan</div>
                        <div class="report-section-content"><?php echo nl2br(htmlspecialchars($r['meals'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($r['notes'])): ?>
                    <div class="report-section">
                        <div class="report-section-title">📌 Nota Tambahan</div>
                        <div class="report-section-content"><?php echo nl2br(htmlspecialchars($r['notes'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        <?php else: ?>
            <div class="report-card empty-state">
                <div class="icon">📝</div>
                <h3>Tiada Laporan</h3>
                <p style="margin-top: 10px;">Tiada laporan aktiviti harian untuk tarikh yang dipilih.</p>
            </div>
        <?php endif; ?>
    </div>

</main>
</body>
</html>
