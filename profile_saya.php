<?php
// profile_saya.php — Guru Profile & Salary Statement
require_once 'auth_guard.php';
sahkan_peranan('teacher');
require_once 'db.php';

$username = $_SESSION['username'];
$uid = (int)$_SESSION['user_id'];

// ── Teacher Info from DB ─────────────────────────────────────
$r_teacher = mysqli_query($conn, "SELECT * FROM teachers WHERE user_id = $uid LIMIT 1");
$teacher = mysqli_fetch_assoc($r_teacher);
$teacher_id = $teacher ? (int)$teacher['id'] : 0;

// --- PROSES PERMOHONAN MODUL (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['module_action'])) {
    $mod_action = $_POST['module_action'];
    $target_mod = $_POST['module_name'];
    
    if (in_array($target_mod, ['Taska', 'Tadika', 'KAFA Care'])) {
        if ($teacher_id > 0) {
            if ($mod_action == 'register') {
                $check_tm = $conn->query("SELECT id, status FROM teacher_modules WHERE teacher_id = $teacher_id AND module = '$target_mod'");
                if ($check_tm->num_rows > 0) {
                    $row_tm = $check_tm->fetch_assoc();
                    if ($row_tm['status'] == 'Rejected' || $row_tm['status'] == 'Pending_Drop') {
                        $conn->query("UPDATE teacher_modules SET status = 'Pending_Register' WHERE teacher_id = $teacher_id AND module = '$target_mod'");
                    }
                } else {
                    $conn->query("INSERT INTO teacher_modules (teacher_id, module, status) VALUES ($teacher_id, '$target_mod', 'Pending_Register')");
                }
                echo "<script>alert('Permohonan pendaftaran modul $target_mod berjaya dihantar dan menanti kelulusan pentadbir.'); window.location.href='profile_saya.php';</script>";
                exit();
            } elseif ($mod_action == 'drop') {
                $check_tm = $conn->query("SELECT id, status FROM teacher_modules WHERE teacher_id = $teacher_id AND module = '$target_mod'");
                if ($check_tm->num_rows > 0) {
                    $row_tm = $check_tm->fetch_assoc();
                    if ($row_tm['status'] == 'Approved') {
                        $conn->query("UPDATE teacher_modules SET status = 'Pending_Drop' WHERE teacher_id = $teacher_id AND module = '$target_mod'");
                        echo "<script>alert('Permohonan untuk mengugurkan modul $target_mod berjaya dihantar.'); window.location.href='profile_saya.php';</script>";
                        exit();
                    }
                }
            }
        }
    }
}

// ── Fetch Module Requests Status ──────────────────────────────
$module_states = [
    'Taska' => 'Not_Registered',
    'Tadika' => 'Not_Registered',
    'KAFA Care' => 'Not_Registered'
];
if ($teacher_id) {
    $r_mods = mysqli_query($conn, "SELECT module, status FROM teacher_modules WHERE teacher_id = $teacher_id");
    if ($r_mods) {
        while ($row = mysqli_fetch_assoc($r_mods)) {
            $module_states[$row['module']] = $row['status'];
        }
    }
}

// ── Staff Registry Info ──────────────────────────────────────
$r_staff = mysqli_query($conn, "SELECT * FROM staff WHERE user_id = $uid LIMIT 1");
$staff = mysqli_fetch_assoc($r_staff);
$staff_id = $staff ? (int)$staff['id'] : 0;

