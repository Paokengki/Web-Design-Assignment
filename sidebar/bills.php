<?php
require_once __DIR__ . '/../base.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ../login/_member_login.php');
    exit;
}

$pageTitle = 'Bills - Cafe Dash';
$extraStylesheets = ['../css/bills.css'];
$bodyClass = 'bills-page';
require_once __DIR__ . '/../home/_home_sidebar.php';

$bills = [];

// Load payment headers first; item details are loaded per payment below.
$sql = "
    SELECT
        p.Payment_ID,
        p.Payment_amount,
        p.Subtotal_amount,
        p.SST_amount,
        p.Currency,
        p.Payment_status,
        p.Payment_type,
        p.Created_at,
        p.Paid_at,
        r.Name AS Restaurant_Name
    FROM Payment p
    LEFT JOIN Restaurant r ON p.Restaurant_ID = r.Restaurant_ID
    WHERE p.User_ID = ?
    ORDER BY p.Created_at DESC
";

$stmt = $conn->prepare($sql);
if ($stmt !== false) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result !== false) {
        while ($row = $result->fetch_assoc()) {
            $paymentId = (int) $row['Payment_ID'];
            $row['items'] = [];
            $row['display_restaurant_name'] = (string) ($row['Restaurant_Name'] ?? '');
            $itemRestaurantNames = [];

            // Pull line items so users can review exactly what was purchased.
            $itemSql = "
                SELECT
                    pi.Item_name,
                    pi.Item_type,
                    pi.Quantity,
                    pi.Unit_amount,
                    pi.Line_total,
                    pi.Sugar_level,
                    pi.Ice_level,
                    pi.Remark,
                    r.Name AS Restaurant_Name
                FROM Payment_Item pi
                LEFT JOIN Food f ON pi.Food_ID = f.Food_ID
                LEFT JOIN Restaurant r ON f.Restaurant_ID = r.Restaurant_ID
                WHERE pi.Payment_ID = ?
                ORDER BY Payment_Item_ID ASC
            ";

            $itemStmt = $conn->prepare($itemSql);
            if ($itemStmt !== false) {
                $itemStmt->bind_param('i', $paymentId);
                $itemStmt->execute();
                $itemResult = $itemStmt->get_result();

                if ($itemResult !== false) {
                    while ($item = $itemResult->fetch_assoc()) {
                        $itemRestaurantName = trim((string) ($item['Restaurant_Name'] ?? ''));
                        if ($itemRestaurantName !== '') {
                            $itemRestaurantNames[$itemRestaurantName] = true;
                        }

                        $row['items'][] = $item;
                    }
                }

                $itemStmt->close();
            }

            // Fallback for multi-restaurant checkouts where Payment.Restaurant_ID is intentionally NULL.
            if (trim($row['display_restaurant_name']) === '') {
                $distinctNames = array_keys($itemRestaurantNames);
                if (count($distinctNames) === 1) {
                    $row['display_restaurant_name'] = $distinctNames[0];
                } elseif (count($distinctNames) > 1) {
                    $row['display_restaurant_name'] = 'Multiple Restaurants';
                } else {
                    $row['display_restaurant_name'] = 'Unknown';
                }
            }

            $bills[] = $row;
        }
    }

    $stmt->close();
}
?>

<div class="main">
    <div class="main-navbar">
        <a href="../sidebar/home.php" class="cart"><ion-icon name="arrow-back-outline"></ion-icon></a>
    </div>

    <div class="main-menus bills-menus">
        <div class="main-detail">
            <h2 class="main-title">Your Bills</h2>

            <?php if (count($bills) === 0): ?>
                <p style="margin-top: 12px;">No transactions yet.</p>
            <?php else: ?>
                <?php foreach ($bills as $bill): ?>
                    <div style="background:#fff; border-radius:10px; padding:14px; margin-top:14px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <strong class="bills-id">Bill #<?php echo (int) $bill['Payment_ID']; ?></strong>
                            <span>Status: <?php echo htmlspecialchars((string) $bill['Payment_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <p style="margin-top:6px; color:#555;">Restaurant: <?php echo htmlspecialchars((string) ($bill['display_restaurant_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p style="color:#555;">Paid at: <?php echo htmlspecialchars((string) ($bill['Paid_at'] ?? $bill['Created_at']), ENT_QUOTES, 'UTF-8'); ?></p>

                        <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                            <thead>
                                <tr style="text-align:left; background:#f8f8f8;">
                                    <th style="padding:8px;">Item</th>
                                    <th style="padding:8px;">Qty</th>
                                    <th style="padding:8px;">Unit</th>
                                    <th style="padding:8px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bill['items'] as $item): ?>
                                    <tr>
                                        <td style="padding:8px; border-top:1px solid #eee;">
                                            <?php echo htmlspecialchars((string) $item['Item_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if (!empty($item['Sugar_level']) || !empty($item['Ice_level']) || !empty($item['Remark'])): ?>
                                                <br>
                                                <small style="color:#777;">
                                                    <?php
                                                    $parts = [];
                                                    if (!empty($item['Sugar_level'])) {
                                                        $parts[] = 'Sugar: ' . (string) $item['Sugar_level'];
                                                    }
                                                    if (!empty($item['Ice_level'])) {
                                                        $parts[] = 'Ice: ' . (string) $item['Ice_level'];
                                                    }
                                                    if (!empty($item['Remark'])) {
                                                        $parts[] = 'Remark: ' . (string) $item['Remark'];
                                                    }
                                                    echo htmlspecialchars(implode(' | ', $parts), ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:8px; border-top:1px solid #eee;"><?php echo (int) $item['Quantity']; ?></td>
                                        <td style="padding:8px; border-top:1px solid #eee;">RM <?php echo number_format((float) $item['Unit_amount'], 2); ?></td>
                                        <td style="padding:8px; border-top:1px solid #eee;">RM <?php echo number_format((float) $item['Line_total'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div style="margin-top:10px; border-top:1px solid #eee; padding-top:10px;">
                            <p>Subtotal: RM <?php echo number_format((float) $bill['Subtotal_amount'], 2); ?></p>
                            <p>SST (6%): RM <?php echo number_format((float) $bill['SST_amount'], 2); ?></p>
                            <strong class="bills-grand-total">Grand Total: RM <?php echo number_format((float) $bill['Payment_amount'], 2); ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../home/_home_footer.php'; ?>
