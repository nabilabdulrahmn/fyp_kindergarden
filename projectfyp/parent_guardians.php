<?php
// parent_guardians.php
// Penjaga Sah - Pengurusan untuk Ibu Bapa
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$msg = '';

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
$children_result = $conn->query($sql_children);
$children_list = array();
if ($children_result) {
    while ($row = $children_result->fetch_assoc()) {
        $children_list[] = $row;
    }
}

// Proses tambah penjaga
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_guardian'])) {
    $student_id = (int)$_POST['student_id'];
    $g_name = $conn->real_escape_string($_POST['guardian_name']);
    $g_relationship = $conn->real_escape_string($_POST['relationship']);
    $g_ic = $conn->real_escape_string($_POST['ic_number']);
    $g_phone = $conn->real_escape_string($_POST['phone_number']);

    // Sahkan student milik parent ini
    $verify = $conn->query("SELECT id FROM students WHERE id = $student_id AND parent_id = $parent_id LIMIT 1");
    if ($verify && $verify->num_rows > 0) {
        // Upload foto penjaga (pilihan)
        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['size'] > 0) {
            $target_dir = "uploads/guardians/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            $photo = $target_dir . time() . "_" . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
        }

        $sql_add = "INSERT INTO authorized_guardians (student_id, guardian_name, relationship, ic_number, phone_number, photo_path, added_by) 
                    VALUES ($student_id, '$g_name', '$g_relationship', '$g_ic', '$g_phone', '$photo', $user_id)";
        if ($conn->query($sql_add)) {
            $msg = "<div class='alert success'>Penjaga sah berjaya ditambah!</div>";
        } else {
            $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
        }
    } else {
        $msg = "<div class='alert error'>Akses tidak dibenarkan. Anak ini bukan milik anda.</div>";
    }
}

