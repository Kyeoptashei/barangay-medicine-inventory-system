<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "bhw_system");
if ($conn->connect_error) { die(json_encode(['error' => 'Connection failed'])); }

$y = isset($_GET['y']) ? intval($_GET['y']) : date('Y');
$m = isset($_GET['m']) ? intval($_GET['m']) : date('n');

/**
 * FIXED QUERY:
 * Sinisigurado nito na ang Top 5 medicines sa buwan na iyon 
 * ay ipapakita kasama ang sakit (symptom) na pinaka-dahilan kung bakit sila ibinigay.
 */
$query = "SELECT 
            m.medicine_name, 
            SUM(c.quantity_given) as total, 
            (SELECT symptoms 
             FROM consultations c2 
             WHERE c2.medicine_id = m.id 
             AND YEAR(c2.date_visit) = $y 
             AND MONTH(c2.date_visit) = $m 
             GROUP BY symptoms 
             ORDER BY COUNT(*) DESC 
             LIMIT 1) as top_symptom
          FROM consultations c 
          JOIN medicines m ON c.medicine_id = m.id 
          WHERE YEAR(c.date_visit) = $y 
          AND MONTH(c.date_visit) = $m 
          GROUP BY m.id, m.medicine_name 
          ORDER BY total DESC 
          LIMIT 5";

$res = $conn->query($query);
$data = [];

if ($res) {
    while($row = $res->fetch_assoc()) {
        // Kung walang symptom na mahanap, default to 'General Checkup'
        $row['top_symptom'] = $row['top_symptom'] ?? 'General Checkup';
        $data[] = $row;
    }
}

echo json_encode($data);
?>