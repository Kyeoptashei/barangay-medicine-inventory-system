<?php
$conn = new mysqli("localhost", "root", "", "bhw_system");
if(isset($_GET['search'])){
    $q = $conn->real_escape_string($_GET['search']);
    $res = $conn->query("SELECT DISTINCT household_no, purok FROM residents WHERE household_no LIKE '%$q%' LIMIT 5");
    $data = [];
    while($row = $res->fetch_assoc()){ $data[] = $row; }
    echo json_encode($data);
}
?>