<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sysarch_db');

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
    return $conn;
    $conn->query("CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL,
    student_name VARCHAR(200) NOT NULL,
    purpose VARCHAR(100) DEFAULT '',
    lab VARCHAR(50) DEFAULT '',
    reservation_date DATE,
    reservation_time TIME,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
}

function setupDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8 COLLATE utf8_general_ci");
    $conn->select_db(DB_NAME);

    // -------------------------------------------------------
    // ACCOUNTS TABLE
    // -------------------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS accounts (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        id_number          VARCHAR(50)  NOT NULL UNIQUE,
        last_name          VARCHAR(100) NOT NULL,
        first_name         VARCHAR(100) NOT NULL,
        middle_name        VARCHAR(100),
        course_level       INT          DEFAULT 1,
        email              VARCHAR(150) NOT NULL UNIQUE,
        password           VARCHAR(255) NOT NULL,
        course             VARCHAR(50)  DEFAULT 'BSIT',
        address            TEXT,
        profile_photo      VARCHAR(255) DEFAULT 'register.png',
        role               VARCHAR(20)  DEFAULT 'student',
        remaining_sessions INT          DEFAULT 30,
        created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )");

    // Safely add columns that may be missing in older installs
    $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) DEFAULT 'register.png'");
    $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'student'");
    $conn->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS remaining_sessions INT DEFAULT 30");

    // -------------------------------------------------------
    // ANNOUNCEMENTS TABLE
    // -------------------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS announcements (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        admin_name VARCHAR(100) DEFAULT 'CCS Admin',
        content    TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // -------------------------------------------------------
    // SIT-IN RECORDS TABLE
    // -------------------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS sit_in_records (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        id_number             VARCHAR(50)  NOT NULL,
        student_name          VARCHAR(200) NOT NULL,
        purpose               VARCHAR(100) DEFAULT '',
        lab                   VARCHAR(50)  DEFAULT '',
        session               INT          DEFAULT 0,
        status                VARCHAR(20)  DEFAULT 'active',
        reward_points         INT          DEFAULT 0,
        task_completed_points INT          DEFAULT 0,
        created_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        timeout_at            TIMESTAMP    NULL DEFAULT NULL
    )");
    $conn->query("ALTER TABLE sit_in_records ADD COLUMN IF NOT EXISTS reward_points INT DEFAULT 0");
    $conn->query("ALTER TABLE sit_in_records ADD COLUMN IF NOT EXISTS task_completed_points INT DEFAULT 0");
    $conn->query("ALTER TABLE sit_in_records ADD COLUMN IF NOT EXISTS timeout_at TIMESTAMP NULL DEFAULT NULL");

    // -------------------------------------------------------
    // RESERVATIONS TABLE
    // -------------------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_number VARCHAR(50) NOT NULL,
        student_name VARCHAR(200) NOT NULL,
        purpose VARCHAR(100) DEFAULT '',
        lab VARCHAR(50) DEFAULT '',
        reservation_date DATE,
        reservation_time TIME,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add missing columns if they don't exist
    $result = $conn->query("SHOW COLUMNS FROM reservations LIKE 'reservation_date'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN reservation_date DATE");
    }
    $result = $conn->query("SHOW COLUMNS FROM reservations LIKE 'reservation_time'");
    if ($result && $result->num_rows == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN reservation_time TIME");
    }

    // -------------------------------------------------------
    // DEFAULT ADMIN ACCOUNT
    // -------------------------------------------------------
    $adminEmail = 'admin';
    $adminId    = 'admin';
    $adminPass  = password_hash('admin123', PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM accounts WHERE id_number = ? OR email = ?");
    $check->bind_param('ss', $adminId, $adminEmail);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO accounts
            (id_number, last_name, first_name, middle_name, course_level, email, password, course, address, profile_photo, role, remaining_sessions)
            VALUES (?, 'Admin', 'Site', '', 0, ?, ?, '', '', 'register.png', 'admin', 0)");
        $insert->bind_param('sss', $adminId, $adminEmail, $adminPass);
        $insert->execute();
        $insert->close();
    }

    $check->close();
    $conn->close();
}
    

setupDatabase();
session_start();
?>