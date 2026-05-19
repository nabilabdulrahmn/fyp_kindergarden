<?php
// teacher_daily_report.php
// Laporan Aktiviti Harian - Guru
session_start();
require 'db.php';

// Pastikan hanya cikgu yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Dapatkan atau buat teacher_id untuk rujukan FK
$check_teacher = $conn->query("SELECT id FROM teachers WHERE user_id = '$user_id'");
if ($check_teacher->num_rows == 0) {
    $conn->query("INSERT INTO teachers (user_id, full_name) VALUES ('$user_id', '$username')");
    $teacher_id = $conn->insert_id;
} else {
    $teacher_row = $check_teacher->fetch_assoc();
    $teacher_id = $teacher_row['id'];
}

$msg = '';

// --- PROSES SIMPAN LAPORAN HARIAN (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_laporan'])) {
    $class_id = (int)$_POST['class_id'];
    $report_date = $conn->real_escape_string($_POST['report_date']);
    $activities = $conn->real_escape_string($_POST['activities']);
    $meals = $conn->real_escape_string($_POST['meals']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $photo_path = '';

    // Handle Upload File (Gambar) jika ada
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed_ext)) {
            $upload_dir = 'uploads/daily_reports/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_name = uniqid('dr_') . '.' . $ext;
            $dest_path = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['photo']['tmp_path'] ?? $_FILES['photo']['tmp_name'], $dest_path)) {
                $photo_path = $dest_path;
            }
        }
    }

    if ($class_id > 0 && !empty($report_date) && !empty($activities)) {
        // Semak jika rekod untuk kelas + tarikh ini sudah wujud (Unique constraint)
        $check = $conn->query("SELECT id FROM daily_reports WHERE class_id='$class_id' AND report_date='$report_date'");
        if ($check->num_rows > 0) {
            // Update
            $sql = "UPDATE daily_reports SET 
                    activities='$activities', 
                    meals='$meals', 
                    notes='$notes', 
                    teacher_id='$teacher_id'";
            if (!empty($photo_path)) {
                $sql .= ", photo_path='$photo_path'";
            }
            $sql .= " WHERE class_id='$class_id' AND report_date='$report_date'";
        } else {
            // Insert
            $sql = "INSERT INTO daily_reports (class_id, teacher_id, report_date, activities, meals, notes, photo_path) 
                    VALUES ('$class_id', '$teacher_id', '$report_date', '$activities', '$meals', '$notes', '$photo_path')";
        }

        if ($conn->query($sql)) {
            $msg = "<div class='alert success'>Laporan Aktiviti Harian berjaya disimpan!</div>";
        } else {
            $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
        }
    } else {
        $msg = "<div class='alert error'>Sila isi semua ruangan wajib.</div>";
    }
}

// --- AMBIL SENARAI KELAS ---
// Guru boleh buat laporan untuk mana-mana kelas yang mereka ajar atau kelas yang tidak mempunyai guru.
$classes_result = $conn->query("SELECT id, class_name, module FROM classes WHERE teacher_id = '$teacher_id' OR teacher_id IS NULL ORDER BY class_name ASC");

// Ambil laporan harian yang telah dihantar oleh guru ini
$sql_history = "SELECT dr.*, c.class_name, c.module 
                FROM daily_reports dr 
                JOIN classes c ON dr.class_id = c.id 
                WHERE dr.teacher_id = '$teacher_id' 
                ORDER BY dr.report_date DESC, dr.created_at DESC LIMIT 30";
