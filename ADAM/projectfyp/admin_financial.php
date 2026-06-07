<?php
// admin_financial.php — Enhanced Financial Dashboard, Invoice & Payment Tracking
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$themeColor = '#77dd77';
$msg = '';
$msgType = 'success'; // 'success' or 'error'

// ─── AUTO-DETECT OVERDUE ─────────────────────────────────────────────
$conn->query("UPDATE invoices SET status='Overdue' WHERE status='Pending' AND due_date < CURDATE()");

// ─── POST ACTIONS ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Generate Single Invoice
    if (isset($_POST['generate_invoice'])) {
        $student_id = (int)$_POST['student_id'];
        $fee_type   = trim($_POST['fee_type']);
        $amount     = (float)$_POST['amount'];
        $due_date   = $_POST['due_date'];
        $notes      = trim($_POST['notes'] ?? '');

        // Generate invoice number: INV-YYYYMM-NNNN
        $year_month = date('Ym');
        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE invoice_number LIKE ?");
        $like_pattern = "INV-$year_month-%";
        $count_stmt->bind_param("s", $like_pattern);
        $count_stmt->execute();
        $cnt_result = $count_stmt->get_result()->fetch_assoc();
        $next_num = str_pad(($cnt_result['cnt'] + 1), 4, '0', STR_PAD_LEFT);
        $invoice_number = "INV-$year_month-$next_num";
        $count_stmt->close();

        // Look up parent_id from students
        $p_stmt = $conn->prepare("SELECT parent_id FROM students WHERE id = ?");
        $p_stmt->bind_param("i", $student_id);
        $p_stmt->execute();
        $p_result = $p_stmt->get_result();

        if ($p_result->num_rows > 0) {
            $parent_id = $p_result->fetch_assoc()['parent_id'];
            $p_stmt->close();

            $ins_stmt = $conn->prepare("INSERT INTO invoices (parent_id, student_id, invoice_number, amount, type, status, due_date, notes, generated_by) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?)");
            $gen_by = $_SESSION['user_id'];
            $ins_stmt->bind_param("iisdsssi", $parent_id, $student_id, $invoice_number, $amount, $fee_type, $due_date, $notes, $gen_by);

            if ($ins_stmt->execute()) {
                $msg = "Invois $invoice_number berjaya dijana!";
                // Log
                $log_stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, 'Success', ?)");
                $log_action = "Generated invoice $invoice_number for Student ID: $student_id";
                $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $log_action, $_SERVER['REMOTE_ADDR']);
                $log_stmt->execute();
                $log_stmt->close();
            } else {
                $msg = "Ralat: " . $conn->error;
                $msgType = 'error';
            }
            $ins_stmt->close();
        } else {
            $p_stmt->close();
            $msg = "Ralat: Pelajar tidak ditemui.";
            $msgType = 'error';
        }
    }

    // 2. Batch Invoice — Monthly Tuition for ALL active students
    if (isset($_POST['batch_invoice'])) {
        $year_month = date('Ym');
        $current_month = date('Y-m');
        $generated = 0;
        $skipped = 0;

        // Get all active students
        $active_stmt = $conn->prepare("SELECT s.id, s.parent_id, s.full_name, s.module FROM students s WHERE s.status = 'Active'");
        $active_stmt->execute();
        $active_result = $active_stmt->get_result();

        while ($student = $active_result->fetch_assoc()) {
            // Check if student already has unpaid tuition invoice for current month
            $check_stmt = $conn->prepare("SELECT id FROM invoices WHERE student_id = ? AND type = 'Monthly Tuition' AND status IN ('Pending','Overdue') AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $check_stmt->bind_param("is", $student['id'], $current_month);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $skipped++;
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();

            // Get fee amount from fee_structures
            $fee_stmt = $conn->prepare("SELECT amount FROM fee_structures WHERE module = ? AND fee_type = 'Monthly Tuition' LIMIT 1");
            $fee_stmt->bind_param("s", $student['module']);
            $fee_stmt->execute();
            $fee_result = $fee_stmt->get_result();

            if ($fee_result->num_rows > 0) {
                $fee_amount = $fee_result->fetch_assoc()['amount'];
                $fee_stmt->close();

                // Generate invoice number
                $count_stmt2 = $conn->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE invoice_number LIKE ?");
                $like_pattern2 = "INV-$year_month-%";
                $count_stmt2->bind_param("s", $like_pattern2);
                $count_stmt2->execute();
                $cnt2 = $count_stmt2->get_result()->fetch_assoc();
                $next_num2 = str_pad(($cnt2['cnt'] + 1), 4, '0', STR_PAD_LEFT);
                $inv_num = "INV-$year_month-$next_num2";
                $count_stmt2->close();

                $due = date('Y-m-d', strtotime('+30 days'));
                $gen_by = $_SESSION['user_id'];
                $type_tuition = 'Monthly Tuition';
                $batch_note = 'Auto-generated batch invoice';

                $batch_ins = $conn->prepare("INSERT INTO invoices (parent_id, student_id, invoice_number, amount, type, status, due_date, notes, generated_by) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?)");
                $batch_ins->bind_param("iisdsssi", $student['parent_id'], $student['id'], $inv_num, $fee_amount, $type_tuition, $due, $batch_note, $gen_by);
                $batch_ins->execute();
                $batch_ins->close();
                $generated++;
            } else {
                $fee_stmt->close();
                $skipped++;
            }
        }
        $active_stmt->close();

        $msg = "Invois bulanan dijana: $generated berjaya, $skipped dilangkau.";

        // Log
        $log_stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, 'Success', ?)");
        $log_action = "Batch invoice generated: $generated created, $skipped skipped";
        $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $log_action, $_SERVER['REMOTE_ADDR']);
        $log_stmt->execute();
        $log_stmt->close();
    }

    // 3. Mark as Paid
    if (isset($_POST['mark_paid'])) {
        $invoice_id     = (int)$_POST['invoice_id'];
        $payment_method = $_POST['payment_method'];
        $transaction_ref = trim($_POST['transaction_ref'] ?? '');

        // Update invoice
        $upd_stmt = $conn->prepare("UPDATE invoices SET status='Paid', paid_at=NOW(), payment_method=? WHERE id=?");
        $upd_stmt->bind_param("si", $payment_method, $invoice_id);
        $upd_stmt->execute();
        $upd_stmt->close();

        // Get invoice details for payment record
        $inv_detail = $conn->prepare("SELECT parent_id, amount FROM invoices WHERE id=?");
        $inv_detail->bind_param("i", $invoice_id);
        $inv_detail->execute();
        $inv_data = $inv_detail->get_result()->fetch_assoc();
        $inv_detail->close();

        // Insert into payments
        $receipt_num = 'RCP-' . date('Ymd') . '-' . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);
        $pay_stmt = $conn->prepare("INSERT INTO payments (invoice_id, parent_id, amount, payment_method, transaction_ref, payment_date, receipt_number, status) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'Completed')");
        $pay_stmt->bind_param("iidsss", $invoice_id, $inv_data['parent_id'], $inv_data['amount'], $payment_method, $transaction_ref, $receipt_num);
        $pay_stmt->execute();
        $pay_stmt->close();

        $msg = "Invois #$invoice_id ditandakan sebagai Dibayar.";

        // Log
        $log_stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, 'Success', ?)");
        $log_action = "Marked invoice ID $invoice_id as Paid via $payment_method";
        $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $log_action, $_SERVER['REMOTE_ADDR']);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

