<?php
session_start();
date_default_timezone_set('Asia/Manila');

$conn = new mysqli("localhost", "root", "", "bhw_system");
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); }

$view = isset($_GET['view']) ? $_GET['view'] : 'pending'; 
$today = date("Y-m-d");

// --- FILTER LOGIC ---
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// --- 1. AUTO-DISPOSAL LOGIC ---
$expired_stocks = $conn->query("SELECT * FROM medicines WHERE quantity > 0 AND expiry_date < '$today'");
if ($expired_stocks->num_rows > 0) {
    while ($row = $expired_stocks->fetch_assoc()) {
        $m_id = $row['id'];
        $m_name = mysqli_real_escape_string($conn, $row['medicine_name']);
        $m_gen = mysqli_real_escape_string($conn, $row['generic_name']);
        $qty = $row['quantity'];
        $exp = $row['expiry_date'];
        $conn->query("INSERT INTO pullout_history (medicine_name, generic_name, quantity, expiry_date, date_pulled) 
                      VALUES ('$m_name', '$m_gen', $qty, '$exp', NOW())");
        $conn->query("UPDATE medicines SET quantity = 0 WHERE id = $m_id");
    }
}

// --- 2. RELEASE LOGIC ---
if (isset($_POST['confirm_release'])) {
    $id = intval($_POST['id']);
    $res_data = $conn->query("SELECT * FROM medicines WHERE id = $id")->fetch_assoc();
    $qty_to_move = intval($res_data['reserved_quantity']);
    if ($qty_to_move > 0) {
        $conn->query("UPDATE medicines SET quantity = $qty_to_move, reserved_quantity = 0 WHERE id = $id");
        $conn->query("INSERT INTO transactions (medicine_id, quantity, type, date_created) 
                      VALUES ($id, $qty_to_move, 'restock', NOW())");
        $_SESSION['msg'] = "Success: New batch has been released to the shelf!";
    }
    header("Location: inventory_actions.php?view=history");
    exit();
}

// --- 3. RESTOCK FROM DISPOSAL LOGIC ---
if (isset($_POST['restock_from_disposal'])) {
    $m_name = mysqli_real_escape_string($conn, $_POST['medicine_name']);
    $new_qty = intval($_POST['new_quantity']);
    $new_expiry = $_POST['new_expiry'];
    $check = $conn->query("SELECT id FROM medicines WHERE medicine_name = '$m_name' LIMIT 1");
    if($check->num_rows > 0){
        $m_id = $check->fetch_assoc()['id'];
        $conn->query("UPDATE medicines SET reserved_quantity = $new_qty, expiry_date = '$new_expiry' WHERE id = $m_id");
        $_SESSION['msg'] = "Success: Stock added to Pending Release!";
    }
    header("Location: inventory_actions.php?view=pending");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Stock Reports | BHW System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7fe; color: #333; display: flex; }
        .sidebar { width: 220px; position: fixed; height: 100vh; background: #fff; padding: 20px; border-right: 1px solid #eee; }
        .sidebar h2 { color: #DC143C; font-size: 22px; text-align: center; margin-bottom: 25px; font-weight: 800; }
        .sidebar-menu a { display: block; padding: 12px; color: #444; text-decoration: none; border-radius: 12px; margin-bottom: 5px; font-weight: 600; }
        .sidebar-menu a.active { background: #DC143C; color: white; }
        .main-content { margin-left: 240px; padding: 40px; width: calc(100% - 240px); }
        
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .filter-group { display: flex; gap: 8px; align-items: center; }
        .filter-input { padding: 10px; border-radius: 10px; border: 1px solid #ddd; outline: none; font-size: 13px; }
        .btn-print { background: #DC143C; color: white; border: none; padding: 10px 18px; border-radius: 10px; cursor: pointer; font-weight: bold; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 13px; }

        .tab-container { display: flex; gap: 12px; margin-bottom: 30px; }
        .tab-btn { padding: 14px 22px; border-radius: 14px; border: none; cursor: pointer; text-decoration: none; font-weight: 700; background: white; color: #DC143C; box-shadow: 0 4px 6px rgba(0,0,0,0.03); }
        .active-tab { background: #DC143C !important; color: white !important; }
        
        .report-card { background: #fff; border-radius: 24px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; padding: 18px; color: #999; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #f8f9fa; }
        td { padding: 18px; border-bottom: 1px solid #fbfbfb; font-size: 14px; }
        
        .badge { padding: 6px 14px; border-radius: 10px; font-size: 11px; font-weight: 800; }
        .badge-pending { background: #fff4e5; color: #d68102; }
        .badge-history { background: #e3fcef; color: #27ae60; }
        .badge-invalid { background: #fff1f0; color: #cf1322; }

        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background:#fff; margin:8% auto; padding:35px; width:450px; border-radius:25px; text-align: center; }
        .btn-opt { padding: 16px; border-radius: 15px; border:none; cursor:pointer; font-weight:700; width: 100%; margin-top: 10px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 10px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>👩‍⚕️ BHW</h2>
    <div class="sidebar-menu">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
      
        <a href="inventory_actions.php" class="active"><i class="fas fa-file-medical-alt"></i> Stock Reports</a>
        <a href="inventory.php"><i class="fas fa-pills"></i> Inventory</a>
        <a href="residents.php"><i class="fas fa-users"></i> Residents</a>
        <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="header-section">
        <h1>Inventory History</h1>
        
        <?php if($view != 'pending'): ?>
        <form method="GET" class="filter-group">
            <input type="hidden" name="view" value="<?= $view ?>">
            <input type="text" name="search" placeholder="Search medicine..." value="<?= htmlspecialchars($search) ?>" class="filter-input">
            
            <select name="month" class="filter-input">
                <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= sprintf('%02d', $m) ?>" <?= $selected_month == sprintf('%02d', $m) ? 'selected' : '' ?>>
                        <?= date('F', mktime(0,0,0,$m, 1)) ?>
                    </option>
                <?php endfor; ?>
            </select>

            <select name="year" class="filter-input">
                <?php for($y=date('Y'); $y>=2021; $y--): ?>
                    <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <button type="submit" class="filter-input" style="background:#DC143C; color:white; cursor:pointer; border:none; font-weight:bold;">Filter</button>
            <a href="print_inventory_report.php?view=<?= $view ?>&month=<?= $selected_month ?>&year=<?= $selected_year ?>&search=<?= $search ?>" target="_blank" class="btn-print">
                <i class="fas fa-file-pdf"></i> PRINT
            </a>
        </form>
        <?php endif; ?>
    </div>

    <?php if(isset($_SESSION['msg'])): ?>
        <div style="background:#27ae60; color:white; padding:15px; border-radius:12px; margin-bottom:20px;">
            <i class="fas fa-check-circle"></i> <?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="tab-container">
        <a href="?view=pending" class="tab-btn <?= $view == 'pending' ? 'active-tab' : '' ?>">PENDING RELEASE</a>
        <a href="?view=history" class="tab-btn <?= $view == 'history' ? 'active-tab' : '' ?>">RELEASE HISTORY</a>
        <a href="?view=disposal" class="tab-btn <?= $view == 'disposal' ? 'active-tab' : '' ?>">DISPOSAL LOGS</a>
    </div>

    <div class="report-card">
        <?php if($view == 'pending'): ?>
            <h3>Waiting for Shelf (Batch Control)</h3>
            <table>
                <thead><tr><th>Medicine</th><th>Current Shelf</th><th>New Batch Expiry</th><th>Qty to Release</th><th>Action</th></tr></thead>
                <tbody>
                    <?php
                    $res = $conn->query("SELECT * FROM medicines WHERE reserved_quantity > 0");
                    while($p = $res->fetch_assoc()): 
                        $can_release = ($p['quantity'] <= 0);
                    ?>
                        <tr>
                            <td><b><?= htmlspecialchars($p['medicine_name']) ?></b></td>
                            <td><span style="color: <?= $can_release ? 'red' : 'green' ?>"><?= $p['quantity'] ?> in shelf</span></td>
                            <td><b><?= date('M d, Y', strtotime($p['expiry_date'])) ?></b></td>
                            <td><span class="badge badge-pending"><?= $p['reserved_quantity'] ?></span></td>
                            <td>
                                <?php if($can_release): ?>
                                    <button onclick='triggerRelease(<?= json_encode($p) ?>)' style="background:#DC143C; color:white; border:none; padding:10px 18px; border-radius:12px; cursor:pointer; font-weight:bold;">RELEASE</button>
                                <?php else: ?>
                                    <button disabled style="background:#ccc; color:#666; border:none; padding:10px 18px; border-radius:12px; cursor:not-allowed;">LOCKED</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        <?php elseif($view == 'history'): ?>
            <h3>Release History (<?= date('F Y', strtotime("$selected_year-$selected_month-01")) ?>)</h3>
            <table>
                <thead><tr><th>Date Released</th><th>Medicine</th><th>Expiry Date</th><th>Qty Added</th></tr></thead>
                <tbody>
                    <?php
                    $query = "SELECT t.*, m.medicine_name, m.expiry_date FROM transactions t 
                              JOIN medicines m ON t.medicine_id = m.id 
                              WHERE t.type='restock' 
                              AND MONTH(t.date_created) = '$selected_month' 
                              AND YEAR(t.date_created) = '$selected_year'
                              AND m.medicine_name LIKE '%$search%'
                              ORDER BY t.date_created DESC";
                    $res = $conn->query($query);
                    if($res->num_rows > 0):
                        while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($row['date_created'])) ?></td>
                                <td><b><?= htmlspecialchars($row['medicine_name']) ?></b></td>
                                <td><?= date('M d, Y', strtotime($row['expiry_date'])) ?></td>
                                <td><span class="badge badge-history">+ <?= $row['quantity'] ?></span></td>
                            </tr>
                        <?php endwhile; 
                    else: ?>
                        <tr><td colspan="4" style="text-align:center; color:#999; padding:30px;">No records found for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php else: ?>
            <h3>Disposal Logs (<?= date('F Y', strtotime("$selected_year-$selected_month-01")) ?>)</h3>
            <table>
                <thead><tr><th>Medicine</th><th>Qty Pulled</th><th>Expiry Date</th><th>Date Pulled</th><th>Action</th></tr></thead>
                <tbody>
                    <?php
                    $query = "SELECT * FROM pullout_history 
                              WHERE MONTH(date_pulled) = '$selected_month' 
                              AND YEAR(date_pulled) = '$selected_year'
                              AND medicine_name LIKE '%$search%'
                              ORDER BY date_pulled DESC";
                    $res = $conn->query($query);
                    if($res->num_rows > 0):
                        while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($row['medicine_name']) ?></b></td>
                                <td><span class="badge badge-invalid"><?= $row['quantity'] ?></span></td>
                                <td><?= date('M d, Y', strtotime($row['expiry_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($row['date_pulled'])) ?></td>
                                <td><button onclick='triggerRestock(<?= json_encode($row) ?>)' style="background:#27ae60; color:white; border:none; padding:8px 15px; border-radius:8px; cursor:pointer;">RESTOCK</button></td>
                            </tr>
                        <?php endwhile; 
                    else: ?>
                        <tr><td colspan="5" style="text-align:center; color:#999; padding:30px;">No disposal records for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div id="releaseModal" class="modal">
    <div class="modal-content" style="border-top: 10px solid #DC143C;">
        <h2 id="mName" style="color:#DC143C;"></h2>
        <p style="margin:20px 0;">Shelf is now empty. Ready to release new batch.</p>
        <form method="POST">
            <input type="hidden" name="id" id="mId">
            <button type="submit" name="confirm_release" class="btn-opt" style="background:#DC143C; color:white;">CONFIRM RELEASE</button>
            <button type="button" onclick="closeModal('releaseModal')" style="background:none; border:none; margin-top:10px; cursor:pointer; color:#999;">Cancel</button>
        </form>
    </div>
</div>

<div id="restockModal" class="modal">
    <div class="modal-content">
        <h2 style="color:#27ae60;">Restock Item</h2>
        <form method="POST">
            <input type="hidden" name="medicine_name" id="restock_mname">
            <label style="display:block; text-align:left; margin-bottom:5px;">Quantity:</label>
            <input type="number" name="new_quantity" required min="1">
            <label style="display:block; text-align:left; margin-bottom:5px;">New Expiry Date:</label>
            <input type="date" name="new_expiry" required min="<?= $today ?>">
            <button type="submit" name="restock_from_disposal" class="btn-opt" style="background:#27ae60; color:white;">MOVE TO PENDING</button>
            <button type="button" onclick="closeModal('restockModal')" style="background:none; border:none; margin-top:10px; cursor:pointer; color:#999;">Cancel</button>
        </form>
    </div>
</div>

<script>
function triggerRelease(data) {
    document.getElementById('mId').value = data.id;
    document.getElementById('mName').innerText = data.medicine_name;
    document.getElementById('releaseModal').style.display = 'block';
}
function triggerRestock(data) {
    document.getElementById('restock_mname').value = data.medicine_name;
    document.getElementById('restockModal').style.display = 'block';
}
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function(event) { if (event.target.className == 'modal') event.target.style.display = "none"; }
</script>

</body>
</html>