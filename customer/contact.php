<?php
// "Contact" was renamed to "Feedback". Kept so old links and bookmarks still work.
require __DIR__ . '/../app/bootstrap.php';
header('Location: ' . url('customer/feedback.php'), true, 301);
exit;
