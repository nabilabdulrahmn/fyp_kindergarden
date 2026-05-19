<?php
// teacher_facility_request.php
// Aduan Kerosakan Fasiliti - Guru
session_start();
require 'db.php';

// Pastikan hanya cikgu yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$msg = '';

// --- PROSES HANTAR ADUAN FASILITI (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hantar_aduan'])) {
    $location = $conn->real_escape_string($_POST['location']);
    $issue_description = $conn->real_escape_string($_POST['issue_description']);
    $priority = $conn->real_escape_string($_POST['priority']);
    $photo_path = '';

    // Handle Upload File (Gambar) jika ada
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed_ext)) {
            $upload_dir = 'uploads/facility_requests/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_name = uniqid('fac_') . '.' . $ext;
            $dest_path = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['photo']['tmp_path'] ?? $_FILES['photo']['tmp_name'], $dest_path)) {
                $photo_path = $dest_path;
            }
        }
    }

    if (!empty($location) && !empty($issue_description)) {
        $sql = "INSERT INTO facility_requests (requested_by, location, issue_description, priority, status, photo_path) 
                VALUES ('$user_id', '$location', '$issue_description', '$priority', 'Pending', '$photo_path')";
        if ($conn->query($sql)) {
            $msg = "<div class='alert success'>Aduan kerosakan fasiliti berjaya dihantar ke pihak Admin!</div>";
        } else {
            $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
        }
    } else {
        $msg = "<div class='alert error'>Sila isi semua ruangan wajib.</div>";
    }
}

// --- AMBIL SEJARAH ADUAN GURU INI SAHAJA (LIMITASI CAPASITI AKSES GURU) ---
$my_requests = [];
$req_res = $conn->query("SELECT * FROM facility_requests WHERE requested_by = '$user_id' ORDER BY created_at DESC");
if ($req_res) {
    while ($row = $req_res->fetch_assoc()) {
        $my_requests[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Aduan Kerosakan Fasiliti - Guru</title>
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
            border-left: 5px solid #ffb347;
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
        
        select, input[type="text"], input[type="file"], textarea {
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
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        th { background-color: #f9f9f9; padding: 10px; text-align: left; color: #444; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-pending { background: #ffe4b5; color: #d2691e; }
        .badge-inprogress { background: #b5ead7; color: #0e6251; }
        .badge-completed { background: #d4edda; color: #155724; }
        
        .prio-Urgent { color: #e74c3c; font-weight: bold; }
        .prio-High { color: #e67e22; font-weight: bold; }
        .prio-Medium { color: #f1c40f; font-weight: bold; }
        .prio-Low { color: #3498db; }
        
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
    </style>
</head>
<body>

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">🔧 Aduan Kerosakan Fasiliti & Penyelenggaraan</h2>
    </div>

    <?php echo $msg; ?>

    <div class="main-container">
        
        <!-- Panel Tulis Aduan -->
        <div class="panel left-panel">
            <h3>✍️ Borang Aduan Baru</h3>
            <form method="POST" action="teacher_facility_request.php" enctype="multipart/form-data">
                
                <div class="form-group">
                    <label>Lokasi Kerosakan</label>
                    <input type="text" name="location" placeholder="Cth: Bilik Darjah Tadika A, Tandas Blok B, Taman Permainan" required>
                </div>

                <div class="form-group">
                    <label>Tahap Keutamaan (Priority)</label>
                    <select name="priority" required>
                        <option value="Low">Rendah (Low)</option>
                        <option value="Medium" selected>Sederhana (Medium)</option>
                        <option value="High">Tinggi (High)</option>
                        <option value="Urgent">Segera (Urgent)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Keterangan Kerosakan / Isu</label>
                    <textarea name="issue_description" placeholder="Sila terangkan kerosakan atau bantuan penyelenggaraan yang diperlukan secara terperinci..." rows="5" required></textarea>
                </div>

                <div class="form-group">
                    <label>Muat Naik Gambar Bukti (Pilihan)</label>
                    <input type="file" name="photo" accept="image/*">
                </div>

                <button type="submit" name="hantar_aduan" class="btn-submit">💾 Hantar Aduan Fasiliti</button>
            </form>
        </div>

        <!-- Panel Sejarah Aduan -->
        <div class="panel right-panel">
            <h3>🕒 Sejarah Aduan Kerosakan Anda</h3>
            
            <div style="max-height: 550px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Tarikh</th>
                            <th>Lokasi & Butiran</th>
                            <th>Tahap</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($my_requests) > 0): ?>
                            <?php foreach ($my_requests as $req): 
                                $statusClass = 'badge-pending';
                                if ($req['status'] == 'In Progress') $statusClass = 'badge-inprogress';
                                if ($req['status'] == 'Completed') $statusClass = 'badge-completed';
                            ?>
                                <tr>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($req['created_at'])); ?><br>
                                        <span style="font-size:10px; color:#999;"><?php echo date('H:i A', strtotime($req['created_at'])); ?></span>
                                    </td>
                                    <td>
                                        <strong>📍 <?php echo htmlspecialchars($req['location']); ?></strong><br>
                                        <span style="font-size:12px; color:#555;"><?php echo nl2br(htmlspecialchars($req['issue_description'])); ?></span>
                                        <?php if(!empty($req['photo_path'])): ?>
                                            <div style="margin-top:8px;">
                                                <a href="<?php echo htmlspecialchars($req['photo_path']); ?>" target="_blank" style="font-size:11px; color:#84b6f4; font-weight:bold;">🖼️ Lihat Gambar</a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if($req['status'] == 'Completed' && !empty($req['resolved_at'])): ?>
                                            <div style="margin-top:5px; font-size:11px; color:#2ecc71;">
                                                ✓ Selesai pada: <?php echo date('d/m/Y', strtotime($req['resolved_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="prio-<?php echo $req['priority']; ?>"><?php echo htmlspecialchars($req['priority']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; color:#888; padding:30px;">Tiada rekod aduan kerosakan dibuat.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>

</body>
</html>
