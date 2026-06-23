<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bhw_system");

if ($conn->connect_error) { die("❌ Connection failed: " . $conn->connect_error); }

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Nurse') { 
    header("Location: index.php"); 
    exit(); 
}

/* ==========================================
    1. SYNCED MEDICINE MAPPING
   ========================================== */
$med_map = [
    "Fever" => "Biogesic", "Flu" => "Bioflu", "Cough" => "Ambroxol",
    "Dengue" => "Hydrite/ORS", "Hypertension" => "Losartan", 
    "Diarrhea" => "Diatabs", "Allergy" => "Cetirizine", "Cold" => "Neozep"
];

/* ==========================================
    2. FILTER LOGIC
   ========================================== */
$current_month = date('n'); 
$current_year = 2026; 
$selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
$selected_year = isset($_GET['year']) ? $_GET['year'] : $current_year;

$conditions = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $s = $conn->real_escape_string($_GET['search']);
    $conditions[] = "(c.patient_name LIKE '%$s%' OR c.household_no LIKE '%$s%')";
}
if ($selected_month !== 'all') {
    $conditions[] = "MONTH(c.date_visit) = '" . $conn->real_escape_string($selected_month) . "'";
}
if (!empty($selected_year)) {
    $conditions[] = "YEAR(c.date_visit) = '" . $conn->real_escape_string($selected_year) . "'";
}
$where_clause = (count($conditions) > 0) ? "WHERE " . implode(" AND ", $conditions) : "";

