<?php
session_start();
$conn = new mysqli("localhost","root","","bhw_system");
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); }

/* ====== DELETE ACTION ====== */
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $role = $_GET['role'];
    if($role == 'Head'){
        $conn->query("DELETE FROM residents WHERE id = $id");
    } else {
        $conn->query("DELETE FROM family_members WHERE id = $id");
    }
    header("Location: residents.php?deleted=1"); exit();
}

/* ====== SAVE ACTION (Add/Update) ====== */
if(isset($_POST["saveResident"])){
    $resId = $_POST["residentId"]; 
    $role = $_POST["residentRole"]; 
    $h = $conn->real_escape_string($_POST["householdNumber"]);
    $name = $conn->real_escape_string($_POST["fullName"]);
    $age = $conn->real_escape_string($_POST["age"]);
    $bd = $_POST["birthdate"];
    $p = $conn->real_escape_string($_POST["purok"]);
    $cls = $conn->real_escape_string($_POST["classification"]);
    $is_preg = isset($_POST["is_pregnant"]) ? 1 : 0;
    $is_pwd = isset($_POST["is_pwd"]) ? 1 : 0;
    $contact = isset($_POST["contactNumber"]) ? $conn->real_escape_string($_POST["contactNumber"]) : "";

    if(!empty($resId)){
        if($role == 'Head'){
            $conn->query("UPDATE residents SET household_no='$h', purok='$p', head_of_family='$name', age='$age', birthdate='$bd', contact='$contact', classification='$cls', is_pregnant='$is_preg', is_pwd='$is_pwd' WHERE id=$resId");
        } else {
            $conn->query("UPDATE family_members SET full_name='$name', age='$age', birthdate='$bd', classification='$cls', is_pregnant='$is_preg', is_pwd='$is_pwd' WHERE id=$resId");
        }
    } else {
        $checkHH = $conn->query("SELECT id FROM residents WHERE household_no = '$h' LIMIT 1");
        if($checkHH->num_rows > 0){
            $row = $checkHH->fetch_assoc();
            $headId = $row['id'];
            $conn->query("INSERT INTO family_members (resident_id, full_name, age, birthdate, classification, is_pregnant, is_pwd) 
                          VALUES ('$headId', '$name', '$age', '$bd', '$cls', '$is_preg', '$is_pwd')");
        } else {
            $conn->query("INSERT INTO residents (household_no, purok, head_of_family, age, birthdate, contact, classification, is_pregnant, is_pwd) 
                          VALUES ('$h', '$p', '$name', '$age', '$bd', '$contact', '$cls', '$is_preg', '$is_pwd')");
        }
    }
    header("Location: residents.php?success=1"); exit();
}

/* ====== FETCH DATA ====== */
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

$countRes = $conn->query("SELECT COUNT(id) AS total FROM residents")->fetch_assoc()['total'];
$nextHH = "HH-".str_pad(($countRes + 1), 3, "0", STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Residents Management | BHW System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7fe; display: flex; min-height: 100vh; }
        .sidebar { width: 220px; position: fixed; height: 100vh; background: #fff; padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
        .sidebar h2 { color: #DC143C; font-size: 20px; text-align: center; margin-bottom: 30px; font-weight: 800; }
        .sidebar-menu a { display: block; padding: 12px; color: #000000; text-decoration: none; border-radius: 10px; margin-bottom: 5px; font-weight: 500; }
        .sidebar-menu a.active { background: #DC143C; color: #fff; }
        .main-content { margin-left: 220px; padding: 30px; width: calc(100% - 220px); }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .filter-card { background: #fff; padding: 15px 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px; display: flex; gap: 10px; align-items: center; }
        .filter-card input, .filter-card select { padding: 8px 12px; border-radius: 10px; border: 1.5px solid #edf2f7; outline: none; font-size: 13px; }
        .table-container { background: #fff; border-radius: 20px; padding: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: #64748b; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; }
        td { padding: 15px; border-bottom: 1px solid #f8fafc; font-size: 14px; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; display: inline-block; margin-right: 2px; }
        .badge-adult { background: #eef2ff; color: #4f46e5; }
        .badge-child { background: #dcfce7; color: #15803d; }
        .badge-teen { background: #fff7ed; color: #c2410c; }
        .badge-senior { background: #fee2e2; color: #991b1b; }
        .badge-baby { background: #e0f2fe; color: #0369a1; }
        .badge-toddler { background: #fef3c7; color: #d97706; }
        .badge-pregnant { background: #fce7f3; color: #db2777; }
        .badge-pwd { background: #ede9fe; color: #7c3aed; }

        .modal { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(15, 23, 42, 0.4); visibility: hidden; opacity: 0; transition: 0.3s; z-index: 1000; }
        .modal.active { visibility: visible; opacity: 1; }
        .modal-content { background: #fff; border-radius: 24px; padding: 30px; width: 500px; max-height: 90vh; overflow-y: auto; position: relative; }
        label { font-size: 10px; font-weight: 800; color: #4f46e5; display: block; margin-top: 15px; text-transform: uppercase; }
        .modal-content input, .modal-content select { width: 100%; padding: 12px; border-radius: 12px; border: 2.2px solid #f1f5f9; margin-top: 5px; outline: none; }
        .btn-add { background: #6366f1; color: #fff; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 700; }
        
        .search-results { position: absolute; background: white; width: calc(100% - 60px); border-radius: 12px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); z-index: 10; display: none; border: 1px solid #f1f5f9; max-height: 150px; overflow-y: auto; }
        .search-item { padding: 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f8fafc; }
        .search-item:hover { background: #f4f7fe; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>👩‍⚕️ BHW </h2>
    <div class="sidebar-menu">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
    
        <a href="inventory_actions.php"><i class="fas fa-clipboard-list"></i> Stock Reports</a>
        <a href="inventory.php"><i class="fas fa-pills"></i> Inventory</a>
        <a href="residents.php" class="active"><i class="fas fa-users"></i> Residents</a>
        <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="header-section">
        <h1>Residents Records</h1>
        <button class="btn-add" onclick="openModal()"><i class="fas fa-plus"></i> Add Resident</button>
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
        <input type="text" id="sName" placeholder="Search name..." style="flex:1;" onkeyup="filterT()">
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
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody id="residentTable">
                <?php while($row = $residents->fetch_assoc()): 
                    // Calculation of Age and Classification logic
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
                    <td style="text-align:center;">
                        <button onclick='editResident(<?= json_encode($row) ?>)' style="border:none; background:#ffb703; color:white; padding:6px 10px; border-radius:8px; cursor:pointer;"><i class="fas fa-pen"></i></button>
                        <a href="residents.php?delete=<?= $row['id'] ?>&role=<?= $row['role'] ?>" onclick="return confirm('Sigurado ka beh?')" style="border:none; background:#fb8500; color:white; padding:7px 11px; border-radius:8px; text-decoration:none; display:inline-block;"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="resModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle" style="color:#4f46e5; font-weight:800;"><i class="fas fa-user-plus"></i> New Resident</h3>
        <form id="residentForm" method="POST" autocomplete="off">
            <input type="hidden" name="residentId" id="resId">
            <input type="hidden" name="residentRole" id="resRole">
            
            <label>HOUSEHOLD #</label>
            <input type="text" name="householdNumber" id="hh_input" placeholder="Type HH-..." required>
            <div id="hh_dropdown" class="search-results"></div>

            <div style="display:flex; gap:10px;">
                <div style="flex:1;"><label>PUROK</label><select name="purok" id="p_disp"><option value="1">1</option><option value="2">2</option><option value="3A">3A</option><option value="3B">3B</option></select></div>
                <div style="flex:1;"><label>CLASSIFICATION</label><input type="text" name="classification" id="c_disp" placeholder="Auto-fills..." readonly></div>
            </div>

            <div style="display:flex; gap:20px; margin-top:10px; background:#f8fafc; padding:10px; border-radius:10px;">
                <label style="margin:0; cursor:pointer; font-size:11px;"><input type="checkbox" name="is_pregnant" id="chk_preg" value="1"> PREGNANT</label>
                <label style="margin:0; cursor:pointer; font-size:11px;"><input type="checkbox" name="is_pwd" id="chk_pwd" value="1"> PWD</label>
            </div>

            <label>FULL NAME</label>
            <input type="text" name="fullName" id="fName" required>

            <div style="display:flex; gap:10px;">
                <div style="flex:1.5;"><label>BIRTHDATE</label><input type="date" name="birthdate" id="bdate" onchange="calc()" required></div>
                <div style="flex:1;"><label>AGE</label><input type="text" name="age" id="a_disp" placeholder="Auto-fills..." readonly></div>
            </div>

            <label>CONTACT NUMBER</label>
            <input type="text" name="contactNumber" id="cNum" placeholder="09xxxxxxxxx">

            <button type="submit" name="saveResident" style="width:100%; background:#6366f1; color:white; padding:14px; border-radius:12px; border:none; margin-top:25px; font-weight:800; cursor:pointer;">Save Resident</button>
            <button type="button" onclick="closeModal()" style="width:100%; background:none; border:none; color:gray; margin-top:10px; cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

<script>
function calc() {
    let bdVal = document.getElementById('bdate').value;
    if(!bdVal) return;
    let bd = new Date(bdVal);
    let today = new Date();
    
    let diffMonths = (today.getFullYear() - bd.getFullYear()) * 12 + (today.getMonth() - bd.getMonth());
    if (today.getDate() < bd.getDate()) diffMonths--;

    let ageDisplay = "";
    let cls = "";

    if (diffMonths < 12) {
        let act = diffMonths < 0 ? 0 : diffMonths;
        ageDisplay = act + " months";
        cls = "Baby";
    } else {
        let years = Math.floor(diffMonths / 12);
        ageDisplay = years + " years old";
        if (years >= 1 && years <= 3) cls = "Toddler";
        else if (years > 3 && years <= 12) cls = "Child";
        else if (years > 12 && years <= 19) cls = "Teen";
        else if (years >= 60) cls = "Senior";
        else cls = "Adult";
    }

    document.getElementById('a_disp').value = ageDisplay;
    document.getElementById('c_disp').value = cls;
}

function openModal() { 
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> New Resident';
    document.getElementById('residentForm').reset();
    document.getElementById('resId').value = "";
    document.getElementById('resRole').value = "";
    document.getElementById('hh_input').value = "<?= $nextHH ?>";
    document.getElementById('resModal').classList.add('active'); 
}

function closeModal() { document.getElementById('resModal').classList.remove('active'); }

function editResident(data) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Edit Resident';
    document.getElementById('resId').value = data.id;
    document.getElementById('resRole').value = data.role;
    document.getElementById('hh_input').value = data.household_no;
    document.getElementById('p_disp').value = data.purok;
    document.getElementById('fName').value = data.full_name;
    document.getElementById('bdate').value = data.birthdate;
    
    // Call calc after setting birthdate to ensure ageDisplay is formatted
    setTimeout(calc, 10); 
    
    document.getElementById('chk_preg').checked = data.is_pregnant == 1;
    document.getElementById('chk_pwd').checked = data.is_pwd == 1;
    document.getElementById('cNum').value = data.contact || "";
    document.getElementById('resModal').classList.add('active');
}

const hhIn = document.getElementById('hh_input');
const hhDrop = document.getElementById('hh_dropdown');
hhIn.addEventListener('input', function() {
    let q = this.value;
    if(q.length > 0) {
        fetch('get_purok.php?search=' + q).then(res => res.json()).then(data => {
            hhDrop.innerHTML = '';
            if(data.length > 0) {
                hhDrop.style.display = 'block';
                data.forEach(item => {
                    let d = document.createElement('div');
                    d.className = 'search-item';
                    d.innerHTML = `<strong>${item.household_no}</strong> (Purok ${item.purok})`;
                    d.onclick = () => {
                        hhIn.value = item.household_no;
                        document.getElementById('p_disp').value = item.purok;
                        hhDrop.style.display = 'none';
                    };
                    hhDrop.appendChild(d);
                });
            } else { hhDrop.style.display = 'none'; }
        });
    } else { hhDrop.style.display = 'none'; }
});

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