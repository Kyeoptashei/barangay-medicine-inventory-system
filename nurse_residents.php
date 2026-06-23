<?php
session_start();
$conn = new mysqli("localhost","root","","bhw_system");
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); }

/* ====== FETCH DATA (View Only) ====== */
$sql = "
    SELECT * FROM (
        SELECT id, household_no, purok, head_of_family AS full_name, age, birthdate, classification, is_pregnant, is_pwd, 'Head' AS role 
        FROM residents
        UNION ALL
        SELECT fm.id, r.household_no, r.purok, fm.full_name, fm.age, fm.birthdate, fm.classification, fm.is_pregnant, fm.is_pwd, 'Member' AS role 
        FROM family_members fm 
        JOIN residents r ON fm.resident_id = r.id
    ) AS combined
    ORDER BY household_no ASC, role DESC"; 

$residents = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Nurse View - Residents | BHW System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7fe; display: flex; min-height: 100vh; }
        .sidebar { width: 220px; position: fixed; height: 100vh; background: #fff; padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
        .sidebar h2 { color: #DC143C; font-size: 20px; text-align: center; margin-bottom: 30px; font-weight: 800; }
        .sidebar-menu a { display: block; padding: 12px; color: #64748b; text-decoration: none; border-radius: 10px; margin-bottom: 5px; font-weight: 500; }
        .sidebar-menu a.active { background: #DC143C; color: #fff; }
        .main-content { margin-left: 220px; padding: 30px; width: calc(100% - 220px); }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        
        /* Filter Card Design */
        .filter-card { background: #fff; padding: 15px 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px; display: flex; gap: 10px; align-items: center; }
        .filter-card input, .filter-card select { padding: 8px 12px; border-radius: 10px; border: 1.5px solid #edf2f7; outline: none; font-size: 13px; }
        
        .table-container { background: #fff; border-radius: 20px; padding: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: #64748b; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; }
        td { padding: 15px; border-bottom: 1px solid #f8fafc; font-size: 14px; }
        
        /* Badge Styles */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block; margin-right: 2px; }
        .badge-adult { background: #eef2ff; color: #4f46e5; }
        .badge-child { background: #dcfce7; color: #15803d; }
        .badge-teen { background: #fff7ed; color: #c2410c; }
        .badge-senior { background: #fee2e2; color: #991b1b; }
        .badge-baby { background: #e0f2fe; color: #0369a1; }
        .badge-toddler { background: #fef3c7; color: #d97706; }
        .badge-pregnant { background: #fce7 pink; color: #db2777; background: #fce7f3;}
        .badge-pwd { background: #ede9fe; color: #7c3aed; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>👩‍⚕️ NURSE </h2>
    <div class="sidebar-menu">
    <a href="nurse_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="consultation.php"><i class="fas fa-stethoscope"></i> Consultation</a>
    <a href="nurse_inventory.php"><i class="fas fa-boxes-stacked"></i> Inventory</a>
    <a href="nurse_residents.php"class="active"><i class="fas fa-users"></i> Residents</a>
    <a href="transactions.php" ><i class="fas fa-history"></i> Transactions</a>
    <a href="archive_history.php"><i class="fas fa-archive"></i> Archives</a>
    <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="header-section">
        <h1 style="color: #1e293b;">Residents Directory <span style="font-size: 14px; color: #64748b; font-weight: normal;">(View Only)</span></h1>
    </div>

    <div class="filter-card">
        <input type="text" id="sHH" placeholder="# HH" style="width: 80px;" onkeyup="filterT()">
        <select id="sPurok" onchange="filterT()">
            <option value="">Purok</option><option>1</option><option>2</option><option>3A</option><option>3B</option>
        </select>
        <select id="sClass" onchange="filterT()">
            <option value="">All Status</option>
            <option value="BABY">Baby</option><option value="TODDLER">Toddler</option><option value="CHILD">Child</option>
            <option value="TEEN">Teen</option><option value="ADULT">Adult</option><option value="SENIOR">Senior</option>
            <option value="PREG">Pregnant</option><option value="PWD">PWD</option>
        </select>
        <input type="text" id="sName" placeholder="Search resident name..." style="flex:1;" onkeyup="filterT()">
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>HH #</th>
                    <th>Classification</th>
                    <th>Purok</th>
                    <th>Full Name</th>
                    <th>Birthdate</th>
                    <th>Age</th>
                </tr>
            </thead>
            <tbody id="residentTable">
                <?php while($row = $residents->fetch_assoc()): 
                    $birthDate = new DateTime($row['birthdate']);
                    $today = new DateTime();
                    $diff = $today->diff($birthDate);
                    
                    $ageMonths = ($diff->y * 12) + $diff->m;
                    $finalClass = "";
                    $ageDisplay = "";

                    if ($ageMonths < 12) {
                        $finalClass = "BABY";
                        $ageDisplay = $ageMonths . " months";
                    } else {
                        $years = $diff->y;
                        $ageDisplay = $years . " years old";
                        if ($years >= 1 && $years <= 3) $finalClass = "TODDLER";
                        elseif ($years > 3 && $years <= 12) $finalClass = "CHILD";
                        elseif ($years > 12 && $years <= 19) $finalClass = "TEEN";
                        elseif ($years >= 60) $finalClass = "SENIOR";
                        else $finalClass = "ADULT";
                    }
                    $badgeColorClass = "badge-" . strtolower($finalClass);
                ?>
                <tr>
                    <td style="font-weight: 800; color:#4f46e5;"><?= $row['household_no'] ?></td>
                    <td>
                        <span class="badge <?= $badgeColorClass ?>"><?= $finalClass ?></span>
                        <?php if($row['is_pregnant']): ?><span class="badge badge-pregnant">PREG</span><?php endif; ?>
                        <?php if($row['is_pwd']): ?><span class="badge badge-pwd">PWD</span><?php endif; ?>
                    </td>
                    <td><span style="color:#4f46e5; font-weight:600; background:#eef2ff; padding:4px 10px; border-radius:20px;">Purok <?= $row['purok'] ?></span></td>
                    <td style="font-weight: 500; color: #1e293b;"><?= htmlspecialchars($row['full_name']) ?></td>
                    <td style="color:#64748b;"><?= date('M d, Y', strtotime($row['birthdate'])) ?></td>
                    <td style="font-weight:700; color: #0f172a;"><?= $ageDisplay ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterT() {
    const hh = document.getElementById("sHH").value.toLowerCase();
    const nm = document.getElementById("sName").value.toLowerCase();
    const pk = document.getElementById("sPurok").value;
    const cls = document.getElementById("sClass").value.toUpperCase();
    const rows = document.querySelectorAll("#residentTable tr");
    
    rows.forEach(r => {
        const hhM = r.cells[0].textContent.toLowerCase().includes(hh);
        const pkM = !pk || r.cells[2].textContent.includes(pk);
        const nmM = r.cells[3].textContent.toLowerCase().includes(nm);
        const clsM = !cls || r.cells[1].textContent.toUpperCase().includes(cls);
        r.style.display = (hhM && pkM && nmM && clsM) ? "" : "none";
    });
}
</script>

</body>
</html>