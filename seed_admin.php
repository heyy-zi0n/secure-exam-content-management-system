<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance();

    // Password hash for: Password123!
    $hashedPassword = password_hash('Password123!', PASSWORD_BCRYPT);

    $stmt = $db->prepare("
        INSERT INTO users (staff_id, full_name, email, password, role, department_code, status)
        VALUES (:staff_id, :full_name, :email, :password, :role, :department_code, :status)
        ON DUPLICATE KEY UPDATE 
            password = VALUES(password),
            department_code = VALUES(department_code),
            status = 'active'
    ");

    $stmt->execute([
        ':staff_id'        => 'FCIT/ADM/001',
        ':full_name'       => 'System Administrator',
        ':email'           => 'admin@lasu.edu.ng',
        ':password'        => $hashedPassword,
        ':role'            => 'admin',
        ':department_code' => 'CSC', // Matches new department code
        ':status'          => 'active'
    ]);

    echo "<h2 style='color:green;'>Admin Account Seeded Successfully!</h2>";
    echo "<p><b>Email:</b> admin@lasu.edu.ng<br>";
    echo "<b>Staff ID:</b> FCIT/ADM/001<br>";
    echo "<b>Password:</b> Password123!</p>";
    echo "<p><a href='auth/login.php'>Go to Login Page</a></p>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>Error Seeding Database:</h2> " . $e->getMessage();
}