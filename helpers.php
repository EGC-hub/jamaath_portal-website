<?php

// Date of Birth to Age Auto-Calculation Helper
if (!function_exists('calculateAge')) {
    function calculateAge($dob) {
        if (empty($dob)) return 'N/A';
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
?>