// ─── DASHBOARD STATS ─────────────────────────────────────────────────
$stat_query = $conn->query("SELECT status, SUM(amount) as total FROM invoices GROUP BY status");
$total_paid = 0; $total_pending = 0; $total_overdue = 0;
while ($r = $stat_query->fetch_assoc()) {
    if ($r['status'] === 'Paid')    $total_paid    = $r['total'];
    if ($r['status'] === 'Pending') $total_pending = $r['total'];
    if ($r['status'] === 'Overdue') $total_overdue = $r['total'];
}

// This month's income
$month_income_q = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM invoices WHERE status='Paid' AND MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE())");
$month_income = $month_income_q->fetch_assoc()['total'];

// ─── FETCH DATA FOR FORMS ────────────────────────────────────────────
$students_query = $conn->query("SELECT id, full_name, module FROM students WHERE status = 'Active' ORDER BY full_name");
$students_list = [];
while ($s = $students_query->fetch_assoc()) {
    $students_list[] = $s;
}

$fee_structures_query = $conn->query("SELECT id, module, fee_type, amount FROM fee_structures ORDER BY module, fee_type");
$fee_structures = [];
while ($f = $fee_structures_query->fetch_assoc()) {
    $fee_structures[] = $f;
}

// ─── INVOICE LIST WITH FILTERS ───────────────────────────────────────
$filter_status = $_GET['filter_status'] ?? '';
$filter_from   = $_GET['filter_from'] ?? '';
$filter_to     = $_GET['filter_to'] ?? '';
$filter_search = $_GET['filter_search'] ?? '';

