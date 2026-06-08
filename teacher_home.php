<?php
// teacher_home.php — Teacher Dashboard (Class-Based, Multi-Tab)
// SECURITY: sahkan_peranan('teacher') redirects any non-teacher.
require_once 'auth_guard.php';
sahkan_peranan('teacher');
require_once 'db.php';

$username = $_SESSION['username'];
$uid      = (int)$_SESSION['user_id'];

// ── Teacher Info ──────────────────────────────────────────────
$teacher_id   = 0;
$teacher_name = htmlspecialchars($username);

$r = mysqli_query($conn, "SELECT id, full_name FROM teachers WHERE user_id = $uid LIMIT 1");
if ($r && $row = mysqli_fetch_assoc($r)) {
    $teacher_id   = (int)$row['id'];
    $teacher_name = htmlspecialchars($row['full_name'] ?: $username);
}

// ── Handle Class Request POST ─────────────────────────────────
$request_msg  = '';
$request_type_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_request_submit']) && $teacher_id) {
    $req_class_id   = (int)($_POST['request_class_id'] ?? 0);
    $req_type       = ($_POST['request_type'] ?? '');

    if ($req_class_id > 0 && in_array($req_type, ['Add', 'Drop'])) {
        // Prevent duplicate pending requests
        $dup = mysqli_query($conn, "SELECT id FROM teacher_class_requests 
                WHERE teacher_id = $teacher_id AND class_id = $req_class_id 
                AND request_type = '" . mysqli_real_escape_string($conn, $req_type) . "' 
                AND status = 'Pending' LIMIT 1");
        if ($dup && mysqli_num_rows($dup) > 0) {
            $request_msg = 'Permohonan yang sama masih dalam proses kelulusan.';
            $request_type_msg = 'warning';
        } else {
            $stmt = $conn->prepare("INSERT INTO teacher_class_requests (teacher_id, class_id, request_type, status) VALUES (?, ?, ?, 'Pending')");
            $stmt->bind_param("iis", $teacher_id, $req_class_id, $req_type);
            if ($stmt->execute()) {
                $request_msg = 'Permohonan berjaya dihantar! Menunggu kelulusan admin.';
                $request_type_msg = 'success';
            } else {
                $request_msg = 'Ralat semasa menghantar permohonan. Sila cuba lagi.';
                $request_type_msg = 'error';
            }
            $stmt->close();
        }
    } else {
        $request_msg = 'Sila pilih kelas dan jenis permohonan yang sah.';
        $request_type_msg = 'warning';
    }
}

// ── Fetch ALL classes for this teacher ────────────────────────
$classes = [];
if ($teacher_id) {
    $r = mysqli_query($conn, "SELECT id, class_name, module FROM classes WHERE teacher_id = $teacher_id ORDER BY class_name ASC");
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $classes[] = $row;
        }
    }
}

$today = date('Y-m-d');

// ── Build per-class data ──────────────────────────────────────
$class_data = [];

// Module → class_group mapping
$module_group_map = [
    'Tadika'    => ['Tadika 4-5 Tahun', 'Tadika 6 Tahun'],
    'KAFA Care' => ['KAFA Care'],
    'Taska'     => ['Aktiviti Taska'],
];

