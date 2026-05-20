<?php
// home.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Tentukan warna tema ikut role
$themeColor = '#84b6f4'; // Biru (Parent)
if ($role == 'teacher') $themeColor = '#ffb347'; // Oren Pastel (Teacher)
if ($role == 'admin') $themeColor = '#77dd77'; // Hijau Pastel (Admin)
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?php echo ucfirst($role); ?></title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            display: flex;
            background-color: #f4f7f6;
            height: 100vh;
        }
        /* Sidebar Kiri */
        .sidebar {
            width: 270px; /* Lebarkan sikit untuk teks panjang */
            background-color: <?php echo $themeColor; ?>;
            color: white; padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            display: flex; flex-direction: column;
            overflow-y: auto; /* Tambah scroll kalau menu banyak */
        }
        .sidebar h2 { text-align: center; margin-bottom: 20px; font-size: 22px; padding: 0 10px; }
        .sidebar a {
            padding: 12px 20px; text-decoration: none; font-size: 14px;
            color: white; display: block; border-bottom: 1px solid rgba(255,255,255,0.2);
            transition: 0.3s;
        }
        .sidebar a:hover { background-color: rgba(255,255,255,0.2); padding-left: 25px; }
        .sidebar .logout-btn { margin-top: auto; background-color: #ff6961; text-align: center; font-weight: bold; border-bottom: none; }
        .sidebar .logout-btn:hover { background-color: #ff4c4c; padding-left: 20px;}
        
        /* Label Kategori Menu */
        .menu-label { padding: 15px 20px 5px; font-size: 11px; color: rgba(255,255,255,0.8); font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }

        /* Content Kanan */
        .content { flex: 1; padding: 40px; overflow-y: auto; }
        .content-header { background: white; padding: 20px 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .profile-icon { width: 50px; height: 50px; background-color: <?php echo $themeColor; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: bold; }
        .card-container { display: flex; gap: 20px; flex-wrap: wrap; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); flex: 1; min-width: 250px; border-left: 5px solid <?php echo $themeColor; ?>; }
        .card h3 { margin-top: 0; color: #333; }
        .card p { color: #666; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>Panel <?php echo ucfirst($role); ?></h2>
        
        <?php if ($role == 'parent'): ?>
            <div class="menu-label">Anak Saya</div>
            <a href="profil_anak.php">👧 Profil Anak Saya</a>
            <a href="sejarah_kehadiran.php">📅 Sejarah Kehadiran</a>
            <a href="laporan_harian.php">📝 Laporan Aktiviti Harian</a>
            
            <div class="menu-label">Akademik & Perkembangan</div>
            <a href="milestone.php">📈 Pencapaian & Perkembangan</a>
            <a href="report_card_anak.php">🎓 Kad Laporan & Ulasan</a>

            <div class="menu-label">Komunikasi</div>
            <a href="parent_inbox.php">💬 Peti Masuk</a>
            <a href="parent_calendar.php">📆 Kalendar Sekolah & RSVP</a>
            <a href="parent_announcements.php">📢 Pengumuman</a>

            <div class="menu-label">Kewangan</div>
            <a href="parent_payments.php">💳 Pembayaran & Invois</a>
            <a href="parent_payment_history.php">🧾 Sejarah Pembayaran</a>
            <a href="daftar_anak.php">📋 Pendaftaran Adik-Beradik</a>

            <div class="menu-label">Keselamatan & Pengangkutan</div>
            <a href="parent_bus_tracking.php">🚌 Jejak Bas Langsung</a>
            <a href="parent_checkin_log.php">🔐 Log Daftar Masuk/Keluar</a>
            <a href="parent_guardians.php">👤 Penjaga Sah</a>

        <?php elseif ($role == 'teacher'): ?>
            <div class="menu-label">Pengurusan Kelas</div>
            <a href="ambil_kehadiran.php">📅 Kehadiran Harian</a>
            <a href="maklumat_pelajar_lengkap.php">🩺 Profil Pelajar & Kesihatan</a>
            <a href="lesson_plan.php">📚 Rancangan Mengajar</a>
            <a href="aktiviti_kelas.php">🎨 Jadual Aktiviti</a>
            
            <div class="menu-label">Perkembangan Pelajar</div>
            <a href="perkembangan.php">📈 Perkembangan Kanak-kanak</a>
            <a href="report_card.php">🎓 Prestasi Akademik & Kad Laporan</a>

            <div class="menu-label">Komunikasi</div>
            <a href="teacher_daily_report.php">📝 Laporan Aktiviti Harian</a>
            <a href="teacher_announcements.php">📢 Pengumuman Kelas</a>
            <a href="teacher_inbox.php">💬 Mesej & Maklum Balas</a>

            <div class="menu-label">Operasi</div>
            <a href="teacher_inventory_request.php">📦 Permohonan Inventori</a>
            <a href="teacher_facility_request.php">🔧 Aduan Kerosakan Fasiliti</a>
            <a href="teacher_meal_plan.php">🍽️ Pelan Pemakanan & Alahan</a>
            <a href="teacher_bus_roster.php">🚌 Jadual Bas & Kepulangan</a>

            <div class="menu-label">Keselamatan</div>
            <a href="teacher_checkin.php">🔐 Pengesahan Daftar Masuk/Keluar</a>

        <?php elseif ($role == 'admin'): ?>
            <div class="menu-label">Akademik & Pelajar</div>
            <a href="maklumat_pelajar_lengkap.php">📁 Direktori Pelajar & Kesihatan</a>
            <a href="senarai_kehadiran.php">📊 Rekod Kehadiran Keseluruhan</a>
            <a href="arkib_pelajar.php">🗄️ Arkib & Alumni</a>
            <a href="jadual_aktiviti.php">🗓️ Jadual Aktiviti Induk</a>
            <a href="rancangan_mengajar.php">📚 Semakan Rancangan Mengajar</a>
            <a href="laporan_akademik.php">📈 Laporan Prestasi Akademik</a>

            <div class="menu-label">Komunikasi</div>
            <a href="admin_announcements.php">📢 Pengumuman Sekolah</a>
            <a href="admin_calendar.php">📆 Pengurusan Kalendar</a>
            <a href="admin_inbox.php">💬 Peti Masuk & Maklum Balas</a>

            <div class="menu-label">Kewangan & Pendaftaran</div>
            <a href="senarai_pelajar.php">🎓 Permohonan Baru & Senarai Menunggu</a>
            <a href="admin_doc_verify.php">📄 Pengesahan Dokumen</a>
            <a href="admin_enrollment.php">📋 Status Pendaftaran</a>
            <a href="admin_fees.php">💰 Pemantauan Yuran</a>
            <a href="admin_invoices.php">🧾 Penjanaan Invois</a>
            <a href="admin_expenses.php">📊 Laporan Perbelanjaan</a>

            <div class="menu-label">Operasi & Sumber</div>
            <a href="admin_staff.php">👥 Direktori Staf</a>
            <a href="admin_payroll.php">💼 Penggajian (Payroll)</a>
            <a href="admin_transport.php">🚌 Pengangkutan & Laluan</a>
            <a href="admin_meal_plans.php">🍽️ Perancangan Pemakanan</a>
            <a href="admin_inventory.php">📦 Inventori & Sumber</a>
            <a href="admin_facility.php">🏗️ Fasiliti & Penyelenggaraan</a>

            <div class="menu-label">Keselamatan & Sistem</div>
            <a href="admin_checkin_monitor.php">🔐 Pantauan Daftar Masuk/Keluar</a>
            <a href="admin_visitors.php">🪪 Log Pelawat</a>
            <a href="lulus_pendaftaran.php">✅ Kelulusan Pengguna</a>
            <a href="manage_users.php">👤 Peranan & Akses Pengguna</a>
            <a href="sys_logs.php">🕵️ Log Sistem</a>
        <?php endif; ?>

        <a href="logout.php" class="logout-btn">Log Keluar Sistem</a>
    </div>

    <div class="content">
        <div class="content-header">
            <div class="profile-icon"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            <div>
                <h1 style="margin: 0; font-size: 24px;">Selamat Datang, <?php echo htmlspecialchars($username); ?>!</h1>
                <p style="margin: 5px 0 0 0; color: #777;">Anda sedang log masuk sebagai <strong><?php echo ucfirst($role); ?></strong>.</p>
            </div>
        </div>

        <div class="card-container">
            <div class="card">
                <h3>📌 Makluman Terkini</h3>
                <?php if ($role == 'parent'): ?>
                    <p>Sila pastikan profil pendaftaran anak anda telah dilengkapkan. Semak invois yuran anda di tab 'Fee Management'.</p>
                <?php elseif ($role == 'teacher'): ?>
                    <p>Sila semak 'Health & Allergy Alerts' sebelum memulakan sesi makan. Kemas kini kehadiran selewat-lewatnya jam 10:00 pagi.</p>
                <?php elseif ($role == 'admin'): ?>
                    <p>Amaran: Sebagai System Controller, anda hanya memantau sistem. Tiada perubahan rekod kewangan atau pelajar dibenarkan tanpa kebenaran Pengetua.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>📊 Status Ringkas</h3>
                <p>Modul ini akan memaparkan ringkasan data bergantung kepada peranan anda setelah sistem beroperasi sepenuhnya.</p>
            </div>
        </div>
    </div>

</body>
</html>