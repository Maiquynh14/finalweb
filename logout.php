<?php
// logout.php
declare(strict_types=1);

session_start();

// 1) Xoá toàn bộ dữ liệu session
$_SESSION = [];

// 2) Huỷ cookie session nếu có
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3) Huỷ session trên server
session_destroy();

// 4) Bắt đầu session mới để lưu flash message
session_start();
$_SESSION['flash_message'] = 'Bạn đã đăng xuất thành công.';

// 5) Chuyển hướng về trang login
header('Location: login.php');
exit();
