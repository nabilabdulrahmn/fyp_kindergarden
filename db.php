<?php
// db.php
$host = 'localhost';
$username = 'root'; // Default XAMPP
$password = ''; // Default XAMPP tiada password
$dbname = 'childcare_db';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- AUTOMIGRATION: Create teacher_modules table if it doesn't exist ---
$conn->query("CREATE TABLE IF NOT EXISTS `teacher_modules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` INT(11) NOT NULL,
  `module` ENUM('Taska', 'Tadika', 'KAFA Care') NOT NULL,
  `status` ENUM('Pending_Register', 'Pending_Drop', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending_Register',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_teacher_module` (`teacher_id`, `module`),
  CONSTRAINT `fk_tm_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// --- AUTOMIGRATION: Create teacher_class_requests table if it doesn't exist ---
$conn->query("CREATE TABLE IF NOT EXISTS `teacher_class_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` INT(11) NOT NULL,
  `class_id` INT(11) NOT NULL,
  `request_type` ENUM('Add', 'Drop') NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_tcr_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tcr_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");


// --- AUTO-SEEDING: Seed teacher_modules from existing class teaching assignments if empty ---
$count_res = $conn->query("SELECT COUNT(*) as cnt FROM teacher_modules");
if ($count_res) {
    $count_row = $count_res->fetch_assoc();
    if ($count_row['cnt'] == 0) {
        $conn->query("INSERT INTO teacher_modules (teacher_id, module, status)
                      SELECT DISTINCT teacher_id, module, 'Approved' 
                      FROM classes 
                      WHERE teacher_id IS NOT NULL
                      ON DUPLICATE KEY UPDATE status='Approved'");
    }
}

// ── Automasi Invois Tunggakan (Overdue) & Notifikasi Mesej ──────
if (!function_exists('check_and_update_overdue_invoices')) {
    function check_and_update_overdue_invoices($conn) {
        $threshold_date = date('Y-m-d H:i:s', strtotime('-14 days'));
        
        $sql_overdue = "SELECT i.*, p.user_id AS parent_user_id, s.full_name AS student_name 
                        FROM invoices i
                        JOIN parents p ON i.parent_id = p.id
                        JOIN students s ON i.student_id = s.id
                        WHERE i.status = 'Pending' AND i.created_at < '$threshold_date'";
        $res = $conn->query($sql_overdue);
        if ($res && $res->num_rows > 0) {
            // Cari admin user_id
            $admin_res = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $admin_user_id = ($admin_res && $admin_res->num_rows > 0) ? (int)$admin_res->fetch_assoc()['id'] : 1;
            
            while ($inv = $res->fetch_assoc()) {
                $invoice_id = (int)$inv['id'];
                $invoice_number = $conn->real_escape_string($inv['invoice_number']);
                $amount = number_format($inv['amount'], 2);
                $parent_user_id = (int)$inv['parent_user_id'];
                $student_name = $conn->real_escape_string($inv['student_name']);
                $fee_type = $conn->real_escape_string($inv['type']);
                
                // 1. Kemaskini status invois ke Overdue
                $conn->query("UPDATE invoices SET status = 'Overdue' WHERE id = $invoice_id");
                
                // 2. Hantar mesej peti masuk jika belum dihantar
                $subject = "TUNGGAKAN YURAN: $invoice_number";
                $check_msg = $conn->query("SELECT id FROM messages WHERE receiver_id = $parent_user_id AND subject = '$subject' LIMIT 1");
                if ($check_msg && $check_msg->num_rows == 0) {
                    $body = "Salam Sejahtera,\n\n"
                          . "Ini adalah pemberitahuan rasmi bahawa pembayaran yuran bagi anak anda, $student_name, bernilai RM $amount telah melepasi tarikh matang.\n\n"
                          . "Butiran Invois:\n"
                          . "- No. Invois: $invoice_number\n"
                          . "- Jenis Yuran: $fee_type\n"
                          . "- Jumlah: RM $amount\n\n"
                          . "Sila jelaskan bayaran yuran ini dengan kadar segera menerusi pindahan bank ke akaun rasmi taska dan muat naik bukti pemindahan dalam portal pembayaran ibu bapa.\n\n"
                          . "Terima Kasih,\n"
                          . "Sistem Kewangan Taska Care Centre";
                    
                    $body_esc = $conn->real_escape_string($body);
                    $conn->query("INSERT INTO messages (sender_id, receiver_id, subject, body, is_read, created_at) 
                                  VALUES ($admin_user_id, $parent_user_id, '$subject', '$body_esc', 0, NOW())");
                }
            }
        }
    }
}

if (isset($conn) && !$conn->connect_error) {
    check_and_update_overdue_invoices($conn);
}
?>