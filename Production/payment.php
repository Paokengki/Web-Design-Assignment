<?php
require_once 'base.php';

$pageTitle = 'Payment - Cafe Dash';
$extraStylesheets = [];
require_once 'home/_home_sidebar.php';

// 从 config 读取 Stripe Publishable Key
$stripeConfig = require __DIR__ . '/config/stripe.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$items    = $_SESSION['cart'];
$subtotal = 0.0;

foreach ($items as $item) {
    $quantity   = max(1, (int) ($item['quantity'] ?? 1));
    $unitAmount = (float) ($item['unitAmount'] ?? 0);
    $subtotal  += $quantity * $unitAmount;
}

$sst        = round($subtotal * 0.06, 2);
$grandTotal = round($subtotal + $sst, 2);
?>

<div class="main">
    <div class="main-navbar">
        <a href="javascript:history.back()" class="cart"><ion-icon name="arrow-back-outline"></ion-icon></a>
        <div class="profile">
            <a class="cart" href="home.php"><ion-icon name="home-outline"></ion-icon></a>
        </div>
    </div>

    <div class="main-menus">
        <div class="main-detail">
            <h2 class="main-title">Payment Summary</h2>

            <?php if (count($items) === 0): ?>
                <p>Your cart is empty.</p>
            <?php else: ?>

                <!-- 订单表格 -->
                <table style="width:100%; border-collapse:collapse; margin-top:12px; background:#fff; border-radius:8px; overflow:hidden;">
                    <thead>
                        <tr style="background:#f2f2f2; text-align:left;">
                            <th style="padding:10px;">Item</th>
                            <th style="padding:10px;">Quantity</th>
                            <th style="padding:10px;">Unit Amount</th>
                            <th style="padding:10px;">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $itemName   = (string) ($item['itemName'] ?? '');
                            $quantity   = max(1, (int) ($item['quantity'] ?? 1));
                            $unitAmount = (float) ($item['unitAmount'] ?? 0);
                            $lineTotal  = $quantity * $unitAmount;
                            ?>
                            <tr>
                                <td style="padding:10px; border-top:1px solid #eee;"><?php echo htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:10px; border-top:1px solid #eee;"><?php echo $quantity; ?></td>
                                <td style="padding:10px; border-top:1px solid #eee;">RM <?php echo number_format($unitAmount, 2); ?></td>
                                <td style="padding:10px; border-top:1px solid #eee;">RM <?php echo number_format($lineTotal, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- 金额汇总 -->
                <div style="margin-top:16px; background:#fff; padding:14px; border-radius:8px;">
                    <p><strong>Total Amount:</strong> RM <?php echo number_format($subtotal, 2); ?></p>
                    <p><strong>SST (6%):</strong> RM <?php echo number_format($sst, 2); ?></p>
                    <p style="font-size:20px; color:#6E2C00;"><strong>Total Amount + SST:</strong> RM <?php echo number_format($grandTotal, 2); ?></p>
                </div>

                <!-- Stripe 卡号输入区域 -->
                <div style="margin-top:14px;">
                    <label style="display:block; margin-bottom:6px; font-weight:bold; font-size:14px;">Card Details</label>
                    <div id="card-element" style="padding:12px; border:1px solid #ddd; border-radius:8px; background:#fff; margin-bottom:10px;"></div>
                    <div id="card-errors" style="color:red; font-size:14px; margin-bottom:10px;"></div>
                    <button type="button" class="search-btn" id="pay-btn">
                        Pay Now — RM <?php echo number_format($grandTotal, 2); ?>
                    </button>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'home/_home_footer.php'; ?>

<!-- Stripe.js -->
<script src="https://js.stripe.com/v3/"></script>
<script>
    // Publishable Key 从 config 读取
    const stripe = Stripe('<?php echo htmlspecialchars($stripeConfig["publishable_key"], ENT_QUOTES, "UTF-8"); ?>');

    const elements = stripe.elements({ locale: 'en' });

    const cardElement = elements.create('card', {
        hidePostalCode: true,
        disableLink: true,
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                fontFamily: 'Arial, sans-serif',
                '::placeholder': { color: '#aab7c4' }
            },
            invalid: { color: '#fa755a' }
        }
    });

    cardElement.mount('#card-element');

    // 实时错误提示
    cardElement.on('change', (event) => {
        document.getElementById('card-errors').textContent = event.error ? event.error.message : '';
    });

    // 点击 Pay Now
    document.getElementById('pay-btn').addEventListener('click', async () => {
    const btn      = document.getElementById('pay-btn');
    const errorDiv = document.getElementById('card-errors');
    const original = btn.textContent;

    btn.disabled         = true;
    btn.textContent      = 'Processing...';
    errorDiv.textContent = '';

    try {
        const res = await fetch('create_payment_intent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        // 尝试解析 JSON。如果解析失败，通常是因为后端返回了 PHP 报错网页
        const text = await res.text(); 
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Server Response was not JSON:", text);
            throw new Error("Server returned invalid data. Check browser Network tab for details.");
        }

        if (data.error) {
            errorDiv.textContent = data.error;
            btn.disabled    = false;
            btn.textContent = original;
            return;
        }

        // Stripe 确认付款
        const { paymentIntent, error: stripeError } = await stripe.confirmCardPayment(
            data.clientSecret,
            { payment_method: { card: cardElement } }
        );

        if (stripeError) {
            errorDiv.textContent = stripeError.message;
            btn.disabled    = false;
            btn.textContent = original;
        } else if (paymentIntent.status === 'succeeded') {
            window.location.href = 'payment_success.php';
        }

    } catch (err) {
        // 这里会显示上面定义的详细错误，方便调试
        errorDiv.textContent = err.message; 
        btn.disabled    = false;
        btn.textContent = original;
    }
});
</script>