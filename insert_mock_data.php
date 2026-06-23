<?php
$conn = new mysqli("localhost", "root", "", "bhw_system");
if($conn->connect_error){ die("❌ DB Failed: ".$conn->connect_error); }

// 1. Gawa muna tayo ng "Fake Medicines" para may ma-JOIN ang transactions
$conn->query("SET FOREIGN_KEY_CHECKS = 0;"); // Pansamantalang i-off ang restrictions

// Siguraduhin nating may laman ang medicines table with specific IDs
$meds = [
    [1, 'Amoxicillin', 'Antibiotic', 'Capsule'],
    [2, 'Paracetamol', 'Analgesic', 'Tablet'],
    [3, 'Losartan', 'Antihypertensive', 'Tablet'],
    [4, 'Mefenamic Acid', 'NSAID', 'Capsule'],
    [5, 'Ascorbic Acid', 'Vitamin C', 'Tablet']
];

foreach ($meds as $m) {
    // I-insert o i-update para siguradong nandiyan ang ID 1-5
    $conn->query("INSERT INTO medicines (id, medicine_name, generic_name, category, quantity, unit, expiry_date) 
                  VALUES ($m[0], '$m[1]', '$m[2]', '$m[3]', 0, 'pcs', '2026-12-31')
                  ON DUPLICATE KEY UPDATE medicine_name='$m[1]'");
}

echo "✅ Medicine Bases Created...<br>";

// 2. Generate Mock Data (2021-2026)
$start = new DateTime('2021-01-01');
$end   = new DateTime('2026-01-31');
$interval = new DateInterval('P10D'); 
$period = new DatePeriod($start, $interval, $end);

$count_release = 0;
$count_disposal = 0;

foreach ($period as $dt) {
    $date_string = $dt->format('Y-m-d H:i:s');
    $m_id = rand(1, 5); // Randomly pick ID 1 to 5
    $qty = rand(20, 100);
    
    // Para sa Release History (Transactions Table)
    $conn->query("INSERT INTO transactions (medicine_id, quantity, type, date_created) 
                  VALUES ($m_id, $qty, 'restock', '$date_string')");
    $count_release++;

    // Para sa Disposal Logs (Pullout History Table)
    if(rand(1, 10) > 6) {
        $m_info = $meds[$m_id-1];
        $exp = $dt->format('Y-m-d');
        $conn->query("INSERT INTO pullout_history (medicine_name, generic_name, quantity, expiry_date, date_pulled) 
                      VALUES ('$m_info[1]', '$m_info[2]', ".rand(5, 15).", '$exp', '$date_string')");
        $count_disposal++;
    }
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1;"); // I-on ulit ang safety

echo "✅ <b>Release History:</b> $count_release entries inserted.<br>";
echo "✅ <b>Disposal Logs:</b> $count_disposal entries inserted.<br>";
echo "Punta ka na sa <b>Inventory Actions</b> tab bebe, andun na yan! ✨";
?>