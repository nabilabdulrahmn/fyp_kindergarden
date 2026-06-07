<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';
require_once 'includes/admin_layout.php';

sahkan_peranan('admin');

// Date Range Filter
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');

// 1. Revenue Charts Data
// Monthly Revenue (last 6 months, ignoring filter for the chart to show trend)
$months = [];
$monthly_revenue = [];
for($i=5; $i>=0; $i--) {
    $m_val = date('Y-m', strtotime("-$i months"));
    $months[] = date('M', strtotime($m_val."-01"));
    $res = $conn->query("SELECT SUM(amount_paid) as s FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$m_val' AND status='Verified'");
    $monthly_revenue[] = (float)($res->fetch_assoc()['s'] ?? 0);
}

// Fee Type Breakdown (using line items from paid invoices in range)
$fee_types = [];
$fee_amounts = [];
$sql_fee = "SELECT ili.description, SUM(ili.line_total) as s 
            FROM invoice_line_items ili 
            JOIN invoices i ON ili.invoice_id = i.id 
            WHERE i.status IN ('Paid', 'Partial') 
            AND i.issued_date BETWEEN '$from_date' AND '$to_date'
            GROUP BY ili.description 
            ORDER BY s DESC LIMIT 5";
$res_fee = $conn->query($sql_fee);
while($r = $res_fee->fetch_assoc()) {
    $fee_types[] = $r['description'];
    $fee_amounts[] = (float)$r['s'];
}

// 2. Collection Rate Table
$collections = [];
$sql_col = "SELECT c.class_name, 
            SUM(i.total_amount) as invoiced, 
            SUM(i.paid_amount) as collected, 
            SUM(i.balance_due) as outstanding
            FROM invoices i 
            JOIN students s ON i.student_id = s.id 
            JOIN student_classes sc ON s.id = sc.student_id 
            JOIN classes c ON sc.class_id = c.id 
            WHERE i.status != 'Void' AND i.issued_date BETWEEN '$from_date' AND '$to_date'
            GROUP BY c.id ORDER BY c.class_name";
$res_col = $conn->query($sql_col);
$tot_inv = 0; $tot_col = 0; $tot_out = 0;
while($r = $res_col->fetch_assoc()) {
    $r['rate'] = $r['invoiced'] > 0 ? ($r['collected'] / $r['invoiced']) * 100 : 0;
    $tot_inv += $r['invoiced'];
    $tot_col += $r['collected'];
    $tot_out += $r['outstanding'];
    $collections[] = $r;
}
$tot_rate = $tot_inv > 0 ? ($tot_col / $tot_inv) * 100 : 0;

// 3. AR Aging Table
$aging = [
    'Semasa (0-30 hari)' => ['count'=>0, 'amount'=>0],
    '31-60 hari' => ['count'=>0, 'amount'=>0],
    '61-90 hari' => ['count'=>0, 'amount'=>0],
    '> 90 hari' => ['count'=>0, 'amount'=>0]
];
$sql_age = "SELECT balance_due, DATEDIFF(CURDATE(), due_date) as days_overdue FROM invoices WHERE status IN ('Sent', 'Partial', 'Overdue') AND balance_due > 0";
$res_age = $conn->query($sql_age);
$tot_age_c = 0; $tot_age_a = 0;
while($r = $res_age->fetch_assoc()) {
    $d = (int)$r['days_overdue'];
    $a = (float)$r['balance_due'];
    if($d <= 30) { $aging['Semasa (0-30 hari)']['count']++; $aging['Semasa (0-30 hari)']['amount']+=$a; }
    elseif($d <= 60) { $aging['31-60 hari']['count']++; $aging['31-60 hari']['amount']+=$a; }
    elseif($d <= 90) { $aging['61-90 hari']['count']++; $aging['61-90 hari']['amount']+=$a; }
    else { $aging['> 90 hari']['count']++; $aging['> 90 hari']['amount']+=$a; }
    $tot_age_c++; $tot_age_a+=$a;
}

