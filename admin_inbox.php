<?php
// admin_inbox.php
// Peti Masuk & Maklum Balas Ibu Bapa - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Tandakan mesej sebagai telah dibaca
if (isset($_GET['read'])) {
    $read_id = (int)$_GET['read'];
    $conn->query("UPDATE messages SET is_read = 1 WHERE id = $read_id AND receiver_id = $user_id");
    header("Location: admin_inbox.php");
    exit();
}

// Proses Balas Mesej
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reply'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = $conn->real_escape_string("RE: " . $_POST['original_subject']);
    $body = $conn->real_escape_string($_POST['reply_body']);
    
    $sql = "INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES ($user_id, $receiver_id, '$subject', '$body')";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Balasan berjaya dihantar!</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

// Ambil mesej masuk (Admin sebagai receiver)
$sql_inbox = "SELECT m.*, u.username, u.role 
              FROM messages m 
              JOIN users u ON m.sender_id = u.id 
              WHERE m.receiver_id = $user_id 
              ORDER BY m.created_at DESC";
$inbox = $conn->query($sql_inbox);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Peti Masuk Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #9c27b0; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        
        .msg-list { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
        .msg-item { padding: 15px; border-bottom: 1px solid #eee; display: flex; align-items: flex-start; transition: background 0.2s; }
        .msg-item:last-child { border-bottom: none; }
        .msg-item:hover { background: #f9f9f9; }
        .msg-item.unread { background: #f3f0f7; border-left: 4px solid #9c27b0; }
        
        .sender-avatar { width: 40px; height: 40px; background: #e1bee7; color: #6a1b9a; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; margin-right: 15px; flex-shrink: 0; }
        .msg-content { flex: 1; }
        .msg-header { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .sender-name { font-weight: bold; color: #333; font-size: 14px; }
        .sender-role { font-size: 11px; background: #eee; padding: 2px 6px; border-radius: 10px; color: #666; margin-left: 5px; }
        .msg-time { font-size: 12px; color: #999; }
        .msg-subject { font-weight: bold; font-size: 14px; margin-bottom: 5px; color: #444; }
        .msg-body { font-size: 13px; color: #666; line-height: 1.5; }
        
        .msg-actions { margin-top: 10px; }
        .btn-reply, .btn-mark { font-size: 12px; padding: 5px 10px; border-radius: 4px; text-decoration: none; cursor: pointer; border: none; }
        .btn-reply { background: #9c27b0; color: white; margin-right: 5px; }
        .btn-mark { background: #e0e0e0; color: #333; }
        
        /* Modal Reply */
        .reply-form { background: #fdfdfd; padding: 15px; border: 1px solid #ddd; border-radius: 6px; margin-top: 10px; display: none; }
        textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; margin-bottom: 10px; font-family: inherit; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
    <script>
        function toggleReply(id) {
            var form = document.getElementById('reply-form-' + id);
            form.style.display = form.style.display === 'block' ? 'none' : 'block';
        }
    </script>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>💬 Peti Masuk & Maklum Balas</h2>
        <?php echo $msg; ?>
        
        <div class="msg-list">
            <?php if ($inbox && $inbox->num_rows > 0): ?>
                <?php while($row = $inbox->fetch_assoc()): ?>
                    <div class="msg-item <?php echo $row['is_read'] == 0 ? 'unread' : ''; ?>">
                        <div class="sender-avatar"><?php echo strtoupper(substr($row['username'], 0, 1)); ?></div>
                        <div class="msg-content">
                            <div class="msg-header">
                                <div>
                                    <span class="sender-name"><?php echo htmlspecialchars($row['username']); ?></span>
                                    <span class="sender-role"><?php echo ucfirst($row['role']); ?></span>
                                </div>
                                <div class="msg-time"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></div>
                            </div>
                            <div class="msg-subject"><?php echo htmlspecialchars($row['subject'] ?? 'Tiada Tajuk'); ?></div>
                            <div class="msg-body"><?php echo nl2br(htmlspecialchars($row['body'])); ?></div>
                            
                            <div class="msg-actions">
                                <button class="btn-reply" onclick="toggleReply(<?php echo $row['id']; ?>)">↪ Balas Mesej</button>
                                <?php if ($row['is_read'] == 0): ?>
                                    <a href="?read=<?php echo $row['id']; ?>" class="btn-mark">✔ Tanda Dibaca</a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Reply Form (Hidden by default) -->
                            <div id="reply-form-<?php echo $row['id']; ?>" class="reply-form">
                                <form method="POST">
                                    <input type="hidden" name="receiver_id" value="<?php echo $row['sender_id']; ?>">
                                    <input type="hidden" name="original_subject" value="<?php echo htmlspecialchars($row['subject']); ?>">
                                    <textarea name="reply_body" rows="3" required placeholder="Taip balasan anda di sini..."></textarea>
                                    <button type="submit" name="send_reply" class="btn-reply">Hantar Balasan</button>
                                    <button type="button" class="btn-mark" onclick="toggleReply(<?php echo $row['id']; ?>)">Batal</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 30px; text-align: center; color: #999;">Tiada mesej baru dalam peti masuk anda.</div>
            <?php endif; ?>
        </div>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
