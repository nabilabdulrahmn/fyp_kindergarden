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
    
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "UPDATE users SET status = 'approved' WHERE id = '$id'");
        // If this user is a teacher/staff, activate staff row
        mysqli_query($conn, "UPDATE staff SET status = 'Active' WHERE user_id = '$id'");
        mysqli_commit($conn);
        $msg = 'Akaun telah diluluskan!';
        $msg_type = 'success';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $msg = 'Ralat semasa meluluskan: ' . mysqli_error($conn);
        $msg_type = 'error';
    }
}

// --- PROSES TOLAK ---
if (isset($_GET['reject'])) {
    $id = $_GET['reject'];
    
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "UPDATE users SET status = 'rejected' WHERE id = '$id'");
        mysqli_query($conn, "UPDATE staff SET status = 'Inactive' WHERE user_id = '$id'");
        mysqli_commit($conn);
        $msg = 'Akaun telah ditolak.';
        $msg_type = 'warning';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $msg = 'Ralat semasa menolak: ' . mysqli_error($conn);
        $msg_type = 'error';
    }
}

// --- PROSES PADAM ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "DELETE FROM users WHERE id = '$id' AND status = 'rejected'");
        mysqli_query($conn, "DELETE FROM staff WHERE user_id = '$id'");
        mysqli_commit($conn);
        $msg = 'Rekod pengguna telah dipadam.';
        $msg_type = 'warning';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $msg = 'Ralat semasa memadam: ' . mysqli_error($conn);
        $msg_type = 'error';
    }
}

// Ambil senarai pengguna pending
$sql_pending = "SELECT u.*, 
                       COALESCE(p.full_name, t.full_name) AS full_name,
                       COALESCE(p.phone_number, t.phone_number) AS phone_number,
                       COALESCE(p.ic_number, t.ic_number) AS ic_number,
                       COALESCE(p.address, t.address) AS address,
                       COALESCE(p.race, t.race) AS race,
                       COALESCE(p.age, t.age) AS age,
                       COALESCE(p.email, t.email, u.email) AS detail_email
                FROM users u
                LEFT JOIN parents p ON p.user_id = u.id
                LEFT JOIN teachers t ON t.user_id = u.id
                WHERE u.status = 'pending' 
                ORDER BY u.created_at DESC";
$result_pending = mysqli_query($conn, $sql_pending);
$count_pending = mysqli_num_rows($result_pending);

// Ambil senarai pengguna yang ditolak
$sql_rejected = "SELECT u.*, 
                        COALESCE(p.full_name, t.full_name) AS full_name,
                        COALESCE(p.phone_number, t.phone_number) AS phone_number,
                        COALESCE(p.ic_number, t.ic_number) AS ic_number,
                        COALESCE(p.address, t.address) AS address,
                        COALESCE(p.race, t.race) AS race,
                        COALESCE(p.age, t.age) AS age,
                        COALESCE(p.email, t.email, u.email) AS detail_email
                 FROM users u
                 LEFT JOIN parents p ON p.user_id = u.id
                 LEFT JOIN teachers t ON t.user_id = u.id
                 WHERE u.status = 'rejected' 
                 ORDER BY u.created_at DESC";
$result_rejected = mysqli_query($conn, $sql_rejected);
$count_rejected = mysqli_num_rows($result_rejected);