// 4. P&L Summary
$income = $conn->query("SELECT SUM(amount_paid) as s FROM payments WHERE status='Verified' AND DATE(payment_date) BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['s'] ?? 0;

$expenses = [
    'Gaji & Elaun' => 0,
    'Bekalan & Bahan' => 0,
    'Penyelenggaraan' => 0,
    'Lain-lain' => 0
];
// Payroll (assuming payroll table has month mapping, approx by month)
$m_start = date('Y-m', strtotime($from_date));
$res_pay = $conn->query("SELECT SUM(net_salary) as s FROM payroll WHERE month = '$m_start'");
$expenses['Gaji & Elaun'] = (float)($res_pay->fetch_assoc()['s'] ?? 0);

$res_exp = $conn->query("SELECT category, SUM(amount) as s FROM expenses WHERE expense_date BETWEEN '$from_date' AND '$to_date' GROUP BY category");
while($r = $res_exp->fetch_assoc()) {
    $cat = $r['category'];
    if(in_array($cat, array_keys($expenses))) {
        $expenses[$cat] = (float)$r['s'];
    } else {
        $expenses['Lain-lain'] += (float)$r['s'];
    }
}
$tot_exp = array_sum($expenses);
$surplus = $income - $tot_exp;

// Export CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Kewangan_' . date('Ymd') . '.csv');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['LAPORAN KEWANGAN', "Dari: $from_date", "Hingga: $to_date"]);
    fputcsv($output, []);
    
    fputcsv($output, ['KADAR KUTIPAN KELAS']);
    fputcsv($output, ['Kelas', 'Jumlah Diinvois (RM)', 'Jumlah Dikutip (RM)', 'Tertunggak (RM)', 'Kadar Kutipan (%)']);
    foreach($collections as $c) {
        fputcsv($output, [$c['class_name'], $c['invoiced'], $c['collected'], $c['outstanding'], number_format($c['rate'],1).'%']);
    }
    fputcsv($output, ['JUMLAH', $tot_inv, $tot_col, $tot_out, number_format($tot_rate,1).'%']);
    fputcsv($output, []);
    
    fputcsv($output, ['RINGKASAN UNTUNG RUGI (P&L)']);
    fputcsv($output, ['PENDAPATAN', $income]);
    foreach($expenses as $k => $v) fputcsv($output, ["Perbelanjaan: $k", $v]);
    fputcsv($output, ['SURPLUS BERSIH', $surplus]);
    
    fclose($output);
    exit;
}

renderAdminHeader('Laporan Kewangan');
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="bg-white p-4 rounded-xl shadow-sm border border-[#c7c5d4]/20 mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
    <form method="GET" class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-500">Dari:</span>
            <input type="date" name="from_date" value="<?= $from_date ?>" class="rounded-lg border-gray-300 text-sm focus:border-[#333093] focus:ring-[#333093]/20">
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-500">Hingga:</span>
            <input type="date" name="to_date" value="<?= $to_date ?>" class="rounded-lg border-gray-300 text-sm focus:border-[#333093] focus:ring-[#333093]/20">
        </div>
        <button type="submit" class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors">Tapis</button>
    </form>
    
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center gap-2 shadow-sm">
            <span class="material-symbols-outlined text-[18px]">print</span> Cetak PDF
        </button>
        <a href="?export=csv&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center gap-2 shadow-sm">
            <span class="material-symbols-outlined text-[18px]">download</span> Eksport CSV
        </a>
    </div>
</div>

