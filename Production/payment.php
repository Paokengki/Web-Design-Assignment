<?php
require_once 'base.php';

$pageTitle = 'Payment - Cafe Dash';
$extraStylesheets = [];
require_once 'home/_home_sidebar.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$items = $_SESSION['cart'];
$subtotal = 0.0;

foreach ($items as $item) {
    $quantity = max(1, (int) ($item['quantity'] ?? 1));
    $unitAmount = (float) ($item['unitAmount'] ?? 0);
    $subtotal += $quantity * $unitAmount;
}

$sst = round($subtotal * 0.06, 2);
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
                <table style="width:100%; border-collapse: collapse; margin-top: 12px; background:#fff; border-radius:8px; overflow:hidden;">
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
                            $itemName = (string) ($item['itemName'] ?? '');
                            $quantity = max(1, (int) ($item['quantity'] ?? 1));
                            $unitAmount = (float) ($item['unitAmount'] ?? 0);
                            $lineTotal = $quantity * $unitAmount;
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

                <div style="margin-top: 16px; background:#fff; padding:14px; border-radius:8px;">
                    <p><strong>Total Amount:</strong> RM <?php echo number_format($subtotal, 2); ?></p>
                    <p><strong>SST (6%):</strong> RM <?php echo number_format($sst, 2); ?></p>
                    <p style="font-size: 20px; color:#6E2C00;"><strong>Total Amount + SST:</strong> RM <?php echo number_format($grandTotal, 2); ?></p>
                </div>

                <div style="margin-top: 14px;">
                    <button type="button" class="search-btn" onclick="alert('Payment gateway port is ready for integration.');">Pay Now</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'home/_home_footer.php'; ?>
