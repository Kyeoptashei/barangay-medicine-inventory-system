<?php  
session_start();
$conn = new mysqli("localhost", "root", "", "bhw_system"); 
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); } 

$today = date("Y-m-d"); 

// --- 1. AUTOMATIC PULL-OUT (7 Days Before Expiry) ---
$pullout_query = $conn->query("SELECT * FROM medicines WHERE expiry_date <= DATE_ADD('$today', INTERVAL 7 DAY) AND quantity > 0");
while($p = $pullout_query->fetch_assoc()){
    $p_id = $p['id'];
    $p_name = mysqli_real_escape_string($conn, $p['medicine_name']);
    $p_gen = mysqli_real_escape_string($conn, $p['generic_name']);
    $p_qty = $p['quantity'];
    $p_exp = $p['expiry_date'];
    
    $conn->query("INSERT INTO pullout_history (medicine_name, generic_name, quantity, expiry_date) VALUES ('$p_name', '$p_gen', $p_qty, '$p_exp')");
    $conn->query("UPDATE medicines SET quantity = 0 WHERE id = $p_id"); 
}

// --- 2. ACTIONS ---

// DELETE
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM medicines WHERE id = $id");
    header("Location: inventory.php"); exit();
}

// RESTOCK (To Reserved)
if (isset($_POST['restock_medicine'])) {
    $id = intval($_POST['id']);
    $res_qty = intval($_POST['res_quantity']);
    $new_expiry = $_POST['new_expiry'];
    $conn->query("UPDATE medicines SET reserved_quantity = reserved_quantity + $res_qty, expiry_date = '$new_expiry' WHERE id = $id");
    header("Location: inventory.php"); exit();
}

// SAVE / UPDATE (Updated with Form and Target Group)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_medicine'])) {
    $id = $_POST['id'];
    $name = mysqli_real_escape_string($conn, $_POST['medicine_name']);
    $generic = mysqli_real_escape_string($conn, $_POST['generic_name']);
    $form = mysqli_real_escape_string($conn, $_POST['form']); // BAGONG COLUMN
    $target = mysqli_real_escape_string($conn, $_POST['target_group']); // BAGONG COLUMN
    $qty = intval($_POST['quantity']);
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $expiry = $_POST['expiry_date'];

    if (!empty($id)) {
        // SQL UPDATE (Kasama na ang Form at Target)
        $sql = "UPDATE medicines SET 
                medicine_name='$name', 
                generic_name='$generic', 
                form='$form', 
                target_group='$target', 
                quantity='$qty', 
                unit='$unit', 
                expiry_date='$expiry' 
                WHERE id=$id";
    } else {
        // SQL INSERT (Kasama na ang Form at Target)
        $sql = "INSERT INTO medicines (medicine_name, generic_name, form, target_group, quantity, unit, expiry_date) 
                VALUES ('$name', '$generic', '$form', '$target', '$qty', '$unit', '$expiry')";
    }
    $conn->query($sql);
    header("Location: inventory.php"); exit();
}

// --- 3. FETCH DATA ---
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$query = "SELECT *, DATEDIFF(expiry_date, '$today') as days_left FROM medicines";

if ($filter == 'expired') {
    $query .= " WHERE expiry_date <= '$today'";
} elseif ($filter == 'outofstock') {
    $query .= " WHERE quantity <= 0 AND expiry_date > '$today'";
}

