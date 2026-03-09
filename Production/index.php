<?php
session_start(); // Starts the session to track the user

$error_message = ""; // To store errors if login fails

// 1. Process Login Logic
if (isset($_POST['Login_btn'])) {
    
    // Database Connection (XAMPP defaults)
    $conn = new mysqli("localhost", "root", "", "cafedash_db");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $user_input = $_POST['username'];
    $pass_input = $_POST['password'];

    // 2. Prepared Statement (The "Safe" Way to check if user is valid)
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    // 3. IF-ELSE Statement for Validity
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // Check if password matches (Using plain text check as per your current setup)
        if ($pass_input === $row['password']) {
            // IF VALID: Save session data and redirect
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            
            header("Location: home.php"); 
            exit();
        } else {
            // IF NOT VALID (Wrong Password)
            $error_message = "Incorrect Password!";
        }
    } else {
        // IF NOT VALID (User doesn't exist)
        $error_message = "User not found!";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CafeDash | Login</title>
    <link rel="stylesheet" href="css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Modern Error Styling */
        .error-box {
            background: rgba(255, 77, 77, 0.2);
            border: 1px solid #ff4d4d;
            color: #fff;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <video autoplay muted loop id="myVideo">
        <source src="material/images/coffee login.mp4" type="video/mp4">
    </video>

    <div class="content">
        <div class="wrapper">
            <form action="" method="post">
                <h1>Login</h1>
                <div class="logo">
                    <img src="material/images/logo.png" alt="logo" style="width:200px;height:200px;">
                </div>

                <?php if ($error_message != ""): ?>
                    <div class="error-box">
                        <i class='bx bx-error-circle'></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="textbox">
                    <input type="text" name="username" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>

                <div class="textbox">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>

                <div class="remember-pass">
                    <label><input type="checkbox" name="remember">Remember me</label>
                    <div class="link">
                        <a href="forgot.php">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" name="Login_btn" class="btn">Login</button>

                <div class="register">
                    <p>Don't have an account? <a href="register.php">Register</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>