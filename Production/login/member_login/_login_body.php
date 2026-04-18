
<body>
    <video autoplay muted loop id="myVideo">
        <source src="../material/images/coffee login.mp4" type="video/mp4">
    </video>

    <div class="content">
        <div class="wrapper">
            <form action="" method="post">
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
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>

                <div class="remember-pass">
                    <label><input type="checkbox" name="remember">Remember me</label>
                    <div class="link">
                        <a href="member_login/_forgot.php">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" name="Login_btn" class="btn">Login</button>

                <div class="register">
                    <p>Don't have an account? <a href="register.php">Register</a></p>
                </div>

                <div style="margin-top: 20px; text-align: center;">
                    <a href="../index.php" style="color: red; text-decoration: none; font-size: 14px;">Back to Home</a>
                </div>
            
            </form>
        </div>
    </div>
</body>
</html>