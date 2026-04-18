<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
	header("Location: ../login/_member_login.php");
    exit;
}

// Include CSV functions
require_once __DIR__ . '/../profile/avatar_csv_functions.php';

// Get user ID
$user_id = $_SESSION['user_id'];

// Database connection
$conn = new mysqli("localhost", "root", "", "cafedash_db");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get user information from database
$stmt = $conn->prepare("SELECT User_Name, Email, Contain_number, Address FROM User WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$user_data = [
    'User_Name' => '',
    'Email' => '',
    'Contain_number' => '',
    'Address' => ''
];

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
}

$stmt->close();
$conn->close();

// Get avatar from CSV
$profile_image = getAvatarFromCSV($user_id);

// Use default SVG if no profile image
if (!$profile_image) {
    $profile_image = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 150 150%22%3E%3Crect width=%22150%22 height=%22150%22 fill=%22%23D3D3D3%22/%3E%3Ccircle cx=%2275%22 cy=%2250%22 r=%2230%22 fill=%22white%22/%3E%3Cpath d=%22M 30 90 Q 30 80 75 80 Q 120 80 120 90 L 120 150 Q 120 150 75 150 Q 30 150 30 150 Z%22 fill=%22white%22/%3E%3C/svg%3E';
}

$pageTitle = 'Settings - Cafe Dash';
$extraStylesheets = ['../css/setting style.css'];
$bodyClass = 'setting-page';

