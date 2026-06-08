<?php
// parent_payment_history.php
// Sejarah Pembayaran - Paparan untuk Ibu Bapa
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];

// Dapatkan parent_id
$sql_parent = "SELECT id, full_name FROM parents WHERE user_id = $user_id LIMIT 1";
$res_parent = $conn->query($sql_parent);
if (!$res_parent || $res_parent->num_rows == 0) {
    echo "<script>alert('Profil ibu bapa tidak dijumpai.'); window.location.href='home.php';</script>";
    exit();
}
$parent = $res_parent->fetch_assoc();
$parent_id = (int)$parent['id'];
$parent_name = htmlspecialchars($parent['full_name'] ?: $username);

// Ambil sejarah pembayaran parent ini sahaja (termasuk status, ref, verified_at, rejection_reason)
$sql_payments = "SELECT p.*, i.invoice_number, i.amount AS invoice_amount, i.type AS invoice_type,
                 s.full_name AS student_name
                 FROM payments p 
                 INNER JOIN invoices i ON p.invoice_id = i.id 
                 INNER JOIN students s ON i.student_id = s.id
                 WHERE p.parent_id = $parent_id 
                 ORDER BY p.payment_date DESC";
$payments = $conn->query($sql_payments);

// Statistik
$total_payments = 0;
$verified_count = 0;
$pending_count = 0;
$payment_list = array();
if ($payments) {
    while ($row = $payments->fetch_assoc()) {
        $payment_list[] = $row;
        if ($row['status'] == 'Verified') {
            $total_payments += $row['amount_paid'];
            $verified_count++;
        }
        if ($row['status'] == 'Pending') {
            $pending_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sejarah Pembayaran — Portal Ibu Bapa</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { min-height:100dvh; font-family:'Inter',sans-serif; }
        .nav-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px;
                    color:rgba(255,255,255,.55); font-size:12px; letter-spacing:.03em; font-weight:500;
                    transition:background .15s, color .15s; text-decoration:none; cursor:pointer; }
        .nav-link:hover { background:rgba(255,255,255,.08); color:rgba(255,255,255,.9); }
        .nav-link.active { background:rgba(255,255,255,.13); color:#fff; border-left:3px solid #84b6f4; padding-left:11px; }
        .accordion-btn.open { background:rgba(255,255,255,.09); color:#fff; }
        .accordion-btn.open .chevron { transform:rotate(90deg); }
        .sub-link { padding:8px 12px; font-size:11px; border-radius:6px;
                    border-left:2px solid rgba(255,255,255,.08); margin-left:4px; }
        .sub-link:hover { border-left-color:rgba(132,182,244,.5); background:rgba(255,255,255,.06); }
    </style>
</head>
<body class="bg-[#f7f9fb] text-[#191c1e] overflow-x-hidden">
<?php include 'sidebar_parent.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside id="sidebar"
    class="fixed top-0 left-0 h-screen w-[260px] bg-[#1a1c2e] flex flex-col py-5 z-50
           transition-transform duration-300 -translate-x-full md:translate-x-0">

    <div class="px-5 mb-5 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-[#3a78c9] flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-[18px]">family_restroom</span>
            </div>
            <span class="text-white font-bold text-[15px]">Panel Ibu Bapa</span>
        </div>
        <button class="md:hidden text-white/50 hover:text-white" onclick="toggleSidebar()">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 space-y-0.5 pb-4">
        <a href="parent_home.php" class="nav-link">
            <span class="material-symbols-outlined text-[20px]">dashboard</span><span>Dashboard</span>
        </a>

        <!-- ── Anak Saya ── -->
        <button onclick="toggleAcc('acc-anak')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">child_care</span>
                <span>Anak Saya</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-anak">chevron_right</span>
        </button>
        <div id="acc-anak" class="hidden pl-3 space-y-0.5">
            <a href="profil_anak.php"      class="nav-link sub-link">👧 <span>Profil Anak Saya</span></a>
            <a href="sejarah_kehadiran.php" class="nav-link sub-link">📅 <span>Sejarah Kehadiran</span></a>
            <a href="laporan_harian.php"   class="nav-link sub-link">📝 <span>Laporan Aktiviti Harian</span></a>
        </div>

        <!-- ── Akademik & Perkembangan ── -->
        <button onclick="toggleAcc('acc-akademik')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">school</span>
                <span>Akademik &amp; Perkembangan</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-akademik">chevron_right</span>
        </button>
        <div id="acc-akademik" class="hidden pl-3 space-y-0.5">
            <a href="milestone.php"        class="nav-link sub-link">📈 <span>Pencapaian &amp; Perkembangan</span></a>
            <a href="report_card_anak.php" class="nav-link sub-link">🎓 <span>Kad Laporan &amp; Ulasan</span></a>
        </div>

        <!-- ── Komunikasi ── -->
        <button onclick="toggleAcc('acc-komunikasi')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">campaign</span>
                <span>Komunikasi</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-komunikasi">chevron_right</span>
        </button>
        <div id="acc-komunikasi" class="hidden pl-3 space-y-0.5">
            <a href="parent_inbox.php"          class="nav-link sub-link">💬 <span>Peti Masuk</span></a>
            <a href="parent_calendar.php"       class="nav-link sub-link">📆 <span>Kalendar Sekolah &amp; RSVP</span></a>
            <a href="parent_announcements.php"  class="nav-link sub-link">📢 <span>Pengumuman</span></a>
        </div>

        <!-- ── Kewangan ── -->
        <button onclick="toggleAcc('acc-kewangan')"
                class="accordion-btn nav-link w-full text-left justify-between open" aria-expanded="true">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">payments</span>
                <span>Kewangan</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200 rotate-90"
                  id="chevron-acc-kewangan">chevron_right</span>
        </button>
        <div id="acc-kewangan" class="pl-3 space-y-0.5">
            <a href="parent_payments.php"        class="nav-link sub-link">💳 <span>Pembayaran &amp; Invois</span></a>
            <a href="parent_payment_history.php" class="nav-link sub-link active">🧾 <span>Sejarah Pembayaran</span></a>
            <a href="daftar_anak.php"            class="nav-link sub-link">📋 <span>Pendaftaran Adik-Beradik</span></a>
        </div>

        <!-- ── Keselamatan & Pengangkutan ── -->
        <button onclick="toggleAcc('acc-keselamatan')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">security</span>
                <span>Keselamatan &amp; Pengangkutan</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-keselamatan">chevron_right</span>
        </button>
        <div id="acc-keselamatan" class="hidden pl-3 space-y-0.5">
            <a href="parent_bus_tracking.php" class="nav-link sub-link">🚌 <span>Jejak Bas Langsung</span></a>
            <a href="parent_checkin_log.php"  class="nav-link sub-link">🔐 <span>Log Daftar Masuk/Keluar</span></a>
            <a href="parent_guardians.php"    class="nav-link sub-link">👤 <span>Penjaga Sah</span></a>
        </div>
    </nav>

    <div class="px-3 pt-3 border-t border-white/10">
        <a href="logout.php" class="nav-link text-red-400 hover:text-red-300 hover:bg-red-500/10">
            <span class="material-symbols-outlined text-[20px]">logout</span><span>Log Keluar Sistem</span>
        </a>
    </div>
</aside>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

<!-- ═══════════ MAIN CONTENT ═══════════ -->
<main class="md:ml-[260px] min-h-screen">
    
    <!-- Top Bar -->
    <header class="fixed top-0 right-0 w-full md:w-[calc(100%-260px)] bg-white border-b border-[#e0e3e5]
                   flex items-center justify-between px-6 h-[68px] z-40 shadow-sm">
        <div class="flex items-center gap-4">
            <button class="md:hidden p-2 rounded-lg hover:bg-gray-100" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-[#464552]">menu</span>
            </button>
            <div>
                <h1 class="text-[18px] font-semibold text-[#191c1e]">🧾 Sejarah Pembayaran</h1>
                <p class="text-[11px] text-[#777583]">Lihat rekod transaksi lampau dan muat turun resit rasmi</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e]"><?php echo $parent_name; ?></span>
                <span class="text-[11px] font-bold text-[#3a78c9] bg-[#84b6f4]/20 px-2 py-0.5 rounded-full">Ibu Bapa / Penjaga</span>
            </div>
            <div class="w-10 h-10 rounded-full bg-[#3a78c9] flex items-center justify-center text-white font-bold select-none">
                <?php echo strtoupper(substr($username,0,1)); ?>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="pt-[90px] px-6 pb-8 max-w-[1440px] mx-auto">
        
        <!-- Stats Row -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-6">
            <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">payments</span>
                </div>
                <div>
                    <p class="text-[12px] text-gray-500 font-medium">Jumlah Lunas Diterima</p>
                    <h3 class="text-[22px] font-bold text-green-600">RM <?php echo number_format($total_payments, 2); ?></h3>
                </div>
            </div>
            
            <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">check_circle</span>
                </div>
                <div>
                    <p class="text-[12px] text-gray-500 font-medium">Resit Sah Dijana</p>
                    <h3 class="text-[22px] font-bold text-blue-600"><?php echo $verified_count; ?> Transaksi</h3>
                </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">hourglass_empty</span>
                </div>
                <div>
                    <p class="text-[12px] text-gray-500 font-medium">Dalam Semakan Admin</p>
                    <h3 class="text-[22px] font-bold text-amber-600"><?php echo $pending_count; ?> Bil</h3>
                </div>
            </div>
        </div>

        <!-- Transactions Container -->
        <div class="bg-white rounded-xl border border-[#c7c5d4]/20 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-gray-500">history</span>
                    Sejarah Transaksi &amp; Resit
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100/70 text-gray-600 text-[11px] font-semibold uppercase tracking-wider">
                            <th class="p-4 border-b border-gray-100">Tarikh Transaksi</th>
                            <th class="p-4 border-b border-gray-100">Rujukan Bil</th>
                            <th class="p-4 border-b border-gray-100">Nama Pelajar</th>
                            <th class="p-4 border-b border-gray-100">Kaedah Bayaran</th>
                            <th class="p-4 border-b border-gray-100">Ref Transaksi</th>
                            <th class="p-4 border-b border-gray-100">Jumlah Bayaran</th>
                            <th class="p-4 border-b border-gray-100">Status</th>
                            <th class="p-4 border-b border-gray-100 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[13px]">
                        <?php if (count($payment_list) > 0): ?>
                            <?php foreach ($payment_list as $p): ?>
                                <?php 
                                    $statusClass = '';
                                    if ($p['status'] === 'Verified') {
                                        $statusClass = 'bg-green-100 text-green-700';
                                    } elseif ($p['status'] === 'Rejected') {
                                        $statusClass = 'bg-red-100 text-red-700';
                                    } else {
                                        $statusClass = 'bg-amber-100 text-amber-700';
                                    }
                                ?>
                                <tr class="hover:bg-gray-50/40 transition">
                                    <td class="p-4 text-gray-500">
                                        <?php echo date('d/m/Y H:i', strtotime($p['payment_date'])); ?>
                                    </td>
                                    <td class="p-4 font-bold text-gray-800">
                                        <?php echo htmlspecialchars($p['invoice_number'] ?? 'INV-' . $p['invoice_id']); ?>
                                    </td>
                                    <td class="p-4 font-medium"><?php echo htmlspecialchars($p['student_name']); ?></td>
                                    <td class="p-4 text-gray-600"><?php echo htmlspecialchars($p['payment_method']); ?></td>
                                    <td class="p-4 text-gray-600 font-mono text-xs"><?php echo htmlspecialchars($p['transaction_ref'] ?: '-'); ?></td>
                                    <td class="p-4 font-bold text-gray-800">RM <?php echo number_format($p['amount_paid'], 2); ?></td>
                                    <td class="p-4">
                                        <div class="relative group inline-block">
                                            <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold cursor-default <?php echo $statusClass; ?>">
                                                <?php echo $p['status']; ?>
                                            </span>
                                            
                                            <!-- Show rejection reason tooltip if rejected -->
                                            <?php if ($p['status'] === 'Rejected' && !empty($p['rejection_reason'])): ?>
                                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-48 bg-red-900 text-white text-[11px] rounded p-2 opacity-0 group-hover:opacity-100 pointer-events-none transition duration-200 z-10 shadow-lg text-center leading-normal">
                                                    <strong>Sebab Tolak:</strong><br>
                                                    <?php echo htmlspecialchars($p['rejection_reason']); ?>
                                                    <div class="w-2.5 h-2.5 bg-red-900 transform rotate-45 absolute top-full left-1/2 -translate-x-1/2 -mt-1.5"></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <?php if ($p['status'] === 'Verified'): ?>
                                            <a href="view_receipt.php?id=<?php echo $p['id']; ?>" target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg text-xs transition shadow-sm">
                                                <span class="material-symbols-outlined text-[14px]">receipt</span> Resit Cetak
                                            </a>
                                        <?php elseif ($p['status'] === 'Rejected'): ?>
                                            <span class="text-xs text-red-500 font-medium flex items-center justify-center gap-1">
                                                <span class="material-symbols-outlined text-[15px]">info</span> Sila Re-upload
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400 font-medium">Menunggu Semakan</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-400">
                                    <span class="material-symbols-outlined text-[48px] opacity-35 block mb-2">history</span>
                                    Tiada rekod pembayaran dijumpai.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.toggle('hidden');
}
window.addEventListener('resize', () => {
    const s = document.getElementById('sidebar'), o = document.getElementById('sidebar-overlay');
    if (window.innerWidth >= 768) { s.classList.remove('-translate-x-full'); o.classList.add('hidden'); }
    else s.classList.add('-translate-x-full');
});
function toggleAcc(id) {
    const panel = document.getElementById(id);
    const btn   = panel.previousElementSibling;
    panel.classList.toggle('hidden');
    btn && btn.classList.toggle('open');
}
</script>

</main>
</body>
</html>
