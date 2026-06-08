<?php
// senarai_pelajar.php
session_start();
require 'db.php';

// Pastikan hanya admin/pengetua yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// --- PROSES KELULUSAN (APPROVE), GRADUASI ATAU BUANG PELAJAR ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Luluskan Pelajar
    if (isset($_POST['approve_student'])) {
        $student_id = $_POST['student_id'];
        $conn->query("UPDATE students SET status='Active' WHERE id='$student_id'");
        
        $log_action = "Meluluskan pendaftaran pelajar ID: " . $student_id;
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $conn->query("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES ('{$_SESSION['user_id']}', '{$_SESSION['username']}', '$log_action', 'Success', '$ip_address')");
        
        echo "<script>alert('Pendaftaran pelajar berjaya diluluskan!'); window.location.href='senarai_pelajar.php';</script>";
    }
    
    // 2. Berhentikan Pelajar
    if (isset($_POST['buang_student'])) {
        $student_id = $_POST['student_id'];
        $conn->query("UPDATE students SET status='Withdrawn' WHERE id='$student_id'");
        
        $log_action = "Memberhentikan/Membuang pelajar ID: " . $student_id;
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $conn->query("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES ('{$_SESSION['user_id']}', '{$_SESSION['username']}', '$log_action', 'Success', '$ip_address')");
        
        echo "<script>alert('Status pelajar telah ditukar kepada Berhenti (Withdrawn).'); window.location.href='senarai_pelajar.php';</script>";
    }

    // 3. Graduasi Pelajar (TAMBAHAN BARU)
    if (isset($_POST['graduate_student'])) {
        $student_id = $_POST['student_id'];
        $conn->query("UPDATE students SET status='Graduated' WHERE id='$student_id'");
        
        $log_action = "Menamatkan pembelajaran (Graduated) pelajar ID: " . $student_id;
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $conn->query("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES ('{$_SESSION['user_id']}', '{$_SESSION['username']}', '$log_action', 'Success', '$ip_address')");
        
        echo "<script>alert('Tahniah! Pelajar telah ditukar status kepada Tamat Belajar (Graduated) dan dimasukkan ke Arkib.'); window.location.href='senarai_pelajar.php';</script>";
    }
}

// --- TAPISAN (FILTER) PENCARIAN ---
$filter_module = isset($_GET['module']) ? $_GET['module'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// By default, kita tak nak tunjuk Graduated/Withdrawn kat sini melainkan admin filter
$sql = "SELECT * FROM students WHERE 1=1";

if ($filter_module != '') {
    $sql .= " AND module = '$filter_module'";
}

if ($filter_status != '') {
    $sql .= " AND status = '$filter_status'";
} else {
    // Kalau tak filter status, tunjuk Pending & Active je (sebab Graduated/Withdrawn masuk arkib)
    $sql .= " AND status IN ('Pending', 'Active')";
}

$sql .= " ORDER BY created_at DESC"; 
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Senarai Pelajar - Pengetua</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f4f7f6; margin: 0; padding: 30px; display: flex; flex-direction: column; align-items: center; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; width: 100%; border-top: 8px solid #77dd77; }
        .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header-box h2 { color: #555; margin: 0; }
        .filter-form { background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee; display: flex; gap: 15px; align-items: center; margin-bottom: 20px; }
        select { padding: 8px; border-radius: 5px; border: 1px solid #ccc; }
        .btn-filter { background: #77dd77; color: white; border: none; padding: 8px 15px; border-radius: 5px; font-weight: bold; cursor: pointer; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background-color: #f2f2f2; padding: 12px; text-align: left; color: #333; border-bottom: 2px solid #ddd; }
        td { padding: 12px; border-bottom: 1px solid #eee; }

        .tag { padding: 4px 8px; border-radius: 5px; font-size: 11px; color: white; font-weight: bold; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; }
        .tag-kafa, .tag-kafacare { background: #b5ead7; color: #444; }

        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .status-Pending { background: #ffe4b5; color: #d2691e; }
        .status-Active { background: #d4edda; color: #155724; }
        .status-Withdrawn { background: #f8d7da; color: #721c24; }
        .status-Graduated { background: #d1ecf1; color: #0c5460; } /* Warna biru cair untuk Graduated */

        .btn-approve { background: #84b6f4; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; }
        .btn-reject { background: #ff6961; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; margin-left: 5px; }
        .btn-graduate { background: #b19cd9; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; margin-left: 5px; } /* Warna ungu/biru pastel */
        
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="container">
        <div class="header-box">
            <h2>🎓 Pengurusan & Senarai Pelajar</h2>
        </div>

        <form method="GET" action="senarai_pelajar.php" class="filter-form">
            <label style="font-weight:bold; color:#555;">Modul:</label>
            <select name="module">
                <option value="">Semua Modul</option>
                <option value="Taska" <?php if($filter_module=='Taska') echo 'selected'; ?>>Taska</option>
                <option value="Tadika" <?php if($filter_module=='Tadika') echo 'selected'; ?>>Tadika</option>
                <option value="KAFA Care" <?php if($filter_module=='KAFA Care') echo 'selected'; ?>>KAFA Care</option>
            </select>

            <label style="font-weight:bold; color:#555;">Status:</label>
            <select name="status">
                <option value="">Status Terkini (Pending & Active)</option>
                <option value="Pending" <?php if($filter_status=='Pending') echo 'selected'; ?>>Pending (Menunggu Kelulusan)</option>
                <option value="Active" <?php if($filter_status=='Active') echo 'selected'; ?>>Active</option>
                <option value="Graduated" <?php if($filter_status=='Graduated') echo 'selected'; ?>>Graduated (Alumni)</option>
                <option value="Withdrawn" <?php if($filter_status=='Withdrawn') echo 'selected'; ?>>Withdrawn (Berhenti)</option>
            </select>

            <button type="submit" class="btn-filter">Cari Pelajar</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Tarikh Daftar</th>
                    <th>Nama Pelajar</th>
                    <th>MyKid</th>
                    <th>Modul</th>
                    <th>Status</th>
                    <th>Tindakan (Pengetua)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $tagClass = 'tag-' . strtolower(str_replace(' ', '', $row['module'] ?? ''));
                    ?>
                        <tr>
                            <td><?php echo !empty($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '-'; ?></td>
                            <td style="font-weight:500; color:#333;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['mykid_number']); ?></td>
                            <td><span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($row['module']); ?></span></td>
                            <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo strtoupper($row['status']); ?></span></td>
                            
                            <td>
                                <form method="POST" action="senarai_pelajar.php" style="margin:0;">
                                    <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                    
                                    <?php if ($row['status'] == 'Pending'): ?>
                                        <button type="submit" name="approve_student" class="btn-approve">✔ Luluskan</button>
                                        <button type="submit" name="buang_student" class="btn-reject" onclick="return confirm('Pasti mahu membatalkan permohonan ini?');">✖ Batal</button>
                                    
                                    <?php elseif ($row['status'] == 'Active'): ?>
                                        <button type="submit" name="graduate_student" class="btn-graduate" onclick="return confirm('Pasti mahu tamatkan pembelajaran pelajar ini (Graduated)? Data akan dipindah ke Arkib.');">🎓 Graduasi</button>
                                        <button type="submit" name="buang_student" class="btn-reject" onclick="return confirm('Pasti mahu berhentikan pelajar ini (Withdrawn)? Data akan dipindah ke Arkib.');">✖ Berhenti</button>
                                    <?php endif; ?>
                                    
                                    </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 20px; color:#888;">Tiada rekod pelajar ditemui.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>


</main>
</body>
</html>