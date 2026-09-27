<?php
session_start();

// Remove all session data and invalidate the browser's session cookie.
session_unset();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// The JavaScript confirmation dialogs redirect after this request. A normal
// form submission is redirected here by PHP.
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
    http_response_code(204);
    exit();
}

header('Location: /SUPPLY_OFFICE_first/modules/auth/index.php', true, 303);
exit();
?>
