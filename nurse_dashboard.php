<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bhw_system");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Nurse') { 
    header("Location: index.php"); 
    exit(); 
}

/* ======================================================
   1. AUTO-ARCHIVE LOGIC 
   (Inililipat ang records na > 5 years old sa archive)
   ====================================================== */
$check_old = $conn->query("SELECT id FROM consultations WHERE date_visit < DATE_SUB(CURDATE(), INTERVAL 5 YEAR) LIMIT 1");

if ($check_old && $check_old->num_rows > 0) {
    // Kopyahin sa archive table
    $conn->query("INSERT INTO consultations_archive SELECT * FROM consultations WHERE date_visit < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)");
    // Burahin sa main table
    $conn->query("DELETE FROM consultations WHERE date_visit < DATE_SUB(CURDATE(), INTERVAL 5 YEAR)");
}

/* ======================================================
   2. FILTER LOGIC
   ====================================================== */
$selectedYear  = isset($_GET['year']) ? $_GET['year'] : date('Y');
$selectedRange = isset($_GET['range']) ? $_GET['range'] : 'entire_year';
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('n');

$conditions = " WHERE YEAR(date_visit) = '$selectedYear'";
$label_text = "Year $selectedYear";

if ($selectedRange == 'this_week') {
    $conditions = " WHERE date_visit >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $label_text = "This Week";
} elseif ($selectedRange == 'specific_month') {
    $conditions .= " AND MONTH(date_visit) = '$selectedMonth'";
    $monthName = date("F", mktime(0, 0, 0, $selectedMonth, 10));
    $label_text = "$monthName $selectedYear";
}

/* ======================================================
   3. SYMPTOM GRAPH LOGIC (Unique Patient Episodes)
   ====================================================== */
$symptom_data = [];
$tracker = []; 

$graph_query = $conn->query("SELECT patient_name, symptoms, WEEK(date_visit) as visit_week, YEAR(date_visit) as visit_year FROM consultations $conditions");

if ($graph_query) {
    while($row = $graph_query->fetch_assoc()){
        $p_name = strtolower(trim($row['patient_name']));
        $week = $row['visit_week'];
        $year = $row['visit_year'];
        
        $individual_symptoms = preg_split('/[\/,]+/', $row['symptoms']);
        
        foreach($individual_symptoms as $symptom) {
            $symptom = trim($symptom);
            if(!empty($symptom)) {
                $key = $p_name . "_" . strtolower($symptom) . "_w" . $week . "_" . $year;
                
                if(!isset($tracker[$key])) {
                    $symptom_data[$symptom] = ($symptom_data[$symptom] ?? 0) + 1;
                    $tracker[$key] = true;
                }
            }
        }
    }
}

arsort($symptom_data);
$symptom_labels = array_keys($symptom_data);
$symptom_counts = array_values($symptom_data);

