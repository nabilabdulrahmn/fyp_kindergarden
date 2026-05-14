<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Fetch pending enrollments for admin
$pending_result = null;
$pending_users_count = 0;
if ($role == 'admin') {
    $sql_pending = "SELECT * FROM enrollments WHERE status = 'Pending'";
    $pending_result = mysqli_query($conn, $sql_pending);

    // Kira jumlah pengguna yang menunggu kelulusan
    $sql_pending_users = "SELECT COUNT(*) as total FROM users WHERE status = 'pending'";
    $res_pending_users = mysqli_query($conn, $sql_pending_users);
    $row_count = mysqli_fetch_assoc($res_pending_users);
    $pending_users_count = $row_count['total'];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - <?php echo ucfirst($role); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter','Segoe UI',sans-serif;margin:0;display:flex;background:#f0f4f3;height:100vh;overflow:hidden}

/* ===== SIDEBAR ===== */
.sidebar{width:260px;background:linear-gradient(180deg,#2e7d32 0%,#388e3c 40%,#43a047 100%);color:#fff;display:flex;flex-direction:column;box-shadow:4px 0 20px rgba(0,0,0,.1);position:relative;overflow:hidden}
.sidebar::before{content:'';position:absolute;top:-60px;right:-60px;width:180px;height:180px;background:rgba(255,255,255,.04);border-radius:50%}
.sidebar::after{content:'';position:absolute;bottom:-40px;left:-40px;width:120px;height:120px;background:rgba(255,255,255,.03);border-radius:50%}

.sidebar-brand{padding:28px 24px;text-align:center;border-bottom:1px solid rgba(255,255,255,.12)}
.sidebar-brand .brand-icon{font-size:36px;margin-bottom:8px;display:block}
.sidebar-brand h2{font-size:16px;font-weight:700;letter-spacing:.5px}
.sidebar-brand p{font-size:11px;opacity:.7;margin-top:4px}

.sidebar-nav{flex:1;padding:16px 0;overflow-y:auto}
.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:14px 24px;color:rgba(255,255,255,.85);text-decoration:none;font-size:14px;font-weight:500;transition:all .3s ease;border-left:3px solid transparent;position:relative;z-index:1}
.sidebar-nav a:hover{background:rgba(255,255,255,.12);color:#fff;padding-left:28px;border-left-color:#a5d6a7}
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

/* Cards */
.card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-bottom:32px}
.info-card{background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.04);border-left:5px solid #43a047;transition:transform .3s,box-shadow .3s}
.info-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
.info-card h3{font-size:15px;color:#37474f;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.info-card p{font-size:13px;color:#607d8b;line-height:1.7}

/* ===== PENDING TABLE ===== */
.table-section{background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.04)}
.table-section h2{font-size:18px;font-weight:700;color:#263238;margin-bottom:6px;display:flex;align-items:center;gap:10px}
.table-section .table-subtitle{font-size:13px;color:#90a4ae;margin-bottom:24px}

.pending-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
.pending-table thead th{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);color:#2e7d32;padding:14px 16px;text-align:left;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.5px}
.pending-table thead th:first-child{border-radius:10px 0 0 0}
.pending-table thead th:last-child{border-radius:0 10px 0 0}
.pending-table tbody tr{transition:background .2s}
.pending-table tbody tr:hover{background:#f1f8e9}
.pending-table tbody td{padding:14px 16px;border-bottom:1px solid #f0f0f0;color:#455a64}
.pending-table tbody tr:last-child td{border-bottom:none}

.status-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.3px}
.status-badge.pending{background:#fff3e0;color:#e65100}
.status-badge.approved{background:#e8f5e9;color:#2e7d32}

.btn-approve{background:linear-gradient(135deg,#43a047,#66bb6a);color:#fff;border:none;padding:9px 20px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;transition:all .3s;font-family:'Inter',sans-serif}
.btn-approve:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(67,160,71,.4)}
.btn-approve:active{transform:translateY(0)}

.empty-state{text-align:center;padding:48px 20px;color:#90a4ae}
.empty-state .empty-icon{font-size:48px;margin-bottom:12px;display:block}
.empty-state p{font-size:14px}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">🏫</span>
        <h2>Panel <?php echo ucfirst($role); ?></h2>
        <p>Sistem Pengurusan Kanak-Kanak</p>
    </div>

    <div class="sidebar-nav">
        <?php if ($role == 'parent'): ?>
            <a href="daftar_anak.php"><span class="nav-emoji">📝</span> Pendaftaran Pelajar</a>
            <a href="yuran.php"><span class="nav-emoji">💳</span> Pengurusan Yuran</a>
            <a href="kehadiran.php"><span class="nav-emoji">📊</span> Prestasi & Kehadiran</a>
            <a href="upload_mc.php"><span class="nav-emoji">🩺</span> Muat Naik MC</a>

        <?php elseif ($role == 'teacher'): ?>
            <a href="ambil_kehadiran.php"><span class="nav-emoji">📅</span> Ambil Kehadiran</a>
            <a href="perkembangan.php"><span class="nav-emoji">📈</span> Perkembangan Pelajar</a>
            <a href="homework.php"><span class="nav-emoji">📚</span> Kerja Sekolah</a>
            <a href="report_card.php"><span class="nav-emoji">🎓</span> Kad Laporan</a>
            <a href="staff_mc.php"><span class="nav-emoji">🩺</span> Muat Naik MC (Guru)</a>

        <?php elseif ($role == 'admin'): ?>
            <a href="home.php"><span class="nav-emoji">📋</span> Kelulusan Pendaftaran Anak</a>
            <a href="lulus_pendaftaran.php"><span class="nav-emoji">👤</span> Kelulusan Akaun Pengguna</a>
            <div class="nav-divider"></div>
            <a href="sys_monitor.php"><span class="nav-emoji">🖥️</span> Pemantauan Sistem</a>
            <a href="manage_users.php"><span class="nav-emoji">👥</span> Urus Pengguna</a>
            <a href="sys_config.php"><span class="nav-emoji">⚙️</span> Konfigurasi</a>
            <a href="sys_logs.php"><span class="nav-emoji">📁</span> Log Sistem</a>
            <a href="backup.php"><span class="nav-emoji">💾</span> Backup & Restore</a>
        <?php endif; ?>
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
            <h1>Selamat Datang, <?php echo htmlspecialchars($username); ?>!</h1>
            <p>Anda sedang log masuk sebagai <strong><?php echo ucfirst($role); ?></strong></p>
        </div>
    </div>

    <div class="main-area">

        <!-- Info Cards -->
        <div class="card-grid">
            <div class="info-card">
                <h3>📌 Makluman Terkini</h3>
                <?php if ($role == 'parent'): ?>
                    <p>Sila pastikan profil pendaftaran anak anda telah dilengkapkan. Semak invois yuran anda di tab 'Pengurusan Yuran'.</p>
                <?php elseif ($role == 'teacher'): ?>
                    <p>Sila kemas kini kehadiran pelajar selewat-lewatnya jam 10:00 pagi setiap hari bertugas.</p>
                <?php elseif ($role == 'admin'): ?>
                    <p>Sila semak senarai pendaftaran yang menunggu kelulusan di bawah. Klik "Luluskan" untuk meluluskan permohonan.</p>
                    <?php if ($pending_users_count > 0): ?>
                    <p style="margin-top:8px;color:#e65100;font-weight:600">⚠️ <?php echo $pending_users_count; ?> akaun pengguna baru menunggu kelulusan. <a href="lulus_pendaftaran.php" style="color:#2e7d32">Semak sekarang →</a></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="info-card">
                <h3>📊 Status Ringkas</h3>
                <p>Modul ini akan memaparkan ringkasan data seperti jumlah tunggakan, peratusan kehadiran, atau log sistem terkini.</p>
            </div>
        </div>

        <!-- ADMIN: Pending Approvals Table -->
        <?php if ($role == 'admin'): ?>
        <div class="table-section">
            <h2>📋 Senarai Pendaftaran Menunggu Kelulusan</h2>
            <p class="table-subtitle">Semak dan luluskan permohonan pendaftaran daripada ibu bapa.</p>

            <?php if ($pending_result && $pending_result->num_rows > 0): ?>
            <table class="pending-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Ibu Bapa</th>
                        <th>Nama Kanak-Kanak</th>
                        <th>Program</th>
                        <th>Status</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_assoc($pending_result)) {
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_ibubapa']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_anak']); ?></td>
                        <td><?php echo htmlspecialchars($row['program']); ?></td>
                        <td><span class="status-badge pending"><?php echo $row['status']; ?></span></td>
                        <td>
                            <form method="POST" action="lulus_pendaftaran.php" style="display:inline">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="luluskan" class="btn-approve">✅ Luluskan</button>
                            </form>
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
        <?php endif; ?>

    </div>
</div>

</body>
</html>