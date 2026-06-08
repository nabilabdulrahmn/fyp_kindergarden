<?php
// view_payslip.php
// Paparan Cetakan Slip Gaji Rasmi - Print-ready A4 Page
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

if (!isset($_GET['id'])) {
    die("ID Penggajian tidak dinyatakan.");
}

$payroll_id = (int)$_GET['id'];

// Ambil maklumat slip gaji & staff
$sql = "SELECT p.*, s.full_name AS staff_name, s.ic_number AS staff_ic, s.position AS staff_position,
               s.department AS staff_dept, s.employment_type, s.user_id AS staff_user_id
        FROM payroll p
        JOIN staff s ON p.staff_id = s.id
        WHERE p.id = $payroll_id LIMIT 1";

$result = $conn->query($sql);
if (!$result || $result->num_rows == 0) {
    die("Rekod slip gaji tidak dijumpai.");
}

$pay = $result->fetch_assoc();

// Sekuriti: Staf hanya boleh lihat slip gaji sendiri, admin boleh lihat semua
if ($role !== 'admin' && (int)$pay['staff_user_id'] !== $user_id) {
    die("Akses dinafikan. Anda tidak dibenarkan melihat slip gaji ini.");
}

// Parse breakdown details
$allowance_list = [];
if (!empty($pay['allowance_details'])) {
    $allowance_list = json_decode($pay['allowance_details'], true);
}
if (empty($allowance_list) && $pay['allowances'] > 0) {
    $allowance_list[] = ['name' => 'Elaun Am', 'amount' => $pay['allowances']];
}

