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
?>