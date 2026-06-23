<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bhw_system");
if ($conn->connect_error) { die("❌ Connection failed: " . $conn->connect_error); }

/* ===== 1. DATE & FILTER LOGIC (Real-time 2026) ===== */
$today = date("Y-m-d"); 
$currentYear = 2026;
$selectedYear = isset($_GET['year_filter']) ? $_GET['year_filter'] : $currentYear;
$selectedMonth = isset($_GET['month_filter']) ? $_GET['month_filter'] : date('n'); 
$timeRange = isset($_GET['time_range']) ? $_GET['time_range'] : 'all';

/* ===== 2. FETCH STATS CARDS DATA (SYNCED WITH INVENTORY) ===== */
$totalHeads = $conn->query("SELECT COUNT(*) AS total FROM residents")->fetch_assoc()['total'] ?? 0;
$totalMembers = $conn->query("SELECT COUNT(*) AS total FROM family_members")->fetch_assoc()['total'] ?? 0;
$totalResidents = $totalHeads + $totalMembers;

// Out of Stock = Quantity 0 pero HINDI pa expired
$outOfStock = $conn->query("SELECT COUNT(*) AS total FROM medicines WHERE quantity <= 0 AND expiry_date > '$today'")->fetch_assoc()['total'] ?? 0;

// Expired = Binibilang lahat ng expired sa main table
$expiredStock = $conn->query("SELECT COUNT(*) AS total FROM medicines WHERE expiry_date <= '$today'")->fetch_assoc()['total'] ?? 0;

$totalMedicines = $conn->query("SELECT COUNT(*) AS total FROM medicines")->fetch_assoc()['total'] ?? 0;

/* ===== 3. GRAPH DATA LOGIC (CONNECTED TO TRANSACTIONS & CONSULTATIONS) ===== */
$conditions = ["YEAR(logs.d_date) = $selectedYear"];
if ($timeRange == 'week') {
    $conditions[] = "WEEK(logs.d_date, 1) = WEEK(CURDATE(), 1)";
} elseif ($timeRange == 'month') {
    $conditions[] = "MONTH(logs.d_date) = $selectedMonth";
}
$where_clause = implode(" AND ", $conditions);

/** * ACCURACY LOGIC:
 * Kinukuha natin ang 'quantity_given' mula sa consultations 
 * AT ang 'quantity' mula sa transactions kung saan ang type ay HINDI 'restock'
 */
$trending_query = "
    SELECT m.medicine_name, IFNULL(SUM(logs.qty), 0) as total_used
    FROM medicines m
    LEFT JOIN (
        SELECT medicine_id, quantity_given as qty, date_visit as d_date FROM consultations 
        UNION ALL
        SELECT medicine_id, quantity as qty, date_created as d_date FROM transactions 
        WHERE type IN ('dispensed', 'out', 'release', 'consumption') 
    ) as logs ON m.id = logs.medicine_id
    WHERE $where_clause
    GROUP BY m.id, m.medicine_name
    HAVING total_used > 0
    ORDER BY total_used DESC
    LIMIT 10";

$trending_res = $conn->query($trending_query);
$medNames = []; $medUsage = [];
while($row = $trending_res->fetch_assoc()){ 
    $medNames[] = $row['medicine_name']; 
    $medUsage[] = $row['total_used']; 
}

