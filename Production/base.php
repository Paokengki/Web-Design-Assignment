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

$result = null;
$currentScript = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
if ($currentScript === 'home.php') {
    $sql = "SELECT * FROM Restaurant";
    $result = $conn->query($sql);

    if ($result === false) {
        die("Failed to fetch restaurants: " . $conn->error);
    }
}

// Only run this logic IF the login button has been pressed
if (isset($_POST['Login_btn'])) {

    // Now it is safe to read these because the form was submitted
    $user_input = $_POST['User_name']; 
    $pass_input = $_POST['password'];

    $stmt = $conn->prepare("SELECT User_id, User_name, Password FROM User WHERE User_name = ?");
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        if ($pass_input === $row['Password']) {
            $_SESSION['user_id'] = $row['User_id'];
            $_SESSION['User_name'] = $row['User_name'];
            header("Location: home.php"); 
            exit();
        } else {
            $error_message = "Incorrect Password!";
        }
    } else {
        $error_message = "User not found!";
    }
    $stmt->close();
    $conn->close();
}
?>

<?php
$error_message = "";

if (isset($_POST['Logout_btn'])) {
    session_unset();
    session_destroy();
    header("Location: _login_base.php"); // Goes back to your specific login file
    exit();
}

?>