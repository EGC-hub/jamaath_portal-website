<?php

// Enable error reporting to identify any environmental issues immediately
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// MySQL Server configuration defaults (standard XAMPP credentials)
$db_host = 'localhost';
$db_user = 'u184821809_nvk_admin';
$db_pass = 'Nvk@2026!';
$db_name = 'u184821809_nvk_portal_db';

try {
    // Connect to the database server
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create the central database if it does not exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Re-establish connection with database selected
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


    // Create 'members' database table with First/Last name division
    $db->exec("CREATE TABLE IF NOT EXISTS members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_no VARCHAR(50) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        father_husband_name VARCHAR(100) NOT NULL,
        dob DATE NOT NULL,
        gender VARCHAR(20) NOT NULL,
        mahallah VARCHAR(100) NOT NULL,
        address TEXT,
        phone VARCHAR(20) NOT NULL,
        blood_group VARCHAR(10),
        occupation VARCHAR(100),
        status VARCHAR(50) DEFAULT 'Active',
        deceased_date DATE NULL,
        chanda_status VARCHAR(50) DEFAULT 'Unpaid',
        photo LONGTEXT NULL,
        dependents_count INT DEFAULT 0,
        date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Create 'welfare' database table with Timestamp mapping
    $db->exec("CREATE TABLE IF NOT EXISTS welfare (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(100) NOT NULL,
        amount INT NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Create separate 'nikah_registry' table with DATETIME support
    $db->exec("CREATE TABLE IF NOT EXISTS nikah_registry (
        id INT AUTO_INCREMENT PRIMARY KEY,
        groom_name VARCHAR(150) NOT NULL,
        bride_name VARCHAR(150) NOT NULL,
        nikah_datetime DATETIME NOT NULL,
        details TEXT NOT NULL,
        date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Create separate 'burial_registry' table with DATETIME support
    $db->exec("CREATE TABLE IF NOT EXISTS burial_registry (
        id INT AUTO_INCREMENT PRIMARY KEY,
        deceased_id INT NULL,
        deceased_name VARCHAR(150) NOT NULL,
        burial_datetime DATETIME NOT NULL,
        plot_details VARCHAR(255) NOT NULL,
        date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    $count = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
    if ($count == 0) {
        // Populating clean Nagercoil demographics
        $db->exec("INSERT INTO members (card_no, first_name, last_name, father_husband_name, dob, gender, mahallah, address, phone, blood_group, occupation, status, deceased_date, chanda_status, dependents_count, photo) VALUES 
        ('K-104', 'Haja', 'Mydeen', 'Peer Mohammad', '1968-05-14', 'Male', 'Kottar', '12/4A, Mosque Street, Kottar, Nagercoil', '9843120543', 'O+', 'Merchant (Textiles)', 'Active', NULL, 'Paid', 4, 'https://placehold.co/150x150/0f766e/ffffff?text=Haja+Mydeen'),
        ('E-302', 'Sheik Mujibur', 'Rahman', 'Abdul Kadar', '1975-11-23', 'Male', 'Edalakudy', '45, Middle Street, Edalakudy, Nagercoil', '9443290111', 'B+', 'Business', 'Active', NULL, 'Unpaid', 3, 'https://placehold.co/150x150/0f766e/ffffff?text=Sheik+Mujibur'),
        ('EL-089', 'Aisha', 'Beevi', 'Late Rahmathullah', '1952-08-01', 'Female', 'Elankadai', '8, Thaikka Street, Elankadai, Nagercoil', '9894012345', 'A+', 'Homemaker', 'Active', NULL, 'Paid', 1, 'https://placehold.co/150x150/0f766e/ffffff?text=Aisha+Beevi'),
        ('M-055', 'Mohammad', 'Fazil', 'Samsudeen', '1992-12-12', 'Male', 'Meenakshipuram', '14/B, Railway Road, Meenakshipuram, Nagercoil', '7010254988', 'O-', 'Software Engineer', 'Active', NULL, 'Paid', 2, 'https://placehold.co/150x150/0f766e/ffffff?text=Fazil'),
        ('K-221', 'Shahul', 'Hameed', 'Mydeen Pillai', '1945-04-10', 'Male', 'Kottar', '88, Qabarstan Road, Kottar, Nagercoil', '9486012455', 'AB+', 'Retired Teacher', 'Deceased', '2025-08-14', 'Paid', 0, 'https://placehold.co/150x150/64748b/ffffff?text=Shahul+Hameed')");
        
        $db->exec("INSERT INTO welfare (name, type, amount, status) VALUES 
        ('Mohammad Fazil', 'Higher Education Aid', 15000, 'Approved'),
        ('Aisha Beevi', 'Marriage Assistance', 25000, 'Approved'),
        ('Sheik Mujibur Rahman', 'Medical Aid', 10000, 'Pending')");

        $db->exec("INSERT INTO nikah_registry (groom_name, bride_name, nikah_datetime, details) VALUES 
        ('Mohamed Anas', 'Shahana Fathima', '2026-05-10 11:30:00', 'Kottar Central Mosque. Registered Book #14, Page 44')");

        $db->exec("INSERT INTO burial_registry (deceased_name, burial_datetime, plot_details) VALUES 
        ('Shahul Hameed (Marhoom)', '2025-08-14 16:45:00', 'Kottar Graveyard, Block C, Row 4, Grave #12')");
    }

} catch (PDOException $e) {
    die("<div style='font-family: sans-serif; padding: 30px; max-width: 600px; margin: 50px auto; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
            <h3 style='margin: 0 0 10px 0; font-size: 20px;'>XAMPP Database Connection Error</h3>
            <p style='font-size: 14px; line-height: 1.5;'>Please make sure Apache and MySQL services are turned ON inside your XAMPP Control Panel.</p>
            <p style='font-size: 13px; font-weight: bold; margin-top: 15px;'>Technical Error Code:</p>
            <code style='display: block; background: #fff; padding: 10px; border-radius: 6px; font-size: 12px; word-break: break-all; border: 1px solid #f5c6cb;'>" . htmlspecialchars($e->getMessage()) . "</code>
         </div>");
}
?>