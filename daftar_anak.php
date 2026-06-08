<?php
// daftar_anak.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['daftar_pelajar'])) {
    // Maklumat Anak
    $nama_penuh = $_POST['full_name'];
    $mykid = $_POST['mykid'];
    $modul = $_POST['module'];
    $kesihatan = $_POST['health_record'];
    $alahan = $_POST['allergies'];
    
    // Alamat Pelajar (Revise Baru)
    $alamat = $_POST['address'];
    $poskod = $_POST['postcode'];
    $negeri = $_POST['state'];

    // Maklumat Ibu Bapa / Penjaga
    $p_name = $_POST['parent_name'];
    $p_ic = $_POST['parent_ic'];
    $p_employer = $_POST['employer_name'];
    $p_emp_address = $_POST['employer_address'];
    $p_email = $_POST['parent_email'];
    $p_phone = $_POST['parent_phone'];
    
    // Validasi: Wajib Isi [cite: 61]
    if(empty($nama_penuh) || empty($mykid) || empty($alamat) || empty($poskod) || empty($negeri)) {
        echo "<script>alert('Sila pastikan maklumat pelajar dan alamat telah diisi!'); window.history.back();</script>";
        exit();
    }

    $target_dir = "uploads/";
    $mykid_file = $target_dir . time() . "_" . basename($_FILES["file_mykid"]["name"]);
    move_uploaded_file($_FILES["file_mykid"]["tmp_name"], $mykid_file);

    $kesihatan_file = $target_dir . time() . "_" . basename($_FILES["file_kesihatan"]["name"]);
    move_uploaded_file($_FILES["file_kesihatan"]["tmp_name"], $kesihatan_file);

    // Dapatkan parent_id dari jadual parents
    $sql_parent = "SELECT id FROM parents WHERE user_id = $user_id LIMIT 1";
    $res_parent = $conn->query($sql_parent);
    if ($res_parent && $res_parent->num_rows > 0) {
        $parent = $res_parent->fetch_assoc();
        $parent_id = (int)$parent['id'];
    } else {
        // Jika tiada rekod parent, cipta satu rekod baru dalam jadual parents
        $p_name_esc = $conn->real_escape_string($p_name);
        $p_ic_esc = $conn->real_escape_string($p_ic);
        $p_phone_esc = $conn->real_escape_string($p_phone);
        $alamat_esc = $conn->real_escape_string($alamat);
        $sql_insert_parent = "INSERT INTO parents (user_id, full_name, ic_number, phone_number, address) 
                              VALUES ($user_id, '$p_name_esc', '$p_ic_esc', '$p_phone_esc', '$alamat_esc')";
        if ($conn->query($sql_insert_parent)) {
            $parent_id = $conn->insert_id;
        } else {
            echo "<script>alert('Ralat membuat profil penjaga: " . $conn->error . "'); window.history.back();</script>";
            exit();
        }
    }

    // SQL INSERT yang telah dikemaskini dengan parent_id (bukan user_id)
    $sql = "INSERT INTO students (parent_id, full_name, mykid_number, module, health_record, allergies, 
            address, postcode, state,
            parent_name, parent_ic, parent_phone, parent_email, employer_name, employer_address) 
            VALUES ('$parent_id', '$nama_penuh', '$mykid', '$modul', '$kesihatan', '$alahan', 
            '$alamat', '$poskod', '$negeri',
            '$p_name', '$p_ic', '$p_phone', '$p_email', '$p_employer', '$p_emp_address')";
            
    if ($conn->query($sql) === TRUE) {
        if (function_exists('catat_log')) {
            catat_log($conn, $user_id, $_SESSION['username'], "Mendaftarkan pelajar baru: $nama_penuh", "Success");
        }
        echo "<script>alert('Pendaftaran berjaya dihantar!'); window.location.href='home.php';</script>";
    } else {
        echo "<script>alert('Ralat: " . $conn->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Daftar Pelajar Baru</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f0f8ff; margin: 0; padding: 40px 20px; display: flex; justify-content: center; }
        .form-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 750px; width: 100%; border-top: 8px solid #84b6f4; }
        .form-container h2 { color: #84b6f4; text-align: center; margin-top: 0; }
        .section-title { background: #eef6ff; padding: 10px; border-radius: 5px; color: #444; font-weight: bold; margin: 25px 0 15px 0; border-left: 4px solid #84b6f4; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; color: #444; margin-bottom: 5px; }
        input[type="text"], input[type="email"], select, textarea, input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .row { display: flex; gap: 15px; }
        .row .form-group { flex: 1; }
        button.btn-submit { background-color: #84b6f4; color: white; padding: 15px; border: none; border-radius: 8px; width: 100%; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 20px; }
        .btn-back { display: block; text-align: center; margin-top: 20px; color: #666; text-decoration: none; }
    </style>
</head>
<body>
<?php include 'sidebar_parent.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="form-container">
        <h2>Pendaftaran Pelajar Baru 🧸</h2>
        
        <form method="POST" action="daftar_anak.php" enctype="multipart/form-data">
            
            <div class="section-title">A. Maklumat Pelajar</div>
            <div class="form-group">
                <label>Nama Penuh Anak</label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Nombor MyKid</label>
                <input type="text" name="mykid" required>
            </div>
            
            <div class="form-group">
                <label>Alamat Rumah</label>
                <textarea name="address" required placeholder="Alamat lengkap kediaman"></textarea>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>Poskod</label>
                    <input type="text" name="postcode" required placeholder="Cth: 06010">
                </div>
                <div class="form-group">
                    <label>Negeri</label>
                    <select name="state" required>
                        <option value="">-- Pilih Negeri --</option>
                        <option value="Kedah">Kedah</option>
                        <option value="Pulau Pinang">Pulau Pinang</option>
                        <option value="Perlis">Perlis</option>
                        <option value="Perak">Perak</option>
                        <option value="Selangor">Selangor</option>
                        <option value="Kuala Lumpur">Kuala Lumpur</option>
                        <option value="Negeri Sembilan">Negeri Sembilan</option>
                        <option value="Melaka">Melaka</option>
                        <option value="Johor">Johor</option>
                        <option value="Pahang">Pahang</option>
                        <option value="Terengganu">Terengganu</option>
                        <option value="Kelantan">Kelantan</option>
                        <option value="Sabah">Sabah</option>
                        <option value="Sarawak">Sarawak</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Modul / Penempatan Kelas</label>
                <select name="module" required>
                    <option value="">-- Sila Pilih --</option>
                    <option value="Taska">Taska (Childcare)</option>
                    <option value="Tadika">Tadika (Kindergarten)</option>
                    <option value="KAFA Care">KAFA Care (Transit)</option>
                </select>
            </div>

            <div class="section-title">B. Maklumat Ibu Bapa / Penjaga</div>
            <div class="form-group">
                <label>Nama Penuh Penjaga</label>
                <input type="text" name="parent_name" required>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>No. IC Penjaga</label>
                    <input type="text" name="parent_ic" required>
                </div>
                <div class="form-group">
                    <label>No. Telefon</label>
                    <input type="text" name="parent_phone" required>
                </div>
            </div>
            <div class="form-group">
                <label>Emel</label>
                <input type="email" name="parent_email" required>
            </div>
            <div class="form-group">
                <label>Nama Majikan</label>
                <input type="text" name="employer_name" required>
            </div>
            <div class="form-group">
                <label>Alamat Majikan</label>
                <textarea name="employer_address" required></textarea>
            </div>

            <div class="section-title">C. Rekod Kesihatan & Dokumen</div>
            <div class="form-group">
                <label>Rekod Kesihatan Umum</label>
                <textarea name="health_record"></textarea>
            </div>
            <div class="form-group">
                <label>Alahan (Allergies)</label>
                <textarea name="allergies"></textarea>
            </div>

            <div class="form-group">
                <label>Salinan MyKid (JPG/PDF)</label>
                <input type="file" name="file_mykid" required>
            </div>
            <div class="form-group">
                <label>Rekod Kesihatan / Buku Cucuk</label>
                <input type="file" name="file_kesihatan" required>
            </div>

            <button type="submit" name="daftar_pelajar" class="btn-submit">Hantar Pendaftaran</button>
            <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard</a>
            
        </form>
    </div>


</main>
</body>
</html>