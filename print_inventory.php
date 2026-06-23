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
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #DC143C; padding-bottom: 10px; }
        .header h1 { color: #DC143C; margin-bottom: 5px; }
        .meta-info { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8f9fa; color: #555; padding: 12px; border: 1px solid #ddd; text-align: left; font-size: 12px; text-transform: uppercase; }
        td { padding: 12px; border: 1px solid #ddd; font-size: 13px; }
        .footer { margin-top: 50px; display: flex; justify-content: space-between; }
        .sign-box { text-align: center; width: 200px; }
        .line { border-top: 1px solid #000; margin-top: 40px; padding-top: 5px; font-weight: bold; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 20px; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #27ae60; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
            🖨️ CLICK TO PRINT / SAVE AS PDF
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>

    <div class="header">
        <h1>BARANGAY HEALTH WORKER SYSTEM</h1>
        <p>Barangay Inventory Report | <?= $title ?></p>
    </div>

    <div class="meta-info">
        <div><strong>Period:</strong> <?= $month_name ?> <?= $year ?></div>
        <div><strong>Date Generated:</strong> <?= date('M d, Y') ?></div>
    </div>

    <table>
        <thead>
            <?php if($view == 'history'): ?>
                <tr>
                    <th>Date Released</th>
                    <th>Medicine Name</th>
                    <th>Generic Name</th>
                    <th>Quantity Released</th>
                </tr>
            <?php else: ?>
                <tr>
                    <th>Date Pulled</th>
                    <th>Medicine Name</th>
                    <th>Generic Name</th>
                    <th>Quantity Pulled</th>
                    <th>Reason</th>
                </tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php if($res->num_rows > 0): ?>
                <?php while($row = $res->fetch_assoc()): ?>
                    <tr>
                        <?php if($view == 'history'): ?>
                            <td><?= date('M d, Y', strtotime($row['date_created'])) ?></td>
                            <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td><?= htmlspecialchars($row['generic_name']) ?></td>
                            <td><?= $row['quantity'] ?></td>
                        <?php else: ?>
                            <td><?= date('M d, Y', strtotime($row['date_pulled'])) ?></td>
                            <td><?= htmlspecialchars($row['medicine_name']) ?></td>
                            <td><?= htmlspecialchars($row['generic_name']) ?></td>
                            <td><?= $row['quantity'] ?></td>
                            <td>Expired/Pulled Out</td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">No data available for this selection.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="sign-box">
            <div class="line">Prepared by:</div>
            <p>BHW Officer</p>
        </div>
        <div class="sign-box">
            <div class="line">Approved by:</div>
            <p>Barangay Captain / Midwife</p>
        </div>
    </div>

</body>
</html>