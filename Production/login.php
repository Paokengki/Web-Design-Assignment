<?php
session_start(); // Starts the session to track the user

// 1. Database Configuration
$servername = "localhost";
$username   = "root"; // Default XAMPP username
$password   = "";     // Default XAMPP password
$dbname     = "cafedash_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Process Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = $_POST['username'];
    $pass_input = $_POST['password'];

    // 3. Prepared Statement (The "Safe" Way)
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // 4. Password Check 
        // Use password_verify() if you hashed passwords, or direct check for testing
        if ($pass_input === $row['password']) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            
            header("Location: home.html"); // Success!
            exit();
        } else {
            echo "<script>alert('Incorrect Password'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('User not found'); window.history.back();</script>";
    }
    $stmt->close();
}
$conn->close();
?>