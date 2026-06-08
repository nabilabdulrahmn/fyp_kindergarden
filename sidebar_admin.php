<?php
// sidebar_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
?>
<!-- Styles and Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>
    .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
    #sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 260px;
        background-color: #1a1c2e;
        display: flex;
        flex-direction: column;
        padding-top: 1.25rem;
        padding-bottom: 1.25rem;
        z-index: 50;
        transition: transform 0.3s ease;
        transform: translateX(-100%);
        box-sizing: border-box;
    }
    @media (min-width: 768px) {
        #sidebar {
            transform: translateX(0) !important;
        }
        .main-content-shifted {
            margin-left: 260px !important;
            width: calc(100% - 260px) !important;
        }
    }
    #sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 40;
        display: none;
    }
    @media (max-width: 767px) {
        #sidebar-overlay.active {
            display: block;
        }
    }
    .nav-link { 
        display: flex; 
        align-items: center; 
        gap: 10px; 
        padding: 10px 14px; 
        border-radius: 8px;
        color: rgba(255,255,255,.55) !important; 
        font-size: 12px; 
        letter-spacing: .03em; 
        font-weight: 500;
        transition: background .15s, color .15s; 
        text-decoration: none !important; 
        cursor: pointer;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
        box-sizing: border-box;
    }
    .nav-link:hover { 
        background: rgba(255,255,255,.08); 
        color: rgba(255,255,255,.9) !important; 
    }
    .nav-link.active { 
        background: rgba(255,255,255,.13); 
        color: #fff !important; 
        border-left: 3px solid #e2dfff; 
        padding-left: 11px; 
    }
    .accordion-btn.open { 
        background: rgba(255,255,255,.09); 
        color: #fff !important; 
    }
    .sub-link { 
        padding: 8px 12px; 
        font-size: 11px; 
        border-radius: 6px;
        border-left: 2px solid rgba(255,255,255,.08); 
        margin-left: 4px; 
        box-sizing: border-box;
    }
    .sub-link:hover { 
        border-left-color: rgba(226,223,255,.5); 
        background: rgba(255,255,255,.06); 
    }
    .mobile-toggle-btn {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 45;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    @media (min-width: 768px) {
        .mobile-toggle-btn {
            display: none !important;
        }
    }
    .sidebar-nav-container::-webkit-scrollbar { width:5px; }
    .sidebar-nav-container::-webkit-scrollbar-track { background:#1e2124; }
    .sidebar-nav-container::-webkit-scrollbar-thumb { background:#444; border-radius:10px; }
</style>

<!-- Floating Mobile Toggle -->
<button id="mobile-sidebar-toggle" class="mobile-toggle-btn" onclick="toggleSidebar()">
    <span class="material-symbols-outlined" style="color: #464552;">menu</span>
</button>

<aside id="sidebar">
    <!-- Logo -->
    <div style="padding: 0 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 32px; height: 32px; border-radius: 8px; background-color: #5452b5; display: flex; align-items: center; justify-content: center;">
                <span class="material-symbols-outlined text-white" style="font-size: 18px; color: white;">school</span>
            </div>
            <span style="color: white; font-weight: bold; font-size: 15px;">Panel Admin</span>
        </div>
        <button class="md:hidden" style="color: rgba(255,255,255,0.5); background: transparent; border: none; cursor: pointer; display: flex; align-items: center;" onclick="toggleSidebar()">
            <span class="material-symbols-outlined" style="font-size: 24px;">close</span>
        </button>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav-container" style="flex: 1; overflow-y: auto; padding: 0 12px; display: flex; flex-direction: column; gap: 2px; padding-bottom: 16px;">
        <a href="admin_home.php" class="nav-link <?php echo ($current_page == 'admin_home.php') ? 'active' : ''; ?>">
            <span class="material-symbols-outlined" style="font-size: 20px;">dashboard</span>
            <span>Dashboard</span>
        </a>

        <!-- ── Akademik & Pelajar ── -->
        <?php
        $akademik_pages = ['maklumat_pelajar_lengkap.php', 'senarai_kehadiran.php', 'arkib_pelajar.php', 'jadual_aktiviti.php', 'rancangan_mengajar.php', 'laporan_akademik.php'];
        $is_akademik_open = in_array($current_page, $akademik_pages);
        ?>
        <button onclick="toggleAcc('acc-akademik')"
                class="accordion-btn nav-link <?php echo $is_akademik_open ? 'open' : ''; ?>" aria-expanded="<?php echo $is_akademik_open ? 'true' : 'false'; ?>" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">school</span>
                <span>Akademik &amp; Pelajar</span>
            </div>
            <span class="material-symbols-outlined chevron"
                  id="chevron-acc-akademik" style="font-size: 17px; transition: transform 0.2s; <?php echo $is_akademik_open ? 'transform: rotate(90deg);' : ''; ?>">chevron_right</span>
        </button>
        <div id="acc-akademik" style="display: <?php echo $is_akademik_open ? 'block' : 'none'; ?>; padding-left: 12px; display: flex; flex-direction: column; gap: 2px;">
            <a href="maklumat_pelajar_lengkap.php" class="nav-link sub-link <?php echo ($current_page == 'maklumat_pelajar_lengkap.php') ? 'active' : ''; ?>">📁 <span>Direktori Pelajar &amp; Kesihatan</span></a>
            <a href="senarai_kehadiran.php"         class="nav-link sub-link <?php echo ($current_page == 'senarai_kehadiran.php') ? 'active' : ''; ?>">📊 <span>Rekod Kehadiran Keseluruhan</span></a>
            <a href="arkib_pelajar.php"             class="nav-link sub-link <?php echo ($current_page == 'arkib_pelajar.php') ? 'active' : ''; ?>">🗄️ <span>Arkib &amp; Alumni</span></a>
            <a href="jadual_aktiviti.php"           class="nav-link sub-link <?php echo ($current_page == 'jadual_aktiviti.php') ? 'active' : ''; ?>">🗓️ <span>Jadual Aktiviti Induk</span></a>
            <a href="rancangan_mengajar.php"        class="nav-link sub-link <?php echo ($current_page == 'rancangan_mengajar.php') ? 'active' : ''; ?>">📚 <span>Semakan Rancangan Mengajar</span></a>
            <a href="laporan_akademik.php"          class="nav-link sub-link <?php echo ($current_page == 'laporan_akademik.php') ? 'active' : ''; ?>">📈 <span>Laporan Prestasi Akademik</span></a>
        </div>

        <!-- ── Komunikasi ── -->
        <?php
        $komunikasi_pages = ['admin_announcements.php', 'admin_calendar.php', 'admin_inbox.php'];
        $is_komunikasi_open = in_array($current_page, $komunikasi_pages);
        ?>
        <button onclick="toggleAcc('acc-komunikasi')"
                class="accordion-btn nav-link <?php echo $is_komunikasi_open ? 'open' : ''; ?>" aria-expanded="<?php echo $is_komunikasi_open ? 'true' : 'false'; ?>" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">campaign</span>
                <span>Komunikasi</span>
            </div>
            <span class="material-symbols-outlined chevron"
                  id="chevron-acc-komunikasi" style="font-size: 17px; transition: transform 0.2s; <?php echo $is_komunikasi_open ? 'transform: rotate(90deg);' : ''; ?>">chevron_right</span>
        </button>
        <div id="acc-komunikasi" style="display: <?php echo $is_komunikasi_open ? 'block' : 'none'; ?>; padding-left: 12px; display: flex; flex-direction: column; gap: 2px;">
            <a href="admin_announcements.php" class="nav-link sub-link <?php echo ($current_page == 'admin_announcements.php') ? 'active' : ''; ?>">📢 <span>Pengumuman Sekolah</span></a>
            <a href="admin_calendar.php"      class="nav-link sub-link <?php echo ($current_page == 'admin_calendar.php') ? 'active' : ''; ?>">📆 <span>Pengurusan Kalendar</span></a>
            <a href="admin_inbox.php"         class="nav-link sub-link <?php echo ($current_page == 'admin_inbox.php') ? 'active' : ''; ?>">💬 <span>Peti Masuk &amp; Maklum Balas</span></a>
        </div>

        <!-- ── Kewangan & Pendaftaran ── -->
        <?php
        $kewangan_pages = ['senarai_pelajar.php', 'admin_doc_verify.php', 'admin_enrollment.php', 'admin_fees.php', 'admin_invoices.php', 'admin_expenses.php', 'view_income_statement.php'];
        $is_kewangan_open = in_array($current_page, $kewangan_pages);
        ?>
        <button onclick="toggleAcc('acc-kewangan')"
                class="accordion-btn nav-link <?php echo $is_kewangan_open ? 'open' : ''; ?>" aria-expanded="<?php echo $is_kewangan_open ? 'true' : 'false'; ?>" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">payments</span>
                <span>Kewangan &amp; Pendaftaran</span>
            </div>
            <span class="material-symbols-outlined chevron"
                  id="chevron-acc-kewangan" style="font-size: 17px; transition: transform 0.2s; <?php echo $is_kewangan_open ? 'transform: rotate(90deg);' : ''; ?>">chevron_right</span>
        </button>
        <div id="acc-kewangan" style="display: <?php echo $is_kewangan_open ? 'block' : 'none'; ?>; padding-left: 12px; display: flex; flex-direction: column; gap: 2px;">
            <a href="senarai_pelajar.php"   class="nav-link sub-link <?php echo ($current_page == 'senarai_pelajar.php') ? 'active' : ''; ?>">🎓 <span>Permohonan Baru &amp; Senarai Menunggu</span></a>
            <a href="admin_doc_verify.php"  class="nav-link sub-link <?php echo ($current_page == 'admin_doc_verify.php') ? 'active' : ''; ?>">📄 <span>Pengesahan Dokumen</span></a>
            <a href="admin_enrollment.php"  class="nav-link sub-link <?php echo ($current_page == 'admin_enrollment.php') ? 'active' : ''; ?>">📋 <span>Status Pendaftaran</span></a>
            <a href="admin_fees.php"        class="nav-link sub-link <?php echo ($current_page == 'admin_fees.php') ? 'active' : ''; ?>">💰 <span>Pemantauan Yuran</span></a>
            <a href="admin_invoices.php"    class="nav-link sub-link <?php echo ($current_page == 'admin_invoices.php') ? 'active' : ''; ?>">🧾 <span>Penjanaan Invois</span></a>
            <a href="admin_expenses.php"    class="nav-link sub-link <?php echo ($current_page == 'admin_expenses.php' || $current_page == 'view_income_statement.php') ? 'active' : ''; ?>">📊 <span>Penyata Pendapatan</span></a>
        </div>

        <!-- ── Operasi & Sumber ── -->
        <?php
        $operasi_pages = ['admin_staff.php', 'admin_payroll.php', 'admin_transport.php', 'admin_meal_plans.php', 'admin_inventory.php', 'admin_facility.php'];
        $is_operasi_open = in_array($current_page, $operasi_pages);
        ?>
        <button onclick="toggleAcc('acc-operasi')"
                class="accordion-btn nav-link <?php echo $is_operasi_open ? 'open' : ''; ?>" aria-expanded="<?php echo $is_operasi_open ? 'true' : 'false'; ?>" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">settings_accessibility</span>
                <span>Operasi &amp; Sumber</span>
            </div>
            <span class="material-symbols-outlined chevron"
                  id="chevron-acc-operasi" style="font-size: 17px; transition: transform 0.2s; <?php echo $is_operasi_open ? 'transform: rotate(90deg);' : ''; ?>">chevron_right</span>
        </button>
        <div id="acc-operasi" style="display: <?php echo $is_operasi_open ? 'block' : 'none'; ?>; padding-left: 12px; display: flex; flex-direction: column; gap: 2px;">
            <a href="admin_staff.php"      class="nav-link sub-link <?php echo ($current_page == 'admin_staff.php') ? 'active' : ''; ?>">👥 <span>Direktori Staf</span></a>
            <a href="admin_payroll.php"    class="nav-link sub-link <?php echo ($current_page == 'admin_payroll.php') ? 'active' : ''; ?>">💼 <span>Penggajian (Payroll)</span></a>
            <a href="admin_transport.php"  class="nav-link sub-link <?php echo ($current_page == 'admin_transport.php') ? 'active' : ''; ?>">🚌 <span>Pengangkutan &amp; Laluan</span></a>
            <a href="admin_meal_plans.php" class="nav-link sub-link <?php echo ($current_page == 'admin_meal_plans.php') ? 'active' : ''; ?>">🍽️ <span>Perancangan Pemakanan</span></a>
            <a href="admin_inventory.php"  class="nav-link sub-link <?php echo ($current_page == 'admin_inventory.php') ? 'active' : ''; ?>">📦 <span>Inventori &amp; Sumber</span></a>
            <a href="admin_facility.php"   class="nav-link sub-link <?php echo ($current_page == 'admin_facility.php') ? 'active' : ''; ?>">🏗️ <span>Fasiliti &amp; Penyelenggaraan</span></a>
        </div>

        <!-- ── Keselamatan & Sistem ── -->
        <?php
        $keselamatan_pages = ['admin_checkin_monitor.php', 'admin_visitors.php', 'lulus_pendaftaran.php', 'manage_users.php', 'sys_logs.php'];
        $is_keselamatan_open = in_array($current_page, $keselamatan_pages);
        ?>
        <button onclick="toggleAcc('acc-keselamatan')"
                class="accordion-btn nav-link <?php echo $is_keselamatan_open ? 'open' : ''; ?>" aria-expanded="<?php echo $is_keselamatan_open ? 'true' : 'false'; ?>" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">security</span>
                <span>Keselamatan &amp; Sistem</span>
            </div>
            <span class="material-symbols-outlined chevron"
                  id="chevron-acc-keselamatan" style="font-size: 17px; transition: transform 0.2s; <?php echo $is_keselamatan_open ? 'transform: rotate(90deg);' : ''; ?>">chevron_right</span>
        </button>
        <div id="acc-keselamatan" style="display: <?php echo $is_keselamatan_open ? 'block' : 'none'; ?>; padding-left: 12px; display: flex; flex-direction: column; gap: 2px;">
            <a href="admin_checkin_monitor.php" class="nav-link sub-link <?php echo ($current_page == 'admin_checkin_monitor.php') ? 'active' : ''; ?>">🔐 <span>Pantauan Daftar Masuk/Keluar</span></a>
            <a href="admin_visitors.php"        class="nav-link sub-link <?php echo ($current_page == 'admin_visitors.php') ? 'active' : ''; ?>">🪪 <span>Log Pelawat</span></a>
            <a href="lulus_pendaftaran.php"     class="nav-link sub-link <?php echo ($current_page == 'lulus_pendaftaran.php') ? 'active' : ''; ?>">✅ <span>Kelulusan Pengguna</span></a>
            <a href="manage_users.php"          class="nav-link sub-link <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>">👤 <span>Peranan &amp; Akses Pengguna</span></a>
            <a href="sys_logs.php"              class="nav-link sub-link <?php echo ($current_page == 'sys_logs.php') ? 'active' : ''; ?>">🕵️ <span>Log Sistem</span></a>
        </div>
    </nav>

    <!-- Logout -->
    <div style="padding: 12px; border-top: 1px solid rgba(255,255,255,0.1);">
        <a href="logout.php" class="nav-link" style="color: #f87171 !important;">
            <span class="material-symbols-outlined" style="font-size: 20px;">logout</span>
            <span>Log Keluar Sistem</span>
        </a>
    </div>
</aside>

<div id="sidebar-overlay" onclick="toggleSidebar()"></div>

<script>
function toggleSidebar() {
    const s = document.getElementById('sidebar');
    const o = document.getElementById('sidebar-overlay');
    if (s.classList.contains('active-mobile')) {
        s.style.transform = '';
        s.classList.remove('active-mobile');
        o.classList.remove('active');
    } else {
        s.style.transform = 'translateX(0px)';
        s.classList.add('active-mobile');
        o.classList.add('active');
    }
}
function toggleAcc(id) {
    const panel = document.getElementById(id);
    const btn = panel.previousElementSibling;
    const chevron = document.getElementById('chevron-' + id);
    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'block';
        btn.classList.add('open');
        if (chevron) chevron.style.transform = 'rotate(90deg)';
    } else {
        panel.style.display = 'none';
        btn.classList.remove('open');
        if (chevron) chevron.style.transform = '';
    }
}
// Run on load
document.addEventListener('DOMContentLoaded', () => {
    // Hide floating toggle if header/top-bar exists
    const hasHeader = document.querySelector('header') || document.querySelector('.header-bar') || document.querySelector('.top-bar');
    const toggleBtn = document.getElementById('mobile-sidebar-toggle');
    if (hasHeader && toggleBtn) {
        toggleBtn.style.display = 'none';
    }
});
</script>
