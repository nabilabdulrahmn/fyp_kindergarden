<?php
// perkembangan.php
// Perkembangan Kanak-kanak - Guru
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

// --- PROSES SIMPAN PENCAPAIAN (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tambah_pencapaian'])) {
    $student_id = (int)$_POST['student_id'];
    $category_id = (int)$_POST['category_id'];
    $milestone_name = $conn->real_escape_string($_POST['milestone_name']);
    $status = $conn->real_escape_string($_POST['status']);
    $observed_date = $conn->real_escape_string($_POST['observed_date']);
    $notes = $conn->real_escape_string($_POST['notes']);

    if ($student_id > 0 && $category_id > 0 && !empty($milestone_name)) {
        if (!sahkan_akses_pelajar($conn, $teacher_id, $student_id)) {
            $msg = "<div class='alert error'>Ralat: Anda tiada kebenaran untuk murid ini.</div>";
        } else {
            $sql = "INSERT INTO milestones (student_id, category_id, milestone_name, status, observed_date, teacher_id, notes) 
                    VALUES ('$student_id', '$category_id', '$milestone_name', '$status', '$observed_date', '$teacher_id', '$notes')";
            if ($conn->query($sql)) {
                $msg = "<div class='alert success'>Rekod perkembangan berjaya disimpan!</div>";
            } else {
                $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
            }
        }
    } else {
        $msg = "<div class='alert error'>Sila isi semua ruangan wajib.</div>";
    }
}

// --- PROSES KEMAS KINI STATUS (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kemaskini_status'])) {
    $milestone_id = (int)$_POST['milestone_id'];
    $new_status = $conn->real_escape_string($_POST['new_status']);
    $update_notes = $conn->real_escape_string($_POST['update_notes']);

    // verify milestone belongs to a student we have access to
    $ms_check = $conn->query("SELECT student_id FROM milestones WHERE id = $milestone_id LIMIT 1");
    if ($ms_check && $ms_row = $ms_check->fetch_assoc()) {
        $student_id = (int)$ms_row['student_id'];
        if (!sahkan_akses_pelajar($conn, $teacher_id, $student_id)) {
            $msg = "<div class='alert error'>Ralat: Anda tiada kebenaran untuk rekod ini.</div>";
        } else {
            $sql = "UPDATE milestones SET status='$new_status', notes='$update_notes', teacher_id='$teacher_id' WHERE id='$milestone_id'";
            if ($conn->query($sql)) {
                $msg = "<div class='alert success'>Status pencapaian berjaya dikemas kini!</div>";
            } else {
                $msg = "<div class='alert error'>Ralat kemas kini: " . $conn->error . "</div>";
            }
        }
    } else {
        $msg = "<div class='alert error'>Ralat: Rekod perkembangan tidak dijumpai.</div>";
    }
}

// --- FILTER PELAJAR ---
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Validate that teacher has access to the selected student
if ($selected_student_id > 0) {
    if (!sahkan_akses_pelajar($conn, $teacher_id, $selected_student_id)) {
        $msg = "<div class='alert error'>Ralat: Akses dinafi untuk pelajar yang dipilih.</div>";
        $selected_student_id = 0;
    }
}

