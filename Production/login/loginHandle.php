<?php
require_once __DIR__ . '/../base.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>CafeDash | Welcome</title>
	<link rel="stylesheet" href="../css/style.css">
	<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
	<video autoplay muted loop id="myVideo">
		<source src="../material/images/coffee login.mp4" type="video/mp4">
	</video>

	<div class="content">
		<div class="wrapper">
			<form action="" method="post">
				<h1>Welcome</h1>
				<div class="logo">
					<img src="../material/images/logo.png" alt="logo" style="width:200px;height:200px;">
				</div>

				<button type="submit" name="Member_login" class="btn">Member</button>
				<button type="submit" name="Admin_login" class="btn">Admin</button>
			</form>
		</div>
	</div>
</body>
</html>