<?php
// admin_staff.php
// Direktori & Pengurusan Staf - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

// --- PROSES KELULUSAN MODUL GURU (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['approve_module_request']) || isset($_POST['reject_module_request']))) {
    $tm_id = (int)$_POST['tm_id'];
    $req_type = $_POST['req_type']; // 'Pending_Register' atau 'Pending_Drop'
    $mod_name = $conn->real_escape_string($_POST['module_name']);
    $teacher_name = $conn->real_escape_string($_POST['teacher_name']);
    
    if (isset($_POST['approve_module_request'])) {
        if ($req_type == 'Pending_Register') {
            $conn->query("UPDATE teacher_modules SET status = 'Approved' WHERE id = $tm_id");
            $msg = "<div class='alert success'>Permohonan pendaftaran modul $mod_name oleh $teacher_name telah diluluskan.</div>";
        } elseif ($req_type == 'Pending_Drop') {
            $conn->query("DELETE FROM teacher_modules WHERE id = $tm_id");
            $msg = "<div class='alert success'>Permohonan penguguran modul $mod_name oleh $teacher_name telah diluluskan. Modul digugurkan.</div>";
        }
    } elseif (isset($_POST['reject_module_request'])) {
        if ($req_type == 'Pending_Register') {
            $conn->query("UPDATE teacher_modules SET status = 'Rejected' WHERE id = $tm_id");
            $msg = "<div class='alert success'>Permohonan pendaftaran modul $mod_name oleh $teacher_name telah ditolak.</div>";
        } elseif ($req_type == 'Pending_Drop') {
            $conn->query("UPDATE teacher_modules SET status = 'Approved' WHERE id = $tm_id");
            $msg = "<div class='alert success'>Permohonan penguguran modul $mod_name oleh $teacher_name telah ditolak. Modul kekal aktif.</div>";
        }
    }
}

