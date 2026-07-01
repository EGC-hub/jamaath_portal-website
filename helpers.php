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

if (!function_exists('getHijriDate')) {
    function getHijriDate($gregorianDate)
    {
        if (empty($gregorianDate) || $gregorianDate === '0000-00-00') {
            return '';
        }

        try {
            $time = strtotime($gregorianDate);
            if (!$time)
                return '';

            $year = (int) date('Y', $time);
            $month = (int) date('m', $time);
            $day = (int) date('d', $time);

            if (($year > 1582) || (($year == 1582) && ($month > 10)) || (($year == 1582) && ($month == 10) && ($day > 14))) {
                $jd = (int) ((1461 * ($year + 4800 + (int) (($month - 14) / 12))) / 4) +
                    (int) ((367 * ($month - 2 - 12 * ((int) (($month - 14) / 12)))) / 12) -
                    (int) ((3 * (int) (($year + 4900 + (int) (($month - 14) / 12)) / 100)) / 4) + $day - 32075;
            } else {
                $jd = 367 * $year - (int) ((7 * ($year + 5001 + (int) (($month - 9) / 7))) / 4) +
                    (int) ((275 * $month) / 9) + $day + 1729777;
            }

            $l = $jd - 1948440 + 10632;
            $n = (int) (($l - 1) / 10631);
            $l = $l - 10631 * $n + 354;
            $z = ((int) ((10985 - $l) / 5316)) * ((int) ((50 * $l) / 17719)) + ((int) ($l / 5670)) * ((int) ((43 * $l) / 15238));
            $l = $l - ((int) ((30 - $z) / 15)) * ((int) ((17719 * $z) / 50)) - ((int) ($z / 16)) * ((int) ((15238 * $z) / 43)) + 29;

            $month = (int) ((24 * $l) / 709);
            $day = $l - (int) ((709 * $month) / 24);
            $year = 30 * $n + $z - 30;

            $hijriMonths = [
                1 => "Muharram",
                2 => "Safar",
                3 => "Rabi' al-Awwal",
                4 => "Rabi' ath-Thani",
                5 => "Jumada al-Ula",
                6 => "Jumada al-Akhirah",
                7 => "Rajab",
                8 => "Sha'ban",
                9 => "Ramadan",
                10 => "Shawwal",
                11 => "Dhu al-Qa'dah",
                12 => "Dhu al-Hijjah"
            ];

            $hijriMonthName = $hijriMonths[$month] ?? '';

            return "$day $hijriMonthName $year AH";
        } catch (Exception $e) {
            return '';
        }
    }
}
?>