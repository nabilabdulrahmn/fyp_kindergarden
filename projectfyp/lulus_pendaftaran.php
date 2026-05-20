<?php
// lulus_pendaftaran.php
// Halaman Kelulusan Pendaftaran Pengguna Baru (Admin Sahaja)
session_start();
include 'db.php';

// Pastikan hanya admin yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];
$msg = '';
$msg_type = '';

// --- PROSES LULUSKAN ---
if (isset($_GET['approve'])) {
    $id = $_GET['approve'];
    $sql = "UPDATE users SET status = 'approved' WHERE id = '$id'";
    if (mysqli_query($conn, $sql)) {
        $msg = 'Akaun telah diluluskan!';
        $msg_type = 'success';
    } else {
        $msg = 'Ralat semasa meluluskan: ' . mysqli_error($conn);
        $msg_type = 'error';
    }
}

// --- PROSES TOLAK ---
if (isset($_GET['reject'])) {
    $id = $_GET['reject'];
    $sql = "UPDATE users SET status = 'rejected' WHERE id = '$id'";
    if (mysqli_query($conn, $sql)) {
        $msg = 'Akaun telah ditolak.';
        $msg_type = 'warning';
    } else {
        $msg = 'Ralat semasa menolak: ' . mysqli_error($conn);
        $msg_type = 'error';
    }
}

// --- PROSES PADAM ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM users WHERE id = '$id' AND status = 'rejected'";
    if (mysqli_query($conn, $sql)) {
        $msg = 'Rekod pengguna telah dipadam.';
        $msg_type = 'warning';
    } else {
        $msg = 'Ralat semasa memadam: ' . mysqli_error($conn);
        $msg_type = 'error';
    }
}

// Ambil senarai pengguna pending
$sql_pending = "SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC";
$result_pending = mysqli_query($conn, $sql_pending);
$count_pending = mysqli_num_rows($result_pending);

// Ambil senarai pengguna yang ditolak
$sql_rejected = "SELECT * FROM users WHERE status = 'rejected' ORDER BY created_at DESC";
$result_rejected = mysqli_query($conn, $sql_rejected);
$count_rejected = mysqli_num_rows($result_rejected);

// Ambil senarai pengguna yang diluluskan (terkini sahaja)
$sql_approved = "SELECT * FROM users WHERE status = 'approved' ORDER BY created_at DESC";
$result_approved = mysqli_query($conn, $sql_approved);
$count_approved = mysqli_num_rows($result_approved);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelulusan Pendaftaran - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter','Segoe UI',sans-serif;margin:0;display:flex;background:#f0f4f3;height:100vh;overflow:hidden}

