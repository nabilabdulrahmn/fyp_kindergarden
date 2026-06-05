<?php
// parent_inbox.php
// Peti Masuk - Mesej Ibu Bapa
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$msg = '';

// Proses hantar mesej baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = $conn->real_escape_string($_POST['subject']);
    $body = $conn->real_escape_string($_POST['body']);

    // Sahkan penerima wujud dan merupakan guru atau admin sahaja (parent tak boleh mesej parent lain)
    $verify_receiver = $conn->query("SELECT id, role FROM users WHERE id = $receiver_id AND role IN ('teacher', 'admin') LIMIT 1");
    if ($verify_receiver && $verify_receiver->num_rows > 0) {
        $sql_send = "INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES ($user_id, $receiver_id, '$subject', '$body')";
        if ($conn->query($sql_send)) {
            $msg = "<div class='alert success'>Mesej berjaya dihantar!</div>";
        } else {
            $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
        }
    } else {
        $msg = "<div class='alert error'>Penerima tidak sah. Anda hanya boleh menghantar mesej kepada guru atau admin.</div>";
    }
}

// Ambil mesej diterima (hanya mesej untuk user ini)
$sql_inbox = "SELECT m.*, u.username AS sender_name, u.role AS sender_role 
              FROM messages m 
              INNER JOIN users u ON m.sender_id = u.id 
              WHERE m.receiver_id = $user_id 
              ORDER BY m.created_at DESC 
              LIMIT 50";
$inbox = $conn->query($sql_inbox);

// Ambil mesej dihantar
$sql_sent = "SELECT m.*, u.username AS receiver_name 
             FROM messages m 
             INNER JOIN users u ON m.receiver_id = u.id 
             WHERE m.sender_id = $user_id 
             ORDER BY m.created_at DESC 
             LIMIT 50";
$sent = $conn->query($sql_sent);

// Senarai guru & admin untuk hantar mesej (parent hanya boleh mesej guru/admin)
$sql_contacts = "SELECT id, username, role FROM users WHERE role IN ('teacher', 'admin') AND status = 'approved' ORDER BY role, username";
$contacts = $conn->query($sql_contacts);

