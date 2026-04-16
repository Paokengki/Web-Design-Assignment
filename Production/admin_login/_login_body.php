<?php
// Note: $error_message is pulled from base.php logic
?>
<body>
    <video autoplay muted loop id="myVideo">
        <source src="material/images/coffee login.mp4" type="video/mp4">
    </video>

    <div class="content">
        <div class="wrapper">
            <form action="" method="post">
                <h1>Admin Login</h1>
                <div class="logo">
                    <img src="material/images/logo.png" alt="logo" style="width:200px;height:200px;">
                </div>

                <?php if ($error_message != ""): ?>
                    <div class="error-box">
                        <i class='bx bx-error-circle'></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="textbox">
                    <input type="text" name="admin_name" placeholder="Admin Username" required>
                    <i class='bx bxs-user-badge'></i>
                </div>

                <div class="textbox">
                    <input type="password" name="admin_password" placeholder="Admin Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                
                <button type="submit" name="Admin_login_btn" class="btn">Login</button>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="index.php" style="color: white; text-decoration: none; font-size: 14px;">Back to Home</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>