require '../home/_home_sidebar.php';
?>

	<!-- The settings page keeps profile, account, notification, and privacy in separate tabs. -->
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
	<div class="main setting-main">
		<!-- Profile Content -->
		<div id="profile" class="setting-content active">
			<h2>Profile Settings</h2>
			<div class="setting-form">
				<!-- Profile Picture Section -->
				<div class="profile-picture-section">
					<div class="profile-picture-container">
						<div id="profilePicWrapper" class="profile-pic-wrapper">
						<img id="profilePic" src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile Picture">
						</div>
						<label for="profileImageInput" class="upload-btn">Change Photo</label>
						<input type="file" id="profileImageInput" accept="image/*" style="display: none;">
					</div>
				</div>

				<!-- Personal Information -->
				<div class="form-group">
					<label>Full Name</label>
					<input type="text" id="fullName" placeholder="Enter your full name" value="<?php echo htmlspecialchars($user_data['User_Name'] ?? ''); ?>">
				</div>
				<div class="form-group">
					<label>Email</label>
					<input type="email" id="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($user_data['Email'] ?? ''); ?>">
				</div>
				<div class="form-group">
					<label>Phone Number</label>
					<input type="tel" id="phone" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($user_data['Contain_number'] ?? ''); ?>">
				</div>
				<div class="form-group">
					<label>Address</label>
					<textarea id="address" placeholder="Enter your address" rows="3"><?php echo htmlspecialchars($user_data['Address'] ?? ''); ?></textarea>
				</div>
				<button class="save-btn" id="saveProfileBtn">Save Profile</button>
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
					<input type="password" id="accountCurrentPassword" placeholder="Enter current password">
				</div>
				<div class="form-group">
					<label>New Password</label>
					<input type="password" id="accountNewPassword" placeholder="Enter new password">
				</div>
				<div class="form-group">
					<label>Confirm Password</label>
					<input type="password" id="accountConfirmPassword" placeholder="Confirm new password">
				</div>
				<button class="save-btn" id="accountUpdatePasswordBtn">Update Password</button>
			</div>
		</div>

		<!-- Privacy Content -->
		<div id="privacy" class="setting-content">
			<h2>Privacy Settings</h2>
			<div class="setting-form">
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

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		console.log('=== Setting Page Loaded ===');
		
		// Switch the visible settings tab without leaving the page.
		document.querySelectorAll('.setting-tab-link').forEach(link => {
			link.addEventListener('click', function(e) {
				e.preventDefault();
				
				// Read the target section from the data-tab attribute.
				const tabName = this.getAttribute('data-tab');
				console.log('Tab clicked:', tabName);
				
				// Hide every section before showing the selected one.
				document.querySelectorAll('.setting-content').forEach(content => {
					content.classList.remove('active');
				});
				
				// Show the requested settings section.
				const selectedContent = document.getElementById(tabName);
				if (selectedContent) {
					selectedContent.classList.add('active');
				}
			});
		});

		// Keep the avatar preview responsive and fall back to a default image if needed.
		const profileImageInput = document.getElementById('profileImageInput');
		const profilePic = document.getElementById('profilePic');
		const profilePicWrapper = document.getElementById('profilePicWrapper');

		console.log('Elements found:', {
			profileImageInput: !!profileImageInput,
			profilePic: !!profilePic,
			profilePicWrapper: !!profilePicWrapper
		});

		if (profilePic) {
			profilePic.onerror = function() {
				console.log('Image load failed, using fallback SVG');
				this.src = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 150 150%22%3E%3Crect width=%22150%22 height=%22150%22 fill=%22%23D3D3D3%22/%3E%3Ccircle cx=%2275%22 cy=%2250%22 r=%2230%22 fill=%22white%22/%3E%3Cpath d=%22M 30 90 Q 30 80 75 80 Q 120 80 120 90 L 120 150 Q 120 150 75 150 Q 30 150 30 150 Z%22 fill=%22white%22/%3E%3C/svg%3E';
				this.onerror = null;
			};
		}

		if (profileImageInput) {
			profileImageInput.addEventListener('change', function(e) {
				const file = e.target.files[0];
				if (!file) {
					console.log('No file selected');
					return;
				}
				
				console.log('File selected:', file.name, 'Size:', file.size, 'Type:', file.type);
				
				// Show the selected avatar immediately before uploading it.
				const reader = new FileReader();
				reader.onload = function(event) {
					console.log('Preview loaded');
					profilePic.src = event.target.result;
				};
				reader.readAsDataURL(file);

				// Upload the avatar to the server and persist the CSV mapping.
				const formData = new FormData();
				formData.append('profileImage', file);

				console.log('Starting upload...');
				fetch('../api/profile/upload_avatar_csv.php', {
					method: 'POST',
					body: formData
				})
				.then(response => {
					console.log('Response status:', response.status);
					return response.json();
				})
				.then(data => {
					console.log('Upload response:', data);
					if (data.success) {
						console.log('Upload successful');
						alert('Profile picture uploaded successfully!');
						setTimeout(() => {
							location.reload();
						}, 1000);
					} else {
						alert('Upload failed: ' + data.message);
						console.error('Upload error:', data.message);
					}
				})
				.catch(error => {
					console.error('Upload error:', error);
					alert('Error occurred during upload: ' + error.message);
				});
			});
		} else {
			console.warn('profileImageInput element not found!');
		}

		// Let the avatar image itself act as the upload trigger.
		if (profilePicWrapper) {
			profilePicWrapper.addEventListener('click', function() {
				console.log('Avatar clicked');
				if (profileImageInput) {
					profileImageInput.click();
				}
			});
			profilePicWrapper.style.cursor = 'pointer';
		}

		// Save the basic profile fields through the profile API.
		const saveProfileBtn = document.getElementById('saveProfileBtn');
		if (saveProfileBtn) {
			saveProfileBtn.addEventListener('click', function() {
				console.log('Save Profile button clicked');
				
				const fullName = document.getElementById('fullName').value;
				const email = document.getElementById('email').value;
				const phone = document.getElementById('phone').value;
				const address = document.getElementById('address').value;

				if (!fullName.trim()) {
					alert('Please enter your name');
					return;
				}

				const formData = new FormData();
				formData.append('full_name', fullName);
				formData.append('email', email);
				formData.append('phone', phone);
				formData.append('address', address);

				console.log('Sending profile data to server...');
				fetch('../api/profile/save_profile.php', {
					method: 'POST',
					body: formData
				})
				.then(response => {
					console.log('Response status:', response.status);
					return response.json();
				})
				.then(data => {
					console.log('Save response:', data);
					if (data.success) {
						alert('Personal information saved successfully!');
					} else {
						alert('Save failed: ' + data.message);
					}
				})
				.catch(error => {
					console.error('Save error:', error);
					alert('Error occurred during saving: ' + error.message);
				});
			});
		}

		// The account tab uses the same password API, but with a separate UI trigger.
		const accountUpdatePasswordBtn = document.getElementById('accountUpdatePasswordBtn');
		if (accountUpdatePasswordBtn) {
			accountUpdatePasswordBtn.addEventListener('click', function() {
				const currentPassword = document.getElementById('accountCurrentPassword').value;
				const newPassword = document.getElementById('accountNewPassword').value;
				const confirmPassword = document.getElementById('accountConfirmPassword').value;

				if (!currentPassword.trim()) {
					alert('Please enter current password');
					return;
				}
				if (!newPassword.trim()) {
					alert('Please enter new password');
					return;
				}
				if (!confirmPassword.trim()) {
					alert('Please confirm new password');
					return;
				}
				if (newPassword !== confirmPassword) {
					alert('New password and confirm password do not match');
					return;
				}
				if (newPassword.length < 6) {
					alert('New password must be at least 6 characters long');
					return;
				}
				if (currentPassword === newPassword) {
					alert('New password cannot be the same as current password');
					return;
				}

				const formData = new FormData();
				formData.append('current_password', currentPassword);
				formData.append('new_password', newPassword);
				formData.append('confirm_password', confirmPassword);

				fetch('../api/profile/change_password.php', {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						alert('Password updated successfully!');
						document.getElementById('accountCurrentPassword').value = '';
						document.getElementById('accountNewPassword').value = '';
						document.getElementById('accountConfirmPassword').value = '';
					} else {
						alert('Update failed: ' + data.message);
					}
				})
				.catch(error => {
					alert('Password update error: ' + error.message);
				});
			});
		}
	});
	</script>

<?php require_once '../home/_home_footer.php'; ?>