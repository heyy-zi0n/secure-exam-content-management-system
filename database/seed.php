<?php
/**
 * FCIT Exam CMS - Seeder Script
 */

require_once __DIR__ . '/../config/database.php';

echo "<pre>\n=== FCIT Exam CMS Seeder ===\n\n";

try {
    $db = Database::getInstance();

    $defaultPassword = password_hash('Password123!', PASSWORD_DEFAULT);

    $testUsers = [
        [
            'staff_id'        => 'FCIT/ADM/001',
            'full_name'       => 'Dr. System Administrator',
            'email'           => 'admin@lasu.edu.ng',
            'password'        => $defaultPassword,
            'role'            => 'admin',
            'department_code' => 'CSC'
        ],
        [
            'staff_id'        => 'FCIT/HOD/CSC',
            'full_name'       => 'Prof. A. O. Computer',
            'email'           => 'csc.hod@lasu.edu.ng',
            'password'        => $defaultPassword,
            'role'            => 'hod',
            'department_code' => 'CSC'
        ],
        [
            'staff_id'        => 'FCIT/OFF/001',
            'full_name'       => 'Mr. Exam Officer',
            'email'           => 'exam.officer@lasu.edu.ng',
            'password'        => $defaultPassword,
            'role'            => 'exam_officer',
            'department_code' => 'CSC'
        ],
        [
            'staff_id'        => 'FCIT/LEC/101',
            'full_name'       => 'Dr. J. Doe (Lecturer)',
            'email'           => 'lecturer@lasu.edu.ng',
            'password'        => $defaultPassword,
            'role'            => 'lecturer',
            'department_code' => 'CSC'
        ],
        [
            'staff_id'        => 'FCIT/MOD/201',
            'full_name'       => 'Prof. Peer Moderator',
            'email'           => 'moderator@lasu.edu.ng',
            'password'        => $defaultPassword,
            'role'            => 'moderator',
            'department_code' => 'CSC'
        ]
    ];

    $stmt = $db->prepare("
        INSERT INTO users (staff_id, full_name, email, password, role, department_code) 
        VALUES (:staff_id, :full_name, :email, :password, :role, :department_code)
        ON DUPLICATE KEY UPDATE 
            full_name = VALUES(full_name),
            password = VALUES(password),
            role = VALUES(role),
            department_code = VALUES(department_code)
    ");

    foreach ($testUsers as $user) {
        $stmt->execute($user);
        echo "[+] Seeding Account: {$user['staff_id']} ({$user['role']})\n";
    }

    echo "\n=== Seeding Completed Successfully! ===\n";
    echo "Default Password for all accounts: Password123!\n";

} catch (Exception $e) {
    echo "[-] Seeding Error: " . $e->getMessage() . "\n";
}

echo "</pre>";