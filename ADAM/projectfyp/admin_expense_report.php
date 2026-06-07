<?php
// admin_expense_report.php — Expense Tracking & Financial Reporting
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$themeColor = '#77dd77';
$msg = '';
$msgType = 'success';

// Ensure uploads directory exists
@mkdir('uploads/expenses', 0777, true);

// ─── POST ACTIONS ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Add Expense
    if (isset($_POST['add_expense'])) {
        $category     = $_POST['category'];
        $amount       = (float)$_POST['amount'];
        $description  = trim($_POST['description']);
        $expense_date = $_POST['expense_date'];
        $receipt_file = null;

        // Handle file upload
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION);
            $filename = 'expense_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $target = 'uploads/expenses/' . $filename;
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $target)) {
                $receipt_file = $target;
            }
        }

        $stmt = $conn->prepare("INSERT INTO expenses (category, description, amount, expense_date, receipt_file, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $created_by = $_SESSION['user_id'];
        $stmt->bind_param("ssdssi", $category, $description, $amount, $expense_date, $receipt_file, $created_by);

        if ($stmt->execute()) {
            $msg = "Perbelanjaan berjaya direkodkan.";
            // Log
            $log_stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, 'Success', ?)");
            $log_action = "Added expense: $category - RM" . number_format($amount, 2) . " ($description)";
            $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $log_action, $_SERVER['REMOTE_ADDR']);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $msg = "Ralat: " . $conn->error;
            $msgType = 'error';
        }
        $stmt->close();
    }

    // 2. Delete Expense
    if (isset($_POST['delete_expense'])) {
        $expense_id = (int)$_POST['expense_id'];

        $del_stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
        $del_stmt->bind_param("i", $expense_id);
        if ($del_stmt->execute()) {
            $msg = "Perbelanjaan berjaya dipadam.";
            // Log
            $log_stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, 'Success', ?)");
            $log_action = "Deleted expense ID: $expense_id";
            $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $log_action, $_SERVER['REMOTE_ADDR']);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $msg = "Ralat semasa memadam.";
            $msgType = 'error';
        }
        $del_stmt->close();
    }
}

// ─── SUMMARY MONTH/YEAR FILTER ───────────────────────────────────────
$summary_month = isset($_GET['summary_month']) ? (int)$_GET['summary_month'] : (int)date('m');
$summary_year  = isset($_GET['summary_year']) ? (int)$_GET['summary_year'] : (int)date('Y');

// Total Income this month (paid invoices)
$inc_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM invoices WHERE status='Paid' AND MONTH(paid_at)=? AND YEAR(paid_at)=?");
$inc_stmt->bind_param("ii", $summary_month, $summary_year);
$inc_stmt->execute();
$total_income = $inc_stmt->get_result()->fetch_assoc()['total'];
$inc_stmt->close();

// Total Expenses this month
$exp_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE MONTH(expense_date)=? AND YEAR(expense_date)=?");
$exp_stmt->bind_param("ii", $summary_month, $summary_year);
$exp_stmt->execute();
$total_expenses = $exp_stmt->get_result()->fetch_assoc()['total'];
$exp_stmt->close();

$net_balance = $total_income - $total_expenses;

// Category breakdown for selected month
$cat_stmt = $conn->prepare("SELECT category, SUM(amount) as total FROM expenses WHERE MONTH(expense_date)=? AND YEAR(expense_date)=? GROUP BY category ORDER BY total DESC");
$cat_stmt->bind_param("ii", $summary_month, $summary_year);
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();
$categories = [];
while ($c = $cat_result->fetch_assoc()) {
    $categories[] = $c;
}
$cat_stmt->close();

$category_colors = [
    'Salary'           => '#e74c3c',
    'Utilities'        => '#f39c12',
    'Food & Supplies'  => '#27ae60',
    'Equipment'        => '#3498db',
    'Maintenance'      => '#9b59b6',
    'Transportation'   => '#1abc9c',
    'Miscellaneous'    => '#95a5a6'
];

// ─── EXPENSE LIST WITH FILTERS ───────────────────────────────────────
$filter_category = $_GET['filter_category'] ?? '';
$filter_from     = $_GET['filter_from'] ?? '';
$filter_to       = $_GET['filter_to'] ?? '';

