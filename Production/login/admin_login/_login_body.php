<?php
// Note: $error_message is pulled from base.php logic
?>
<body>
    <video autoplay muted loop id="myVideo">
        <source src="../material/images/coffee login.mp4" type="video/mp4">
    </video>

    <div class="content auth-content">
        <div class="wrapper auth-wrapper">
            <form action="" method="post" class="auth-form">
                <h1>Admin Login</h1>
                <div class="logo">
                    <img src="../material/images/logo.png" alt="logo" style="width:200px;height:200px;">
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
                    <input type="password" name="admin_password" id="adminLoginPassword" placeholder="Admin Password" required>
                    <i class='bx bxs-lock-alt'></i>
                    <button type="button" class="toggle-pass" data-target="#adminLoginPassword" aria-label="Toggle password visibility">
                        <i class='bx bx-show'></i>
                    </button>
                </div>
                
                <button type="submit" name="Admin_login_btn" class="btn auth-btn">Login</button>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="../index.php" class="back-home" style="color: white; text-decoration: none; font-size: 14px;">Back to Home</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-pass').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.querySelector(button.getAttribute('data-target'));
                var icon = button.querySelector('i');

                if (!target || !icon) {
                    return;
                }

                var isPassword = target.getAttribute('type') === 'password';
                target.setAttribute('type', isPassword ? 'text' : 'password');
                icon.classList.toggle('bx-show', !isPassword);
                icon.classList.toggle('bx-hide', isPassword);
            });
        });
    </script>
</body>
</html>