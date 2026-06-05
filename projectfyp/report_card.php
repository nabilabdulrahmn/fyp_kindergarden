<?php
// report_card.php
// Prestasi Akademik & Kad Laporan - Guru
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

// --- PROSES SIMPAN LAPORAN AKADEMIK (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hantar_laporan'])) {
    $student_id = (int)$_POST['student_id'];
    $term = $conn->real_escape_string($_POST['term']);
    $reading_score = $conn->real_escape_string($_POST['reading_score']);
    $writing_score = $conn->real_escape_string($_POST['writing_score']);
    $behaviour_score = $conn->real_escape_string($_POST['behaviour_score']);
    $interaction_score = $conn->real_escape_string($_POST['interaction_score']);
    $teacher_comment = $conn->real_escape_string($_POST['teacher_comment']);

    if ($student_id > 0 && !empty($term)) {
        // Semak jika rekod untuk pelajar + penggal ini sudah wujud
        $check = $conn->query("SELECT id FROM report_cards WHERE student_id='$student_id' AND term='$term'");
        if ($check->num_rows > 0) {
            // Update
            $sql = "UPDATE report_cards SET 
                    reading_score='$reading_score', 
                    writing_score='$writing_score', 
                    behaviour_score='$behaviour_score', 
                    interaction_score='$interaction_score', 
                    teacher_comment='$teacher_comment' 
                    WHERE student_id='$student_id' AND term='$term'";
        } else {
            // Insert
            $sql = "INSERT INTO report_cards (student_id, term, reading_score, writing_score, behaviour_score, interaction_score, teacher_comment) 
                    VALUES ('$student_id', '$term', '$reading_score', '$writing_score', '$behaviour_score', '$interaction_score', '$teacher_comment')";
        }

        if ($conn->query($sql)) {
            $msg = "<div class='alert success'>Kad Laporan berjaya disimpan!</div>";
        } else {
            $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
        }
    } else {
        $msg = "<div class='alert error'>Sila isi semua ruangan wajib.</div>";
    }
}

// --- FILTER PELAJAR ---
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Ambil senarai pelajar aktif
$students_result = $conn->query("SELECT id, full_name, module FROM students WHERE status='Active' ORDER BY full_name ASC");