// Tandakan mesej sebagai dibaca
if (isset($_GET['read'])) {
    $read_id = (int)$_GET['read'];
    $conn->query("UPDATE messages SET is_read = 1 WHERE id = $read_id AND receiver_id = $user_id");
    header("Location: parent_inbox.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peti Masuk - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #84b6f4; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }
        .alert { padding: 12px 15px; margin-bottom: 15px; border-radius: 8px; font-size: 14px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }

        .tabs { display: flex; gap: 0; margin-bottom: 0; }
        .tab { padding: 12px 25px; background: #e8e8e8; border: none; cursor: pointer; font-size: 14px; font-weight: bold; color: #666; transition: 0.3s; border-radius: 12px 12px 0 0; }
        .tab.active { background: white; color: #333; }

        .tab-content { background: white; border-radius: 0 16px 16px 16px; padding: 25px 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 25px; display: none; }
        .tab-content.active { display: block; }

        .compose-form { background: #f9f9f9; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: bold; color: #555; font-size: 13px; margin-bottom: 5px; }
        .form-group select, .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn-send { background: #84b6f4; color: white; border: none; padding: 10px 30px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-send:hover { background: #6a9bd8; }

        .msg-item { padding: 15px; border-bottom: 1px solid #f0f0f0; display: flex; gap: 12px; align-items: flex-start; transition: 0.2s; }
        .msg-item:hover { background: #f8f9fa; }
        .msg-item.unread { background: #eff7ff; border-left: 3px solid #84b6f4; }
        .msg-avatar { width: 40px; height: 40px; border-radius: 50%; background: #84b6f4; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0; }
        .msg-info { flex: 1; }
        .msg-sender { font-weight: bold; color: #333; font-size: 14px; }
        .msg-subject { color: #555; font-size: 13px; margin-top: 2px; }
        .msg-body { color: #888; font-size: 12px; margin-top: 4px; }
        .msg-time { font-size: 11px; color: #aaa; white-space: nowrap; }
        .msg-role { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; color: white; margin-left: 5px; }
        .role-teacher { background: #ffb347; }
        .role-admin { background: #77dd77; }

        .empty-state { text-align: center; padding: 40px; color: #aaa; }
    </style>
    <script>
        function showTab(tabName) {
            var contents = document.querySelectorAll('.tab-content');
            var tabs = document.querySelectorAll('.tab');
            for (var i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
                tabs[i].classList.remove('active');
            }
            document.getElementById('tab-' + tabName).classList.add('active');
            document.querySelector('[onclick="showTab(\'' + tabName + '\')"]').classList.add('active');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2>💬 Peti Masuk</h2>
                <div class="subtitle">Komunikasi dengan guru dan pentadbir sekolah</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <?php echo $msg; ?>

        <div class="tabs">
            <button class="tab active" onclick="showTab('inbox')">📥 Peti Masuk</button>
            <button class="tab" onclick="showTab('sent')">📤 Dihantar</button>
            <button class="tab" onclick="showTab('compose')">✏️ Tulis Mesej</button>
        </div>

        <!-- Peti Masuk -->
        <div class="tab-content active" id="tab-inbox">
            <?php if ($inbox && $inbox->num_rows > 0): ?>
                <?php while ($m = $inbox->fetch_assoc()): ?>
                    <div class="msg-item <?php echo $m['is_read'] ? '' : 'unread'; ?>">
                        <div class="msg-avatar"><?php echo strtoupper(substr($m['sender_name'], 0, 1)); ?></div>
                        <div class="msg-info">
                            <div class="msg-sender">
                                <?php echo htmlspecialchars($m['sender_name']); ?>
                                <span class="msg-role role-<?php echo $m['sender_role']; ?>"><?php echo ucfirst($m['sender_role']); ?></span>
                            </div>
                            <div class="msg-subject"><?php echo htmlspecialchars($m['subject'] ?? 'Tiada Subjek'); ?></div>
                            <div class="msg-body"><?php echo htmlspecialchars(substr($m['body'], 0, 100)); ?><?php echo strlen($m['body']) > 100 ? '...' : ''; ?></div>
                        </div>
                        <div style="text-align: right;">
                            <div class="msg-time"><?php echo date('d/m H:i', strtotime($m['created_at'])); ?></div>
                            <?php if (!$m['is_read']): ?>
                                <a href="?read=<?php echo $m['id']; ?>" style="font-size: 11px; color: #84b6f4;">Tandakan Dibaca</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div style="font-size: 40px; margin-bottom: 10px;">📭</div>
                    <p>Tiada mesej dalam peti masuk.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Dihantar -->
        <div class="tab-content" id="tab-sent">
            <?php if ($sent && $sent->num_rows > 0): ?>
                <?php while ($m = $sent->fetch_assoc()): ?>
                    <div class="msg-item">
                        <div class="msg-avatar" style="background: #77dd77;">📤</div>
                        <div class="msg-info">
                            <div class="msg-sender">Kepada: <?php echo htmlspecialchars($m['receiver_name']); ?></div>
                            <div class="msg-subject"><?php echo htmlspecialchars($m['subject'] ?? 'Tiada Subjek'); ?></div>
                            <div class="msg-body"><?php echo htmlspecialchars(substr($m['body'], 0, 100)); ?></div>
                        </div>
                        <div class="msg-time"><?php echo date('d/m H:i', strtotime($m['created_at'])); ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div style="font-size: 40px; margin-bottom: 10px;">📤</div>
                    <p>Tiada mesej dihantar.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tulis Mesej -->
        <div class="tab-content" id="tab-compose">
            <div class="compose-form">
                <h3 style="margin-bottom: 15px; color: #333;">✏️ Tulis Mesej Baru</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Kepada (Guru / Admin sahaja)</label>
                        <select name="receiver_id" required>
                            <option value="">-- Pilih Penerima --</option>
                            <?php if ($contacts) { while ($c = $contacts->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['username']); ?> (<?php echo ucfirst($c['role']); ?>)
                                </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subjek</label>
                        <input type="text" name="subject" required placeholder="Tajuk mesej anda">
                    </div>
                    <div class="form-group">
                        <label>Mesej</label>
                        <textarea name="body" rows="5" required placeholder="Tulis mesej anda di sini..."></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn-send">📨 Hantar Mesej</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
