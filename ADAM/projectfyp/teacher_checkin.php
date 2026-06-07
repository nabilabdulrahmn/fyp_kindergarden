<?php
// teacher_checkin.php
// Pengesahan Daftar Masuk & Keluar - Guru
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
$today = date('Y-m-d');

// --- PROSES DAFTAR MASUK (CHECK-IN) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['proses_checkin'])) {
    $student_id = (int)$_POST['student_id'];
    $guardian_name = $conn->real_escape_string($_POST['guardian_name']);
    $notes = $conn->real_escape_string($_POST['notes']);

    if ($student_id > 0 && !empty($guardian_name)) {
        // Semak jika sudah check-in hari ini
        $check = $conn->query("SELECT id FROM checkin_checkout WHERE student_id='$student_id' AND date='$today'");
        if ($check->num_rows == 0) {
            $sql = "INSERT INTO checkin_checkout (student_id, checkin_time, checkin_by, guardian_name, date, notes) 
                    VALUES ('$student_id', NOW(), '$user_id', '$guardian_name', '$today', '$notes')";
            if ($conn->query($sql)) {
                $msg = "<div class='alert success'>Pelajar berjaya didaftarkan MASUK!</div>";
            } else {
                $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
            }
        } else {
            $msg = "<div class='alert error'>Pelajar sudah didaftarkan masuk hari ini.</div>";
        }
    } else {
        $msg = "<div class='alert error'>Sila isi nama penjaga yang menghantar.</div>";
    }
}

// --- PROSES DAFTAR KELUAR (CHECK-OUT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['proses_checkout'])) {
    $student_id = (int)$_POST['student_id'];
    $guardian_name = $conn->real_escape_string($_POST['guardian_name_out']);
    $notes = $conn->real_escape_string($_POST['notes_out']);

    if ($student_id > 0 && !empty($guardian_name)) {
        // Semak jika sudah check-in hari ini
        $check = $conn->query("SELECT id, checkout_time FROM checkin_checkout WHERE student_id='$student_id' AND date='$today'");
        if ($check->num_rows > 0) {
            $row = $check->fetch_assoc();
            if (empty($row['checkout_time'])) {
                $sql = "UPDATE checkin_checkout SET 
                        checkout_time=NOW(), 
                        checkout_by='$user_id', 
                        guardian_name=CONCAT(guardian_name, ' (Dipulangkan kepada: ', '$guardian_name', ')'), 
                        notes=CONCAT(IFNULL(notes,''), ' | Keluar: ', '$notes') 
                        WHERE student_id='$student_id' AND date='$today'";
                if ($conn->query($sql)) {
                    $msg = "<div class='alert success'>Pelajar berjaya didaftarkan KELUAR!</div>";
                } else {
                    $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
                }
            } else {
                $msg = "<div class='alert error'>Pelajar sudah didaftarkan keluar hari ini.</div>";
            }
        } else {
            $msg = "<div class='alert error'>Pelajar belum didaftarkan masuk hari ini. Tidak boleh daftar keluar.</div>";
        }
    } else {
        $msg = "<div class='alert error'>Sila isi nama penjaga yang mengambil.</div>";
    }
}

// --- AMBIL SENARAI PELAJAR UNTUK DROPDOWN ---
$students_result = $conn->query("SELECT id, full_name, module FROM students WHERE status='Active' ORDER BY full_name ASC");