foreach ($classes as $cls) {
    $cid   = (int)$cls['id'];
    $cname = htmlspecialchars($cls['class_name']);
    $cmod  = $cls['module'];

    // Student count
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM student_classes WHERE class_id = $cid");
    $students = ($r) ? (int)mysqli_fetch_assoc($r)['c'] : 0;

    // Present today
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM attendance a
        INNER JOIN student_classes sc ON sc.student_id = a.student_id
        WHERE sc.class_id = $cid AND a.date = '$today' AND a.status = 'Present'");
    $present = ($r) ? (int)mysqli_fetch_assoc($r)['c'] : 0;

    // Absent today
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM attendance a
        INNER JOIN student_classes sc ON sc.student_id = a.student_id
        WHERE sc.class_id = $cid AND a.date = '$today' AND a.status = 'Absent'");
    $absent = ($r) ? (int)mysqli_fetch_assoc($r)['c'] : 0;

    // Lesson plans count for this class (match class_group from module)
    $lp_count = 0;
    if (isset($module_group_map[$cmod])) {
        $groups = $module_group_map[$cmod];
        $groups_esc = "'" . implode("','", array_map(function($g) use ($conn) { return mysqli_real_escape_string($conn, $g); }, $groups)) . "'";
        $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM lesson_plans WHERE teacher_id = $teacher_id AND class_group IN ($groups_esc)");
        $lp_count = ($r) ? (int)mysqli_fetch_assoc($r)['c'] : 0;
    }

    // Recent daily reports for this class
    $reports = [];
    $r = mysqli_query($conn, "SELECT report_date, activities, notes FROM daily_reports 
        WHERE class_id = $cid AND teacher_id = $teacher_id 
        ORDER BY report_date DESC LIMIT 3");
    if ($r) { while ($row = mysqli_fetch_assoc($r)) $reports[] = $row; }

    // Upcoming activities
    $upcoming = [];
    if (isset($module_group_map[$cmod])) {
        $groups = $module_group_map[$cmod];
        $groups_esc = "'" . implode("','", array_map(function($g) use ($conn) { return mysqli_real_escape_string($conn, $g); }, $groups)) . "'";
        $r = mysqli_query($conn, "SELECT activity_name, activity_date, activity_time, status
            FROM activity_schedules WHERE teacher_id = $teacher_id AND class_group IN ($groups_esc) AND activity_date >= '$today'
            ORDER BY activity_date ASC LIMIT 3");
        if ($r) { while ($row = mysqli_fetch_assoc($r)) $upcoming[] = $row; }
    }

    $class_data[] = [
        'id'       => $cid,
        'name'     => $cname,
        'module'   => $cmod,
        'students' => $students,
        'present'  => $present,
        'absent'   => $absent,
        'plans'    => $lp_count,
        'reports'  => $reports,
        'upcoming' => $upcoming,
    ];
}

// ── Data for class request form ───────────────────────────────
$my_class_ids = array_map(function($c) { return (int)$c['id']; }, $classes);

// All classes (for Add — classes NOT taught by this teacher)
$add_classes = [];
$drop_classes = [];
if ($teacher_id) {
    $r = mysqli_query($conn, "SELECT id, class_name, module FROM classes ORDER BY class_name ASC");
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            if (!in_array((int)$row['id'], $my_class_ids)) {
                $add_classes[] = $row;
            } else {
                $drop_classes[] = $row;
            }
        }
    }
}

