<?php
// login.php
session_start();
require 'db.php'; 

// ── HELPER: sanitize phone to digits-only ──
function sanitize_phone($phone) {
    return preg_replace('/[^0-9]/', '', $phone);
}

// ── HELPER: sanitize IC to digits-only (strip dashes) ──
function sanitize_ic($ic) {
    return preg_replace('/[^0-9]/', '', $ic);
}

// ── Preserve form data on error ──
$form_data = $_SESSION['form_data'] ?? [];
$form_errors = $_SESSION['form_errors'] ?? [];
$login_error = $_SESSION['login_error'] ?? '';
$register_success = $_SESSION['register_success'] ?? '';
unset($_SESSION['form_data'], $_SESSION['form_errors'], $_SESSION['login_error'], $_SESSION['register_success']);

// ── PROCESS LOGIN ──
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $user = $conn->real_escape_string(trim($_POST['username']));
    $pass = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$user'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            if ($row['status'] === 'pending') {
                $login_error = 'Akaun anda masih dalam proses kelulusan oleh admin.';
            } else if ($row['status'] === 'rejected') {
                $login_error = 'Pendaftaran akaun anda telah ditolak.';
            } else {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                header("Location: home.php");
                exit();
            }
        } else {
            $login_error = 'Kata laluan salah!';
        }
    } else {
        $login_error = 'Username tidak wujud!';
    }
}