$query .= " ORDER BY expiry_date ASC, medicine_name ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Inventory - BHW System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #fcfcfc; color: #333; min-height: 100vh; }
        .sidebar { width: 220px; position: fixed; height: 100vh; background: #fff; padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar h2 { color: #DC143C; font-size: 22px; text-align: center; margin-bottom: 25px; font-weight: 800; }
        .sidebar-menu a { display: block; padding: 12px; color: #333; text-decoration: none; border-radius: 8px; margin-bottom: 5px; transition: 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: #DC143C; color: #fff; }
        .main-content { margin-left: 240px; padding: 30px; }
        .action-bar { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; }
        .btn-add { background: #fbc02d; color: #333; padding: 12px 25px; border: none; border-radius: 10px; cursor: pointer; font-weight: 800; }
        #searchBar { padding: 12px 20px; border-radius: 10px; border: none; flex-grow: 1; box-shadow: 0 4px 10px rgba(0,0,0,0.1); outline: none; }
        .inventory-container { background: #fff; border-radius: 20px; padding: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th { text-align: left; padding: 15px; color: #DC143C; font-weight: 800; border-bottom: 3px solid #eee; font-size: 13px; }
        tbody td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 6px; font-weight: 800; font-size: 11px; display: inline-block; }
        .badge-form { background: #e0f2fe; color: #0369a1; }
        .badge-target { background: #f5f3ff; color: #7c3aed; }
        .status-pill { padding: 6px 10px; border-radius: 8px; font-weight: 800; font-size: 10px; color: #fff; text-align: center; display: inline-block; width: 100%; }
        .btn-action { border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-weight: 700; color: #fff; font-size: 11px; text-transform: uppercase; margin-right: 4px; }
        .modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }
        .modal-content { background: #fff; margin: 3% auto; padding: 30px; width: 450px; border-radius: 20px; }
        label { font-weight: 700; font-size: 13px; color: #555; display: block; margin: 10px 0 5px; }
        .form-control { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>👩‍⚕️ BHW</h2>
    <div class="sidebar-menu">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
       
        <a href="inventory_actions.php" ><i class="fas fa-file-medical-alt"></i> Stock Reports</a>
        <a href="inventory.php" class="active"><i class="fas fa-pills"></i> Inventory</a>
        <a href="residents.php"><i class="fas fa-users"></i> Residents</a>
        <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <h1>Medicine Inventory</h1>
    <div class="action-bar">
        <button class="btn-add" onclick="openModal()">➕ ADD NEW MEDICINE</button>
        <input type="text" id="searchBar" placeholder="🔍 Search medicine, generic, or classification...">
    </div>

    <div class="inventory-container">
        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Classification</th>
                    <th>Target</th>
                    <th>Shelf Qty</th>
                    <th>Reserved</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): 
                    $qty = intval($row['quantity']); 
                    $res = intval($row['reserved_quantity']);
                    $days = intval($row['days_left']);
                ?>
                <tr>
                    <td><b><?= htmlspecialchars($row['medicine_name']) ?></b><br><small><?= htmlspecialchars($row['generic_name']) ?></small></td>
                    <td><span class="badge badge-form"><?= $row['form'] ?></span></td>
                    <td><span class="badge badge-target"><?= $row['target_group'] ?></span></td>
                    <td><?= $qty ?> <small><?= $row['unit'] ?></small></td>
                    <td style="color:#DC143C;"><strong><?= $res ?></strong></td>
                    <td><?= date('M d, Y', strtotime($row['expiry_date'])) ?></td>
                    <td>
                        <?php if($days <= 0): ?><span class="status-pill" style="background:#2c3e50;">EXPIRED</span>
                        <?php elseif($qty <= 0): ?><span class="status-pill" style="background:#d32f2f;">OUT OF STOCK</span>
                        <?php else: ?><span class="status-pill" style="background:#27ae60;">GOOD STOCK</span><?php endif; ?>
                    </td>
                    <td style="white-space: nowrap;">
                        <button class="btn-action" style="background:#27ae60;" onclick='openRestock(<?= json_encode($row) ?>)'>Restock</button>
                        <button class="btn-action" style="background:#3498db;" onclick='editMed(<?= json_encode($row) ?>)'>Edit</button>
                        <a href="inventory.php?delete_id=<?= $row['id'] ?>" class="btn-action" style="background:#e74c3c; text-decoration:none;" onclick="return confirm('Delete?')">DEL</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="medModal" class="modal"><div class="modal-content">
    <h2 id="modalTitle" style="text-align:center; color:#DC143C;">Medicine Details</h2>
    <form method="POST">
        <input type="hidden" name="id" id="formId">
        <label>Medicine Name</label>
        <input type="text" name="medicine_name" id="formName" class="form-control" required>
        <label>Generic Name</label>
        <input type="text" name="generic_name" id="formGeneric" class="form-control">
        
        <label>Classification (Form)</label>
        <input type="text" name="form" id="formType" class="form-control" placeholder="Tablet, Syrup, etc.">
        
        <label>Target Group</label>
        <input type="text" name="target_group" id="formTarget" class="form-control" placeholder="Adult, Infant, etc.">

        <div style="display:flex; gap:10px;">
            <div style="flex:1;"><label>Qty</label><input type="number" name="quantity" id="formQty" class="form-control"></div>
            <div style="flex:1;"><label>Unit</label><input type="text" name="unit" id="formUnit" class="form-control"></div>
        </div>
        <label>Expiry Date</label>
        <input type="date" name="expiry_date" id="formExpiry" class="form-control">
        
        <button type="submit" name="save_medicine" class="btn-add" style="width:100%; margin-top:15px;">SAVE CHANGES</button>
        <button type="button" onclick="closeModals()" style="width:100%; background:none; border:none; cursor:pointer; margin-top:10px; color:#999;">CANCEL</button>
    </form>
</div></div>

<div id="restockModal" class="modal"><div class="modal-content">
    <h2 style="color:#27ae60; text-align:center;">Restock (Reserved)</h2>
    <form method="POST">
      <input type="hidden" name="id" id="rsId">
      <p id="rsName" style="font-weight:bold; margin:15px 0; text-align:center; color:#DC143C;"></p>
      <label>Quantity to Reserve:</label>
      <input type="number" name="res_quantity" class="form-control" required>
      <label>New Batch Expiry:</label>
      <input type="date" name="new_expiry" class="form-control" required>
      <button type="submit" name="restock_medicine" class="btn-add" style="width:100%; background:#27ae60; color:#fff; margin-top:15px;">CONFIRM</button>
      <button type="button" onclick="closeModals()" style="width:100%; border:none; background:none; margin-top:10px; cursor:pointer;">CANCEL</button>
    </form>
</div></div>

<script>
function closeModals() { document.querySelectorAll('.modal').forEach(m => m.style.display='none'); }
function openModal() { closeModals(); document.getElementById('formId').value=""; document.getElementById('modalTitle').innerText="ADD NEW MEDICINE"; document.getElementById('medModal').style.display="block"; }
function openRestock(data) { closeModals(); document.getElementById('rsId').value=data.id; document.getElementById('rsName').innerText=data.medicine_name; document.getElementById('restockModal').style.display="block"; }

function editMed(data) {
    closeModals();
    document.getElementById('modalTitle').innerText = "EDIT MEDICINE";
    document.getElementById('formId').value = data.id;
    document.getElementById('formName').value = data.medicine_name;
    document.getElementById('formGeneric').value = data.generic_name;
    document.getElementById('formType').value = data.form; // BAGONG DATA
    document.getElementById('formTarget').value = data.target_group; // BAGONG DATA
    document.getElementById('formQty').value = data.quantity;
    document.getElementById('formUnit').value = data.unit;
    document.getElementById('formExpiry').value = data.expiry_date;
    document.getElementById('medModal').style.display="block";
}

document.getElementById("searchBar").addEventListener("keyup", function() {
    let q = this.value.toLowerCase();
    document.querySelectorAll("tbody tr").forEach(row => { row.style.display = row.innerText.toLowerCase().includes(q) ? "" : "none"; });
});
</script>
</body>
</html>