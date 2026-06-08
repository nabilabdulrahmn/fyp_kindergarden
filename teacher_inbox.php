<?php
// teacher_inbox.php
// Mesej & Maklum Balas - Guru
session_start();
require 'db.php';

// Pastikan hanya cikgu yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Dapatkan teacher_id
$check_teacher = $conn->query("SELECT id FROM teachers WHERE user_id = '$user_id'");
if ($check_teacher->num_rows == 0) {
    $conn->query("INSERT INTO teachers (user_id, full_name) VALUES ('$user_id', '$username')");
    $teacher_id = $conn->insert_id;
} else {
    $teacher_row = $check_teacher->fetch_assoc();
    $teacher_id = $teacher_row['id'];
}

$msg = '';

// --- PROSES HANTAR MESEJ (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hantar_mesej'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = $conn->real_escape_string($_POST['subject']);
    $body = $conn->real_escape_string($_POST['body']);

    // Semak sekuriti penerima: Mestilah admin ATAU parent kepada pelajar kelas guru ini dan modul yang diluluskan sahaja
    $allowed_sql = "
        SELECT id FROM users WHERE role = 'admin' AND id = '$receiver_id'
        UNION
        SELECT DISTINCT u.id 
        FROM users u
        JOIN parents p ON u.id = p.user_id
        JOIN students s ON p.id = s.parent_id
        JOIN student_classes sc ON s.id = sc.student_id
        JOIN classes c ON sc.class_id = c.id
        JOIN teacher_modules tm ON tm.teacher_id = c.teacher_id AND tm.module = c.module AND tm.status = 'Approved'
        WHERE c.teacher_id = '$teacher_id' AND u.id = '$receiver_id' AND s.module = c.module
    ";
    $allowed_res = $conn->query($allowed_sql);

    if ($allowed_res && $allowed_res->num_rows > 0) {
        $sql = "INSERT INTO messages (sender_id, receiver_id, subject, body, is_read) 
                VALUES ('$user_id', '$receiver_id', '$subject', '$body', 0)";
        if ($conn->query($sql)) {
            $msg = "<div class='alert success'>Mesej berjaya dihantar!</div>";
        } else {
            $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
        }
    } else {
        $msg = "<div class='alert error'>Akses Ditolak: Anda tidak dibenarkan menghantar mesej kepada pengguna ini.</div>";
    }
}

// --- TANDAKAN SEBAGAI DIBACA ---
if (isset($_GET['read_id'])) {
    $read_id = (int)$_GET['read_id'];
    $conn->query("UPDATE messages SET is_read = 1 WHERE id = '$read_id' AND receiver_id = '$user_id'");
    header("Location: teacher_inbox.php");
    exit();
}

// --- QUERY SENARAI PENERIMA DIBENARKAN (ADMINS & PARENTS KELAS CIKGU INI BAGI MODUL DILULUSKAN SAHAJA) ---
$recipients = [];
$recipient_sql = "
    SELECT id, username, 'Admin' as full_name, 'Admin' as role_label FROM users WHERE role = 'admin'
    UNION
    SELECT DISTINCT u.id, u.username, p.full_name, 'Ibu/Bapa' as role_label
    FROM users u
    JOIN parents p ON u.id = p.user_id
    JOIN students s ON p.id = s.parent_id
    JOIN student_classes sc ON s.id = sc.student_id
    JOIN classes c ON sc.class_id = c.id
    JOIN teacher_modules tm ON tm.teacher_id = c.teacher_id AND tm.module = c.module AND tm.status = 'Approved'
    WHERE c.teacher_id = '$teacher_id' AND s.module = c.module
";
$rec_res = $conn->query($recipient_sql);
if ($rec_res) {
    while ($r = $rec_res->fetch_assoc()) {
        $recipients[] = $r;
    }
}

// --- AMBIL MESEJ MASUK (INBOX) ---
// Tapis mesej masuk supaya hanya memaparkan mesej daripada admin ATAU parent daripada modul yang diluluskan sahaja
$inbox_messages = [];
$inbox_sql = "SELECT m.*, u.username as sender_username 
              FROM messages m 
              JOIN users u ON m.sender_id = u.id 
              WHERE m.receiver_id = '$user_id' 
                AND (
                  u.role = 'admin'
                  OR u.id IN (
                      SELECT DISTINCT u2.id
                      FROM users u2
                      JOIN parents p ON u2.id = p.user_id
                      JOIN students s ON p.id = s.parent_id
                      JOIN student_classes sc ON s.id = sc.student_id
                      JOIN classes c ON sc.class_id = c.id
                      JOIN teacher_modules tm ON tm.teacher_id = c.teacher_id AND tm.module = c.module AND tm.status = 'Approved'
                      WHERE c.teacher_id = '$teacher_id' AND s.module = c.module
                  )
                )
              ORDER BY m.created_at DESC";
$inbox_res = $conn->query($inbox_sql);
if ($inbox_res) {
    while ($row = $inbox_res->fetch_assoc()) {
        $inbox_messages[] = $row;
    }
}

// --- AMBIL MESEJ KELUAR (SENT) ---
// Tapis mesej keluar supaya hanya memaparkan mesej kepada admin ATAU parent daripada modul yang diluluskan sahaja
$sent_messages = [];
$sent_sql = "SELECT m.*, u.username as receiver_username 
            FROM messages m 
            JOIN users u ON m.receiver_id = u.id 
            WHERE m.sender_id = '$user_id' 
              AND (
                u.role = 'admin'
                OR u.id IN (
                    SELECT DISTINCT u2.id
                    FROM users u2
                    JOIN parents p ON u2.id = p.user_id
                    JOIN students s ON p.id = s.parent_id
                    JOIN student_classes sc ON s.id = sc.student_id
                    JOIN classes c ON sc.class_id = c.id
                    JOIN teacher_modules tm ON tm.teacher_id = c.teacher_id AND tm.module = c.module AND tm.status = 'Approved'
                    WHERE c.teacher_id = '$teacher_id' AND s.module = c.module
                )
              )
            ORDER BY m.created_at DESC";
