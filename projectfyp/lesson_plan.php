<?php
// lesson_plan.php
session_start();
require 'db.php';

// Pastikan hanya cikgu yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- PROSES SIMPAN RANCANGAN MENGAJAR ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_rph'])) {
    $class_group = $_POST['class_group'];
    $subject = $_POST['subject'];
    $teaching_date = $_POST['teaching_date'];
    $topic = $conn->real_escape_string($_POST['topic']);
    $learning_objective = $conn->real_escape_string($_POST['learning_objective']);
    $activities = $conn->real_escape_string($_POST['activities']);

    $sql = "INSERT INTO lesson_plans (teacher_id, class_group, subject, teaching_date, topic, learning_objective, activities) 
            VALUES ('$user_id', '$class_group', '$subject', '$teaching_date', '$topic', '$learning_objective', '$activities')";
            
    if ($conn->query($sql) === TRUE) {
        // Catat Log 
        if (function_exists('catat_log')) {
            catat_log($conn, $user_id, $username, "Menghantar RPH untuk: $class_group ($subject)", "Success");
        }
        echo "<script>alert('Rancangan Mengajar berjaya dihantar kepada Pengetua!'); window.location.href='lesson_plan.php';</script>";
    } else {
        echo "<script>alert('Ralat: " . $conn->error . "');</script>";
    }
}

// --- AMBIL REKOD RPH CIKGU INI SAHAJA ---
$sql_history = "SELECT * FROM lesson_plans WHERE teacher_id = '$user_id' ORDER BY teaching_date DESC LIMIT 50";
$result_history = $conn->query($sql_history);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Lesson Plan - Cikgu</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f4f7f6; margin: 0; padding: 30px; display: flex; flex-direction: column; align-items: center; }
        
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 900px; width: 100%; border-top: 8px solid #ffb347; margin-bottom: 30px; }
        
        h2 { color: #555; margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;}
        
        /* Form Styling */
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .form-group { flex: 1; display: flex; flex-direction: column; }
        label { font-weight: bold; color: #555; margin-bottom: 5px; font-size: 14px; }
        input[type="text"], input[type="date"], select, textarea { padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; width: 100%; box-sizing: border-box; }
        textarea { resize: vertical; height: 80px; }
        
        .btn-submit { background-color: #ffb347; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; font-size: 15px; margin-top: 10px; }
        .btn-submit:hover { background-color: #f39c12; }

        /* Table Styling */
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        th { background-color: #fff3e0; padding: 12px; text-align: left; color: #d35400; border-bottom: 2px solid #ffe0b2; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        
        .tag-class { display: inline-block; padding: 4px 8px; border-radius: 5px; font-size: 11px; font-weight: bold; background: #eee; color: #555; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; text-align: center; width: 100%;}
    </style>
</head>
<body>

    <div class="container">
        <h2>📝 Tulis Rancangan Mengajar (Lesson Plan)</h2>
        
        <form method="POST" action="lesson_plan.php">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Kumpulan Kelas / Program</label>
                    <select name="class_group" id="class_group" onchange="updateSubjects()" required>
                        <option value="">-- Pilih Kelas --</option>
                        <option value="Tadika 4-5 Tahun">Tadika (4 - 5 Tahun)</option>
                        <option value="Tadika 6 Tahun">Tadika (6 Tahun)</option>
                        <option value="KAFA Care">KAFA Care (Transit)</option>
                        <option value="Aktiviti Taska">Aktiviti Taska (Penjagaan)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Subjek / Teras Pembelajaran</label>
                    <select name="subject" id="subject" required>
                        <option value="">Sila pilih kelas dahulu</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tarikh Mengajar</label>
                    <input type="date" name="teaching_date" required>
                </div>
                
                <div class="form-group">
                    <label>Topik / Tema Utama</label>
                    <input type="text" name="topic" placeholder="Cth: Mengenal Haiwan Jinak / Surah Al-Fatihah" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Objektif Pembelajaran (Learning Objective)</label>
                <textarea name="learning_objective" placeholder="Di akhir pembelajaran, kanak-kanak akan dapat..." required></textarea>
            </div>

            <div class="form-group">
                <label>Penerangan Aktiviti (Activities)</label>
                <textarea name="activities" placeholder="1. Guru menunjukkan gambar... 2. Murid mewarna... 3. Sesi nyanyian..." required></textarea>
            </div>

            <button type="submit" name="simpan_rph" class="btn-submit">💾 Simpan & Hantar RPH</button>
        </form>
    </div>

    <div class="container">
        <h2>📚 Sejarah Rancangan Mengajar Saya</h2>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Tarikh</th>
                    <th style="width: 25%;">Kelas & Subjek</th>
                    <th style="width: 20%;">Topik</th>
                    <th style="width: 40%;">Objektif & Aktiviti</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_history->num_rows > 0): ?>
                    <?php while($row = $result_history->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:bold; color:#555;">
                                <?php echo date('d/m/Y', strtotime($row['teaching_date'])); ?>
                            </td>
                            <td>
                                <span class="tag-class"><?php echo htmlspecialchars($row['class_group']); ?></span><br>
                                <strong style="color:#d35400; font-size:12px;"><?php echo htmlspecialchars($row['subject']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($row['topic']); ?></td>
                            <td>
                                <strong style="font-size:11px; color:#777;">Objektif:</strong><br>
                                <?php echo nl2br(htmlspecialchars($row['learning_objective'])); ?><br><br>
                                <strong style="font-size:11px; color:#777;">Aktiviti:</strong><br>
                                <?php echo nl2br(htmlspecialchars($row['activities'])); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding: 20px; color:#888;">Tiada rekod rancangan mengajar dihantar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

    <script>
        const subjects = {
            'Tadika 4-5 Tahun': [
                'Bahasa Melayu (Asas Huruf)', 
                'English (Basic Phonics)', 
                'Matematik Awal', 
                'Sains Awal', 
                'Kreativiti & Estetika'
            ],
            'Tadika 6 Tahun': [
                'Bahasa Melayu Lanjutan', 
                'English (Reading & Grammar)', 
                'Matematik (Operasi Asas)', 
                'Sains Awal (Eksperimen)', 
                'Pendidikan Islam / Moral'
            ],
            'KAFA Care': [
                'Asas Al-Quran & Iqra', 
                'Hafazan', 
                'Fardu Ain (Ibadah, Akidah)', 
                'Pelajaran Jawi & Khat'
            ],
            'Aktiviti Taska': [
                'Perkembangan Motor Kasar', 
                'Perkembangan Motor Halus', 
                'Sensory Play', 
                'Pengurusan Diri / Sosial',
                'Tiada Silibus Khusus (Bebas)'
            ]
        };

        function updateSubjects() {
            const classSelect = document.getElementById('class_group');
            const subjectSelect = document.getElementById('subject');
            const selectedClass = classSelect.value;

            // Kosongkan pilihan subjek yang lama
            subjectSelect.innerHTML = '<option value="">-- Pilih Subjek --</option>';

            // Masukkan senarai baru ikut array 'subjects' di atas
            if (selectedClass in subjects) {
                subjects[selectedClass].forEach(function(sub) {
                    const option = document.createElement('option');
                    option.value = sub;
                    option.textContent = sub;
                    subjectSelect.appendChild(option);
                });
            }
        }
    </script>

</body>
</html>