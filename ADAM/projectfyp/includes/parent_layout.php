<?php
/**
 * Parent Layout Functions
 * Sistem Pengurusan Taska - Susun Atur Portal Ibu Bapa
 * 
 * Provides renderParentHeader() and renderParentFooter() for consistent parent portal UI.
 */

/**
 * Render the parent portal header: DOCTYPE through content area opening.
 *
 * @param string $pageTitle Page title for <title> tag and top bar
 */
function renderParentHeader($pageTitle = 'Portal Ibu Bapa') {
    $currentPage = basename($_SERVER['PHP_SELF']);
    $username = $_SESSION['username'] ?? 'Pengguna';
    $avatarLetter = mb_strtoupper(mb_substr($username, 0, 1));

    // Navigation structure
    $navGroups = [
        [
            'type'  => 'link',
            'label' => 'Dashboard',
            'icon'  => 'dashboard',
            'href'  => 'parent_home.php',
        ],
        [
            'type'     => 'accordion',
            'label'    => 'Anak Saya',
            'icon'     => 'face',
            'id'       => 'acc-anak',
            'children' => [
                ['Profil Anak', 'profil_anak.php'],
                ['Sejarah Kehadiran', 'sejarah_kehadiran.php'],
                ['Laporan Harian', 'laporan_harian.php'],
            ],
        ],
        [
            'type'     => 'accordion',
            'label'    => 'Akademik & Perkembangan',
            'icon'     => 'school',
            'id'       => 'acc-akademik',
            'children' => [
                ['Pencapaian', 'milestone.php'],
                ['Kad Laporan', 'report_card_anak.php'],
            ],
        ],
        [
            'type'     => 'accordion',
            'label'    => 'Komunikasi',
            'icon'     => 'forum',
            'id'       => 'acc-komunikasi',
            'children' => [
                ['Peti Masuk', 'parent_inbox.php'],
                ['Kalendar', 'parent_calendar.php'],
                ['Pengumuman', 'parent_announcements.php'],
            ],
        ],
        [
            'type'     => 'accordion',
            'label'    => 'Kewangan',
            'icon'     => 'payments',
            'id'       => 'acc-kewangan',
            'children' => [
                ['Invois Saya', 'parent_invoices.php'],
                ['Buat Pembayaran', 'parent_payment.php'],
                ['Sejarah Pembayaran', 'parent_payment_history.php'],
                ['Daftar Anak Baru', 'parent_register_child.php'],
                ['Pendaftaran Semula', 'parent_reenrollment.php'],
            ],
        ],
        [
            'type'     => 'accordion',
            'label'    => 'Keselamatan',
            'icon'     => 'security',
            'id'       => 'acc-keselamatan',
            'children' => [
                ['Jejak Bas', 'parent_bus_tracking.php'],
                ['Log Check-in', 'parent_checkin_log.php'],
                ['Penjaga Sah', 'parent_guardians.php'],
            ],
        ],
    ];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — Portal Ibu Bapa</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Symbols Outlined -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Sidebar nav link styles */
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1.25rem;
            color: #cbd5e1;
            font-size: 0.875rem;
            border-radius: 0.5rem;
            margin: 0.15rem 0.75rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #0ea5e9, #06b6d4);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }

        /* Accordion styles */
        .acc-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            padding: 0.7rem 1.25rem;
            color: #e2e8f0;
            font-size: 0.875rem;
            font-weight: 500;
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
        }

        .acc-btn:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .acc-btn .chevron {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 1.25rem;
        }

        .acc-btn.open .chevron {
            transform: rotate(180deg);
        }

        .acc-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s ease;
        }

        .acc-content.open {
            max-height: 600px;
        }

        .acc-content .nav-link {
            padding-left: 3.25rem;
            font-size: 0.8125rem;
        }

        /* Sidebar scrollbar */
        #sidebar::-webkit-scrollbar {
            width: 4px;
        }

        #sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }

        #sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* Sidebar divider */
        .sidebar-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.08);
            margin: 0.75rem 1.25rem;
        }
    </style>
