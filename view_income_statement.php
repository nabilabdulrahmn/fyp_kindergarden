<?php
// view_income_statement.php
// Penyata Pendapatan Cetakan Rasmi - Print-ready A4 Page
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'pengetua'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

$print_month = isset($_GET['print_month']) ? $_GET['print_month'] : '';
$print_year = isset($_GET['print_year']) ? (int)$_GET['print_year'] : (int)date('Y');

$period_label = '';
$where_pay = '';
$where_payroll = '';
$where_exp = '';

if (!empty($print_month)) {
    $month_str = sprintf("%04d-%02d", $print_year, $print_month);
    $period_label = date('F Y', mktime(0, 0, 0, (int)$print_month, 1, $print_year));
    
    $where_pay = " AND DATE_FORMAT(payment_date, '%Y-%m') = '$month_str' ";
    $where_payroll = " AND month = '$month_str' ";
    $where_exp = " AND DATE_FORMAT(expense_date, '%Y-%m') = '$month_str' ";
} else {
    $period_label = "Tahun " . $print_year;
    
    $where_pay = " AND DATE_FORMAT(payment_date, '%Y') = '$print_year' ";
    $where_payroll = " AND month LIKE '$print_year-%' ";
    $where_exp = " AND DATE_FORMAT(expense_date, '%Y') = '$print_year' ";
}

// 1. Kutipan Hasil (Revenue from verified parent payments)
$rev_sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total FROM payments WHERE status = 'Verified' $where_pay";
$rev_res = $conn->query($rev_sql);
$total_revenue = $rev_res ? (float)$rev_res->fetch_assoc()['total'] : 0.0;

// 2. Kos Gaji Kakitangan (Staff Payroll expenses where Paid)
$pay_sql = "SELECT COALESCE(SUM(net_salary), 0) AS total FROM payroll WHERE payment_status = 'Paid' $where_payroll";
$pay_res = $conn->query($pay_sql);
$total_payroll = $pay_res ? (float)$pay_res->fetch_assoc()['total'] : 0.0;

// 3. Kos Operasi - Group by Category
$cat_totals = [
    'Operasi' => 0.0,
    'Barangan Runcit & Makanan' => 0.0,
    'Alat Tulis & BBM' => 0.0,
    'Penyelenggaraan' => 0.0,
    'Lain-lain' => 0.0
];

$exp_sql = "SELECT category, SUM(amount) AS total FROM expenses WHERE 1=1 $where_exp GROUP BY category";
$exp_res = $conn->query($exp_sql);
$total_operating = 0.0;

if ($exp_res) {
    while ($row = $exp_res->fetch_assoc()) {
        $cat = $row['category'];
        $amt = (float)$row['total'];
        if (array_key_exists($cat, $cat_totals)) {
            $cat_totals[$cat] = $amt;
        } else {
            $cat_totals['Lain-lain'] += $amt;
        }
        $total_operating += $amt;
    }
}

