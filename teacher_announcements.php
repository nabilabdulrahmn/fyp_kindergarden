<?php
// teacher_announcements.php
// Pengumuman Kelas - Guru
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

$msg = '';

// --- PROSES SIMPAN PENGUMUMAN (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hantar_pengumuman'])) {
    $class_id = (int)$_POST['class_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

    // Semak sekuriti: Adakah guru ini benar-benar mengajar kelas ini dan modul diluluskan?
    if (!empty($approved_mods)) {
        $modules_list = "'" . implode("','", array_map(function($m) use ($conn) { return mysqli_real_escape_string($conn, $m); }, $approved_mods)) . "'";
        $check_class = $conn->query("SELECT id FROM classes WHERE id = '$class_id' AND teacher_id = '$teacher_id' AND module IN ($modules_list)");
        if ($check_class && $check_class->num_rows > 0) {
            $sql = "INSERT INTO announcements (author_id, title, content, scope, class_id, is_pinned) 
                    VALUES ('$user_id', '$title', '$content', 'class', '$class_id', '$is_pinned')";
            if ($conn->query($sql)) {
                $msg = "<div class='alert success'>Pengumuman kelas berjaya disiarkan!</div>";
            } else {
                $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
            }
        } else {
            $msg = "<div class='alert error'>Akses Ditolak: Anda tiada kebenaran untuk menyiarkan pengumuman kepada kelas ini.</div>";
        }
    } else {
        $msg = "<div class='alert error'>Akses Ditolak: Anda tiada modul mengajar yang aktif.</div>";
    }
}

// --- AMBIL KELAS YANG DIAJAR OLEH GURU INI SAHAJA ---
$my_classes = [];
if (!empty($approved_mods)) {
    $modules_list = "'" . implode("','", array_map(function($m) use ($conn) { return mysqli_real_escape_string($conn, $m); }, $approved_mods)) . "'";
    $classes_res = $conn->query("SELECT id, class_name, module FROM classes WHERE teacher_id = '$teacher_id' AND module IN ($modules_list)");
    if ($classes_res) {
        while ($c = $classes_res->fetch_assoc()) {
            $my_classes[] = $c;
        }
    }
}

// Ambil pengumuman yang ditulis oleh guru ini sahaja
if (empty($approved_mods)) {
    $sql_history = "SELECT a.*, c.class_name FROM announcements a LEFT JOIN classes c ON a.class_id = c.id WHERE 1=0";
} else {
    $modules_list = "'" . implode("','", array_map(function($m) use ($conn) { return mysqli_real_escape_string($conn, $m); }, $approved_mods)) . "'";
    $sql_history = "SELECT a.*, c.class_name 
                    FROM announcements a 
                    JOIN classes c ON a.class_id = c.id 
                    WHERE a.author_id = '$user_id' AND a.scope = 'class' AND c.module IN ($modules_list)
                    ORDER BY a.is_pinned DESC, a.created_at DESC";
}
$history_result = $conn->query($sql_history);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pengumuman Kelas - Guru</title>
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
        .left-panel { flex: 4; }
        .right-panel { flex: 6; border-top: 5px solid #84b6f4; }
        
        h3 { color: #555; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; }
        label { font-weight: bold; color: #666; font-size: 13px; }
        
        select, input[type="text"], textarea {
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
        
        .ann-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
            background: #fff;
        }
        .ann-box.pinned {
            border-left: 5px solid #e74c3c;
            background: #fffbfb;
        }
        .ann-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .ann-title {
            font-size: 16px;
            font-weight: bold;
            color: #2d3748;
        }
        .ann-meta {
            font-size: 11px;
            color: #a0aec0;
        }
        .ann-body {
            font-size: 14px;
            color: #4a5568;
            line-height: 1.6;
        }
        .pin-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
    </style>
</head>
<body>
<?php include 'sidebar_teacher.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">📢 Pengumuman Kelas & Hebahan</h2>
    </div>

    <?php echo $msg; ?>

    <div class="main-container">
        
        <!-- Panel Form -->
        <div class="panel left-panel">
            <h3>✍️ Siarkan Pengumuman Baru</h3>
            
            <?php if (count($my_classes) > 0): ?>
                <form method="POST" action="teacher_announcements.php">
                    <div class="form-group">
                        <label>Pilih Kelas Sasaran</label>
                        <select name="class_id" required>
                            <?php foreach ($my_classes as $mc): ?>
                                <option value="<?php echo $mc['id']; ?>"><?php echo htmlspecialchars($mc['class_name']); ?> (<?php echo htmlspecialchars($mc['module']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tajuk Pengumuman</label>
                        <input type="text" name="title" placeholder="Cth: Pertukaran Waktu Pulang / Bawa Baju Sukan" required>
                    </div>

                    <div class="form-group">
                        <label>Kandungan Pengumuman</label>
                        <textarea name="content" placeholder="Tulis mesej penuh anda di sini..." rows="6" required></textarea>
                    </div>

                    <div class="form-group" style="flex-direction:row; align-items:center; gap:8px;">
                        <input type="checkbox" name="is_pinned" id="is_pinned" style="transform: scale(1.2);">
                        <label for="is_pinned" style="cursor:pointer; font-weight:normal;">Pin pengumuman ini di bahagian atas</label>
                    </div>

                    <button type="submit" name="hantar_pengumuman" class="btn-submit">📢 Siarkan Sekarang</button>
                </form>
            <?php else: ?>
                <div class="alert error" style="margin:0;">
                    ⚠️ <strong>Tiada Kelas Ditugaskan:</strong> Anda belum ditugaskan untuk mengajar mana-mana kelas dalam sistem. Sila hubungi Admin untuk menetapkan kelas anda.
                </div>
            <?php endif; ?>
        </div>

        <!-- Panel Sejarah Pengumuman -->
        <div class="panel right-panel">
            <h3>📋 Sejarah Pengumuman Yang Disiarkan</h3>
            
            <?php if ($history_result && $history_result->num_rows > 0): ?>
                <?php while ($a = $history_result->fetch_assoc()): ?>
                    <div class="ann-box <?php echo $a['is_pinned'] ? 'pinned' : ''; ?>">
                        <div class="ann-header">
                            <div>
                                <span class="ann-title"><?php echo htmlspecialchars($a['title']); ?></span>
                                <span style="font-size:11px; color:#4a5568; background:#edf2f7; padding:2px 6px; border-radius:4px; margin-left:8px;">
                                    🎯 Kelas: <?php echo htmlspecialchars($a['class_name']); ?>
                                </span>
                            </div>
                            <div>
                                <?php if($a['is_pinned']): ?>
                                    <span class="pin-badge">📌 PINNED</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ann-body">
                            <?php echo nl2br(htmlspecialchars($a['content'])); ?>
                        </div>
                        <div class="ann-meta" style="margin-top:10px; text-align:right;">
                            Disiarkan pada: <?php echo date('d/m/Y H:i A', strtotime($a['created_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align:center; padding:50px; color:#888;">Tiada rekod pengumuman kelas yang telah anda siarkan.</p>
            <?php endif; ?>
        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>


</main>
</body>
</html>
