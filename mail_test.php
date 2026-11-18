<?php
/**
 * mail_test.php — 邮件诊断工具。在项目根目录的终端运行：
 *     php mail_test.php 收信的邮箱@gmail.com
 * 它使用和 "Forgot password" 相同的设置（config/mail.config.php），并打印完整的 SMTP 过程。
 * 测试完请删除此文件。
 */
if (PHP_SAPI !== 'cli') exit('Run from the command line only.');
require __DIR__ . '/app/services/cafe_mailer.php';

$to = $argv[1] ?? '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) exit("Usage: php mail_test.php you@example.com\n");

$cfg = cafe_mail_config();
if (!$cfg) exit("config/mail.config.php was not found or is missing a key (host, port, username, password, from_email, from_name).\nSee the PHP error output above.\n");
if (!cafe_mail_load()) exit("PHPMailer is not installed: vendor/autoload.php not found.\nRun: composer require phpmailer/phpmailer\n");

echo "Using SMTP {$cfg['host']}:{$cfg['port']} as {$cfg['username']}\n\n";

try {
	$m = cafe_mail_new($cfg);
	$m->SMTPDebug = 2;
	$m->Debugoutput = 'echo';
	$m->addAddress($to);
	$m->Subject = 'CafeDash mail test';
	$m->Body = 'If you can read this, email sending works.';
	$m->send();
	echo "\n\n=== SENT OK — check the inbox (and Spam) of $to ===\n";
} catch (Throwable $e) {
	echo "\n\n=== FAILED: " . $e->getMessage() . " ===\n";
}
