<?php
// aktiviti_kelas.php
session_start();
require 'db.php';
require_once 'auth_guard.php';

// Pastikan hanya cikgu yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$teacher_id = dapatkan_teacher_id($conn);
$approved_mods = dapatkan_modul_diluluskan($conn, $teacher_id);

$valid_classes = [];
if (in_array('Tadika', $approved_mods)) {
    $valid_classes[] = 'Tadika 4-5 Tahun';
    $valid_classes[] = 'Tadika 6 Tahun';
}
if (in_array('KAFA Care', $approved_mods)) {
    $valid_classes[] = 'KAFA Care';
}
if (in_array('Taska', $approved_mods)) {
    $valid_classes[] = 'Aktiviti Taska';
}

// ==========================================
// 1. TETAPAN FILTER (PENGURUSAN HARIAN)
// ==========================================
$daily_class = isset($_GET['daily_class']) ? $_GET['daily_class'] : '';
if (empty($daily_class)) {
    $daily_class = !empty($valid_classes) ? $valid_classes[0] : '';
}
$daily_date = isset($_GET['daily_date']) ? $_GET['daily_date'] : date('Y-m-d');

// Verify daily class is valid
if (!empty($daily_class) && !in_array($daily_class, $valid_classes)) {
    echo "<script>alert('Ralat: Anda tiada kebenaran untuk kelas ini.'); window.location.href='aktiviti_kelas.php';</script>";
    exit();
}

// --- TINDAKAN 1: TAMBAH AKTIVITI BARU ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tambah_aktiviti'])) {
    $time_input = $_POST['activity_time'];
    $name_input = $conn->real_escape_string($_POST['activity_name']);
    
    if (!empty($time_input) && !empty($name_input)) {
        if (!in_array($daily_class, $valid_classes)) {
            echo "<script>alert('Ralat: Akses dinafi.'); window.location.href='aktiviti_kelas.php';</script>";
            exit();
        }
        $sql_add = "INSERT INTO activity_schedules (teacher_id, class_group, activity_date, activity_time, activity_name, status) 
                    VALUES ('$user_id', '$daily_class', '$daily_date', '$time_input', '$name_input', 'Pending')";
        $conn->query($sql_add);
        
        echo "<script>window.location.href='aktiviti_kelas.php?daily_class=$daily_class&daily_date=$daily_date';</script>";
    }
}

// --- TINDAKAN 2: SIMPAN STATUS CHECKLIST ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_jadual'])) {
    $all_activity_ids = isset($_POST['all_ids']) ? $_POST['all_ids'] : [];
    $completed_ids = isset($_POST['completed_ticks']) ? $_POST['completed_ticks'] : [];

    if (!in_array($daily_class, $valid_classes)) {
        echo "<script>alert('Ralat: Akses dinafi.'); window.location.href='aktiviti_kelas.php';</script>";
        exit();
    }

    foreach ($all_activity_ids as $act_id) {
        $status = in_array($act_id, $completed_ids) ? 'Completed' : 'Pending';
        $conn->query("UPDATE activity_schedules SET status='$status' WHERE id='$act_id' AND class_group='$daily_class'");
    }
    
    if (function_exists('catat_log')) {
        catat_log($conn, $user_id, $username, "Mengemas kini checklist aktiviti untuk $daily_class pada $daily_date", "Success");
    }
    
    echo "<script>alert('Jadual aktiviti berjaya dikemas kini!'); window.location.href='aktiviti_kelas.php?daily_class=$daily_class&daily_date=$daily_date';</script>";
}

// --- AMBIL DATA RUTIN HARIAN ---
if (empty($daily_class)) {
    $sql_daily = "SELECT * FROM activity_schedules WHERE 1=0";
} else {
    $sql_daily = "SELECT * FROM activity_schedules WHERE class_group='$daily_class' AND activity_date='$daily_date' ORDER BY activity_time ASC";
}
$result_daily = $conn->query($sql_daily);


