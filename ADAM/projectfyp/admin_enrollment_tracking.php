<?php
// admin_enrollment_tracking.php — Status Pendaftaran (Enrollment Tracking)
session_start();
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$themeColor = '#77dd77';

// ======================== QUERIES FOR STATS ========================
$stat_pending = $conn->query("SELECT COUNT(*) AS c FROM students WHERE status='Pending'")->fetch_assoc()['c'];
$stat_waitlist = $conn->query("SELECT COUNT(*) AS c FROM waitlist WHERE status='Waiting'")->fetch_assoc()['c'];
$stat_active = $conn->query("SELECT COUNT(*) AS c FROM students WHERE status='Active'")->fetch_assoc()['c'];
$stat_graduated = $conn->query("SELECT COUNT(*) AS c FROM students WHERE status='Graduated'")->fetch_assoc()['c'];
$stat_withdrawn = $conn->query("SELECT COUNT(*) AS c FROM students WHERE status='Withdrawn'")->fetch_assoc()['c'];

// ======================== FILTERS ========================
$filter_name = isset($_GET['name']) ? trim($_GET['name']) : '';
$filter_module = isset($_GET['module']) ? $_GET['module'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$where = [];
$params = [];
$types = '';

if ($filter_name !== '') {
    $where[] = "s.full_name LIKE ?";
    $params[] = '%' . $filter_name . '%';
    $types .= 's';
}

if ($filter_module !== '') {
    $where[] = "s.module = ?";
    $params[] = $filter_module;
    $types .= 's';
}

if ($filter_status !== '') {
    if ($filter_status === 'Waitlist') {
        $where[] = "w.id IS NOT NULL";
    } else {
        $where[] = "s.status = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
}

// ======================== QUERIES FOR STUDENTS ========================
$sql = "SELECT s.*, p.full_name AS parent_full_name, p.phone_number AS parent_phone, w.id AS waitlist_id 
        FROM students s 
        LEFT JOIN parents p ON s.parent_id = p.id
        LEFT JOIN waitlist w ON s.id = w.student_id AND w.status = 'Waiting'";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students_result = $stmt->get_result();
$stmt->close();

// ======================== QUERIES FOR HISTORY LOG ========================
$history_sql = "
    SELECT h.*, s.full_name AS student_name, u.username AS changed_by_name
    FROM enrollment_history h
    JOIN students s ON h.student_id = s.id
    LEFT JOIN users u ON h.changed_by = u.id
    ORDER BY h.changed_at DESC LIMIT 50
";
$history_result = $conn->query($history_sql);

?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pendaftaran - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: 270px; background-color: <?php echo $themeColor; ?>; color: white; padding-top: 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); display: flex; flex-direction: column; overflow-y: auto; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; }
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header h2 { margin: 0; font-size: 22px; }
        .menu-label { padding: 15px 20px 5px; font-size: 11px; font-weight: 700; letter-spacing: 1px; opacity: 0.7; }
        .sidebar a { padding: 12px 20px; text-decoration: none; font-size: 14px; color: white; display: block; border-bottom: 1px solid rgba(255,255,255,0.1); transition: all 0.3s; }
        .sidebar a:hover { background-color: rgba(255,255,255,0.2); padding-left: 25px; }
        .sidebar a.active { background: rgba(255,255,255,0.25); font-weight: bold; border-left: 4px solid white; }

        /* Content */
        .content { margin-left: 270px; flex: 1; padding: 30px 40px; overflow-y: auto; min-height: 100vh; }
        h1 { color: #333; margin-bottom: 5px; font-size: 26px; }
        .subtitle { color: #888; margin-bottom: 25px; font-size: 14px; }

        /* Stat Cards */
        .stat-row { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 150px; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 3px 15px rgba(0,0,0,0.05); text-align: center; }
        .stat-card .number { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-card .label { font-size: 12px; color: #777; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .stat-pending { border-left: 5px solid #f0ad4e; }
        .stat-pending .number { color: #f0ad4e; }
        .stat-waitlist { border-left: 5px solid #fd7e14; }
        .stat-waitlist .number { color: #fd7e14; }
        .stat-active-bg { border-left: 5px solid #28a745; }
        .stat-active-bg .number { color: #28a745; }
        .stat-graduated { border-left: 5px solid #17a2b8; }
        .stat-graduated .number { color: #17a2b8; }
        .stat-withdrawn { border-left: 5px solid #dc3545; }
        .stat-withdrawn .number { color: #dc3545; }

        /* Filter */
        .filter-bar { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-bar input[type="text"], .filter-bar select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; outline: none; font-family: inherit; font-size: 14px; }
        .filter-bar input[type="text"]:focus, .filter-bar select:focus { border-color: <?php echo $themeColor; ?>; }
        .btn-search { padding: 10px 20px; background: <?php echo $themeColor; ?>; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-search:hover { background: #5cbd5c; }

        /* Student Card */
        .student-card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; }
        .student-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .student-info h3 { margin: 0 0 5px; font-size: 18px; color: #333; display: flex; align-items: center; gap: 10px; }
        .student-info p { margin: 2px 0; color: #666; font-size: 13px; }
        .module-tag { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white; display: inline-block; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; color: #1a5928; }
        .tag-kafacare { background: #b5ead7; color: #2d6a4f; }
        
        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: 700; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-graduated { background: #d1ecf1; color: #0c5460; }
        .badge-withdrawn { background: #f8d7da; color: #721c24; }
        .badge-waitlist { background: #ffe8d6; color: #fd7e14; }

        /* Progress Tracker */
        .tracker { display: flex; align-items: center; justify-content: space-between; position: relative; padding: 20px 0; margin-top: 10px; }
        .tracker::before { content: ''; position: absolute; top: 50%; left: 0; right: 0; height: 3px; background: #e0e0e0; z-index: 1; transform: translateY(-50%); }
        .tracker-step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; width: 100px; }
        .tracker-circle { width: 30px; height: 30px; border-radius: 50%; background: #fff; border: 3px solid #e0e0e0; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; color: #aaa; transition: all 0.3s; margin-bottom: 8px; }
        .tracker-label { font-size: 11px; font-weight: 600; color: #888; text-align: center; line-height: 1.2; text-transform: uppercase; letter-spacing: 0.5px; }
        
        /* Completed Step */
        .tracker-step.completed .tracker-circle { background: #28a745; border-color: #28a745; color: white; }
        .tracker-step.completed .tracker-label { color: #28a745; }
        /* Current Step */
        .tracker-step.current .tracker-circle { border-color: <?php echo $themeColor; ?>; color: <?php echo $themeColor; ?>; box-shadow: 0 0 0 5px rgba(119, 221, 119, 0.2); animation: pulse 2s infinite; }
        .tracker-step.current .tracker-label { color: <?php echo $themeColor; ?>; font-weight: 700; }
        /* Error/Rejected/Withdrawn Step */
        .tracker-step.error .tracker-circle { background: #dc3545; border-color: #dc3545; color: white; }
        .tracker-step.error .tracker-label { color: #dc3545; }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(119, 221, 119, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(119, 221, 119, 0); }
            100% { box-shadow: 0 0 0 0 rgba(119, 221, 119, 0); }
        }

        /* History Table */
        .history-section { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 25px; margin-top: 40px; }
        .history-section h2 { margin-top: 0; margin-bottom: 20px; font-size: 20px; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f8f9fa; padding: 12px 15px; text-align: left; font-size: 13px; color: #555; font-weight: 700; border-bottom: 2px solid #eee; text-transform: uppercase; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #444; }
        tr:hover { background-color: #fafffe; }
        
        .mini-badge { padding: 3px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header"><h2>Admin Panel</h2></div>
    <div class="menu-label">PENGURUSAN PELAJAR</div>
    <a href="senarai_pelajar.php">Senarai Pelajar</a>
    <a href="admin_enrollment.php">Pendaftaran &amp; Pengesahan</a>
    <a href="admin_document_verification.php">Pengesahan Dokumen</a>
    <a href="admin_enrollment_tracking.php" class="active">Status Pendaftaran</a>
    <a href="senarai_kehadiran.php">Kehadiran</a>
    <a href="arkib_pelajar.php">Arkib Pelajar</a>
    <div class="menu-label">KEWANGAN</div>
    <a href="admin_financial.php">Invois &amp; Pembayaran</a>
    <a href="admin_expense_report.php">Perbelanjaan &amp; Laporan</a>
    <div class="menu-label">AKADEMIK</div>
    <a href="aktiviti_kelas.php">Jadual Aktiviti</a>
    <a href="lesson_plan.php">Rancangan Pengajaran</a>
    <div class="menu-label">SISTEM</div>
    <a href="logout.php" style="color:#ff6961;">Log Keluar</a>
</div>

<!-- Content -->
<div class="content">
    <h1>📈 Status Pendaftaran</h1>
    <p class="subtitle">Jejak status permohonan dan pendaftaran pelajar dengan paparan visual.</p>

    <!-- Stats -->
    <div class="stat-row">
        <div class="stat-card stat-pending">
            <div class="number"><?php echo $stat_pending; ?></div>
            <div class="label">Permohonan Baru</div>
        </div>
        <div class="stat-card stat-waitlist">
            <div class="number"><?php echo $stat_waitlist; ?></div>
            <div class="label">Senarai Tunggu</div>
        </div>
        <div class="stat-card stat-active-bg">
            <div class="number"><?php echo $stat_active; ?></div>
            <div class="label">Pelajar Aktif</div>
        </div>
        <div class="stat-card stat-graduated">
            <div class="number"><?php echo $stat_graduated; ?></div>
            <div class="label">Telah Graduasi</div>
        </div>
        <div class="stat-card stat-withdrawn">
            <div class="number"><?php echo $stat_withdrawn; ?></div>
            <div class="label">Berhenti/Ditolak</div>
        </div>
    </div>

    <!-- Filter -->
    <form method="GET" class="filter-bar">
        <input type="text" name="name" placeholder="Cari nama pelajar..." value="<?php echo htmlspecialchars($filter_name); ?>" style="flex:1; min-width:200px;">
        <select name="module">
            <option value="">Semua Modul</option>
            <option value="Taska" <?php if($filter_module=='Taska') echo 'selected'; ?>>Taska</option>
            <option value="Tadika" <?php if($filter_module=='Tadika') echo 'selected'; ?>>Tadika</option>
            <option value="KAFA Care" <?php if($filter_module=='KAFA Care') echo 'selected'; ?>>KAFA Care</option>
        </select>
        <select name="status">
            <option value="">Semua Status</option>
            <option value="Pending" <?php if($filter_status=='Pending') echo 'selected'; ?>>Pending (Baru)</option>
            <option value="Waitlist" <?php if($filter_status=='Waitlist') echo 'selected'; ?>>Senarai Tunggu</option>
            <option value="Active" <?php if($filter_status=='Active') echo 'selected'; ?>>Aktif</option>
            <option value="Graduated" <?php if($filter_status=='Graduated') echo 'selected'; ?>>Graduasi</option>
            <option value="Withdrawn" <?php if($filter_status=='Withdrawn') echo 'selected'; ?>>Berhenti</option>
        </select>
        <button type="submit" class="btn-search">🔍 Cari</button>
    </form>

    <!-- Student Cards -->
    <?php if ($students_result && $students_result->num_rows > 0): ?>
        <?php while ($student = $students_result->fetch_assoc()): 
            $modSlug = strtolower(str_replace(' ', '', $student['module']));
            $tagClass = 'tag-' . $modSlug;
            
            $status = $student['status'];
            $docs_verified = $student['documents_verified'];
            $in_waitlist = !empty($student['waitlist_id']);
            
            $badgeClass = 'badge-' . strtolower($status);
            $badgeText = $status;
            if ($in_waitlist && $status == 'Pending') {
                $badgeClass = 'badge-waitlist';
                $badgeText = 'Senarai Tunggu';
            }

            // Determine current step
            $currentStep = 1;
            if ($status == 'Pending') {
                if ($in_waitlist) {
                    $currentStep = 4;
                } elseif ($docs_verified == 1) {
                    $currentStep = 3;
                } else {
                    $currentStep = 2; // Waiting for docs to be verified
                }
            } elseif ($status == 'Active') {
                $currentStep = 6;
            } elseif ($status == 'Graduated' || $status == 'Withdrawn') {
                $currentStep = 0; // Show special end state
            }
        ?>
        <div class="student-card">
            <div class="student-header">
                <div class="student-info">
                    <h3>
                        <?php echo htmlspecialchars($student['full_name']); ?>
                        <span class="module-tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($student['module']); ?></span>
                    </h3>
                    <p>Ibu Bapa: <?php echo htmlspecialchars($student['parent_full_name'] ?? $student['parent_name'] ?? '-'); ?> | Tel: <?php echo htmlspecialchars($student['parent_phone'] ?? '-'); ?></p>
                    <p>Tarikh Daftar: <?php echo !empty($student['created_at']) ? date('d-m-Y', strtotime($student['created_at'])) : '-'; ?></p>
                </div>
                <div>
                    <span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($badgeText); ?></span>
                </div>
            </div>

            <?php if ($currentStep > 0): ?>
            <!-- Visual Tracker -->
            <div class="tracker">
                <div class="tracker-step <?php echo $currentStep >= 1 ? ($currentStep == 1 ? 'current' : 'completed') : ''; ?>">
                    <div class="tracker-circle"><?php echo $currentStep > 1 ? '✔' : '1'; ?></div>
                    <div class="tracker-label">Daftar<br>Online</div>
                </div>
                <div class="tracker-step <?php echo $currentStep >= 2 ? ($currentStep == 2 ? 'current' : 'completed') : ''; ?>">
                    <div class="tracker-circle"><?php echo $currentStep > 2 ? '✔' : '2'; ?></div>
                    <div class="tracker-label">Hantar<br>Dokumen</div>
                </div>
                <div class="tracker-step <?php echo $currentStep >= 3 ? ($currentStep == 3 ? 'current' : 'completed') : ''; ?>">
                    <div class="tracker-circle"><?php echo $currentStep > 3 ? '✔' : '3'; ?></div>
                    <div class="tracker-label">Pengesahan<br>Dokumen</div>
                </div>
                <div class="tracker-step <?php echo $currentStep >= 4 ? ($currentStep == 4 ? 'current' : 'completed') : ''; ?>">
                    <div class="tracker-circle"><?php echo $currentStep > 4 ? '✔' : '4'; ?></div>
                    <div class="tracker-label">Senarai<br>Tunggu</div>
                </div>
                <div class="tracker-step <?php echo $currentStep >= 5 ? ($currentStep == 5 ? 'current' : 'completed') : ''; ?>">
                    <div class="tracker-circle"><?php echo $currentStep > 5 ? '✔' : '5'; ?></div>
                    <div class="tracker-label">Diluluskan</div>
                </div>
                <div class="tracker-step <?php echo $currentStep == 6 ? 'completed' : ''; ?>">
                    <div class="tracker-circle"><?php echo $currentStep == 6 ? '✔' : '6'; ?></div>
                    <div class="tracker-label">Pelajar<br>Aktif</div>
                </div>
            </div>
            <?php else: ?>
                <!-- Graduated/Withdrawn state -->
                <div style="text-align:center; padding: 20px; background:#f8f9fa; border-radius:8px;">
                    <div style="font-size:32px; margin-bottom:10px;">
                        <?php echo $status == 'Graduated' ? '🎓' : '🛑'; ?>
                    </div>
                    <p style="font-weight:bold; color: #555;">
                        Pelajar ini telah <?php echo $status == 'Graduated' ? 'bergraduasi' : 'berhenti / ditolak'; ?>.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 50px; background: white; border-radius: 12px; color: #888;">
            <div style="font-size: 40px; margin-bottom: 15px;">🔍</div>
            <p>Tiada rekod pendaftaran ditemui.</p>
        </div>
    <?php endif; ?>

    <!-- History Table -->
    <div class="history-section">
        <h2>Laporan Sejarah Perubahan Status</h2>
        <table>
            <thead>
                <tr>
                    <th>Tarikh & Masa</th>
                    <th>Nama Pelajar</th>
                    <th>Dari Status</th>
                    <th>Ke Status</th>
                    <th>Diubah Oleh</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($history_result && $history_result->num_rows > 0): ?>
                    <?php while ($h = $history_result->fetch_assoc()): 
                        $fromClass = 'badge-' . strtolower($h['from_status'] ?? 'pending');
                        $toClass = 'badge-' . strtolower($h['to_status']);
                    ?>
                    <tr>
                        <td><?php echo date('d-m-Y H:i', strtotime($h['changed_at'])); ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($h['student_name']); ?></td>
                        <td>
                            <?php if ($h['from_status']): ?>
                                <span class="mini-badge <?php echo $fromClass; ?>"><?php echo htmlspecialchars($h['from_status']); ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><span class="mini-badge <?php echo $toClass; ?>"><?php echo htmlspecialchars($h['to_status']); ?></span></td>
                        <td><?php echo htmlspecialchars($h['changed_by_name'] ?? 'Sistem'); ?></td>
                        <td><small><?php echo htmlspecialchars($h['notes'] ?? '-'); ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:30px; color:#aaa;">Tiada sejarah rekod ditemui.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