$history_result = $conn->query($sql_history);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Laporan Aktiviti Harian - Guru</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            background-color: #f4f7f6; 
            margin: 0; 
            padding: 20px;
        }
        .header-bar {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-left: 5px solid #ffb347; /* Oren Cikgu */
        }
        .main-container {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .panel {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .left-panel { flex: 5; }
        .right-panel { flex: 5; border-top: 5px solid #84b6f4; }
        
        h3 { color: #555; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; }
        label { font-weight: bold; color: #666; font-size: 13px; }
        
        select, input[type="date"], input[type="file"], textarea {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-family: inherit;
        }
        
        button {
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            border: none;
            transition: 0.2s;
        }
        .btn-submit { background-color: #ffb347; color: white; width: 100%; font-size: 15px; }
        .btn-submit:hover { background-color: #e67e22; }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .report-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .report-item:last-child { border-bottom: none; }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .report-class {
            font-weight: bold;
            color: #d35400;
        }
        .report-date {
            font-size: 12px;
            color: #777;
        }
        .report-body {
            font-size: 13px;
            color: #444;
            line-height: 1.5;
        }
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
    </style>
</head>
<body>

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">📝 Laporan Aktiviti Harian Kelas</h2>
    </div>

    <?php echo $msg; ?>

    <div class="main-container">
        
        <!-- Panel Form Input -->
        <div class="panel left-panel">
            <h3>✍️ Tulis Laporan Aktiviti Harian</h3>
            <form method="POST" action="teacher_daily_report.php" enctype="multipart/form-data">
                
                <div class="form-group">
                    <label>Pilih Kelas</label>
                    <select name="class_id" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php 
                        if ($classes_result && $classes_result->num_rows > 0) {
                            while($c = $classes_result->fetch_assoc()) {
                                echo "<option value='{$c['id']}'>{$c['class_name']} ({$c['module']})</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tarikh Laporan</label>
                    <input type="date" name="report_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Aktiviti Utama (Main Activities)</label>
                    <textarea name="activities" placeholder="Cth: 1. Sesi bercerita dan melukis haiwan peliharaan. 2. Main buaian di taman mini." rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label>Pelan / Rekod Makanan (Meals Log)</label>
                    <textarea name="meals" placeholder="Cth: Breakfast: Roti Telur & Susu. Lunch: Nasi Ayam & Buah Oren." rows="2"></textarea>
                </div>

                <div class="form-group">
                    <label>Ulasan & Catatan Tambahan (Notes)</label>
                    <textarea name="notes" placeholder="Cth: Semua murid bersemangat. Ali kelihatan mengantuk sedikit." rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Muat Naik Gambar Aktiviti (Pilihan)</label>
                    <input type="file" name="photo" accept="image/*">
                </div>

                <button type="submit" name="simpan_laporan" class="btn-submit">📢 Siarkan Laporan Harian</button>
            </form>
        </div>

        <!-- Panel Sejarah Log Laporan -->
        <div class="panel right-panel">
            <h3>📋 Sejarah Laporan Harian</h3>
            
            <?php if ($history_result && $history_result->num_rows > 0): ?>
                <?php while ($h = $history_result->fetch_assoc()): ?>
                    <div class="report-item">
                        <div class="report-header">
                            <span class="report-class"><?php echo htmlspecialchars($h['class_name']); ?> (<?php echo htmlspecialchars($h['module']); ?>)</span>
                            <span class="report-date">📅 <?php echo date('d/m/Y', strtotime($h['report_date'])); ?></span>
                        </div>
                        <div class="report-body">
                            <strong>Aktiviti:</strong><br>
                            <?php echo nl2br(htmlspecialchars($h['activities'])); ?><br><br>
                            <?php if(!empty($h['meals'])): ?>
                                <strong>Makanan:</strong> <?php echo htmlspecialchars($h['meals']); ?><br><br>
                            <?php endif; ?>
                            <?php if(!empty($h['notes'])): ?>
                                <strong>Nota:</strong> <em><?php echo htmlspecialchars($h['notes']); ?></em><br><br>
                            <?php endif; ?>
                            <?php if(!empty($h['photo_path'])): ?>
                                <div style="margin-top:8px;">
                                    <img src="<?php echo htmlspecialchars($h['photo_path']); ?>" alt="Activity" style="max-width:150px; border-radius:6px; border:1px solid #ddd;">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align:center; padding:50px; color:#888;">Tiada rekod laporan harian yang telah anda siarkan.</p>
            <?php endif; ?>
        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>

</body>
</html>
