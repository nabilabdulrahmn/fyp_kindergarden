<?php
// senarai_kehadiran.php
session_start();
require 'db.php';

// Pastikan hanya admin (dan mungkin cikgu) boleh akses
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'teacher')) {
    header("Location: login.php");
    exit();
}

// --- TETAPAN FILTER (GET) ---
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_module = isset($_GET['module']) ? $_GET['module'] : '';

// --- SQL QUERY UNTUK PENGIRAAN AUTOMATIK ---
// Mengira jumlah Hadir (Present), Tidak Hadir (Absent) dan Peratusan
$sql = "SELECT 
            s.id, 
            s.full_name, 
            s.module, 
            COUNT(a.id) AS total_days,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS total_present,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS total_absent
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id 
            AND MONTH(a.date) = '$filter_month' 
            AND YEAR(a.date) = '$filter_year'
        WHERE s.status = 'Active'";

if ($filter_module != '') {
    $sql .= " AND s.module = '$filter_module'";
}

$sql .= " GROUP BY s.id ORDER BY s.module, s.full_name ASC";
$result = $conn->query($sql);

// Senarai nama bulan untuk paparan yang lebih cantik
$bulan_melayu = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Mac', '04' => 'April',
    '05' => 'Mei', '06' => 'Jun', '07' => 'Julai', '08' => 'Ogos',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Disember'
];
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Overall Attendance Tracker</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 30px; margin: 0;}
        .container { 
            background: white; padding: 30px; border-radius: 12px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); width: 100%; max-width: 1000px; margin: 0 auto;
            border-top: 8px solid #77dd77; 
        }
        h2 { color: #555; margin-bottom: 5px; text-align: center; }
        p.subtitle { text-align: center; color: #777; margin-bottom: 25px; font-size: 14px; }

        .filter-form {
            background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee;
            display: flex; gap: 15px; align-items: center; justify-content: center; margin-bottom: 20px;
        }
        select { padding: 8px 12px; border-radius: 5px; border: 1px solid #ccc; font-family: inherit;}
        .btn-filter { background: #77dd77; color: white; border: none; padding: 9px 20px; border-radius: 5px; font-weight: bold; cursor: pointer; }
        .btn-filter:hover { background: #5cb85c; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .tag { padding: 4px 8px; border-radius: 5px; font-size: 11px; color: white; font-weight: bold; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; }
        .tag-kafa { background: #b5ead7; color: #444; }

        .stat-box { display: inline-block; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; text-align: center; min-width: 30px;}
        .stat-present { background: #d4edda; color: #155724; }
        .stat-absent { background: #f8d7da; color: #721c24; }
        
        /* Progress Bar untuk Peratusan */
        .progress-container { width: 100px; background-color: #e9ecef; border-radius: 10px; overflow: hidden; display: inline-block; vertical-align: middle; margin-right: 10px;}
        .progress-bar { height: 10px; border-radius: 10px; }
        .perc-text { font-weight: bold; font-size: 12px; }

        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
        
        @media print {
            .btn-back, .filter-form { display: none; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; border: none; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>📊 Overall Attendance Tracker</h2>
        <p class="subtitle">Pemantauan & Pengiraan Automatik Kehadiran Pelajar Bulan <?php echo $bulan_melayu[$filter_month]; ?> <?php echo $filter_year; ?></p>

        <form method="GET" action="senarai_kehadiran.php" class="filter-form">
            <label style="font-weight:bold; color:#555;">Bulan:</label>
            <select name="month">
                <?php foreach($bulan_melayu as $num => $name): ?>
                    <option value="<?php echo $num; ?>" <?php if($filter_month == $num) echo 'selected'; ?>><?php echo $name; ?></option>
                <?php endforeach; ?>
            </select>

            <label style="font-weight:bold; color:#555;">Tahun:</label>
            <select name="year">
                <?php for($y = 2024; $y <= date('Y')+1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php if($filter_year == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>

            <label style="font-weight:bold; color:#555;">Modul:</label>
            <select name="module">
                <option value="">Semua Modul</option>
                <option value="Taska" <?php if($filter_module=='Taska') echo 'selected'; ?>>Taska</option>
                <option value="Tadika" <?php if($filter_module=='Tadika') echo 'selected'; ?>>Tadika</option>
                <option value="KAFA Care" <?php if($filter_module=='KAFA Care') echo 'selected'; ?>>KAFA Care</option>
            </select>

            <button type="submit" class="btn-filter">Kira & Tapis</button>
            <button type="button" onclick="window.print()" class="btn-filter" style="background: #555;">🖨️ Cetak</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Nama Pelajar</th>
                    <th>Modul</th>
                    <th style="text-align:center;">Jumlah Rekod (Hari)</th>
                    <th style="text-align:center;">Hadir</th>
                    <th style="text-align:center;">Tidak Hadir</th>
                    <th>Peratusan (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $tagClass = 'tag-' . strtolower(str_replace(' ', '', $row['module'] ?? ''));
                        
                        $total_days = $row['total_days'];
                        $total_present = $row['total_present'] ?? 0;
                        $total_absent = $row['total_absent'] ?? 0;
                        
                        // Pengiraan Peratusan (Cegah pembahagian dengan sifar)
                        $percentage = 0;
                        if ($total_days > 0) {
                            $percentage = round(($total_present / $total_days) * 100);
                        }

                        // Warna Progress Bar mengikut peratusan
                        $barColor = '#2ecc71'; // Hijau (Bagus)
                        if ($percentage < 80) $barColor = '#f1c40f'; // Kuning (Amaran)
                        if ($percentage < 60) $barColor = '#e74c3c'; // Merah (Kritikal)
                    ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($row['module']); ?></span></td>
                            
                            <td style="text-align:center; color:#555;"><?php echo $total_days; ?> Hari</td>
                            
                            <td style="text-align:center;">
                                <span class="stat-box stat-present"><?php echo $total_present; ?></span>
                            </td>
                            
                            <td style="text-align:center;">
                                <span class="stat-box stat-absent"><?php echo $total_absent; ?></span>
                            </td>
                            
                            <td>
                                <?php if($total_days > 0): ?>
                                    <div class="progress-container">
                                        <div class="progress-bar" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $barColor; ?>;"></div>
                                    </div>
                                    <span class="perc-text" style="color: <?php echo $barColor; ?>;"><?php echo $percentage; ?>%</span>
                                <?php else: ?>
                                    <span style="color:#999; font-style:italic; font-size:11px;">Belum direkod</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">Tiada rekod pelajar aktif ditemui.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</body>
</html>