// 4. Untung Bersih (Net profit)
$net_profit = $total_revenue - $total_payroll - $total_operating;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Penyata Pendapatan - <?php echo $period_label; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #f0f0f0; margin: 0; padding: 20px; }
        .statement-box { max-width: 800px; margin: auto; padding: 40px; border: 1px solid #eee; background: #fff; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); border-radius: 8px; position: relative; }
        
        .header-section { text-align: center; margin-bottom: 30px; }
        .header-section h2 { margin: 0; color: #1a1c2e; font-size: 24px; font-weight: 700; letter-spacing: 0.5px; }
        .header-section p { margin: 4px 0; font-size: 12px; color: #666; }
        
        .period-title { text-align: center; margin-bottom: 25px; font-size: 15px; font-weight: 600; color: #555; background: #f9f9f9; padding: 8px; border-radius: 4px; border: 1px solid #eee; }
        
        .divider { border-top: 2px solid #1a1c2e; margin: 15px 0; opacity: 0.15; }
        
        .finance-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .finance-table td { padding: 10px; font-size: 13px; border-bottom: 1px solid #f9f9f9; }
        .finance-table tr.header-row td { font-weight: bold; font-size: 14px; color: #1a1c2e; background: #f5f5f5; border-bottom: 2px solid #ddd; }
        .finance-table tr.subtotal-row td { font-weight: bold; border-top: 1px solid #333; border-bottom: 1px solid #333; }
        .finance-table tr.grandtotal-row td { font-weight: bold; font-size: 16px; background: #e0f2f1; color: #00695c; border-top: 2px solid #00695c; border-bottom: 2px double #00695c; }
        .finance-table tr.grandtotal-row.loss td { background: #ffe5e5; color: #c62828; border-top: 2px solid #c62828; border-bottom: 2px double #c62828; }
        
        .text-right { text-align: right; }
        .indent { padding-left: 30px !important; }
        
        .sign-table { width: 100%; border-collapse: collapse; margin-top: 50px; font-size: 12px; }
        .sign-table td { width: 50%; text-align: center; border: none; }
        .sign-line { width: 200px; border-bottom: 1px solid #333; margin: 50px auto 5px auto; }

        .footer { text-align: center; margin-top: 50px; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 15px; }
        
        .no-print-bar { max-width: 800px; margin: 0 auto 15px auto; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border: none; }
        .btn-print { background: #1a1c2e; color: white; }
        .btn-print:hover { background: #111322; }
        .btn-back { background: #e0e0e0; color: #333; }
        .btn-back:hover { background: #d0d0d0; }

        @media print {
            body { background: #fff; padding: 0; }
            .statement-box { border: none; box-shadow: none; padding: 0; }
            .no-print-bar { display: none; }
        }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="no-print-bar">
        <button onclick="window.close();" class="btn btn-back">⬅️ Tutup Halaman</button>
        <button onclick="window.print();" class="btn btn-print">🖨️ Cetak Penyata Pendapatan (A4)</button>
    </div>

    <div class="statement-box">
        <!-- Header -->
        <div class="header-section">
            <h2>TASKA CARE CENTRE</h2>
            <p>Penyata Pendapatan Komprehensif (Income Statement)</p>
            <p>123, Jalan Taman Melati, Kuala Lumpur | Tel: +603-4100 0000</p>
        </div>

        <div class="period-title">
            Bagi Tempoh Berakhir: <?php echo $period_label; ?>
        </div>

        <div class="divider"></div>

        <!-- Statement Financial Grid -->
        <table class="finance-table">
            <!-- PENDAPATAN -->
            <tr class="header-row">
                <td>PENDAPATAN / HASIL (REVENUES)</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="indent">Kutipan Yuran Pelajar (Verified Fees)</td>
                <td class="text-right">RM <?php echo number_format($total_revenue, 2); ?></td>
                <td></td>
            </tr>
            <tr class="subtotal-row">
                <td>JUMLAH HASIL (A)</td>
                <td></td>
                <td class="text-right">RM <?php echo number_format($total_revenue, 2); ?></td>
            </tr>
            
            <tr><td colspan="3" style="border:none; height:15px;"></td></tr>

            <!-- PERBELANJAAN -->
            <tr class="header-row">
                <td>PERBELANJAAN (EXPENSES)</td>
                <td></td>
                <td></td>
            </tr>
            <!-- Gaji -->
            <tr>
                <td class="indent">Kos Penggajian Kakitangan (Paid Payroll)</td>
                <td class="text-right">RM <?php echo number_format($total_payroll, 2); ?></td>
                <td></td>
            </tr>
            <!-- Kos Operasi by Category -->
            <tr>
                <td class="indent">Kos Operasi (Air, Elektrik, Sewa)</td>
                <td class="text-right">RM <?php echo number_format($cat_totals['Operasi'], 2); ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="indent">Kos Barangan Runcit & Makanan</td>
                <td class="text-right">RM <?php echo number_format($cat_totals['Barangan Runcit & Makanan'], 2); ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="indent">Kos Alat Tulis & Bahan Mengajar (BBM)</td>
                <td class="text-right">RM <?php echo number_format($cat_totals['Alat Tulis & BBM'], 2); ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="indent">Kos Penyelenggaraan & Pembaikan</td>
                <td class="text-right">RM <?php echo number_format($cat_totals['Penyelenggaraan'], 2); ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="indent">Kos Lain-lain Belanja</td>
                <td class="text-right">RM <?php echo number_format($cat_totals['Lain-lain'], 2); ?></td>
                <td></td>
            </tr>
            <tr class="subtotal-row">
                <td>JUMLAH BELANJA (B)</td>
                <td></td>
                <td class="text-right" style="color: #c62828;">RM <?php echo number_format($total_payroll + $total_operating, 2); ?></td>
            </tr>

            <tr><td colspan="3" style="border:none; height:20px;"></td></tr>

            <!-- UNTUNG/RUGI BERSIH -->
            <tr class="grandtotal-row <?php echo $net_profit < 0 ? 'loss' : ''; ?>">
                <td>UNTUNG / (RUGI) BERSIH (A - B)</td>
                <td></td>
                <td class="text-right">RM <?php echo number_format($net_profit, 2); ?></td>
            </tr>
        </table>

        <!-- Signatures -->
        <table class="sign-table">
            <tr>
                <td>
                    <p>Disediakan Oleh:</p>
                    <div class="sign-line"></div>
                    <p><strong>Pengurus Kewangan</strong><br>Taska Care Centre</p>
                </td>
                <td>
                    <p>Disahkan Oleh:</p>
                    <div class="sign-line"></div>
                    <p><strong>Pengetua / Pentadbir Utama</strong><br>Taska Care Centre</p>
                </td>
            </tr>
        </table>

        <div class="footer">
            Laporan Penyata Pendapatan ini dijana secara komputer oleh Sistem Pengurusan Kewangan Taska Care Centre.
        </div>
    </div>


</main>
</body>
</html>
