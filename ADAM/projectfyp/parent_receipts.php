<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'parent') {
    header('Location: login.php');
    exit();
}
require 'db.php';

$themeColor = '#84b6f4';

// Get parent_id
$parent_q = $conn->prepare('SELECT id FROM parents WHERE user_id = ?');
$parent_q->bind_param('i', $_SESSION['user_id']);
$parent_q->execute();
$parent_id = $parent_q->get_result()->fetch_assoc()['id'];

// Get parent full_name
$parent_name_q = $conn->prepare('SELECT full_name FROM parents WHERE id = ?');
$parent_name_q->bind_param('i', $parent_id);
$parent_name_q->execute();
$parent_name = $parent_name_q->get_result()->fetch_assoc()['full_name'];

// Summary cards queries
// 1. Jumlah Dibayar (All Time)
$total_q = $conn->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE parent_id = ? AND status = "Completed"');
$total_q->bind_param('i', $parent_id);
$total_q->execute();
$total_all = $total_q->get_result()->fetch_assoc()['total'];

// 2. Tahun Ini
$year_q = $conn->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE parent_id = ? AND status = "Completed" AND YEAR(payment_date) = YEAR(CURDATE())');
$year_q->bind_param('i', $parent_id);
$year_q->execute();
$total_year = $year_q->get_result()->fetch_assoc()['total'];

// 3. Bulan Ini
$month_q = $conn->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE parent_id = ? AND status = "Completed" AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())');
$month_q->bind_param('i', $parent_id);
$month_q->execute();
$total_month = $month_q->get_result()->fetch_assoc()['total'];

// Get children for filter dropdown
$children_q = $conn->prepare('SELECT id, full_name FROM students WHERE parent_id = ?');
$children_q->bind_param('i', $parent_id);
$children_q->execute();
$children_result = $children_q->get_result();
$children = [];
while($child = $children_result->fetch_assoc()) {
    $children[] = $child;
}

// Filters
$filter_child = isset($_GET['child_id']) ? intval($_GET['child_id']) : 0;
$filter_from = isset($_GET['from']) ? $_GET['from'] : '';
$filter_to = isset($_GET['to']) ? $_GET['to'] : '';

// Build payment history query
$sql = 'SELECT p.*, i.invoice_number, i.type, s.full_name as student_name FROM payments p JOIN invoices i ON p.invoice_id = i.id JOIN students s ON i.student_id = s.id WHERE p.parent_id = ? AND p.status = "Completed"';
$params = [$parent_id];
$types = 'i';

if($filter_child > 0) {
    $sql .= ' AND i.student_id = ?';
    $params[] = $filter_child;
    $types .= 'i';
}
if(!empty($filter_from)) {
    $sql .= ' AND DATE(p.payment_date) >= ?';
    $params[] = $filter_from;
    $types .= 's';
}
if(!empty($filter_to)) {
    $sql .= ' AND DATE(p.payment_date) <= ?';
    $params[] = $filter_to;
    $types .= 's';
}

$sql .= ' ORDER BY p.payment_date DESC';

