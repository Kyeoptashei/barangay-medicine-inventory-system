<?php
$conn = new mysqli("localhost", "root", "", "bhw_system");
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); }

// 1. FRESH START
$conn->query("TRUNCATE TABLE consultations");
$conn->query("TRUNCATE TABLE consultations_archive");

// 2. KUNIN ANG RESIDENTS
$residents = $conn->query("SELECT id, household_no, head_of_family, age, classification FROM residents")->fetch_all(MYSQLI_ASSOC);

/**
 * 🎯 SMART MEDICINE SELECTOR (BEBE VERSION)
 * Tinitingnan ang Sakit + Age + Target Group sa Inventory
 */
function getAccurateMedicine($conn, $illness, $age, $class) {
    $med_name = "";
    
    // Logic base sa listahan mo bebe
    switch ($illness) {
        case "Fever":
            if ($age <= 1) $med_name = "Calpol Drops";
            elseif ($age <= 3) $med_name = "Tempra Syrup";
            else $med_name = "Biogesic 500mg";
            break;

        case "Cough":
            if ($age <= 3) $med_name = "Solmux Syrup";
            elseif ($age <= 12) $med_name = "Ambroxol Kids"; // O "Ascof Forte Kids"
            else $med_name = "Ambroxol 30mg";
            break;

        case "Cold":
            if ($age <= 1) $med_name = "Disudrin Drops";
            elseif ($age > 12) $med_name = "Neozep Forte"; // O "Bioflu"
            else $med_name = "Bioflu"; 
            break;

        case "Hypertension":
            // For Senior Citizens
            $med_name = (rand(0,1) == 0) ? "Losartan 50mg" : "Amlodipine 5mg";
            break;

        case "Diabetes":
            $med_name = "Metformin 500mg";
            break;

        case "Allergy":
            $med_name = "Reiter (Cetirizine)";
            break;

        case "Diarrhea":
            if ($age <= 1) $med_name = "ORS Glucosol";
            elseif ($age <= 12) $med_name = "Hydrite";
            else $med_name = "Diatabs";
            break;

        case "Prenatal Checkup":
            $med_name = (rand(0,1) == 0) ? "Obimin Plus" : "Sangobion";
            break;

        case "Vitamins Only":
            if ($age <= 1) $med_name = "Ceelin Drops";
            elseif ($age <= 3) $med_name = "Nutrilin Syrup";
            else $med_name = "Enervon-C";
            break;

        case "Skin Allergy":
            $med_name = "Canesten";
            break;

        default:
            $med_name = "Enervon-C";
    }

    // Query sa inventory base sa pangalan para makuha ang ID
    $res = $conn->query("SELECT id FROM medicines WHERE medicine_name LIKE '%$med_name%' LIMIT 1");
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc()['id'] : 0;
}

$total_c = 0; $total_a = 0;

// 3. GENERATION LOOP (Jan 2021 to March 2026)
for ($y = 2021; $y <= 2026; $y++) {
    for ($m = 1; $m <= 12; $m++) {
        // Cutoff: Today is March 20, 2026
        if ($y == 2026 && $m > 3) break;

        $records_per_month = rand(12, 18);
        for ($i = 0; $i < $records_per_month; $i++) {
            $r = $residents[array_rand($residents)];
            $age = intval($r['age']);
            $class = $r['classification'];

            // Assign Illness base sa Age/Classification
            if ($class == "Prenatal") {
                $illness = "Prenatal Checkup";
            } elseif ($age >= 60) {
                $illness = ["Hypertension", "Diabetes", "Cough", "Fever"][rand(0,3)];
            } elseif ($age <= 3) {
                $illness = ["Fever", "Cough", "Cold", "Diarrhea", "Vitamins Only"][rand(0,4)];
            } else {
                $illness = ["Fever", "Cough", "Cold", "Allergy", "Flu", "Skin Allergy"][rand(0,5)];
            }

            $med_id = getAccurateMedicine($conn, $illness, $age, $class);
            $date = "$y-" . str_pad($m, 2, "0", STR_PAD_LEFT) . "-" . str_pad(rand(1, 28), 2, "0", STR_PAD_LEFT);
            
            // ARCHIVE LOGIC: Older than 5 years (Jan-March 2021)
            $is_archived = ($y == 2021 && $m <= 3);
            $table = $is_archived ? "consultations_archive" : "consultations";

            $stmt = $conn->prepare("INSERT INTO $table (resident_id, household_no, patient_name, symptoms, medicine_id, quantity_given, date_visit, age, height, weight, bp, action_taken) VALUES (?, ?, ?, ?, ?, 10, ?, ?, ?, ?, ?, 'Given Medicine')");
            
            $h = ($age <= 3) ? rand(50, 95) : rand(145, 175);
            $w = ($age <= 3) ? rand(5, 15) : rand(45, 85);
            $bp = ($illness == "Hypertension" || $age >= 60) ? rand(130, 150)."/".rand(85, 95) : "120/80";
            
            $stmt->bind_param("isssisiiss", 
                $r['id'], $r['household_no'], $r['head_of_family'], 
                $illness, $med_id, $date, $age, $h, $w, $bp
            );
            
            if($stmt->execute()){
                if($is_archived) $total_a++; else $total_c++;
            }
        }
    }
}

echo "<div style='font-family:sans-serif; padding:20px; border:3px solid #DC143C; border-radius:15px; background:#f4f7fe; max-width:600px; margin:20px auto;'>";
echo "<h2 style='color:#DC143C; text-align:center;'>✨ Data Generation Perfected! ✨</h2>";
echo "📏 <b>Residents Processed:</b> " . count($residents) . "<br>";
echo "📦 <b>Archived (2021):</b> $total_a records<br>";
echo "🏥 <b>Current (2021-2026):</b> $total_c records<br><br>";
echo "<p style='background:#fff; padding:10px; border-radius:8px; font-size:13px;'><b>System Note:</b> Na-apply na ang age-group logic. Ang mga <i>Infants</i> ay nakatanggap ng <i>Drops</i>, ang <i>Seniors</i> ay may <i>Hypertension meds</i>, at ang <i>Prenatal</i> ay may <i>Obimin/Sangobion</i>.</p>";
echo "</div>";
?>