$sent_res = $conn->query($sent_sql);
if ($sent_res) {
    while ($row = $sent_res->fetch_assoc()) {
        $sent_messages[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Mesej & Maklum Balas - Guru</title>
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
        .btn-submit { background-color: #ffb347; color: white; width: 100%; font-size: 15px; }
        .btn-submit:hover { background-color: #e67e22; }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 15px; }
        .tab-btn { background: #e2e8f0; color: #4a5568; }
        .tab-btn.active { background: #84b6f4; color: white; }
        
        .msg-list { max-height: 500px; overflow-y: auto; }
        .msg-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            background: #fff;
            transition: 0.2s;
        }
        .msg-card.unread {
            background: #f0f7ff;
            border-left: 4px solid #84b6f4;
        }
        .msg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #718096;
            margin-bottom: 8px;
        }
        .msg-sender { font-weight: bold; color: #2d3748; }
        .msg-title { font-weight: bold; font-size: 14px; margin-bottom: 5px; color: #1a202c; }
        .msg-body { font-size: 13px; color: #4a5568; line-height: 1.5; }
        
        .btn-read { background: #2ecc71; color: white; padding: 4px 8px; font-size: 11px; margin-top: 8px; display: inline-block; text-decoration: none; border-radius: 4px; }
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
    </style>
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            document.getElementById(tabName + '-content').style.display = 'block';
            document.getElementById(tabName + '-btn').classList.add('active');
        }
    </script>
</head>
<body>
<?php include 'sidebar_teacher.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">💬 Peti Mesej & Maklum Balas</h2>
    </div>

    <?php echo $msg; ?>

    <div class="main-container">
        
        <!-- Panel Tulis Mesej -->
        <div class="panel left-panel">
            <h3>✉️ Tulis Mesej Baru</h3>
            <form method="POST" action="teacher_inbox.php">
                
                <div class="form-group">
                    <label>Penerima Mesej</label>
                    <select name="receiver_id" required>
                        <option value="">-- Pilih Penerima --</option>
                        <?php foreach ($recipients as $rec): ?>
                            <option value="<?php echo $rec['id']; ?>">
                                <?php echo htmlspecialchars($rec['full_name'] ?: $rec['username']); ?> (<?php echo $rec['role_label']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Subjek / Perkara</label>
                    <input type="text" name="subject" placeholder="Cth: Perkembangan Ali / Maklumbalas Program" required>
                </div>

                <div class="form-group">
                    <label>Mesej Anda</label>
                    <textarea name="body" placeholder="Tulis kandungan mesej anda..." rows="6" required></textarea>
                </div>

                <button type="submit" name="hantar_mesej" class="btn-submit">🚀 Hantar Mesej</button>
            </form>
        </div>

        <!-- Panel Senarai Mesej (Tabs: Peti Masuk / Mesej Dihantar) -->
        <div class="panel right-panel">
            <div class="tabs">
                <button id="inbox-btn" class="tab-btn active" onclick="showTab('inbox')">📥 Peti Masuk</button>
                <button id="sent-btn" class="tab-btn" onclick="showTab('sent')">📤 Mesej Dihantar</button>
            </div>
            
            <!-- Tab Peti Masuk -->
            <div id="inbox-content" class="tab-content msg-list">
                <?php if (count($inbox_messages) > 0): ?>
                    <?php foreach ($inbox_messages as $in): ?>
                        <div class="msg-card <?php echo !$in['is_read'] ? 'unread' : ''; ?>">
                            <div class="msg-header">
                                <span class="msg-sender">Daripada: <?php echo htmlspecialchars($in['sender_username']); ?></span>
                                <span>📅 <?php echo date('d/m/Y H:i A', strtotime($in['created_at'])); ?></span>
                            </div>
                            <div class="msg-title"><?php echo htmlspecialchars($in['subject'] ?: '(Tiada Tajuk)'); ?></div>
                            <div class="msg-body"><?php echo nl2br(htmlspecialchars($in['body'])); ?></div>
                            <?php if(!$in['is_read']): ?>
                                <a href="teacher_inbox.php?read_id=<?php echo $in['id']; ?>" class="btn-read">✓ Tanda Dibaca</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; padding:50px; color:#888;">Tiada mesej masuk.</p>
                <?php endif; ?>
            </div>
            
            <!-- Tab Mesej Dihantar -->
            <div id="sent-content" class="tab-content msg-list" style="display:none;">
                <?php if (count($sent_messages) > 0): ?>
                    <?php foreach ($sent_messages as $s): ?>
                        <div class="msg-card">
                            <div class="msg-header">
                                <span class="msg-sender">Kepada: <?php echo htmlspecialchars($s['receiver_username']); ?></span>
                                <span>📅 <?php echo date('d/m/Y H:i A', strtotime($s['created_at'])); ?></span>
                            </div>
                            <div class="msg-title"><?php echo htmlspecialchars($s['subject'] ?: '(Tiada Tajuk)'); ?></div>
                            <div class="msg-body"><?php echo nl2br(htmlspecialchars($s['body'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; padding:50px; color:#888;">Tiada mesej yang telah anda hantar.</p>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>


</main>
</body>
</html>