// Ambil rekod kad laporan pelajar yang dipilih
$report_cards = [];
if ($selected_student_id > 0) {
    $sql_r = "SELECT rc.*, s.full_name, s.module, s.mykid_number 
              FROM report_cards rc 
              JOIN students s ON rc.student_id = s.id 
              WHERE rc.student_id = '$selected_student_id' 
              ORDER BY rc.created_at DESC";
    $r_result = $conn->query($sql_r);
    if ($r_result) {
        while ($row = $r_result->fetch_assoc()) {
            $report_cards[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Prestasi Akademik & Kad Laporan - Guru</title>
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
        
        select, input[type="text"], textarea {
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
        
        /* Report Card layout */
        .report-card-view {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            position: relative;
        }
        .report-card-view h4 {
            margin-top: 0;
            color: #d35400;
            border-bottom: 1px dashed #eee;
            padding-bottom: 8px;
        }
        .scores-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 15px 0;
        }
        .score-item {
            background: #fdfdfd;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .score-val {
            font-weight: bold;
            color: #2c3e50;
            font-size: 16px;
        }
        .comment-box {
            background: #fff8f0;
            border-left: 4px solid #ffb347;
            padding: 10px 15px;
            border-radius: 4px;
            font-style: italic;
            font-size: 13px;
        }
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
        
        @media print {
            .header-bar, .left-panel, .btn-back, .print-btn { display: none !important; }
            body { background: white; padding: 0; }
            .right-panel { width: 100% !important; flex: 100% !important; box-shadow: none !important; border: none !important; }
            .report-card-view { page-break-after: always; border: 1px solid #000; }
        }
    </style>
</head>
<body>

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">🎓 Prestasi Akademik & Kad Laporan</h2>
        
        <form method="GET" action="report_card.php">
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
        
        <!-- Panel Input Prestasi -->
        <div class="panel left-panel">
            <h3>📝 Rekod / Kemas Kini Prestasi</h3>
            <form method="POST" action="report_card.php?student_id=<?php echo $selected_student_id; ?>">
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
                    <label>Penggal (Term)</label>
                    <select name="term" required>
                        <option value="Mid-Term">Mid-Term (Pertengahan Tahun)</option>
                        <option value="Final-Term">Final-Term (Akhir Tahun)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Kemahiran Membaca (Reading)</label>
                    <select name="reading_score" required>
                        <option value="Cemerlang (A)">Cemerlang (A)</option>
                        <option value="Sangat Baik (B)">Sangat Baik (B)</option>
                        <option value="Baik (C)">Baik (C)</option>
                        <option value="Memuaskan (D)">Memuaskan (D)</option>
                        <option value="Perlu Bantuan (E)">Perlu Bantuan (E)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Kemahiran Menulis & Mewarna (Writing)</label>
                    <select name="writing_score" required>
                        <option value="Cemerlang (A)">Cemerlang (A)</option>
                        <option value="Sangat Baik (B)">Sangat Baik (B)</option>
                        <option value="Baik (C)">Baik (C)</option>
                        <option value="Memuaskan (D)">Memuaskan (D)</option>
                        <option value="Perlu Bantuan (E)">Perlu Bantuan (E)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tingkah Laku & Sahsiah (Behaviour)</label>
                    <select name="behaviour_score" required>
                        <option value="Cemerlang (A)">Cemerlang (A)</option>
                        <option value="Sangat Baik (B)">Sangat Baik (B)</option>
                        <option value="Baik (C)">Baik (C)</option>
                        <option value="Memuaskan (D)">Memuaskan (D)</option>
                        <option value="Perlu Bantuan (E)">Perlu Bantuan (E)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Interaksi Sosial & Komunikasi (Social/Interaction)</label>
                    <select name="interaction_score" required>
                        <option value="Cemerlang (A)">Cemerlang (A)</option>
                        <option value="Sangat Baik (B)">Sangat Baik (B)</option>
                        <option value="Baik (C)">Baik (C)</option>
                        <option value="Memuaskan (D)">Memuaskan (D)</option>
                        <option value="Perlu Bantuan (E)">Perlu Bantuan (E)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ulasan Guru (Teacher's Comment)</label>
                    <textarea name="teacher_comment" placeholder="Berikan ulasan positif dan cadangan penambahbaikan..." rows="4" required></textarea>
                </div>

                <button type="submit" name="hantar_laporan" class="btn-submit">💾 Simpan Kad Laporan</button>
            </form>
        </div>

        <!-- Panel Paparan Kad Laporan -->
        <div class="panel right-panel">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #eee; padding-bottom:10px; margin-bottom:15px;">
                <h3 style="margin:0; border:none; padding:0;">🎓 Rekod Kad Laporan Akademik</h3>
                <?php if ($selected_student_id > 0 && count($report_cards) > 0): ?>
                    <button class="print-btn" onclick="window.print()" style="background:#555; color:white;">🖨️ Cetak Kad Laporan</button>
                <?php endif; ?>
            </div>
            
            <?php if ($selected_student_id > 0): ?>
                <?php if (count($report_cards) > 0): ?>
                    <?php foreach ($report_cards as $rc): ?>
                        <div class="report-card-view">
                            <h4>📋 KAD LAPORAN AKADEMIK - <?php echo strtoupper($rc['term']); ?></h4>
                            <div style="font-size: 13px; color:#555; margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px;">
                                <strong>Nama:</strong> <?php echo htmlspecialchars($rc['full_name']); ?><br>
                                <strong>MyKid:</strong> <?php echo htmlspecialchars($rc['mykid_number']); ?><br>
                                <strong>Program:</strong> <?php echo htmlspecialchars($rc['module']); ?>
                            </div>
                            
                            <div class="scores-grid">
                                <div class="score-item">
                                    <span>Membaca (Reading)</span>
                                    <span class="score-val"><?php echo htmlspecialchars($rc['reading_score']); ?></span>
                                </div>
                                <div class="score-item">
                                    <span>Menulis (Writing)</span>
                                    <span class="score-val"><?php echo htmlspecialchars($rc['writing_score']); ?></span>
                                </div>
                                <div class="score-item">
                                    <span>Tingkah Laku (Behaviour)</span>
                                    <span class="score-val"><?php echo htmlspecialchars($rc['behaviour_score']); ?></span>
                                </div>
                                <div class="score-item">
                                    <span>Interaksi Sosial</span>
                                    <span class="score-val"><?php echo htmlspecialchars($rc['interaction_score']); ?></span>
                                </div>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <strong style="font-size: 13px; color:#333; display:block; margin-bottom: 5px;">✍️ Ulasan & Catatan Guru:</strong>
                                <div class="comment-box">
                                    "<?php echo nl2br(htmlspecialchars($rc['teacher_comment'])); ?>"
                                </div>
                            </div>
                            
                            <div style="font-size:10px; color:#999; margin-top:20px; text-align:right;">
                                Dibuat pada: <?php echo date('d/m/Y H:i A', strtotime($rc['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; padding:30px; color:#888;">Tiada rekod kad laporan ditemui untuk pelajar ini. Sila buat kemasukan pertama di panel kiri.</p>
                <?php endif; ?>
            <?php else: ?>
                <p style="text-align:center; padding:50px; color:#888;">Sila pilih pelajar di bahagian atas untuk melihat rekod prestasi akademik.</p>
            <?php endif; ?>
        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>

</body>
</html>
