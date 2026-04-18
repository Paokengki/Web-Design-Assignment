<?php
require_once __DIR__ . '/../base.php';

$pageTitle = 'Payment - Cafe Dash';
$extraStylesheets = ['../css/payment style.css'];
$bodyClass = 'payment-page';
require_once __DIR__ . '/../home/_home_sidebar.php';

// Load the Stripe public key once for the client-side card confirmation step.
$stripeConfig = require __DIR__ . '/../config/stripe.php';

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
            <a class="cart" href="../sidebar/home.php"><ion-icon name="home-outline"></ion-icon></a>
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
                <div class="payment-totals-card">
                    <div class="payment-totals-list">
                        <div class="payment-total-row">
                            <span class="payment-total-label">Total Amount</span>
                            <strong class="payment-total-value">RM <?php echo number_format($subtotal, 2); ?></strong>
                        </div>
                        <div class="payment-total-row">
                            <span class="payment-total-label">SST (6%)</span>
                            <strong class="payment-total-value">RM <?php echo number_format($sst, 2); ?></strong>
                        </div>
                        <div class="payment-total-row payment-total-row-grand">
                            <span class="payment-total-label">Total Amount + SST</span>
                            <strong class="payment-total-value">RM <?php echo number_format($grandTotal, 2); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- The card field only collects payment details; the charge is created server-side. -->
                <div class="payment-card-section">
                    <label class="payment-card-label">Card Details</label>
                    <div class="payment-card-row">
                        <div class="payment-card-field">
                            <div id="card-element" class="payment-card-element"></div>
                        </div>
                    </div>
                    <div class="payment-card-actions">
                        <button type="button" class="search-btn payment-action-btn" id="pay-btn">
                            Pay Now — RM <?php echo number_format($grandTotal, 2); ?>
                        </button>
                    </div>
                    <div id="card-errors" class="payment-card-errors"></div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../home/_home_footer.php'; ?>

<!-- Stripe.js -->
<script src="https://js.stripe.com/v3/"></script>
<script>
    // Initialize Stripe with the public key loaded from config.
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

    // Show card validation errors immediately as the user types.
    cardElement.on('change', (event) => {
        document.getElementById('card-errors').textContent = event.error ? event.error.message : '';
    });

    // Recompute the amount on the server, create a PaymentIntent, then confirm the card payment.
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

        // Parse the JSON response before handing the secret to Stripe.
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

        // Confirm the card payment with the PaymentIntent secret.
        const { paymentIntent, error: stripeError } = await stripe.confirmCardPayment(
            data.clientSecret,
            { payment_method: { card: cardElement } }
        );

        if (stripeError) {
            errorDiv.textContent = stripeError.message;
            btn.disabled    = false;
            btn.textContent = original;
        } else if (paymentIntent.status === 'succeeded') {
            // Persist the successful transaction into Payment and Payment_Item tables.
            const finalizeRes = await fetch('finalize_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ payment_intent_id: paymentIntent.id })
            });

            const finalizeData = await finalizeRes.json();
            if (!finalizeData || !finalizeData.success) {
                errorDiv.textContent = (finalizeData && finalizeData.message) ? finalizeData.message : 'Payment succeeded but failed to save order.';
                btn.disabled    = false;
                btn.textContent = original;
                return;
            }

            window.location.href = 'Payment_success.php?payment_id=' + encodeURIComponent(finalizeData.payment_id || '');
        }

    } catch (err) {
        // 这里会显示上面定义的详细错误，方便调试
        errorDiv.textContent = err.message; 
        btn.disabled    = false;
        btn.textContent = original;
    }
});
</script>