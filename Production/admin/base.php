<?php
$sessionState = session_status();
if ($sessionState === PHP_SESSION_NONE) {
    session_start();
}

// 1. INITIALIZE CONNECTION FIRST
$conn = new mysqli("localhost", "root", "", "cafedash_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = "";

function sanitizeNameForPath($name) {
    $safe = preg_replace('/[\\\\\/\\:\*\?\"<>\|]+/', '', $name);
    $safe = trim($safe);
    return $safe !== '' ? $safe : 'restaurant_' . uniqid();
}

function getRestaurantFolderPath($restaurantName) {
    $safeName = sanitizeNameForPath($restaurantName);
    return __DIR__ . '/../material/' . $safeName;
}

function ensureRestaurantFolder($restaurantName) {
    $folder = getRestaurantFolderPath($restaurantName);
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }
    return $folder;
}

function renameRestaurantFolder($oldName, $newName) {
    $oldPath = getRestaurantFolderPath($oldName);
    $newPath = getRestaurantFolderPath($newName);
    if ($oldPath !== $newPath && is_dir($oldPath)) {
        if (!is_dir($newPath)) {
            rename($oldPath, $newPath);
        } else {
            ensureRestaurantFolder($newName);
        }
    }
    return $newPath;
}

function saveImageAsJpeg($sourceFile, $destinationFile) {
    $info = getimagesize($sourceFile);
    if ($info === false) {
        return false;
    }

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $srcImage = imagecreatefromjpeg($sourceFile);
            break;
        case 'image/png':
            $srcImage = imagecreatefrompng($sourceFile);
            break;
        case 'image/gif':
            $srcImage = imagecreatefromgif($sourceFile);
            break;
        case 'image/webp':
            $srcImage = imagecreatefromwebp($sourceFile);
            break;
        default:
            return false;
    }

    if (!$srcImage) {
        return false;
    }

    $result = imagejpeg($srcImage, $destinationFile, 90);
    imagedestroy($srcImage);
    return $result;
}

