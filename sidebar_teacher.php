<?php
// sidebar_teacher.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guru';
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
        border-left: 3px solid #ffb347; 
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
        border-left-color: rgba(255,179,71,.5); 
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
            <div style="width: 32px; height: 32px; border-radius: 8px; background-color: #c97a2a; display: flex; align-items: center; justify-content: center;">
                <span class="material-symbols-outlined text-white" style="font-size: 18px; color: white;">school</span>
            </div>
            <span style="color: white; font-weight: bold; font-size: 15px;">Panel Guru</span>
        </div>
        <button class="md:hidden" style="color: rgba(255,255,255,0.5); background: transparent; border: none; cursor: pointer; display: flex; align-items: center;" onclick="toggleSidebar()">
            <span class="material-symbols-outlined" style="font-size: 24px;">close</span>
        </button>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav-container" style="flex: 1; overflow-y: auto; padding: 0 12px; display: flex; flex-direction: column; gap: 2px; padding-bottom: 16px;">
        <a href="teacher_home.php" class="nav-link <?php echo ($current_page == 'teacher_home.php') ? 'active' : ''; ?>">
            <span class="material-symbols-outlined" style="font-size: 20px;">dashboard</span>
            <span>Dashboard</span>
        </a>

        <a href="profile_saya.php" class="nav-link <?php echo ($current_page == 'profile_saya.php') ? 'active' : ''; ?>">
            <span class="material-symbols-outlined" style="font-size: 20px;">account_circle</span>
            <span>Profil Saya</span>
        </a>

        <!-- ── Pengurusan Kelas ── -->
        <?php
        $kelas_pages = ['ambil_kehadiran.php', 'maklumat_pelajar_lengkap.php', 'lesson_plan.php', 'aktiviti_kelas.php', 'senarai_kehadiran.php'];
        $is_kelas_open = in_array($current_page, $kelas_pages);
        ?>
        <button onclick="toggleAcc('acc-kelas')"
                class="accordion-btn nav-link <?php echo $is_kelas_open ? 'open' : ''; ?>" aria-expanded="<?php echo $is_kelas_open ? 'true' : 'false'; ?>" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">school</span>
                <span>Pengurusan Kelas</span>
            </div>
            <span class="material-symbols-outlined chevron"
                  id="chevron-acc-kelas" style="font-size: 17px; transition: transform 0.2s; <?php echo $is_kelas_open ? 'transform: rotate(90deg);' : ''; ?>">chevron_right</span>
        </button>
        <div id="acc-kelas" style="display: <?php echo $is_kelas_open ? 'block' : 'none'; ?>; padding-left: 12px; display: flex; flex-direction: column; gap: 2px;">
            <a href="ambil_kehadiran.php"         class="nav-link sub-link <?php echo ($current_page == 'ambil_kehadiran.php') ? 'active' : ''; ?>">📋 <span>Kehadiran Harian</span></a>
            <a href="maklumat_pelajar_lengkap.php" class="nav-link sub-link <?php echo ($current_page == 'maklumat_pelajar_lengkap.php') ? 'active' : ''; ?>">📂 <span>Profil Pelajar &amp; Kesihatan</span></a>
            <a href="lesson_plan.php"             class="nav-link sub-link <?php echo ($current_page == 'lesson_plan.php') ? 'active' : ''; ?>">📚 <span>Rancangan Mengajar</span></a>
            <a href="aktiviti_kelas.php"          class="nav-link sub-link <?php echo ($current_page == 'aktiviti_kelas.php') ? 'active' : ''; ?>">🗓️ <span>Jadual Aktiviti</span></a>
        </div>

        <!-- ── Perkembangan Pelajar ── -->
        <?php
        $perkembangan_pages = ['perkembangan.php', 'report_card.php'];
        $is_perkembangan_open = in_array($current_page, $perkembangan_pages);
        ?>
        <button onclick="toggleAcc('acc-perkembangan')"
                class="accordion-btn nav-link <?php echo $is_perkembangan_open ? 'open' : ''; ?>" aria-expanded="<?php echo $is_perkembangan_open ? 'true' : 'false'; ?>" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">monitoring</span>
                <span>Perkembangan Pelajar</span>
            </div>
            <span class="material-symbols-outlined chevron"
                  id="chevron-acc-perkembangan" style="font-size: 17px; transition: transform 0.2s; <?php echo $is_perkembangan_open ? 'transform: rotate(90deg);' : ''; ?>">chevron_right</span>
        </button>
        <div id="acc-perkembangan" style="display: <?php echo $is_perkembangan_open ? 'block' : 'none'; ?>; padding-left: 12px; display: flex; flex-direction: column; gap: 2px;">
            <a href="perkembangan.php" class="nav-link sub-link <?php echo ($current_page == 'perkembangan.php') ? 'active' : ''; ?>">📈 <span>Perkembangan Kanak-kanak</span></a>
            <a href="report_card.php"  class="nav-link sub-link <?php echo ($current_page == 'report_card.php') ? 'active' : ''; ?>">🎓 <span>Prestasi &amp; Kad Laporan</span></a>
        </div>

        <!-- ── Komunikasi ── -->
        <?php
        $komunikasi_pages = ['teacher_daily_report.php', 'teacher_announcements.php', 'teacher_inbox.php'];
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
            <a href="teacher_daily_report.php"  class="nav-link sub-link <?php echo ($current_page == 'teacher_daily_report.php') ? 'active' : ''; ?>">📝 <span>Laporan Aktiviti Harian</span></a>
            <a href="teacher_announcements.php" class="nav-link sub-link <?php echo ($current_page == 'teacher_announcements.php') ? 'active' : ''; ?>">📢 <span>Pengumuman Kelas</span></a>
            <a href="teacher_inbox.php"         class="nav-link sub-link <?php echo ($current_page == 'teacher_inbox.php') ? 'active' : ''; ?>">💬 <span>Mesej &amp; Maklum Balas</span></a>
        </div>

        <!-- ── Operasi ── -->
        <?php
        $operasi_pages = ['teacher_inventory_request.php', 'teacher_facility_request.php', 'teacher_meal_plan.php', 'teacher_bus_roster.php'];
        $is_operasi_open = in_array($current_page, $operasi_pages);
        ?>
        <button onclick="toggleAcc('acc-operasi')"
                class="accordion-btn nav-link <?php echo $is_operasi_open ? 'open' : ''; ?>" aria-expanded="<?php echo $is_operasi_open ? 'true' : 'false'; ?>" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">engineering</span>
                <span>Operasi</span>
            </div>
            <span class="material-symbols-outlined chevron"
                  id="chevron-acc-operasi" style="font-size: 17px; transition: transform 0.2s; <?php echo $is_operasi_open ? 'transform: rotate(90deg);' : ''; ?>">chevron_right</span>
        </button>
        <div id="acc-operasi" style="display: <?php echo $is_operasi_open ? 'block' : 'none'; ?>; padding-left: 12px; display: flex; flex-direction: column; gap: 2px;">
            <a href="teacher_inventory_request.php" class="nav-link sub-link <?php echo ($current_page == 'teacher_inventory_request.php') ? 'active' : ''; ?>">📦 <span>Permohonan Inventori</span></a>
            <a href="teacher_facility_request.php"  class="nav-link sub-link <?php echo ($current_page == 'teacher_facility_request.php') ? 'active' : ''; ?>">🏗️ <span>Aduan Fasiliti</span></a>
            <a href="teacher_meal_plan.php"         class="nav-link sub-link <?php echo ($current_page == 'teacher_meal_plan.php') ? 'active' : ''; ?>">🍽️ <span>Pelan Pemakanan &amp; Alahan</span></a>
            <a href="teacher_bus_roster.php"        class="nav-link sub-link <?php echo ($current_page == 'teacher_bus_roster.php') ? 'active' : ''; ?>">🚌 <span>Jadual Bas &amp; Kepulangan</span></a>
        </div>

        <!-- ── Keselamatan ── -->
        <?php
        $keselamatan_pages = ['teacher_checkin.php'];
        $is_keselamatan_open = in_array($current_page, $keselamatan_pages);
        ?>
        <button onclick="toggleAcc('acc-keselamatan')"
                class="accordion-btn nav-link <?php echo $is_keselamatan_open ? 'open' : ''; ?>" aria-expanded="<?php echo $is_keselamatan_open ? 'true' : 'false'; ?>" style="justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="material-symbols-outlined" style="font-size: 20px;">security</span>
                <span>Keselamatan</span>
            </div>
            <span class="material-symbols-outlined chevron"
                  id="chevron-acc-keselamatan" style="font-size: 17px; transition: transform 0.2s; <?php echo $is_keselamatan_open ? 'transform: rotate(90deg);' : ''; ?>">chevron_right</span>
        </button>
        <div id="acc-keselamatan" style="display: <?php echo $is_keselamatan_open ? 'block' : 'none'; ?>; padding-left: 12px; display: flex; flex-direction: column; gap: 2px;">
            <a href="teacher_checkin.php" class="nav-link sub-link <?php echo ($current_page == 'teacher_checkin.php') ? 'active' : ''; ?>">🔐 <span>Pengesahan Daftar Masuk</span></a>
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