/* --- DASHBOARD CARDS --- */
$today = date('Y-m-d');
$total_today = $conn->query("SELECT COUNT(*) as total FROM consultations WHERE DATE(date_visit) = '$today'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Nurse Dashboard | BHW System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #DC143C; --sidebar-width: 260px; --bg: #f4f7fe; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); display: flex; }

        .sidebar { width: var(--sidebar-width); background: #fff; height: 100vh; position: fixed; padding: 25px 20px; border-right: 1px solid #e0e0e0; z-index: 1000; }
        .sidebar h2 { color: var(--primary); text-align: center; margin-bottom: 30px; font-weight: 800; }
        .sidebar a { display: block; padding: 14px 18px; color: #596870; text-decoration: none; border-radius: 12px; margin-bottom: 10px; font-weight: 600; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }

        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); min-height: 100vh; }
        .purple-header { background: var(--primary); height: 180px; padding: 40px; color: white; text-align: center; }
        .content-body { padding: 0 40px; margin-top: -60px; }

        .cards-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { 
            background: white; padding: 25px; border-radius: 15px; text-align: center; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-decoration: none; color: #333; 
            transition: 0.3s; display: flex; flex-direction: column; align-items: center; border: 1px solid transparent;
        }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .stat-card i { font-size: 24px; color: var(--primary); margin-bottom: 10px; }
        .stat-card .value { font-size: 18px; font-weight: 800; color: #1a202c; }

        .filter-section { background: white; padding: 15px 25px; border-radius: 15px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        select { padding: 8px 12px; border-radius: 8px; border: 1px solid #e2e8f0; }

        .graph-box { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>👩‍⚕️ Nurse</h2>
    <a href="nurse_dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="consultation.php"><i class="fas fa-stethoscope"></i> Consultation</a>
    <a href="nurse_inventory.php"><i class="fas fa-boxes-stacked"></i> Inventory</a>
    <a href="nurse_residents.php"><i class="fas fa-users"></i> Residents</a>
    <a href="transactions.php"><i class="fas fa-history"></i> Transactions</a>
    <a href="archive_history.php" ><i class="fas fa-archive"></i> Archives</a>
    <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    <div class="purple-header"><h1>Nurse Control Panel</h1></div>
    <div class="content-body">
        <div class="cards-grid">
            <a href="consultation.php" class="stat-card"><i class="fas fa-file-medical"></i><h3>Consultation</h3><div class="value">Today: <?= $total_today ?></div></a>
            <a href="nurse_inventory.php" class="stat-card"><i class="fas fa-boxes-stacked"></i><h3>Inventory</h3><div class="value">Check Stocks</div></a>
            <a href="nurse_residents.php" class="stat-card"><i class="fas fa-users"></i><h3>Residents</h3><div class="value">View Profiles</div></a>
            <a href="transactions.php" class="stat-card"><i class="fas fa-history"></i><h3>Transactions</h3><div class="value">Service Logs</div></a>
           
        </div>

        <form method="GET" id="filterForm" class="filter-section">
            Year: 
            <select name="year" onchange="this.form.submit()">
                <?php 
                $startYear = 2021;
                $endYear = date('Y'); 
                for($y=$startYear; $y<=$endYear; $y++) {
                    echo "<option value='$y' ".($selectedYear==$y?'selected':'').">$y</option>";
                }
                ?>
            </select>

            Range: 
            <select name="range" id="rangeSelect" onchange="toggleMonth()">
                <option value="entire_year" <?= $selectedRange=='entire_year'?'selected':'' ?>>Entire Year</option>
                <option value="specific_month" <?= $selectedRange=='specific_month'?'selected':'' ?>>Specific Month</option>
                <option value="this_week" <?= $selectedRange=='this_week'?'selected':'' ?>>This Week</option>
            </select>

            <div id="monthContainer" style="display: <?= $selectedRange=='specific_month'?'inline':'none' ?>;">
                Month: 
                <select name="month" onchange="this.form.submit()">
                    <?php 
                    for($m=1; $m<=12; $m++) {
                        $mName = date("M", mktime(0, 0, 0, $m, 10));
                        echo "<option value='$m' ".($selectedMonth==$m?'selected':'').">$mName</option>";
                    }
                    ?>
                </select>
            </div>
        </form>

        <div class="graph-box">
            <h2><i class="fas fa-chart-line"></i> Top Symptoms (<?= $label_text ?>)</h2>
            <p style="font-size: 0.8rem; color: #666; margin-bottom: 15px;">*Counted as 1 unique patient episode per symptom per week.</p>
            <div style="height: 350px;"><canvas id="illnessChart"></canvas></div>
        </div>
    </div>
</div>

<script>
    function toggleMonth() {
        const range = document.getElementById('rangeSelect').value;
        const monthBox = document.getElementById('monthContainer');
        monthBox.style.display = (range === 'specific_month') ? 'inline' : 'none';
        if(range !== 'specific_month') document.getElementById('filterForm').submit();
    }

    const ctx = document.getElementById('illnessChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($symptom_labels) ?>,
            datasets: [{
                label: 'Unique Patients',
                data: <?= json_encode($symptom_counts) ?>,
                backgroundColor: 'rgba(227, 18, 18, 0.7)',
                borderColor: '#DC143C',
                borderWidth: 2,
                borderRadius: 8,
                barThickness: 40
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });
</script>
</body>
</html>