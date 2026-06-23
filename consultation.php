<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bhw_system");
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); }

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Nurse') { header("Location: index.php"); exit(); }

$today = date("Y-m-d");

/* ==========================================
    1. SAVING LOGIC
   ========================================== */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_consultation'])) {
    $h_no = $_POST['household_no'];
    $stmt_res = $conn->prepare("SELECT id FROM residents WHERE household_no = ?");
    $stmt_res->bind_param("s", $h_no);
    $stmt_res->execute();
    $res_id_data = $stmt_res->get_result()->fetch_assoc();
    
    $resident_id = $res_id_data['id'] ?? 0;
    $patient_name = $_POST['family_member_name'];
    $med_id = !empty($_POST['medicine_id']) ? $_POST['medicine_id'] : null;
    $qty = intval($_POST['quantity_given']);
    $date = $_POST['date_visit'];
    $act = $_POST['action_taken'];
    $age = $_POST['age'] ?? '';
    $ht = $_POST['height'] ?? '';
    $wt = $_POST['weight'] ?? '';
    $bp = $_POST['bp'] ?? '';
    $symp = ($_POST['symptoms'] === 'Other') ? $_POST['other_symptom'] : $_POST['symptoms'];

    $stmt = $conn->prepare("INSERT INTO consultations (resident_id, patient_name, household_no, medicine_id, symptoms, quantity_given, date_visit, action_taken, age, height, weight, bp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issisissssss", $resident_id, $patient_name, $h_no, $med_id, $symp, $qty, $date, $act, $age, $ht, $wt, $bp);

    if($stmt->execute()){
        if ($med_id && $qty > 0) { 
            $conn->query("UPDATE medicines SET quantity = quantity - $qty WHERE id = $med_id"); 
        }
        echo "<script>alert('Consultation Saved!'); window.location='consultation.php';</script>";
    }
    exit();
}

/* ==========================================
    2. DATA FETCHING 
   ========================================== */
$search_pool = [];
$heads = $conn->query("SELECT id, household_no, head_of_family, birthdate, is_pregnant FROM residents");
while($h = $heads->fetch_assoc()){
    $search_pool[] = ['name' => $h['head_of_family'], 'hh_no' => $h['household_no'], 'bday' => $h['birthdate'], 'preg' => $h['is_pregnant']];
}
$m_res = $conn->query("SELECT fm.full_name, fm.birthdate, fm.is_pregnant, r.household_no FROM family_members fm JOIN residents r ON fm.resident_id = r.id");
while($m = $m_res->fetch_assoc()){
    $search_pool[] = ['name' => $m['full_name'], 'hh_no' => $m['household_no'], 'bday' => $m['birthdate'], 'preg' => $m['is_pregnant']];
}

$all_meds = [];
$m_inv = $conn->query("SELECT id, medicine_name, generic_name, quantity, form, target_group FROM medicines WHERE quantity > 0 AND expiry_date > '$today'");
while($m = $m_inv->fetch_assoc()){ $all_meds[] = $m; }

