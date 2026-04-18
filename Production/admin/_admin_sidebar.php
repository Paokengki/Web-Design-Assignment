<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    // 1. Logic to prevent "Undefined variable" errors
    if (!isset($pageTitle) || trim((string) $pageTitle) === '') {
        $pageTitle = 'Admin Panel - Cafe Dash';
    }
    if (!isset($extraStylesheets) || !is_array($extraStylesheets)) {
        $extraStylesheets = [];
    }
    // ADD THIS LINE: This fixes your Warning
    if (!isset($bodyClass)) {
        $bodyClass = '';
    }
    ?>
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
    <link rel="stylesheet" href="../Css/home style.css">

	<script src="../js/app.js"></script>

	
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    
    <?php foreach ($extraStylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
</head>

<body<?php echo trim((string)$bodyClass) !== '' ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
    <div class="sidebar">
		
        <h1 class="logo">Cafe Dash</h1>
        
        <div class="sidebar-menus">
            <a href="../admin_panel.php"><ion-icon name="mail-outline"></ion-icon>Feedback</a>
            <a href="../admin/_admin_restaurants.php"><ion-icon name="restaurant-outline"></ion-icon>Restaurants</a>
			<a href="../admin/_admin_members.php"><ion-icon name="people-outline"></ion-icon>Members</a>
            <a href="../admin/_admin_setting.php"><ion-icon name="settings-outline"></ion-icon>Setting</a>
        </div>
        
        <div class="sidebar-logout">
			<a href="../index.php"><ion-icon name="log-out-outline"></ion-icon>Logout</a>
		</div>	
    </div>