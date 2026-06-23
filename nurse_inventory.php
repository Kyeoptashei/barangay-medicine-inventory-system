<?php  
session_start();
$conn = new mysqli("localhost", "root", "", "bhw_system"); 
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); } 

$today = date("Y-m-d"); 

// --- FETCH DATA (Filtered: No Expired, No Out of Stock) ---
$query = "SELECT *, DATEDIFF(expiry_date, '$today') as days_left 
          FROM medicines 
          WHERE expiry_date > '$today' 
          AND quantity > 0 
          ORDER BY expiry_date ASC, medicine_name ASC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Nurse View - Available Medicines</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f8fafc; color: #334155; min-height: 100vh; display: flex; }

        /* --- MODERN SIDEBAR CSS (Base sa Screenshot) --- */
        .sidebar { 
            width: 260px; 
            background: #fff; 
            height: 100vh; 
            position: fixed; 
            display: flex; 
            flex-direction: column; 
            padding: 30px 15px;
            border-right: 1px solid #e2e8f0;
        }

        .sidebar-header { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 0 15px 30px; 
        }

        .sidebar-header img { width: 35px; border-radius: 50%; }
        .sidebar-header h2 { color: #DC143C; font-size: 24px; font-weight: 800; }

        .sidebar-menu { flex-grow: 1; }

        .sidebar-menu a { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            padding: 12px 18px; 
            color: #64748b; 
            text-decoration: none; 
            border-radius: 12px; 
            margin-bottom: 8px; 
            font-weight: 500;
            transition: all 0.3s ease;
        }

        /* Active State - Purple Gradient/Solid */
        .sidebar-menu a.active { 
            background: #DC143C; 
            color: #fff; 
            box-shadow: 0 4px 12px rgba(128, 90, 213, 0.3);
        }

        .sidebar-menu a:hover:not(.active) { 
            background: #f1f5f9; 
            color: #805ad5; 
            transform: translateX(5px);
        }

        .sidebar-menu i { font-size: 18px; width: 25px; text-align: center; }

        /* --- MAIN CONTENT --- */
        .main-content { margin-left: 260px; padding: 40px; width: 100%; }
        
        .header-flex { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        .header-title h1 { font-size: 28px; color: #1e293b; margin-bottom: 5px; }
        .view-tag { background: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }

        #searchBar { 
            padding: 12px 20px; 
            border-radius: 12px; 
            border: 1px solid #e2e8f0; 
            width: 350px; 
            outline: none; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            transition: 0.3s;
        }
        #searchBar:focus { border-color: #805ad5; box-shadow: 0 0 0 3px rgba(128, 90, 213, 0.1); }

        /* Table Container */
        .inventory-card { background: #fff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        
        table { width: 100%; border-collapse: collapse; }
        thead th { text-align: left; padding: 15px; color: #94a3b8; font-weight: 700; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; }
        tbody td { padding: 18px 15px; border-bottom: 1px solid #f8fafc; font-size: 14px; color: #334155; }

        .badge { padding: 5px 12px; border-radius: 8px; font-weight: 700; font-size: 11px; }
        .badge-form { background: #e0f2fe; color: #0284c7; }
        .badge-target { background: #f5f3ff; color: #7c3aed; }
        
        .status-available { color: #059669; font-weight: 800; display: flex; align-items: center; gap: 5px; }
        .status-available::before { content: '●'; font-size: 12px; }
        .status-warning { color: #d97706; font-weight: 800; display: flex; align-items: center; gap: 5px; }
        .status-warning::before { content: '●'; font-size: 12px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
       <h2>👩‍⚕️ Nurse</h2>
    </div>
    
    <div class="sidebar-menu">
        <a href="nurse_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="consultation.php"><i class="fas fa-stethoscope"></i> Consultation</a>
        <a href="nurse_inventory.php" class="active" ><i class="fas fa-boxes-stacked"></i> Inventory</a>
        <a href="nurse_residents.php" ><i class="fas fa-users"></i> Residents</a>
        <a href="transactions.php"><i class="fas fa-history"></i> Transactions</a>
        <a href="archive_history.php"><i class="fas fa-archive"></i> Archives</a>
        <a href="logout.php" ><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="header-flex">
        <div class="header-title">
            <h1>Medicine Inventory</h1>
            <span class="view-tag"><i class="fas fa-eye"></i> READ-ONLY ACCESS</span>
        </div>
        <input type="text" id="searchBar" placeholder="🔍 Quick search medicines...">
    </div>

    <div class="inventory-card">
        <table>
            <thead>
                <tr>
                    <th>Medicine & Generic</th>
                    <th>Classification</th>
                    <th>Target Group</th>
                    <th>Stock Level</th>
                    <th>Expiry Date</th>
                    <th>Availability</th>
                </tr>
            </thead>
            <tbody>
                <?php if($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $qty = intval($row['quantity']); 
                        $days = intval($row['days_left']);
                    ?>
                    <tr>
                        <td>
                            <strong style="color: #1e293b;"><?= htmlspecialchars($row['medicine_name']) ?></strong><br>
                            <small style="color:#94a3b8;"><?= htmlspecialchars($row['generic_name']) ?></small>
                        </td>
                        <td><span class="badge badge-form"><?= $row['form'] ?: '---' ?></span></td>
                        <td><span class="badge badge-target"><?= $row['target_group'] ?: '---' ?></span></td>
                        <td>
                            <b style="font-size: 15px;"><?= $qty ?></b> <small style="color: #64748b;"><?= $row['unit'] ?></small>
                        </td>
                        <td>
                            <span style="<?= $days <= 30 ? 'color:#d97706; font-weight:600;' : '' ?>">
                                <?= date('M d, Y', strtotime($row['expiry_date'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if($days <= 30): ?>
                                <span class="status-warning">EXPIRING SOON</span>
                            <?php else: ?>
                                <span class="status-available">AVAILABLE</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:50px; color: #94a3b8;">Walang available na gamot sa ngayon.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById("searchBar").addEventListener("keyup", function() {
    let q = this.value.toLowerCase();
    document.querySelectorAll("tbody tr").forEach(row => { 
        row.style.display = row.innerText.toLowerCase().includes(q) ? "" : "none"; 
    });
});
</script>

</body>
</html>