$inv_sql = "SELECT i.*, s.full_name AS student_name, s.module, p.full_name AS parent_name
            FROM invoices i
            LEFT JOIN students s ON i.student_id = s.id
            LEFT JOIN parents p ON i.parent_id = p.id
            WHERE 1=1";
$inv_params = [];
$inv_types = '';

if ($filter_status !== '') {
    $inv_sql .= " AND i.status = ?";
    $inv_params[] = $filter_status;
    $inv_types .= 's';
}
if ($filter_from !== '') {
    $inv_sql .= " AND DATE(i.created_at) >= ?";
    $inv_params[] = $filter_from;
    $inv_types .= 's';
}
if ($filter_to !== '') {
    $inv_sql .= " AND DATE(i.created_at) <= ?";
    $inv_params[] = $filter_to;
    $inv_types .= 's';
}
if ($filter_search !== '') {
    $inv_sql .= " AND s.full_name LIKE ?";
    $inv_params[] = "%$filter_search%";
    $inv_types .= 's';
}

$inv_sql .= " ORDER BY i.created_at DESC LIMIT 100";

$inv_stmt = $conn->prepare($inv_sql);
if (!empty($inv_params)) {
    $inv_stmt->bind_param($inv_types, ...$inv_params);
}
$inv_stmt->execute();
$invoices_result = $inv_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kewangan — Invois & Pembayaran</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; min-height: 100vh; }

        /* ── Sidebar ─────────────────────────────────── */
        .sidebar { width: 270px; background-color: <?php echo $themeColor; ?>; color: white; padding-top: 0; box-shadow: 2px 0 10px rgba(0,0,0,0.12); display: flex; flex-direction: column; overflow-y: auto; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; }
        .sidebar-header { padding: 25px 20px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header h2 { font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .menu-label { padding: 15px 20px 5px; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; opacity: 0.7; }
        .sidebar a { padding: 11px 20px; text-decoration: none; font-size: 14px; color: white; display: block; border-bottom: 1px solid rgba(255,255,255,0.1); transition: all 0.3s ease; }
        .sidebar a:hover { background-color: rgba(255,255,255,0.2); padding-left: 28px; }
        .sidebar a.active { background-color: rgba(255,255,255,0.25); border-left: 4px solid #fff; font-weight: 600; }

        /* ── Content ─────────────────────────────────── */
        .content { flex: 1; margin-left: 270px; padding: 30px 40px; overflow-y: auto; }
        .page-title { color: #333; font-size: 26px; font-weight: 700; margin-bottom: 5px; }
        .page-subtitle { color: #888; font-size: 14px; margin-bottom: 30px; }

        /* ── Stat Cards ──────────────────────────────── */
        .stat-row { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
        .stat-card { flex: 1; min-width: 220px; padding: 25px; border-radius: 15px; color: white; position: relative; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.1); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .stat-card .stat-icon { font-size: 36px; margin-bottom: 10px; }
        .stat-card .stat-amount { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
        .stat-card .stat-label { font-size: 13px; opacity: 0.9; font-weight: 500; }
        .stat-card::after { content: ''; position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        .stat-green { background: linear-gradient(135deg, #77dd77, #66cc66); }
        .stat-yellow { background: linear-gradient(135deg, #f0ad4e, #eea236); }
        .stat-red { background: linear-gradient(135deg, #ff6961, #e55b53); }
        .stat-blue { background: linear-gradient(135deg, #84b6f4, #6a9bd8); }

        /* ── Cards ───────────────────────────────────── */
        .card { background: white; padding: 28px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card-title { font-size: 18px; font-weight: 700; color: #333; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 3px solid <?php echo $themeColor; ?>; display: flex; align-items: center; gap: 10px; }
        .card-title .emoji { font-size: 22px; }

        /* ── Form Elements ───────────────────────────── */
        .form-row { display: flex; gap: 20px; margin-bottom: 18px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;
            font-family: inherit; font-size: 14px; transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: #fafafa;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: <?php echo $themeColor; ?>; box-shadow: 0 0 0 3px rgba(119,221,119,0.2); background: #fff;
        }
        .form-group textarea { resize: vertical; min-height: 70px; }

        .btn-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 5px; }
        .btn { padding: 11px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 14px; font-family: inherit; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-green { background: <?php echo $themeColor; ?>; color: white; }
        .btn-green:hover { background: #66cc66; }
        .btn-blue { background: #84b6f4; color: white; }
        .btn-blue:hover { background: #6a9bd8; }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 6px; }
        .btn-sm-green { background: #77dd77; color: white; }
        .btn-sm-green:hover { background: #5cbd5c; }
        .btn-sm-red { background: #ff6961; color: white; }
        .btn-sm-red:hover { background: #e55b53; }

        /* ── Filter Bar ──────────────────────────────── */
        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; padding: 18px; background: #f8faf8; border-radius: 10px; margin-bottom: 20px; border: 1px solid #e8ede8; }
        .filter-bar .form-group { min-width: 150px; flex: unset; }
        .filter-bar label { font-size: 12px; }
        .filter-bar input, .filter-bar select { padding: 8px 12px; font-size: 13px; }

        /* ── Table ────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f2f4f3; padding: 12px 14px; text-align: left; color: #555; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #ddd; white-space: nowrap; }
        td { padding: 12px 14px; border-bottom: 1px solid #eee; color: #444; vertical-align: middle; }
        tr:hover { background-color: #f9fbf9; }

        /* ── Badges ───────────────────────────────────── */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-overdue { background: #f8d7da; color: #721c24; }

        /* ── Message ──────────────────────────────────── */
        .msg { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 500; display: flex; align-items: center; gap: 10px; animation: slideIn 0.4s ease; }
        .msg-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .msg-error { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Mark Paid Inline Form ─────────────────── */
        .pay-form { display: none; background: #f8faf8; padding: 12px; border-radius: 8px; margin-top: 8px; border: 1px solid #ddd; }
        .pay-form.visible { display: block; animation: slideIn 0.3s ease; }
        .pay-form select, .pay-form input { padding: 6px 10px; font-size: 12px; border: 1px solid #ccc; border-radius: 5px; margin-right: 6px; margin-bottom: 6px; }

        /* ── Empty State ──────────────────────────────── */
        .empty-state { text-align: center; padding: 40px; color: #aaa; }
        .empty-state .emoji { font-size: 48px; margin-bottom: 10px; }
    </style>
</head>
<body>

    <!-- ── SIDEBAR ──────────────────────────────────── -->
    <div class='sidebar'>
        <div class='sidebar-header'><h2>Admin Panel</h2></div>
        <div class='menu-label'>PENGURUSAN PELAJAR</div>
        <a href='senarai_pelajar.php'>Senarai Pelajar</a>
        <a href='admin_enrollment.php'>Pendaftaran & Pengesahan</a>
        <a href='admin_document_verification.php'>Pengesahan Dokumen</a>
        <a href='admin_enrollment_tracking.php'>Status Pendaftaran</a>
        <a href='senarai_kehadiran.php'>Kehadiran</a>
        <a href='arkib_pelajar.php'>Arkib Pelajar</a>
        <div class='menu-label'>KEWANGAN</div>
        <a href='admin_financial.php' class='active'>Invois & Pembayaran</a>
        <a href='admin_expense_report.php'>Perbelanjaan & Laporan</a>
        <div class='menu-label'>AKADEMIK</div>
        <a href='aktiviti_kelas.php'>Jadual Aktiviti</a>
        <a href='lesson_plan.php'>Rancangan Pengajaran</a>
        <div class='menu-label'>SISTEM</div>
        <a href='logout.php' style='color:#ff6961;'>Log Keluar</a>
    </div>

    <!-- ── MAIN CONTENT ─────────────────────────────── -->
    <div class="content">
        <h1 class="page-title">💰 Kewangan — Invois & Pembayaran</h1>
        <p class="page-subtitle">Urus invois, pantau pembayaran dan jana laporan kewangan</p>

        <?php if ($msg): ?>
            <div class="msg <?php echo $msgType === 'error' ? 'msg-error' : 'msg-success'; ?>">
                <?php echo $msgType === 'error' ? '❌' : '✅'; ?>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- ── SECTION 1: Dashboard Stats ──────────── -->
        <div class="stat-row">
            <div class="stat-card stat-green">
                <div class="stat-icon">💵</div>
                <div class="stat-amount">RM <?php echo number_format($total_paid, 2); ?></div>
                <div class="stat-label">Jumlah Kutipan (Dibayar)</div>
            </div>
            <div class="stat-card stat-yellow">
                <div class="stat-icon">⏳</div>
                <div class="stat-amount">RM <?php echo number_format($total_pending, 2); ?></div>
                <div class="stat-label">Belum Dibayar</div>
            </div>
            <div class="stat-card stat-red">
                <div class="stat-icon">⚠️</div>
                <div class="stat-amount">RM <?php echo number_format($total_overdue, 2); ?></div>
                <div class="stat-label">Tertunggak</div>
            </div>
            <div class="stat-card stat-blue">
                <div class="stat-icon">📅</div>
                <div class="stat-amount">RM <?php echo number_format($month_income, 2); ?></div>
                <div class="stat-label">Pendapatan Bulan Ini</div>
            </div>
        </div>

        <!-- ── SECTION 2: Generate Invoice ─────────── -->
        <div class="card">
            <div class="card-title"><span class="emoji">🧾</span> Jana Invois Baru</div>
            <form method="POST" id="invoiceForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Pelajar</label>
                        <select name="student_id" id="studentSelect" required>
                            <option value="">— Pilih Pelajar —</option>
                            <?php foreach ($students_list as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-module="<?php echo htmlspecialchars($s['module']); ?>">
                                    <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo htmlspecialchars($s['module']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jenis Bayaran</label>
                        <select name="fee_type" id="feeTypeSelect" required>
                            <option value="">— Pilih Jenis —</option>
                            <?php foreach ($fee_structures as $f): ?>
                                <option value="<?php echo htmlspecialchars($f['fee_type']); ?>"
                                        data-module="<?php echo htmlspecialchars($f['module']); ?>"
                                        data-amount="<?php echo $f['amount']; ?>">
                                    <?php echo htmlspecialchars($f['fee_type']); ?> (<?php echo htmlspecialchars($f['module']); ?> — RM <?php echo number_format($f['amount'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                            <option value="Lain-lain" data-module="all" data-amount="0">Lain-lain / Custom</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jumlah (RM)</label>
                        <input type="number" step="0.01" name="amount" id="amountInput" required placeholder="0.00" min="0.01">
                    </div>
                    <div class="form-group">
                        <label>Tarikh Akhir Bayaran</label>
                        <input type="date" name="due_date" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nota (pilihan)</label>
                        <textarea name="notes" placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
                <div class="btn-row">
                    <button type="submit" name="generate_invoice" class="btn btn-green">🧾 Jana Invois</button>
                </div>
            </form>
            <hr style="margin: 20px 0; border: none; border-top: 1px dashed #ddd;">
            <form method="POST" onsubmit="return confirm('Jana invois bulanan untuk SEMUA pelajar aktif?');">
                <div class="btn-row">
                    <button type="submit" name="batch_invoice" class="btn btn-blue">📦 Jana Invois Bulanan (Semua Pelajar)</button>
                </div>
            </form>
        </div>

        <!-- ── SECTION 3: Invoice & Payment Tracking ── -->
        <div class="card">
            <div class="card-title"><span class="emoji">💳</span> Senarai Invois & Pembayaran</div>

            <!-- Filter Bar -->
            <form method="GET" class="filter-bar">
                <div class="form-group">
                    <label>Status</label>
                    <select name="filter_status">
                        <option value="">Semua</option>
                        <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Paid" <?php echo $filter_status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Overdue" <?php echo $filter_status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Dari Tarikh</label>
                    <input type="date" name="filter_from" value="<?php echo htmlspecialchars($filter_from); ?>">
                </div>
                <div class="form-group">
                    <label>Hingga Tarikh</label>
                    <input type="date" name="filter_to" value="<?php echo htmlspecialchars($filter_to); ?>">
                </div>
                <div class="form-group">
                    <label>Cari Pelajar</label>
                    <input type="text" name="filter_search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Nama pelajar...">
                </div>
                <button type="submit" class="btn btn-green btn-sm" style="margin-bottom:6px;">🔍 Tapis</button>
            </form>

            <!-- Invoice Table -->
            <div class="table-wrapper">
            <?php if ($invoices_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>No. Invois</th>
                            <th>Pelajar</th>
                            <th>Ibu Bapa</th>
                            <th>Jenis</th>
                            <th>Jumlah (RM)</th>
                            <th>Tarikh Jana</th>
                            <th>Tarikh Akhir</th>
                            <th>Status</th>
                            <th>Kaedah Bayaran</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($inv = $invoices_result->fetch_assoc()): ?>
                        <?php
                            $badge_class = 'badge-pending';
                            if ($inv['status'] === 'Paid') $badge_class = 'badge-paid';
                            if ($inv['status'] === 'Overdue') $badge_class = 'badge-overdue';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($inv['student_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($inv['parent_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($inv['type']); ?></td>
                            <td style="font-weight:600;">RM <?php echo number_format($inv['amount'], 2); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($inv['created_at'])); ?></td>
                            <td><?php echo $inv['due_date'] ? date('d/m/Y', strtotime($inv['due_date'])) : '-'; ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($inv['status']); ?></span></td>
                            <td><?php echo $inv['payment_method'] ? htmlspecialchars($inv['payment_method']) : '-'; ?></td>
                            <td>
                                <?php if ($inv['status'] === 'Pending' || $inv['status'] === 'Overdue'): ?>
                                    <button type="button" class="btn btn-sm btn-sm-green" onclick="togglePayForm(<?php echo $inv['id']; ?>)">✅ Tandakan Dibayar</button>
                                    <div id="payForm-<?php echo $inv['id']; ?>" class="pay-form">
                                        <form method="POST">
                                            <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                            <select name="payment_method" required>
                                                <option value="">Kaedah...</option>
                                                <option value="FPX">FPX</option>
                                                <option value="Manual Transfer">Manual Transfer</option>
                                                <option value="Cash">Cash</option>
                                            </select>
                                            <input type="text" name="transaction_ref" placeholder="No. Rujukan (pilihan)" style="width:140px;">
                                            <button type="submit" name="mark_paid" class="btn btn-sm btn-sm-green">Sahkan</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #28a745; font-size: 12px; font-weight: 600;">✅ Dibayar</span>
                                    <?php if ($inv['paid_at']): ?>
                                        <br><small style="color:#888;"><?php echo date('d/m/Y H:i', strtotime($inv['paid_at'])); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="emoji">📭</div>
                    <p>Tiada invois ditemui.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── JavaScript ────────────────────────────────── -->
    <script>
        // Fee structure amount mapping
        const feeAmounts = {};
        <?php foreach ($fee_structures as $f): ?>
            feeAmounts['<?php echo htmlspecialchars($f['module']); ?>_<?php echo htmlspecialchars($f['fee_type']); ?>'] = <?php echo $f['amount']; ?>;
        <?php endforeach; ?>

        const studentSelect = document.getElementById('studentSelect');
        const feeTypeSelect = document.getElementById('feeTypeSelect');
        const amountInput   = document.getElementById('amountInput');
        const feeOptions    = feeTypeSelect.querySelectorAll('option[data-module]');

        // Filter fee types based on selected student's module
        studentSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const module = selected.getAttribute('data-module') || '';

            feeOptions.forEach(opt => {
                const optModule = opt.getAttribute('data-module');
                if (optModule === 'all' || optModule === module || module === '') {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            });

            // Reset fee type selection
            feeTypeSelect.value = '';
            amountInput.value = '';
        });

        // Auto-populate amount when fee type changes
        feeTypeSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const amt = selected.getAttribute('data-amount');
            if (amt && parseFloat(amt) > 0) {
                amountInput.value = parseFloat(amt).toFixed(2);
            } else {
                amountInput.value = '';
                amountInput.focus();
            }
        });

        // Toggle mark-paid inline form
        function togglePayForm(id) {
            const form = document.getElementById('payForm-' + id);
            form.classList.toggle('visible');
        }
    </script>

</body>
</html>
