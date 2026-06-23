<?php
session_start();
date_default_timezone_set('Asia/Manila');

$conn = new mysqli("localhost", "root", "", "bhw_system");
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); }

$view = isset($_GET['view']) ? $_GET['view'] : 'history';
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$month_name = date('F', mktime(0, 0, 0, $month, 10));

// Query based on view
if($view == 'history'){
    $title = "Medicine Release Report";
    $query = "SELECT t.*, m.medicine_name, m.generic_name FROM transactions t 
              JOIN medicines m ON t.medicine_id = m.id 
              WHERE t.type='restock' 
              AND MONTH(t.date_created) = '$month' 
              AND YEAR(t.date_created) = '$year'
              AND m.medicine_name LIKE '%$search%'
              ORDER BY t.date_created ASC";
} else {
    $title = "Medicine Disposal Report";
    $query = "SELECT * FROM pullout_history 
              WHERE MONTH(date_pulled) = '$month' 
              AND YEAR(date_pulled) = '$year'
              AND medicine_name LIKE '%$search%'
              ORDER BY date_pulled ASC";
}

$res = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Report - <?= $title ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; line-height: 1.5; }
        
        /* 🏥 HEADER WITH LOGO SETUP */
        .header-container { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-bottom: 3px solid #DC143C; 
            padding-bottom: 20px; 
            margin-bottom: 30px;
        }
        .logo-box { width: 100px; padding-right: 20px; }
        .logo-box img { width: 100px; height: auto; }
        .header-text { text-align: center; }
        .header-text h1 { color: #DC143C; margin: 0; font-size: 28px; text-transform: uppercase; }
        .header-text p { margin: 2px 0; font-weight: 600; color: #555; }
        
        .meta-info { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 14px; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8f9fa; color: #333; padding: 12px; border: 1px solid #999; text-align: left; font-size: 11px; text-transform: uppercase; }
        td { padding: 10px; border: 1px solid #999; font-size: 13px; }
        
        .footer { margin-top: 60px; display: flex; justify-content: space-between; padding: 0 20px; }
        .sign-box { text-align: center; width: 220px; }
        .line { border-top: 2px solid #333; margin-top: 45px; padding-top: 5px; font-weight: bold; text-transform: uppercase; font-size: 14px; }
        .sign-box p { margin: 0; font-size: 12px; color: #666; font-style: italic; }
        
        .btn-zone { margin-bottom: 20px; text-align: left; }
        .btn-print { padding: 10px 20px; background: #27ae60; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; }
        .btn-close { padding: 10px 20px; background: #666; color: white; border: none; border-radius: 6px; cursor: pointer; margin-left: 10px; font-weight: bold; font-size: 14px; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .header-container { border-bottom: 2px solid #DC143C; }
            /* Make sure logo colors/images are printed */
            img { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="no-print btn-zone">
        <button onclick="window.print()" class="btn-print">🖨️ CLICK TO PRINT / SAVE AS PDF</button>
        <button onclick="window.close()" class="btn-close">Close</button>
    </div>

    <div class="header-container">
        <div class="logo-box">
            <img src="logo.png" alt="BHW Logo" onerror="this.src='https://via.placeholder.com/100?text=LOGO'">
        </div>
        <div class="header-text">
            <p>REPUBLIC OF THE PHILIPPINES</p>
            <h1>BARANGAY HEALTH CENTER</h1>
            <p>Inventory Management System | <?= strtoupper($title) ?></p>
        </div>
    </div>

    <div class="meta-info">
        <div>PERIOD: <?= strtoupper($month_name) ?> <?= $year ?></div>
        <div>REPORT DATE: <?= date('M d, Y') ?></div>
    </div>

    <table>
        <thead>
            <?php if($view == 'history'): ?>
                <tr>
                    <th>Date Released</th>
                    <th>Medicine Name</th>
                    <th>Generic Name</th>
                    <th style="text-align: right;">Quantity</th>
                </tr>
            <?php else: ?>
                <tr>
                    <th>Date Pulled</th>
                    <th>Medicine Name</th>
                    <th>Generic Name</th>
                    <th style="text-align: right;">Quantity</th>
                    <th>Reason/Remarks</th>
                </tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php if($res && $res->num_rows > 0): ?>
                <?php while($row = $res->fetch_assoc()): ?>
                    <tr>
                        <?php if($view == 'history'): ?>
                            <td><?= date('M d, Y', strtotime($row['date_created'])) ?></td>
                            <td><strong><?= htmlspecialchars($row['medicine_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['generic_name']) ?></td>
                            <td style="text-align: right;"><?= number_format($row['quantity']) ?></td>
                        <?php else: ?>
                            <td><?= date('M d, Y', strtotime($row['date_pulled'])) ?></td>
                            <td><strong><?= htmlspecialchars($row['medicine_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['generic_name']) ?></td>
                            <td style="text-align: right;"><?= number_format($row['quantity']) ?></td>
                            <td>Expired / Pulled Out</td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="<?= ($view == 'history' ? '4' : '5') ?>" style="text-align:center; padding: 20px;">No records found for this period.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="sign-box">
            <div class="line">BHW Officer</div>
            <p>Prepared by</p>
        </div>
        <div class="sign-box">
            <div class="line">Brgy. Captain / Midwife</div>
            <p>Approved by</p>
        </div>
    </div>

</body>
</html>