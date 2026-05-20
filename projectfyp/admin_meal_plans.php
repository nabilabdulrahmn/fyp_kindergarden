<?php
// admin_meal_plans.php
// Perancangan Makanan & Diet - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_meal'])) {
    $meal_date = $conn->real_escape_string($_POST['meal_date']);
    $meal_type = $conn->real_escape_string($_POST['meal_type']);
    $menu_description = $conn->real_escape_string($_POST['menu_description']);
    $allergens = $conn->real_escape_string($_POST['allergens']);
    
    $sql = "INSERT INTO meal_plans (meal_date, meal_type, menu_description, allergens, created_by) 
            VALUES ('$meal_date', '$meal_type', '$menu_description', '$allergens', $user_id)";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Menu berjaya ditambah.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

$sql_meals = "SELECT * FROM meal_plans ORDER BY meal_date DESC, FIELD(meal_type, 'Breakfast', 'Lunch', 'Snack')";
$result = $conn->query($sql_meals);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Perancangan Makanan - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #ff5722; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"], input[type="date"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #ff5722; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #e64a19; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .allergen-tag { background: #ffebee; color: #c62828; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; border: 1px solid #ffcdd2;}
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🍽️ Perancangan Makanan & Pemakanan</h2>
        <?php echo $msg; ?>
        
        <div style="background: #fbe9e7; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffccbc;">
            <h3 style="margin-top:0; color:#d84315;">Tetapkan Menu Harian</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Tarikh Menu</label>
                        <input type="date" name="meal_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Waktu Makan</label>
                        <select name="meal_type" required>
                            <option value="Breakfast">Sarapan Pagi (Breakfast)</option>
                            <option value="Lunch">Makan Tengah Hari (Lunch)</option>
                            <option value="Snack">Minum Petang (Snack)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Penerangan Menu</label>
                        <input type="text" name="menu_description" required placeholder="Cth: Nasi Lemak, Telur Rebus, Timun, Sambal, Susu">
                    </div>
                    <div class="form-group">
                        <label>Amaran Alergi (Jika Ada)</label>
                        <input type="text" name="allergens" placeholder="Cth: Kacang, Tenusu (Lactose), Telur">
                    </div>
                </div>
                <button type="submit" name="add_meal">+ Tambah Menu Ke Jadual</button>
            </form>
        </div>

        <h3>Jadual Menu Semasa</h3>
        <table>
            <thead>
                <tr>
                    <th>Tarikh</th>
                    <th>Waktu Makan</th>
                    <th>Menu Dihidangkan</th>
                    <th>Amaran Alahan (Allergens)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo date('d/m/Y', strtotime($row['meal_date'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['meal_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['menu_description']); ?></td>
                            <td>
                                <?php if (!empty($row['allergens'])): ?>
                                    <span class="allergen-tag">⚠️ <?php echo htmlspecialchars($row['allergens']); ?></span>
                                <?php else: ?>
                                    <span style="color:#aaa; font-size:12px;">Tiada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Tiada jadual pemakanan ditetapkan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
