<?php
session_start();
$conn = new mysqli("localhost","root","","bhw_system");
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); }

if(!isset($_GET['resident_id'])){ header("Location: residents.php"); exit(); }
$resident_id = $_GET['resident_id'];
$resident = $conn->query("SELECT * FROM residents WHERE id=$resident_id")->fetch_assoc();

/* ====== ADD FAMILY MEMBER ====== */
if(isset($_POST["addMember"])){
  $name = $_POST["memberName"];
  $age = $_POST["memberAge"];
  $bd = $_POST["memberBirthdate"]; 
  $sex = $_POST["memberSex"];
  $rel = $_POST["memberRelationship"];
  $conn->query("INSERT INTO family_members (resident_id,full_name,age,birthdate,sex,relationship) VALUES ('$resident_id','$name','$age','$bd','$sex','$rel')");
  header("Location: family.php?resident_id=$resident_id"); exit();
}

/* ====== EDIT MEMBER ====== */
if(isset($_POST["updateMember"])){
  $id = $_POST["editMemberId"];
  $name = $_POST["editName"];
  $age = $_POST["editAge"];
  $bd = $_POST["editBirthdate"]; 
  $sex = $_POST["editSex"];
  $rel = $_POST["editRelationship"];
  $conn->query("UPDATE family_members SET full_name='$name', age='$age', birthdate='$bd', sex='$sex', relationship='$rel' WHERE id=$id");
  header("Location: family.php?resident_id=$resident_id"); exit();
}

if(isset($_GET['delete_member'])){
  $conn->query("DELETE FROM family_members WHERE id=".$_GET['delete_member']);
  header("Location: family.php?resident_id=$resident_id"); exit();
}