// ==========================================
// 2. TETAPAN FILTER (SEJARAH AKTIVITI BAWAH)
// ==========================================
$hist_class = isset($_GET['hist_class']) ? $_GET['hist_class'] : '';
$hist_status = isset($_GET['hist_status']) ? $_GET['hist_status'] : '';
$hist_start = isset($_GET['hist_start']) ? $_GET['hist_start'] : date('Y-m-01'); // Awal bulan
$hist_end = isset($_GET['hist_end']) ? $_GET['hist_end'] : date('Y-m-t'); // Hujung bulan

// --- AMBIL DATA SEJARAH ---
$sql_history = "SELECT * FROM activity_schedules WHERE teacher_id = '$user_id'";

if ($hist_class != '') {
    if (in_array($hist_class, $valid_classes)) {
        $sql_history .= " AND class_group = '$hist_class'";
    } else {
        $sql_history .= " AND 1=0";
    }
} else {
    if (empty($valid_classes)) {
        $sql_history .= " AND 1=0";
    } else {
        $valid_list = "'" . implode("','", array_map(function($g) use ($conn) { return mysqli_real_escape_string($conn, $g); }, $valid_classes)) . "'";
        $sql_history .= " AND class_group IN ($valid_list)";
    }
}

if ($hist_status != '') $sql_history .= " AND status = '$hist_status'";
$sql_history .= " AND activity_date BETWEEN '$hist_start' AND '$hist_end'";
$sql_history .= " ORDER BY activity_date DESC, activity_time ASC";

