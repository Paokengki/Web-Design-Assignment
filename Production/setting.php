<!DOCTYPE html>
<html>
<head>
    <title>Setting - Cafe Dash</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Css/setting style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
</head>
<body>

<!-- Home Sidebar - Left -->
<div class="sidebar">
	<!-- Logo -->
	<h1 class="logo">Cafe Dash</h1>
	
	<!-- List of menus -->
	<div class="sidebar-menus">
		<a href="home.php"><ion-icon name="storefront-outline"></ion-icon>Home</a>
		<a href="#"><ion-icon name="receipt-outline"></ion-icon>Bills</a>
		<a href="#"><ion-icon name="wallet-outline"></ion-icon>Wallet</a>
		<a href="#"><ion-icon name="notifications-outline"></ion-icon>Notification</a>
		<a href="#"><ion-icon name="chatbubbles-outline"></ion-icon>Contact Us</a>
		<a href="setting.php"><ion-icon name="settings-outline"></ion-icon>Setting</a>
	</div>
	<!-- logout -->
	<div class="sidebar-logout">
		<a href="login.php"><ion-icon name="log-out-outline"></ion-icon>Logout</a>
	</div>
</div>

<!-- Settings Sidebar - Right of Home Sidebar -->
<div class="settingsidebar">
	<h2>
		<ion-icon name="settings-outline"></ion-icon> Setting
	</h2>

	<div class="sidebar-menus">
		<a href="#" data-tab="profile" class="setting-tab-link">
			<ion-icon name="person-outline"></ion-icon> Profile
		</a>
		<a href="#" data-tab="notification" class="setting-tab-link">
			<ion-icon name="notifications-outline"></ion-icon> Notification
		</a>
		<a href="#" data-tab="account" class="setting-tab-link">
			<ion-icon name="lock-closed-outline"></ion-icon> Account
		</a>
		<a href="#" data-tab="privacy" class="setting-tab-link">
			<ion-icon name="shield-outline"></ion-icon> Privacy
		</a>
	</div>
</div>

<!-- Main Content -->
<div class="main">
	<!-- Profile Content -->
	<div id="profile" class="setting-content active">
		<h2>Profile Settings</h2>
		<div class="setting-form">
			<div class="form-group">
				<label>Full Name</label>
				<input type="text" placeholder="Enter your full name">
			</div>
			<div class="form-group">
				<label>Email</label>
				<input type="email" placeholder="Enter your email">
			</div>
			<div class="form-group">
				<label>Phone Number</label>
				<input type="tel" placeholder="Enter your phone number">
			</div>
			<button class="save-btn">Save Changes</button>
		</div>
	</div>

	<!-- Notification Content -->
	<div id="notification" class="setting-content">
		<h2>Notification Settings</h2>
		<div class="setting-form">
			<div class="notification-item">
				<div class="notification-text">
					<h4>Order Updates</h4>
					<p>Get notified when your order status changes</p>
				</div>
				<input type="checkbox" checked>
			</div>
			<div class="notification-item">
				<div class="notification-text">
					<h4>Promotions</h4>
					<p>Receive promotional offers and discounts</p>
				</div>
				<input type="checkbox" checked>
			</div>
			<div class="notification-item">
				<div class="notification-text">
					<h4>Email Notifications</h4>
					<p>Receive notifications via email</p>
				</div>
				<input type="checkbox" checked>
			</div>
		</div>
	</div>

	<!-- Account Content -->
	<div id="account" class="setting-content">
		<h2>Account Settings</h2>
		<div class="setting-form">
			<div class="form-group">
				<label>Current Password</label>
				<input type="password" placeholder="Enter current password">
			</div>
			<div class="form-group">
				<label>New Password</label>
				<input type="password" placeholder="Enter new password">
			</div>
			<div class="form-group">
				<label>Confirm Password</label>
				<input type="password" placeholder="Confirm new password">
			</div>
			<button class="save-btn">Update Password</button>
		</div>
	</div>

	<!-- Privacy Content -->
	<div id="privacy" class="setting-content">
		<h2>Privacy Settings</h2>
		<div class="setting-form">
			<div class="privacy-item">
				<div class="privacy-text">
					<h4>Profile Visibility</h4>
					<p>Control who can see your profile</p>
				</div>
				<select>
					<option>Public</option>
					<option>Friends Only</option>
					<option>Private</option>
				</select>
			</div>
			<div class="privacy-item">
				<div class="privacy-text">
					<h4>Data Collection</h4>
					<p>Allow us to collect usage data for improvement</p>
				</div>
				<input type="checkbox" checked>
			</div>
		</div>
	</div>
</div>

<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<!-- adding javascript -->
<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script src="js/app.js"></script>

<script>
	// Settings Tab Navigation
	document.querySelectorAll('.setting-tab-link').forEach(link => {
		link.addEventListener('click', function(e) {
			e.preventDefault();
			
			// Get the tab name from data-tab attribute
			const tabName = this.getAttribute('data-tab');
			
			// Hide all content sections
			document.querySelectorAll('.setting-content').forEach(content => {
				content.classList.remove('active');
			});
			
			// Show the selected content section
			const selectedContent = document.getElementById(tabName);
			if (selectedContent) {
				selectedContent.classList.add('active');
			}
		});
	});
</script>
</body>
</html>