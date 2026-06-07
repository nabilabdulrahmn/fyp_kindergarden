<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';
require_once 'includes/admin_layout.php';

sahkan_peranan('admin');

// 1. Funnel Data
$funnel_data = [
    'Permohonan' => 0,
    'Dalam Semakan' => 0,
    'Dokumen Disahkan' => 0,
    'Tawaran Dihantar' => 0,
    'Didaftarkan' => 0,
    'Aktif' => 0
];

$res_f1 = $conn->query("SELECT COUNT(*) as c FROM applications");
$funnel_data['Permohonan'] = $res_f1->fetch_assoc()['c'];

$res_f2 = $conn->query("SELECT COUNT(*) as c FROM applications WHERE status IN ('Under Review', 'Pending')");
$funnel_data['Dalam Semakan'] = $res_f2->fetch_assoc()['c'];

$res_f3 = $conn->query("SELECT COUNT(*) as c FROM applications WHERE documents_verified=1");
$funnel_data['Dokumen Disahkan'] = $res_f3->fetch_assoc()['c'];

$res_f4 = $conn->query("SELECT COUNT(*) as c FROM applications WHERE status='Offer Sent'");
$funnel_data['Tawaran Dihantar'] = $res_f4->fetch_assoc()['c'];

$res_f5 = $conn->query("SELECT COUNT(*) as c FROM applications WHERE status='Enrolled'");
$funnel_data['Didaftarkan'] = $res_f5->fetch_assoc()['c'];

$res_f6 = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Active'");
$funnel_data['Aktif'] = $res_f6->fetch_assoc()['c'];