$members = $conn->query("SELECT * FROM family_members WHERE resident_id=$resident_id ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Family Members | BHW System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Clean Modern Theme */
    :root { --primary: #DC143C; --bg: #f8f9fa; --text: #444; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

    /* Sidebar consistent with Dashboard */
    .sidebar { width: 220px; position: fixed; height: 100vh; background: #fff; padding: 20px; border-right: 1px solid #eee; z-index: 100; }
    .sidebar h2 { color: var(--primary); font-size: 22px; text-align: center; margin-bottom: 25px; font-weight: 800; }
    .sidebar-menu { list-style: none; }
    .sidebar-menu a { display: block; padding: 12px; color: #444; text-decoration: none; border-radius: 12px; margin-bottom: 5px; font-weight: 600; transition: 0.3s; }
    .sidebar-menu a:hover { background: #f0f0f0; color: var(--primary); }
    .sidebar-menu a.active { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(118, 75, 162, 0.3); }

    .container { margin-left: 220px; padding: 40px; }
    
    .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .header-box h1 { font-size: 24px; font-weight: 700; color: #2d3436; }

    /* Button Styling */
    .btn { padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 14px; }
    .btn-add { background: var(--primary); color: white; }
    .btn-back { background: #edf2f7; color: #4a5568; }
    .btn:hover { opacity: 0.9; transform: translateY(-2px); }

    /* Table Styling consistent with your Image */
    .table-box { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #edf2f7; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 15px; color: #b2bec3; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f3f5; }
    td { padding: 15px; border-bottom: 1px solid #f1f3f5; font-size: 14px; color: #2d3436; font-weight: 500; }
    
    .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #eef2ff; color: var(--primary); }

    /* Action Buttons */
    .action-btn { padding: 8px; border: none; border-radius: 8px; cursor: pointer; color: white; margin-right: 5px; transition: 0.2s; }
    .edit-btn { background: #6c5ce7; }
    .del-btn { background: #ff7675; }

    /* Modal Styling */
    .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; visibility: hidden; opacity: 0; transition: .2s; z-index: 1000; }
    .modal.active { visibility: visible; opacity: 1; }
    .modal-content { background: white; padding: 30px; border-radius: 20px; width: 100%; max-width: 400px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
    .modal-content h3 { margin-bottom: 20px; color: var(--primary); }

    input, select { width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 10px; border: 1px solid #eee; background: #f8f9fa; outline: none; }
    input:focus { border-color: var(--primary); }
    label { font-size: 11px; color: #a0aec0; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; display: block; }
</style>
</head>
<body>

<div class="sidebar">
    <h2>👩‍⚕️ BHW </h2>
    <div class="sidebar-menu">
        <a href="dashboard.php" ><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="health_matrix.php"><i class="fas fa-chart-line"></i> Health Matrix</a>
        <a href="inventory_actions.php"><i class="fas fa-clipboard-list"></i> Stock Reports</a>
        <a href="inventory.php"><i class="fas fa-pills"></i> Inventory</a>
        <a href="residents.php" class="active"><i class="fas fa-users"></i> Residents</a>
         <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container">
    <div class="header-box">
        <div>
            <p style="color: var(--primary); font-weight: 700; font-size: 13px;">FAMILY RECORD</p>
            <h1>House of <?= $resident['head_of_family'] ?></h1>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="residents.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            <button class="btn btn-add" onclick="document.getElementById('addModal').classList.add('active')">
                <i class="fas fa-plus"></i> Add Family Member
            </button>
        </div>
    </div>

    <div class="table-box">
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Birthdate</th>
                    <th>Age</th>
                    <th>Sex</th>
                    <th>Relationship</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($m = $members->fetch_assoc()): ?>
                <tr>
                    <td><strong style="color:#2d3436;"><?= $m['full_name'] ?></strong></td>
                    <td><?= date("M d, Y", strtotime($m['birthdate'])) ?></td>
                    <td><?= $m['age'] ?> yrs</td>
                    <td><span class="status-pill"><?= $m['sex'] ?></span></td>
                    <td><span style="color: #636e72;"><?= $m['relationship'] ?></span></td>
                    <td>
                        <button class="action-btn edit-btn" onclick="editMember('<?= $m['id'] ?>','<?= addslashes($m['full_name']) ?>','<?= $m['age'] ?>','<?= $m['birthdate'] ?>','<?= $m['sex'] ?>','<?= $m['relationship'] ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="family.php?resident_id=<?= $resident_id ?>&delete_member=<?= $m['id'] ?>" onclick="return confirm('Remove this member?')">
                            <button class="action-btn del-btn"><i class="fas fa-trash"></i></button>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <h3>➕ New Member</h3>
        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="memberName" placeholder="e.g. Jane Doe" required>
            <label>Birthdate</label>
            <input type="date" name="memberBirthdate" onchange="calcAge(this.value, 'ma')" required>
            <label>Age</label>
            <input type="number" name="memberAge" id="ma" readonly>
            <label>Sex</label>
            <select name="memberSex"><option>Male</option><option>Female</option></select>
            <label>Relationship</label>
            <input type="text" name="memberRelationship" placeholder="e.g. Spouse, Son">
            <button class="btn btn-add" name="addMember" style="width:100%; justify-content: center;">Save Member</button>
            <button type="button" class="btn" onclick="closeModal('addModal')" style="width:100%; background:none; color:#aaa; margin-top:10px;">Cancel</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>✏️ Edit Member</h3>
        <form method="POST">
            <input type="hidden" name="editMemberId" id="editMemberId">
            <label>Full Name</label>
            <input type="text" name="editName" id="editName">
            <label>Birthdate</label>
            <input type="date" name="editBirthdate" id="editBirthdate" onchange="calcAge(this.value, 'ea')" required>
            <label>Age</label>
            <input type="number" name="editAge" id="ea" readonly>
            <label>Sex</label>
            <select name="editSex" id="editSex"><option>Male</option><option>Female</option></select>
            <label>Relationship</label>
            <input type="text" name="editRelationship" id="editRelationship">
            <button class="btn btn-add" name="updateMember" style="width:100%; justify-content: center;">Update Changes</button>
            <button type="button" class="btn" onclick="closeModal('editModal')" style="width:100%; background:none; color:#aaa; margin-top:10px;">Cancel</button>
        </form>
    </div>
</div>

<script>
function closeModal(id){document.getElementById(id).classList.remove("active");}
function editMember(id,n,a,bd,s,r){
  document.getElementById('editMemberId').value=id;
  document.getElementById('editName').value=n;
  document.getElementById('ea').value=a;
  document.getElementById('editBirthdate').value=bd;
  document.getElementById('editSex').value=s;
  document.getElementById('editRelationship').value=r;
  document.getElementById('editModal').classList.add('active');
}
function calcAge(birthdate, targetId) {
    if(!birthdate) return;
    let birthday = new Date(birthdate);
    let today = new Date();
    let age = today.getFullYear() - birthday.getFullYear();
    let m = today.getMonth() - birthday.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthday.getDate())) { age--; }
    document.getElementById(targetId).value = age;
}
</script>
</body>
</html>