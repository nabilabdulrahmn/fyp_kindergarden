<?php
// sejarah_kehadiran.php
// Sejarah Kehadiran - Paparan untuk Ibu Bapa sahaja
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

// Ambil senarai anak untuk dropdown
$sql_children = "SELECT id, full_name FROM students WHERE parent_id = $parent_id AND status = 'Active' ORDER BY full_name";
$children = $conn->query($sql_children);

// Pilihan anak dan bulan
$selected_child = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
$selected_month = isset($_GET['month']) ? $conn->real_escape_string($_GET['month']) : date('Y-m');

// Sahkan anak milik parent ini (cegah akses data anak orang lain)
if ($selected_child > 0) {
    $verify = $conn->query("SELECT id FROM students WHERE id = $selected_child AND parent_id = $parent_id LIMIT 1");
    if (!$verify || $verify->num_rows == 0) {
        echo "<script>alert('Akses tidak dibenarkan.'); window.location.href='sejarah_kehadiran.php';</script>";
        exit();
    }
}

// Ambil rekod kehadiran
$attendance_data = array();
$stats = array('Present' => 0, 'Absent' => 0, 'MC' => 0);
if ($selected_child > 0) {
    $sql_att = "SELECT a.date, a.status, a.mc_file_path 
                FROM attendance a 
                WHERE a.student_id = $selected_child 
                AND DATE_FORMAT(a.date, '%Y-%m') = '$selected_month'
                ORDER BY a.date DESC";
    $res_att = $conn->query($sql_att);
    if ($res_att) {
        while ($row = $res_att->fetch_assoc()) {
            $attendance_data[] = $row;
            $stats[$row['status']]++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sejarah Kehadiran - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #84b6f4; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }

        .filter-bar { background: white; border-radius: 16px; padding: 20px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; font-weight: bold; color: #555; font-size: 13px; margin-bottom: 5px; }
        .filter-group select, .filter-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn-filter { background: #84b6f4; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px; height: 42px; }
        .btn-filter:hover { background: #6a9bd8; }

        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .stat-number { font-size: 32px; font-weight: bold; }
        .stat-label { font-size: 13px; color: #888; margin-top: 5px; }
        .stat-present .stat-number { color: #28a745; }
        .stat-absent .stat-number { color: #ff6961; }
        .stat-mc .stat-number { color: #ffb347; }

        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px 15px; text-align: left; font-size: 13px; color: #555; border-bottom: 2px solid #eee; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; }
        .badge-present { background: #28a745; }
        .badge-absent { background: #ff6961; }
        .badge-mc { background: #ffb347; }
        .empty-state { text-align: center; padding: 40px; color: #aaa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2>📅 Sejarah Kehadiran</h2>
                <div class="subtitle">Pantau kehadiran anak anda di sekolah</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Pilih Anak</label>
                <select name="child_id" required>
                    <option value="">-- Pilih Anak --</option>
                    <?php 
                    if ($children) {
                        while ($c = $children->fetch_assoc()):
                    ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selected_child == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['full_name']); ?>
                        </option>
                    <?php 
                        endwhile;
                    }
                    ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Bulan</label>
                <input type="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
            </div>
            <button type="submit" class="btn-filter">🔍 Cari</button>
        </form>

        <?php if ($selected_child > 0): ?>
            <div class="stats-row">
                <div class="stat-card stat-present">
                    <div class="stat-number"><?php echo $stats['Present']; ?></div>
                    <div class="stat-label">✅ Hadir</div>
                </div>
                <div class="stat-card stat-absent">
                    <div class="stat-number"><?php echo $stats['Absent']; ?></div>
                    <div class="stat-label">❌ Tidak Hadir</div>
                </div>
                <div class="stat-card stat-mc">
                    <div class="stat-number"><?php echo $stats['MC']; ?></div>
                    <div class="stat-label">🏥 MC</div>
                </div>
            </div>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Tarikh</th>
                            <th>Hari</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($attendance_data) > 0): ?>
                            <?php for ($i = 0; $i < count($attendance_data); $i++): ?>
                                <?php
                                    $row = $attendance_data[$i];
                                    $badge_class = 'badge-present';
                                    if ($row['status'] == 'Absent') $badge_class = 'badge-absent';
                                    if ($row['status'] == 'MC') $badge_class = 'badge-mc';
                                    $hari = array('Sunday'=>'Ahad','Monday'=>'Isnin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Khamis','Friday'=>'Jumaat','Saturday'=>'Sabtu');
                                    $day_en = date('l', strtotime($row['date']));
                                    $day_ms = isset($hari[$day_en]) ? $hari[$day_en] : $day_en;
                                ?>
                                <tr>
                                    <td><strong><?php echo date('d/m/Y', strtotime($row['date'])); ?></strong></td>
                                    <td><?php echo $day_ms; ?></td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $row['status']; ?></span></td>
                                </tr>
                            <?php endfor; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="empty-state">Tiada rekod kehadiran untuk bulan ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card empty-state">
                <div style="font-size: 50px; margin-bottom: 15px;">📋</div>
                <h3>Sila pilih anak untuk melihat rekod kehadiran.</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
