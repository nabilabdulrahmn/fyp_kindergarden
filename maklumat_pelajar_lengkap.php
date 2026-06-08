<?php
// maklumat_pelajar_lengkap.php
session_start();
require 'db.php';
require_once 'auth_guard.php';

// Pastikan admin ATAU teacher yang boleh akses (Kemas kini baru)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'teacher')) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];
$approved_mods = [];
$teacher_id = 0;

if ($user_role == 'teacher') {
    $teacher_id = dapatkan_teacher_id($conn);
    $approved_mods = dapatkan_modul_diluluskan($conn, $teacher_id);
    
    if (empty($approved_mods)) {
        $sql = "SELECT s.*, p.full_name AS parent_name, p.phone_number AS parent_phone, p.address, u.email AS parent_email 
                FROM students s 
                LEFT JOIN parents p ON s.parent_id = p.id 
                LEFT JOIN users u ON p.user_id = u.id 
                WHERE 1=0";
    } else {
        $modules_list = "'" . implode("','", array_map(function($m) use ($conn) { return mysqli_real_escape_string($conn, $m); }, $approved_mods)) . "'";
        $sql = "SELECT DISTINCT s.*, p.full_name AS parent_name, p.phone_number AS parent_phone, p.address, u.email AS parent_email
                FROM students s
                INNER JOIN student_classes sc ON s.id = sc.student_id
                INNER JOIN classes c ON sc.class_id = c.id
                LEFT JOIN parents p ON s.parent_id = p.id
                LEFT JOIN users u ON p.user_id = u.id
                WHERE c.teacher_id = $teacher_id 
                  AND s.module IN ($modules_list) 
                  AND c.module IN ($modules_list)
                ORDER BY s.module, s.full_name ASC";
    }
} else {
    // Admin sees everything
    $sql = "SELECT s.*, p.full_name AS parent_name, p.phone_number AS parent_phone, p.address, u.email AS parent_email
            FROM students s
            LEFT JOIN parents p ON s.parent_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            ORDER BY s.module, s.full_name ASC";
}

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Profil Lengkap Pelajar - Sistem Childcare</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 30px; }
        .container { 
            background: white; padding: 30px; border-radius: 12px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); width: 100%; border-top: 8px solid #84b6f4; 
        }
        h2 { color: #555; margin-bottom: 25px; text-align: center; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        
        .tag { padding: 3px 8px; border-radius: 5px; font-size: 10px; color: white; font-weight: bold; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; }
        .tag-kafa, .tag-kafacare { background: #b5ead7; color: #444; }

        .health-box { background: #fff5f5; padding: 5px; border-radius: 4px; border: 1px solid #ffdada; color: #c53030; font-size: 12px; }
        .no-allergy { color: #999; font-style: italic; }
        
        .info-box { font-size: 11px; color: #666; margin-top: 5px; background: #fafafa; padding: 5px; border-radius: 4px; border: 1px solid #eee;}
        
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
        
        @media print {
            .btn-back, .no-print { display: none; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>
<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') include 'sidebar_admin.php';
    elseif ($_SESSION['role'] === 'teacher') include 'sidebar_teacher.php';
}
?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="container">
        <h2>📋 Profil & Maklumat Lengkap Pelajar</h2>
        
        <!-- Tapisan / Filters -->
        <div class="no-print filter-card" style="background: #fdfdfd; border: 1px solid #e0e3e5; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; gap: 20px; align-items: center; justify-content: space-between; flex-wrap: wrap; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                <div class="filter-group" style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-weight: bold; font-size: 13px; color: #555;">Kategori Pelajar:</label>
                    <select id="filter-module" onchange="applyFilters()" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 13px; outline: none; background: white; cursor: pointer;">
                        <option value="ALL">Semua Kategori (Semua)</option>
                        <?php if ($user_role == 'teacher'): ?>
                            <?php foreach ($approved_mods as $mod): ?>
                                <option value="<?php echo htmlspecialchars($mod); ?>"><?php echo htmlspecialchars($mod); ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="Taska">Taska</option>
                            <option value="Tadika">Tadika</option>
                            <option value="KAFA Care">KAFA Care</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="filter-group" style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-weight: bold; font-size: 13px; color: #555;">Status Alahan:</label>
                    <select id="filter-allergy" onchange="applyFilters()" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 13px; outline: none; background: white; cursor: pointer;">
                        <option value="ALL">Semua Status (Semua)</option>
                        <option value="yes">Ada Alahan ⚠️</option>
                        <option value="no">Tiada Alahan</option>
                    </select>
                </div>
            </div>
            <div>
                <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer; background: #555; color: white; border: none; border-radius: 5px; font-size: 13px; font-weight: bold; transition: opacity 0.2s;">🖨️ Cetak Laporan</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 20%;">Maklumat Pelajar</th>
                    <th style="width: 25%;">Alamat Kediaman</th>
                    <th style="width: 25%;">Maklumat Penjaga & Majikan</th>
                    <th style="width: 30%;">Rekod Kesihatan & Alahan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $tagClass = 'tag-' . strtolower(str_replace(' ', '', $row['module'] ?? ''));
                    ?>
                        <tr class="student-row" data-module="<?php echo htmlspecialchars($row['module'] ?? ''); ?>" data-allergy="<?php echo !empty($row['allergies']) ? 'yes' : 'no'; ?>">
                            <td>
                                <strong style="font-size: 14px;"><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></strong><br>
                                <span style="color:#777; font-size:12px;">MyKid: <?php echo htmlspecialchars($row['mykid_number'] ?? '-'); ?></span><br>
                                <div style="margin-top: 5px;"><span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($row['module'] ?? '-'); ?></span></div>
                            </td>
                            
                            <td>
                                <?php echo nl2br(htmlspecialchars($row['address'] ?? 'Tiada Rekod Alamat')); ?><br>
                                <strong><?php echo htmlspecialchars($row['postcode'] ?? ''); ?> <?php echo htmlspecialchars($row['state'] ?? ''); ?></strong>
                            </td>

                            <td>
                                <strong><?php echo htmlspecialchars($row['parent_name'] ?? 'Tiada Rekod'); ?></strong><br>
                                <span style="color:#777; font-size:12px;">📞 <?php echo htmlspecialchars($row['parent_phone'] ?? '-'); ?></span><br>
                                <span style="color:#777; font-size:12px;">📧 <?php echo htmlspecialchars($row['parent_email'] ?? '-'); ?></span>
                                
                                <div class="info-box">
                                    <strong>Majikan:</strong> <?php echo htmlspecialchars($row['employer_name'] ?? '-'); ?>
                                </div>
                            </td>

                            <td>
                                <strong style="font-size: 12px; color: #555;">Status Umum:</strong><br>
                                <?php echo !empty($row['health_record']) ? htmlspecialchars($row['health_record']) : '<span style="color:#999; font-size:11px;">Tiada masalah direkodkan.</span>'; ?>
                                
                                <div style="margin-top: 8px;">
                                    <?php if(!empty($row['allergies'])): ?>
                                        <div class="health-box">⚠️ <strong>Alahan:</strong> <?php echo htmlspecialchars($row['allergies']); ?></div>
                                    <?php else: ?>
                                        <span class="no-allergy" style="font-size:11px;">Tiada Alahan Makanan/Ubat</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Tiada rekod pelajar dalam sistem.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

    <script>
    function applyFilters() {
        const selectedModule = document.getElementById('filter-module').value;
        const selectedAllergy = document.getElementById('filter-allergy').value;
        
        const rows = document.querySelectorAll('.student-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const rowModule = row.getAttribute('data-module');
            const rowAllergy = row.getAttribute('data-allergy');
            
            const matchesModule = (selectedModule === 'ALL' || rowModule === selectedModule);
            const matchesAllergy = (selectedAllergy === 'ALL' || rowAllergy === selectedAllergy);
            
            if (matchesModule && matchesAllergy) {
                row.style.display = 'table-row';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Handle empty filter results
        const emptyRow = document.getElementById('empty-filter-row');
        if (visibleCount === 0) {
            if (!emptyRow) {
                const tbody = document.querySelector('tbody');
                const tr = document.createElement('tr');
                tr.id = 'empty-filter-row';
                tr.innerHTML = `<td colspan="4" style="text-align:center; padding:30px; color:#999; font-style:italic; font-size:14px;">Tiada rekod pelajar yang mematuhi tapisan yang dipilih.</td>`;
                tbody.appendChild(tr);
            } else {
                emptyRow.style.display = 'table-row';
            }
        } else {
            if (emptyRow) {
                emptyRow.style.display = 'none';
            }
        }
    }
    </script>

</main>
</body>
</html>