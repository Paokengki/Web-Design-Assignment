<?php
require __DIR__ . '/../app/bootstrap.php';
redirect(current_staff() ? 'admin/overview.php' : 'auth/staff_login.php');
