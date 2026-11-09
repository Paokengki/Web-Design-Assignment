<?php
require __DIR__ . '/../app/bootstrap.php';

// Sign-out is POST + CSRF so a link on another site cannot log people out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) redirect('index.php');

if (($_POST['who'] ?? '') === 'staff') {
	unset($_SESSION['staff_id']);
	redirect('auth/staff_login.php');
}

unset($_SESSION['user_id'], $_SESSION['User_name'], $_SESSION['cart']);
session_regenerate_id(true);
redirect('index.php');
