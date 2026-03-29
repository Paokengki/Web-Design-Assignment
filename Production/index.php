<?php require '_base.php';

//set title to cafe dash
$_title = 'Cafe Dash'; 
//get header
include '_head.php';
?>

<!-- use the css for below -->
<link rel="stylesheet" href="/Css/index_login_page.css">

     <video autoplay muted loop id="myVideo">
        <source src="material/images/coffee login.mp4" type="video/mp4">
    </video>

    <div class="content">
        <div class="wrapper">
            <form action="">
                <h1>Login</h1>
                <div class="logo"><img src="material/images/logo.png" alt="logo" style="width:200px;height:200px;"></div>
                <div class="textbox">
                    <input type="text" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="textbox">
                    <input type="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>

                <div class="remember-pass">
                    <label><input type="checkbox">Remember me</label>
					<div class="link">
                    <a href="forgot.html">Forgot password?</a>
					</div>
                </div>

                <button type="button" class="btn" onclick="redirectToHome()">Login</button>
				
				<script>
					function redirectToHome() {
						window.location.href = "home.html";
					}
				</script>

                <div class="register">
                    <p>Don't have an account? <a href="register.html">Register</a></p>
                </div>
            </form>
        </div>
    </div>
<?php 
include '_footer.php';
?>