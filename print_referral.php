<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bhw_system");

if (!isset($_GET['id'])) { die("No record found."); }

$id = $_GET['id'];
$query = "SELECT c.*, r.head_of_family, r.household_no, m.medicine_name 
          FROM consultations c 
          JOIN residents r ON c.resident_id = r.id 
          LEFT JOIN medicines m ON c.medicine_id = m.id 
          WHERE c.id = '$id'";
$res = $conn->query($query);
$data = $res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Referral Slip - <?= $data['head_of_family'] ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; padding: 40px; color: #333; }
        .referral-card { width: 100%; max-width: 800px; margin: auto; border: 2px solid #333; padding: 30px; position: relative; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .ref-no { position: absolute; top: 10px; right: 20px; font-size: 12px; }
        .section { margin-bottom: 15px; }
        .label { font-weight: bold; text-transform: uppercase; font-size: 12px; color: #555; }
        .value { font-size: 16px; border-bottom: 1px dotted #ccc; display: block; padding: 5px 0; }
        .footer { margin-top: 50px; display: flex; justify-content: space-between; }
        .sig-box { text-align: center; width: 200px; }
        .sig-line { border-top: 1px solid #000; margin-top: 40px; font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">

<div class="referral-card">
    <div class="ref-no">REF-2024-<?= str_pad($data['id'], 4, '0', STR_PAD_LEFT) ?></div>
    <div class="header">
        <h2>BARANGAY HEALTH CENTER</h2>
        <p>Referral Slip / Emergency Transfer Form</p>
    </div>

    <div class="section">
        <span class="label">Date & Time of Referral:</span>
        <span class="value"><?= date('F d, Y - h:i A', strtotime($data['date_visit'])) ?></span>
    </div>

    <div class="section">
        <span class="label">Patient Name:</span>
        <span class="value"><?= strtoupper($data['head_of_family']) ?> (HH# <?= $data['household_no'] ?>)</span>
    </div>

    <div class="section">
        <span class="label">Reason for Referral / Symptoms:</span>
        <span class="value"><?= $data['symptoms'] ?></span>
    </div>

    <div class="section">
        <span class="label">Vital Signs & Nurse's Notes:</span>
        <span class="value" style="min-height: 60px;"><?= nl2br($data['nurse_notes']) ?></span>
    </div>

    <div class="section">
        <span class="label">Initial Treatment Given (if any):</span>
        <span class="value"><?= $data['medicine_name'] ? $data['medicine_name'] . " (".$data['quantity_given']." pcs)" : "None" ?></span>
    </div>

    <div class="section">
        <span class="label">Referred To:</span>
        <span class="value">District Hospital / Nearest Medical Center</span>
    </div>

    <div class="footer">
        <div class="sig-box">
            <div class="sig-line">Nurse on Duty</div>
            <p style="font-size:10px;">Signature over Printed Name</p>
        </div>
        <div class="sig-box">
            <div class="sig-line">Date Signed</div>
        </div>
    </div>
</div>

<div style="text-align:center; margin-top:20px;" class="no-print">
    <button onclick="window.print()" style="padding:10px 20px; cursor:pointer;">Print Now</button>
    <button onclick="window.close()" style="padding:10px 20px; cursor:pointer;">Close</button>
</div>

</body>
</html>