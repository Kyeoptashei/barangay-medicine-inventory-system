<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bhw_system");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Nurse') { 
    header("Location: index.php"); 
    exit(); 
}

/* --- FILTER & SEARCH LOGIC --- */
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$month  = isset($_GET['month']) ? $_GET['month'] : '';
$year   = isset($_GET['year']) ? $_GET['year'] : '';

$query = "SELECT * FROM consultations_archive WHERE 1=1";

if ($search != '') {
    $query .= " AND (patient_name LIKE '%$search%' OR household_no LIKE '%$search%')";
}
if ($month != '') {
    $query .= " AND MONTH(date_visit) = '$month'";
}
if ($year != '') {
    $query .= " AND YEAR(date_visit) = '$year'";
}

$query .= " ORDER BY date_visit DESC";
$archived_recs = $conn->query($query);
$total_rows = $archived_recs->num_rows; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archived Records | BHW System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DC143C; --bg: #f4f7fe; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; display: flex; }
        
        /* Sidebar Styling */
        .sidebar { width: 260px; background: #fff; height: 100vh; position: fixed; padding: 25px 20px; border-right: 1px solid #e0e0e0; transition: all 0.3s; }
        .sidebar h2 { color: var(--primary); text-align: center; margin-bottom: 30px; font-weight: 800; }
        .sidebar a { display: block; padding: 14px 18px; color: #596870; text-decoration: none; border-radius: 12px; margin-bottom: 8px; font-weight: 600; }
        .sidebar a:hover { background: var(--primary); color: white; }
        .sidebar a.active { background: var(--primary); color: white; }

        /* Main Content */
        .main-content { margin-left: 260px; padding: 40px; width: calc(100% - 260px); }
        
        /* Filter Bar */
        .filter-bar { display: flex; gap: 10px; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 25px; align-items: center; }
        .filter-bar input, .filter-bar select { padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; }
        .btn-print { background: #DC143C; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        /* Table Style */
        .table-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #f0f2f5; color: #718096; font-size: 12px; text-transform: uppercase; }
        td { padding: 15px 12px; border-bottom: 1px solid #f0f2f5; font-size: 14px; }

        /* 🖨️ PRINT HEADER STYLE */
        .print-header { display: none; }

        @media print {
            .no-print, .sidebar, i.fas { display: none !important; }
            body { background: white; }
            .main-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            
            .print-header { 
                display: flex !important; 
                align-items: center;
                justify-content: center;
                gap: 20px;
                margin-bottom: 25px;
                border-bottom: 2px solid #000;
                padding-bottom: 15px;
            }
            .print-logo { width: 80px; height: 80px; object-fit: contain; }
            .print-text { text-align: center; }
            .print-text h1 { font-size: 20px; margin: 0; text-transform: uppercase; color: #000; }
            .print-text p { margin: 2px 0; font-size: 12px; }

            .table-card { box-shadow: none !important; padding: 0 !important; }
            th { background-color: #f0f2f5 !important; color: black !important; -webkit-print-color-adjust: exact; }
            td, th { border: 1px solid #dee2e6 !important; }
        }
    </style>
</head>
<body>

<div class="sidebar no-print">
    <h2>👩‍⚕️ Nurse</h2>
    <a href="nurse_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="consultation.php"><i class="fas fa-stethoscope"></i> Consultation</a>
    <a href="nurse_inventory.php"><i class="fas fa-boxes-stacked"></i> Inventory</a>
    <a href="nurse_residents.php"><i class="fas fa-users"></i> Residents</a>
    <a href="transactions.php"><i class="fas fa-history"></i> Transactions</a>
    <a href="archive_history.php" class="active"><i class="fas fa-archive"></i> Archives</a>
    <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    <div class="print-header">
        <img src="logo.png" class="print-logo" onerror="this.style.display='none'">
        <div class="print-text">
            <p>REPUBLIC OF THE PHILIPPINES</p>
            <h1>Barangay Bagacay Health Center</h1>
            <p>Archived Consultation Records Summary</p>
            <p style="font-weight:bold;">
                <?php 
                    $mName = ($month != '') ? date('F', mktime(0, 0, 0, $month, 1)) : 'All Months';
                    $yName = ($year != '') ? $year : 'All Years';
                    echo "RECORDS FOR: " . strtoupper($mName) . " $yName"; 
                ?>
                | Total Records: <?= $total_rows ?>
            </p>
        </div>
    </div>

    <h1 style="margin-bottom: 20px;" class="no-print">📦 Archived Records</h1>

    <form method="GET" class="filter-bar no-print">
        <input type="text" name="search" placeholder="Search Patient/HH..." value="<?= htmlspecialchars($search) ?>" style="flex: 1;">
        
        <select name="month">
            <option value="">All Months</option>
            <?php for($m=1; $m<=12; $m++): ?>
                <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
            <?php endfor; ?>
        </select>

        <select name="year">
            <option value="">All Years</option>
            <?php for($y=2021; $y<=date('Y'); $y++): ?>
                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit" class="btn-print" style="background: #4a5568;"><i class="fas fa-search"></i> FILTER</button>
        <button type="button" onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> PRINT REPORT</button>
    </form>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Date Visit</th>
                    <th>HH #</th>
                    <th>Patient Name</th>
                    <th>Symptom</th>
                    <th>Vitals</th>
                    <th class="no-print">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if($total_rows > 0): ?>
                    <?php while($r = $archived_recs->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($r['date_visit'])) ?></td>
                        <td><?= $r['household_no'] ?></td>
                        <td><strong><?= htmlspecialchars($r['patient_name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['symptoms']) ?></td>
                        <td><?= $r['bp'] ?> | <?= $r['weight'] ?>kg</td>
                        <td class="no-print"><span style="color: #a0aec0; font-size: 12px;">Archived</span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px;">Walang nahanap na record.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>