// Ambil senarai pelajar aktif (terhad kepada modul dan kelas diajar)
if (empty($approved_mods)) {
    $students_result = $conn->query("SELECT id, full_name, module FROM students WHERE 1=0");
} else {
    $modules_list = "'" . implode("','", array_map(function($m) use ($conn) { return mysqli_real_escape_string($conn, $m); }, $approved_mods)) . "'";
    $students_result = $conn->query("SELECT DISTINCT s.id, s.full_name, s.module 
                                     FROM students s 
                                     INNER JOIN student_classes sc ON s.id = sc.student_id
                                     INNER JOIN classes c ON sc.class_id = c.id
                                     WHERE s.status='Active' 
                                       AND c.teacher_id = $teacher_id
                                       AND s.module IN ($modules_list)
                                       AND c.module IN ($modules_list)
                                     ORDER BY s.full_name ASC");
}

// Ambil kategori milestones
$categories_result = $conn->query("SELECT * FROM milestone_categories ORDER BY category_name ASC");

// Ambil rekod milestone pelajar yang dipilih
$milestones_list = [];
if ($selected_student_id > 0) {
    $sql_m = "SELECT m.*, mc.category_name, t.full_name as teacher_name 
              FROM milestones m 
              JOIN milestone_categories mc ON m.category_id = mc.id 
              LEFT JOIN teachers t ON m.teacher_id = t.id 
              WHERE m.student_id = '$selected_student_id' 
              ORDER BY m.observed_date DESC, m.created_at DESC";
    $m_result = $conn->query($sql_m);
    if ($m_result) {
        while ($row = $m_result->fetch_assoc()) {
            $milestones_list[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Perkembangan Kanak-kanak - Guru</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid #ffb347; /* Oren Cikgu */
        }
        .header-bar form { display: flex; gap: 15px; align-items: center; margin: 0; }
        
        select, input[type="date"], input[type="text"], textarea {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-family: inherit;
        }
        
        button {
            padding: 9px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            border: none;
            transition: 0.2s;
        }
        .btn-search { background-color: #ffb347; color: white; }
        .btn-search:hover { background-color: #f39c12; }
        
        .btn-submit { background-color: #2ecc71; color: white; width: 100%; padding: 12px; font-size: 15px; }
        .btn-submit:hover { background-color: #27ae60; }
        
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
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Table / Timeline Style */
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background-color: #f9f9f9; padding: 10px; text-align: left; color: #444; }
        td { padding: 12px 10px; border-bottom: 1px solid #eee; }
        
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; color: white; display: inline-block; }
        .badge-belum { background: #e74c3c; }
        .badge-sedang { background: #f39c12; }
        .badge-telah { background: #2ecc71; }
        
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
        .edit-form { display: none; margin-top: 10px; background: #f9fafb; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb; }
    </style>
    <script>
        function toggleEdit(id) {
            var form = document.getElementById('edit-form-' + id);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</head>
<body>
<?php include 'sidebar_teacher.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">📈 Perkembangan Kanak-kanak</h2>
        
        <form method="GET" action="perkembangan.php">
            <label style="font-weight:bold; color:#555;">Pilih Pelajar:</label>
            <select name="student_id" required>
                <option value="">-- Pilih Kanak-kanak --</option>
                <?php 
                if ($students_result && $students_result->num_rows > 0) {
                    while($s = $students_result->fetch_assoc()) {
                        $sel = ($selected_student_id == $s['id']) ? 'selected' : '';
                        echo "<option value='{$s['id']}' $sel>{$s['full_name']} ({$s['module']})</option>";
                    }
                }
                ?>
            </select>
            <button type="submit" class="btn-search">Pilih</button>
        </form>
    </div>

    <?php echo $msg; ?>

    <div class="main-container">
        
        <!-- Panel Tambah Milestone -->
        <div class="panel left-panel">
            <h3>📝 Tambah Rekod Pemerhatian</h3>
            <form method="POST" action="perkembangan.php?student_id=<?php echo $selected_student_id; ?>">
                <div class="form-group">
                    <label>Pelajar</label>
                    <select name="student_id" required>
                        <option value="">-- Pilih Pelajar --</option>
                        <?php 
                        mysqli_data_seek($students_result, 0);
                        while($s = $students_result->fetch_assoc()) {
                            $sel = ($selected_student_id == $s['id']) ? 'selected' : '';
                            echo "<option value='{$s['id']}' $sel>{$s['full_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Kategori Perkembangan</label>
                    <select name="category_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php 
                        if ($categories_result && $categories_result->num_rows > 0) {
                            while($c = $categories_result->fetch_assoc()) {
                                echo "<option value='{$c['id']}'>{$c['category_name']} ({$c['age_group']})</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Milestone / Milestone Diperhatikan</label>
                    <input type="text" name="milestone_name" placeholder="Cth: Boleh berdiri sebelah kaki selama 5 saat" required>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="Belum Capai">Belum Capai</option>
                        <option value="Sedang Berkembang">Sedang Berkembang</option>
                        <option value="Telah Capai">Telah Capai</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tarikh Diperhatikan</label>
                    <input type="date" name="observed_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Ulasan & Nota</label>
                    <textarea name="notes" placeholder="Cth: Pelajar menunjukkan keyakinan tinggi namun masih memerlukan sokongan dinding..." rows="3"></textarea>
                </div>

                <button type="submit" name="tambah_pencapaian" class="btn-submit">💾 Simpan Pemerhatian</button>
            </form>
        </div>

        <!-- Panel Senarai Milestone Pelajar -->
        <div class="panel right-panel">
            <h3>📊 Rekod Perkembangan Kanak-kanak</h3>
            
            <?php if ($selected_student_id > 0): ?>
                <?php if (count($milestones_list) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 25%;">Kategori</th>
                                <th style="width: 35%;">Pemerhatian / Milestone</th>
                                <th style="width: 20%;">Status</th>
                                <th style="width: 20%;">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($milestones_list as $m): 
                                $badgeClass = 'badge-belum';
                                if ($m['status'] == 'Sedang Berkembang') $badgeClass = 'badge-sedang';
                                if ($m['status'] == 'Telah Capai') $badgeClass = 'badge-telah';
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($m['category_name']); ?></strong><br>
                                        <span style="font-size:11px; color:#888;">Observed: <?php echo date('d/m/Y', strtotime($m['observed_date'])); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($m['milestone_name']); ?></strong><br>
                                        <span style="font-size:12px; color:#555;"><?php echo nl2br(htmlspecialchars($m['notes'])); ?></span><br>
                                        <span style="font-size:10px; color:#999;">Oleh: <?php echo htmlspecialchars($m['teacher_name'] ?? 'Sistem'); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($m['status']); ?></span>
                                    </td>
                                    <td>
                                        <button onclick="toggleEdit(<?php echo $m['id']; ?>)" style="background:#84b6f4; color:white; font-size:11px; padding:5px 8px;">✏️ Kemaskini</button>
                                        
                                        <!-- Form Edit Dropdown -->
                                        <div id="edit-form-<?php echo $m['id']; ?>" class="edit-form">
                                            <form method="POST" action="perkembangan.php?student_id=<?php echo $selected_student_id; ?>">
                                                <input type="hidden" name="milestone_id" value="<?php echo $m['id']; ?>">
                                                
                                                <label style="font-size:11px; margin-bottom:2px; display:block;">Status Baru:</label>
                                                <select name="new_status" style="font-size:12px; padding:4px; width:100%; margin-bottom:5px;" required>
                                                    <option value="Belum Capai" <?php if($m['status']=='Belum Capai') echo 'selected'; ?>>Belum Capai</option>
                                                    <option value="Sedang Berkembang" <?php if($m['status']=='Sedang Berkembang') echo 'selected'; ?>>Sedang Berkembang</option>
                                                    <option value="Telah Capai" <?php if($m['status']=='Telah Capai') echo 'selected'; ?>>Telah Capai</option>
                                                </select>
                                                
                                                <label style="font-size:11px; margin-bottom:2px; display:block;">Nota Tambahan:</label>
                                                <textarea name="update_notes" style="font-size:12px; padding:4px; width:100%; margin-bottom:5px;" rows="2"><?php echo htmlspecialchars($m['notes']); ?></textarea>
                                                
                                                <button type="submit" name="kemaskini_status" style="background:#2ecc71; color:white; font-size:11px; padding:4px 8px; width:100%;">Simpan</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align:center; padding:30px; color:#888;">Tiada rekod perkembangan ditemui untuk pelajar ini. Sila rekodkan pemerhatian pertama di panel kiri.</p>
                <?php endif; ?>
            <?php else: ?>
                <p style="text-align:center; padding:50px; color:#888;">Sila pilih pelajar di bahagian atas untuk melihat rekod perkembangan.</p>
            <?php endif; ?>
        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>


</main>
</body>
</html>
