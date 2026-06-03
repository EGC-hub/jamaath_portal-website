<?php
// Start dynamic session context
session_start();

// Wipe all active session variables
$_SESSION = array();

// Destroy session on the host machine
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect back to the public website home
header("Location: index.html?msg=Logged out successfully");
exit;
?>