// 2. Class Capacity
$classes = [];
$res_cap = $conn->query("
    SELECT c.id, c.class_name, c.module, c.capacity, 
    (SELECT COUNT(*) FROM student_classes sc JOIN students s ON sc.student_id=s.id WHERE sc.class_id=c.id AND s.status='Active') as enrolled,
    (SELECT COUNT(*) FROM applications a WHERE a.module=c.module AND a.status='Waitlisted') as waitlist
    FROM classes c
    ORDER BY c.module, c.class_name
");
while($r = $res_cap->fetch_assoc()) {
    $classes[] = $r;
}

// 3. Upcoming Deadlines
$deadlines = [];
$res_dead = $conn->query("
    SELECT a.id, a.child_name, p.full_name as parent_name, a.enrollment_offer_expiry,
    DATEDIFF(a.enrollment_offer_expiry, CURDATE()) as days_left
    FROM applications a 
    JOIN parents p ON a.parent_id = p.id 
    WHERE a.status='Offer Sent' AND a.enrollment_offer_expiry IS NOT NULL
    ORDER BY a.enrollment_offer_expiry ASC
    LIMIT 10
");
while($r = $res_dead->fetch_assoc()) {
    $deadlines[] = $r;
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Pendaftaran_' . date('Ymd') . '.csv');
    $output = fopen('php://output', 'w');
    
    // Funnel
    fputcsv($output, ['CORONG PENDAFTARAN']);
    fputcsv($output, ['Peringkat', 'Jumlah']);
    foreach($funnel_data as $k => $v) fputcsv($output, [$k, $v]);
    fputcsv($output, []);
    
    // Classes
    fputcsv($output, ['KAPASITI KELAS']);
    fputcsv($output, ['Kelas', 'Modul', 'Kapasiti', 'Didaftarkan', 'Senarai Tunggu', 'Pengisian (%)']);
    foreach($classes as $c) {
        $pct = $c['capacity'] > 0 ? number_format(($c['enrolled']/$c['capacity'])*100,1) : 0;
        fputcsv($output, [$c['class_name'], $c['module'], $c['capacity'], $c['enrolled'], $c['waitlist'], $pct.'%']);
    }
    fclose($output);
    exit;
}

renderAdminHeader('Penjejak Pendaftaran (Enrollment Tracker)');
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex justify-end mb-6 gap-2">
    <button onclick="window.print()" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[18px]">print</span> Cetak PDF
    </button>
    <a href="?export=csv" class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[18px]">download</span> Eksport CSV
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- SECTION 1: FUNNEL -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6">
        <h3 class="text-md font-bold text-gray-800 mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#333093]">filter_alt</span> Corong Pendaftaran
        </h3>
        <div class="h-64 relative">
            <canvas id="funnelChart"></canvas>
        </div>
    </div>

    <!-- SECTION 3: UPCOMING DEADLINES -->
    <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6">
        <h3 class="text-md font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#333093]">event_busy</span> Tarikh Akhir Tawaran
        </h3>
        <div class="space-y-3">
            <?php if (empty($deadlines)): ?>
                <div class="text-center py-8 text-gray-500 text-sm">Tiada tarikh akhir terdekat.</div>
            <?php else: ?>
                <?php foreach($deadlines as $d): 
                    $days = $d['days_left'];
                    if($days < 0) $badge = "<span class='bg-red-100 text-red-700 text-[10px] px-2 py-0.5 rounded font-bold'>Tamat Tempoh</span>";
                    elseif($days <= 1) $badge = "<span class='bg-red-100 text-red-700 text-[10px] px-2 py-0.5 rounded font-bold'>Hari Ini/Esok</span>";
                    elseif($days <= 3) $badge = "<span class='bg-amber-100 text-amber-700 text-[10px] px-2 py-0.5 rounded font-bold'>$days hari lagi</span>";
                    else $badge = "<span class='bg-emerald-100 text-emerald-700 text-[10px] px-2 py-0.5 rounded font-bold'>$days hari lagi</span>";
                ?>
                <div class="p-3 border border-gray-100 rounded-lg hover:bg-gray-50 transition-colors">
                    <div class="flex justify-between items-start mb-1">
                        <h4 class="font-semibold text-gray-800 text-sm truncate w-32" title="<?= htmlspecialchars($d['child_name']) ?>"><?= htmlspecialchars($d['child_name']) ?></h4>
                        <?= $badge ?>
                    </div>
                    <div class="text-[11px] text-gray-500 flex justify-between">
                        <span>Ibu/Bapa: <?= htmlspecialchars($d['parent_name']) ?></span>
                        <span class="font-medium"><?= date('d/m/Y', strtotime($d['enrollment_offer_expiry'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SECTION 2: CLASS CAPACITY -->
<div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-100 bg-[#f7f9fb]/50">
        <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#333093]">meeting_room</span> Kapasiti Kelas
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-[#f7f9fb] text-xs uppercase text-gray-500 border-b">
                <tr>
                    <th class="px-6 py-3 font-medium">Kelas</th>
                    <th class="px-6 py-3 font-medium">Modul</th>
                    <th class="px-6 py-3 font-medium text-center">Kapasiti</th>
                    <th class="px-6 py-3 font-medium text-center">Didaftarkan</th>
                    <th class="px-6 py-3 font-medium text-center">Senarai Tunggu</th>
                    <th class="px-6 py-3 font-medium w-48">Pengisian (%)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach($classes as $c): 
                    $pct = $c['capacity'] > 0 ? ($c['enrolled'] / $c['capacity']) * 100 : 0;
                    $bg = 'bg-emerald-500'; $text = 'text-emerald-700'; $bg_soft = 'bg-emerald-100';
                    if ($pct >= 100) { $bg = 'bg-red-500'; $text = 'text-red-700'; $bg_soft = 'bg-red-100'; }
                    elseif ($pct >= 80) { $bg = 'bg-amber-500'; $text = 'text-amber-700'; $bg_soft = 'bg-amber-100'; }
                ?>
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($c['class_name']) ?></td>
                    <td class="px-6 py-4"><span class="inline-flex px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 border border-gray-200"><?= htmlspecialchars($c['module']) ?></span></td>
                    <td class="px-6 py-4 text-center"><?= $c['capacity'] ?></td>
                    <td class="px-6 py-4 text-center font-bold text-gray-800"><?= $c['enrolled'] ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php if($c['waitlist'] > 0): ?>
                            <span class="inline-flex px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-xs font-semibold border border-amber-200"><?= $c['waitlist'] ?></span>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="<?= $bg ?> h-2 rounded-full" style="width: <?= min(100, $pct) ?>%"></div>
                            </div>
                            <span class="text-xs font-semibold <?= $text ?> bg-white border <?= str_replace('bg-','border-',$bg_soft) ?> px-1.5 py-0.5 rounded shadow-sm"><?= number_format($pct, 0) ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('funnelChart').getContext('2d');
    
    const labels = <?= json_encode(array_keys($funnel_data)) ?>;
    const data = <?= json_encode(array_values($funnel_data)) ?>;
    
    // Create a horizontal bar chart that looks like a funnel
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Jumlah',
                data: data,
                backgroundColor: [
                    '#e0e7ff', // Permohonan
                    '#c7d2fe', // Dalam semakan
                    '#a5b4fc', // Dokumen disahkan
                    '#818cf8', // Tawaran
                    '#6366f1', // Didaftarkan
                    '#4338ca'  // Aktif
                ],
                borderRadius: 4,
                borderWidth: 0,
                barPercentage: 0.8,
                categoryPercentage: 0.9
            }]
        },
        options: {
            indexAxis: 'y', // horizontal
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.raw + ' pemohon';
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { display: false }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        font: { family: "'Inter', sans-serif", weight: '500' },
                        color: '#4b5563'
                    }
                }
            }
        }
    });
});
</script>

<?php renderAdminFooter(); ?>
