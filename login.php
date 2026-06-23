<?php
session_start();

// --- DATABASE CONNECTION ---
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "bhw_system";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

// --- LOGIN LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password'])) {
    $user_input = trim($_POST['username']);
    $pass_input = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($pass_input, $user['password']) || $pass_input === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'Nurse') {
                header("Location: nurse_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Can't Find Username!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Health System</title>
    <style>
        body { 
            margin: 0; 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            background: url('https://sorsogoncity.wordpress.com/wp-content/uploads/2024/11/2024-11-17-08-40-07-5623373407931976982605.jpg?w=1440') no-repeat center center fixed;
            background-size: cover;
            height: 100vh; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            overflow: hidden;
        }

        .blur-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 1;
        }

        .login-card { 
            background: rgba(255, 255, 255, 0.92); 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); 
            width: 100%; 
            max-width: 380px; 
            text-align: center; 
            position: relative; 
            z-index: 2;
            animation: fadeIn 0.8s ease-out;
        }

        /* --- Logo Styling --- */
        .login-logo {
            width: 100px; /* Adjust size as needed */
            height: auto;
            margin-bottom: 2px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 { margin-bottom: 10px; color: #1a2a6c; font-size: 24px; }
        p { color: #555; margin-bottom: 30px; font-size: 14px; }
        
        input { 
            width: 100%; 
            padding: 15px; 
            margin: 10px 0; 
            border: 1.5px solid #ddd; 
            border-radius: 10px; 
            box-sizing: border-box; 
            font-size: 16px;
            transition: 0.3s;
        }

        input:focus {
            border-color: #2575fc;
            outline: none;
            box-shadow: 0 0 8px rgba(37, 117, 252, 0.2);
        }

        button { 
            width: 100%; 
            padding: 15px; 
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: bold; 
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 15px;
        }

        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 117, 252, 0.4);
        }

        .error { 
            color: #721c24; 
            background: #f8d7da; 
            padding: 12px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-size: 14px; 
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

    <div class="blur-overlay"></div>

    <div class="login-card">
        <img src="logo.png" alt="Health Center Logo" class="login-logo">
        
        <h2>Barangay Bagacay Health Center</h2>
        <p>Please enter your credentials to continue</p>
        
        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required autocomplete="off">
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">LOGIN</button>
        </form>
    </div>

</body>
</html>