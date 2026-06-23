<?php
session_start();

/* ====== DB CONNECTION ====== */
$conn = new mysqli("localhost","root","","bhw_system");
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); }

/* ====== GET SELECTED RESIDENT ====== */
if(!isset($_GET['resident_id'])){
    header("Location: nurse_residents.php"); exit();
}
$resident_id = $_GET['resident_id'];
$resident = $conn->query("SELECT * FROM residents WHERE id=$resident_id")->fetch_assoc();

/* ====== FETCH MEMBERS (View Only) ====== */
$members = $conn->query("SELECT * FROM family_members WHERE resident_id=$resident_id ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Family Records | BHW System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* --- UNIFORM THEME (Match sa Consultation/Inventory) --- */
    :root { 
        --primary: #DC143C; 
        --bg-gray: #f8fafc; 
        --sidebar-width: 260px;
    }
    
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body { 
        font-family: 'Segoe UI', sans-serif; 
        background: var(--bg-gray); 
        display: flex; 
        color: #2d3436; 
        min-height: 100vh;
    }

    /* --- SIDEBAR (Consistent with your screenshots) --- */
    .sidebar { 
            width: var(--sidebar-width); 
            background: #fff; 
            height: 100vh; 
            position: fixed; 
            padding: 25px 20px; 
            border-right: 1px solid #edf2f7; 
            z-index: 1000; 
        }
        .sidebar h2 { color: var(--primary); text-align: center; margin-bottom: 30px; font-weight: 800; font-size: 24px; }
        .sidebar a { 
            display: block; padding: 14px 18px; color: #596870; text-decoration: none; 
            border-radius: 12px; margin-bottom: 10px; transition: 0.3s; font-weight: 600; font-size: 15px; 
        }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }
        .sidebar a i { margin-right: 12px; width: 20px; text-align: center; }
    /* --- MAIN CONTENT --- */
    .main-content { 
        margin-left: var(--sidebar-width); 
        width: calc(100% - var(--sidebar-width)); 
        padding: 40px; 
    }
    
    .header-section { 
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; 
    }
    .header-section h1 { font-size: 26px; font-weight: 800; color: #2d3436; }
    
    .btn-back { 
        background: white; color: var(--primary); padding: 10px 20px; 
        border: 2px solid var(--primary); border-radius: 10px; 
        text-decoration: none; font-weight: 700; font-size: 14px; transition: 0.3s;
    }
    .btn-back:hover { background: var(--primary); color: white; }

    /* --- TABLE CARD (White box style) --- */
    .table-card { 
        background: white; border-radius: 20px; padding: 30px; 
        box-shadow: 0 5px 25px rgba(0,0,0,0.02); 
    }
    
    .house-info { margin-bottom: 20px; color: #636e72; font-weight: 500; }
    .house-info span { color: var(--primary); font-weight: 700; margin-right: 15px; }

    table { width: 100%; border-collapse: collapse; }
    th { 
        text-align: left; padding: 15px; color: #b2bec3; 
        font-size: 11px; text-transform: uppercase; letter-spacing: 1px;
        border-bottom: 2px solid #f8f9fa; 
    }
    td { padding: 18px 15px; border-bottom: 1px solid #f8f9fa; font-size: 15px; }

    /* --- STATUS/BADGE STYLE --- */
    .badge-sex { 
        background: #f0ebf8; color: var(--primary); padding: 4px 12px; 
        border-radius: 6px; font-size: 12px; font-weight: 700; 
    }
    .badge-relation { 
        background: #f1f3f5; color: #636e72; padding: 4px 10px; 
        border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase;
    }
</style>
</head>
<body>

<div class="sidebar">
    <h2>👩‍⚕️ Nurse</h2>
    <a href="nurse_dashboard.php" ><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="consultation.php" ><i class="fas fa-stethoscope"></i> Consultation</a>
    <a href="nurse_inventory.php" ><i class="fas fa-boxes-stacked"></i> Inventory</a>
    <a href="nurse_residents.php" class="active" ><i class="fas fa-users"></i> Residents</a>
    <a href="transactions.php"><i class="fas fa-history"></i> Transactions</a>
    <a href="archive_history.php" ><i class="fas fa-archive"></i> Archives</a>
    
    <div class="logout-container">
        <a href="login.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> &nbsp; Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="header-section">
        <h1>Family of <?= htmlspecialchars($resident['head_of_family']) ?></h1>
        <a href="nurse_residents.php" class="btn-back"><i class="fas fa-arrow-left"></i> BACK TO LIST</a>
    </div>

    <div class="table-card">
        <div class="house-info">
            <span><i class="fas fa-home"></i> HH# <?= htmlspecialchars($resident['household_no']) ?></span>
            <span><i class="fas fa-map-marker-alt"></i> Purok <?= htmlspecialchars($resident['purok']) ?></span>
            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($resident['contact'] ?: 'No contact number') ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Age</th>
                    <th>Sex</th>
                    <th>Relationship</th>
                </tr>
            </thead>
            <tbody>
                <?php if($members->num_rows > 0): ?>
                    <?php while($m = $members->fetch_assoc()): ?>
                    <tr>
                        <td><b style="color:#2d3436;"><?= htmlspecialchars($m['full_name']) ?></b></td>
                        <td><?= htmlspecialchars($m['age']) ?> yrs</td>
                        <td><span class="badge-sex"><?= htmlspecialchars($m['sex']) ?></span></td>
                        <td><span class="badge-relation"><?= htmlspecialchars($m['relationship']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; color:#b2bec3; padding:40px;">No family members listed for this household.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>