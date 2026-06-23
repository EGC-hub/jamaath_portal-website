<?php

$config = include '../config.php';

// Enable error reporting to identify any environmental issues immediately (Disable this when live in production!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Production MySQL Database connection credentials
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'jamath_portal';

try {
    // Establish PDO MySQL Connection
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div style='font-family: sans-serif; padding: 30px; max-width: 600px; margin: 50px auto; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
            <h3 style='margin: 0 0 10px 0; font-size: 20px;'>Database Connection Error</h3>
            <p style='font-size: 14px; line-height: 1.5;'>The server was unable to establish a secure link to your MySQL deployment databases using the provided u184821809 credentials.</p>
            <p style='font-size: 13px; font-weight: bold; margin-top: 15px;'>Technical Error Code:</p>
            <code style='display: block; background: #fff; padding: 10px; border-radius: 6px; font-size: 12px; word-break: break-all; border: 1px solid #f5c6cb;'>" . htmlspecialchars($e->getMessage()) . "</code>
         </div>");
}
?>