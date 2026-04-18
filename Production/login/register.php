<?php
declare(strict_types=1);

$errors = [];
$success_message = "";

$form_values = [
	'user_name' => '',
	'email' => '',
	'phone' => '',
	'address' => ''
];

// Validate the submitted registration form before touching the database.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$form_values['user_name'] = trim($_POST['user_name'] ?? '');
	$form_values['email'] = trim($_POST['email'] ?? '');
	$form_values['phone'] = trim($_POST['phone'] ?? '');
	$form_values['address'] = trim($_POST['address'] ?? '');
	$password = (string) ($_POST['password'] ?? '');
	$confirm_password = (string) ($_POST['confirm_password'] ?? '');

	if ($form_values['user_name'] === '') {
		$errors[] = 'Username is required.';
	} elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $form_values['user_name'])) {
		$errors[] = 'Username must be 3-30 characters and contain only letters, numbers, or underscore.';
	}

	if ($form_values['email'] === '') {
		$errors[] = 'Email is required.';
	} elseif (!filter_var($form_values['email'], FILTER_VALIDATE_EMAIL)) {
		$errors[] = 'Please enter a valid email address.';
	}

	if ($form_values['phone'] === '') {
		$errors[] = 'Phone number is required.';
	} elseif (!preg_match('/^[0-9+\-\s]{8,20}$/', $form_values['phone'])) {
		$errors[] = 'Phone number format is invalid.';
	}

	if ($form_values['address'] === '') {
		$errors[] = 'Address is required.';
	}

	if ($password === '') {
		$errors[] = 'Password is required.';
	} elseif (strlen($password) < 8) {
		$errors[] = 'Password must be at least 8 characters.';
	}

	if ($confirm_password === '') {
		$errors[] = 'Please confirm your password.';
	} elseif ($password !== $confirm_password) {
		$errors[] = 'Password and Confirm Password do not match.';
	}

	if (empty($errors)) {
		// Check for duplicates, then insert the new account with a hashed password.
		try {
			$pdo = new PDO(
				'mysql:host=localhost;dbname=cafedash_db;charset=utf8mb4',
				'root',
				'',
				[
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
				]
			);

			$check_stmt = $pdo->prepare('SELECT User_ID FROM User WHERE User_Name = :username OR Email = :email LIMIT 1');
			$check_stmt->execute([
				':username' => $form_values['user_name'],
				':email' => $form_values['email']
			]);

			if ($check_stmt->fetch()) {
				$errors[] = 'Username or email already exists. Please use a different one.';
			} else {
				$hashed_password = password_hash($password, PASSWORD_DEFAULT);
				$insert_stmt = $pdo->prepare(
					'INSERT INTO User (User_Name, Password, Address, Contain_number, Email, Suspend)
					 VALUES (:username, :password, :address, :phone, :email, 0)'
				);

				$insert_stmt->execute([
					':username' => $form_values['user_name'],
					':password' => $hashed_password,
					':address' => $form_values['address'],
					':phone' => $form_values['phone'],
					':email' => $form_values['email']
				]);

				// Clear the form values after a successful registration.
				$success_message = 'Registration successful. You can now login with your account.';
				$form_values = [
					'user_name' => '',
					'email' => '',
					'phone' => '',
					'address' => ''
				];
			}
		} catch (PDOException $e) {
			$errors[] = 'System error: unable to complete registration right now.';
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>CafeDash | Register</title>
	<link rel="stylesheet" href="../Css/register.css">
	<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
</head>
<body>
	<video autoplay muted loop id="myVideo">
		<source src="../material/images/coffee login.mp4" type="video/mp4">
	</video>

	<div class="content">
		<div class="wrapper register-wrapper">
			<form action="" method="post" id="registerForm" novalidate>
				<h1>Sign Up</h1>
				<div class="logo">
					<img src="../material/images/logo.png" alt="logo" style="width:180px;height:180px;">
				</div>

				<?php if (!empty($errors)): ?>
					<div class="error-box">
						<i class='bx bx-error-circle'></i>
						<ul>
							<?php foreach ($errors as $error): ?>
								<li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ($success_message !== ''): ?>
					<div class="success-box">
						<i class='bx bx-check-circle'></i>
						<?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
					</div>
				<?php endif; ?>

				<div class="textbox">
					<input type="text" name="user_name" placeholder="Username" value="<?php echo htmlspecialchars($form_values['user_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
					<i class='bx bxs-user'></i>
				</div>

				<div class="textbox">
					<input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($form_values['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
					<i class='bx bxs-envelope'></i>
				</div>

				<div class="textbox">
					<input type="text" name="phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($form_values['phone'], ENT_QUOTES, 'UTF-8'); ?>" required>
					<i class='bx bxs-phone'></i>
				</div>

				<div class="textbox">
					<input type="text" name="address" placeholder="Address" value="<?php echo htmlspecialchars($form_values['address'], ENT_QUOTES, 'UTF-8'); ?>" required>
					<i class='bx bxs-map'></i>
				</div>

				<div class="textbox">
					<input type="password" name="password" id="passwordField" placeholder="Password" required>
					<i class='bx bxs-lock-alt'></i>
					<button type="button" class="toggle-pass" data-target="#passwordField" aria-label="Toggle password visibility">
						<i class='bx bx-show'></i>
					</button>
				</div>

				<div class="textbox">
					<input type="password" name="confirm_password" id="confirmPasswordField" placeholder="Confirm Password" required>
					<i class='bx bxs-lock-alt'></i>
					<button type="button" class="toggle-pass" data-target="#confirmPasswordField" aria-label="Toggle confirm password visibility">
						<i class='bx bx-show'></i>
					</button>
				</div>

				<button type="submit" class="btn">Create Account</button>

				<div class="register">
					<p>Already have an account? <a href="_member_login.php">Login</a></p>
				</div>
			</form>
		</div>
	</div>

	<script>
		$(document).ready(function () {
			$('.toggle-pass').on('click', function () {
				const target = $($(this).data('target'));
				const icon = $(this).find('i');
				const isPassword = target.attr('type') === 'password';

				target.attr('type', isPassword ? 'text' : 'password');
				icon.toggleClass('bx-show bx-hide');
			});

			// Keep the confirmation error on the client side before the form submits.
			$('#registerForm').on('submit', function (e) {
				const password = $('#passwordField').val();
				const confirmPassword = $('#confirmPasswordField').val();

				if (password !== confirmPassword) {
					e.preventDefault();
					if ($('.client-error').length === 0) {
						$('<div class="error-box client-error"><i class="bx bx-error-circle"></i> Password and Confirm Password do not match.</div>')
							.insertAfter('.logo');
					}
				}
			});
		});
	</script>
</body>
</html>
