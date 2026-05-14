<?php
include 'db.php';

if (isset($_POST['submit'])) {
    $username   = $_POST['username'];
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama_ibubapa   = $_POST['nama_ibubapa'];
    $no_ic          = $_POST['no_ic'];
    $no_telefon     = $_POST['no_telefon'];
    $alamat         = $_POST['alamat'];
    $nama_anak      = $_POST['nama_anak'];
    $no_mykid       = $_POST['no_mykid'];
    $tarikh_lahir   = $_POST['tarikh_lahir'];
    $program        = $_POST['program'];
    $masalah_kesihatan = $_POST['masalah_kesihatan'];
    $status = 'Pending';

    $sql = "INSERT INTO enrollments (username, password, nama_ibubapa, no_ic, no_telefon, alamat, nama_anak, no_mykid, tarikh_lahir, program, masalah_kesihatan, status) VALUES ('$username', '$password', '$nama_ibubapa', '$no_ic', '$no_telefon', '$alamat', '$nama_anak', '$no_mykid', '$tarikh_lahir', '$program', '$masalah_kesihatan', '$status')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Pendaftaran berjaya. Menunggu kelulusan Admin.'); window.location.href='login.php';</script>";
    } else {
        echo "<script>alert('Ralat: " . $conn->error . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pendaftaran Baru - Sistem Pengurusan Kanak-Kanak</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter','Segoe UI',sans-serif;min-height:100vh;background:linear-gradient(135deg,#fce4ec 0%,#e8eaf6 50%,#e3f2fd 100%);display:flex;justify-content:center;align-items:flex-start;padding:40px 20px}
body::before,body::after{content:'';position:fixed;border-radius:50%;opacity:.08;z-index:0;animation:floatB 20s ease-in-out infinite}
body::before{width:400px;height:400px;background:#f48fb1;top:-100px;right:-100px}
body::after{width:300px;height:300px;background:#90caf9;bottom:-80px;left:-80px;animation-delay:-10s}
@keyframes floatB{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(30px,40px) scale(1.1)}}

.form-wrapper{width:100%;max-width:720px;position:relative;z-index:1}

.form-header{background:linear-gradient(135deg,#f8bbd0,#bbdefb);border-radius:20px 20px 0 0;padding:35px 40px;text-align:center;position:relative;overflow:hidden}
.form-header::after{content:'';position:absolute;bottom:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#f48fb1,#90caf9,#f48fb1);background-size:200% 100%;animation:shimmer 3s linear infinite}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.form-header .emoji-icon{font-size:48px;margin-bottom:10px;display:block;animation:bounce 2s ease-in-out infinite}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.form-header h1{font-size:26px;font-weight:700;color:#37474f;margin-bottom:6px}
.form-header p{font-size:14px;color:#546e7a}

.form-body{background:rgba(255,255,255,.92);backdrop-filter:blur(20px);border-radius:0 0 20px 20px;padding:40px;box-shadow:0 20px 60px rgba(0,0,0,.08),0 4px 16px rgba(0,0,0,.04)}

.section-title{display:flex;align-items:center;gap:12px;margin-bottom:24px;margin-top:10px}
.section-title:not(:first-child){margin-top:36px;padding-top:32px;border-top:1px solid #e0e0e0}
.section-badge{background:linear-gradient(135deg,#f48fb1,#ce93d8);color:#fff;font-size:12px;font-weight:700;padding:5px 14px;border-radius:20px;letter-spacing:.5px;white-space:nowrap}
.section-badge.blue{background:linear-gradient(135deg,#90caf9,#80deea)}
.section-badge.purple{background:linear-gradient(135deg,#ce93d8,#b39ddb)}
.section-title h2{font-size:18px;font-weight:600;color:#37474f}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.form-row.single{grid-template-columns:1fr}
.form-group{margin-bottom:22px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#455a64;margin-bottom:8px}
.form-group label .req{color:#ef5350;margin-left:2px}

.form-group input[type="text"],
.form-group input[type="password"],
.form-group input[type="date"],
.form-group select,
.form-group textarea{width:100%;padding:13px 16px;border:2px solid #e0e0e0;border-radius:12px;font-size:14px;font-family:'Inter',sans-serif;color:#37474f;background:#fafafa;transition:all .3s ease;outline:none}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#f48fb1;background:#fff;box-shadow:0 0 0 4px rgba(244,143,177,.12)}
.form-group textarea{resize:vertical;min-height:90px}
.form-group select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23546e7a' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 16px center}
.form-group .helper{display:block;font-size:11px;color:#90a4ae;margin-top:6px}

.btn-submit{display:block;width:100%;padding:16px;margin-top:32px;border:none;border-radius:14px;font-size:16px;font-weight:700;font-family:'Inter',sans-serif;color:#fff;background:linear-gradient(135deg,#f48fb1,#90caf9);background-size:200% 200%;cursor:pointer;transition:all .4s ease;letter-spacing:.5px}
.btn-submit:hover{background-position:100% 100%;transform:translateY(-2px);box-shadow:0 8px 25px rgba(244,143,177,.35)}
.btn-submit:active{transform:translateY(0)}

.back-link{display:block;text-align:center;margin-top:20px;color:#90a4ae;text-decoration:none;font-size:13px;font-weight:500;transition:color .3s}
.back-link:hover{color:#f48fb1}

@media(max-width:600px){
.form-row{grid-template-columns:1fr}
.form-header{padding:25px 20px}
.form-body{padding:25px 20px}
.form-header h1{font-size:22px}
}
</style>
</head>
<body>
<div class="form-wrapper">

<div class="form-header">
    <span class="emoji-icon">🧒</span>
    <h1>Pendaftaran Baharu</h1>
    <p>Sila lengkapkan semua maklumat di bawah untuk pendaftaran</p>
</div>

<div class="form-body">
<form method="POST" action="daftar_anak.php">

<!-- BAHAGIAN A -->
<div class="section-title">
    <span class="section-badge">BAHAGIAN A</span>
    <h2>Maklumat Akaun</h2>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Username <span class="req">*</span></label>
        <input type="text" name="username" placeholder="Cth: ibu_ali2024" required>
        <span class="helper">ID log masuk unik anda</span>
    </div>
    <div class="form-group">
        <label>Kata Laluan <span class="req">*</span></label>
        <input type="password" name="password" placeholder="Minimum 6 aksara" required>
        <span class="helper">Gunakan kombinasi huruf & nombor</span>
    </div>
</div>

<!-- BAHAGIAN B -->
<div class="section-title">
    <span class="section-badge blue">BAHAGIAN B</span>
    <h2>Maklumat Ibu Bapa / Penjaga</h2>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Nama Penuh <span class="req">*</span></label>
        <input type="text" name="nama_ibubapa" placeholder="Cth: Siti Aminah binti Yusof" required>
    </div>
    <div class="form-group">
        <label>No. Kad Pengenalan <span class="req">*</span></label>
        <input type="text" name="no_ic" placeholder="Cth: 850612-14-5678" required>
        <span class="helper">Format: XXXXXX-XX-XXXX</span>
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label>No. Telefon <span class="req">*</span></label>
        <input type="text" name="no_telefon" placeholder="Cth: 012-3456789" required>
    </div>
    <div class="form-group"></div>
</div>
<div class="form-row single">
    <div class="form-group">
        <label>Alamat Rumah <span class="req">*</span></label>
        <textarea name="alamat" placeholder="Masukkan alamat penuh termasuk poskod dan negeri..." required></textarea>
    </div>
</div>

<!-- BAHAGIAN C -->
<div class="section-title">
    <span class="section-badge purple">BAHAGIAN C</span>
    <h2>Maklumat Kanak-Kanak</h2>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Nama Penuh Anak <span class="req">*</span></label>
        <input type="text" name="nama_anak" placeholder="Cth: Muhammad Ali bin Ahmad" required>
    </div>
    <div class="form-group">
        <label>No. MyKid <span class="req">*</span></label>
        <input type="text" name="no_mykid" placeholder="Cth: 200315-14-1234" required>
        <span class="helper">12 digit nombor MyKid</span>
    </div>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Tarikh Lahir <span class="req">*</span></label>
        <input type="date" name="tarikh_lahir" required>
    </div>
    <div class="form-group">
        <label>Pilihan Program <span class="req">*</span></label>
        <select name="program" required>
            <option value="">-- Sila Pilih Program --</option>
            <option value="Taska">Taska (Awal Kanak-Kanak)</option>
            <option value="Tadika">Tadika (Prasekolah)</option>
            <option value="KAFA Care">KAFA Care (Transit & Agama)</option>
        </select>
    </div>
</div>
<div class="form-row single">
    <div class="form-group">
        <label>Masalah Kesihatan / Alahan</label>
        <textarea name="masalah_kesihatan" placeholder="Nyatakan sebarang alahan makanan, ubat-ubatan, atau masalah kesihatan (jika ada)..."></textarea>
        <span class="helper">Kosongkan jika tiada masalah kesihatan</span>
    </div>
</div>

<button type="submit" name="submit" class="btn-submit">📋 Hantar Pendaftaran</button>
<a href="login.php" class="back-link">⬅ Sudah ada akaun? Kembali ke Log Masuk</a>

</form>
</div>
</div>
</body>
</html>