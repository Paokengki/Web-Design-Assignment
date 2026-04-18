<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

$sessionState = session_status();
if ($sessionState === PHP_SESSION_NONE) {
    session_start();
}

$error_message = "";
$conn = new mysqli("localhost", "root", "", "cafedash_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 4. Global Data Fetching (e.g., for home.php)
$result = null;
$currentScript = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
if ($currentScript === 'home.php') {
    $sql = "SELECT * FROM Restaurant";
    $result = $conn->query($sql);

    if ($result === false) {
        die("Failed to fetch restaurants: " . $conn->error);
    }
}

// 5. Admin Feedback Logic (Must run before any HTML output)
/**
 * 
 * Deletes a specific feedback record from the contact_us table
 */
if (isset($_POST['Member_login'])) {
    header("Location: _member_login.php");
    exit();
}

if (isset($_POST['Admin_login'])) {
    header("Location: _admin_login.php");
    exit();
}

if (isset($_POST['delete_feedback'])) {
    $feedbackId = $_POST['feedback_id'];
    $stmt = $conn->prepare("DELETE FROM contact_us WHERE id = ?");
    $stmt->bind_param("i", $feedbackId);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Feedback deleted successfully.";
    } else {
        $_SESSION['msg'] = "Error deleting feedback.";
    }
    $stmt->close();
    
    // Refresh to update the list
    header("Location: admin_panel.php");
    exit();
}

/**
 * Helper function to fetch all feedback for the Admin Panel
 */
function getAllFeedback($conn) {
    $sql = "SELECT * FROM contact_us ORDER BY id DESC";
    return $conn->query($sql);
}
// --- base.php additions ---

/**
 * Admin Login Logic
 */

if (isset($_POST['Admin_login_btn'])) {
    $admin_user = $_POST['admin_name'];
    $admin_pass = $_POST['admin_password'];

    // Query the 'Admin' table specifically
    $stmt = $conn->prepare("SELECT Admin_ID, Name, Password FROM Admin WHERE Name = ?");
    $stmt->bind_param("s", $admin_user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // Direct string comparison as used in your Member login
        if ($admin_pass === $row['Password']) {
            $_SESSION['admin_id'] = $row['Admin_ID'];
            $_SESSION['admin_name'] = $row['Name'];
            header("Location: admin_panel.php"); // Redirect to the control panel
            exit();
        } else {
            $error_message = "Incorrect Admin Password!";
        }
    } else {
        $error_message = "Admin account not found!";
    }
    $stmt->close();
}

// 6. User Login Logic
if (isset($_POST['Login_btn'])) {
    $user_input = trim((string) ($_POST['User_name'] ?? ''));
    $pass_input = (string) ($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT User_id, User_name, Password, Suspend FROM User WHERE User_name = ? OR Email = ? LIMIT 1");
    $stmt->bind_param("ss", $user_input, $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if ($row['Suspend'] == 1) {
            $error_message = "Your account has been suspended. Please contact the administrator.";
        } else {
            // Support both hashed passwords (new accounts) and legacy plain text values.
            $is_valid_password = password_verify($pass_input, $row['Password']) || ($pass_input === $row['Password']);

            if ($is_valid_password) {
                $_SESSION['user_id'] = $row['User_id'];
                $_SESSION['User_name'] = $row['User_name'];
                header("Location: home.php");
                exit();
            } else {
                $error_message = "Incorrect Password!";
            }
        }
    } else {
        $error_message = "User not found!";
    }
    $stmt->close();
}

// 7. User Logout Logic
if (isset($_POST['Logout_btn'])) {
    session_unset();
    session_destroy();
    header("Location: _login_base.php");
    exit();
}



?>