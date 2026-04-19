
<body>
    <video autoplay muted loop id="myVideo">
        <source src="../material/images/coffee login.mp4" type="video/mp4">
    </video>

    <div class="content auth-content">
        <div class="wrapper auth-wrapper">
            <form action="" method="post" class="auth-form">
                <h1>Login</h1>
                <div class="logo">
                    <img src="../material/images/logo.png" alt="logo" style="width:200px;height:200px;">
                </div>

                <?php if ($error_message != ""): ?>
                    <div class="error-box">
                        <i class='bx bx-error-circle'></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="textbox">
                    <input type="text" name="User_name" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>

                <div class="textbox">
                    <input type="password" name="password" id="memberLoginPassword" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                    <button type="button" class="toggle-pass" data-target="#memberLoginPassword" aria-label="Toggle password visibility">
                        <i class='bx bx-show'></i>
                    </button>
                </div>

                <div class="remember-pass">
                    <label><input type="checkbox" name="remember">Remember me</label>
                    <div class="link">
                        <a href="member_login/_forgot.php">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" name="Login_btn" class="btn auth-btn">Login</button>

                <div class="register">
                    <p>Don't have an account? <a href="register.php">Register</a></p>
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