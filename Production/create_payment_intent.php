<?php
/**
 * Stripe PaymentIntent 创建接口
 * 仅供 payment.php 通过 fetch 调用
 */

// 1. 开启输出缓冲，防止 base.php 的 HTML 意外输出到 JSON 中
ob_start();

// 2. 环境配置：生产环境下不直接显示 PHP 错误，避免破坏 JSON 结构
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 3. 引入基础配置和加载器
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/vendor/autoload.php';

// 4. 读取配置并初始化 Stripe
$stripeConfig = require __DIR__ . '/config/stripe.php';
if (isset($stripeConfig['secret_key'])) {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
}

/**
 * 此时我们要准备输出 JSON 了。
 * 只有在这里才执行 ob_clean()，确保清空了之前 base.php 可能输出的菜单、空格或换行。
 */
if (ob_get_length()) ob_clean(); 
header('Content-Type: application/json');

try {
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // 获取购物车数据
    $items = $_SESSION['cart'] ?? [];
    if (empty($items)) {
        throw new Exception('Cart is empty');
    }

    // 后端重新计算总额 (以 Sen/分 为单位)
    $subtotal = 0.0;
    foreach ($items as $item) {
        $quantity   = max(1, (int) ($item['quantity'] ?? 1));
        $unitAmount = (float) ($item['unitAmount'] ?? 0);
        $subtotal  += $quantity * $unitAmount;
    }

    $sst         = round($subtotal * 0.06, 2);
    $grandTotal  = round($subtotal + $sst, 2);
    $amountInSen = (int) round($grandTotal * 100);

    // 调用 Stripe API 创建支付意向
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount'   => $amountInSen,
        'currency' => 'myr',
        'metadata' => ['source' => 'cafe_dash'],
    ]);

    // 成功返回 clientSecret
    echo json_encode(['clientSecret' => $paymentIntent->client_secret]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe API: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

// 确保逻辑执行完毕后直接退出，不让系统再输出任何东西
exit;