// Request history
$request_history = [];
if ($teacher_id) {
    $r = mysqli_query($conn, "SELECT tcr.*, c.class_name, c.module 
        FROM teacher_class_requests tcr
        JOIN classes c ON tcr.class_id = c.id
        WHERE tcr.teacher_id = $teacher_id
        ORDER BY tcr.created_at DESC LIMIT 20");
    if ($r) { while ($row = mysqli_fetch_assoc($r)) $request_history[] = $row; }
}

// ── Helper ────────────────────────────────────────────────────
function time_ago($dt) {
    $diff = (new DateTime())->diff(new DateTime($dt));
    if ($diff->days > 0) return $diff->days . ' hari lepas';
    if ($diff->h > 0)    return $diff->h  . ' jam lepas';
    if ($diff->i > 0)    return $diff->i  . ' min lepas';
    return 'Baru sahaja';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard Guru — SMS</title>
    <meta name="description" content="Panel Guru — Sistem Pengurusan Sekolah"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { min-height:max(884px,100dvh); font-family:'Inter',sans-serif; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:#1e2124; }
        ::-webkit-scrollbar-thumb { background:#444; border-radius:10px; }

        /* Cards */
        .bento-card { transition:transform .2s ease, box-shadow .2s ease; }
        .bento-card:hover { transform:translateY(-2px); box-shadow:0 10px 20px -4px rgba(0,0,0,.06); }
        .kpi-card { background:#fff; border-radius:12px; padding:20px 22px;
                    box-shadow:0 1px 3px rgba(0,0,0,.06); border:1px solid rgba(199,197,212,.2); }

        /* Tabs */
        .class-tab-btn {
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 10px 10px 0 0;
            border: 1px solid rgba(199,197,212,.25);
            border-bottom: none;
            background: #f0f1f4;
            color: #777583;
            cursor: pointer;
            transition: all .2s ease;
            position: relative;
            top: 1px;
        }
        .class-tab-btn:hover { background: #fff; color: #191c1e; }
        .class-tab-btn.active {
            background: #fff;
            color: #c97a2a;
            border-color: rgba(199,197,212,.35);
            box-shadow: 0 -2px 6px rgba(201,122,42,.08);
        }
        .class-tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #fff;
        }
        .class-panel { display:none; }
        .class-panel.active { display:block; }
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
                <h1 class="text-[20px] font-semibold text-[#191c1e]">Selamat Datang, <?php echo htmlspecialchars($username); ?>!</h1>
                <p class="text-[12px] text-[#777583]">Sistem Pengurusan Sekolah (SMS)</p>
            </div>
        </div>
        <a href="profile_saya.php" class="flex items-center gap-3 hover:opacity-85 transition group">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e] group-hover:text-[#c97a2a] transition"><?php echo $teacher_name; ?></span>
                <span class="text-[11px] font-bold text-[#c97a2a] bg-[#ffb347]/20 px-2 py-0.5 rounded-full mt-0.5">Guru Kelas</span>
            </div>
            <div class="relative">
                <div class="w-11 h-11 rounded-full bg-[#c97a2a] border-2 border-[#ffb347]/50 shadow
                            flex items-center justify-center text-white text-[18px] font-bold select-none">
                    <?php echo strtoupper(substr($username,0,1)); ?>
                </div>
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
            </div>
        </a>
    </header>

    <!-- Alert Banner -->
    <div class="fixed top-[68px] right-0 w-full md:w-[calc(100%-260px)] bg-[#fff3e0] text-[#7c4d00]
                flex items-center justify-center gap-2 py-[7px] px-4 z-30 text-[12px] font-medium">
        <span class="material-symbols-outlined text-[16px]">schedule</span>
        <span>Peringatan: Sila kemaskini kehadiran kelas sebelum jam 10:00 pagi. Semak alahan pelajar sebelum sesi makan.</span>
        <a href="ambil_kehadiran.php" class="font-bold underline ml-2 hover:opacity-80">Ambil Kehadiran</a>
    </div>

    <!-- Content -->
    <div class="pt-[110px] px-6 pb-8 max-w-[1440px] mx-auto">

<?php if (empty($class_data)): ?>
        <!-- No classes assigned -->
        <div class="bg-white rounded-xl p-10 text-center shadow-sm border border-[#c7c5d4]/20">
            <span class="material-symbols-outlined text-[64px] text-[#c97a2a]/30 block mb-4">school</span>
            <h2 class="text-[22px] font-bold text-[#191c1e] mb-2">Tiada Kelas Ditetapkan</h2>
            <p class="text-[14px] text-[#777583] mb-6">Anda belum ditugaskan ke mana-mana kelas. Sila hubungi pentadbir atau hantar permohonan kelas di bawah.</p>
        </div>
<?php else: ?>

        <!-- ── Class Tabs ────────────────────────────────────── -->
        <?php if (count($class_data) > 1): ?>
        <div class="flex flex-wrap gap-1 mb-0" id="classTabs">
            <?php foreach ($class_data as $i => $cd): ?>
            <button type="button"
                    class="class-tab-btn <?php echo $i === 0 ? 'active' : ''; ?>"
                    onclick="switchClassTab(<?php echo $i; ?>)"
                    id="tab-btn-<?php echo $i; ?>">
                <span class="material-symbols-outlined text-[16px] align-middle mr-1">class</span>
                <?php echo $cd['name']; ?>
                <span class="ml-1.5 text-[10px] px-1.5 py-0.5 rounded-full bg-[#c97a2a]/10 text-[#c97a2a] font-bold"><?php echo $cd['module']; ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ── Class Panels ──────────────────────────────────── -->
        <?php foreach ($class_data as $i => $cd): ?>
        <div class="class-panel <?php echo $i === 0 ? 'active' : ''; ?> bg-white/0 border-t-0" id="class-panel-<?php echo $i; ?>">

            <!-- Hero Row -->
            <div class="grid grid-cols-12 gap-5 mb-5 <?php echo count($class_data) > 1 ? 'mt-0' : ''; ?>">
                <!-- Welcome Card -->
                <div class="col-span-12 lg:col-span-8 bg-white rounded-xl <?php echo count($class_data) > 1 ? 'rounded-tl-none' : ''; ?> p-7 relative overflow-hidden shadow-sm border border-[#c7c5d4]/20 bento-card">
                    <div class="relative z-10 flex flex-col sm:flex-row items-center justify-between gap-6">
                        <div class="max-w-sm">
                            <p class="text-[12px] text-[#c97a2a] font-bold uppercase tracking-wider mb-1">Kelas Anda</p>
                            <h2 class="text-[28px] font-bold text-[#191c1e] leading-tight mb-2"><?php echo $cd['name']; ?></h2>
                            <p class="text-[14px] text-[#464552] mb-5">
                                Anda mempunyai <strong><?php echo $cd['students']; ?> pelajar</strong> dalam kelas ini.
                                Hari ini: <span class="text-green-600 font-bold"><?php echo $cd['present']; ?> hadir</span>,
                                <span class="text-red-500 font-bold"><?php echo $cd['absent']; ?> tidak hadir</span>.
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <a href="ambil_kehadiran.php"
                                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#c97a2a] text-white rounded-lg text-[13px] font-semibold hover:opacity-90 transition">
                                    <span class="material-symbols-outlined text-[18px]">how_to_reg</span>Ambil Kehadiran
                                </a>
                                <a href="teacher_daily_report.php"
                                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#ffb347]/20 text-[#7c4d00] rounded-lg text-[13px] font-semibold hover:bg-[#ffb347]/30 transition">
                                    <span class="material-symbols-outlined text-[18px]">edit_note</span>Laporan Harian
                                </a>
                            </div>
                        </div>
                        <div class="hidden sm:flex w-44 h-44 rounded-2xl bg-[#ffb347]/15 items-center justify-center flex-shrink-0 border-4 border-white shadow-lg transform rotate-2">
                            <span class="material-symbols-outlined text-[#c97a2a] text-[80px]">class</span>
                        </div>
                    </div>
                    <div class="absolute -right-16 -top-16 w-64 h-64 bg-[#ffb347]/10 rounded-full blur-3xl pointer-events-none"></div>
                </div>

                <!-- Right Cards -->
                <div class="col-span-12 lg:col-span-4 grid grid-cols-2 gap-5">
                    <!-- Date -->
                    <div class="col-span-2 bg-[#fff3e0] rounded-xl p-5 flex flex-col justify-between bento-card">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-[#7c4d00] font-bold">Tarikh Hari Ini</p>
                                <h3 class="text-[15px] font-semibold text-[#191c1e] mt-1"><?php echo date('l, d M Y'); ?></h3>
                            </div>
                            <span class="material-symbols-outlined text-[36px] text-[#c97a2a]">calendar_today</span>
                        </div>
                        <p class="text-[12px] text-[#7c4d00] mt-3 font-medium">
                            Minggu <?php echo date('W'); ?> — Sesi <?php echo date('Y'); ?>
                        </p>
                    </div>
                    <!-- Quick Actions -->
                    <div class="col-span-2 bg-[#1a1c2e] text-white rounded-xl p-5 bento-card">
                        <h3 class="text-[15px] font-semibold mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">bolt</span>Tindakan Pantas
                        </h3>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="lesson_plan.php"
                               class="flex flex-col items-center gap-1.5 p-3 bg-white/10 hover:bg-white/20 rounded-lg transition text-white no-underline">
                                <span class="material-symbols-outlined text-[22px]">menu_book</span>
                                <span class="text-[11px] font-medium">Rancangan Mengajar</span>
                            </a>
                            <a href="perkembangan.php"
                               class="flex flex-col items-center gap-1.5 p-3 bg-white/10 hover:bg-white/20 rounded-lg transition text-white no-underline">
                                <span class="material-symbols-outlined text-[22px]">monitoring</span>
                                <span class="text-[11px] font-medium">Perkembangan</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-5">
                <div class="kpi-card bento-card">
                    <div class="w-10 h-10 rounded-lg bg-[#ffb347]/20 flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-[#c97a2a] text-[22px]">groups</span>
                    </div>
                    <p class="text-[12px] text-[#777583] mb-1">Pelajar Dalam Kelas</p>
                    <h4 class="text-[34px] font-bold text-[#191c1e] leading-none"><?php echo $cd['students']; ?></h4>
                </div>
                <div class="kpi-card bento-card">
                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-green-600 text-[22px]">how_to_reg</span>
                    </div>
                    <p class="text-[12px] text-[#777583] mb-1">Hadir Hari Ini</p>
                    <h4 class="text-[34px] font-bold text-green-600 leading-none"><?php echo $cd['present']; ?></h4>
                </div>
                <div class="kpi-card bento-card">
                    <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-red-500 text-[22px]">person_off</span>
                    </div>
                    <p class="text-[12px] text-[#777583] mb-1">Tidak Hadir</p>
                    <h4 class="text-[34px] font-bold text-red-500 leading-none"><?php echo $cd['absent']; ?></h4>
                </div>
                <div class="kpi-card bento-card">
                    <div class="w-10 h-10 rounded-lg bg-[#d6e3ff] flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-[#0f3e78] text-[22px]">menu_book</span>
                    </div>
                    <p class="text-[12px] text-[#777583] mb-1">Rancangan Mengajar</p>
                    <h4 class="text-[34px] font-bold text-[#191c1e] leading-none"><?php echo $cd['plans']; ?></h4>
                </div>
            </div>

            <!-- Bottom Row: Upcoming Activities + Recent Reports -->
            <div class="grid grid-cols-12 gap-5">
                <!-- Upcoming Activities -->
                <div class="col-span-12 lg:col-span-7 bg-white rounded-xl p-6 shadow-sm bento-card border border-[#c7c5d4]/20">
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h3 class="text-[16px] font-semibold">Aktiviti Akan Datang</h3>
                            <p class="text-[12px] text-[#777583] mt-0.5">Jadual aktiviti kelas <strong><?php echo $cd['name']; ?></strong></p>
                        </div>
                        <a href="aktiviti_kelas.php" class="text-[#c97a2a] font-semibold text-[13px] hover:underline">Lihat Semua</a>
                    </div>
                    <?php if (!empty($cd['upcoming'])): ?>
                    <div class="space-y-3">
                        <?php foreach ($cd['upcoming'] as $act): ?>
                        <div class="flex items-center gap-4 p-3 bg-[#f7f9fb] rounded-lg">
                            <div class="w-10 h-10 rounded-lg bg-[#ffb347]/20 flex-shrink-0 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#c97a2a] text-[20px]">event</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-semibold truncate"><?php echo htmlspecialchars($act['activity_name']); ?></p>
                                <p class="text-[11px] text-[#777583]"><?php echo date('d M Y', strtotime($act['activity_date'])); ?> — <?php echo htmlspecialchars($act['activity_time']); ?></p>
                            </div>
                            <span class="text-[10px] px-2 py-1 rounded-full font-medium flex-shrink-0
                                <?php echo ($act['status']==='Completed') ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                <?php echo htmlspecialchars($act['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-10 text-[#777583]">
                        <span class="material-symbols-outlined text-[48px] opacity-30 block mb-2">event_busy</span>
                        <p class="text-[13px]">Tiada aktiviti dijadualkan.</p>
                        <a href="aktiviti_kelas.php" class="text-[#c97a2a] text-[12px] font-semibold mt-2 inline-block hover:underline">Tambah Aktiviti</a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Reports -->
                <div class="col-span-12 lg:col-span-5 bg-white rounded-xl p-6 shadow-sm bento-card border border-[#c7c5d4]/20 flex flex-col">
                    <h3 class="text-[16px] font-semibold mb-5">Laporan Harian Terkini</h3>
                    <div class="flex-1 space-y-4">
                        <?php if (!empty($cd['reports'])): ?>
                            <?php foreach ($cd['reports'] as $rep): ?>
                            <div class="flex gap-3">
                                <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#fff3e0] flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[#c97a2a] text-[18px]">description</span>
                                </div>
                                <div>
                                    <p class="text-[13px] font-semibold"><?php echo date('d M Y', strtotime($rep['report_date'])); ?></p>
                                    <p class="text-[11px] text-[#464552] line-clamp-2"><?php echo htmlspecialchars(substr($rep['activities'],0,80)); ?>...</p>
                                    <span class="text-[10px] text-[#777583] mt-0.5 block"><?php echo time_ago($rep['report_date']); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#fff3e0] flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#c97a2a] text-[18px]">info</span>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold">Tiada laporan lagi</p>
                                <p class="text-[11px] text-[#464552]">Mula buat laporan harian anda untuk kelas ini.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <a href="teacher_daily_report.php"
                       class="mt-5 block w-full py-2 text-center border-2 border-[#e0e3e5] rounded-lg
                              text-[13px] font-semibold hover:bg-[#f2f4f6] transition">
                        Lihat Semua Laporan
                    </a>
                </div>
            </div>

        </div><!-- /class-panel -->
        <?php endforeach; ?>

<?php endif; ?>

        <!-- ══════════════════════════════════════════════════════
             Permohonan Kelas Mengajar (Class Teaching Request)
             ══════════════════════════════════════════════════════ -->
        <div class="mt-8 bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden">
            <!-- Section Header -->
            <div class="px-7 py-5 border-b border-[#e0e3e5] flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-[#c97a2a]/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[#c97a2a] text-[22px]">swap_horiz</span>
                </div>
                <div>
                    <h3 class="text-[17px] font-bold text-[#191c1e]">Permohonan Kelas Mengajar</h3>
                    <p class="text-[12px] text-[#777583]">Mohon untuk tambah atau gugur kelas yang anda ajar</p>
                </div>
            </div>

            <div class="p-7">
                <!-- Flash Message -->
                <?php if ($request_msg): ?>
                <div class="mb-5 px-4 py-3 rounded-lg text-[13px] font-medium flex items-center gap-2
                    <?php 
                        if ($request_type_msg === 'success') echo 'bg-green-50 text-green-700 border border-green-200';
                        elseif ($request_type_msg === 'error') echo 'bg-red-50 text-red-700 border border-red-200';
                        else echo 'bg-yellow-50 text-yellow-700 border border-yellow-200';
                    ?>">
                    <span class="material-symbols-outlined text-[18px]">
                        <?php echo $request_type_msg === 'success' ? 'check_circle' : ($request_type_msg === 'error' ? 'error' : 'warning'); ?>
                    </span>
                    <?php echo htmlspecialchars($request_msg); ?>
                </div>
                <?php endif; ?>

                <!-- Request Form -->
                <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <!-- Request Type -->
                    <div class="md:col-span-3">
                        <label class="block text-[12px] font-semibold text-[#464552] mb-1.5">Jenis Permohonan</label>
                        <select name="request_type" id="requestType" onchange="updateClassDropdown()"
                                class="w-full px-3 py-2.5 border border-[#d0d5dd] rounded-lg text-[13px] bg-white focus:ring-2 focus:ring-[#c97a2a]/30 focus:border-[#c97a2a] transition" required>
                            <option value="">— Pilih Jenis —</option>
                            <option value="Add">➕ Tambah Kelas</option>
                            <option value="Drop">➖ Gugur Kelas</option>
                        </select>
                    </div>

                    <!-- Class Dropdown -->
                    <div class="md:col-span-6">
                        <label class="block text-[12px] font-semibold text-[#464552] mb-1.5">Pilih Kelas</label>
                        <select name="request_class_id" id="requestClassId"
                                class="w-full px-3 py-2.5 border border-[#d0d5dd] rounded-lg text-[13px] bg-white focus:ring-2 focus:ring-[#c97a2a]/30 focus:border-[#c97a2a] transition" required>
                            <option value="">— Sila pilih jenis permohonan dahulu —</option>
                        </select>
                    </div>

                    <!-- Submit -->
                    <div class="md:col-span-3">
                        <button type="submit" name="class_request_submit" value="1"
                                class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#c97a2a] text-white rounded-lg text-[13px] font-semibold hover:bg-[#b06a22] active:scale-[.97] transition shadow-sm">
                            <span class="material-symbols-outlined text-[18px]">send</span>
                            Hantar Permohonan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Request History -->
            <?php if (!empty($request_history)): ?>
            <div class="border-t border-[#e0e3e5]">
                <div class="px-7 py-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-[#777583]">history</span>
                    <h4 class="text-[14px] font-semibold text-[#464552]">Sejarah Permohonan</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13px]">
                        <thead>
                            <tr class="bg-[#f7f9fb] text-[#777583] text-[11px] uppercase tracking-wider">
                                <th class="px-7 py-3 font-semibold">Tarikh</th>
                                <th class="px-4 py-3 font-semibold">Kelas</th>
                                <th class="px-4 py-3 font-semibold">Modul</th>
                                <th class="px-4 py-3 font-semibold">Jenis</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0f1f4]">
                            <?php foreach ($request_history as $rh): ?>
                            <tr class="hover:bg-[#fafbfc] transition">
                                <td class="px-7 py-3 text-[#464552] whitespace-nowrap"><?php echo date('d M Y, H:i', strtotime($rh['created_at'])); ?></td>
                                <td class="px-4 py-3 font-medium text-[#191c1e]"><?php echo htmlspecialchars($rh['class_name']); ?></td>
                                <td class="px-4 py-3 text-[#777583]"><?php echo htmlspecialchars($rh['module']); ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($rh['request_type'] === 'Add'): ?>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                                            <span class="material-symbols-outlined text-[13px]">add_circle</span>Tambah
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full">
                                            <span class="material-symbols-outlined text-[13px]">remove_circle</span>Gugur
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $status_styles = [
                                        'Pending'  => 'bg-yellow-100 text-yellow-700',
                                        'Approved' => 'bg-green-100 text-green-700',
                                        'Rejected' => 'bg-red-100 text-red-700',
                                    ];
                                    $status_icons = [
                                        'Pending'  => 'hourglass_top',
                                        'Approved' => 'check_circle',
                                        'Rejected' => 'cancel',
                                    ];
                                    $st = $rh['status'];
                                    $stClass = $status_styles[$st] ?? 'bg-gray-100 text-gray-600';
                                    $stIcon  = $status_icons[$st] ?? 'help';
                                    ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full <?php echo $stClass; ?>">
                                        <span class="material-symbols-outlined text-[13px]"><?php echo $stIcon; ?></span>
                                        <?php echo htmlspecialchars($st); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="border-t border-[#e0e3e5] px-7 py-6 text-center text-[#777583]">
                <span class="material-symbols-outlined text-[36px] opacity-30 block mb-1">inbox</span>
                <p class="text-[13px]">Tiada sejarah permohonan lagi.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- ═══════════ JAVASCRIPT ═══════════ -->
<script>
// Class tab switching
function switchClassTab(index) {
    document.querySelectorAll('.class-tab-btn').forEach(function(btn, i) {
        btn.classList.toggle('active', i === index);
    });
    document.querySelectorAll('.class-panel').forEach(function(panel, i) {
        panel.classList.toggle('active', i === index);
    });
}

// Dynamic class dropdown based on request type
const addClasses = <?php echo json_encode($add_classes, JSON_HEX_TAG | JSON_HEX_APOS); ?>;
const dropClasses = <?php echo json_encode($drop_classes, JSON_HEX_TAG | JSON_HEX_APOS); ?>;

function updateClassDropdown() {
    const type = document.getElementById('requestType').value;
    const select = document.getElementById('requestClassId');
    select.innerHTML = '';

    let classes = [];
    if (type === 'Add') {
        classes = addClasses;
    } else if (type === 'Drop') {
        classes = dropClasses;
    }

    if (classes.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = type === 'Add' ? '— Tiada kelas tersedia untuk ditambah —' :
                          type === 'Drop' ? '— Tiada kelas untuk digugurkan —' :
                          '— Sila pilih jenis permohonan dahulu —';
        select.appendChild(opt);
        return;
    }

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '— Pilih Kelas —';
    select.appendChild(placeholder);

    classes.forEach(function(cls) {
        const opt = document.createElement('option');
        opt.value = cls.id;
        opt.textContent = cls.class_name + ' (' + cls.module + ')';
        select.appendChild(opt);
    });
}
</script>

</body>
</html>
