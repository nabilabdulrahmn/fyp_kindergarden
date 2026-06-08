<?php
// admin_announcements.php
// Pengurusan Pengumuman Sekolah - Admin
session_start();
require 'db.php';

// Sahkan Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Proses Tambah Pengumuman
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_announcement'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    $scope = $conn->real_escape_string($_POST['scope']);
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    // class_id hanya relevan jika scope = 'class'
    $class_id = ($scope == 'class' && !empty($_POST['class_id'])) ? (int)$_POST['class_id'] : 'NULL';

    $sql = "INSERT INTO announcements (author_id, title, content, scope, class_id, is_pinned) 
            VALUES ($user_id, '$title', '$content', '$scope', $class_id, $is_pinned)";
    
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Pengumuman berjaya ditambah!</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

// Proses Padam Pengumuman
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM announcements WHERE id = $del_id");
    header("Location: admin_announcements.php");
    exit();
}

// Ambil senarai pengumuman
$sql_announcements = "SELECT a.*, u.username, c.class_name 
                      FROM announcements a 
                      LEFT JOIN users u ON a.author_id = u.id 
                      LEFT JOIN classes c ON a.class_id = c.id 
                      ORDER BY a.is_pinned DESC, a.created_at DESC";
$result = $conn->query($sql_announcements);

// Ambil senarai kelas untuk dropdown
$classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pengumuman Sekolah - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #84b6f4; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"], textarea, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #84b6f4; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #6a9bd8; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; color: white; }
        .bg-global { background: #ffb347; }
        .bg-class { background: #77dd77; }
        .bg-pinned { background: #ff6961; margin-left: 5px; }
        
        .btn-sm { padding: 5px 10px; font-size: 12px; text-decoration: none; border-radius: 4px; }
        .btn-del { background: #ff6961; color: white; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
    <script>
        function toggleClassSelect() {
            var scope = document.getElementById('scope').value;
            var classSelect = document.getElementById('class-select-group');
            if(scope === 'class') {
                classSelect.style.display = 'block';
            } else {
                classSelect.style.display = 'none';
            }
        }
    </script>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>📢 Pengurusan Pengumuman Sekolah</h2>
        <?php echo $msg; ?>
        
        <!-- Form Tambah Pengumuman -->
        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h3 style="margin-top:0; color:#555;">Tulis Pengumuman Baru</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Tajuk Pengumuman</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Kandungan</label>
                    <textarea name="content" rows="4" required></textarea>
                </div>
                <div style="display: flex; gap: 20px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Skop (Sasaran)</label>
                        <select name="scope" id="scope" onchange="toggleClassSelect()">
                            <option value="global">Global (Semua Ibu Bapa & Guru)</option>
                            <option value="class">Kelas Spesifik Sahaja</option>
                        </select>
                    </div>
                    <div class="form-group" id="class-select-group" style="flex: 1; display: none;">
                        <label>Pilih Kelas</label>
                        <select name="class_id">
                            <option value="">-- Pilih Kelas --</option>
                            <?php while($c = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="is_pinned" value="1"> 📌 Pin di atas (Pengumuman Penting)</label>
                </div>
                <button type="submit" name="add_announcement">Siar Pengumuman</button>
            </form>
        </div>

        <!-- Senarai Pengumuman -->
        <h3>Senarai Pengumuman Semasa</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Tarikh</th>
                    <th style="width: 45%;">Pengumuman</th>
                    <th style="width: 20%;">Skop</th>
                    <th style="width: 15%;">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></strong><br>
                                <span style="font-size:11px; color:#888;"><?php echo date('H:i A', strtotime($row['created_at'])); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                <?php if($row['is_pinned']): ?>
                                    <span class="badge bg-pinned">📌 Pinned</span>
                                <?php endif; ?>
                                <div style="margin-top: 5px; color: #555; font-size: 13px;">
                                    <?php echo nl2br(htmlspecialchars($row['content'])); ?>
                                </div>
                                <div style="margin-top: 5px; font-size: 11px; color: #aaa;">Oleh: <?php echo htmlspecialchars($row['username']); ?></div>
                            </td>
                            <td>
                                <?php if($row['scope'] == 'global'): ?>
                                    <span class="badge bg-global">Global</span>
                                <?php else: ?>
                                    <span class="badge bg-class">Kelas: <?php echo htmlspecialchars($row['class_name'] ?? '-'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?delete=<?php echo $row['id']; ?>" class="btn-sm btn-del" onclick="return confirm('Pasti mahu padam pengumuman ini?');">Padam</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:20px;">Tiada pengumuman direkodkan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