$symptoms_logic = [
    "Fever" => ["keywords" => ["Paracetamol", "Biogesic"]],
    "Hypertension" => ["keywords" => ["Losartan", "Amlodipine"]],
    "Cough" => ["keywords" => ["Ambroxol", "Lagundi", "Carbocisteine"]],
    "Cold" => ["keywords" => ["Phenylephrine"]],
    "Diarrhea" => ["keywords" => ["Loperamide", "Bacillus Clausii", "Oral Rehydration"]],
    "Allergy" => ["keywords" => ["Cetirizine"]],
    "Diabetes" => ["keywords" => ["Metformin"]],
    "Prenatal" => ["keywords" => ["Folic", "Ferrous", "Multivitamins", "Vitamin", "Calcium"]]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consultation | BHW System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #DC143C; --sidebar-width: 260px; --bg: #f4f7fe; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); display: flex; }
        .sidebar { width: var(--sidebar-width); background: #fff; height: 100vh; position: fixed; padding: 25px 20px; border-right: 1px solid #e0e0e0; z-index: 1000; }
        .sidebar h2 { color: var(--primary); text-align: center; margin-bottom: 30px; font-weight: 800; }
        .sidebar a { display: block; padding: 14px 18px; color: #596870; text-decoration: none; border-radius: 12px; margin-bottom: 10px; font-weight: 600; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); min-height: 100vh; padding: 40px; }
        .table-card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); margin-top: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #f0f2f5; color: #a0aec0; font-size: 11px; text-transform: uppercase; }
        td { padding: 18px 15px; border-bottom: 1px solid #f0f2f5; font-size: 13px; }
        .status-badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; background: #f3f0ff; color: var(--primary); }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); overflow-y: auto; }
        .modal-content { background: white; margin: 30px auto; width: 550px; padding: 35px; border-radius: 24px; }
        input, select { width: 100%; padding: 12px; margin-top: 6px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 14px; }
        label { font-weight: 700; font-size: 11px; color: #4a5568; margin-top: 15px; display: block; text-transform: uppercase; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        #resSuggestions { background: white; border: 1px solid #e2e8f0; position: absolute; width: 100%; z-index: 100; display: none; max-height: 200px; overflow-y: auto; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .suggest-item { padding: 12px; cursor: pointer; border-bottom: 1px solid #f7fafc; }
        .suggest-item:hover { background: #f4f7fe; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>👩‍⚕️ Nurse</h2>
    <a href="nurse_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="consultation.php" class="active"><i class="fas fa-stethoscope"></i> Consultation</a>
    <a href="nurse_inventory.php"><i class="fas fa-boxes-stacked"></i> Inventory</a>
    <a href="nurse_residents.php"><i class="fas fa-users"></i> Residents</a>
    <a href="transactions.php"><i class="fas fa-history"></i> Transactions</a>
    <a href="archive_history.php"><i class="fas fa-archive"></i> Archives</a>
    <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="margin:0; font-weight:800; color:#1a202c;">Consultation History</h1>
            <p style="color:#718096; font-size:14px;">Records of patient visits and medicine distribution.</p>
        </div>
        <button onclick="openModal('cModal')" style="background:#fbc02d; border:none; padding:14px 25px; border-radius:12px; font-weight:800; cursor:pointer; box-shadow: 0 4px 12px rgba(251, 192, 45, 0.3);">+ NEW CONSULTATION</button>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>HH #</th>
                    <th>Patient Name</th>
                    <th>Vitals (BP/H/W)</th>
                    <th>Symptoms</th>
                    <th>Medicine</th>
                    <th>Qty</th>
                    <th>Action Taken</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recs = $conn->query("SELECT c.*, m.medicine_name FROM consultations c LEFT JOIN medicines m ON c.medicine_id = m.id ORDER BY c.date_visit DESC LIMIT 15");
                while($r = $recs->fetch_assoc()): ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($r['date_visit'])) ?></td>
                    <td style="font-weight:700; color:var(--primary);"><?= $r['household_no'] ?></td>
                    <td>
                        <div style="font-weight:700; color:#2d3748;"><?= htmlspecialchars($r['patient_name']) ?></div>
                        <div style="font-size:11px; color:#a0aec0;">Age: <?= $r['age'] ?></div>
                    </td>
                    <td style="font-size:11px; color:#718096;">
                        <b>BP:</b> <?= $r['bp'] ?: '--' ?><br>
                        <b>H:</b> <?= $r['height'] ?: '--' ?> cm | <b>W:</b> <?= $r['weight'] ?: '--' ?> kg
                    </td>
                    <td><?= htmlspecialchars($r['symptoms']) ?></td>
                    <td><span class="status-badge"><?= $r['medicine_name'] ?: 'N/A' ?></span></td>
                    <td style="font-weight:700;"><?= $r['quantity_given'] ?></td>
                    <td><small><?= $r['action_taken'] ?></small></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="cModal" class="modal">
    <div class="modal-content">
        <h2 style="color:var(--primary);">New Record</h2>
        <form method="POST" id="consultationForm">
            <div style="position:relative;">
                <label>Search Resident Name</label>
                <input type="text" id="resSearch" onkeyup="filterResidents()" placeholder="Type name..." autocomplete="off" required>
                <div id="resSuggestions"></div>
            </div>

            <div class="grid-2">
                <div><label>Patient Name</label><input type="text" name="family_member_name" id="selectedName" readonly style="background:#f7fafc;" required></div>
                <div><label>HH #</label><input type="text" name="household_no" id="selectedHH" readonly style="background:#f7fafc;" required></div>
            </div>

            <div class="grid-3">
                <div><label>Age</label><input type="text" name="age" id="age" readonly style="background:#f7fafc;"></div>
                <div><label>BP</label><input type="text" name="bp" placeholder="120/80"></div>
                <div><label>Visit Date</label><input type="date" name="date_visit" value="<?= $today ?>"></div>
            </div>

            <div class="grid-2">
                <div><label>Height (cm)</label><input type="text" name="height" placeholder="cm"></div>
                <div><label>Weight (kg)</label><input type="text" name="weight" placeholder="kg"></div>
                <input type="hidden" id="isPregnant">
            </div>

            <label>Symptom</label>
            <select name="symptoms" id="symptomSelect" onchange="toggleOtherSymptom(); autoSelectMedicine();" required>
                <option value="">-- Select --</option>
                <option value="Fever">Fever</option>
                <option value="Cough">Cough</option>
                <option value="Cold">Cold</option>
                <option value="Diarrhea">Diarrhea</option>
                <option value="Hypertension">Hypertension</option>
                <option value="Diabetes">Diabetes</option>
                <option value="Prenatal">Prenatal</option>
                <option value="Other">Other</option>
            </select>

            <div id="otherSymptomContainer" style="display:none; margin-top: 5px;">
                <input type="text" name="other_symptom" id="otherSymptomInput" placeholder="Specify symptom...">
            </div>

            <div class="grid-2" style="background: #fdf2ff; padding: 10px; border-radius: 10px; margin-top: 15px;">
                <div><label>Form</label><input type="text" id="medClass" readonly style="border:none; background:transparent; font-weight:bold;"></div>
                <div><label>Target Group</label><input type="text" id="medTarget" readonly style="border:none; background:transparent; font-weight:bold;"></div>
            </div>

            <label>Medicine Suggested</label>
            <select name="medicine_id" id="medicineSelect" onchange="updateMedDetails()">
                <option value="">No Medicine Given</option>
            </select>

            <div class="grid-2">
                <div><label>Qty Given</label><input type="number" name="quantity_given" id="qtyInput" value="1" min="0" required></div>
                <div><label>Action Taken</label>
                    <select name="action_taken" id="actionTakenSelect" onchange="checkEmergency()">
                        <option value="Given Medicine">Given Medicine</option>
                        <option value="Advice Given">Advice Given</option>
                        <option value="Emergency Transfer">Emergency Transfer</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="save_consultation" style="width:100%; background:var(--primary); color:white; border:none; padding:16px; border-radius:14px; font-weight:800; cursor:pointer; margin-top:25px;">SAVE RECORD</button>
            <button type="button" onclick="closeModal('cModal')" style="width:100%; background:none; border:none; color:#a0aec0; margin-top:10px; cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

<script>
const searchPool = <?= json_encode($search_pool) ?>;
const symptomsLogic = <?= json_encode($symptoms_logic) ?>;
const allMeds = <?= json_encode($all_meds) ?>;

function openModal(id) { 
    document.getElementById('consultationForm').reset();
    document.getElementById('resSuggestions').style.display = 'none';
    document.getElementById(id).style.display = 'block'; 
}
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function filterResidents() {
    const val = document.getElementById('resSearch').value.toLowerCase();
    const sug = document.getElementById('resSuggestions');
    sug.innerHTML = ""; 
    if(!val) { sug.style.display="none"; return; }
    const matches = searchPool.filter(r => r.name.toLowerCase().includes(val)).slice(0, 8);
    matches.forEach(r => {
        const div = document.createElement('div');
        div.className = "suggest-item";
        div.innerHTML = `<strong>${r.name}</strong> <small>(${r.hh_no})</small>`;
        div.onclick = () => {
            document.getElementById('resSearch').value = r.name;
            document.getElementById('selectedName').value = r.name;
            document.getElementById('selectedHH').value = r.hh_no;
            document.getElementById('isPregnant').value = r.preg;
            calculateAge(r.bday);
            sug.style.display = "none";
            setTimeout(autoSelectMedicine, 50); 
        };
        sug.appendChild(div);
    });
    sug.style.display = "block";
}

function calculateAge(bday) {
    if(!bday || bday === '0000-00-00') { document.getElementById('age').value = '0'; return; }
    const birthDate = new Date(bday);
    const today = new Date();
    
    let diffMonths = (today.getFullYear() - birthDate.getFullYear()) * 12 + (today.getMonth() - birthDate.getMonth());
    if (today.getDate() < birthDate.getDate()) diffMonths--;

    let ageDisplay = "";

    if (diffMonths < 12) {
        let actualMonths = diffMonths < 0 ? 0 : diffMonths;
        ageDisplay = actualMonths + " mos";
    } else {
        ageDisplay = Math.floor(diffMonths / 12);
    }
    
    document.getElementById('age').value = ageDisplay;
}

function autoSelectMedicine() {
    const symp = document.getElementById('symptomSelect').value;
    const ageRaw = document.getElementById('age').value;
    const isMonths = ageRaw.includes("mos");
    const ageNum = parseInt(ageRaw) || 0;
    const isPreg = document.getElementById('isPregnant').value;
    const medSelect = document.getElementById('medicineSelect');
    const qtyInput = document.getElementById('qtyInput');
    
    medSelect.innerHTML = '<option value="">No Medicine Given</option>';
    
    let targetGroup = "Adult";
    if (symp === "Prenatal" || isPreg == 1) { targetGroup = "Prenatal"; }
    else if (isMonths) { targetGroup = "Infant"; }
    else if (ageNum >= 1 && ageNum <= 3) { targetGroup = "Toddler"; }
    else if (ageNum > 3 && ageNum <= 12) { targetGroup = "Children"; }
    else if (ageNum >= 13 && ageNum < 60) { targetGroup = "Adult"; } 
    else if (ageNum >= 60) { targetGroup = "Senior Citizen"; }

    const rules = symptomsLogic[symp];
    let firstId = null;

    allMeds.forEach(m => {
        let matchesSymptom = (rules && rules.keywords.some(k => m.generic_name.toLowerCase().includes(k.toLowerCase())));
        
        // Logical check for Pregnancy: 
        // If pregnant, they can take "Prenatal" meds OR "All ages" (like Paracetamol)
        let matchesTarget = (m.target_group === targetGroup || m.target_group === "All ages");
        
        // Special case: If symptom is Prenatal, allow all medicines tagged for Prenatal patients regardless of generic name keywords (since they are usually vitamins)
        if (symp === "Prenatal" && m.target_group === "Prenatal") { matchesSymptom = true; }

        if ((matchesSymptom && matchesTarget) || (symp === "Other" && matchesTarget)) {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.text = `${m.medicine_name} (${m.form})`;
            opt.setAttribute('data-class', m.form);
            opt.setAttribute('data-target', m.target_group);
            medSelect.add(opt);
            if (!firstId) firstId = m.id;
        }
    });

    if (firstId) {
        medSelect.value = firstId;
        qtyInput.value = 1;
        updateMedDetails();
    } else {
        medSelect.value = "";
        qtyInput.value = 0;
        document.getElementById('medClass').value = "None";
        document.getElementById('medTarget').value = "No stock available";
    }
    checkEmergency();
}

function updateMedDetails() {
    const sel = document.getElementById('medicineSelect');
    const qtyInput = document.getElementById('qtyInput');
    const opt = sel.options[sel.selectedIndex];
    if(opt && opt.value !== "") {
        document.getElementById('medClass').value = opt.getAttribute('data-class');
        document.getElementById('medTarget').value = opt.getAttribute('data-target');
        if(qtyInput.value == 0) qtyInput.value = 1;
    } else {
        document.getElementById('medClass').value = "None";
        document.getElementById('medTarget').value = "N/A";
        qtyInput.value = 0;
    }
}

function checkEmergency() {
    const action = document.getElementById('actionTakenSelect').value;
    if(action === "Emergency Transfer") {
        document.getElementById('medicineSelect').value = "";
        updateMedDetails();
    }
}

function toggleOtherSymptom() {
    const symp = document.getElementById('symptomSelect').value;
    document.getElementById('otherSymptomContainer').style.display = (symp === 'Other') ? 'block' : 'none';
}
</script>
</body>
</html>