$exp_sql = "SELECT * FROM expenses WHERE 1=1";
$exp_params = [];
$exp_types = '';

if ($filter_category !== '') {
    $exp_sql .= " AND category = ?";
    $exp_params[] = $filter_category;
    $exp_types .= 's';
}
if ($filter_from !== '') {
    $exp_sql .= " AND expense_date >= ?";
    $exp_params[] = $filter_from;
    $exp_types .= 's';
}
if ($filter_to !== '') {
    $exp_sql .= " AND expense_date <= ?";
    $exp_params[] = $filter_to;
    $exp_types .= 's';
}

$exp_sql .= " ORDER BY expense_date DESC";

$exp_list_stmt = $conn->prepare($exp_sql);
if (!empty($exp_params)) {
    $exp_list_stmt->bind_param($exp_types, ...$exp_params);
}
$exp_list_stmt->execute();
$expenses_result = $exp_list_stmt->get_result();

// ─── 6-MONTH COMPARISON ─────────────────────────────────────────────
$monthly_comparison = [];
for ($i = 5; $i >= 0; $i--) {
    $m = (int)date('m', strtotime("-$i months"));
    $y = (int)date('Y', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));

    // Income
    $inc_q = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM invoices WHERE status='Paid' AND MONTH(paid_at)=? AND YEAR(paid_at)=?");
    $inc_q->bind_param("ii", $m, $y);
    $inc_q->execute();
    $inc_val = $inc_q->get_result()->fetch_assoc()['total'];
    $inc_q->close();

    // Expenses
    $exp_q = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE MONTH(expense_date)=? AND YEAR(expense_date)=?");
    $exp_q->bind_param("ii", $m, $y);
    $exp_q->execute();
    $exp_val = $exp_q->get_result()->fetch_assoc()['total'];
    $exp_q->close();

    $monthly_comparison[] = [
        'label'   => $month_label,
        'income'  => $inc_val,
        'expense' => $exp_val,
        'balance' => $inc_val - $exp_val
    ];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perbelanjaan & Laporan Kewangan</title>
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
        .stat-row { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 25px; }
        .stat-card { flex: 1; min-width: 220px; padding: 25px; border-radius: 15px; color: white; position: relative; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.1); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .stat-card .stat-icon { font-size: 36px; margin-bottom: 10px; }
        .stat-card .stat-amount { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
        .stat-card .stat-label { font-size: 13px; opacity: 0.9; font-weight: 500; }
        .stat-card::after { content: ''; position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        .stat-green { background: linear-gradient(135deg, #77dd77, #66cc66); }
        .stat-red { background: linear-gradient(135deg, #ff6961, #e55b53); }
        .stat-blue { background: linear-gradient(135deg, #84b6f4, #6a9bd8); }
        .stat-negative { background: linear-gradient(135deg, #ff6961, #cc4444); }

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

        .btn { padding: 11px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 14px; font-family: inherit; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 6px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-green { background: <?php echo $themeColor; ?>; color: white; }
        .btn-green:hover { background: #66cc66; }
        .btn-sm { padding: 7px 14px; font-size: 12px; border-radius: 6px; }
        .btn-sm-red { background: #ff6961; color: white; border: none; border-radius: 6px; padding: 7px 14px; font-size: 12px; cursor: pointer; font-weight: 700; font-family: inherit; transition: all 0.3s ease; }
        .btn-sm-red:hover { background: #e55b53; transform: translateY(-1px); }
        .btn-print { background: #6c757d; color: white; }
        .btn-print:hover { background: #5a6268; }

        /* ── Filter Bar ──────────────────────────────── */
        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; padding: 18px; background: #f8faf8; border-radius: 10px; margin-bottom: 20px; border: 1px solid #e8ede8; }
        .filter-bar .form-group { min-width: 150px; flex: unset; }
        .filter-bar label { font-size: 12px; }
        .filter-bar input, .filter-bar select { padding: 8px 12px; font-size: 13px; }

        /* ── Summary Filter Bar ──────────────────────── */
        .summary-filter { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
        .summary-filter .form-group { min-width: 100px; flex: unset; }
        .summary-filter label { font-size: 12px; }
        .summary-filter select { padding: 8px 12px; font-size: 13px; }

        /* ── Table ────────────────────────────────────── */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f2f4f3; padding: 12px 14px; text-align: left; color: #555; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #ddd; white-space: nowrap; }
        td { padding: 12px 14px; border-bottom: 1px solid #eee; color: #444; vertical-align: middle; }
        tr:hover { background-color: #f9fbf9; }

        /* ── Category Tags ────────────────────────────── */
        .cat-tag { padding: 4px 12px; border-radius: 15px; font-size: 11px; font-weight: 700; color: white; display: inline-block; }

        /* ── Category Breakdown Bar ────────────────── */
        .cat-breakdown { margin-top: 20px; }
        .cat-item { display: flex; align-items: center; gap: 15px; margin-bottom: 12px; }
        .cat-name { width: 150px; font-size: 13px; font-weight: 600; color: #444; }
        .cat-bar-wrap { flex: 1; background: #eee; border-radius: 8px; height: 24px; overflow: hidden; position: relative; }
        .cat-bar { height: 100%; border-radius: 8px; transition: width 0.8s ease; min-width: 2px; display: flex; align-items: center; justify-content: flex-end; padding-right: 8px; }
        .cat-bar span { font-size: 10px; color: white; font-weight: 700; white-space: nowrap; }
        .cat-amount { width: 120px; text-align: right; font-size: 13px; font-weight: 600; color: #333; }
        .cat-pct { width: 50px; text-align: right; font-size: 12px; color: #888; }

        /* ── Comparison Table ─────────────────────────── */
        .positive { color: #28a745; font-weight: 700; }
        .negative { color: #dc3545; font-weight: 700; }
        .totals-row { background: #f2f4f3; font-weight: 700; }

        /* ── Message ──────────────────────────────────── */
        .msg { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 500; display: flex; align-items: center; gap: 10px; animation: slideIn 0.4s ease; }
        .msg-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .msg-error { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Empty State ──────────────────────────────── */
        .empty-state { text-align: center; padding: 40px; color: #aaa; }
        .empty-state .emoji { font-size: 48px; margin-bottom: 10px; }

        /* ── Print Styles ─────────────────────────────── */
        @media print {
            .sidebar, .no-print { display: none !important; }
            .content { margin-left: 0 !important; padding: 10px !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd; page-break-inside: avoid; }
            .stat-card { box-shadow: none !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .btn, .btn-sm-red, .filter-bar, .summary-filter { display: none !important; }
            body { background: white !important; }
        }
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
        <a href='admin_financial.php'>Invois & Pembayaran</a>
        <a href='admin_expense_report.php' class='active'>Perbelanjaan & Laporan</a>
        <div class='menu-label'>AKADEMIK</div>
        <a href='aktiviti_kelas.php'>Jadual Aktiviti</a>
        <a href='lesson_plan.php'>Rancangan Pengajaran</a>
        <div class='menu-label'>SISTEM</div>
        <a href='logout.php' style='color:#ff6961;'>Log Keluar</a>
    </div>

    <!-- ── MAIN CONTENT ─────────────────────────────── -->
    <div class="content">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:5px;">
            <div>
                <h1 class="page-title">📊 Perbelanjaan & Laporan Kewangan</h1>
                <p class="page-subtitle">Rekod perbelanjaan, ringkasan kewangan dan perbandingan bulanan</p>
            </div>
            <button onclick="window.print()" class="btn btn-print no-print">🖨️ Cetak Laporan</button>
        </div>

        <?php if ($msg): ?>
            <div class="msg <?php echo $msgType === 'error' ? 'msg-error' : 'msg-success'; ?>">
                <?php echo $msgType === 'error' ? '❌' : '✅'; ?>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- ── SECTION 1: Add Expense Form ─────────── -->
        <div class="card no-print">
            <div class="card-title"><span class="emoji">💸</span> Rekod Perbelanjaan Baru</div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="category" required>
                            <option value="">— Pilih Kategori —</option>
                            <option value="Salary">Salary (Gaji)</option>
                            <option value="Utilities">Utilities (Utiliti)</option>
                            <option value="Food & Supplies">Food & Supplies (Makanan & Bekalan)</option>
                            <option value="Equipment">Equipment (Peralatan)</option>
                            <option value="Maintenance">Maintenance (Penyelenggaraan)</option>
                            <option value="Transportation">Transportation (Pengangkutan)</option>
                            <option value="Miscellaneous">Miscellaneous (Pelbagai)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jumlah (RM)</label>
                        <input type="number" step="0.01" name="amount" required placeholder="0.00" min="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Keterangan</label>
                        <input type="text" name="description" required placeholder="Cth: Bayaran bil elektrik bulan Mei">
                    </div>
                    <div class="form-group">
                        <label>Tarikh</label>
                        <input type="date" name="expense_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Resit (pilihan)</label>
                        <input type="file" name="receipt_file" accept="image/*,.pdf" style="padding:8px;">
                    </div>
                </div>
                <button type="submit" name="add_expense" class="btn btn-green">💾 Simpan Perbelanjaan</button>
            </form>
        </div>

        <!-- ── SECTION 2: Financial Summary ────────── -->
        <div class="card">
            <div class="card-title"><span class="emoji">📈</span> Ringkasan Kewangan</div>

            <!-- Month/Year Filter -->
            <form method="GET" class="summary-filter no-print">
                <div class="form-group">
                    <label>Bulan</label>
                    <select name="summary_month">
                        <?php
                        $months_ms = ['', 'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];
                        for ($m = 1; $m <= 12; $m++):
                        ?>
                            <option value="<?php echo $m; ?>" <?php echo $summary_month == $m ? 'selected' : ''; ?>><?php echo $months_ms[$m]; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tahun</label>
                    <select name="summary_year">
                        <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $summary_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <!-- Preserve expense filter params -->
                <?php if ($filter_category): ?><input type="hidden" name="filter_category" value="<?php echo htmlspecialchars($filter_category); ?>"><?php endif; ?>
                <?php if ($filter_from): ?><input type="hidden" name="filter_from" value="<?php echo htmlspecialchars($filter_from); ?>"><?php endif; ?>
                <?php if ($filter_to): ?><input type="hidden" name="filter_to" value="<?php echo htmlspecialchars($filter_to); ?>"><?php endif; ?>
                <button type="submit" class="btn btn-green btn-sm">📊 Kira</button>
            </form>

            <p style="font-size:13px; color:#888; margin-bottom:15px;">
                Menunjukkan data untuk: <strong><?php echo $months_ms[$summary_month] . ' ' . $summary_year; ?></strong>
            </p>

            <!-- Stat Cards -->
            <div class="stat-row">
                <div class="stat-card stat-green">
                    <div class="stat-icon">💵</div>
                    <div class="stat-amount">RM <?php echo number_format($total_income, 2); ?></div>
                    <div class="stat-label">Jumlah Pendapatan</div>
                </div>
                <div class="stat-card stat-red">
                    <div class="stat-icon">💸</div>
                    <div class="stat-amount">RM <?php echo number_format($total_expenses, 2); ?></div>
                    <div class="stat-label">Jumlah Perbelanjaan</div>
                </div>
                <div class="stat-card <?php echo $net_balance >= 0 ? 'stat-blue' : 'stat-negative'; ?>">
                    <div class="stat-icon"><?php echo $net_balance >= 0 ? '📈' : '📉'; ?></div>
                    <div class="stat-amount">RM <?php echo number_format(abs($net_balance), 2); ?></div>
                    <div class="stat-label">Baki Bersih <?php echo $net_balance < 0 ? '(Defisit)' : ''; ?></div>
                </div>
            </div>

            <!-- Category Breakdown -->
            <?php if (!empty($categories)): ?>
            <h4 style="margin: 25px 0 15px; color: #444; font-size: 15px;">📋 Pecahan Mengikut Kategori</h4>
            <div class="cat-breakdown">
                <?php foreach ($categories as $cat):
                    $pct = $total_expenses > 0 ? ($cat['total'] / $total_expenses) * 100 : 0;
                    $color = $category_colors[$cat['category']] ?? '#95a5a6';
                ?>
                <div class="cat-item">
                    <div class="cat-name"><?php echo htmlspecialchars($cat['category']); ?></div>
                    <div class="cat-bar-wrap">
                        <div class="cat-bar" style="width: <?php echo max($pct, 2); ?>%; background: <?php echo $color; ?>;">
                            <span><?php echo number_format($pct, 1); ?>%</span>
                        </div>
                    </div>
                    <div class="cat-amount">RM <?php echo number_format($cat['total'], 2); ?></div>
                    <div class="cat-pct"><?php echo number_format($pct, 1); ?>%</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p style="color:#aaa; text-align:center; padding:20px;">Tiada perbelanjaan direkodkan bulan ini.</p>
            <?php endif; ?>
        </div>

        <!-- ── SECTION 3: Expense List ─────────────── -->
        <div class="card">
            <div class="card-title"><span class="emoji">📝</span> Senarai Perbelanjaan</div>

            <!-- Filter -->
            <form method="GET" class="filter-bar no-print">
                <!-- Preserve summary params -->
                <input type="hidden" name="summary_month" value="<?php echo $summary_month; ?>">
                <input type="hidden" name="summary_year" value="<?php echo $summary_year; ?>">
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="filter_category">
                        <option value="">Semua</option>
                        <?php
                        $all_cats = ['Salary','Utilities','Food & Supplies','Equipment','Maintenance','Transportation','Miscellaneous'];
                        foreach ($all_cats as $ac):
                        ?>
                            <option value="<?php echo $ac; ?>" <?php echo $filter_category === $ac ? 'selected' : ''; ?>><?php echo $ac; ?></option>
                        <?php endforeach; ?>
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
                <button type="submit" class="btn btn-green btn-sm">🔍 Tapis</button>
            </form>

            <div class="table-wrapper">
            <?php if ($expenses_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tarikh</th>
                            <th>Kategori</th>
                            <th>Keterangan</th>
                            <th>Jumlah (RM)</th>
                            <th>Resit</th>
                            <th class="no-print">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($exp = $expenses_result->fetch_assoc()):
                        $color = $category_colors[$exp['category']] ?? '#95a5a6';
                    ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($exp['expense_date'])); ?></td>
                            <td><span class="cat-tag" style="background:<?php echo $color; ?>;"><?php echo htmlspecialchars($exp['category']); ?></span></td>
                            <td><?php echo htmlspecialchars($exp['description']); ?></td>
                            <td style="font-weight:600;">RM <?php echo number_format($exp['amount'], 2); ?></td>
                            <td>
                                <?php if ($exp['receipt_file']): ?>
                                    <a href="<?php echo htmlspecialchars($exp['receipt_file']); ?>" target="_blank" style="color:#3498db; font-weight:600;">📎 Lihat</a>
                                <?php else: ?>
                                    <span style="color:#ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="no-print">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Pasti mahu padam perbelanjaan ini?');">
                                    <input type="hidden" name="expense_id" value="<?php echo $exp['id']; ?>">
                                    <button type="submit" name="delete_expense" class="btn-sm-red">🗑️ Padam</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="emoji">📭</div>
                    <p>Tiada perbelanjaan ditemui.</p>
                </div>
            <?php endif; ?>
            </div>
        </div>

        <!-- ── SECTION 4: Monthly Comparison ───────── -->
        <div class="card">
            <div class="card-title"><span class="emoji">📊</span> Perbandingan Bulanan (6 Bulan Terakhir)</div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th>Pendapatan (RM)</th>
                            <th>Perbelanjaan (RM)</th>
                            <th>Baki (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $total_inc_6 = 0;
                    $total_exp_6 = 0;
                    foreach ($monthly_comparison as $mc):
                        $total_inc_6 += $mc['income'];
                        $total_exp_6 += $mc['expense'];
                    ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo $mc['label']; ?></td>
                            <td>RM <?php echo number_format($mc['income'], 2); ?></td>
                            <td>RM <?php echo number_format($mc['expense'], 2); ?></td>
                            <td class="<?php echo $mc['balance'] >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo $mc['balance'] < 0 ? '-' : ''; ?>RM <?php echo number_format(abs($mc['balance']), 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                        <tr class="totals-row">
                            <td>JUMLAH</td>
                            <td>RM <?php echo number_format($total_inc_6, 2); ?></td>
                            <td>RM <?php echo number_format($total_exp_6, 2); ?></td>
                            <?php $total_bal_6 = $total_inc_6 - $total_exp_6; ?>
                            <td class="<?php echo $total_bal_6 >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo $total_bal_6 < 0 ? '-' : ''; ?>RM <?php echo number_format(abs($total_bal_6), 2); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>