// Proses nyahaktifkan penjaga (bukan padam - keselamatan)
if (isset($_GET['deactivate'])) {
    $guard_id = (int)$_GET['deactivate'];
    // Sahkan penjaga ini ditambah oleh parent ini
    $verify_guard = $conn->query("SELECT ag.id FROM authorized_guardians ag 
                                  INNER JOIN students s ON ag.student_id = s.id 
                                  WHERE ag.id = $guard_id AND s.parent_id = $parent_id LIMIT 1");
    if ($verify_guard && $verify_guard->num_rows > 0) {
        $conn->query("UPDATE authorized_guardians SET is_active = 0 WHERE id = $guard_id");
        $msg = "<div class='alert success'>Penjaga telah dinyahaktifkan.</div>";
    } else {
        $msg = "<div class='alert error'>Akses tidak dibenarkan.</div>";
    }
}

// Proses aktifkan semula
if (isset($_GET['activate'])) {
    $guard_id = (int)$_GET['activate'];
    $verify_guard = $conn->query("SELECT ag.id FROM authorized_guardians ag 
                                  INNER JOIN students s ON ag.student_id = s.id 
                                  WHERE ag.id = $guard_id AND s.parent_id = $parent_id LIMIT 1");
    if ($verify_guard && $verify_guard->num_rows > 0) {
        $conn->query("UPDATE authorized_guardians SET is_active = 1 WHERE id = $guard_id");
        $msg = "<div class='alert success'>Penjaga telah diaktifkan semula.</div>";
    }
}

// Ambil senarai penjaga sah untuk anak-anak parent ini
$sql_guardians = "SELECT ag.*, s.full_name AS student_name 
                  FROM authorized_guardians ag 
                  INNER JOIN students s ON ag.student_id = s.id 
                  WHERE s.parent_id = $parent_id
                  ORDER BY ag.is_active DESC, s.full_name, ag.guardian_name";
$guardians = $conn->query($sql_guardians);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjaga Sah - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1100px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #b084f4; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }
        .alert { padding: 12px 15px; margin-bottom: 15px; border-radius: 8px; font-size: 14px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }

        .add-form { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .add-form h3 { margin-bottom: 15px; color: #333; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; font-weight: bold; color: #555; font-size: 13px; margin-bottom: 5px; }
        .form-group select, .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn-add { background: #b084f4; color: white; border: none; padding: 10px 30px; border-radius: 8px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        .btn-add:hover { background: #9068d4; }

        .guardian-card { background: white; border-radius: 16px; padding: 25px; margin-bottom: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); display: flex; gap: 20px; align-items: center; transition: 0.2s; }
        .guardian-card:hover { transform: translateY(-1px); }
        .guardian-card.inactive { opacity: 0.5; border-left: 4px solid #ccc; }
        .guardian-card.active { border-left: 4px solid #28a745; }
        .guardian-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #b084f4, #84b6f4); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold; flex-shrink: 0; overflow: hidden; }
        .guardian-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .guardian-info { flex: 1; }
        .guardian-name { font-size: 18px; font-weight: bold; color: #333; }
        .guardian-meta { font-size: 13px; color: #888; margin-top: 4px; }
        .guardian-student { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; background: #e8f4fd; color: #84b6f4; margin-top: 6px; }
        .guardian-status { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; }
        .status-active { background: #28a745; }
        .status-inactive { background: #ccc; }

        .btn-action { padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: bold; text-decoration: none; margin-left: 5px; }
        .btn-deactivate { background: #ff6961; color: white; }
        .btn-activate { background: #28a745; color: white; }

        .empty-state { text-align: center; padding: 40px; color: #aaa; background: white; border-radius: 16px; }

        .security-notice { background: #fff3cd; border: 1px solid #ffc107; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; font-size: 13px; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2>👤 Penjaga Sah</h2>
                <div class="subtitle">Urus senarai penjaga yang dibenarkan mengambil anak anda</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <?php echo $msg; ?>

        <div class="security-notice">
            ⚠️ <strong>Peringatan Keselamatan:</strong> Hanya penjaga yang disenaraikan dan aktif di sini dibenarkan mengambil anak anda dari sekolah. Sila pastikan maklumat sentiasa dikemas kini.
        </div>

        <!-- Form Tambah Penjaga -->
        <div class="add-form">
            <h3>➕ Tambah Penjaga Sah Baru</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Untuk Anak</label>
                        <select name="student_id" required>
                            <option value="">-- Pilih Anak --</option>
                            <?php for ($i = 0; $i < count($children_list); $i++): ?>
                                <option value="<?php echo $children_list[$i]['id']; ?>">
                                    <?php echo htmlspecialchars($children_list[$i]['full_name']); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nama Penuh Penjaga</label>
                        <input type="text" name="guardian_name" required placeholder="Nama penuh">
                    </div>
                    <div class="form-group">
                        <label>Hubungan</label>
                        <select name="relationship" required>
                            <option value="">-- Pilih --</option>
                            <option value="Datuk/Nenek">Datuk/Nenek</option>
                            <option value="Pak Cik/Mak Cik">Pak Cik/Mak Cik</option>
                            <option value="Abang/Kakak">Abang/Kakak</option>
                            <option value="Pengasuh">Pengasuh</option>
                            <option value="Jiran">Jiran</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>No. IC</label>
                        <input type="text" name="ic_number" required placeholder="Cth: 800101-14-1234">
                    </div>
                    <div class="form-group">
                        <label>No. Telefon</label>
                        <input type="text" name="phone_number" required placeholder="Cth: 012-3456789">
                    </div>
                    <div class="form-group">
                        <label>Foto Penjaga (Pilihan)</label>
                        <input type="file" name="photo" accept=".jpg,.png,.jpeg">
                    </div>
                </div>
                <button type="submit" name="add_guardian" class="btn-add">✅ Tambah Penjaga</button>
            </form>
        </div>

        <!-- Senarai Penjaga -->
        <h3 style="margin-bottom: 15px; color: #333;">📋 Senarai Penjaga Sah</h3>
        <?php if ($guardians && $guardians->num_rows > 0): ?>
            <?php while ($g = $guardians->fetch_assoc()): ?>
                <div class="guardian-card <?php echo $g['is_active'] ? 'active' : 'inactive'; ?>">
                    <div class="guardian-avatar">
                        <?php if ($g['photo_path'] && file_exists($g['photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($g['photo_path']); ?>" alt="Foto">
                        <?php else: ?>
                            <?php echo strtoupper(substr($g['guardian_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="guardian-info">
                        <div class="guardian-name"><?php echo htmlspecialchars($g['guardian_name']); ?></div>
                        <div class="guardian-meta">
                            🏷️ <?php echo htmlspecialchars($g['relationship']); ?> | 
                            🪪 <?php echo htmlspecialchars($g['ic_number']); ?> | 
                            📞 <?php echo htmlspecialchars($g['phone_number']); ?>
                        </div>
                        <span class="guardian-student">👧 <?php echo htmlspecialchars($g['student_name']); ?></span>
                    </div>
                    <div style="text-align: center;">
                        <span class="guardian-status <?php echo $g['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $g['is_active'] ? 'Aktif' : 'Tidak Aktif'; ?>
                        </span>
                        <div style="margin-top: 10px;">
                            <?php if ($g['is_active']): ?>
                                <a href="?deactivate=<?php echo $g['id']; ?>" class="btn-action btn-deactivate" onclick="return confirm('Pasti mahu nyahaktifkan penjaga ini?');">Nyahaktif</a>
                            <?php else: ?>
                                <a href="?activate=<?php echo $g['id']; ?>" class="btn-action btn-activate">Aktifkan</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 50px; margin-bottom: 15px;">👤</div>
                <h3>Tiada Penjaga Sah Berdaftar</h3>
                <p style="margin-top: 10px;">Sila tambahkan penjaga yang dibenarkan mengambil anak anda.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
