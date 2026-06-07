<?php
// admin_expenses.php
// Pelaporan Perbelanjaan & Kewangan - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Tambah Perbelanjaan Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $amount = (float)$_POST['amount'];
    $expense_date = $conn->real_escape_string($_POST['expense_date']);
    
    $sql = "INSERT INTO expenses (category, description, amount, expense_date, recorded_by) 
            VALUES ('$category', '$description', $amount, '$expense_date', $user_id)";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Rekod perbelanjaan berjaya ditambah.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

// Ambil Rekod Perbelanjaan
$sql_expenses = "SELECT e.*, u.username FROM expenses e LEFT JOIN users u ON e.recorded_by = u.id ORDER BY e.expense_date DESC";
$result = $conn->query($sql_expenses);

// Kira Jumlah Perbelanjaan
$total_expenses = 0;

// --- Data untuk Carta Stacked Bar (Chart.js) ---
$categories = [
    'Operasi',
    'Barangan Runcit & Makanan',
    'Alat Tulis & BBM',
    'Penyelenggaraan',
    'Lain-lain'
];

// 1. Data Harian (15 hari terakhir dengan perbelanjaan)
$sql_dates = "SELECT DISTINCT expense_date 
              FROM expenses 
              ORDER BY expense_date DESC 
              LIMIT 15";
$res_dates = $conn->query($sql_dates);
$daily_labels = [];
if ($res_dates) {
    while ($row = $res_dates->fetch_assoc()) {
        $daily_labels[] = $row['expense_date'];
    }
}
$daily_labels = array_reverse($daily_labels); // Susun kronologi

$daily_display_labels = [];
$daily_datasets = [];
if (!empty($daily_labels)) {
    $dates_placeholder = implode("','", array_map([$conn, 'real_escape_string'], $daily_labels));
    $sql_daily_cat = "SELECT expense_date, category, SUM(amount) as total 
                      FROM expenses 
                      WHERE expense_date IN ('$dates_placeholder') 
                      GROUP BY expense_date, category";
    $res_daily_cat = $conn->query($sql_daily_cat);
    
    $daily_map = [];
    if ($res_daily_cat) {
        while ($row = $res_daily_cat->fetch_assoc()) {
            $daily_map[$row['expense_date']][$row['category']] = (float)$row['total'];
        }
    }
    
    foreach ($daily_labels as $d) {
        $daily_display_labels[] = date('d/m', strtotime($d));
    }
    
    foreach ($categories as $cat) {
        $data_array = [];
        foreach ($daily_labels as $d) {
            $data_array[] = $daily_map[$d][$cat] ?? 0.0;
        }
        $daily_datasets[$cat] = $data_array;
    }
} else {
    $daily_datasets = array_fill_keys($categories, []);
}

// 2. Data Bulanan (12 bulan terakhir)
$sql_months = "SELECT DISTINCT DATE_FORMAT(expense_date, '%Y-%m') as ym 
               FROM expenses 
               ORDER BY ym DESC 
               LIMIT 12";
$res_months = $conn->query($sql_months);
$monthly_labels = [];
if ($res_months) {
    while ($row = $res_months->fetch_assoc()) {
        $monthly_labels[] = $row['ym'];
    }
}
$monthly_labels = array_reverse($monthly_labels); // Susun kronologi

$monthly_display_labels = [];
$monthly_datasets = [];
if (!empty($monthly_labels)) {
    $ym_placeholders = implode("','", array_map([$conn, 'real_escape_string'], $monthly_labels));
    $sql_monthly_cat = "SELECT DATE_FORMAT(expense_date, '%Y-%m') as ym, category, SUM(amount) as total 
                        FROM expenses 
                        WHERE DATE_FORMAT(expense_date, '%Y-%m') IN ('$ym_placeholders') 
                        GROUP BY ym, category";
    $res_monthly_cat = $conn->query($sql_monthly_cat);
    
    $monthly_map = [];
    if ($res_monthly_cat) {
        while ($row = $res_monthly_cat->fetch_assoc()) {
            $monthly_map[$row['ym']][$row['category']] = (float)$row['total'];
        }
    }
    
    foreach ($monthly_labels as $ym) {
        $monthly_display_labels[] = date('M Y', strtotime($ym . '-01'));
    }
    
    foreach ($categories as $cat) {
        $data_array = [];
        foreach ($monthly_labels as $ym) {
            $data_array[] = $monthly_map[$ym][$cat] ?? 0.0;
        }
        $monthly_datasets[$cat] = $data_array;
    }
} else {
    $monthly_datasets = array_fill_keys($categories, []);
}