// --- AMBIL REKOD DAFTAR MASUK/KELUAR HARI INI ---
$logs_result = $conn->query("
    SELECT c.*, s.full_name, s.module, u_in.username as in_by_user, u_out.username as out_by_user
    FROM checkin_checkout c
    JOIN students s ON c.student_id = s.id
    LEFT JOIN users u_in ON c.checkin_by = u_in.id
    LEFT JOIN users u_out ON c.checkout_by = u_out.id
    WHERE c.date = '$today'
    ORDER BY c.checkin_time DESC
");

// Dapatkan penjaga berdaftar jika ada AJAX/JavaScript (Opsional, kami masukkan data terus dalam JSON untuk JS)
$guardians_data = [];
$g_res = $conn->query("SELECT student_id, guardian_name, relationship FROM authorized_guardians WHERE is_active = 1");
if ($g_res) {
    while ($row = $g_res->fetch_assoc()) {
        $guardians_data[$row['student_id']][] = $row['guardian_name'] . ' (' . $row['relationship'] . ')';
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Daftar Masuk & Keluar - Guru</title>
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
        
        select, input[type="text"], textarea {
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
        .btn-checkin { background-color: #2ecc71; color: white; width: 100%; font-size: 15px; }
        .btn-checkin:hover { background-color: #27ae60; }
        
        .btn-checkout { background-color: #e74c3c; color: white; width: 100%; font-size: 15px; }
        .btn-checkout:hover { background-color: #c0392b; }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        th { background-color: #f9f9f9; padding: 10px; text-align: left; color: #444; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        
        .badge { padding: 4px 8px; border-radius: 5px; font-size: 10px; color: white; font-weight: bold; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; }
        .tag-kafa, .tag-kafacare { background: #b5ead7; color: #444;}
        
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 15px; }
        .tab-btn { background: #e2e8f0; color: #4a5568; }
        .tab-btn.active { background: #ffb347; color: white; }
    </style>
    <script>
        const guardiansData = <?php echo json_encode($guardians_data); ?>;
        
        function updateGuardianOptions(type) {
            const studentSelect = document.getElementById(type === 'in' ? 'student_id_in' : 'student_id_out');
            const guardianList = document.getElementById(type === 'in' ? 'guardian_list_in' : 'guardian_list_out');
            const studentId = studentSelect.value;
            
            // Clear lists
            guardianList.innerHTML = '<option value="">-- Pilih Penjaga Penyerah/Penerima --</option><option value="Ibu / Bapa">Ibu / Bapa</option>';
            
            if (studentId && guardiansData[studentId]) {
                guardiansData[studentId].forEach(function(g) {
                    const opt = document.createElement('option');
                    opt.value = g;
                    opt.textContent = g;
                    guardianList.appendChild(opt);
                });
            }
            
            const optOther = document.createElement('option');
            optOther.value = 'Lain-lain';
            optOther.textContent = 'Lain-lain (Tulis di bawah)';
            guardianList.appendChild(optOther);
        }
        
        function handleCustomGuardian(type) {
            const selectEl = document.getElementById(type === 'in' ? 'guardian_list_in' : 'guardian_list_out');
            const customInput = document.getElementById(type === 'in' ? 'guardian_custom_in' : 'guardian_custom_out');
            
            if (selectEl.value === 'Lain-lain') {
                customInput.style.display = 'block';
                customInput.setAttribute('required', 'required');
                customInput.name = type === 'in' ? 'guardian_name' : 'guardian_name_out';
                selectEl.name = '';
            } else {
                customInput.style.display = 'none';
                customInput.removeAttribute('required');
                selectEl.name = type === 'in' ? 'guardian_name' : 'guardian_name_out';
            }
        }
        
        function showActionTab(tabName) {
            document.querySelectorAll('.action-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            document.getElementById(tabName + '-content').style.display = 'block';
            document.getElementById(tabName + '-btn').classList.add('active');
        }
    </script>
</head>
<body>

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">🔐 Pengesahan Log Daftar Masuk / Keluar (Check-In / Out)</h2>
    </div>

    <?php echo $msg; ?>

    <div class="main-container">
        
        <!-- Panel Form Check-in/out -->
        <div class="panel left-panel">
            <div class="tabs">
                <button id="in-btn" class="tab-btn active" onclick="showActionTab('in')">📥 Daftar Masuk (In)</button>
                <button id="out-btn" class="tab-btn" onclick="showActionTab('out')">📤 Daftar Keluar (Out)</button>
            </div>
            
            <!-- Form Daftar Masuk -->
            <div id="in-content" class="action-content">
                <h3>📥 Daftar Masuk Pelajar</h3>
                <form method="POST" action="teacher_checkin.php">
                    <input type="hidden" name="proses_checkin" value="1">
                    
                    <div class="form-group">
                        <label>Pilih Pelajar</label>
                        <select name="student_id" id="student_id_in" onchange="updateGuardianOptions('in')" required>
                            <option value="">-- Pilih Pelajar --</option>
                            <?php 
                            if ($students_result && $students_result->num_rows > 0) {
                                mysqli_data_seek($students_result, 0);
                                while($s = $students_result->fetch_assoc()) {
                                    echo "<option value='{$s['id']}'>{$s['full_name']} ({$s['module']})</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Dihantar Oleh (Penjaga)</label>
                        <select id="guardian_list_in" name="guardian_name" onchange="handleCustomGuardian('in')" required>
                            <option value="">-- Sila pilih pelajar dahulu --</option>
                        </select>
                        <input type="text" id="guardian_custom_in" placeholder="Masukkan nama penjaga penyerah" style="display:none; margin-top:8px;">
                    </div>

                    <div class="form-group">
                        <label>Ulasan & Nota Suhu / Kesihatan (Pilihan)</label>
                        <textarea name="notes" placeholder="Cth: Suhu badan 36.5°C, kelihatan aktif dan ceria." rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn-checkin">📥 Daftar Masuk Sekarang</button>
                </form>
            </div>
            
            <!-- Form Daftar Keluar -->
            <div id="out-content" class="action-content" style="display:none;">
                <h3>📤 Daftar Keluar Pelajar</h3>
                <form method="POST" action="teacher_checkin.php">
                    <input type="hidden" name="proses_checkout" value="1">
                    
                    <div class="form-group">
                        <label>Pilih Pelajar</label>
                        <select name="student_id" id="student_id_out" onchange="updateGuardianOptions('out')" required>
                            <option value="">-- Pilih Pelajar --</option>
                            <?php 
                            if ($students_result && $students_result->num_rows > 0) {
                                mysqli_data_seek($students_result, 0);
                                while($s = $students_result->fetch_assoc()) {
                                    echo "<option value='{$s['id']}'>{$s['full_name']} ({$s['module']})</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Diambil Oleh (Penjaga Penerima)</label>
                        <select id="guardian_list_out" name="guardian_name_out" onchange="handleCustomGuardian('out')" required>
                            <option value="">-- Sila pilih pelajar dahulu --</option>
                        </select>
                        <input type="text" id="guardian_custom_out" placeholder="Masukkan nama penjaga penerima" style="display:none; margin-top:8px;">
                    </div>

                    <div class="form-group">
                        <label>Catatan Tambahan (Pilihan)</label>
                        <textarea name="notes_out" placeholder="Cth: Ibu mengambil awal kerana ada temu janji klinik." rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn-checkout">📤 Daftar Keluar Sekarang</button>
                </form>
            </div>
        </div>

        <!-- Panel Rekod Log Hari Ini -->
        <div class="panel right-panel">
            <h3>📋 Log Daftar Masuk & Keluar Hari Ini (<?php echo date('d/m/Y', strtotime($today)); ?>)</h3>
            
            <table>
                <thead>
                    <tr>
                        <th>Pelajar</th>
                        <th>Daftar Masuk</th>
                        <th>Daftar Keluar</th>
                        <th>Pendaftar / Nota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                        <?php while ($log = $logs_result->fetch_assoc()): 
                            $tagClass = 'badge tag-' . strtolower(str_replace(' ', '', $log['module']));
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['full_name']); ?></strong><br>
                                    <span class="<?php echo $tagClass; ?>"><?php echo htmlspecialchars($log['module']); ?></span>
                                </td>
                                <td>
                                    <span style="color:#2ecc71; font-weight:bold;">📥 <?php echo date('h:i A', strtotime($log['checkin_time'])); ?></span><br>
                                    <span style="font-size:11px; color:#555;">Penjaga: <?php echo htmlspecialchars($log['guardian_name']); ?></span>
                                </td>
                                <td>
                                    <?php if(!empty($log['checkout_time'])): ?>
                                        <span style="color:#e74c3c; font-weight:bold;">📤 <?php echo date('h:i A', strtotime($log['checkout_time'])); ?></span>
                                    <?php else: ?>
                                        <span style="color:#7f8c8d; font-style:italic; font-size:11px;">Belum Keluar</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:11px; color:#555;">
                                    <strong>In:</strong> <?php echo htmlspecialchars($log['in_by_user'] ?? '-'); ?> 
                                    <?php if(!empty($log['out_by_user'])): ?>
                                        | <strong>Out:</strong> <?php echo htmlspecialchars($log['out_by_user']); ?>
                                    <?php endif; ?><br>
                                    <em>Nota: <?php echo htmlspecialchars($log['notes'] ?: '-'); ?></em>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; color:#888; padding:50px;">Tiada rekod daftar masuk/keluar untuk hari ini lagi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>

</body>
</html>