// Count total records for the print header
$count_res = $conn->query("SELECT COUNT(*) as total FROM consultations c $where_clause");
$total_records = $count_res->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction History | BHW System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DC143C; --bg: #f8f9fa; --text: #2d3436; --sidebar-width: 260px; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); display: flex; color: var(--text); }
        
        /* Sidebar */
        .sidebar { width: var(--sidebar-width); background: #fff; height: 100vh; position: fixed; padding: 25px 20px; border-right: 1px solid #edf2f7; z-index: 1000; }
        .sidebar h2 { color: var(--primary); text-align: center; margin-bottom: 30px; font-weight: 800; }
        .sidebar a { display: block; padding: 14px 18px; color: #596870; text-decoration: none; border-radius: 12px; margin-bottom: 8px; font-weight: 600; }
        .sidebar a.active { background: var(--primary); color: white; }

        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 40px; }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        /* Filters */
        .filter-container { display: flex; gap: 10px; align-items: center; }
        .search-input { padding: 10px 15px; border: 1px solid #e0e0e0; border-radius: 10px; font-size: 14px; width: 250px; outline: none; }
        .dropdown-select { padding: 10px; border: 1px solid #e0e0e0; border-radius: 10px; font-size: 14px; background: white; cursor: pointer; min-width: 120px; outline: none; }
        
        .btn-print { background: var(--primary); color: white; padding: 10px 20px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px; }

        /* Table Card */
        .history-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: #b2bec3; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #f8f9fa; }
        td { padding: 15px; border-bottom: 1px solid #f8f9fa; font-size: 14px; }
        .status-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #f3f0ff; color: var(--primary); }

        .btn-referral { background:#e67e22; color:white; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; font-size:11px; font-weight:700; transition: 0.3s; }
        .btn-referral:hover { background: #d35400; transform: translateY(-2px); }

        /* Print Style Elements */
        .print-header { display: none; }

        @media print { 
            .no-print, .sidebar { display: none !important; } 
            .main-content { margin: 0 !important; width: 100% !important; padding: 0 !important; } 
            body { background: white; color: black; }
            .history-card { box-shadow: none !important; padding: 0 !important; border-radius: 0; }
            
            /* Eto yung logo layout sa mismong Report page */
            .print-header { 
                display: flex !important; 
                align-items: center;
                justify-content: center;
                gap: 20px;
                margin-bottom: 25px;
                border-bottom: 2px solid #000;
                padding-bottom: 15px;
            }
            .print-logo { width: 80px; height: 80px; }
            .print-text { text-align: center; }
            .print-text h1 { font-size: 20px; margin: 0; text-transform: uppercase; }
            .print-text p { margin: 2px 0; font-size: 12px; }

            table { border: 1px solid #000; width: 100%; }
            th { border: 1px solid #000 !important; background: #f2f2f2 !important; color: black !important; padding: 10px 5px; font-size: 10px; }
            td { border: 1px solid #000 !important; padding: 8px 5px; font-size: 11px; }
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
    <a href="transactions.php" class="active"><i class="fas fa-history"></i> Transactions</a>
    <a href="archive_history.php"><i class="fas fa-archive"></i> Archives</a>
    <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">

    <div class="print-header">
        <img src="logo.png" class="print-logo" onerror="this.style.display='none'">
        <div class="print-text">
            <p>REPUBLIC OF THE PHILIPPINES</p>
            <h1>Barangay Bagacay Health Center</h1>
            <p>Consultation Transaction Summary Report</p>
            <p style="font-weight:bold;">
                PERIOD: <?= ($selected_month == 'all') ? "ALL MONTHS" : strtoupper(date('F', mktime(0,0,0,$selected_month,1))) ?> <?= $selected_year ?> 
                | Total Records: <?= $total_records ?>
            </p>
        </div>
    </div>

    <div class="header-section no-print">
        <h1>Transaction History</h1>
        <div class="filter-container">
            <form method="GET" style="display:flex; gap:10px;">
                <input type="text" name="search" class="search-input" placeholder="Search Patient/HH..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <select name="month" class="dropdown-select" onchange="this.form.submit()">
                    <option value="all">All Months</option>
                    <?php for($m=1; $m<=12; $m++) { 
                        $mName = date('F', mktime(0,0,0,$m,1)); 
                        $sel = ($selected_month == $m) ? 'selected' : '';
                        echo "<option value='$m' $sel>$mName</option>"; 
                    } ?>
                </select>
                <select name="year" class="dropdown-select" onchange="this.form.submit()">
                    <?php for($y=2021; $y<=2026; $y++) {
                        $selYear = ($selected_year == $y) ? 'selected' : '';
                        echo "<option value='$y' $selYear>$y</option>"; 
                    } ?>
                </select>
            </form>
            <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> PRINT REPORT</button>
        </div>
    </div>

    <div class="history-card">
        <table>
            <thead>
                <tr>
                    <th>Date Visit</th>
                    <th>HH #</th>
                    <th>Patient Name</th>
                    <th>Symptom</th>
                    <th>Vitals</th>
                    <th class="no-print">Medicine</th>
                    <th class="no-print">Qty</th>
                    <th class="no-print">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = "SELECT c.*, m.medicine_name FROM consultations c 
                          LEFT JOIN medicines m ON c.medicine_id = m.id 
                          $where_clause ORDER BY c.date_visit DESC";
                $res = $conn->query($query);
                if($res && $res->num_rows > 0):
                    while($row = $res->fetch_assoc()): 
                        $final_med = !empty($row['medicine_name']) ? $row['medicine_name'] : ($med_map[$row['symptoms']] ?? 'General Med');
                        $js_data = json_encode([
                            'name' => $row['patient_name'],
                            'hh' => $row['household_no'],
                            'symptom' => $row['symptoms'],
                            'bp' => $row['bp'],
                            'weight' => $row['weight'],
                            'height' => $row['height'],
                            'age' => $row['age'],
                            'date' => date('M d, Y', strtotime($row['date_visit'])),
                            'action' => $row['action_taken']
                        ]);
                ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($row['date_visit'])) ?></td>
                        <td><?= $row['household_no'] ?></td>
                        <td><strong><?= htmlspecialchars($row['patient_name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['symptoms']) ?></td>
                        <td style="font-size:12px;"><?= $row['bp'] ?> | <?= $row['weight'] ?>kg</td>
                        <td class="no-print"><span class="status-badge"><?= $final_med ?></span></td>
                        <td class="no-print"><?= $row['quantity_given'] ?> pcs</td>
                        <td class="no-print">
                            <?php if(strpos($row['action_taken'], 'Emergency') !== false || strpos($row['action_taken'], 'Referral') !== false): ?>
                                <button onclick='printReferral(<?= $js_data ?>)' class="btn-referral">
                                    <i class="fas fa-print"></i> REFERRAL
                                </button>
                            <?php else: ?>
                                <span style="color:#bdc3c7; font-size:12px; font-style:italic;"><?= $row['action_taken'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Function para sa Referral Slip (Popup Window)
function printReferral(data) {
    const printWindow = window.open('', '_blank', 'width=850,height=950');
    const content = `
        <html>
        <head>
            <title>Referral Slip - ${data.name}</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; color: #000; line-height: 1.6; }
                .referral-container { border: 3px solid #2c3e50; padding: 30px; min-height: 85vh; display: flex; flex-direction: column; }
                .header-wrapper { display: flex; align-items: center; justify-content: center; border-bottom: 2px solid #2c3e50; padding-bottom: 15px; margin-bottom: 25px; gap: 20px; }
                .header-logo { width: 100px; height: 100px; object-fit: contain; }
                .header-text { text-align: center; }
                .header-text h1 { margin: 0; font-size: 24px; text-transform: uppercase; color: #2c3e50; }
                .title-tag { text-align: center; font-size: 22px; font-weight: 800; text-decoration: underline; margin-bottom: 30px; text-transform: uppercase; color: #2c3e50; }
                .info-section { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
                .info-box { border-bottom: 2px solid #333; padding: 6px 0; }
                .label { font-size: 11px; text-transform: uppercase; color: #555; font-weight: 800; display: block; }
                .value { font-size: 18px; font-weight: 700; }
                .clinical-box { border: 2px solid #000; padding: 25px; border-radius: 8px; margin: 30px 0; background: #fdfdfd; flex-grow: 1; }
                .footer { margin-top: auto; display: flex; justify-content: space-between; padding-top: 50px; }
                .sig-block { text-align: center; width: 250px; }
                .sig-line { border-top: 2px solid #000; margin-bottom: 5px; }
            </style>
        </head>
        <body>
            <div class="referral-container">
                <div class="header-wrapper">
                    <img src="logo.png" alt="Logo" class="header-logo" onerror="this.style.display='none'">
                    <div class="header-text">
                        <p>REPUBLIC OF THE PHILIPPINES</p>
                        <h1>Barangay Bagacay Health Center</h1>
                        <p>Municipal Health Office | BHW Referral System</p>
                    </div>
                </div>
                <div class="title-tag">Medical Referral Slip</div>
                <div class="info-section">
                    <div class="info-box"><span class="label">Patient Name</span><span class="value">${data.name}</span></div>
                    <div class="info-box"><span class="label">Date</span><span class="value">${data.date}</span></div>
                </div>
                <div class="info-section">
                    <div class="info-box"><span class="label">Age</span><span class="value">${data.age} Y/O</span></div>
                    <div class="info-box"><span class="label">Household No.</span><span class="value"># ${data.hh}</span></div>
                </div>
                <div class="clinical-box">
                    <h3>CLINICAL FINDINGS & ASSESSMENT:</h3>
                    <p>Patient presenting with symptoms of: <strong>${data.symptom}</strong></p>
                    <p>Vital Signs: BP: ${data.bp} | Weight: ${data.weight}kg</p>
                    <br>
                    <p><strong>Initial Action Taken:</strong> ${data.action}</p>
                </div>
                <div class="footer">
                    <div class="sig-block"><div class="sig-line"></div><strong>Registered Nurse / BHW</strong></div>
                    <div class="sig-block"><div class="sig-line"></div><strong>Barangay Captain</strong></div>
                </div>
            </div>
            <script>window.onload = function() { setTimeout(() => { window.print(); }, 500); };<\/script>
        </body>
        </html>
    `;
    printWindow.document.write(content);
    printWindow.document.close();
}
</script>
</body>
</html>