<?php
// parent_checkin_log.php
// Log Daftar Masuk/Keluar - Paparan untuk Ibu Bapa
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

// Ambil senarai anak
$sql_children = "SELECT id, full_name FROM students WHERE parent_id = $parent_id AND status = 'Active' ORDER BY full_name";
$children = $conn->query($sql_children);

$selected_child = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
$selected_month = isset($_GET['month']) ? $conn->real_escape_string($_GET['month']) : date('Y-m');

// Sahkan anak milik parent ini
if ($selected_child > 0) {
    $verify = $conn->query("SELECT id FROM students WHERE id = $selected_child AND parent_id = $parent_id LIMIT 1");
    if (!$verify || $verify->num_rows == 0) {
        echo "<script>alert('Akses tidak dibenarkan.'); window.location.href='parent_checkin_log.php';</script>";
        exit();
    }
}

// Ambil rekod daftar masuk/keluar
$checkin_data = array();
if ($selected_child > 0) {
    $sql_ci = "SELECT cc.*, u.username AS checkin_by_name
               FROM checkin_checkout cc
               LEFT JOIN users u ON cc.checkin_by = u.id
               WHERE cc.student_id = $selected_child 
               AND DATE_FORMAT(cc.date, '%Y-%m') = '$selected_month'
               ORDER BY cc.date DESC, cc.checkin_time DESC";
    $res_ci = $conn->query($sql_ci);
    if ($res_ci) {
        while ($row = $res_ci->fetch_assoc()) {
            $checkin_data[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Daftar Masuk/Keluar - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #ff6961; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }

        .filter-bar { background: white; border-radius: 16px; padding: 20px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; font-weight: bold; color: #555; font-size: 13px; margin-bottom: 5px; }
        .filter-group select, .filter-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn-filter { background: #ff6961; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; height: 42px; }
        .btn-filter:hover { background: #e55550; }

        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px 15px; text-align: left; font-size: 13px; color: #555; border-bottom: 2px solid #eee; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }

        .time-in { color: #28a745; font-weight: bold; }
        .time-out { color: #ff6961; font-weight: bold; }
        .time-na { color: #aaa; }
        .guardian-badge { background: #e8f4fd; padding: 3px 10px; border-radius: 12px; font-size: 12px; color: #84b6f4; font-weight: bold; }

        .empty-state { text-align: center; padding: 50px; color: #aaa; }
        .empty-state .icon { font-size: 50px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2>🔐 Log Daftar Masuk/Keluar</h2>
                <div class="subtitle">Pantau masa masuk dan keluar anak anda</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Pilih Anak</label>
                <select name="child_id" required>
                    <option value="">-- Pilih Anak --</option>
                    <?php if ($children) { while ($c = $children->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selected_child == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['full_name']); ?>
                        </option>
                    <?php endwhile; } ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Bulan</label>
                <input type="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
            </div>
            <button type="submit" class="btn-filter">🔍 Cari</button>
        </form>

        <?php if ($selected_child > 0): ?>
            <div class="card">
                <h3 style="margin-bottom: 15px; color: #333;">📋 Rekod Daftar Masuk/Keluar</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Tarikh</th>
                            <th>Masa Masuk</th>
                            <th>Masa Keluar</th>
                            <th>Penjaga/Direkod Oleh</th>
                            <th>Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($checkin_data) > 0): ?>
                            <?php for ($i = 0; $i < count($checkin_data); $i++): ?>
                                <?php $ci = $checkin_data[$i]; ?>
                                <tr>
                                    <td><strong><?php echo date('d/m/Y', strtotime($ci['date'])); ?></strong></td>
                                    <td>
                                        <?php if ($ci['checkin_time']): ?>
                                            <span class="time-in">🟢 <?php echo date('h:i A', strtotime($ci['checkin_time'])); ?></span>
                                        <?php else: ?>
                                            <span class="time-na">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ci['checkout_time']): ?>
                                            <span class="time-out">🔴 <?php echo date('h:i A', strtotime($ci['checkout_time'])); ?></span>
                                        <?php else: ?>
                                            <span class="time-na">Belum keluar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ci['guardian_name']): ?>
                                            <span class="guardian-badge">👤 <?php echo htmlspecialchars($ci['guardian_name']); ?></span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($ci['checkin_by_name'] ?? '-'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($ci['notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endfor; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="empty-state">Tiada rekod daftar masuk/keluar untuk bulan ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card empty-state">
                <div class="icon">🔐</div>
                <h3>Sila pilih anak untuk melihat rekod.</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