$result_history = $conn->query($sql_history);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Activity Scheduling & History</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f4f7f6; margin: 0; padding: 30px; display: flex; flex-direction: column; align-items: center; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 900px; width: 100%; border-top: 8px solid #ffb347; margin-bottom: 30px; }
        
        h2 { color: #555; margin-top: 0; margin-bottom: 5px; }
        p.subtitle { color: #888; font-size: 14px; margin-bottom: 20px; }

        /* Form & Filter */
        .filter-box { background: #fff8f0; padding: 15px; border-radius: 8px; border: 1px solid #ffe0b2; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin-bottom: 20px; }
        .filter-history { background: #f9f9f9; border-color: #ddd; }
        .form-group { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 150px; }
        label { font-weight: bold; color: #666; font-size: 13px; }
        select, input[type="date"], input[type="text"] { padding: 9px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; }
        
        .btn-orange { background-color: #ffb347; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-orange:hover { background-color: #f39c12; }
        .btn-dark { background-color: #34495e; color: white; }
        .btn-dark:hover { background-color: #2c3e50; }

        /* Checklist Section */
        .activity-item { display: flex; align-items: center; justify-content: space-between; padding: 15px; border-bottom: 1px solid #eee; transition: 0.2s; }
        .activity-item:hover { background-color: #fafafa; }
        .time-badge { background: #ffe0b2; color: #e67e22; font-weight: bold; padding: 4px 8px; border-radius: 5px; font-size: 12px; margin-right: 15px;}
        .activity-name { font-size: 15px; color: #444; font-weight: 500; }
        .completed-text { text-decoration: line-through; color: #aaa; font-style: italic; }
        .btn-save { background-color: #2ecc71; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; font-size: 15px; margin-top: 15px; }

        /* History Table */
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px;}
        th { background-color: #f2f2f2; padding: 12px; text-align: left; color: #333; border-bottom: 2px solid #ddd; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .tag-class { display: inline-block; padding: 4px 8px; border-radius: 5px; font-size: 11px; font-weight: bold; background: #eee; color: #555; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-pending { background: #ffe4b5; color: #d2691e; }
        .badge-completed { background: #d4edda; color: #155724; }

        .section-divider { border: 0; border-top: 3px dashed #ddd; margin: 40px 0; }
        .btn-back { display: block; text-align: center; margin-top: 10px; text-decoration: none; color: #777; font-size: 14px; }
        
        @media print {
            .filter-box, .btn-back, .no-print, .add-box { display: none; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; border: none; max-width: 100%; }
        }
    </style>
</head>
<body>
<?php include 'sidebar_teacher.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="container">
        <h2>🎨 Pengurusan Rutin Semasa</h2>
        <p class="subtitle">Sila pilih kelas dan tarikh untuk tambah jadual atau tandakan aktiviti yang telah selesai.</p>

        <form method="GET" action="aktiviti_kelas.php" class="filter-box">
            <div class="form-group">
                <label>Kumpulan Kelas</label>
                <select name="daily_class" required>
                    <?php if (in_array('Tadika', $approved_mods)): ?>
                        <option value="Tadika 4-5 Tahun" <?php if($daily_class=='Tadika 4-5 Tahun') echo 'selected'; ?>>Tadika (4-5 Tahun)</option>
                        <option value="Tadika 6 Tahun" <?php if($daily_class=='Tadika 6 Tahun') echo 'selected'; ?>>Tadika (6 Tahun)</option>
                    <?php endif; ?>
                    <?php if (in_array('KAFA Care', $approved_mods)): ?>
                        <option value="KAFA Care" <?php if($daily_class=='KAFA Care') echo 'selected'; ?>>KAFA Care</option>
                    <?php endif; ?>
                    <?php if (in_array('Taska', $approved_mods)): ?>
                        <option value="Aktiviti Taska" <?php if($daily_class=='Aktiviti Taska') echo 'selected'; ?>>Aktiviti Taska</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tarikh Aktiviti</label>
                <input type="date" name="daily_date" value="<?php echo $daily_date; ?>" required>
            </div>
            
            <input type="hidden" name="hist_class" value="<?php echo $hist_class; ?>">
            <input type="hidden" name="hist_status" value="<?php echo $hist_status; ?>">
            <input type="hidden" name="hist_start" value="<?php echo $hist_start; ?>">
            <input type="hidden" name="hist_end" value="<?php echo $hist_end; ?>">
            
            <button type="submit" class="btn-orange">Buka Jadual</button>
        </form>

        <form method="POST" action="aktiviti_kelas.php?daily_class=<?php echo $daily_class; ?>&daily_date=<?php echo $daily_date; ?>">
            
            <h3 style="font-size: 16px; color: #e67e22; border-bottom: 2px solid #eee; padding-bottom:10px;">📋 Senarai Aktiviti (<?php echo date('d/m/Y', strtotime($daily_date)); ?>)</h3>
            
            <div style="margin-bottom: 20px;">
                <?php if ($result_daily->num_rows > 0): ?>
                    <?php while($row = $result_daily->fetch_assoc()): 
                        $isCompleted = ($row['status'] == 'Completed') ? 'checked' : '';
                        $textClass = ($row['status'] == 'Completed') ? 'completed-text' : '';
                    ?>
                        <div class="activity-item">
                            <div>
                                <span class="time-badge">⏰ <?php echo htmlspecialchars($row['activity_time']); ?></span>
                                <span class="activity-name <?php echo $textClass; ?>"><?php echo htmlspecialchars($row['activity_name']); ?></span>
                            </div>
                            <div>
                                <input type="hidden" name="all_ids[]" value="<?php echo $row['id']; ?>">
                                <input type="checkbox" name="completed_ticks[]" value="<?php echo $row['id']; ?>" <?php echo $isCompleted; ?> style="transform: scale(1.5); cursor: pointer;">
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 15px; color: #aaa; font-style: italic;">Jadual kosong. Sila tambah aktiviti baharu di bawah.</p>
                <?php endif; ?>
            </div>

            <?php if ($result_daily->num_rows > 0): ?>
                <button type="submit" name="simpan_jadual" class="btn-save">💾 Simpan Status Rutin Terkini</button>
            <?php endif; ?>
        </form>

        <div class="filter-box add-box" style="margin-top: 20px;">
            <div style="width: 100%; margin-bottom: 5px;"><strong>➕ Tambah Aktiviti Khas / Rutin Baru</strong></div>
            <form method="POST" action="aktiviti_kelas.php?daily_class=<?php echo $daily_class; ?>&daily_date=<?php echo $daily_date; ?>" style="display: flex; gap: 15px; width: 100%; align-items: flex-end;">
                <div class="form-group" style="flex: 0.3;">
                    <label>Masa</label>
                    <input type="text" name="activity_time" placeholder="Cth: 09:00 AM" required>
                </div>
                <div class="form-group" style="flex: 0.7;">
                    <label>Nama Aktiviti</label>
                    <input type="text" name="activity_name" placeholder="Cth: Taklimat / Sarapan" required>
                </div>
                <button type="submit" name="tambah_aktiviti" class="btn-orange btn-dark">Tambah</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>🕒 Sejarah & Log Rekod Penuh</h2>
            <button onclick="window.print()" class="btn-dark no-print" style="padding: 8px 15px; border-radius:5px; border:none; color:white; cursor:pointer;">🖨️ Cetak</button>
        </div>
        <p class="subtitle">Pantau dan semak kembali rekod aktiviti yang telah berlalu.</p>

        <form method="GET" action="aktiviti_kelas.php" class="filter-box filter-history">
            
            <input type="hidden" name="daily_class" value="<?php echo $daily_class; ?>">
            <input type="hidden" name="daily_date" value="<?php echo $daily_date; ?>">

            <div class="form-group">
                <label>Kumpulan Kelas</label>
                <select name="hist_class">
                    <option value="">Semua Kelas Terdaftar</option>
                    <?php if (in_array('Tadika', $approved_mods)): ?>
                        <option value="Tadika 4-5 Tahun" <?php if($hist_class=='Tadika 4-5 Tahun') echo 'selected'; ?>>Tadika (4-5 Tahun)</option>
                        <option value="Tadika 6 Tahun" <?php if($hist_class=='Tadika 6 Tahun') echo 'selected'; ?>>Tadika (6 Tahun)</option>
                    <?php endif; ?>
                    <?php if (in_array('KAFA Care', $approved_mods)): ?>
                        <option value="KAFA Care" <?php if($hist_class=='KAFA Care') echo 'selected'; ?>>KAFA Care</option>
                    <?php endif; ?>
                    <?php if (in_array('Taska', $approved_mods)): ?>
                        <option value="Aktiviti Taska" <?php if($hist_class=='Aktiviti Taska') echo 'selected'; ?>>Aktiviti Taska</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="hist_status">
                    <option value="">Semua Status</option>
                    <option value="Completed" <?php if($hist_status=='Completed') echo 'selected'; ?>>Selesai (Completed)</option>
                    <option value="Pending" <?php if($hist_status=='Pending') echo 'selected'; ?>>Tertangguh (Pending)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Mula</label>
                <input type="date" name="hist_start" value="<?php echo $hist_start; ?>">
            </div>
            <div class="form-group">
                <label>Akhir</label>
                <input type="date" name="hist_end" value="<?php echo $hist_end; ?>">
            </div>
            <button type="submit" class="btn-orange">Cari Sejarah</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Tarikh & Masa</th>
                    <th>Kelas</th>
                    <th>Nama Aktiviti</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_history->num_rows > 0): ?>
                    <?php while($row = $result_history->fetch_assoc()): 
                        $statusClass = ($row['status'] == 'Completed') ? 'badge-completed' : 'badge-pending';
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo date('d/m/Y', strtotime($row['activity_date'])); ?></strong><br>
                                <span style="color: #888; font-size: 11px;">⏰ <?php echo htmlspecialchars($row['activity_time']); ?></span>
                            </td>
                            <td><span class="tag-class"><?php echo htmlspecialchars($row['class_group']); ?></span></td>
                            <td style="color: #444;"><?php echo htmlspecialchars($row['activity_name']); ?></td>
                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo strtoupper($row['status']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding: 20px; color:#999;">Tiada rekod aktiviti ditemui.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <br>
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>


</main>
</body>
</html>