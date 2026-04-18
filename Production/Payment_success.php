<?php
require_once 'base.php';

$pageTitle = 'Payment Successful - Cafe Dash';
$extraStylesheets = ['Css/payment style.css'];
$bodyClass = 'payment-success-page';
require_once 'home/_home_sidebar.php';

// 清空购物车
$_SESSION['cart'] = [];
?>

<div class="main">
    <div class="main-navbar">
        <div class="profile">
            <a class="cart" href="home.php"><ion-icon name="home-outline"></ion-icon></a>
        </div>
    </div>

    <div class="main-menus">
        <div class="main-detail" style="text-align:center; padding:40px 20px;">

            <ion-icon name="checkmark-circle-outline" style="font-size:80px; color:#4CAF50; display:block; margin:0 auto 16px;"></ion-icon>

            <h2 class="main-title" style="color:#4CAF50;">Payment Successful!</h2>
            <p style="color:#555; margin-top:8px;">Thank you for your order at Cafe Dash.</p>
            <p style="color:#555;">Your order is being prepared. ☕</p>

            <div style="margin-top:30px;">
                <a href="home.php" class="search-btn payment-action-btn">Back to Home</a>
            </div>

        </div>
    </div>
</div>

<?php require_once 'home/_home_footer.php'; ?>