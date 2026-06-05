<?php
// teacher_inventory_request.php
// Permohonan Inventori - Guru
session_start();
require 'db.php';

// Pastikan hanya cikgu yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$msg = '';

// --- PROSES HANTAR PERMOHONAN INVENTORI (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mohon_inventori'])) {
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $quantity = (int)$_POST['quantity'];
    $reason = $conn->real_escape_string($_POST['reason']);

    if (!empty($item_name) && $quantity > 0) {
        $sql = "INSERT INTO inventory_requests (requested_by, item_name, quantity, reason, status) 
                VALUES ('$user_id', '$item_name', '$quantity', '$reason', 'Pending')";
        if ($conn->query($sql)) {
            $msg = "<div class='alert success'>Permohonan inventori berjaya dihantar!</div>";
        } else {
            $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
        }
    } else {
        $msg = "<div class='alert error'>Sila lengkapkan semua ruangan wajib.</div>";
    }
}

// --- AMBIL SENARAI INVENTORI SEDIA ADA ---
$inventory_list = [];
$inv_res = $conn->query("SELECT * FROM inventory ORDER BY item_name ASC");
if ($inv_res) {
    while ($row = $inv_res->fetch_assoc()) {
        $inventory_list[] = $row;
    }
}

// --- AMBIL SEJARAH PERMOHONAN GURU INI SAHAJA (LIMITASI CAPASITI AKSES) ---
$my_requests = [];
$req_res = $conn->query("SELECT * FROM inventory_requests WHERE requested_by = '$user_id' ORDER BY created_at DESC");
if ($req_res) {
    while ($row = $req_res->fetch_assoc()) {
        $my_requests[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Permohonan Inventori - Guru</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            background-color: #f4f7f6; 
            margin: 0; 
            padding: 20px;
        }
        .header-bar {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-left: 5px solid #ffb347;
        }
        .main-container {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .panel {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .left-panel { flex: 4; }
        .right-panel { flex: 6; border-top: 5px solid #84b6f4; }
        
        h3 { color: #555; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; }
        label { font-weight: bold; color: #666; font-size: 13px; }
        
        select, input[type="text"], input[type="number"], textarea {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-family: inherit;
        }
        
        button {
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            border: none;
            transition: 0.2s;
        }
        .btn-submit { background-color: #ffb347; color: white; width: 100%; font-size: 15px; }
        .btn-submit:hover { background-color: #e67e22; }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        th { background-color: #f9f9f9; padding: 10px; text-align: left; color: #444; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-pending { background: #ffe4b5; color: #d2691e; }
        .badge-approved { background: #b5ead7; color: #0e6251; }
        .badge-fulfilled { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        
        .stock-warning { color: #e74c3c; font-weight: bold; }
        .stock-ok { color: #2ecc71; }
        
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
    </style>
    <script>
        function fillItem(name) {
            document.getElementById('item_name').value = name;
        }
    </script>
</head>
<body>

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">📦 Permohonan Stok & Inventori</h2>
    </div>

    <?php echo $msg; ?>

    <div class="main-container">
        
        <!-- Panel Form Permohonan -->
        <div class="panel left-panel">
            <h3>✍️ Borang Permohonan Baru</h3>
            <form method="POST" action="teacher_inventory_request.php">
                
                <div class="form-group">
                    <label>Nama Barang</label>
                    <input type="text" name="item_name" id="item_name" placeholder="Cth: Kertas Lukisan A4 / Sabun Tangan" required>
                    <span style="font-size:11px; color:#777; margin-top:4px;">* Anda juga boleh klik pada barangan dalam senarai stok di sebelah kanan untuk mengisi ruangan ini.</span>
                </div>

                <div class="form-group">
                    <label>Kuantiti</label>
                    <input type="number" name="quantity" min="1" value="1" required>
                </div>

                <div class="form-group">
                    <label>Tujuan / Justifikasi Penggunaan</label>
                    <textarea name="reason" placeholder="Cth: Untuk kegunaan aktiviti seni melukis Kelas Ceria minggu hadapan." rows="4" required></textarea>
                </div>

                <button type="submit" name="mohon_inventori" class="btn-submit">💾 Hantar Permohonan</button>
            </form>
        </div>

        <!-- Panel Senarai Stok & Sejarah Permohonan -->
        <div class="panel right-panel">
            
            <h3>📋 Status Stok Inventori Semasa</h3>
            <div style="max-height: 200px; overflow-y: auto; margin-bottom: 25px;">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Kuantiti Semasa</th>
                            <th>Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($inventory_list) > 0): ?>
                            <?php foreach ($inventory_list as $inv): 
                                $stockClass = ($inv['quantity'] <= $inv['min_stock_level']) ? 'stock-warning' : 'stock-ok';
                            ?>
                                <tr style="cursor:pointer;" onclick="fillItem('<?php echo htmlspecialchars($inv['item_name'], ENT_QUOTES); ?>')">
                                    <td><strong><?php echo htmlspecialchars($inv['item_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($inv['category']); ?></td>
                                    <td class="<?php echo $stockClass; ?>"><?php echo $inv['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($inv['unit']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; color:#888;">Tiada rekod stok inventori.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h3>🕒 Sejarah Permohonan Anda</h3>
            <div style="max-height: 250px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Tarikh</th>
                            <th>Barangan (Kuantiti)</th>
                            <th>Justifikasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($my_requests) > 0): ?>
                            <?php foreach ($my_requests as $req): 
                                $statusClass = 'badge-pending';
                                if ($req['status'] == 'Approved') $statusClass = 'badge-approved';
                                if ($req['status'] == 'Fulfilled') $statusClass = 'badge-fulfilled';
                                if ($req['status'] == 'Rejected') $statusClass = 'badge-rejected';
                            ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($req['created_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($req['item_name']); ?></strong> (<?php echo $req['quantity']; ?>)</td>
                                    <td><?php echo htmlspecialchars($req['reason']); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $req['status']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; color:#888; padding:15px;">Tiada rekod permohonan inventori dibuat.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>

</body>
</html>