// Ambil senarai pengguna yang diluluskan (terkini sahaja)
$sql_approved = "SELECT u.*, 
                        COALESCE(p.full_name, t.full_name) AS full_name,
                        COALESCE(p.phone_number, t.phone_number) AS phone_number,
                        COALESCE(p.ic_number, t.ic_number) AS ic_number,
                        COALESCE(p.address, t.address) AS address,
                        COALESCE(p.race, t.race) AS race,
                        COALESCE(p.age, t.age) AS age,
                        COALESCE(p.email, t.email, u.email) AS detail_email
                 FROM users u
                 LEFT JOIN parents p ON p.user_id = u.id
                 LEFT JOIN teachers t ON t.user_id = u.id
                 WHERE u.status = 'approved' 
                 ORDER BY u.created_at DESC";
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
body{font-family:'Inter','Segoe UI',sans-serif;margin:0;background:#f0f4f3;min-height:100vh;padding:30px 20px;}

.container{background:white;padding:30px;border-radius:12px;box-shadow:0 5px 20px rgba(0,0,0,0.05);max-width:1000px;margin:auto;border-top:8px solid #2e7d32;}

.profile-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#2e7d32,#66bb6a);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;flex-shrink:0}

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

<div class="container">
    
    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 20px;">
        <div class="profile-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
        <div>
            <h2 style="font-size: 22px; font-weight: 700; color: #263238; border: none; padding: 0; margin: 0;">Kelulusan Pendaftaran Pengguna</h2>
            <p style="font-size: 13px; color: #78909c; margin-top: 4px;">Semak dan luluskan akaun pengguna baru</p>
        </div>
    </div>

    <a href="home.php" class="back-link">← Kembali ke Dashboard Utama</a>

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
                        <th>Akaun / Peranan</th>
                        <th>Maklumat Peribadi</th>
                        <th>Profil</th>
                        <th>Alamat Rumah</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_assoc($result_pending)) {
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['username']); ?></strong><br>
                            <span class="badge pending"><?php echo ucfirst($row['role']); ?></span><br>
                            <span style="font-size:11px; color:#999;"><?php echo $row['created_at']; ?></span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></strong><br>
                            <span style="font-size:11px; color:#666;">IC: <?php echo htmlspecialchars($row['ic_number'] ?? '-'); ?></span><br>
                            <span style="font-size:11px; color:#666;">Tel: <?php echo htmlspecialchars($row['phone_number'] ?? '-'); ?></span>
                        </td>
                        <td>
                            <span style="font-size:12px;">📧 <?php echo htmlspecialchars($row['detail_email'] ?? '-'); ?></span><br>
                            <span style="font-size:11px; color:#666;">Bangsa: <?php echo htmlspecialchars($row['race'] ?? '-'); ?></span><br>
                            <span style="font-size:11px; color:#666;">Umur: <?php echo htmlspecialchars($row['age'] ?? '-'); ?> tahun</span>
                        </td>
                        <td style="max-width:220px; font-size:12px; word-wrap:break-word; white-space:normal;"><?php echo nl2br(htmlspecialchars($row['address'] ?? '-')); ?></td>
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
                        <th>Akaun / Peranan</th>
                        <th>Maklumat Peribadi</th>
                        <th>Profil</th>
                        <th>Alamat Rumah</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_assoc($result_rejected)) {
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['username']); ?></strong><br>
                            <span class="badge rejected"><?php echo ucfirst($row['role']); ?></span><br>
                            <span style="font-size:11px; color:#999;"><?php echo $row['created_at']; ?></span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></strong><br>
                            <span style="font-size:11px; color:#666;">IC: <?php echo htmlspecialchars($row['ic_number'] ?? '-'); ?></span><br>
                            <span style="font-size:11px; color:#666;">Tel: <?php echo htmlspecialchars($row['phone_number'] ?? '-'); ?></span>
                        </td>
                        <td>
                            <span style="font-size:12px;">📧 <?php echo htmlspecialchars($row['detail_email'] ?? '-'); ?></span><br>
                            <span style="font-size:11px; color:#666;">Bangsa: <?php echo htmlspecialchars($row['race'] ?? '-'); ?></span><br>
                            <span style="font-size:11px; color:#666;">Umur: <?php echo htmlspecialchars($row['age'] ?? '-'); ?> tahun</span>
                        </td>
                        <td style="max-width:220px; font-size:12px; word-wrap:break-word; white-space:normal;"><?php echo nl2br(htmlspecialchars($row['address'] ?? '-')); ?></td>
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
</body>
</html>