$months_map = [1=>"Jan", 2=>"Feb", 3=>"Mar", 4=>"Apr", 5=>"May", 6=>"Jun", 7=>"Jul", 8=>"Aug", 9=>"Sep", 10=>"Oct", 11=>"Nov", 12=>"Dec"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>BHW Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; display: flex; color: #333; }
        
        /* Sidebar Styles */
        .sidebar { width: 240px; position: fixed; height: 100vh; background: #fff; padding: 25px 20px; border-right: 1px solid #eee; z-index: 100; }
        .sidebar h2 { color: #DC143C; text-align: center; margin-bottom: 30px; font-weight: 800; font-size: 24px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #555; text-decoration: none; border-radius: 12px; margin-bottom: 8px; font-weight: 600; transition: 0.3s; }
        .sidebar-menu a:hover { background: #f0f0f5; color: #DC143C; }
        .sidebar-menu a.active { background: #DC143C; color: white; box-shadow: 0 4px 12px rgba(118, 75, 162, 0.3); }

        /* Main Content */
        .main-content { margin-left: 240px; padding: 40px; width: calc(100% - 240px); }
        .header-area { margin-bottom: 30px; }
        .header-area h1 { font-size: 28px; color: #2d3436; }
        
        /* Stats Cards */
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; text-align: center; text-decoration: none; color: inherit; box-shadow: 0 5px 15px rgba(0,0,0,0.02); transition: 0.3s; border-bottom: 5px solid #eee; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px rgba(0,0,0,0.08); }
        .stat-number { font-size: 36px; font-weight: 800; display: block; margin-bottom: 5px; color: #2d3436; }
        .stat-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #a0a0a0; letter-spacing: 1px; }

        /* Filters */
        .filter-panel { background: white; padding: 20px 25px; border-radius: 15px; margin-bottom: 25px; display: flex; gap: 25px; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .filter-group { display: flex; align-items: center; gap: 10px; font-weight: 600; color: #666; }
        select { padding: 8px 15px; border-radius: 10px; border: 1px solid #e0e0e0; background: #f9f9f9; cursor: pointer; font-weight: 600; color: #444; }

        /* Chart Box */
        .container-box { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.04); }
        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; color: #2d3436; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>👩‍⚕️ BHW </h2>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
        
        <a href="inventory_actions.php"><i class="fas fa-clipboard-list"></i> Stock Reports</a>
        <a href="inventory.php"><i class="fas fa-pills"></i> Inventory</a>
        <a href="residents.php"><i class="fas fa-users"></i> Residents</a>
         <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="header-area">
        <h1>Dashboard Overview</h1>
        <p style="color: #888;">Monitoring Health Data & Medicine Supply for <strong>2026</strong></p>
    </div>

    <div class="card-grid">
    <a href="residents.php" class="stat-card" style="border-color:#a29bfe">
        <span class="stat-number"><?= number_format($totalResidents) ?></span>
        <span class="stat-label">Total Residents</span>
    </a>

    <a href="inventory.php?filter=outofstock" class="stat-card" style="border-color:#ff7675">
        <span class="stat-number" style="color: #d63031;"><?= $outOfStock ?></span>
        <span class="stat-label">Out of Stock</span>
    </a>

    <a href="inventory.php?filter=expired" class="stat-card" style="border-color:#fab1a0">
        <span class="stat-number" style="color: #e17055;"><?= $expiredStock ?></span>
        <span class="stat-label">Expired Meds</span>
    </a>

    <a href="inventory.php" class="stat-card" style="border-color:#74b9ff">
        <span class="stat-number"><?= $totalMedicines ?></span>
        <span class="stat-label">Medicine Types</span>
    </a>
</div>

    <form method="GET" class="filter-panel">
        <div class="filter-group">
            <i class="fas fa-calendar-alt" style="color:#DC143C"></i> Year:
            <select name="year_filter" onchange="this.form.submit()">
                <?php for($y=2026; $y>=2021; $y--): ?>
                    <option value="<?= $y ?>" <?= ($selectedYear == $y) ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <i class="fas fa-filter" style="color:#DC143C"></i> Range:
            <select name="time_range" onchange="this.form.submit()">
                <option value="all" <?= ($timeRange == 'all') ? 'selected' : '' ?>>Entire Year</option>
                <option value="month" <?= ($timeRange == 'month') ? 'selected' : '' ?>>Specific Month</option>
                <option value="week" <?= ($timeRange == 'week') ? 'selected' : '' ?>>This Week</option>
            </select>
        </div>

        <?php if($timeRange == 'month'): ?>
        <div class="filter-group">
            <i class="fas fa-calendar-day" style="color:#DC143C"></i> Month:
            <select name="month_filter" onchange="this.form.submit()">
                <?php foreach($months_map as $num => $name): ?>
                    <option value="<?= $num ?>" <?= ($selectedMonth == $num) ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>

    <div class="container-box">
        <div class="section-title">
            <i class="fas fa-chart-bar" style="color:#DC143C"></i> 
            Medicine Consumption Trend (Top 10)
        </div>
        <div style="height: 420px; position: relative;">
            <?php if(empty($medNames)): ?>
                <div style="text-align:center; padding-top:150px; color:#bbb;">
                    <i class="fas fa-folder-open fa-3x" style="display:block; margin-bottom:10px;"></i>
                    <p>No transaction data found for the selected period.</p>
                </div>
            <?php else: ?>
                <canvas id="medicineTrendsChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
<?php if(!empty($medNames)): ?>
const ctx = document.getElementById('medicineTrendsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($medNames) ?>,
        datasets: [{
            label: 'Total Quantity Dispensed',
            data: <?= json_encode($medUsage) ?>,
            backgroundColor: 'rgba(241, 5, 5, 0.7)',
            borderColor: '#DC143C',
            borderWidth: 2,
            borderRadius: 8,
            hoverBackgroundColor: '#DC143C'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#2d3436',
                padding: 12,
                titleFont: { size: 14 },
                bodyFont: { size: 13 },
                displayColors: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f0f0f0' },
                ticks: { font: { weight: 'bold' } }
            },
            x: {
                grid: { display: false },
                ticks: { font: { weight: 'bold' } }
            }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>