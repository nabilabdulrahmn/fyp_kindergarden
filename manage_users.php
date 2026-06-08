<?php
// manage_users.php
// Urus Pengguna Sistem - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

// Proses Padam Pengguna
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    if ($del_id != $_SESSION['user_id']) { // Elak padam diri sendiri
        $conn->query("DELETE FROM users WHERE id = $del_id");
        $msg = "<div class='alert success'>Pengguna berjaya dipadam.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: Anda tidak boleh padam akaun anda sendiri.</div>";
    }
}

// Proses Tukar Peranan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_role'])) {
    $user_id_to_update = (int)$_POST['user_id'];
    $new_role = $conn->real_escape_string($_POST['role']);
    
    if ($user_id_to_update != $_SESSION['user_id']) {
        $conn->query("UPDATE users SET role = '$new_role' WHERE id = $user_id_to_update");
        $msg = "<div class='alert success'>Peranan pengguna berjaya dikemas kini.</div>";
    }
}

// Ambil senarai pengguna yang aktif/diluluskan
$sql = "SELECT * FROM users WHERE status = 'approved' ORDER BY role ASC, created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Urus Pengguna Sistem - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #3f51b5; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .role-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: white; display: inline-block; }
        .role-admin { background: #e91e63; }
        .role-teacher { background: #ff9800; }
        .role-parent { background: #2196f3; }
        .role-pengetua { background: #9c27b0; }
        
        .btn-del { background: #f44336; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; border: none; cursor: pointer; }
        .btn-save { background: #3f51b5; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        select { padding: 5px; border-radius: 4px; border: 1px solid #ccc; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>👥 Pengurusan Akses & Peranan Pengguna</h2>
        <?php echo $msg; ?>
        <p style="color:#666; font-size:14px; margin-bottom:20px;">Hanya akaun yang telah diluluskan dipaparkan di sini. Untuk meluluskan akaun baru, sila pergi ke <a href="lulus_pendaftaran.php">Kelulusan Pendaftaran</a>.</p>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Maklumat Pengguna</th>
                    <th>Peranan (Role)</th>
                    <th>Tukar Peranan</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['username']); ?></strong><br>
                                <span style="font-size:12px; color:#666;"><?php echo htmlspecialchars($row['email'] ?? 'Tiada Emel'); ?></span>
                            </td>
                            <td>
                                <?php 
                                    $roleClass = 'role-' . strtolower($row['role']);
                                ?>
                                <span class="role-badge <?php echo $roleClass; ?>"><?php echo htmlspecialchars($row['role']); ?></span>
                            </td>
                            <td>
                                <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: flex; gap: 5px; align-items: center;">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <select name="role">
                                        <option value="parent" <?php echo $row['role'] == 'parent' ? 'selected' : ''; ?>>Parent</option>
                                        <option value="teacher" <?php echo $row['role'] == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                        <option value="pengetua" <?php echo $row['role'] == 'pengetua' ? 'selected' : ''; ?>>Pengetua</option>
                                        <option value="admin" <?php echo $row['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <button type="submit" name="update_role" class="btn-save">Simpan</button>
                                </form>
                                <?php else: ?>
                                    <span style="font-size:12px; color:#aaa; font-style:italic;">Akaun Anda</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn-del" onclick="return confirm('AMARAN: Memadam pengguna ini mungkin memadamkan semua rekod berkaitan mereka! Teruskan?');">Padam Akaun</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">Tiada rekod.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
