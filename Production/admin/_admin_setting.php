<?php
// admin/setting.php

// 1. Go up one level to reach base.php in the root folder
require_once '../admin/base.php'; 

// 2. Set the page title for the header
$pageTitle = "Admin Settings - CafeDash";

// 3. Include the admin sidebar (which is in the same folder)
include '_admin_sidebar.php'; 
?>

<div class="main">
    <div class="main-navbar" style="margin-bottom: 20px;">
        <div class="main-title">
            <h1>Settings</h1>
        </div>
        <a href="../admin/_admin_setting.php" style="text-decoration: none; color: inherit;">
            <div class="profile" style="cursor: pointer;">
                <span class="user"><ion-icon name="people-circle-outline"></ion-icon></span>
                <strong>Admin</strong>
            </div>
        </a>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div style="background: var(--whiteColor); color: var(--primaryColor); padding: 15px; border-radius: 8px; margin: 20px 0; font-weight: bold; border-left: 5px solid var(--primaryColor);">
            <i class='bx bx-info-circle'></i> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="main-menus">
        <div id="account" class="setting-content">
            <h2 style="color: var(--darkBrown); margin-bottom: 20px;">Account Settings</h2>
            
            <form action="../admin/base.php" method="POST" class="setting-form" style="max-width: 500px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--blackColor);">Current Password</label>
                    <input type="password" name="current_password" placeholder="Enter current password" required 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--blackColor);">New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password" required 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: var(--blackColor);">Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required 
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>

                <button type="submit" name="update_admin_password" class="save-btn" 
                        style="background: var(--primaryColor); color: white; border: none; padding: 12px 20px; border-radius: 20px; cursor: pointer; font-weight: bold; width: 100%;">
                    Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php 
// Include the shared footer from the root/home folder
include '../home/_home_footer.php'; 
?>