<!-- SECTION 1: CHARTS -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6">
        <h3 class="text-md font-bold text-gray-800 mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#333093]">bar_chart</span> Trend Pendapatan (6 Bulan)
        </h3>
        <div class="h-64">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6">
        <h3 class="text-md font-bold text-gray-800 mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#333093]">pie_chart</span> Pecahan Jenis Yuran
        </h3>
        <div class="h-64 flex justify-center">
            <canvas id="feeChart"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- SECTION 4: P&L SUMMARY -->
    <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden flex flex-col">
        <div class="p-4 border-b border-gray-100 bg-[#f7f9fb]/50">
            <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#333093]">account_balance</span> Untung & Rugi
            </h3>
        </div>
        <div class="p-6 flex-1 flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100">
                    <span class="text-gray-500 font-medium">Pendapatan Dikutip</span>
                    <span class="font-bold text-gray-800 text-lg">RM <?= number_format($income, 2) ?></span>
                </div>
                
                <div class="space-y-3 mb-6">
                    <p class="text-xs font-bold text-gray-400 uppercase">Perbelanjaan</p>
                    <?php foreach($expenses as $k => $v): ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600"><?= $k ?></span>
                        <span class="font-medium text-gray-800">RM <?= number_format($v, 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="p-4 rounded-xl <?= $surplus >= 0 ? 'bg-emerald-50 border border-emerald-100' : 'bg-red-50 border border-red-100' ?>">
                <p class="text-xs font-bold uppercase <?= $surplus >= 0 ? 'text-emerald-600' : 'text-red-600' ?> mb-1">Surplus Bersih</p>
                <p class="text-2xl font-bold <?= $surplus >= 0 ? 'text-emerald-700' : 'text-red-700' ?>">RM <?= number_format($surplus, 2) ?></p>
            </div>
        </div>
    </div>

    <!-- SECTION 3: AR AGING -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-[#f7f9fb]/50">
            <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#333093]">hourglass_bottom</span> Penuaan Akaun Belum Terima (AR Aging)
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-4 gap-4 mb-6">
                <?php 
                $colors = ['text-blue-600 bg-blue-50 border-blue-100', 'text-amber-600 bg-amber-50 border-amber-100', 'text-orange-600 bg-orange-50 border-orange-100', 'text-red-600 bg-red-50 border-red-100'];
                $i = 0;
                foreach($aging as $k => $v): 
                ?>
                <div class="p-4 rounded-xl border <?= $colors[$i] ?> text-center">
                    <p class="text-[11px] font-bold uppercase mb-1 opacity-80"><?= $k ?></p>
                    <p class="text-lg font-bold">RM <?= number_format($v['amount'], 0) ?></p>
                    <p class="text-xs opacity-80 mt-1"><?= $v['count'] ?> invois</p>
                </div>
                <?php $i++; endforeach; ?>
            </div>
            
            <div class="p-4 rounded-xl bg-gray-50 border border-gray-200 flex justify-between items-center">
                <div>
                    <p class="text-sm font-bold text-gray-600 uppercase">Jumlah Keseluruhan Tertunggak</p>
                    <p class="text-xs text-gray-500"><?= $tot_age_c ?> invois belum dijelaskan</p>
                </div>
                <p class="text-2xl font-bold text-red-600">RM <?= number_format($tot_age_a, 2) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 2: COLLECTION RATE -->
<div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-100 bg-[#f7f9fb]/50">
        <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#333093]">percent</span> Kadar Kutipan Mengikut Kelas
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-[#f7f9fb] text-xs uppercase text-gray-500 border-b">
                <tr>
                    <th class="px-6 py-3 font-medium">Kelas</th>
                    <th class="px-6 py-3 font-medium text-right">Jumlah Diinvois (RM)</th>
                    <th class="px-6 py-3 font-medium text-right">Jumlah Dikutip (RM)</th>
                    <th class="px-6 py-3 font-medium text-right">Tertunggak (RM)</th>
                    <th class="px-6 py-3 font-medium text-center w-32">Kadar Kutipan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($collections)): ?>
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Tiada data untuk tarikh yang dipilih.</td></tr>
                <?php endif; ?>
                <?php foreach($collections as $c): 
                    $color = $c['rate'] >= 90 ? 'text-emerald-600 bg-emerald-50 border-emerald-100' : ($c['rate'] >= 70 ? 'text-amber-600 bg-amber-50 border-amber-100' : 'text-red-600 bg-red-50 border-red-100');
                ?>
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 font-semibold text-gray-800"><?= htmlspecialchars($c['class_name']) ?></td>
                    <td class="px-6 py-4 text-right"><?= number_format($c['invoiced'], 2) ?></td>
                    <td class="px-6 py-4 text-right font-medium text-emerald-600"><?= number_format($c['collected'], 2) ?></td>
                    <td class="px-6 py-4 text-right font-medium text-red-500"><?= number_format($c['outstanding'], 2) ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex px-2 py-1 rounded text-xs font-bold border <?= $color ?>"><?= number_format($c['rate'], 1) ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <!-- Totals Row -->
                <?php if (!empty($collections)): ?>
                <tr class="bg-gray-50/80 font-bold border-t-2 border-gray-200">
                    <td class="px-6 py-4 text-gray-800 uppercase text-xs">JUMLAH BESAR</td>
                    <td class="px-6 py-4 text-right text-gray-800"><?= number_format($tot_inv, 2) ?></td>
                    <td class="px-6 py-4 text-right text-emerald-700"><?= number_format($tot_col, 2) ?></td>
                    <td class="px-6 py-4 text-right text-red-600"><?= number_format($tot_out, 2) ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php $t_color = $tot_rate >= 90 ? 'text-emerald-700' : ($tot_rate >= 70 ? 'text-amber-700' : 'text-red-700'); ?>
                        <span class="<?= $t_color ?> text-lg"><?= number_format($tot_rate, 1) ?>%</span>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Pendapatan Dikutip (RM)',
                data: <?= json_encode($monthly_revenue) ?>,
                backgroundColor: '#333093',
                borderRadius: 4,
                borderWidth: 0,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) { return 'RM ' + context.raw.toLocaleString(); }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6' },
                    ticks: { callback: function(value) { return 'RM ' + value; } }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Fee Type Chart
    new Chart(document.getElementById('feeChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($fee_types) ?>,
            datasets: [{
                data: <?= json_encode($fee_amounts) ?>,
                backgroundColor: ['#333093', '#5452b5', '#7371d1', '#918fe6', '#b0aef8'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'right', labels: { font: { family: "'Inter', sans-serif", size: 11 }, boxWidth: 12 } },
                tooltip: {
                    callbacks: {
                        label: function(context) { return context.label + ': RM ' + context.raw.toLocaleString(); }
                    }
                }
            }
        }
    });
});
</script>

<?php renderAdminFooter(); ?>
