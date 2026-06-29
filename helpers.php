<?php

// Date of Birth to Age Auto-Calculation Helper
if (!function_exists('calculateAge')) {
    function calculateAge($dob)
    {
        if (empty($dob))
            return 'N/A';
        try {
            $birthdate = new DateTime($dob);
            $today = new DateTime('today');
            $age = $birthdate->diff($today)->y;
            return $age;
        } catch (Exception $e) {
            return 'N/A';
        }
    }
}

function formatIndianCurrency($num)
{
    $num = (int) $num;
    $negative = $num < 0 ? '-' : '';
    $num = abs($num);

    $explicit_str = (string) $num;
    $len = strlen($explicit_str);

    if ($len <= 3) {
        return $negative . $explicit_str;
    }

    $last_three = substr($explicit_str, -3);
    $remaining = substr($explicit_str, 0, $len - 3);

    // Split the remaining digits into pairs of twos
    $remaining_formatted = preg_replace("/\B(?=(\d{2})+(?!\d))/", ",", $remaining);

    return $negative . $remaining_formatted . ',' . $last_three;
}

function get_global_flash_message()
{
    // 1. If session status isn't active yet, start it safely
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $flash = [
        'success' => '',
        'error' => ''
    ];

    // Check Session Storage (New Flash Method)
    if (isset($_SESSION['flash_msg'])) {
        $flash['success'] = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']); // Delete immediately so it won't show on refresh
    }
    if (isset($_SESSION['flash_error'])) {
        $flash['error'] = $_SESSION['flash_error'];
        unset($_SESSION['flash_error']); // Delete immediately
    }

    // Fallback: Check URL Query String parameters (Old Method)
    if (empty($flash['success']) && isset($_GET['msg'])) {
        $flash['success'] = $_GET['msg'];
    }
    if (empty($flash['error']) && isset($_GET['error'])) {
        $flash['error'] = $_GET['error'];
    }

    return $flash;
}

function isSystemAdmin()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $current_user = $_SESSION['username'] ?? '';
    return ($current_user === 'admin');
}
?>