<?php
// ambil_kehadiran.php
session_start();
require 'db.php';

// Pastikan hanya cikgu yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

// --- PROSES SIMPAN KEHADIRAN (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_kehadiran'])) {
    $tarikh_input = $_POST['tarikh_input'];
    $all_students = isset($_POST['all_students']) ? $_POST['all_students'] : [];
    $hadir_list = isset($_POST['hadir']) ? $_POST['hadir'] : [];

    foreach ($all_students as $stud_id) {
        $status = in_array($stud_id, $hadir_list) ? 'Present' : 'Absent';
        
        $check = $conn->query("SELECT id FROM attendance WHERE student_id='$stud_id' AND date='$tarikh_input'");
        if ($check->num_rows > 0) {
            $conn->query("UPDATE attendance SET status='$status' WHERE student_id='$stud_id' AND date='$tarikh_input'");
        } else {
            $conn->query("INSERT INTO attendance (student_id, date, status) VALUES ('$stud_id', '$tarikh_input', '$status')");
        }
    }
    // Refresh page dengan tarikh yang baru disimpan menggunakan fail yang betul
    echo "<script>alert('Kehadiran berjaya disimpan!'); window.location.href='ambil_kehadiran.php?date=$tarikh_input';</script>";
}

// --- TETAPAN FILTER (GET) ---
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_module = isset($_GET['module']) ? $_GET['module'] : '';

// --- DATA KIRI: AMBIL KEHADIRAN ---
$sql_kiri = "SELECT * FROM students WHERE status = 'Active'";
if ($filter_module != '') $sql_kiri .= " AND module = '$filter_module'";
$sql_kiri .= " ORDER BY full_name ASC";
$result_kiri = $conn->query($sql_kiri);

// --- DATA KANAN: REKOD KEHADIRAN HARIAN ---
// Sistem auto kira kehadiran pelajar untuk rekod [cite: 98, 100-101]
$sql_kanan = "SELECT a.status, s.full_name, s.module 
              FROM attendance a 
              JOIN students s ON a.student_id = s.id 
              WHERE a.date = '$filter_date'";
if ($filter_module != '') $sql_kanan .= " AND s.module = '$filter_module'";
$sql_kanan .= " ORDER BY s.full_name ASC";
$result_kanan = $conn->query($sql_kanan);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pengurusan Kehadiran - Cikgu</title>
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
        
        select, input[type="date"] {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        button.btn-search {
            background-color: #ffb347;
            color: white; border: none; padding: 9px 15px;
            border-radius: 6px; cursor: pointer; font-weight: bold;
        }

        /* SUSUNAN KIRI KANAN (FLEXBOX) */
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

        .left-panel { flex: 6; } /* Bahagian Kiri lebih besar sikit */
        .right-panel { flex: 4; border-top: 5px solid #77dd77; } /* Hijau untuk rekod */

        h3 { color: #555; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background-color: #f9f9f9; padding: 10px; text-align: left; color: #444; }
        td { padding: 10px; border-bottom: 1px solid #eee; }

        .btn-submit {
            background-color: #84b6f4;
            color: white; border: none; padding: 12px;
            border-radius: 6px; width: 100%; font-weight: bold;
            margin-top: 15px; cursor: pointer; font-size: 15px;
        }
        .btn-submit:hover { background-color: #6a9bd8; }

        .tag { padding: 3px 8px; border-radius: 5px; font-size: 11px; color: white; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; }
        .tag-kafa, .tag-kafacare { background: #b5ead7; color: #444;}

        .status-P { color: #2ecc71; font-weight: bold; }
        .status-A { color: #e74c3c; font-weight: bold; }

        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
    </style>
</head>
<body>

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">Sistem Kehadiran 📅</h2>
        
        <form method="GET" action="ambil_kehadiran.php">
            <label style="font-weight:bold; color:#555;">Pilih Tarikh:</label>
            <input type="date" name="date" value="<?php echo $filter_date; ?>">
            
            <label style="font-weight:bold; color:#555;">Modul:</label>
            <select name="module">
                <option value="">Semua Modul</option>
                <option value="Taska" <?php if($filter_module == 'Taska') echo 'selected'; ?>>Taska [cite: 6]</option>
                <option value="Tadika" <?php if($filter_module == 'Tadika') echo 'selected'; ?>>Tadika [cite: 7]</option>
                <option value="KAFA Care" <?php if($filter_module == 'KAFA Care') echo 'selected'; ?>>KAFA Care [cite: 8]</option>
            </select>
            <button type="submit" class="btn-search">Tapis</button>
        </form>
    </div>

    <div class="main-container">
        
        <div class="panel left-panel">
            <h3>📝 Tandakan Kehadiran (<?php echo date('d/m/Y', strtotime($filter_date)); ?>)</h3>
            
            <form method="POST" action="ambil_kehadiran.php?date=<?php echo $filter_date; ?>&module=<?php echo $filter_module; ?>">
                <input type="hidden" name="tarikh_input" value="<?php echo $filter_date; ?>">

                <table>
                    <thead>
                        <tr>
                            <th>Nama Pelajar</th>
                            <th>Modul</th>
                            <th style="text-align:center;">Hadir? (Tik)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_kiri->num_rows > 0): ?>
                            <?php while($row = $result_kiri->fetch_assoc()): 
                                $tagClass = 'tag-' . strtolower(str_replace(' ', '', $row['module']));
                            ?>
                                <tr>
                                    <td style="font-weight:500;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><span class="tag <?php echo $tagClass; ?>"><?php echo $row['module']; ?></span></td>
                                    <td style="text-align:center;">
                                        <input type="hidden" name="all_students[]" value="<?php echo $row['id']; ?>">
                                        <input type="checkbox" name="hadir[]" value="<?php echo $row['id']; ?>" style="transform: scale(1.4); cursor:pointer;">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align:center; color:#888;">Tiada rekod pelajar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="submit" name="simpan_kehadiran" class="btn-submit">💾 Simpan Kehadiran</button>
            </form>
        </div>

        <div class="panel right-panel">
            <h3>📋 Rekod Kehadiran Hari Ini</h3>
            
            <table>
                <thead>
                    <tr>
                        <th>Nama Pelajar</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_kanan->num_rows > 0): ?>
                        <?php while($row = $result_kanan->fetch_assoc()): 
                            $statusText = ($row['status'] == 'Present') ? 'HADIR' : 'ABSENT';
                            $statusClass = ($row['status'] == 'Present') ? 'status-P' : 'status-A';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="2" style="text-align:center; color:#888; padding:30px 0;">Belum ada rekod kehadiran disimpan untuk tarikh ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>

</body>
</html>