</head>
<body class="bg-[#f7f9fb]">

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 w-[260px] bg-[#1a1c2e] text-white h-screen overflow-y-auto z-40 transition-transform duration-300 -translate-x-full md:translate-x-0 flex flex-col">

        <!-- Logo Section -->
        <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-cyan-500 flex items-center justify-center shadow-lg">
                <span class="material-symbols-outlined text-white text-xl">child_care</span>
            </div>
            <div>
                <span class="font-bold text-base tracking-wide">Portal Ibu Bapa</span>
                <p class="text-[0.65rem] text-slate-400 -mt-0.5">Sistem Pengurusan Taska</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-4 space-y-1">
            <?php foreach ($navGroups as $group): ?>
                <?php if ($group['type'] === 'link'): ?>
                    <a href="<?php echo $group['href']; ?>" class="nav-link <?php echo ($currentPage === $group['href']) ? 'active' : ''; ?>">
                        <span class="material-symbols-outlined text-lg"><?php echo $group['icon']; ?></span>
                        <?php echo $group['label']; ?>
                    </a>
                <?php elseif ($group['type'] === 'accordion'): ?>
                    <?php
                        // Check if current page is in this accordion's children
                        $isAccordionActive = false;
                        foreach ($group['children'] as $child) {
                            if ($currentPage === $child[1]) {
                                $isAccordionActive = true;
                                break;
                            }
                        }
                    ?>
                    <div>
                        <button class="acc-btn <?php echo $isAccordionActive ? 'open' : ''; ?>" onclick="toggleAcc('<?php echo $group['id']; ?>')">
                            <span class="material-symbols-outlined text-lg"><?php echo $group['icon']; ?></span>
                            <?php echo $group['label']; ?>
                            <span class="material-symbols-outlined chevron">expand_more</span>
                        </button>
                        <div id="<?php echo $group['id']; ?>" class="acc-content <?php echo $isAccordionActive ? 'open' : ''; ?>">
                            <?php foreach ($group['children'] as $child): ?>
                                <a href="<?php echo $child[1]; ?>" class="nav-link <?php echo ($currentPage === $child[1]) ? 'active' : ''; ?>">
                                    <?php echo $child[0]; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Logout -->
        <div class="border-t border-white/10 p-4">
            <a href="logout.php" class="nav-link text-red-300 hover:text-red-200 hover:bg-red-500/10 !mx-0">
                <span class="material-symbols-outlined text-lg">logout</span>
                Log Keluar
            </a>
        </div>
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebar-overlay" class="hidden md:hidden fixed inset-0 bg-black/50 z-30" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="md:ml-[260px] min-h-screen">
        <!-- Top Bar -->
        <header class="fixed top-0 right-0 left-0 md:left-[260px] h-[68px] bg-white border-b border-gray-200 z-20 flex items-center justify-between px-6">
            <!-- Left: Hamburger + Title -->
            <div class="flex items-center gap-4">
                <button class="md:hidden p-2 rounded-lg hover:bg-gray-100 transition" onclick="toggleSidebar()">
                    <span class="material-symbols-outlined text-gray-600">menu</span>
                </button>
                <h1 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
            </div>

            <!-- Right: User Avatar -->
            <div class="flex items-center gap-3">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($username); ?></p>
                    <p class="text-xs text-sky-600 font-medium">Ibu/Bapa</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-sky-500 to-cyan-500 flex items-center justify-center text-white font-bold text-sm shadow-md">
                    <?php echo $avatarLetter; ?>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="pt-[90px] px-6 pb-8">
<?php
}

/**
 * Render the parent portal footer: closing tags, scripts, and sidebar logic.
 */
function renderParentFooter() {
?>
        </div><!-- end content area -->
    </main>

    <script>
        // Toggle mobile sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        // Toggle accordion sections
        function toggleAcc(id) {
            const content = document.getElementById(id);
            const btn = content.previousElementSibling;

            content.classList.toggle('open');
            btn.classList.toggle('open');
        }

        // Auto-open accordion for current page on load
        document.addEventListener('DOMContentLoaded', function() {
            const activeLink = document.querySelector('.nav-link.active');
            if (activeLink) {
                const accContent = activeLink.closest('.acc-content');
                if (accContent) {
                    accContent.classList.add('open');
                    const accBtn = accContent.previousElementSibling;
                    if (accBtn) {
                        accBtn.classList.add('open');
                    }
                }
            }
        });
    </script>
</body>
</html>
<?php
}
