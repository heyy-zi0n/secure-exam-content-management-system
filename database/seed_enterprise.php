<?php
require_once __DIR__ . '/../config/database.php';

echo "<pre>\n=== Seeding Enterprise DB ===\n\n";

try {
    $db = Database::getInstance();
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Truncate all tables to avoid duplicates
    $tables = [
        'moderator_assignments',
        'lecturer_course_assignments',
        'courses',
        'levels',
        'semesters',
        'academic_sessions',
        'users',
        'departments',
        'roles'
    ];
    foreach ($tables as $t) {
        $db->exec("TRUNCATE TABLE $t");
        echo "[*] Truncated table: $t\n";
    }
    
    // --- 1. Seed Roles ---
    $roles = [
        [
            'role_code' => 'admin',
            'role_name' => 'System Administrator',
            'description' => 'Full system management and configuration access'
        ],
        [
            'role_code' => 'hod',
            'role_name' => 'Head of Department',
            'description' => 'Departmental oversight, lecturer allocations, and moderation workflow approval'
        ],
        [
            'role_code' => 'lecturer',
            'role_name' => 'Lecturer',
            'description' => 'Uploads examination papers, marking guides, and revises papers based on feedback'
        ],
        [
            'role_code' => 'moderator',
            'role_name' => 'Exam Paper Moderator',
            'description' => 'Reviews and vets assigned examination papers by level and department'
        ],
        [
            'role_code' => 'exam_officer',
            'role_name' => 'Exam Officer',
            'description' => 'Manages examination schedules and secure print requests'
        ]
    ];
    $roleStmt = $db->prepare("
        INSERT INTO roles (role_code, role_name, description) 
        VALUES (?, ?, ?)
    ");
    foreach ($roles as $role) {
        $roleStmt->execute([
            $role['role_code'], 
            $role['role_name'], 
            $role['description']
        ]);
        echo "[+] Seeded role: {$role['role_code']}\n";
    }
    
    // --- 2. Seed Departments ---
    $departments = [
        ['CSC', 'Computer Science'],
        ['SWE', 'Software Engineering'],
        ['CYB', 'Cyber Security'],
        ['DAT', 'Data Science'],
        ['ICT', 'Information and Communication Technology']
    ];
    $deptStmt = $db->prepare("INSERT INTO departments (code, name) VALUES (?, ?)");
    foreach ($departments as $dept) {
        $deptStmt->execute($dept);
        echo "[+] Seeded department: {$dept[0]}\n";
    }
    
    // --- 3. Seed Academic Sessions ---
    $sessionStmt = $db->prepare("
        INSERT INTO academic_sessions (session_name, start_date, end_date, is_current) 
        VALUES (?, ?, ?, ?)
    ");
    $semStmt = $db->prepare("
        INSERT INTO semesters 
        (academic_session_id, semester_name, is_active, start_date, end_date) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $firstSessionId = null;
    $firstSessionFirstSemId = null; // Used for courses/assignments
    
    for ($i = 0; $i < 15; $i++) {
        $startYear = 2025 + $i;
        $endYear = 2026 + $i;
        $sessionName = "$startYear/$endYear";
        
        $startDate = "$startYear-10-01";
        $endDate = "$endYear-07-31";
        $isCurrent = ($i === 0) ? 1 : 0; // Only 2025/2026 is active
        
        $sessionStmt->execute([$sessionName, $startDate, $endDate, $isCurrent]);
        $sessionId = $db->lastInsertId();
        
        if ($i === 0) {
            $firstSessionId = $sessionId;
        }
        
        echo "[+] Seeded academic session: $sessionName (id: $sessionId, active: " . ($isCurrent ? 'YES' : 'NO') . ")\n";
        
        // --- Add First and Second Semesters for each session ---
        // First Semester: Oct 1 to Feb 28/29 (approx)
        $firstSemStart = "$startYear-10-01";
        $firstSemEnd = "$endYear-02-28";
        $isFirstSemActive = ($i === 0) ? 1 : 0; // Only first session's first sem is active
        
        $semStmt->execute([$sessionId, 'First', $isFirstSemActive, $firstSemStart, $firstSemEnd]);
        $firstSemId = $db->lastInsertId();
        
        if ($i === 0) {
            $firstSessionFirstSemId = $firstSemId;
        }
        
        // Second Semester: Mar 15 to Jul 31 (approx)
        $secondSemStart = "$endYear-03-15";
        $secondSemEnd = "$endYear-07-31";
        
        $semStmt->execute([$sessionId, 'Second', 0, $secondSemStart, $secondSemEnd]);
        $secondSemId = $db->lastInsertId();
        
        echo "    → First Semester (id: $firstSemId, active: $isFirstSemActive) | Second Semester (id: $secondSemId)\n";
    }
    
    // --- 5. Seed Levels ---
    $levelStmt = $db->prepare("
        INSERT INTO levels (level_code, level_name, description) 
        VALUES (?, ?, ?)
    ");
    $levelIds = [];
    $levelDefs = [
        ['100', '100 Level', 'First Year Undergraduate'],
        ['200', '200 Level', 'Second Year Undergraduate'],
        ['300', '300 Level', 'Third Year Undergraduate'],
        ['400', '400 Level', 'Fourth Year Undergraduate / Final Year']
    ];
    foreach ($levelDefs as $def) {
        $levelStmt->execute($def);
        $levelIds[$def[0]] = $db->lastInsertId();
        echo "[+] Seeded level: {$def[0]}\n";
    }
    
    // --- 6. Seed Users ---
    $defaultPassword = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
    
    $users = [];
    
    // Admin
    $users[] = [
        'staff_id' => 'FCIT/ADM/001',
        'first_name' => 'System',
        'last_name' => 'Administrator',
        'email' => 'admin@lasu.edu.ng',
        'password_hash' => $defaultPassword,
        'department_id' => 1, // CSC
        'role_id' => 1 // admin
    ];
    
    // Per-department users
    foreach ($departments as $idx => $dept) {
        $deptId = $idx + 1;
        $lcCode = strtolower($dept[0]);
        
        $users[] = [
            'staff_id' => "FCIT/HOD/{$dept[0]}",
            'first_name' => 'Prof.',
            'middle_name' => "HOD",
            'last_name' => $dept[0],
            'email' => "$lcCode.hod@lasu.edu.ng",
            'password_hash' => $defaultPassword,
            'department_id' => $deptId,
            'role_id' => 2 // hod
        ];
        
        $users[] = [
            'staff_id' => "FCIT/EO/{$dept[0]}",
            'first_name' => 'Mr.',
            'last_name' => "Exam Officer {$dept[0]}",
            'email' => "$lcCode.officer@lasu.edu.ng",
            'password_hash' => $defaultPassword,
            'department_id' => $deptId,
            'role_id' => 5 // exam_officer
        ];
        
        $users[] = [
            'staff_id' => "FCIT/LEC/{$dept[0]}",
            'first_name' => 'Dr.',
            'last_name' => "Lecturer {$dept[0]}",
            'email' => "$lcCode.lecturer@lasu.edu.ng",
            'password_hash' => $defaultPassword,
            'department_id' => $deptId,
            'role_id' => 3 // lecturer
        ];
        
        $users[] = [
            'staff_id' => "FCIT/MOD/{$dept[0]}",
            'first_name' => 'Prof.',
            'last_name' => "Moderator {$dept[0]}",
            'email' => "$lcCode.moderator@lasu.edu.ng",
            'password_hash' => $defaultPassword,
            'department_id' => $deptId,
            'role_id' => 4 // moderator
        ];
    }
    
    $userStmt = $db->prepare("
        INSERT INTO users 
        (staff_id, first_name, middle_name, last_name, email, password_hash, department_id, role_id, account_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
    ");
    $userIdMap = []; // staff_id -> id
    foreach ($users as $user) {
        $userStmt->execute([
            $user['staff_id'],
            $user['first_name'],
            $user['middle_name'] ?? null,
            $user['last_name'],
            $user['email'],
            $user['password_hash'],
            $user['department_id'],
            $user['role_id']
        ]);
        $userIdMap[$user['staff_id']] = $db->lastInsertId();
        echo "[+] Seeded user: {$user['email']}\n";
    }
    
    // --- 7. Seed Courses ---
    $courses = [
        [
            'dept_code' => 'CSC', 
            'course_code' => 'CSC101', 
            'course_title' => 'Introduction to Computer Science', 
            'level' => '100', 
            'unit' => 3,
            'description' => 'Foundational course for first year computer science students'
        ],
        [
            'dept_code' => 'CSC', 
            'course_code' => 'CSC221', 
            'course_title' => 'Introduction to Artificial Intelligence', 
            'level' => '300', 
            'unit' => 3,
            'description' => 'Intro to AI concepts and algorithms'
        ],
        [
            'dept_code' => 'CSC', 
            'course_code' => 'CSC410', 
            'course_title' => 'Compiler Construction', 
            'level' => '400', 
            'unit' => 3,
            'description' => 'Design and implementation of compilers'
        ],
        
        [
            'dept_code' => 'SWE', 
            'course_code' => 'SWE203', 
            'course_title' => 'Software Requirements Engineering', 
            'level' => '200', 
            'unit' => 3,
            'description' => 'Software requirements gathering and documentation'
        ],
        [
            'dept_code' => 'SWE', 
            'course_code' => 'SWE312', 
            'course_title' => 'Software Architecture & Design', 
            'level' => '300', 
            'unit' => 3,
            'description' => 'Software architecture patterns and design principles'
        ],
        
        [
            'dept_code' => 'CYB', 
            'course_code' => 'CYB301', 
            'course_title' => 'Introduction to Cryptography', 
            'level' => '300', 
            'unit' => 3,
            'description' => 'Foundations of modern cryptography'
        ],
        [
            'dept_code' => 'CYB', 
            'course_code' => 'CYB402', 
            'course_title' => 'Penetration Testing & Vulnerability Analysis', 
            'level' => '400', 
            'unit' => 3,
            'description' => 'Ethical hacking and vulnerability assessment'
        ],
        
        [
            'dept_code' => 'DAT', 
            'course_code' => 'DAT210', 
            'course_title' => 'Data Wrangling & Visualization', 
            'level' => '200', 
            'unit' => 2,
            'description' => 'Data cleaning, preparation, and visualization'
        ],
        [
            'dept_code' => 'DAT', 
            'course_code' => 'DAT302', 
            'course_title' => 'Statistical Machine Learning', 
            'level' => '300', 
            'unit' => 3,
            'description' => 'Statistical models and machine learning algorithms'
        ],
        
        [
            'dept_code' => 'ICT', 
            'course_code' => 'ICT102', 
            'course_title' => 'Introduction to Information Systems', 
            'level' => '100', 
            'unit' => 2,
            'description' => 'Introduction to information systems and their applications'
        ],
        [
            'dept_code' => 'ICT', 
            'course_code' => 'ICT408', 
            'course_title' => 'Telecommunication & Networks', 
            'level' => '400', 
            'unit' => 3,
            'description' => 'Networking concepts and telecommunication systems'
        ]
    ];
    
    $courseStmt = $db->prepare("
        INSERT INTO courses 
        (department_id, course_code, course_title, level_id, semester_id, academic_session_id, course_unit, status, description) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?)
    ");
    $courseIdMap = [];
    foreach ($courses as $course) {
        $deptId = array_search($course['dept_code'], array_column($departments, 0)) + 1;
        $levelId = $levelIds[$course['level']];
        
        $courseStmt->execute([
            $deptId, 
            $course['course_code'], 
            $course['course_title'], 
            $levelId, 
            $firstSessionFirstSemId, 
            $firstSessionId, 
            $course['unit'], 
            $course['description']
        ]);
        $courseIdMap[$course['course_code']] = $db->lastInsertId();
        echo "[+] Seeded course: {$course['course_code']}\n";
    }
    
    // --- 8. Seed Lecturer Course Assignments ---
    $lecCscId = $userIdMap['FCIT/LEC/CSC'];
    $lecSweId = $userIdMap['FCIT/LEC/SWE'];
    $hodCscId = $userIdMap['FCIT/HOD/CSC'];
    $hodSweId = $userIdMap['FCIT/HOD/SWE'];
    
    $assignStmt = $db->prepare("
        INSERT INTO lecturer_course_assignments 
        (lecturer_id, course_id, academic_session_id, assignment_status, assigned_by) 
        VALUES (?, ?, ?, 'Active', ?)
    ");
    
    // Assign CSC101, CSC221, CSC410 to CSC lecturer
    $assignStmt->execute([$lecCscId, $courseIdMap['CSC101'], $firstSessionId, $hodCscId]);
    $assignStmt->execute([$lecCscId, $courseIdMap['CSC221'], $firstSessionId, $hodCscId]);
    $assignStmt->execute([$lecCscId, $courseIdMap['CSC410'], $firstSessionId, $hodCscId]);
    
    // Assign SWE203, SWE312 to SWE lecturer
    $assignStmt->execute([$lecSweId, $courseIdMap['SWE203'], $firstSessionId, $hodSweId]);
    $assignStmt->execute([$lecSweId, $courseIdMap['SWE312'], $firstSessionId, $hodSweId]);
    
    // Assign SWE203 to CSC lecturer (cross-department, approved by SWE HOD)
    $assignStmt->execute([$lecCscId, $courseIdMap['SWE203'], $firstSessionId, $hodSweId]);
    
    echo "[+] Seeded lecturer course assignments!\n";
    
    // --- 9. Seed Moderator Assignments ---
    $modCscId = $userIdMap['FCIT/MOD/CSC'];
    $modSweId = $userIdMap['FCIT/MOD/SWE'];
    $modCybId = $userIdMap['FCIT/MOD/CYB'];
    
    $modAssignStmt = $db->prepare("
        INSERT INTO moderator_assignments 
        (moderator_id, course_id, academic_session_id, department_id, level_id, assignment_status, assigned_by) 
        VALUES (?, ?, ?, ?, ?, 'Active', ?)
    ");
    
    // Assign CSC moderator to all CSC courses
    $modAssignStmt->execute([$modCscId, $courseIdMap['CSC101'], $firstSessionId, 1, $levelIds['100'], $hodCscId]);
    $modAssignStmt->execute([$modCscId, $courseIdMap['CSC221'], $firstSessionId, 1, $levelIds['300'], $hodCscId]);
    $modAssignStmt->execute([$modCscId, $courseIdMap['CSC410'], $firstSessionId, 1, $levelIds['400'], $hodCscId]);
    
    // Assign SWE moderator to all SWE courses
    $modAssignStmt->execute([$modSweId, $courseIdMap['SWE203'], $firstSessionId, 2, $levelIds['200'], $hodSweId]);
    $modAssignStmt->execute([$modSweId, $courseIdMap['SWE312'], $firstSessionId, 2, $levelIds['300'], $hodSweId]);
    
    // Assign CYB moderator to CYB301, CYB402
    $hodCybId = $userIdMap['FCIT/HOD/CYB'];
    $modAssignStmt->execute([$modCybId, $courseIdMap['CYB301'], $firstSessionId, 3, $levelIds['300'], $hodCybId]);
    $modAssignStmt->execute([$modCybId, $courseIdMap['CYB402'], $firstSessionId, 3, $levelIds['400'], $hodCybId]);
    
    echo "[+] Seeded moderator assignments!\n";
    
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n=== Seeded successfully! ===\n";
    echo "All users have password: Password123!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