// --- PROSES KELULUSAN KELAS GURU (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['approve_class_request']) || isset($_POST['reject_class_request']))) {
    $tcr_id = (int)$_POST['tcr_id'];
    $teacher_id = (int)$_POST['teacher_id'];
    $class_id = (int)$_POST['class_id'];
    $req_type = $_POST['req_type']; // 'Add' or 'Drop'
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $teacher_name = $conn->real_escape_string($_POST['teacher_name']);
    
    if (isset($_POST['approve_class_request'])) {
        if ($req_type == 'Add') {
            // Set guru untuk kelas ini
            $conn->query("UPDATE classes SET teacher_id = $teacher_id WHERE id = $class_id");
            $conn->query("UPDATE teacher_class_requests SET status = 'Approved' WHERE id = $tcr_id");
            $msg = "<div class='alert success'>Permohonan menetapkan kelas $class_name untuk $teacher_name telah diluluskan.</div>";
        } elseif ($req_type == 'Drop') {
            // Kosongkan guru untuk kelas ini
            $conn->query("UPDATE classes SET teacher_id = NULL WHERE id = $class_id AND teacher_id = $teacher_id");
            $conn->query("UPDATE teacher_class_requests SET status = 'Approved' WHERE id = $tcr_id");
            $msg = "<div class='alert success'>Permohonan mengugurkan kelas $class_name oleh $teacher_name telah diluluskan.</div>";
        }
    } elseif (isset($_POST['reject_class_request'])) {
        $conn->query("UPDATE teacher_class_requests SET status = 'Rejected' WHERE id = $tcr_id");
        $msg = "<div class='alert success'>Permohonan kelas $class_name oleh $teacher_name telah ditolak.</div>";
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $ic_number = $conn->real_escape_string($_POST['ic_number']);
    $position = $conn->real_escape_string($_POST['position']);
    $phone_number = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $hire_date = $conn->real_escape_string($_POST['hire_date']);
    $department = $conn->real_escape_string($_POST['department']);
    $employment_type = $conn->real_escape_string($_POST['employment_type']);
    
    $sql = "INSERT INTO staff (full_name, ic_number, position, phone_number, email, hire_date, department, employment_type) 
            VALUES ('$full_name', '$ic_number', '$position', '$phone_number', '$email', '$hire_date', '$department', '$employment_type')";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Rekod staf baru berjaya didaftarkan.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

$sql_staff = "SELECT * FROM staff ORDER BY status ASC, full_name ASC";
$result = $conn->query($sql_staff);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pengurusan Staf - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #3f51b5; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"], input[type="email"], input[type="date"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #3f51b5; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #303f9f; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-active { background: #4caf50; }
        .bg-inactive { background: #f44336; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>👥 Direktori & Pengurusan Staf</h2>
        <?php echo $msg; ?>
        
        <div style="background: #e8eaf6; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #c5cae9;">
            <h3 style="margin-top:0; color:#283593;">Pendaftaran Staf / Guru Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Nama Penuh</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>No. Kad Pengenalan</label>
                        <input type="text" name="ic_number" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jawatan</label>
                        <input type="text" name="position" required placeholder="Cth: Guru Tadika, Kerani">
                    </div>
                    <div class="form-group">
                        <label>No. Telefon</label>
                        <input type="text" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label>Emel</label>
                        <input type="email" name="email">
                    </div>
                    <div class="form-group">
                        <label>Tarikh Mula Kerja</label>
                        <input type="date" name="hire_date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jabatan / Seksyen</label>
                        <select name="department" required>
                            <option value="Teaching">Teaching (Pengajaran / Guru)</option>
                            <option value="Admin">Admin (Pentadbiran)</option>
                            <option value="Support">Support (Sokongan)</option>
                            <option value="Kitchen">Kitchen (Dapur)</option>
                            <option value="Transport">Transport (Pengangkutan)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jenis Lantikan</label>
                        <select name="employment_type" required>
                            <option value="Full-Time">Full-Time (Sepenuh Masa)</option>
                            <option value="Part-Time">Part-Time (Sambilan)</option>
                            <option value="Contract">Contract (Kontrak)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_staff">+ Daftar Staf</button>
            </form>
        </div>

        <!-- Panel Kelulusan Modul Guru -->
        <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffe0b2;">
            <h3 style="margin-top:0; color:#e67e22;">📋 Kelulusan Permohonan Modul Guru</h3>
            <?php
            $sql_reqs = "SELECT tm.*, t.full_name AS teacher_name 
                         FROM teacher_modules tm 
                         JOIN teachers t ON tm.teacher_id = t.id 
                         WHERE tm.status IN ('Pending_Register', 'Pending_Drop') 
                         ORDER BY tm.created_at ASC";
            $res_reqs = $conn->query($sql_reqs);
            ?>
            <?php if ($res_reqs && $res_reqs->num_rows > 0): ?>
                <table style="width:100%; border-collapse:collapse; background:white; font-size:13px; border-radius:6px; overflow:hidden;">
                    <thead>
                        <tr style="background:#ffe0b2; color:#d35400;">
                            <th style="padding:10px; text-align:left;">Nama Guru</th>
                            <th style="padding:10px; text-align:left;">Modul</th>
                            <th style="padding:10px; text-align:left;">Jenis Permohonan</th>
                            <th style="padding:10px; text-align:center;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($r_row = $res_reqs->fetch_assoc()): 
                            $req_display = ($r_row['status'] == 'Pending_Register') ? 'Mendaftar Modul' : 'Mengugur Modul';
                            $req_color = ($r_row['status'] == 'Pending_Register') ? 'color:#27ae60; font-weight:bold;' : 'color:#e74c3c; font-weight:bold;';
                        ?>
                            <tr>
                                <td style="padding:10px; border-bottom:1px solid #ffe0b2;"><strong><?php echo htmlspecialchars($r_row['teacher_name']); ?></strong></td>
                                <td style="padding:10px; border-bottom:1px solid #ffe0b2;"><span style="font-weight:600;"><?php echo htmlspecialchars($r_row['module']); ?></span></td>
                                <td style="padding:10px; border-bottom:1px solid #ffe0b2; <?php echo $req_color; ?>"><?php echo $req_display; ?></td>
                                <td style="padding:10px; border-bottom:1px solid #ffe0b2; text-align:center;">
                                    <form method="POST" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="tm_id" value="<?php echo $r_row['id']; ?>">
                                        <input type="hidden" name="req_type" value="<?php echo $r_row['status']; ?>">
                                        <input type="hidden" name="module_name" value="<?php echo $r_row['module']; ?>">
                                        <input type="hidden" name="teacher_name" value="<?php echo $r_row['teacher_name']; ?>">
                                        <button type="submit" name="approve_module_request" style="background:#2ecc71; color:white; border:none; padding:5px 10px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:11px;">Approve</button>
                                        <button type="submit" name="reject_module_request" style="background:#e74c3c; color:white; border:none; padding:5px 10px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:11px; margin-left:5px;">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="margin:0; color:#666; font-style:italic; font-size:13px; text-align:center; padding:10px 0;">Tiada permohonan modul daripada guru buat masa ini.</p>
            <?php endif; ?>
        </div>

        <!-- Panel Kelulusan Kelas Guru -->
        <div style="background:#e8f5e9; padding:20px; border-radius:8px; margin-bottom:30px; border:1px solid #a5d6a7;">
            <h3 style="margin-top:0; color:#2e7d32;">🏫 Kelulusan Permohonan Kelas Guru</h3>
            <?php
            $sql_class_reqs = "SELECT tcr.*, t.full_name AS teacher_name, c.class_name, c.module AS class_module
                               FROM teacher_class_requests tcr
                               JOIN teachers t ON tcr.teacher_id = t.id
                               JOIN classes c ON tcr.class_id = c.id
                               WHERE tcr.status = 'Pending'
                               ORDER BY tcr.created_at ASC";
            $res_class_reqs = $conn->query($sql_class_reqs);
            ?>
            <?php if ($res_class_reqs && $res_class_reqs->num_rows > 0): ?>
                <table style="width:100%; border-collapse:collapse; background:white; font-size:13px; border-radius:6px; overflow:hidden;">
                    <thead>
                        <tr style="background:#c8e6c9; color:#1b5e20;">
                            <th style="padding:10px; text-align:left;">Nama Guru</th>
                            <th style="padding:10px; text-align:left;">Kelas</th>
                            <th style="padding:10px; text-align:left;">Modul</th>
                            <th style="padding:10px; text-align:left;">Jenis</th>
                            <th style="padding:10px; text-align:left;">Tarikh</th>
                            <th style="padding:10px; text-align:center;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($cr = $res_class_reqs->fetch_assoc()):
                            $cr_color = ($cr['request_type'] == 'Add') ? 'color:#27ae60; font-weight:bold;' : 'color:#e74c3c; font-weight:bold;';
                            $cr_label = ($cr['request_type'] == 'Add') ? '➕ Tambah Kelas' : '➖ Gugur Kelas';
                        ?>
                            <tr>
                                <td style="padding:10px; border-bottom:1px solid #c8e6c9;"><strong><?php echo htmlspecialchars($cr['teacher_name']); ?></strong></td>
                                <td style="padding:10px; border-bottom:1px solid #c8e6c9; font-weight:600;"><?php echo htmlspecialchars($cr['class_name']); ?></td>
                                <td style="padding:10px; border-bottom:1px solid #c8e6c9;"><span style="background:#e8eaf6; color:#3f51b5; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:bold;"><?php echo htmlspecialchars($cr['class_module']); ?></span></td>
                                <td style="padding:10px; border-bottom:1px solid #c8e6c9; <?php echo $cr_color; ?>"><?php echo $cr_label; ?></td>
                                <td style="padding:10px; border-bottom:1px solid #c8e6c9; color:#777; font-size:11px;"><?php echo date('d/m/Y H:i', strtotime($cr['created_at'])); ?></td>
                                <td style="padding:10px; border-bottom:1px solid #c8e6c9; text-align:center;">
                                    <form method="POST" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="tcr_id" value="<?php echo $cr['id']; ?>">
                                        <input type="hidden" name="teacher_id" value="<?php echo $cr['teacher_id']; ?>">
                                        <input type="hidden" name="class_id" value="<?php echo $cr['class_id']; ?>">
                                        <input type="hidden" name="req_type" value="<?php echo $cr['request_type']; ?>">
                                        <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($cr['class_name']); ?>">
                                        <input type="hidden" name="teacher_name" value="<?php echo htmlspecialchars($cr['teacher_name']); ?>">
                                        <button type="submit" name="approve_class_request" style="background:#2ecc71; color:white; border:none; padding:5px 12px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:11px;">✔ Lulus</button>
                                        <button type="submit" name="reject_class_request" style="background:#e74c3c; color:white; border:none; padding:5px 12px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:11px; margin-left:5px;">✘ Tolak</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="margin:0; color:#666; font-style:italic; font-size:13px; text-align:center; padding:10px 0;">Tiada permohonan kelas daripada guru buat masa ini.</p>
            <?php endif; ?>
        </div>

        <h3>Senarai Direktori Staf</h3>

        <table>
            <thead>
                <tr>
                    <th>Nama & Jawatan</th>
                    <th>Maklumat Perhubungan</th>
                    <th>Tarikh Lantikan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                <span style="font-size:12px; color:#3f51b5; font-weight:bold;"><?php echo htmlspecialchars($row['position']); ?></span>
                                <span style="font-size:11px; color:#666;"> (<?php echo htmlspecialchars($row['department']); ?> — <?php echo htmlspecialchars($row['employment_type']); ?>)</span><br>
                                <span style="font-size:11px; color:#aaa;">IC: <?php echo htmlspecialchars($row['ic_number']); ?></span>
                            </td>
                            <td>
                                📞 <?php echo htmlspecialchars($row['phone_number'] ?? '-'); ?><br>
                                📧 <?php echo htmlspecialchars($row['email'] ?? '-'); ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['hire_date'])); ?></td>
                            <td>
                                <?php if ($row['status'] == 'Active'): ?>
                                    <span class="badge bg-active">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-inactive">Berhenti</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Tiada rekod staf direkodkan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