$deduction_list = [];
if (!empty($pay['deduction_details'])) {
    $deduction_list = json_decode($pay['deduction_details'], true);
}
if (empty($deduction_list) && $pay['deductions'] > 0) {
    $deduction_list[] = ['name' => 'Potongan Am', 'amount' => $pay['deductions']];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji <?php echo date('M Y', strtotime($pay['month']."-01")); ?> - <?php echo htmlspecialchars($pay['staff_name']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #f0f0f0; margin: 0; padding: 20px; }
        .payslip-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; background: #fff; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); border-radius: 8px; position: relative; }
        
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .header-table td { vertical-align: middle; border: none; }
        
        .logo-section h2 { margin: 0; color: #00897b; font-size: 24px; font-weight: 700; }
        .logo-section p { margin: 3px 0; font-size: 11px; color: #666; }
        
        .payslip-title { text-align: right; }
        .payslip-title h1 { margin: 0 0 5px 0; color: #00897b; font-size: 24px; letter-spacing: 0.5px; }
        .payslip-title p { margin: 2px 0; font-size: 12px; color: #555; }
        
        .divider { border-top: 2px solid #00897b; margin: 15px 0; opacity: 0.2; }
        
        .emp-details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; line-height: 1.6; }
        .emp-details-table td { width: 25%; padding: 5px; border: none; }
        .emp-details-table td.label { font-weight: bold; color: #555; }
        
        .salary-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #ddd; }
        .salary-table th { background: #00897b; color: #fff; padding: 8px 10px; font-size: 12px; text-transform: uppercase; border: 1px solid #00897b; }
        .salary-table td { width: 50%; vertical-align: top; padding: 0; border: 1px solid #ddd; }
        
        .breakdown-table { width: 100%; border-collapse: collapse; }
        .breakdown-table td { padding: 8px 10px; font-size: 12px; border: none; border-bottom: 1px solid #f0f0f0; }
        .breakdown-table tr:last-child td { border-bottom: none; }
        .breakdown-table td.amount { text-align: right; font-weight: 500; }
        
        .total-box-table { width: 100%; border-collapse: collapse; font-size: 12px; background: #e0f2f1; }
        .total-box-table td { padding: 10px; font-weight: bold; color: #00695c; }
        
        .net-salary-section { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 15px; background: #00897b; color: #fff; border-radius: 4px; overflow: hidden; }
        .net-salary-section td { padding: 12px; font-weight: bold; }
        
        .sign-table { width: 100%; border-collapse: collapse; margin-top: 50px; font-size: 12px; }
        .sign-table td { width: 50%; text-align: center; border: none; }
        .sign-line { width: 200px; border-bottom: 1px solid #333; margin: 50px auto 5px auto; }

        .footer { text-align: center; margin-top: 40px; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 15px; }
        
        .no-print-bar { max-width: 800px; margin: 0 auto 15px auto; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border: none; }
        .btn-print { background: #00897b; color: white; }
        .btn-print:hover { background: #00695c; }
        .btn-back { background: #e0e0e0; color: #333; }
        .btn-back:hover { background: #d0d0d0; }

        @media print {
            body { background: #fff; padding: 0; }
            .payslip-box { border: none; box-shadow: none; padding: 0; }
            .no-print-bar { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <button onclick="window.history.back();" class="btn btn-back">⬅️ Kembali</button>
        <button onclick="window.print();" class="btn btn-print">🖨️ Cetak Slip Gaji (A4)</button>
    </div>

    <div class="payslip-box">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-section">
                    <h2>TASKA CARE CENTRE</h2>
                    <p>Penyedia Perkhidmatan Kebajikan & Pendidikan Kanak-Kanak</p>
                    <p>123, Jalan Taman Melati, Kuala Lumpur</p>
                </td>
                <td class="payslip-title">
                    <h1>SLIP PENYATA GAJI</h1>
                    <p><strong>Bulan Gaji:</strong> <?php echo date('F Y', strtotime($pay['month']."-01")); ?></p>
                    <p><strong>Status:</strong> <span style="font-weight: bold; color: <?php echo $pay['payment_status'] === 'Paid' ? '#2e7d32' : '#ff8f00'; ?>"><?php echo $pay['payment_status'] === 'Paid' ? 'DIBAYAR' : 'TERTUNGGAK'; ?></span></p>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- Employee details -->
        <table class="emp-details-table">
            <tr>
                <td class="label">Nama Pekerja:</td>
                <td><?php echo htmlspecialchars($pay['staff_name']); ?></td>
                <td class="label">Jabatan:</td>
                <td><?php echo htmlspecialchars($pay['staff_dept']); ?></td>
            </tr>
            <tr>
                <td class="label">No. IC:</td>
                <td><?php echo htmlspecialchars($pay['staff_ic'] ?: '-'); ?></td>
                <td class="label">Jawatan:</td>
                <td><?php echo htmlspecialchars($pay['staff_position']); ?></td>
            </tr>
            <tr>
                <td class="label">Jenis Pekerjaan:</td>
                <td><?php echo htmlspecialchars($pay['employment_type']); ?></td>
                <td class="label">Tarikh Bayar:</td>
                <td><?php echo $pay['paid_at'] ? date('d M Y', strtotime($pay['paid_at'])) : '-'; ?></td>
            </tr>
        </table>

        <!-- Salary Breakdown -->
        <table class="salary-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Pendapatan & Elaun (Earnings)</th>
                    <th style="width: 50%;">Potongan (Deductions)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <!-- Earnings side -->
                    <td>
                        <table class="breakdown-table">
                            <tr>
                                <td>Gaji Pokok (Basic Salary)</td>
                                <td class="amount">RM <?php echo number_format($pay['basic_salary'], 2); ?></td>
                            </tr>
                            <?php foreach ($allowance_list as $allow): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($allow['name']); ?></td>
                                    <td class="amount">RM <?php echo number_format($allow['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </td>
                    
                    <!-- Deductions side -->
                    <td>
                        <table class="breakdown-table">
                            <?php if (empty($deduction_list)): ?>
                                <tr>
                                    <td style="color:#999; font-style:italic;">Tiada potongan bulan ini</td>
                                    <td class="amount">RM 0.00</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($deduction_list as $ded): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ded['name']); ?></td>
                                        <td class="amount">RM <?php echo number_format($ded['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </table>
                    </td>
                </tr>
                
                <!-- Totals -->
                <tr>
                    <td>
                        <table class="total-box-table">
                            <tr>
                                <td>Jumlah Pendapatan:</td>
                                <td style="text-align: right;">RM <?php echo number_format($pay['basic_salary'] + $pay['allowances'], 2); ?></td>
                            </tr>
                        </table>
                    </td>
                    <td>
                        <table class="total-box-table" style="background: #ffe0b2; color: #e65100;">
                            <tr>
                                <td style="color: #b71c1c;">Jumlah Potongan:</td>
                                <td style="text-align: right; color: #b71c1c;">RM <?php echo number_format($pay['deductions'], 2); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Net Salary -->
        <table class="net-salary-section">
            <tr>
                <td>GAJI BERSIH (NET SALARY)</td>
                <td style="text-align: right; font-size: 18px;">RM <?php echo number_format($pay['net_salary'], 2); ?></td>
            </tr>
        </table>

        <!-- Signatures -->
        <table class="sign-table">
            <tr>
                <td>
                    <p>Disediakan Oleh:</p>
                    <div class="sign-line"></div>
                    <p><strong>Pihak Pentadbiran</strong><br>Taska Care Centre</p>
                </td>
                <td>
                    <p>Diterima Oleh:</p>
                    <div class="sign-line"></div>
                    <p><strong><?php echo htmlspecialchars($pay['staff_name']); ?></strong><br>Tarikh: ....................................</p>
                </td>
            </tr>
        </table>

        <div class="footer">
            Penyata ini dijanakan secara digital. Sebarang kemusykilan sila hubungi Bahagian Sumber Manusia.
        </div>
    </div>

</body>
</html>