$payments_q = $conn->prepare($sql);
$payments_q->bind_param($types, ...$params);
$payments_q->execute();
$payments_result = $payments_q->get_result();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sejarah pembayaran dan resit - Portal Ibu Bapa Tadika KiddieCare">
    <title>Sejarah Pembayaran - Portal Ibu Bapa</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7f6;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 270px;
            background: linear-gradient(180deg, <?php echo $themeColor; ?>, #5a9bd5);
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-header {
            padding: 30px 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .menu-label {
            padding: 20px 25px 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.5);
        }
        .sidebar a {
            display: block;
            padding: 12px 25px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        .sidebar a:hover {
            background: rgba(255,255,255,0.12);
            color: #fff;
        }
        .sidebar a.active {
            background: rgba(255,255,255,0.25);
            font-weight: bold;
            border-left: 4px solid white;
            color: #fff;
        }

        /* Content */
        .content {
            flex: 1;
            margin-left: 270px;
            padding: 35px 40px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .page-subtitle {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 30px;
        }

        /* Summary Cards */
        .summary-cards {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            flex: 1;
            border-radius: 15px;
            padding: 25px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .summary-card.green { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .summary-card.blue { background: linear-gradient(135deg, #2980b9, #3498db); }
        .summary-card.purple { background: linear-gradient(135deg, #8e44ad, #9b59b6); }
        .summary-card .card-icon {
            font-size: 38px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .summary-card .card-label {
            font-size: 13px;
            opacity: 0.85;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .summary-card .card-amount {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .summary-card::after {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        /* Filter Bar */
        .filter-bar {
            background: #fff;
            border-radius: 15px;
            padding: 20px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-bar label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }
        .filter-bar select,
        .filter-bar input[type="date"] {
            padding: 9px 14px;
            border: 1px solid #dde1e6;
            border-radius: 8px;
            font-size: 13px;
            font-family: inherit;
            color: #333;
            background: #f9fafb;
            outline: none;
            transition: border-color 0.2s;
        }
        .filter-bar select:focus,
        .filter-bar input[type="date"]:focus {
            border-color: <?php echo $themeColor; ?>;
        }
        .btn-filter {
            padding: 9px 22px;
            background: <?php echo $themeColor; ?>;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            font-family: inherit;
        }
        .btn-filter:hover {
            background: #5a9bd5;
            transform: translateY(-1px);
        }

        /* Table */
        .table-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .table-card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eef1f5;
        }
        .table-card-header h3 {
            font-size: 17px;
            color: #2c3e50;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background: #f7f9fc;
            padding: 14px 18px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #eef1f5;
        }
        tbody td {
            padding: 14px 18px;
            font-size: 13.5px;
            color: #444;
            border-bottom: 1px solid #f2f4f7;
        }
        tbody tr {
            transition: background 0.15s ease;
        }
        tbody tr:hover {
            background: #f7fafd;
        }
        .badge-method {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #eaf2fd;
            color: #2980b9;
        }
        .btn-view-receipt {
            padding: 7px 16px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
            font-family: inherit;
        }
        .btn-view-receipt:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(39,174,96,0.3);
        }
        .no-records {
            text-align: center;
            padding: 50px 20px;
            color: #95a5a6;
            font-size: 15px;
        }
        .no-records .no-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        /* Receipt Modal */
        .receipt-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.55);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.25s ease;
        }
        .receipt-overlay.show {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .receipt-content {
            background: #fff;
            max-width: 600px;
            width: 90%;
            border-radius: 15px;
            padding: 40px 45px;
            position: relative;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .receipt-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        .receipt-header .subtitle {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 3px;
        }
        .receipt-header .contact {
            font-size: 12px;
            color: #95a5a6;
        }
        .receipt-divider {
            border: none;
            border-top: 2px dashed #dde1e6;
            margin: 18px 0;
        }
        .receipt-title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
        }
        .receipt-table td {
            padding: 10px 5px;
            font-size: 14px;
            color: #444;
            border-bottom: 1px solid #f2f4f7;
        }
        .receipt-table td:first-child {
            font-weight: 600;
            color: #555;
            width: 40%;
        }
        .receipt-table td:last-child {
            color: #2c3e50;
        }
        .receipt-footer {
            text-align: center;
            font-size: 12px;
            color: #95a5a6;
            margin-top: 18px;
            font-style: italic;
        }
        .receipt-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 25px;
        }
        .btn-print {
            padding: 10px 28px;
            background: <?php echo $themeColor; ?>;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            font-family: inherit;
        }
        .btn-print:hover {
            background: #5a9bd5;
            transform: translateY(-1px);
        }
        .btn-close-receipt {
            padding: 10px 28px;
            background: #e0e5eb;
            color: #555;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            font-family: inherit;
        }
        .btn-close-receipt:hover {
            background: #cdd3da;
            transform: translateY(-1px);
        }

        /* Print styles */
        @media print {
            body * {
                visibility: hidden;
            }
            #receiptModal,
            #receiptModal .receipt-content,
            #receiptModal .receipt-content * {
                visibility: visible;
            }
            #receiptModal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: #fff;
                display: flex;
                justify-content: center;
                align-items: flex-start;
                padding-top: 20px;
            }
            #receiptModal .receipt-content {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
                width: 100%;
            }
            .receipt-actions {
                display: none !important;
            }
            .sidebar, .content {
                display: none !important;
            }
        }

        /* Responsive */
        @media (max-width: 900px) {
            .summary-cards {
                flex-direction: column;
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class='sidebar'>
    <div class='sidebar-header'><h2>Portal Ibu Bapa</h2></div>
    <div class='menu-label'>ANAK SAYA</div>
    <a href='home.php'>Dashboard</a>
    <a href='parent_registration.php'>Pendaftaran Anak</a>
    <div class='menu-label'>KEWANGAN</div>
    <a href='parent_invoices.php'>Invois & Pembayaran</a>
    <a href='parent_receipts.php' class='active'>Sejarah Pembayaran</a>
    <div class='menu-label'>AKADEMIK</div>
    <a href='home.php'>Laporan Aktiviti</a>
    <a href='home.php'>Prestasi Anak</a>
    <div class='menu-label'>AKAUN</div>
    <a href='logout.php' style='color:#ff6961;'>Log Keluar</a>
</div>

<!-- Content -->
<div class="content">
    <h1 class="page-title">📄 Sejarah Pembayaran</h1>
    <p class="page-subtitle">Lihat semua rekod pembayaran dan cetak resit anda</p>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card green">
            <div class="card-icon">💰</div>
            <div class="card-label">Jumlah Dibayar (Keseluruhan)</div>
            <div class="card-amount">RM <?php echo number_format($total_all, 2); ?></div>
        </div>
        <div class="summary-card blue">
            <div class="card-icon">📅</div>
            <div class="card-label">Tahun Ini (<?php echo date('Y'); ?>)</div>
            <div class="card-amount">RM <?php echo number_format($total_year, 2); ?></div>
        </div>
        <div class="summary-card purple">
            <div class="card-icon">📆</div>
            <div class="card-label">Bulan Ini (<?php echo date('M Y'); ?>)</div>
            <div class="card-amount">RM <?php echo number_format($total_month, 2); ?></div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar" id="filterForm">
        <label for="child_id">Anak:</label>
        <select name="child_id" id="child_id">
            <option value="0">Semua Anak</option>
            <?php foreach($children as $child): ?>
                <option value="<?php echo $child['id']; ?>" <?php echo ($filter_child == $child['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($child['full_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="from">Dari:</label>
        <input type="date" name="from" id="from" value="<?php echo htmlspecialchars($filter_from); ?>">

        <label for="to">Hingga:</label>
        <input type="date" name="to" id="to" value="<?php echo htmlspecialchars($filter_to); ?>">

        <button type="submit" class="btn-filter">🔍 Tapis</button>
    </form>

    <!-- Payment History Table -->
    <div class="table-card">
        <div class="table-card-header">
            <h3>Senarai Pembayaran</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>No. Resit</th>
                    <th>No. Invois</th>
                    <th>Nama Anak</th>
                    <th>Jenis Yuran</th>
                    <th>Jumlah (RM)</th>
                    <th>Kaedah</th>
                    <th>Tarikh Bayaran</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if($payments_result->num_rows > 0): ?>
                    <?php while($row = $payments_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['receipt_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['type']); ?></td>
                            <td><strong>RM <?php echo number_format($row['amount'], 2); ?></strong></td>
                            <td><span class="badge-method"><?php echo htmlspecialchars($row['payment_method']); ?></span></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['payment_date'])); ?></td>
                            <td>
                                <button class="btn-view-receipt" onclick='showReceipt(<?php echo json_encode([
                                    "receipt_number" => $row["receipt_number"],
                                    "payment_date" => date("d/m/Y H:i", strtotime($row["payment_date"])),
                                    "parent_name" => $parent_name,
                                    "student_name" => $row["student_name"],
                                    "type" => $row["type"],
                                    "invoice_number" => $row["invoice_number"],
                                    "amount" => number_format($row["amount"], 2),
                                    "payment_method" => $row["payment_method"],
                                    "transaction_ref" => $row["transaction_ref"]
                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?>)'>📄 Lihat Resit</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="no-records">
                                <div class="no-icon">📭</div>
                                Tiada sejarah pembayaran
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Receipt Modal -->
<div class="receipt-overlay" id="receiptModal" onclick="if(event.target===this) hideReceipt();">
    <div class="receipt-content">
        <div class="receipt-header">
            <h2>TADIKA KIDDIECARE</h2>
            <div class="subtitle">Pusat Penjagaan & Pendidikan Kanak-Kanak</div>
            <div class="contact">Tel: 03-12345678 | Email: info@tadika-kiddiecare.com</div>
        </div>
        <hr class="receipt-divider">
        <div class="receipt-title">RESIT PEMBAYARAN</div>
        <table class="receipt-table">
            <tr><td>No. Resit</td><td id="r_receipt_number"></td></tr>
            <tr><td>Tarikh</td><td id="r_payment_date"></td></tr>
            <tr><td>Nama Ibu Bapa</td><td id="r_parent_name"></td></tr>
            <tr><td>Nama Anak</td><td id="r_student_name"></td></tr>
            <tr><td>Jenis Yuran</td><td id="r_type"></td></tr>
            <tr><td>No. Invois</td><td id="r_invoice_number"></td></tr>
            <tr><td>Jumlah</td><td id="r_amount"></td></tr>
            <tr><td>Kaedah Pembayaran</td><td id="r_payment_method"></td></tr>
            <tr><td>No. Transaksi</td><td id="r_transaction_ref"></td></tr>
        </table>
        <hr class="receipt-divider">
        <div class="receipt-footer">Ini adalah resit rasmi yang dijana oleh sistem.</div>
        <div class="receipt-actions">
            <button class="btn-print" onclick="window.print();">🖨️ Cetak Resit</button>
            <button class="btn-close-receipt" onclick="hideReceipt();">✖ Tutup</button>
        </div>
    </div>
</div>

<script>
function showReceipt(data) {
    document.getElementById('r_receipt_number').textContent = data.receipt_number || '-';
    document.getElementById('r_payment_date').textContent = data.payment_date || '-';
    document.getElementById('r_parent_name').textContent = data.parent_name || '-';
    document.getElementById('r_student_name').textContent = data.student_name || '-';
    document.getElementById('r_type').textContent = data.type || '-';
    document.getElementById('r_invoice_number').textContent = data.invoice_number || '-';
    document.getElementById('r_amount').textContent = 'RM ' + (data.amount || '0.00');
    document.getElementById('r_payment_method').textContent = data.payment_method || '-';
    document.getElementById('r_transaction_ref').textContent = data.transaction_ref || '-';
    document.getElementById('receiptModal').classList.add('show');
}

function hideReceipt() {
    document.getElementById('receiptModal').classList.remove('show');
}

document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') hideReceipt();
});
</script>

</body>
</html>