// Handle restaurant update
if (isset($_POST['update_restaurant'])) {
    $resId = $_POST['restaurant_id'];
    $name = $_POST['res_name'];
    $type = $_POST['res_type'];
    $email = $_POST['res_email'] ?? '';
    $phone = $_POST['res_phone'] ?? '';
    $address = $_POST['res_address'] ?? '';

    $stmtGet = $conn->prepare("SELECT Name FROM Restaurant WHERE Restaurant_ID = ?");
    $stmtGet->bind_param("i", $resId);
    $stmtGet->execute();
    $currentRes = $stmtGet->get_result()->fetch_assoc();
    $stmtGet->close();
    $oldName = $currentRes['Name'] ?? $name;
    $restaurantFolder = renameRestaurantFolder($oldName, $name);
    ensureRestaurantFolder($name);

    $uploadSuccess = true;
    if (!empty($_FILES['res_image']['name']) && $_FILES['res_image']['error'] === UPLOAD_ERR_OK) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($_FILES['res_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            $uploadSuccess = false;
            $_SESSION['msg'] = "Invalid restaurant image type. Please upload JPG, PNG, GIF, or WEBP.";
        } else {
            $targetFile = rtrim($restaurantFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'shop.jpg';
            if ($extension === 'jpg' || $extension === 'jpeg') {
                if (!move_uploaded_file($_FILES['res_image']['tmp_name'], $targetFile)) {
                    $uploadSuccess = false;
                    $_SESSION['msg'] = "Restaurant updated, but image upload failed.";
                }
            } else {
                $tmpPath = $_FILES['res_image']['tmp_name'];
                if (!saveImageAsJpeg($tmpPath, $targetFile)) {
                    $uploadSuccess = false;
                    $_SESSION['msg'] = "Restaurant updated, but unable to convert image to JPG.";
                }
            }
        }
    }

    $stmt = $conn->prepare("UPDATE Restaurant SET Name = ?, Restaurant_type = ?, Email = ?, Contain_number = ?, Address = ? WHERE Restaurant_ID = ?");
    $stmt->bind_param("sssssi", $name, $type, $email, $phone, $address, $resId);
    if ($stmt->execute()) {
        if (empty($_SESSION['msg'])) {
            $_SESSION['msg'] = $uploadSuccess ? "Restaurant updated successfully!" : "Restaurant updated, but image upload failed.";
        }
    } else {
        $_SESSION['msg'] = "Error updating restaurant.";
    }
    $stmt->close();
    header("Location: _admin_restaurants.php?edit_id=$resId");
    exit();
}

$sessionState = session_status();
if ($sessionState === PHP_SESSION_NONE) {
    session_start();
}

$error_message = "";
$conn = new mysqli("localhost", "root", "", "cafedash_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------------------------------------------------------
// 1. NAVIGATION & FEEDBACK LOGIC
// ---------------------------------------------------------

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
    header("Location: admin_panel.php");
    exit();
}

if (!function_exists('getAllFeedback')) {
    function getAllFeedback($conn) {
        $sql = "SELECT * FROM contact_us ORDER BY id DESC";
        return $conn->query($sql);
    }
}

// ---------------------------------------------------------
// 2. ADMIN LOGIN & LOGOUT
// ---------------------------------------------------------

if (isset($_POST['Admin_login_btn'])) {
    $admin_user = $_POST['admin_name'];
    $admin_pass = $_POST['admin_password'];

    $stmt = $conn->prepare("SELECT Admin_ID, Name, Password FROM Admin WHERE Name = ?");
    $stmt->bind_param("s", $admin_user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if ($admin_pass === $row['Password']) {
            $_SESSION['admin_id'] = $row['Admin_ID']; 
            $_SESSION['admin_name'] = $row['Name'];
            header("Location: admin_panel.php");
            exit();
        } else {
            $error_message = "Incorrect Admin Password!";
        }
    } else {
        $error_message = "Admin account not found!";
    }
    $stmt->close();
}

if (isset($_POST['Logout_btn'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// ---------------------------------------------------------
// 3. RESTAURANT MANAGEMENT
// ---------------------------------------------------------

if (!function_exists('getAllRestaurants')) {
    function getAllRestaurants($conn) {
        $sql = "SELECT * FROM Restaurant ORDER BY Restaurant_ID DESC";
        return $conn->query($sql);
    }
}

if (isset($_POST['add_restaurant'])) {
    $name = trim($_POST['res_name'] ?? '');
    $address = trim($_POST['res_address'] ?? '');
    $type = trim($_POST['res_type'] ?? '');
    $email = trim($_POST['res_email'] ?? '');
    $phone = trim($_POST['res_phone'] ?? '');

    $errors = [];
    if ($name === '') {
        $errors[] = 'Restaurant name is required.';
    }
    if ($type === '') {
        $errors[] = 'Cuisine type is required.';
    }
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    }
    if ($address === '') {
        $errors[] = 'Restaurant address is required.';
    }

    if (!empty($errors)) {
        $_SESSION['msg'] = implode(' ', $errors);
        header("Location: ../admin/_admin_restaurants.php");
        exit();
    }

    ensureRestaurantFolder($name);

    $stmt = $conn->prepare("INSERT INTO Restaurant (Name, Address, Restaurant_type, Email, Contain_number) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $address, $type, $email, $phone);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Restaurant added successfully!";
    } else {
        $_SESSION['msg'] = "Error adding restaurant.";
    }
    $stmt->close();
    header("Location: ../admin/_admin_restaurants.php");
    exit();
}

if (isset($_POST['delete_restaurant'])) {
    $resId = $_POST['restaurant_id'];
    
    // 1. First, delete all payments associated with this restaurant
    $stmt_del_payments = $conn->prepare("DELETE FROM Payment WHERE Restaurant_ID = ?");
    $stmt_del_payments->bind_param("i", $resId);
    $stmt_del_payments->execute();
    $stmt_del_payments->close();

    // 2. Delete all food associated with this restaurant
    $stmt1 = $conn->prepare("DELETE FROM Food WHERE Restaurant_ID = ?");
    $stmt1->bind_param("i", $resId);
    $stmt1->execute();
    $stmt1->close();

    // 3. Now, delete the restaurant
    $stmt2 = $conn->prepare("DELETE FROM Restaurant WHERE Restaurant_ID = ?");
    $stmt2->bind_param("i", $resId);
    
    if ($stmt2->execute()) {
        $_SESSION['msg'] = "Restaurant and all its menu items removed successfully.";
    }
    $stmt2->close();
    
    header("Location: _admin_restaurants.php");
    exit();
}

// ---------------------------------------------------------
// 4. MEMBER MANAGEMENT & SEARCH
// ---------------------------------------------------------

// FIXED: Removed the duplicate getAllMembers function and kept the one with $searchTerm
/**
 * Modified Fetch all members with optional Search
 */
if (!function_exists('getAllMembers')) {
    function getAllMembers($conn, $search = "") {
        if (!empty($search)) {
            // Use LIKE to find matches in name or email
            $stmt = $conn->prepare("SELECT User_ID, User_Name, Email, Contain_number, Address, Suspend 
                                    FROM User 
                                    WHERE User_Name LIKE ? OR Email LIKE ? 
                                    ORDER BY User_ID DESC");
            $searchTerm = "%" . $search . "%";
            $stmt->bind_param("ss", $searchTerm, $searchTerm);
            $stmt->execute();
            return $stmt->get_result();
        } else {
            $sql = "SELECT User_ID, User_Name, Email, Contain_number, Address, Suspend FROM User ORDER BY User_ID DESC";
            return $conn->query($sql);
        }
    }
}

if (isset($_POST['delete_user'])) {
    $userId = $_POST['user_id'];

    // Remove dependent payment records before deleting the user.
    $stmtPayments = $conn->prepare("DELETE FROM Payment WHERE User_ID = ?");
    $stmtPayments->bind_param("i", $userId);
    $stmtPayments->execute();
    $stmtPayments->close();

    $stmt = $conn->prepare("DELETE FROM User WHERE User_ID = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "User #$userId has been removed.";
    } else {
        $_SESSION['msg'] = "Error removing user. Please check related records.";
    }
    $stmt->close();
    header("Location: ../admin/_admin_members.php");
    exit();
}

/**
 * Handle Suspend/Unsuspend Toggle
 */
if (isset($_POST['toggle_suspend'])) {
    $userId = $_POST['user_id'];
    $currentStatus = $_POST['current_status'];
    
    // Toggle the status: if 1 (suspended) make it 0 (active), and vice versa
    $newStatus = ($currentStatus == 1) ? 0 : 1;
    
    $stmt = $conn->prepare("UPDATE User SET Suspend = ? WHERE User_ID = ?");
    $stmt->bind_param("ii", $newStatus, $userId);
    $stmt->execute();
    $stmt->close();
    
    header("Location: ../admin/_admin_members.php");
    exit();
}

// ---------------------------------------------------------
// 5. USER LOGIN (WITH SUSPENSION CHECK)
// ---------------------------------------------------------

if (isset($_POST['Login_btn'])) {
    $user_input = $_POST['User_name'];
    $pass_input = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM User WHERE User_name = ?");
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // SUSPENSION CHECK
        if ($row['Suspend'] == 1) {
            $error_message = "Your account has been suspended. Please contact admin.";
        } 
        else if ($pass_input === $row['Password']) {
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
}

// ---------------------------------------------------------
// 6. ADMIN PASSWORD UPDATE
// ---------------------------------------------------------

if (isset($_POST['update_admin_password'])) {
    ob_start(); 

    $currentPass = $_POST['current_password'];
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];
    
    if (!isset($_SESSION['admin_id'])) {
        $_SESSION['msg'] = "Session error. Please login again.";
        header("Location: index.php"); 
        exit();
    }

    $adminId = $_SESSION['admin_id'];

    if ($newPass !== $confirmPass) {
        $_SESSION['msg'] = "New passwords do not match!";
    } else {
        $stmt = $conn->prepare("SELECT Password FROM Admin WHERE Admin_ID = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($currentPass === $row['Password']) {
                $updateStmt = $conn->prepare("UPDATE Admin SET Password = ? WHERE Admin_ID = ?");
                $updateStmt->bind_param("si", $newPass, $adminId);
                
                if ($updateStmt->execute()) {
                    $_SESSION['msg'] = "Password updated successfully!";
                } else {
                    $_SESSION['msg'] = "Database error.";
                }
                $updateStmt->close();
            } else {
                $_SESSION['msg'] = "Current password is incorrect.";
            }
        }
        $stmt->close();
    }
    
    header("Location: ../admin/_admin_setting.php");
    ob_end_flush();
    exit(); 
}

// Handle add food
if (isset($_POST['add_food'])) {
    $foodName = $_POST['food_name'];
    $foodType = $_POST['food_type'] ?? '';
    $foodDetail = $_POST['food_desc'];
    $restaurantId = $_POST['restaurant_id'];
    $amount = $_POST['food_amount'] ?? null;

    $stmtGet = $conn->prepare("SELECT Name FROM Restaurant WHERE Restaurant_ID = ?");
    $stmtGet->bind_param("i", $restaurantId);
    $stmtGet->execute();
    $resultGet = $stmtGet->get_result();
    $restaurantData = $resultGet->fetch_assoc();
    $stmtGet->close();

    $restaurantName = $restaurantData['Name'] ?? '';
    $restaurantFolder = ensureRestaurantFolder($restaurantName);

    if (!empty($_FILES['food_image']['name']) && $_FILES['food_image']['error'] === UPLOAD_ERR_OK) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($_FILES['food_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            $_SESSION['msg'] = "Invalid food image type. Please upload JPG, PNG, GIF, or WEBP.";
            header("Location: _admin_restaurants.php?edit_id=$restaurantId");
            exit();
        }

        $safeFoodName = sanitizeNameForPath($foodName);
        $targetFile = rtrim($restaurantFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeFoodName . '.jpg';

        if ($extension === 'jpg' || $extension === 'jpeg') {
            if (!move_uploaded_file($_FILES['food_image']['tmp_name'], $targetFile)) {
                $_SESSION['msg'] = "Food item added, but image upload failed.";
                header("Location: _admin_restaurants.php?edit_id=$restaurantId");
                exit();
            }
        } else {
            $tmpPath = $_FILES['food_image']['tmp_name'];
            if (!saveImageAsJpeg($tmpPath, $targetFile)) {
                $_SESSION['msg'] = "Food item added, but unable to convert image to JPG.";
                header("Location: _admin_restaurants.php?edit_id=$restaurantId");
                exit();
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO Food (Name, Food_type, detail, amount, Restaurant_ID) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdi", $foodName, $foodType, $foodDetail, $amount, $restaurantId);
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Food item added successfully!";
    } else {
        $_SESSION['msg'] = "Error adding food item.";
    }
    $stmt->close();
    header("Location: _admin_restaurants.php?edit_id=$restaurantId");
    exit();
}
// Handle delete food
if (isset($_POST['delete_food'])) {
    $foodId = $_POST['food_id'];
    $restaurantId = $_POST['restaurant_id'];
    $stmt = $conn->prepare("DELETE FROM Food WHERE Food_ID = ?");
    $stmt->bind_param("i", $foodId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['msg'] = "Food item deleted.";
    header("Location: _admin_restaurants.php?edit_id=$restaurantId");
    exit();
}
// Handle edit food
if (isset($_POST['edit_food'])) {
    $foodId = $_POST['food_id'];
    $restaurantId = $_POST['restaurant_id'];
    $foodName = $_POST['food_name'];
    $foodType = $_POST['food_type'] ?? '';
    $foodDetail = $_POST['food_desc'];
    $amount = $_POST['food_amount'] ?? null;
    $stmt = $conn->prepare("UPDATE Food SET Name=?, Food_type=?, detail=?, amount=? WHERE Food_ID=?");
    $stmt->bind_param("sssdi", $foodName, $foodType, $foodDetail, $amount, $foodId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['msg'] = "Food item updated.";
    header("Location: _admin_restaurants.php?edit_id=$restaurantId");
    exit();
}