// 3. Data Tahunan (5 tahun terakhir)
$sql_years = "SELECT DISTINCT DATE_FORMAT(expense_date, '%Y') as yr 
              FROM expenses 
              ORDER BY yr DESC 
              LIMIT 5";
$res_years = $conn->query($sql_years);
$yearly_labels = [];
if ($res_years) {
    while ($row = $res_years->fetch_assoc()) {
        $yearly_labels[] = $row['yr'];
    }
}
$yearly_labels = array_reverse($yearly_labels); // Susun kronologi

$yearly_display_labels = [];
$yearly_datasets = [];
if (!empty($yearly_labels)) {
    $yr_placeholders = implode("','", array_map([$conn, 'real_escape_string'], $yearly_labels));
    $sql_yearly_cat = "SELECT DATE_FORMAT(expense_date, '%Y') as yr, category, SUM(amount) as total 
                       FROM expenses 
                       WHERE DATE_FORMAT(expense_date, '%Y') IN ('$yr_placeholders') 
                       GROUP BY yr, category";
    $res_yearly_cat = $conn->query($sql_yearly_cat);
    
    $yearly_map = [];
    if ($res_yearly_cat) {
        while ($row = $res_yearly_cat->fetch_assoc()) {
            $yearly_map[$row['yr']][$row['category']] = (float)$row['total'];
        }
    }
    
    $yearly_display_labels = $yearly_labels;
    
    foreach ($categories as $cat) {
        $data_array = [];
        foreach ($yearly_labels as $yr) {
            $data_array[] = $yearly_map[$yr][$cat] ?? 0.0;
        }
        $yearly_datasets[$cat] = $data_array;
    }
} else {
    $yearly_datasets = array_fill_keys($categories, []);
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Perbelanjaan & Kewangan - Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #f44336; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"], input[type="date"], input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #f44336; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #d32f2f; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
        
        /* --- Chart CSS --- */
        .chart-card { background: white; padding: 25px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #dee2e6; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .filter-buttons { display: flex; gap: 8px; }
        .btn-filter { background: #e0e0e0; color: #333; border: none; padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 12px; transition: background 0.2s, color 0.2s; font-weight: 600; }
        .btn-filter:hover { background: #d0d0d0; }
        .btn-filter.active { background: #f44336; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📊 Laporan Perbelanjaan & Kewangan</h2>
        <?php echo $msg; ?>
        
        <div style="background: #ffebee; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffcdd2;">
            <h3 style="margin-top:0; color:#c62828;">Rekod Perbelanjaan Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="category" required>
                            <option value="Operasi">Operasi (Air, Elektrik, Sewa)</option>
                            <option value="Barangan Runcit & Makanan">Barangan Runcit & Makanan</option>
                            <option value="Alat Tulis & BBM">Alat Tulis & Bahan Mengajar</option>
                            <option value="Penyelenggaraan">Penyelenggaraan & Pembaikan</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tarikh Perbelanjaan</label>
                        <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Butiran / Penerangan</label>
                        <input type="text" name="description" required placeholder="Cth: Beli barang mentah untuk dapur">
                    </div>
                    <div class="form-group">
                        <label>Jumlah (RM)</label>
                        <input type="number" step="0.01" name="amount" required placeholder="Cth: 150.50">
                    </div>
                </div>
                <button type="submit" name="add_expense">Simpan Rekod Perbelanjaan</button>
            </form>
        </div>

        <!-- Chart Card for Expenditure Analysis -->
        <div class="chart-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin: 0; color: #333; display: flex; align-items: center; gap: 8px;">
                    <span>📈 Analisis Aliran Perbelanjaan</span>
                </h3>
                <div class="filter-buttons">
                    <button type="button" class="btn-filter active" onclick="setChartFilter('daily', event)">Harian</button>
                    <button type="button" class="btn-filter" onclick="setChartFilter('monthly', event)">Bulanan</button>
                    <button type="button" class="btn-filter" onclick="setChartFilter('yearly', event)">Tahunan</button>
                </div>
            </div>
            <div style="position: relative; height: 320px; width: 100%;">
                <canvas id="expensesChart"></canvas>
            </div>
        </div>

        <h3>Sejarah Perbelanjaan</h3>
        <table>
            <thead>
                <tr>
                    <th>Tarikh</th>
                    <th>Kategori</th>
                    <th>Butiran</th>
                    <th>Direkod Oleh</th>
                    <th>Jumlah (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $total_expenses += $row['amount'];
                    ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($row['expense_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['category']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td style="font-size: 12px; color: #777;"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td style="color: #d32f2f; font-weight: bold;">RM <?php echo number_format($row['amount'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <tr style="background-color: #fdfdfd;">
                        <td colspan="4" style="text-align: right; font-weight: bold; font-size: 16px;">JUMLAH KESELURUHAN:</td>
                        <td style="color: #c62828; font-weight: bold; font-size: 16px;">RM <?php echo number_format($total_expenses, 2); ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">Tiada rekod perbelanjaan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

    <script>
    // Categories color configuration
    const categoriesInfo = {
        'Operasi': {
            borderColor: '#2196F3',
            backgroundColor: 'rgba(33, 150, 243, 0.7)'
        },
        'Barangan Runcit & Makanan': {
            borderColor: '#FF9800',
            backgroundColor: 'rgba(255, 152, 0, 0.7)'
        },
        'Alat Tulis & BBM': {
            borderColor: '#4CAF50',
            backgroundColor: 'rgba(76, 175, 80, 0.7)'
        },
        'Penyelenggaraan': {
            borderColor: '#9C27B0',
            backgroundColor: 'rgba(156, 39, 176, 0.7)'
        },
        'Lain-lain': {
            borderColor: '#9E9E9E',
            backgroundColor: 'rgba(158, 158, 158, 0.7)'
        }
    };

    // Data passed from PHP
    const rawData = {
        daily: {
            labels: <?php echo json_encode($daily_display_labels); ?>,
            datasets: <?php echo json_encode($daily_datasets); ?>
        },
        monthly: {
            labels: <?php echo json_encode($monthly_display_labels); ?>,
            datasets: <?php echo json_encode($monthly_datasets); ?>
        },
        yearly: {
            labels: <?php echo json_encode($yearly_display_labels); ?>,
            datasets: <?php echo json_encode($yearly_datasets); ?>
        }
    };

    let expensesChart = null;

    function renderChart(filterType) {
        const ctx = document.getElementById('expensesChart').getContext('2d');
        const filterData = rawData[filterType];
        
        if (expensesChart) {
            expensesChart.destroy();
        }
        
        // Map category data streams to Chart.js dataset formats
        const chartDatasets = Object.keys(filterData.datasets).map(catName => {
            return {
                label: catName,
                data: filterData.datasets[catName],
                backgroundColor: categoriesInfo[catName].backgroundColor,
                borderColor: categoriesInfo[catName].borderColor,
                borderWidth: 1.5,
                borderRadius: 4,
                barPercentage: 0.5
            };
        });
        
        expensesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filterData.labels,
                datasets: chartDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 11,
                                family: 'Segoe UI'
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.raw !== null) {
                                    label += 'RM ' + context.raw.toLocaleString('ms-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'RM ' + value;
                            }
                        }
                    }
                }
            }
        });
    }

    function setChartFilter(filterType, event) {
        // Toggle active button highlight
        document.querySelectorAll('.btn-filter').forEach(btn => {
            btn.classList.remove('active');
        });
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        }
        
        renderChart(filterType);
    }

    // Auto-initialize the Daily filter chart on load
    document.addEventListener('DOMContentLoaded', () => {
        renderChart('daily');
    });
    </script>
</body>
</html>