// ── Assigned Classes & Modules (only approved modules) ────────
$classes = [];
if ($teacher_id) {
    $r_classes = mysqli_query($conn, "SELECT c.* FROM classes c 
        INNER JOIN teacher_modules tm ON tm.teacher_id = c.teacher_id AND tm.module = c.module 
        WHERE c.teacher_id = $teacher_id AND tm.status = 'Approved'");
    if ($r_classes) {
        while ($row = mysqli_fetch_assoc($r_classes)) {
            $classes[] = $row;
        }
    }
}

// ── Salary Statement History ──────────────────────────────────
$payroll_history = [];
if ($staff_id) {
    $r_payroll = mysqli_query($conn, "SELECT * FROM payroll WHERE staff_id = $staff_id ORDER BY month DESC");
    if ($r_payroll) {
        while ($row = mysqli_fetch_assoc($r_payroll)) {
            $payroll_history[] = $row;
        }
    }
}

// Format Date helper
function format_date($dt) {
    return date('d/m/Y', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Profil Saya — SMS</title>
    <meta name="description" content="Profil Guru dan Penyata Gaji"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { min-height:max(884px,100dvh); font-family:'Inter',sans-serif; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:#1e2124; }
        ::-webkit-scrollbar-thumb { background:#444; border-radius:10px; }
        

        
        .bento-card { transition:transform .2s ease, box-shadow .2s ease; }
        .bento-card:hover { transform:translateY(-2px); box-shadow:0 10px 20px -4px rgba(0,0,0,.06); }

        /* Print styles for payslip modal */
        @media print {
            body * {
                visibility: hidden;
            }
            #payslip-modal-content, #payslip-modal-content * {
                visibility: visible;
            }
            #payslip-modal-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-[#f7f9fb] text-[#191c1e] overflow-x-hidden">

<?php include 'sidebar_teacher.php'; ?>

<!-- ═══════════ MAIN ═══════════ -->
<main class="md:ml-[260px] min-h-screen main-content-shifted">

    <!-- Top Bar -->
    <header class="fixed top-0 right-0 w-full md:w-[calc(100%-260px)] bg-white border-b border-[#e0e3e5]
                   flex items-center justify-between px-6 h-[68px] z-40 shadow-sm">
        <div class="flex items-center gap-4">
            <button class="md:hidden p-2 rounded-lg hover:bg-gray-100" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-[#464552]">menu</span>
            </button>
            <div>
                <h1 class="text-[20px] font-semibold text-[#191c1e]">Profil Saya</h1>
                <p class="text-[12px] text-[#777583]">Maklumat Peribadi & Penyata Gaji</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e]"><?php echo htmlspecialchars($teacher['full_name'] ?? $username); ?></span>
                <span class="text-[11px] font-bold text-[#c97a2a] bg-[#ffb347]/20 px-2 py-0.5 rounded-full mt-0.5">Guru Kelas</span>
            </div>
            <div class="relative">
                <div class="w-11 h-11 rounded-full bg-[#c97a2a] border-2 border-[#ffb347]/50 shadow
                            flex items-center justify-center text-white text-[18px] font-bold select-none">
                    <?php echo strtoupper(substr($username,0,1)); ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="pt-[90px] px-6 pb-8 max-w-[1440px] mx-auto space-y-6">

        <div class="grid grid-cols-12 gap-6">

            <!-- ── LEFT COLUMN: Personal Info & Modules ── -->
            <div class="col-span-12 lg:col-span-5 space-y-6">

                <!-- Profile Card -->
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6 relative overflow-hidden bento-card">
                    <div class="absolute right-0 top-0 w-32 h-32 bg-[#ffb347]/5 rounded-full blur-2xl pointer-events-none"></div>
                    
                    <div class="flex items-center gap-5 pb-6 border-b border-gray-100">
                        <div class="w-16 h-16 rounded-2xl bg-[#ffb347]/10 flex items-center justify-center text-[#c97a2a]">
                            <span class="material-symbols-outlined text-[36px]">badge</span>
                        </div>
                        <div>
                            <h2 class="text-[18px] font-bold text-gray-800"><?php echo htmlspecialchars($teacher['full_name'] ?? '-'); ?></h2>
                            <p class="text-sm text-gray-500 font-medium"><?php echo htmlspecialchars($staff['position'] ?? 'Guru'); ?></p>
                            <span class="inline-block mt-2 text-[11px] font-bold bg-[#e8f5e9] text-[#2e7d32] px-2 py-0.5 rounded-full">
                                Staf <?php echo htmlspecialchars($staff['status'] ?? 'Active'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4 text-[13px]">
                        <div class="flex justify-between items-center py-1">
                            <span class="text-gray-400 font-medium">No. Kad Pengenalan (IC)</span>
                            <span class="text-gray-700 font-semibold"><?php echo htmlspecialchars($teacher['ic_number'] ?? '-'); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-gray-400 font-medium">No. Telefon</span>
                            <span class="text-gray-700 font-semibold"><?php echo htmlspecialchars($teacher['phone_number'] ?? '-'); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-gray-400 font-medium">Emel</span>
                            <span class="text-gray-700 font-semibold"><?php echo htmlspecialchars($staff['email'] ?? '-'); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-gray-400 font-medium">Pengkhususan</span>
                            <span class="text-gray-700 font-semibold"><?php echo htmlspecialchars($teacher['specialization'] ?? '-'); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-gray-400 font-medium">Jabatan / Bahagian</span>
                            <span class="text-gray-700 font-semibold"><?php echo htmlspecialchars($staff['department'] ?? '-'); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-gray-400 font-medium">Jenis Pekerjaan</span>
                            <span class="text-gray-700 font-semibold"><?php echo htmlspecialchars($staff['employment_type'] ?? '-'); ?></span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-gray-400 font-medium">Tarikh Mula Kerja</span>
                            <span class="text-gray-700 font-semibold"><?php echo $staff['hire_date'] ? format_date($staff['hire_date']) : '-'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Teaching Modules Card -->
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6 bento-card">
                    <h3 class="text-[16px] font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#c97a2a] text-[20px]">school</span>
                        Modul & Kelas yang Diajar
                    </h3>

                    <?php if (!empty($classes)): ?>
                        <div class="space-y-3">
                            <?php foreach ($classes as $cl): 
                                $moduleTag = 'tag-' . strtolower(str_replace(' ', '', $cl['module']));
                                $bgColor = 'bg-[#ffb347]/10 text-[#7c4d00]';
                                if ($cl['module'] == 'Taska') $bgColor = 'bg-red-50 text-red-600';
                                if ($cl['module'] == 'Tadika') $bgColor = 'bg-green-50 text-green-600';
                                if ($cl['module'] == 'KAFA Care') $bgColor = 'bg-blue-50 text-blue-600';
                            ?>
                                <div class="flex items-center justify-between p-3.5 bg-[#f7f9fb] rounded-xl border border-gray-100">
                                    <div>
                                        <h4 class="text-[14px] font-bold text-gray-800"><?php echo htmlspecialchars($cl['class_name']); ?></h4>
                                        <p class="text-[11px] text-gray-400 mt-0.5">Sesi Pagi/Petang</p>
                                    </div>
                                    <span class="text-[11px] font-bold px-3 py-1 rounded-full <?php echo $bgColor; ?>">
                                        <?php echo htmlspecialchars($cl['module']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-400">
                            <span class="material-symbols-outlined text-[36px] opacity-40 block mb-1">school</span>
                            <p class="text-xs">Tiada kelas atau modul yang didaftarkan buat masa ini.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Manage Modules Card -->
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6 bento-card">
                    <h3 class="text-[16px] font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#c97a2a] text-[20px]">settings_accessibility</span>
                        Permohonan & Kelulusan Modul Mengajar
                    </h3>
                    <div class="space-y-4">
                        <?php 
                        $modules_info = [
                            'Taska' => ['name' => 'Taska (Kanak-kanak 0-4 Tahun)', 'color' => 'bg-red-50 text-red-600 border-red-200'],
                            'Tadika' => ['name' => 'Tadika (Kanak-kanak 4-6 Tahun)', 'color' => 'bg-green-50 text-green-600 border-green-200'],
                            'KAFA Care' => ['name' => 'KAFA Care (Transit & Agama)', 'color' => 'bg-blue-50 text-blue-600 border-blue-200']
                        ];
                        foreach ($modules_info as $m_code => $m_data): 
                            $status = $module_states[$m_code];
                        ?>
                            <div class="p-3.5 bg-[#f7f9fb] rounded-xl border border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
                                <div>
                                    <h4 class="text-[13px] font-bold text-gray-800"><?php echo $m_data['name']; ?></h4>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="text-[11px] text-gray-400">Status:</span>
                                        <?php if ($status == 'Approved'): ?>
                                            <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-green-100 text-green-800 border border-green-200">
                                                Aktif (Diluluskan)
                                            </span>
                                        <?php elseif ($status == 'Pending_Register'): ?>
                                            <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200 animate-pulse">
                                                Menunggu Kelulusan Daftar
                                            </span>
                                        <?php elseif ($status == 'Pending_Drop'): ?>
                                            <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-800 border border-orange-200 animate-pulse">
                                                Menunggu Kelulusan Gugur
                                            </span>
                                        <?php elseif ($status == 'Rejected'): ?>
                                            <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-red-100 text-red-800 border border-red-200">
                                                Ditolak
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-600 border border-gray-200">
                                                Tidak Aktif
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="profile_saya.php">
                                        <input type="hidden" name="module_name" value="<?php echo $m_code; ?>">
                                        <?php if ($status == 'Approved'): ?>
                                            <input type="hidden" name="module_action" value="drop">
                                            <button type="submit" onclick="return confirm('Pasti mahu memohon untuk mengugurkan modul <?php echo $m_code; ?>?');"
                                                    class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-[11px] font-semibold transition shadow-sm">
                                                Gugur Modul
                                            </button>
                                        <?php elseif ($status == 'Not_Registered' || $status == 'Rejected'): ?>
                                            <input type="hidden" name="module_action" value="register">
                                            <button type="submit"
                                                    class="px-3 py-1.5 bg-[#c97a2a] hover:bg-[#b06822] text-white rounded-lg text-[11px] font-semibold transition shadow-sm">
                                                Mohon Daftar
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- ── RIGHT COLUMN: Salary Statements (Payroll) ── -->
            <div class="col-span-12 lg:col-span-7">
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6 min-h-[480px]">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-[16px] font-bold text-gray-800">Penyata & Slip Gaji</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Sejarah gaji bulanan anda</p>
                        </div>
                        <span class="material-symbols-outlined text-[#c97a2a] text-[28px]">payments</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-gray-400 font-semibold">
                                    <th class="py-3 px-4">Bulan / Tahun</th>
                                    <th class="py-3 px-4">Gaji Kasar</th>
                                    <th class="py-3 px-4">Gaji Bersih</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-center">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($payroll_history)): ?>
                                    <?php foreach ($payroll_history as $pr): 
                                        $gross_salary = $pr['basic_salary'] + $pr['allowances'];
                                        $monthFormatted = date('F Y', strtotime($pr['month'] . "-01"));
                                    ?>
                                        <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition">
                                            <td class="py-4 px-4 font-semibold text-gray-800"><?php echo $monthFormatted; ?></td>
                                            <td class="py-4 px-4 text-gray-500">RM <?php echo number_format($gross_salary, 2); ?></td>
                                            <td class="py-4 px-4 font-bold text-green-600">RM <?php echo number_format($pr['net_salary'], 2); ?></td>
                                            <td class="py-4 px-4">
                                                <?php if ($pr['payment_status'] == 'Paid'): ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-green-700 bg-green-50 px-2.5 py-1 rounded-full">
                                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> Dibayar
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">
                                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span> Diproses
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <button onclick='showPayslip(<?php echo json_encode($pr); ?>, "<?php echo $monthFormatted; ?>")'
                                                        class="inline-flex items-center gap-1 bg-[#c97a2a] text-white hover:bg-[#b06822] text-xs font-semibold px-3 py-1.5 rounded-lg transition shadow-sm">
                                                    <span class="material-symbols-outlined text-[14px]">receipt_long</span> Slip Gaji
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-20 text-gray-400">
                                            <span class="material-symbols-outlined text-[48px] opacity-35 block mb-2">payments</span>
                                            <p class="text-sm">Tiada rekod penyata gaji ditemui.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </div>

</main>

<!-- ═══════════ MODAL PENYATA GAJI (PAYSLIP) ═══════════ -->
<div id="payslip-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 hidden no-print">
    <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-gray-100 overflow-hidden transform scale-95 transition-all duration-300 flex flex-col max-h-[90vh]">
        
        <!-- Modal Top Bar -->
        <div class="flex justify-between items-center px-6 py-4 bg-gray-50 border-b border-gray-100 no-print">
            <span class="font-bold text-gray-800 text-base">Slip Gaji Kakitangan</span>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="flex items-center gap-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3.5 py-2 rounded-lg transition">
                    <span class="material-symbols-outlined text-[16px]">print</span> Cetak
                </button>
                <button onclick="closePayslip()" class="p-1.5 rounded-lg hover:bg-gray-200 text-gray-400 hover:text-gray-700 transition">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
        </div>

        <!-- Payslip Content (Visible during window.print()) -->
        <div id="payslip-modal-content" class="p-8 overflow-y-auto flex-1 bg-white">
            <!-- Header Slip -->
            <div class="text-center pb-6 border-b-2 border-gray-800">
                <h1 class="text-xl font-bold uppercase tracking-wider text-gray-800">Sistem Pengurusan Kanak-Kanak Terpadu</h1>
                <p class="text-xs text-gray-500 mt-1">Penyata Gaji Bulanan Kakitangan</p>
            </div>

            <!-- Maklumat Slip -->
            <div class="grid grid-cols-2 gap-4 py-6 text-sm">
                <div>
                    <p class="text-gray-400">Nama Kakitangan:</p>
                    <p class="font-bold text-gray-800" id="ps-name"><?php echo htmlspecialchars($teacher['full_name'] ?? ''); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-gray-400">Bulan Gaji:</p>
                    <p class="font-bold text-gray-800" id="ps-month"></p>
                </div>
                <div>
                    <p class="text-gray-400">Jawatan:</p>
                    <p class="font-bold text-gray-800" id="ps-position"><?php echo htmlspecialchars($staff['position'] ?? ''); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-gray-400">Bahagian / Jabatan:</p>
                    <p class="font-bold text-gray-800" id="ps-dept"><?php echo htmlspecialchars($staff['department'] ?? ''); ?></p>
                </div>
            </div>

            <!-- Jadual Gaji -->
            <div class="border border-gray-200 rounded-lg overflow-hidden mt-2">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 font-bold text-gray-800">
                            <th class="py-2.5 px-4">Butiran Pendapatan</th>
                            <th class="py-2.5 px-4 text-right">Jumlah (RM)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-3 px-4 text-gray-700">Gaji Pokok (Basic Salary)</td>
                            <td class="py-3 px-4 text-right font-medium text-gray-800" id="ps-basic">0.00</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4 text-gray-700">Elaun & Bonus (Allowances)</td>
                            <td class="py-3 px-4 text-right font-medium text-gray-800" id="ps-allowance">0.00</td>
                        </tr>
                        <tr class="bg-gray-50/50">
                            <td class="py-3 px-4 font-bold text-gray-800">Kasar (Gross Salary)</td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900" id="ps-gross">0.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border border-gray-200 rounded-lg overflow-hidden mt-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 font-bold text-gray-800">
                            <th class="py-2.5 px-4">Butiran Potongan</th>
                            <th class="py-2.5 px-4 text-right">Jumlah (RM)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-3 px-4 text-gray-700">Potongan (KWSP/SOCSO/Cukai/Lain-lain)</td>
                            <td class="py-3 px-4 text-right font-medium text-red-600" id="ps-deduction">0.00</td>
                        </tr>
                        <tr class="bg-gray-50/50">
                            <td class="py-3 px-4 font-bold text-gray-800">Jumlah Potongan</td>
                            <td class="py-3 px-4 text-right font-bold text-red-700" id="ps-total-deduction">0.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Jumlah Bersih -->
            <div class="mt-6 p-4 bg-green-50/50 border border-green-200 rounded-xl flex justify-between items-center">
                <div>
                    <span class="text-xs text-green-700 font-bold uppercase tracking-wider">Gaji Bersih (Net Salary)</span>
                    <h2 class="text-2xl font-bold text-green-800 mt-1">RM <span id="ps-net">0.00</span></h2>
                </div>
                <div class="text-right">
                    <span class="text-xs text-gray-500 font-semibold block">Status Pembayaran:</span>
                    <span class="inline-block mt-1 bg-green-100 text-green-800 text-xs font-bold px-3 py-1 rounded-full" id="ps-status">DIBAYAR</span>
                </div>
            </div>

            <!-- Footer / Tanda Tangan -->
            <div class="mt-12 grid grid-cols-2 gap-8 pt-8 border-t border-dashed border-gray-200 text-xs">
                <div>
                    <p class="text-gray-400">Disediakan Oleh:</p>
                    <br><br>
                    <p class="font-bold text-gray-800 border-b border-gray-400 inline-block">Pengurus HR & Kewangan</p>
                </div>
                <div class="text-right">
                    <p class="text-gray-400">Diterima Oleh:</p>
                    <br><br>
                    <p class="font-bold text-gray-800 border-b border-gray-400 inline-block"><?php echo htmlspecialchars($teacher['full_name'] ?? ''); ?></p>
                </div>
            </div>
        </div>

    </div>
</div>



// Show payslip in modal
function showPayslip(pr, monthStr) {
    document.getElementById('ps-month').textContent = monthStr;
    document.getElementById('ps-basic').textContent = parseFloat(pr.basic_salary).toFixed(2);
    document.getElementById('ps-allowance').textContent = parseFloat(pr.allowances).toFixed(2);
    
    const gross = parseFloat(pr.basic_salary) + parseFloat(pr.allowances);
    document.getElementById('ps-gross').textContent = gross.toFixed(2);
    document.getElementById('ps-deduction').textContent = parseFloat(pr.deductions).toFixed(2);
    document.getElementById('ps-total-deduction').textContent = parseFloat(pr.deductions).toFixed(2);
    document.getElementById('ps-net').textContent = parseFloat(pr.net_salary).toFixed(2);
    
    const statusEl = document.getElementById('ps-status');
    if (pr.payment_status === 'Paid') {
        statusEl.textContent = 'DIBAYAR';
        statusEl.className = 'inline-block mt-1 bg-green-100 text-green-800 text-xs font-bold px-3 py-1 rounded-full';
    } else {
        statusEl.textContent = 'PROSES';
        statusEl.className = 'inline-block mt-1 bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1 rounded-full';
    }
    
    const modal = document.getElementById('payslip-modal');
    modal.classList.remove('hidden');
    // For smooth scaling animation
    setTimeout(() => {
        modal.firstElementChild.classList.remove('scale-95');
        modal.firstElementChild.classList.add('scale-100');
    }, 10);
}

function closePayslip() {
    const modal = document.getElementById('payslip-modal');
    modal.firstElementChild.classList.remove('scale-100');
    modal.firstElementChild.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 150);
}


</script>
</body>
</html>
