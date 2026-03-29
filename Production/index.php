<?php
require '_login_head.php'; 
?>

<?php 
include 'login.php';
?>

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