// ── PROCESS REGISTRATION ──
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $errors = [];

    // Collect raw inputs
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm_pw  = $_POST['confirm_password'] ?? '';
    $role        = trim($_POST['role'] ?? '');
    $full_name   = trim($_POST['full_name'] ?? '');
    $ic_number   = sanitize_ic($_POST['ic_number'] ?? '');
    $phone_raw   = $_POST['phone_number'] ?? '';
    $phone       = sanitize_phone($phone_raw);
    $email       = trim($_POST['email'] ?? '');
    $race        = trim($_POST['race'] ?? '');
    $age         = (int)($_POST['age'] ?? 0);
    $gender      = trim($_POST['gender'] ?? '');

    // Auto-calculate age and gender from IC number on backend if IC is valid (12 digits)
    if (strlen($ic_number) == 12) {
        $yy = substr($ic_number, 0, 2);
        $mm = substr($ic_number, 2, 2);
        $dd = substr($ic_number, 4, 2);
        if (ctype_digit($yy) && ctype_digit($mm) && ctype_digit($dd)) {
            $year2Digit = (int)$yy;
            $month = (int)$mm;
            $day = (int)$dd;
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                $currentYear = (int)date('Y');
                $currentYear2Digit = $currentYear % 100;
                $birthYear = ($year2Digit <= $currentYear2Digit) ? (2000 + $year2Digit) : (1900 + $year2Digit);
                
                try {
                    $birthDate = new DateTime("$birthYear-$month-$day");
                    // Ensure date is valid (no calendar wrap-around)
                    if ($birthDate->format('Y') == $birthYear && (int)$birthDate->format('m') == $month && (int)$birthDate->format('d') == $day) {
                        $today = new DateTime();
                        $age_diff = $today->diff($birthDate);
                        $calculated_age = $age_diff->y;
                        if ($calculated_age >= 0 && $calculated_age <= 120) {
                            $age = $calculated_age;
                        }
                    }
                } catch (Exception $e) {
                    // Ignore date errors
                }
            }
        }
        
        // Auto-select gender: odd = Lelaki, even = Perempuan
        $lastDigit = (int)substr($ic_number, -1);
        $gender = ($lastDigit % 2 === 0) ? 'Perempuan' : 'Lelaki';
    }
    $addr_street = trim($_POST['addr_street'] ?? '');
    $addr_city   = trim($_POST['addr_city'] ?? '');
    $addr_state  = trim($_POST['addr_state'] ?? '');
    $addr_postal = trim($_POST['addr_postal'] ?? '');
    $addr_country= trim($_POST['addr_country'] ?? 'Malaysia');

    // Build full address from components
    $address_parts = array_filter([$addr_street, $addr_city, $addr_state, $addr_postal, $addr_country]);
    $address = implode(', ', $address_parts);

    // Store form data for prefill on error
    $preserve = [
        'username' => $username, 'role' => $role, 'full_name' => $full_name,
        'ic_number' => $_POST['ic_number'] ?? '', 'phone_number' => $phone_raw,
        'email' => $email, 'race' => $race, 'age' => $age, 'gender' => $gender,
        'addr_street' => $addr_street, 'addr_city' => $addr_city,
        'addr_state' => $addr_state, 'addr_postal' => $addr_postal,
        'addr_country' => $addr_country
    ];

    // ── VALIDATION ──
    // Username
    if (empty($username)) {
        $errors['username'] = 'Username diperlukan.';
    } elseif (strlen($username) < 4) {
        $errors['username'] = 'Username mesti sekurang-kurangnya 4 aksara.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = 'Username hanya boleh mengandungi huruf, nombor dan underscore.';
    }

    // Password
    if (empty($password)) {
        $errors['password'] = 'Kata laluan diperlukan.';
    } else {
        if (strlen($password) < 8) $errors['password'] = 'Kata laluan mesti sekurang-kurangnya 8 aksara.';
        elseif (!preg_match('/[A-Z]/', $password)) $errors['password'] = 'Kata laluan mesti mengandungi huruf besar.';
        elseif (!preg_match('/[a-z]/', $password)) $errors['password'] = 'Kata laluan mesti mengandungi huruf kecil.';
        elseif (!preg_match('/[0-9]/', $password)) $errors['password'] = 'Kata laluan mesti mengandungi nombor.';
        elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $errors['password'] = 'Kata laluan mesti mengandungi aksara khas.';
    }

    // Confirm password
    if ($password !== $confirm_pw) {
        $errors['confirm_password'] = 'Kata laluan tidak sepadan.';
    }

    // Role
    if (!in_array($role, ['parent', 'teacher', 'admin'])) {
        $errors['role'] = 'Sila pilih peranan yang sah.';
    }

    // Full name
    if (empty($full_name)) {
        $errors['full_name'] = 'Nama penuh diperlukan.';
    } elseif (strlen($full_name) < 3) {
        $errors['full_name'] = 'Nama penuh mesti sekurang-kurangnya 3 aksara.';
    } elseif (strlen($full_name) > 100) {
        $errors['full_name'] = 'Nama penuh tidak boleh melebihi 100 aksara.';
    }

    // IC Number: must be exactly 12 digits
    if (empty($ic_number)) {
        $errors['ic_number'] = 'No. Kad Pengenalan diperlukan.';
    } elseif (strlen($ic_number) != 12) {
        $errors['ic_number'] = 'No. Kad Pengenalan mesti 12 digit (cth: 900101149999).';
    }

    // Phone number
    if (empty($phone)) {
        $errors['phone_number'] = 'No. Telefon diperlukan.';
    } elseif (strlen($phone) < 10 || strlen($phone) > 11) {
        $errors['phone_number'] = 'No. Telefon mesti 10-11 digit.';
    }

    // Email
    if (empty($email)) {
        $errors['email'] = 'Alamat emel diperlukan.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Sila masukkan alamat emel yang sah (cth: name@domain.com).';
    }

    // Race
    if (empty($race)) {
        $errors['race'] = 'Sila pilih bangsa.';
    }

    // Age
    if ($age < 18 || $age > 100) {
        $errors['age'] = 'Umur mesti antara 18 hingga 100 tahun.';
    }

    // Address
    if (empty($addr_street)) {
        $errors['addr_street'] = 'Alamat jalan diperlukan.';
    }
    if (empty($addr_city)) {
        $errors['addr_city'] = 'Bandar diperlukan.';
    }
    if (empty($addr_state)) {
        $errors['addr_state'] = 'Negeri diperlukan.';
    }
    if (empty($addr_postal)) {
        $errors['addr_postal'] = 'Poskod diperlukan.';
    } elseif (!preg_match('/^\d{5}$/', $addr_postal)) {
        $errors['addr_postal'] = 'Poskod mesti 5 digit.';
    }

    // DB uniqueness checks (only if no field errors)
    if (empty($errors)) {
        $check_user = $conn->query("SELECT id FROM users WHERE username='" . $conn->real_escape_string($username) . "'");
        if ($check_user->num_rows > 0) $errors['username'] = 'Username sudah wujud. Sila pilih username lain.';

        $check_email = $conn->query("SELECT id FROM users WHERE email='" . $conn->real_escape_string($email) . "'");
        if ($check_email->num_rows > 0) $errors['email'] = 'Emel sudah digunakan. Sila gunakan emel lain.';
    }

    if (!empty($errors)) {
        $_SESSION['form_data'] = $preserve;
        $_SESSION['form_errors'] = $errors;
        header("Location: login.php?action=register");
        exit();
    }

    // ── ALL VALID - INSERT ──
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $conn->begin_transaction();
    try {
        $sql_user = "INSERT INTO users (username, password, role, email, status) VALUES ('" 
            . $conn->real_escape_string($username) . "', '$hashed_password', '" 
            . $conn->real_escape_string($role) . "', '" 
            . $conn->real_escape_string($email) . "', 'pending')";
        $conn->query($sql_user);
        $user_id = $conn->insert_id;

        // Format phone for storage: 01X-XXXXXXX
        $phone_formatted = $phone;
        if (strlen($phone) >= 10) {
            $phone_formatted = substr($phone, 0, 3) . '-' . substr($phone, 3);
        }

        // Format IC for storage: XXXXXX-XX-XXXX
        $ic_formatted = $ic_number;
        if (strlen($ic_number) == 12) {
            $ic_formatted = substr($ic_number, 0, 6) . '-' . substr($ic_number, 6, 2) . '-' . substr($ic_number, 8, 4);
        }

        if ($role === 'parent') {
            $sql_p = "INSERT INTO parents (user_id, full_name, ic_number, phone_number, address, race, age, email) 
                      VALUES ($user_id, '" . $conn->real_escape_string($full_name) . "', '$ic_formatted', '$phone_formatted', '" 
                      . $conn->real_escape_string($address) . "', '" . $conn->real_escape_string($race) . "', $age, '" 
                      . $conn->real_escape_string($email) . "')";
            $conn->query($sql_p);
        } else if ($role === 'teacher') {
            $sql_t = "INSERT INTO teachers (user_id, full_name, ic_number, phone_number, address, race, age, email, specialization) 
                      VALUES ($user_id, '" . $conn->real_escape_string($full_name) . "', '$ic_formatted', '$phone_formatted', '" 
                      . $conn->real_escape_string($address) . "', '" . $conn->real_escape_string($race) . "', $age, '" 
                      . $conn->real_escape_string($email) . "', 'Pendidikan Awal')";
            $conn->query($sql_t);

            $sql_s = "INSERT INTO staff (user_id, full_name, ic_number, phone_number, email, position, department, employment_type, status, race, age, hire_date) 
                      VALUES ($user_id, '" . $conn->real_escape_string($full_name) . "', '$ic_formatted', '$phone_formatted', '" 
                      . $conn->real_escape_string($email) . "', 'Guru', 'Teaching', 'Full-Time', 'Inactive', '" 
                      . $conn->real_escape_string($race) . "', $age, CURDATE())";
            $conn->query($sql_s);
        }
        
        $conn->commit();
        $_SESSION['register_success'] = 'Pendaftaran Berjaya! Akaun anda telah dihantar untuk kelulusan Admin.';
        header("Location: login.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['form_data'] = $preserve;
        $_SESSION['form_errors'] = ['general' => 'Ralat semasa pendaftaran. Sila cuba lagi.'];
        header("Location: login.php?action=register");
        exit();
    }
}

$mode = isset($_GET['action']) && $_GET['action'] == 'register' ? 'register' : 'login';

// Prefill helpers
function fval($field, $form_data) {
    return htmlspecialchars($form_data[$field] ?? '', ENT_QUOTES, 'UTF-8');
}
function ferr($field, $form_errors) {
    return $form_errors[$field] ?? '';
}
function ferr_class($field, $form_errors) {
    return isset($form_errors[$field]) ? 'border-red-400 bg-red-50/30' : 'border-gray-200 bg-[#fcfcfc]';
}
?>
<!DOCTYPE html>
<html lang="ms" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pengurusan Kanak-Kanak Terpadu<?php echo $mode == 'register' ? ' - Daftar' : ' - Log Masuk'; ?></title>
    <meta name="description" content="Sistem Pengurusan Terpadu untuk Taska, Tadika dan KAFA Care. Log masuk atau daftar akaun baharu.">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { font-family: 'Outfit', sans-serif; }

        /* ── Custom scrollbar ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #ddd; border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: #bbb; }

        /* ── Floating animation ── */
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-float-delay { animation: float 6s ease-in-out 2s infinite; }
        .animate-float-slow { animation: float 8s ease-in-out 1s infinite; }

        /* ── Fade slide in ── */
        @keyframes fadeSlideUp { 0% { opacity:0; transform: translateY(20px); } 100% { opacity:1; transform: translateY(0); } }
        .animate-fadeIn { animation: fadeSlideUp 0.5s ease-out both; }
        .animate-fadeIn-d1 { animation: fadeSlideUp 0.5s 0.1s ease-out both; }
        .animate-fadeIn-d2 { animation: fadeSlideUp 0.5s 0.2s ease-out both; }
        .animate-fadeIn-d3 { animation: fadeSlideUp 0.5s 0.3s ease-out both; }

        /* ── Shake for errors ── */
        @keyframes shake { 0%,100% { transform: translateX(0); } 20%,60% { transform: translateX(-4px); } 40%,80% { transform: translateX(4px); } }
        .animate-shake { animation: shake 0.4s ease-in-out; }

        /* ── Password strength meter ── */
        .pw-meter { height: 4px; border-radius: 2px; transition: all 0.3s ease; }
        .pw-weak { background: linear-gradient(90deg, #ef4444 0%, #ef4444 25%, #e5e7eb 25%); }
        .pw-fair { background: linear-gradient(90deg, #f59e0b 0%, #f59e0b 50%, #e5e7eb 50%); }
        .pw-good { background: linear-gradient(90deg, #22c55e 0%, #22c55e 75%, #e5e7eb 75%); }
        .pw-strong { background: linear-gradient(90deg, #16a34a 0%, #16a34a 100%); }

        /* ── Error message style ── */
        .field-error { color: #ef4444; font-size: 11px; margin-top: 4px; font-weight: 500; display: flex; align-items: center; gap: 4px; }
        .field-error .material-symbols-outlined { font-size: 14px; }

        /* ── Check mark for valid fields ── */
        .field-valid { color: #22c55e; }

        /* ── Stepper ── */
        .step-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; transition: all 0.3s ease; }
        .step-dot.active { background: linear-gradient(135deg, #ff6f91, #ff9a76); color: white; box-shadow: 0 4px 15px rgba(255,111,145,0.3); }
        .step-dot.completed { background: #22c55e; color: white; }
        .step-dot.inactive { background: #f3f4f6; color: #9ca3af; }
        .step-line { height: 2px; flex: 1; transition: background 0.3s; }
        .step-line.active { background: linear-gradient(90deg, #ff6f91, #ff9a76); }
        .step-line.inactive { background: #e5e7eb; }

        /* ── Toast ── */
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .toast { animation: slideInRight 0.4s ease-out both; }
    </style>
</head>
<body class="bg-[#fdfbf7] min-h-screen flex flex-col items-center justify-center relative overflow-x-hidden p-4 sm:p-6">

    <!-- ── BACKGROUND GRADIENT BLOBS ── -->
    <div class="absolute top-[-5%] right-[-10%] w-[350px] h-[350px] rounded-full bg-gradient-to-br from-[#ffb347]/40 to-[#ffcc80]/15 blur-3xl -z-10 pointer-events-none animate-float"></div>
    <div class="absolute bottom-[10%] left-[5%] w-[220px] h-[220px] rounded-full bg-gradient-to-br from-[#ff6f91]/30 to-[#ff9aa2]/10 blur-2xl -z-10 pointer-events-none animate-float-delay"></div>
    <div class="absolute top-[40%] left-[-5%] w-[180px] h-[180px] rounded-full bg-gradient-to-br from-[#a7c7e7]/30 to-[#b5d8f7]/10 blur-2xl -z-10 pointer-events-none animate-float-slow"></div>
    <div class="absolute bottom-[-5%] right-[15%] w-[200px] h-[200px] rounded-full bg-gradient-to-br from-[#c3aed6]/25 to-[#ddd6f3]/10 blur-2xl -z-10 pointer-events-none animate-float"></div>

    <!-- ── Toast Notifications ── -->
    <?php if ($register_success): ?>
    <div id="toast-success" class="toast fixed top-6 right-6 z-50 bg-white border border-green-200 rounded-2xl shadow-xl p-4 flex items-center gap-3 max-w-sm">
        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center text-green-500 flex-shrink-0">
            <span class="material-symbols-outlined">check_circle</span>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-800">Berjaya!</p>
            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($register_success); ?></p>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-2 text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined text-[18px]">close</span></button>
    </div>
    <script>setTimeout(() => { const t = document.getElementById('toast-success'); if(t) t.style.opacity='0'; setTimeout(()=>{ if(t) t.remove(); },400); }, 6000);</script>
    <?php endif; ?>

    <?php if ($login_error): ?>
    <div id="toast-error" class="toast fixed top-6 right-6 z-50 bg-white border border-red-200 rounded-2xl shadow-xl p-4 flex items-center gap-3 max-w-sm">
        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-red-500 flex-shrink-0">
            <span class="material-symbols-outlined">error</span>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-800">Ralat</p>
            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($login_error); ?></p>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-2 text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined text-[18px]">close</span></button>
    </div>
    <script>setTimeout(() => { const t = document.getElementById('toast-error'); if(t) t.style.opacity='0'; setTimeout(()=>{ if(t) t.remove(); },400); }, 6000);</script>
    <?php endif; ?>

    <!-- ── BRAND LOGO & HEADER ── -->
    <div class="flex flex-col items-center mb-6 text-center select-none animate-fadeIn">
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#ffb347]/20 to-[#ff6f91]/10 border border-[#ffb347]/30 flex items-center justify-center text-[#c97a2a] shadow-sm mb-4 relative">
            <span class="material-symbols-outlined text-[34px]">school</span>
            <div class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-green-400 border-2 border-white flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-[10px]" style="font-variation-settings:'FILL' 1;">check</span>
            </div>
        </div>
        <p class="text-[10px] font-bold text-[#c97a2a] uppercase tracking-[3px] mb-1.5">Sistem Pengurusan Kanak-Kanak Terpadu</p>
        <h1 class="text-[26px] sm:text-[28px] font-bold text-gray-800 leading-tight">
            <?php echo $mode == 'login' ? 'Selamat Kembali! 👋' : 'Daftar Akaun Baharu'; ?>
        </h1>
        <p class="text-sm text-gray-400 mt-1">
            <?php echo $mode == 'login' ? 'Sila log masuk untuk teruskan.' : 'Lengkapkan maklumat anda di bawah.'; ?>
        </p>
    </div>

    <!-- ── FLOATING FORM CARD ── -->
    <div class="w-full max-w-[<?php echo $mode == 'login' ? '430px' : '580px'; ?>] bg-white rounded-3xl border border-gray-100/80 shadow-[0_25px_60px_rgba(0,0,0,0.04)] relative transition-all duration-300 animate-fadeIn-d1 overflow-hidden">
        
        <!-- Decorative top gradient bar -->
        <div class="h-1.5 w-full bg-gradient-to-r <?php echo $mode=='login' ? 'from-[#27ae60] via-[#2ecc71] to-[#27ae60]' : 'from-[#ff6f91] via-[#ff9a76] to-[#ffc3a0]'; ?>"></div>

        <div class="p-6 sm:p-8">
            <div class="absolute top-0 right-0 w-28 h-28 bg-gradient-to-bl from-[#ff6f91]/5 to-transparent rounded-full blur-xl pointer-events-none"></div>

            <?php if ($mode == 'login'): ?>
                <form method="POST" action="login.php" id="loginForm">
                    <div class="space-y-5">
                        
                        <!-- Username Input -->
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Username</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <span class="material-symbols-outlined text-[20px]">person</span>
                                </span>
                                <input type="text" name="username" id="login-username" placeholder="Masukkan ID Pengguna" 
                                       class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#27ae60]/20 focus:border-[#27ae60] transition bg-[#fcfcfc] text-sm placeholder-gray-400 text-gray-800" required>
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Kata Laluan</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <span class="material-symbols-outlined text-[20px]">lock</span>
                                </span>
                                <input type="password" name="password" id="login-password" placeholder="Masukkan Kata Laluan" 
                                       class="w-full pl-11 pr-11 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#27ae60]/20 focus:border-[#27ae60] transition bg-[#fcfcfc] text-sm placeholder-gray-400 text-gray-800" required>
                                <button type="button" onclick="togglePw('login-password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600 transition">
                                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                                </button>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <button type="submit" name="login" id="btn-login"
                                class="w-full bg-gradient-to-r from-[#27ae60] to-[#2ecc71] hover:from-[#218c53] hover:to-[#27ae60] text-white font-semibold py-3.5 px-4 rounded-xl transition-all duration-200 shadow-lg shadow-[#27ae60]/20 flex items-center justify-center gap-2 text-sm mt-2 hover:shadow-xl hover:shadow-[#27ae60]/25 active:scale-[0.98]">
                            <span class="material-symbols-outlined text-[18px]">login</span>
                            <span>Log Masuk</span>
                        </button>
                    </div>

                    <!-- Toggle Navigation Links -->
                    <div class="flex justify-between items-center text-xs mt-6 pt-4 border-t border-gray-50">
                        <a href="login.php?action=register" class="text-gray-500 hover:text-[#27ae60] transition font-medium">
                            Belum ada akaun? <span class="font-bold text-[#27ae60] underline underline-offset-2">Daftar Baru</span>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-gray-600 transition font-medium">Lupa Password?</a>
                    </div>
                </form>

            <?php else: ?>
                <!-- ── REGISTRATION FORM WITH MULTI-STEP ── -->
                <form method="POST" action="login.php?action=register" id="registerForm" novalidate>
                    
                    <!-- Step Indicator -->
                    <div class="flex items-center justify-center gap-0 mb-6" id="step-indicator">
                        <div class="step-dot active" id="step-dot-1">1</div>
                        <div class="step-line inactive" id="step-line-1"></div>
                        <div class="step-dot inactive" id="step-dot-2">2</div>
                        <div class="step-line inactive" id="step-line-2"></div>
                        <div class="step-dot inactive" id="step-dot-3">3</div>
                    </div>
                    <div class="flex justify-between text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-6 px-1">
                        <span>Akaun</span>
                        <span>Maklumat Diri</span>
                        <span>Alamat</span>
                    </div>

                    <!-- ═══════════════════ STEP 1: Account ═══════════════════ -->
                    <div id="step-1" class="space-y-4">
                        
                        <!-- Username -->
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Cipta Username <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <span class="material-symbols-outlined text-[20px]">person</span>
                                </span>
                                <input type="text" name="username" id="reg-username" placeholder="Cth: ahmad_abu" 
                                       value="<?php echo fval('username', $form_data); ?>"
                                       class="w-full pl-11 pr-10 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('username', $form_errors); ?>" required>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-300" id="username-status"></span>
                            </div>
                            <?php if (ferr('username', $form_errors)): ?>
                                <div class="field-error animate-shake"><span class="material-symbols-outlined">error</span> <?php echo ferr('username', $form_errors); ?></div>
                            <?php endif; ?>
                            <div class="field-error hidden" id="username-error"></div>
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Cipta Kata Laluan <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <span class="material-symbols-outlined text-[20px]">lock</span>
                                </span>
                                <input type="password" name="password" id="reg-password" placeholder="Cipta Kata Laluan Selamat" 
                                       class="w-full pl-11 pr-11 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('password', $form_errors); ?>" required>
                                <button type="button" onclick="togglePw('reg-password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600 transition">
                                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                                </button>
                            </div>
                            <!-- Password strength meter -->
                            <div class="pw-meter w-full mt-2" id="pw-meter"></div>
                            <!-- Password requirements checklist -->
                            <div class="mt-2 space-y-1" id="pw-requirements">
                                <div class="flex items-center gap-1.5 text-[11px]" id="pw-req-length">
                                    <span class="material-symbols-outlined text-[14px] text-gray-300" id="pw-icon-length">circle</span>
                                    <span class="text-gray-400">Sekurang-kurangnya 8 aksara</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px]" id="pw-req-upper">
                                    <span class="material-symbols-outlined text-[14px] text-gray-300" id="pw-icon-upper">circle</span>
                                    <span class="text-gray-400">Satu huruf besar (A-Z)</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px]" id="pw-req-lower">
                                    <span class="material-symbols-outlined text-[14px] text-gray-300" id="pw-icon-lower">circle</span>
                                    <span class="text-gray-400">Satu huruf kecil (a-z)</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px]" id="pw-req-number">
                                    <span class="material-symbols-outlined text-[14px] text-gray-300" id="pw-icon-number">circle</span>
                                    <span class="text-gray-400">Satu nombor (0-9)</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px]" id="pw-req-special">
                                    <span class="material-symbols-outlined text-[14px] text-gray-300" id="pw-icon-special">circle</span>
                                    <span class="text-gray-400">Satu aksara khas (!@#$%...)</span>
                                </div>
                            </div>
                            <?php if (ferr('password', $form_errors)): ?>
                                <div class="field-error animate-shake mt-1"><span class="material-symbols-outlined">error</span> <?php echo ferr('password', $form_errors); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Sahkan Kata Laluan <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <span class="material-symbols-outlined text-[20px]">lock</span>
                                </span>
                                <input type="password" name="confirm_password" id="reg-confirm-password" placeholder="Masukkan Semula Kata Laluan" 
                                       class="w-full pl-11 pr-11 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('confirm_password', $form_errors); ?>" required>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-300" id="confirm-status"></span>
                            </div>
                            <?php if (ferr('confirm_password', $form_errors)): ?>
                                <div class="field-error animate-shake"><span class="material-symbols-outlined">error</span> <?php echo ferr('confirm_password', $form_errors); ?></div>
                            <?php endif; ?>
                            <div class="field-error hidden" id="confirm-error"></div>
                        </div>

                        <!-- Role Dropdown -->
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Daftar Sebagai <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <span class="material-symbols-outlined text-[20px]">group</span>
                                </span>
                                <select name="role" id="reg-role"
                                        class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition bg-[#fcfcfc] text-sm text-gray-800 appearance-none" required>
                                    <option value="" disabled <?php echo empty(fval('role', $form_data)) ? 'selected' : ''; ?>>-- Pilih Peranan --</option>
                                    <option value="parent" <?php echo fval('role', $form_data) == 'parent' ? 'selected' : ''; ?>>Ibu Bapa / Penjaga</option>
                                    <option value="teacher" <?php echo fval('role', $form_data) == 'teacher' ? 'selected' : ''; ?>>Guru</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                                    <span class="material-symbols-outlined text-[18px]">expand_more</span>
                                </span>
                            </div>
                        </div>

                        <!-- Next Step Button -->
                        <button type="button" onclick="goToStep(2)" id="btn-next-1"
                                class="w-full bg-gradient-to-r from-[#ff6f91] to-[#ff9a76] hover:from-[#e05273] hover:to-[#ff6f91] text-white font-semibold py-3.5 px-4 rounded-xl transition-all duration-200 shadow-lg shadow-[#ff6f91]/20 flex items-center justify-center gap-2 text-sm mt-2 hover:shadow-xl active:scale-[0.98]">
                            <span>Seterusnya</span>
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </div>

                    <!-- ═══════════════════ STEP 2: Personal Info ═══════════════════ -->
                    <div id="step-2" class="space-y-4 hidden">

                        <!-- Full Name with character counter -->
                        <div>
                            <label class="flex justify-between text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">
                                <span>Nama Penuh <span class="text-red-400">*</span></span>
                                <span class="text-gray-300 normal-case tracking-normal" id="name-counter">0 / 100</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <span class="material-symbols-outlined text-[20px]">badge</span>
                                </span>
                                <input type="text" name="full_name" id="reg-fullname" placeholder="Masukkan Nama Penuh Seperti di IC" maxlength="100"
                                       value="<?php echo fval('full_name', $form_data); ?>"
                                       class="w-full pl-11 pr-4 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('full_name', $form_errors); ?>" required>
                            </div>
                            <?php if (ferr('full_name', $form_errors)): ?>
                                <div class="field-error animate-shake"><span class="material-symbols-outlined">error</span> <?php echo ferr('full_name', $form_errors); ?></div>
                            <?php endif; ?>
                            <div class="field-error hidden" id="fullname-error"></div>
                        </div>

                        <!-- IC Number & Phone in grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- IC Number -->
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">No. Kad Pengenalan <span class="text-red-400">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                        <span class="material-symbols-outlined text-[20px]">fingerprint</span>
                                    </span>
                                    <input type="text" name="ic_number" id="reg-ic" placeholder="900101-14-9999" maxlength="14"
                                           value="<?php echo fval('ic_number', $form_data); ?>"
                                           class="w-full pl-11 pr-4 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('ic_number', $form_errors); ?>" required>
                                </div>
                                <?php if (ferr('ic_number', $form_errors)): ?>
                                    <div class="field-error animate-shake"><span class="material-symbols-outlined">error</span> <?php echo ferr('ic_number', $form_errors); ?></div>
                                <?php endif; ?>
                                <div class="field-error hidden" id="ic-error"></div>
                            </div>
                            <!-- Phone Number -->
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">No. Telefon <span class="text-red-400">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                        <span class="material-symbols-outlined text-[20px]">phone</span>
                                    </span>
                                    <input type="tel" name="phone_number" id="reg-phone" placeholder="012-3456789" maxlength="15"
                                           value="<?php echo fval('phone_number', $form_data); ?>"
                                           class="w-full pl-11 pr-4 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('phone_number', $form_errors); ?>" required>
                                </div>
                                <?php if (ferr('phone_number', $form_errors)): ?>
                                    <div class="field-error animate-shake"><span class="material-symbols-outlined">error</span> <?php echo ferr('phone_number', $form_errors); ?></div>
                                <?php endif; ?>
                                <div class="field-error hidden" id="phone-error"></div>
                            </div>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Alamat Emel <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                                    <span class="material-symbols-outlined text-[20px]">mail</span>
                                </span>
                                <input type="email" name="email" id="reg-email" placeholder="contoh@email.com"
                                       value="<?php echo fval('email', $form_data); ?>"
                                       class="w-full pl-11 pr-10 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('email', $form_errors); ?>" required>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-300" id="email-status"></span>
                            </div>
                            <?php if (ferr('email', $form_errors)): ?>
                                <div class="field-error animate-shake"><span class="material-symbols-outlined">error</span> <?php echo ferr('email', $form_errors); ?></div>
                            <?php endif; ?>
                            <div class="field-error hidden" id="email-error"></div>
                        </div>

                        <!-- Race, Gender, Age in grid -->
                        <div class="grid grid-cols-3 gap-3">
                            <!-- Race -->
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Bangsa <span class="text-red-400">*</span></label>
                                <select name="race" id="reg-race" class="w-full py-3 px-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition bg-[#fcfcfc] text-sm text-gray-800 appearance-none" required>
                                    <option value="" disabled <?php echo empty(fval('race', $form_data)) ? 'selected' : ''; ?>>Pilih</option>
                                    <option value="Melayu" <?php echo fval('race', $form_data) == 'Melayu' ? 'selected' : ''; ?>>Melayu</option>
                                    <option value="Cina" <?php echo fval('race', $form_data) == 'Cina' ? 'selected' : ''; ?>>Cina</option>
                                    <option value="India" <?php echo fval('race', $form_data) == 'India' ? 'selected' : ''; ?>>India</option>
                                    <option value="Lain-lain" <?php echo fval('race', $form_data) == 'Lain-lain' ? 'selected' : ''; ?>>Lain-lain</option>
                                </select>
                            </div>
                            <!-- Gender -->
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Jantina <span class="text-red-400">*</span></label>
                                <select name="gender" id="reg-gender" class="w-full py-3 px-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition bg-[#fcfcfc] text-sm text-gray-800 appearance-none" required>
                                    <option value="" disabled <?php echo empty(fval('gender', $form_data)) ? 'selected' : ''; ?>>Pilih</option>
                                    <option value="Lelaki" <?php echo fval('gender', $form_data) == 'Lelaki' ? 'selected' : ''; ?>>Lelaki</option>
                                    <option value="Perempuan" <?php echo fval('gender', $form_data) == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                                </select>
                            </div>
                            <!-- Age -->
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Umur <span class="text-red-400">*</span></label>
                                <input type="number" name="age" id="reg-age" min="18" max="100" placeholder="Auto"
                                       value="<?php echo fval('age', $form_data) ?: ''; ?>"
                                       class="w-full py-3 px-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 text-center bg-gray-50/80 cursor-not-allowed <?php echo ferr_class('age', $form_errors); ?>" readonly required>
                            </div>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="flex gap-3 mt-2">
                            <button type="button" onclick="goToStep(1)"
                                    class="flex-1 border border-gray-200 text-gray-500 font-semibold py-3 px-4 rounded-xl transition hover:bg-gray-50 flex items-center justify-center gap-2 text-sm active:scale-[0.98]">
                                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                                <span>Kembali</span>
                            </button>
                            <button type="button" onclick="goToStep(3)" id="btn-next-2"
                                    class="flex-1 bg-gradient-to-r from-[#ff6f91] to-[#ff9a76] hover:from-[#e05273] hover:to-[#ff6f91] text-white font-semibold py-3 px-4 rounded-xl transition-all duration-200 shadow-lg shadow-[#ff6f91]/20 flex items-center justify-center gap-2 text-sm hover:shadow-xl active:scale-[0.98]">
                                <span>Seterusnya</span>
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </button>
                        </div>
                    </div>

                    <!-- ═══════════════════ STEP 3: Address ═══════════════════ -->
                    <div id="step-3" class="space-y-4 hidden">

                        <!-- Street Address -->
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Alamat Jalan <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute top-3.5 left-0 flex items-start pl-3.5 text-gray-400">
                                    <span class="material-symbols-outlined text-[20px]">home</span>
                                </span>
                                <input type="text" name="addr_street" id="reg-street" placeholder="No. rumah, Jalan, Taman"
                                       value="<?php echo fval('addr_street', $form_data); ?>"
                                       class="w-full pl-11 pr-4 py-3 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('addr_street', $form_errors); ?>" required>
                            </div>
                            <?php if (ferr('addr_street', $form_errors)): ?>
                                <div class="field-error animate-shake"><span class="material-symbols-outlined">error</span> <?php echo ferr('addr_street', $form_errors); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- City & Postal Code -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Bandar / Daerah <span class="text-red-400">*</span></label>
                                <input type="text" name="addr_city" id="reg-city" placeholder="Cth: Kuala Lumpur"
                                       value="<?php echo fval('addr_city', $form_data); ?>"
                                       class="w-full py-3 px-4 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('addr_city', $form_errors); ?>" required>
                                <?php if (ferr('addr_city', $form_errors)): ?>
                                    <div class="field-error animate-shake"><span class="material-symbols-outlined">error</span> <?php echo ferr('addr_city', $form_errors); ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Poskod <span class="text-red-400">*</span></label>
                                <input type="text" name="addr_postal" id="reg-postal" placeholder="Cth: 50000" maxlength="5"
                                       value="<?php echo fval('addr_postal', $form_data); ?>"
                                       class="w-full py-3 px-4 border rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition text-sm placeholder-gray-400 text-gray-800 <?php echo ferr_class('addr_postal', $form_errors); ?>" required>
                                <?php if (ferr('addr_postal', $form_errors)): ?>
                                    <div class="field-error animate-shake"><span class="material-symbols-outlined">error</span> <?php echo ferr('addr_postal', $form_errors); ?></div>
                                <?php endif; ?>
                                <div class="field-error hidden" id="postal-error"></div>
                            </div>
                        </div>

                        <!-- State & Country -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Negeri <span class="text-red-400">*</span></label>
                                <select name="addr_state" id="reg-state" class="w-full py-3 px-4 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition bg-[#fcfcfc] text-sm text-gray-800 appearance-none" required>
                                    <option value="" disabled <?php echo empty(fval('addr_state', $form_data)) ? 'selected' : ''; ?>>-- Pilih Negeri --</option>
                                    <?php 
                                    $states = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','W.P. Kuala Lumpur','W.P. Labuan','W.P. Putrajaya'];
                                    foreach($states as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo fval('addr_state', $form_data) == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Negara</label>
                                <input type="text" name="addr_country" id="reg-country" value="<?php echo fval('addr_country', $form_data) ?: 'Malaysia'; ?>"
                                       class="w-full py-3 px-4 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#ff6f91]/20 focus:border-[#ff6f91] transition bg-gray-50 text-sm text-gray-600" readonly>
                            </div>
                        </div>

                        <!-- Terms Agreement -->
                        <div class="flex items-start gap-3 p-3 bg-gray-50/50 rounded-xl border border-gray-100 mt-2">
                            <input type="checkbox" id="reg-agree" class="mt-1 rounded border-gray-300 text-[#ff6f91] focus:ring-[#ff6f91]/30" required>
                            <label for="reg-agree" class="text-xs text-gray-500 leading-relaxed cursor-pointer">
                                Saya bersetuju bahawa maklumat yang diberikan adalah benar dan tepat. Saya memahami akaun ini perlu diluluskan oleh <strong class="text-gray-700">Admin</strong> sebelum boleh digunakan.
                            </label>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="flex gap-3 mt-2">
                            <button type="button" onclick="goToStep(2)"
                                    class="flex-1 border border-gray-200 text-gray-500 font-semibold py-3 px-4 rounded-xl transition hover:bg-gray-50 flex items-center justify-center gap-2 text-sm active:scale-[0.98]">
                                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                                <span>Kembali</span>
                            </button>
                            <button type="submit" name="register" id="btn-submit"
                                    class="flex-1 bg-gradient-to-r from-[#ff6f91] to-[#ff9a76] hover:from-[#e05273] hover:to-[#ff6f91] text-white font-semibold py-3.5 px-4 rounded-xl transition-all duration-200 shadow-lg shadow-[#ff6f91]/20 flex items-center justify-center gap-2 text-sm hover:shadow-xl active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
                                <span>Daftar Sekarang</span>
                            </button>
                        </div>
                    </div>

                    <!-- Toggle Navigation Links -->
                    <div class="text-center text-xs mt-6 pt-4 border-t border-gray-50">
                        <a href="login.php" class="text-gray-500 hover:text-[#ff6f91] transition font-medium">
                            Sudah mempunyai akaun? <span class="font-bold text-[#ff6f91] underline underline-offset-2">Log Masuk</span>
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── BOTTOM BADGES BAR ── -->
    <div class="flex flex-wrap justify-center gap-2.5 mt-10 max-w-2xl px-4 text-[11px] font-semibold text-gray-500 select-none animate-fadeIn-d3">
        <div class="flex items-center gap-2 bg-white px-3.5 py-2 rounded-xl shadow-[0_2px_15px_rgba(0,0,0,0.02)] border border-gray-100/60">
            <span class="w-2 h-2 rounded-full bg-red-400"></span>
            <span>👶 Taska Care</span>
        </div>
        <div class="flex items-center gap-2 bg-white px-3.5 py-2 rounded-xl shadow-[0_2px_15px_rgba(0,0,0,0.02)] border border-gray-100/60">
            <span class="w-2 h-2 rounded-full bg-green-400"></span>
            <span>🎒 Tadika Edu</span>
        </div>
        <div class="flex items-center gap-2 bg-white px-3.5 py-2 rounded-xl shadow-[0_2px_15px_rgba(0,0,0,0.02)] border border-gray-100/60">
            <span class="w-2 h-2 rounded-full bg-blue-400"></span>
            <span>🕌 KAFA Care</span>
        </div>
        <div class="flex items-center gap-2 bg-white px-3.5 py-2 rounded-xl shadow-[0_2px_15px_rgba(0,0,0,0.02)] border border-gray-100/60">
            <span class="w-2 h-2 rounded-full bg-purple-400"></span>
            <span>📝 Laporan Harian</span>
        </div>
        <div class="flex items-center gap-2 bg-white px-3.5 py-2 rounded-xl shadow-[0_2px_15px_rgba(0,0,0,0.02)] border border-gray-100/60">
            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
            <span>📈 Perkembangan</span>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- JAVASCRIPT: Inline Validation, Password Checker, Steps -->
    <!-- ═══════════════════════════════════════════════════════ -->
    <script>
    // ── Toggle password visibility ──
    function togglePw(id, btn) {
        const input = document.getElementById(id);
        const icon = btn.querySelector('.material-symbols-outlined');
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            input.type = 'password';
            icon.textContent = 'visibility';
        }
    }

    <?php if ($mode == 'register'): ?>
    // ── STEP NAVIGATION ──
    let currentStep = 1;
    <?php 
    // If there are form errors, determine which step to show
    if (!empty($form_errors)) {
        $step1_fields = ['username', 'password', 'confirm_password', 'role'];
        $step2_fields = ['full_name', 'ic_number', 'phone_number', 'email', 'race', 'age'];
        $step3_fields = ['addr_street', 'addr_city', 'addr_state', 'addr_postal'];
        
        $errorStep = 1;
        foreach ($form_errors as $field => $msg) {
            if (in_array($field, $step2_fields)) $errorStep = max($errorStep, 2);
            if (in_array($field, $step3_fields)) $errorStep = 3;
        }
        echo "currentStep = " . $errorStep . ";";
    }
    ?>

    function goToStep(step) {
        // Validate current step before proceeding forward
        if (step > currentStep && !validateStep(currentStep)) return;

        // Hide all steps
        document.getElementById('step-1').classList.add('hidden');
        document.getElementById('step-2').classList.add('hidden');
        document.getElementById('step-3').classList.add('hidden');

        // Show target step
        document.getElementById('step-' + step).classList.remove('hidden');
        currentStep = step;

        // Update stepper dots
        for (let i = 1; i <= 3; i++) {
            const dot = document.getElementById('step-dot-' + i);
            dot.classList.remove('active', 'completed', 'inactive');
            if (i < step) {
                dot.classList.add('completed');
                dot.innerHTML = '<span class="material-symbols-outlined text-[16px]" style="font-variation-settings:\'FILL\' 1">check</span>';
            } else if (i === step) {
                dot.classList.add('active');
                dot.textContent = i;
            } else {
                dot.classList.add('inactive');
                dot.textContent = i;
            }
        }

        // Update step lines
        for (let i = 1; i <= 2; i++) {
            const line = document.getElementById('step-line-' + i);
            line.classList.remove('active', 'inactive');
            line.classList.add(i < step ? 'active' : 'inactive');
        }

        // Scroll to top of form
        document.getElementById('registerForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function validateStep(step) {
        let valid = true;
        
        if (step === 1) {
            const username = document.getElementById('reg-username');
            const password = document.getElementById('reg-password');
            const confirm = document.getElementById('reg-confirm-password');
            const role = document.getElementById('reg-role');

            if (!username.value.trim() || username.value.trim().length < 4) {
                showFieldError('username-error', 'Username mesti sekurang-kurangnya 4 aksara.');
                shakeField(username);
                valid = false;
            } else if (!/^[a-zA-Z0-9_]+$/.test(username.value.trim())) {
                showFieldError('username-error', 'Username hanya boleh mengandungi huruf, nombor dan underscore.');
                shakeField(username);
                valid = false;
            }

            if (!password.value || !checkAllPwReqs(password.value)) {
                shakeField(password);
                valid = false;
            }

            if (password.value !== confirm.value) {
                showFieldError('confirm-error', 'Kata laluan tidak sepadan.');
                shakeField(confirm);
                valid = false;
            }

            if (!role.value) {
                shakeField(role);
                valid = false;
            }
        }

        if (step === 2) {
            const fullname = document.getElementById('reg-fullname');
            const ic = document.getElementById('reg-ic');
            const phone = document.getElementById('reg-phone');
            const email = document.getElementById('reg-email');
            const race = document.getElementById('reg-race');
            const age = document.getElementById('reg-age');

            if (!fullname.value.trim() || fullname.value.trim().length < 3) {
                showFieldError('fullname-error', 'Nama penuh mesti sekurang-kurangnya 3 aksara.');
                shakeField(fullname);
                valid = false;
            }

            const icDigits = ic.value.replace(/[^0-9]/g, '');
            if (icDigits.length !== 12) {
                showFieldError('ic-error', 'No. IC mesti 12 digit.');
                shakeField(ic);
                valid = false;
            }

            const phoneDigits = phone.value.replace(/[^0-9]/g, '');
            if (phoneDigits.length < 10 || phoneDigits.length > 11) {
                showFieldError('phone-error', 'No. Telefon mesti 10-11 digit.');
                shakeField(phone);
                valid = false;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value.trim())) {
                showFieldError('email-error', 'Sila masukkan emel yang sah.');
                shakeField(email);
                valid = false;
            }

            if (!race.value) { shakeField(race); valid = false; }
            
            const ageVal = parseInt(age.value);
            if (!ageVal || ageVal < 18 || ageVal > 100) { shakeField(age); valid = false; }
        }

        return valid;
    }

    function showFieldError(id, msg) {
        const el = document.getElementById(id);
        if (el) {
            el.innerHTML = '<span class="material-symbols-outlined">error</span> ' + msg;
            el.classList.remove('hidden');
            el.classList.add('animate-shake');
            setTimeout(() => el.classList.remove('animate-shake'), 500);
        }
    }

    function clearFieldError(id) {
        const el = document.getElementById(id);
        if (el) { el.classList.add('hidden'); el.innerHTML = ''; }
    }

    function shakeField(el) {
        el.classList.add('animate-shake');
        el.classList.add('border-red-400');
        setTimeout(() => { el.classList.remove('animate-shake'); }, 500);
        setTimeout(() => { el.classList.remove('border-red-400'); }, 2000);
    }

    // ── PASSWORD STRENGTH CHECKER ──
    const pwInput = document.getElementById('reg-password');
    if (pwInput) {
        pwInput.addEventListener('input', function() {
            const pw = this.value;
            const meter = document.getElementById('pw-meter');
            
            const checks = {
                length: pw.length >= 8,
                upper: /[A-Z]/.test(pw),
                lower: /[a-z]/.test(pw),
                number: /[0-9]/.test(pw),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(pw)
            };

            // Update requirement icons
            Object.keys(checks).forEach(key => {
                const icon = document.getElementById('pw-icon-' + key);
                const row = document.getElementById('pw-req-' + key);
                if (checks[key]) {
                    icon.textContent = 'check_circle';
                    icon.classList.remove('text-gray-300');
                    icon.classList.add('text-green-500');
                    icon.style.fontVariationSettings = "'FILL' 1";
                    row.querySelector('span:last-child').classList.remove('text-gray-400');
                    row.querySelector('span:last-child').classList.add('text-green-600');
                } else {
                    icon.textContent = 'circle';
                    icon.classList.remove('text-green-500');
                    icon.classList.add('text-gray-300');
                    icon.style.fontVariationSettings = "'FILL' 0";
                    row.querySelector('span:last-child').classList.remove('text-green-600');
                    row.querySelector('span:last-child').classList.add('text-gray-400');
                }
            });

            // Calculate strength
            const passed = Object.values(checks).filter(Boolean).length;
            meter.className = 'pw-meter w-full mt-2';
            if (pw.length === 0) { meter.className = 'pw-meter w-full mt-2'; meter.style.background = '#e5e7eb'; }
            else if (passed <= 2) meter.classList.add('pw-weak');
            else if (passed <= 3) meter.classList.add('pw-fair');
            else if (passed <= 4) meter.classList.add('pw-good');
            else meter.classList.add('pw-strong');

            // Check confirm password match
            checkConfirmMatch();
        });
    }

    function checkAllPwReqs(pw) {
        return pw.length >= 8 && /[A-Z]/.test(pw) && /[a-z]/.test(pw) && /[0-9]/.test(pw) && /[!@#$%^&*(),.?":{}|<>]/.test(pw);
    }

    // ── CONFIRM PASSWORD MATCHER ──
    const confirmInput = document.getElementById('reg-confirm-password');
    if (confirmInput) {
        confirmInput.addEventListener('input', checkConfirmMatch);
    }

    function checkConfirmMatch() {
        const pw = document.getElementById('reg-password');
        const confirm = document.getElementById('reg-confirm-password');
        const status = document.getElementById('confirm-status');
        if (!pw || !confirm || !status) return;

        if (confirm.value.length === 0) {
            status.innerHTML = '';
            clearFieldError('confirm-error');
        } else if (pw.value === confirm.value) {
            status.innerHTML = '<span class="material-symbols-outlined text-green-500 text-[20px]" style="font-variation-settings:\'FILL\' 1">check_circle</span>';
            clearFieldError('confirm-error');
        } else {
            status.innerHTML = '<span class="material-symbols-outlined text-red-400 text-[20px]">cancel</span>';
            showFieldError('confirm-error', 'Kata laluan tidak sepadan.');
        }
    }

    // ── NAME CHARACTER COUNTER ──
    const nameInput = document.getElementById('reg-fullname');
    if (nameInput) {
        const counter = document.getElementById('name-counter');
        const updateCounter = () => {
            const len = nameInput.value.length;
            counter.textContent = len + ' / 100';
            counter.classList.toggle('text-red-400', len > 90);
            counter.classList.toggle('text-amber-400', len > 70 && len <= 90);
        };
        nameInput.addEventListener('input', updateCounter);
        updateCounter(); // Initial on prefill
    }

    // ── CALC AGE FROM IC ──
    function calculateAgeFromIC(icValue) {
        if (!icValue) return null;
        const digits = icValue.replace(/[^0-9]/g, '');
        if (digits.length < 6) return null;
        
        const yy = digits.substring(0, 2);
        const mm = digits.substring(2, 4);
        const dd = digits.substring(4, 6);
        
        const year2Digit = parseInt(yy, 10);
        const month = parseInt(mm, 10);
        const day = parseInt(dd, 10);
        
        if (isNaN(year2Digit) || isNaN(month) || isNaN(day) || month < 1 || month > 12 || day < 1 || day > 31) {
            return null;
        }
        
        const today = new Date();
        const currentYear = today.getFullYear();
        const currentYear2Digit = currentYear % 100;
        
        let birthYear = (year2Digit <= currentYear2Digit) ? (2000 + year2Digit) : (1900 + year2Digit);
        
        const birthDate = new Date(birthYear, month - 1, day);
        // Ensure date didn't wrap around (e.g. Feb 30 -> March 2)
        if (birthDate.getFullYear() !== birthYear || birthDate.getMonth() !== (month - 1) || birthDate.getDate() !== day) {
            return null;
        }
        
        let age = currentYear - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        return (age >= 0 && age <= 120) ? age : null;
    }

    // ── IC NUMBER AUTO-FORMAT & AUTO-CALC AGE & GENDER ──
    const icInput = document.getElementById('reg-ic');
    if (icInput) {
        const handleIcInput = function() {
            let v = this.value.replace(/[^0-9]/g, '');
            if (v.length > 12) v = v.substring(0, 12);
            
            // Format format as typing: XXXXXX-XX-XXXX
            if (v.length > 8) v = v.substring(0,6) + '-' + v.substring(6,8) + '-' + v.substring(8);
            else if (v.length > 6) v = v.substring(0,6) + '-' + v.substring(6);
            this.value = v;
            
            clearFieldError('ic-error');

            const digits = v.replace(/[^0-9]/g, '');
            const ageInput = document.getElementById('reg-age');
            
            if (digits.length >= 6) {
                const calculatedAge = calculateAgeFromIC(v);
                if (ageInput) {
                    if (calculatedAge !== null) {
                        ageInput.value = calculatedAge;
                        if (calculatedAge < 18 || calculatedAge > 100) {
                            showFieldError('ic-error', 'Umur dikesan dari IC ialah ' + calculatedAge + ' tahun (Minimum 18 tahun).');
                        }
                    } else {
                        ageInput.value = '';
                    }
                }
            } else {
                if (ageInput) ageInput.value = '';
            }

            // Auto-select gender: odd = Lelaki, even = Perempuan
            if (digits.length === 12) {
                const lastDigit = parseInt(digits.charAt(11), 10);
                const genderSelect = document.getElementById('reg-gender');
                if (genderSelect && !isNaN(lastDigit)) {
                    genderSelect.value = (lastDigit % 2 === 0) ? 'Perempuan' : 'Lelaki';
                }
            }
        };
        
        icInput.addEventListener('input', handleIcInput);
        
        // Trigger calculation on load for pre-filled data
        if (icInput.value) {
            handleIcInput.call(icInput);
        }
    }

    // ── PHONE NUMBER AUTO-FORMAT ──
    const phoneInput = document.getElementById('reg-phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            let v = this.value.replace(/[^0-9]/g, '');
            if (v.length > 11) v = v.substring(0, 11);
            if (v.length > 3) v = v.substring(0,3) + '-' + v.substring(3);
            this.value = v;
            clearFieldError('phone-error');
        });
    }

    // ── EMAIL INLINE VALIDATION ──
    const emailInput = document.getElementById('reg-email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const val = this.value.trim();
            const status = document.getElementById('email-status');
            if (!val) {
                status.innerHTML = '';
                clearFieldError('email-error');
                return;
            }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailRegex.test(val)) {
                status.innerHTML = '<span class="material-symbols-outlined text-green-500 text-[20px]" style="font-variation-settings:\'FILL\' 1">check_circle</span>';
                clearFieldError('email-error');
                this.classList.remove('border-red-400', 'bg-red-50/30');
            } else {
                status.innerHTML = '<span class="material-symbols-outlined text-red-400 text-[20px]">cancel</span>';
                showFieldError('email-error', 'Format emel tidak sah. Cth: name@domain.com');
                this.classList.add('border-red-400', 'bg-red-50/30');
            }
        });
        emailInput.addEventListener('input', function() {
            clearFieldError('email-error');
            document.getElementById('email-status').innerHTML = '';
        });
    }

    // ── USERNAME INLINE VALIDATION ──
    const usernameInput = document.getElementById('reg-username');
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            const val = this.value.trim();
            const status = document.getElementById('username-status');
            clearFieldError('username-error');

            if (!val) { status.innerHTML = ''; return; }
            
            if (val.length < 4) {
                status.innerHTML = '<span class="material-symbols-outlined text-amber-400 text-[18px]">pending</span>';
            } else if (!/^[a-zA-Z0-9_]+$/.test(val)) {
                status.innerHTML = '<span class="material-symbols-outlined text-red-400 text-[20px]">cancel</span>';
                showFieldError('username-error', 'Hanya huruf, nombor dan underscore dibenarkan.');
            } else {
                status.innerHTML = '<span class="material-symbols-outlined text-green-500 text-[20px]" style="font-variation-settings:\'FILL\' 1">check_circle</span>';
            }
        });
    }

    // ── POSTAL CODE VALIDATION ──
    const postalInput = document.getElementById('reg-postal');
    if (postalInput) {
        postalInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            clearFieldError('postal-error');
        });
        postalInput.addEventListener('blur', function() {
            if (this.value && !/^\d{5}$/.test(this.value)) {
                showFieldError('postal-error', 'Poskod mesti 5 digit.');
            }
        });
    }

    // ── TERMS CHECKBOX -> ENABLE SUBMIT ──
    const agreeCheckbox = document.getElementById('reg-agree');
    const submitBtn = document.getElementById('btn-submit');
    if (agreeCheckbox && submitBtn) {
        agreeCheckbox.addEventListener('change', function() {
            submitBtn.disabled = !this.checked;
        });
    }

    // ── Show correct step on load (for prefilled forms with errors) ──
    if (currentStep !== 1) {
        goToStep(currentStep);
    }
    <?php endif; ?>
    </script>

</body>
</html>