/* ===== SIDEBAR ===== */
.sidebar{width:260px;background:linear-gradient(180deg,#2e7d32 0%,#388e3c 40%,#43a047 100%);color:#fff;display:flex;flex-direction:column;box-shadow:4px 0 20px rgba(0,0,0,.1);position:relative;overflow:hidden;flex-shrink:0}
.sidebar::before{content:'';position:absolute;top:-60px;right:-60px;width:180px;height:180px;background:rgba(255,255,255,.04);border-radius:50%}
.sidebar::after{content:'';position:absolute;bottom:-40px;left:-40px;width:120px;height:120px;background:rgba(255,255,255,.03);border-radius:50%}

.sidebar-brand{padding:28px 24px;text-align:center;border-bottom:1px solid rgba(255,255,255,.12)}
.sidebar-brand .brand-icon{font-size:36px;margin-bottom:8px;display:block}
.sidebar-brand h2{font-size:16px;font-weight:700;letter-spacing:.5px}
.sidebar-brand p{font-size:11px;opacity:.7;margin-top:4px}

.sidebar-nav{flex:1;padding:16px 0;overflow-y:auto}
.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:14px 24px;color:rgba(255,255,255,.85);text-decoration:none;font-size:14px;font-weight:500;transition:all .3s ease;border-left:3px solid transparent;position:relative;z-index:1}
.sidebar-nav a:hover,.sidebar-nav a.active{background:rgba(255,255,255,.12);color:#fff;padding-left:28px;border-left-color:#a5d6a7}
.sidebar-nav a .nav-emoji{font-size:18px;min-width:24px;text-align:center}
.sidebar-nav .nav-divider{height:1px;background:rgba(255,255,255,.08);margin:8px 24px}

.sidebar-logout{padding:16px 24px;border-top:1px solid rgba(255,255,255,.12)}
.sidebar-logout a{display:block;text-align:center;background:rgba(255,255,255,.12);color:#fff;padding:12px;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px;transition:all .3s}
.sidebar-logout a:hover{background:#c62828}

/* ===== MAIN CONTENT ===== */
.content{flex:1;display:flex;flex-direction:column;overflow:hidden}

.top-bar{background:#fff;padding:20px 36px;display:flex;align-items:center;gap:16px;box-shadow:0 2px 8px rgba(0,0,0,.04);z-index:10}
.profile-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#2e7d32,#66bb6a);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;flex-shrink:0}
.top-bar h1{font-size:20px;color:#263238;font-weight:700}
.top-bar p{font-size:13px;color:#78909c;margin-top:2px}

.main-area{flex:1;overflow-y:auto;padding:32px 36px}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:28px}
.stat-card{background:#fff;border-radius:14px;padding:22px 24px;box-shadow:0 2px 12px rgba(0,0,0,.04);display:flex;align-items:center;gap:16px;transition:transform .3s,box-shadow .3s}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
.stat-icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.stat-icon.pending-bg{background:linear-gradient(135deg,#fff3e0,#ffe0b2)}
.stat-icon.approved-bg{background:linear-gradient(135deg,#e8f5e9,#c8e6c9)}
.stat-icon.rejected-bg{background:linear-gradient(135deg,#fce4ec,#f8bbd0)}
.stat-info h3{font-size:26px;color:#263238;font-weight:700;line-height:1}
.stat-info p{font-size:12px;color:#90a4ae;margin-top:4px;font-weight:500;text-transform:uppercase;letter-spacing:.3px}

/* Alert */
.alert{padding:14px 20px;border-radius:10px;margin-bottom:24px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px;animation:slideDown .4s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.alert.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
.alert.error{background:#fce4ec;color:#c62828;border:1px solid #ef9a9a}
.alert.warning{background:#fff3e0;color:#e65100;border:1px solid #ffcc80}

/* Table Section */
.table-section{background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.04);margin-bottom:28px}
.table-section h2{font-size:18px;font-weight:700;color:#263238;margin-bottom:6px;display:flex;align-items:center;gap:10px}
.table-section .table-subtitle{font-size:13px;color:#90a4ae;margin-bottom:24px}

.data-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
.data-table thead th{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);color:#2e7d32;padding:14px 16px;text-align:left;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.5px}
.data-table thead th:first-child{border-radius:10px 0 0 0}
.data-table thead th:last-child{border-radius:0 10px 0 0}
.data-table tbody tr{transition:background .2s}
.data-table tbody tr:hover{background:#f1f8e9}
.data-table tbody td{padding:14px 16px;border-bottom:1px solid #f0f0f0;color:#455a64}
.data-table tbody tr:last-child td{border-bottom:none}

/* Status Badges */
.badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.3px}
.badge.pending{background:#fff3e0;color:#e65100}
.badge.approved{background:#e8f5e9;color:#2e7d32}
.badge.rejected{background:#fce4ec;color:#c62828}

/* Action Buttons */
.btn{display:inline-block;padding:8px 18px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;transition:all .3s;font-family:'Inter',sans-serif;border:none}
.btn-approve{background:linear-gradient(135deg,#43a047,#66bb6a);color:#fff}
.btn-approve:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(67,160,71,.4)}
.btn-reject{background:linear-gradient(135deg,#e53935,#ef5350);color:#fff}
.btn-reject:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(229,57,53,.4)}
.btn-delete{background:linear-gradient(135deg,#757575,#9e9e9e);color:#fff;padding:6px 14px;font-size:11px}
.btn-delete:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(117,117,117,.4)}

.action-group{display:flex;gap:8px;align-items:center}

/* Empty State */
.empty-state{text-align:center;padding:48px 20px;color:#90a4ae}
.empty-state .empty-icon{font-size:48px;margin-bottom:12px;display:block}
.empty-state p{font-size:14px}

/* Back Link */
.back-link{display:inline-flex;align-items:center;gap:6px;color:#43a047;text-decoration:none;font-size:13px;font-weight:600;margin-bottom:24px;transition:color .2s}
.back-link:hover{color:#2e7d32}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">🏫</span>
        <h2>Panel Admin</h2>
        <p>Sistem Pengurusan Kanak-Kanak</p>
    </div>

    <div class="sidebar-nav">
        <a href="home.php"><span class="nav-emoji">🏠</span> Laman Utama</a>
        <a href="lulus_pendaftaran.php" class="active"><span class="nav-emoji">📋</span> Kelulusan Pendaftaran</a>
        <div class="nav-divider"></div>
        <a href="sys_monitor.php"><span class="nav-emoji">🖥️</span> Pemantauan Sistem</a>
        <a href="manage_users.php"><span class="nav-emoji">👥</span> Urus Pengguna</a>
        <a href="sys_config.php"><span class="nav-emoji">⚙️</span> Konfigurasi</a>
        <a href="sys_logs.php"><span class="nav-emoji">📁</span> Log Sistem</a>
        <a href="backup.php"><span class="nav-emoji">💾</span> Backup & Restore</a>
    </div>

    <div class="sidebar-logout">
        <a href="logout.php">🚪 Keluar Sistem</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="content">

    <div class="top-bar">
        <div class="profile-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
        <div>
            <h1>Kelulusan Pendaftaran Pengguna</h1>
            <p>Semak dan luluskan akaun pengguna baru</p>
        </div>
    </div>

    <div class="main-area">

        <a href="home.php" class="back-link">← Kembali ke Dashboard</a>

        <?php if ($msg != ''): ?>
        <div class="alert <?php echo $msg_type; ?>">
            <?php
            if ($msg_type == 'success') { echo '✅'; }
            else if ($msg_type == 'error') { echo '❌'; }
            else { echo '⚠️'; }
            ?>
            <?php echo $msg; ?>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon pending-bg">⏳</div>
                <div class="stat-info">
                    <h3><?php echo $count_pending; ?></h3>
                    <p>Menunggu Kelulusan</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon approved-bg">✅</div>
                <div class="stat-info">
                    <h3><?php echo $count_approved; ?></h3>
                    <p>Telah Diluluskan</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon rejected-bg">❌</div>
                <div class="stat-info">
                    <h3><?php echo $count_rejected; ?></h3>
                    <p>Ditolak</p>
                </div>
            </div>
        </div>

        <!-- PENDING TABLE -->
        <div class="table-section">
            <h2>⏳ Senarai Pendaftaran Menunggu Kelulusan</h2>
            <p class="table-subtitle">Klik "Luluskan" untuk mengaktifkan akaun pengguna, atau "Tolak" untuk menolak pendaftaran.</p>

            <?php if ($count_pending > 0): ?>
            <table class="data-table" id="pending-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Peranan</th>
                        <th>Tarikh Daftar</th>
                        <th>Status</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_assoc($result_pending)) {
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                        <td><?php echo ucfirst($row['role']); ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td><span class="badge pending">Pending</span></td>
                        <td>
                            <div class="action-group">
                                <a href="lulus_pendaftaran.php?approve=<?php echo $row['id']; ?>" class="btn btn-approve" onclick="return confirm('Luluskan akaun ini?')">✅ Luluskan</a>
                                <a href="lulus_pendaftaran.php?reject=<?php echo $row['id']; ?>" class="btn btn-reject" onclick="return confirm('Tolak akaun ini?')">❌ Tolak</a>
                            </div>
                        </td>
                    </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <span class="empty-icon">🎉</span>
                <p>Tiada pendaftaran yang menunggu kelulusan buat masa ini.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- REJECTED TABLE -->
        <?php if ($count_rejected > 0): ?>
        <div class="table-section">
            <h2>❌ Senarai Pendaftaran Ditolak</h2>
            <p class="table-subtitle">Akaun-akaun yang telah ditolak. Anda boleh padam rekod atau luluskan semula.</p>

            <table class="data-table" id="rejected-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Peranan</th>
                        <th>Tarikh Daftar</th>
                        <th>Status</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_assoc($result_rejected)) {
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                        <td><?php echo ucfirst($row['role']); ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td><span class="badge rejected">Ditolak</span></td>
                        <td>
                            <div class="action-group">
                                <a href="lulus_pendaftaran.php?approve=<?php echo $row['id']; ?>" class="btn btn-approve" onclick="return confirm('Luluskan semula akaun ini?')">✅ Luluskan</a>
                                <a href="lulus_pendaftaran.php?delete=<?php echo $row['id']; ?>" class="btn btn-delete" onclick="return confirm('PADAM rekod ini secara kekal?')">🗑️ Padam</a>
                            </div>
                        </td>
                    </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
