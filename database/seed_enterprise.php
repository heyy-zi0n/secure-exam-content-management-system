<?php
require_once __DIR__ . '/../config/database.php';

echo "<pre>\n=== Seeding Enterprise DB ===\n\n";

try {
    $db = Database::getInstance();
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Truncate all tables to avoid duplicates
    $tables = [
        'paper_files',
        'paper_versions',
        'examination_papers',
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
    
    // --- 10. Seed Examination Papers ---
    $lecCscId = $userIdMap['FCIT/LEC/CSC'];
    $lecSweId = $userIdMap['FCIT/LEC/SWE'];
    
    // Get dept ids
    $deptIds = [];
    $deptStmt = $db->query("SELECT id, code FROM departments");
    foreach ($deptStmt->fetchAll() as $d) {
        $deptIds[$d['code']] = $d['id'];
    }
    
    // Get level ids (by name)
    $levelIdsByName = [];
    $lvlStmt = $db->query("SELECT id, level_code FROM levels");
    foreach ($lvlStmt->fetchAll() as $l) {
        $levelIdsByName[$l['level_code']] = $l['id'];
    }
    
    $paperStmt = $db->prepare("
        INSERT INTO examination_papers 
        (course_id, lecturer_id, academic_session_id, semester_id, department_id, level_id, 
         examination_type, paper_title, instructions, duration_minutes, total_marks, 
         submission_status, current_version) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Helper: get course details by code
    $courseDetailsStmt = $db->prepare("
        SELECT c.id, c.department_id, c.level_id, c.semester_id, c.academic_session_id
        FROM courses c WHERE c.course_code = ? LIMIT 1
    ");
    
    function seedPaper($paperStmt, $courseDetailsStmt, $courseCode, $lecturerId, $examType, $title, $instructions, $duration, $marks, $status, $version, $deptIds, $levelIdsByName) {
        $courseDetailsStmt->execute([$courseCode]);
        $c = $courseDetailsStmt->fetch();
        if (!$c) return;
        $paperStmt->execute([
            $c['id'],
            $lecturerId,
            $c['academic_session_id'],
            $c['semester_id'],
            $c['department_id'],
            $c['level_id'],
            $examType,
            $title,
            $instructions,
            $duration,
            $marks,
            $status,
            $version
        ]);
    }
    
    // CSC Lecturer papers
    seedPaper(
        $paperStmt, $courseDetailsStmt, 'CSC101', $lecCscId,
        'Final Examination',
        'CSC101 Final Examination - First Semester 2025/2026',
        "Instructions:\n1. Answer ALL questions in Section A and any TWO (2) questions in Section B.\n2. Use of calculators is permitted.\n3. All questions carry equal marks unless otherwise stated.\n4. Write your matriculation number on every answer sheet provided.",
        180, 100, 'Draft', 1, $deptIds, $levelIdsByName
    );
    echo "[+] Seeded paper: CSC101 Draft\n";
    
    seedPaper(
        $paperStmt, $courseDetailsStmt, 'CSC221', $lecCscId,
        'Mid Semester Test',
        'CSC221 Mid-Semester Test - Introduction to AI',
        "Instructions:\n1. Answer ALL questions.\n2. Time allowed: 1 hour.\n3. Write legibly and use black or blue ink only.",
        60, 30, 'Submitted', 1, $deptIds, $levelIdsByName
    );
    echo "[+] Seeded paper: CSC221 Submitted\n";
    
    seedPaper(
        $paperStmt, $courseDetailsStmt, 'CSC410', $lecCscId,
        'Final Examination',
        'CSC410 Compiler Construction - Final Exam',
        "Instructions:\n1. Answer Question 1 (COMPULSORY) and any other THREE questions.\n2. Each question in Section A carries 25 marks.\n3. Show all workings clearly.",
        180, 100, 'Returned', 2, $deptIds, $levelIdsByName
    );
    echo "[+] Seeded paper: CSC410 Returned\n";
    
    seedPaper(
        $paperStmt, $courseDetailsStmt, 'SWE203', $lecCscId,
        'Continuous Assessment',
        'SWE203 Continuous Assessment II - Requirements Engineering',
        "Instructions:\n1. This is an open-book assessment.\n2. Submit your answers as a single PDF document.\n3. Plagiarism will be severely penalized.",
        120, 40, 'Draft', 1, $deptIds, $levelIdsByName
    );
    echo "[+] Seeded paper: SWE203 (CSC Lecturer) Draft\n";
    
    // SWE Lecturer papers
    seedPaper(
        $paperStmt, $courseDetailsStmt, 'SWE203', $lecSweId,
        'Final Examination',
        'SWE203 Final Examination - Software Requirements Engineering',
        "Instructions:\n1. Section A is compulsory (40 marks).\n2. Answer any THREE questions from Section B (20 marks each).\n3. Total: 100 marks. Time: 3 hours.",
        180, 100, 'Approved', 2, $deptIds, $levelIdsByName
    );
    echo "[+] Seeded paper: SWE203 (SWE Lecturer) Approved\n";
    
    seedPaper(
        $paperStmt, $courseDetailsStmt, 'SWE312', $lecSweId,
        'Practical',
        'SWE312 Practical Test - Software Architecture & Design',
        "Instructions:\n1. This practical test consists of 2 tasks.\n2. Complete all tasks within the given time.\n3. Submit your design diagrams and source code via the provided portal.",
        90, 50, 'Submitted', 1, $deptIds, $levelIdsByName
    );
    echo "[+] Seeded paper: SWE312 Practical Submitted\n";

    // --- 11. Seed Paper Versions and Paper Files (v0.7.1) ---
    require_once __DIR__ . '/../config/constants.php';

    $storageDirs = [
        EXAM_STORAGE_PATH_DRAFT, EXAM_STORAGE_PATH_SUBMIT, EXAM_STORAGE_PATH_APPRV, EXAM_STORAGE_PATH_ARCH
    ];
    foreach ($storageDirs as $d) {
        if (!is_dir($d)) @mkdir($d, 0777, true);
    }
    if (!file_exists(EXAM_STORAGE_PATH . '/.htaccess')) {
        @file_put_contents(EXAM_STORAGE_PATH . '/.htaccess', "Require all denied\nOptions -Indexes\n");
    }

    $versionStmt = $db->prepare("
        INSERT INTO paper_versions
        (examination_paper_id, version_number, created_by, submission_status, change_notes, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $fileStmt = $db->prepare("
        INSERT INTO paper_files
        (paper_version_id, file_type, original_filename, generated_filename, file_extension,
         mime_type, file_size, sha256_hash, storage_path, uploaded_by, uploaded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    function seedDummyFile(string $bucketDir, string $genName, string $ext): array {
        $path = rtrim($bucketDir, '/') . '/' . $genName;
        if ($ext === 'pdf') {
            // Minimal valid PDF 91 bytes
            $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n9\n%%EOF\n";
            $mime = 'application/pdf';
        } elseif ($ext === 'docx' || $ext === 'zip') {
            // Minimal valid ZIP 22 bytes (PK header + empty EOCD)
            $content = "PK\x03\x04" . str_repeat("\x00", 14) . "PK\x05\x06" . str_repeat("\x00", 18);
            $mime = $ext === 'docx'
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/zip';
        } else {
            $content = "Placeholder\n";
            $mime = 'application/octet-stream';
        }
        @file_put_contents($path, $content);
        @chmod($path, 0640);
        return [
            'size'    => filesize($path) ?: strlen($content),
            'sha256'  => hash('sha256', $content),
            'mime'    => $mime,
            'path'    => $path
        ];
    }

    $map = [
        // [paper_id, paper_title, status, version, lecturer_id, course_code, exam_type, session, semester]
        [1,  'CSC101 Final Examination',       'Draft',     1, $lecCscId, 'CSC101', 'Final Examination',       '2025/2026', 'First'],
        [2,  'CSC221 Mid-Semester Test',       'Submitted', 1, $lecCscId, 'CSC221', 'Mid Semester Test',       '2025/2026', 'First'],
        [3,  'CSC410 Compiler Construction',   'Returned',  1, $lecCscId, 'CSC410', 'Final Examination',       '2025/2026', 'First'],
        [3,  'CSC410 Compiler Construction',   'Returned',  2, $lecCscId, 'CSC410', 'Final Examination',       '2025/2026', 'First'],
        [4,  'SWE203 Continuous Assessment',   'Draft',     1, $lecCscId, 'SWE203', 'Continuous Assessment',   '2025/2026', 'First'],
        [5,  'SWE203 Software Requirements',   'Approved',  1, $lecSweId, 'SWE203', 'Final Examination',       '2025/2026', 'First'],
        [5,  'SWE203 Software Requirements',   'Approved',  2, $lecSweId, 'SWE203', 'Final Examination',       '2025/2026', 'First'],
        [6,  'SWE312 Practical Test',          'Submitted', 1, $lecSweId, 'SWE312', 'Practical',               '2025/2026', 'First'],
    ];

    $statusNotes = [
        1 => null,
        2 => 'Corrections applied per review feedback; restructured Q1-Q3.',
        3 => 'Initial draft prior to moderation review.',
        4 => null,
    ];

    $examToSlug = [
        'Mid Semester Test'     => 'MidSemesterTest',
        'Continuous Assessment' => 'ContinuousAssessment',
        'Practical'             => 'Practical',
        'Final Examination'     => 'FinalExam',
        'Supplementary Examination' => 'SupplementaryExam',
    ];
    $typeToAbbr = [
        'Question Paper'            => 'QUESTION',
        'Marking Scheme'            => 'MARKING',
        'Practical Resources'       => 'PRACTICAL',
        'Additional Instructions'   => 'INSTRUCTIONS',
    ];

    foreach ($map as $row) {
        [$paperId, $paperTitle, $status, $version, $lecturerId, $courseCode, $examType, $session, $semester] = $row;

        $changeNotes = $version > 1 ? ($statusNotes[$paperId] ?? null) : null;
        $versionStmt->execute([$paperId, $version, $lecturerId, $status, $changeNotes]);
        $versionId = (int)$db->lastInsertId();

        $bucket = $status === 'Submitted' ? EXAM_STORAGE_PATH_SUBMIT
                : ($status === 'Approved' ? EXAM_STORAGE_PATH_APPRV
                : EXAM_STORAGE_PATH_DRAFT);

        $examSlug = $examToSlug[$examType] ?? 'Exam';
        $semSlug  = $semester . 'Semester';
        $sesSlug  = str_replace('/', '-', $session);

        $filesForPaper = [];
        if ($examType === 'Practical') {
            $filesForPaper[] = ['Question Paper', 'docx'];
            $filesForPaper[] = ['Practical Resources', 'zip'];
        } elseif ($examType === 'Continuous Assessment') {
            $filesForPaper[] = ['Question Paper', 'pdf'];
            $filesForPaper[] = ['Additional Instructions', 'docx'];
        } else {
            $filesForPaper[] = ['Question Paper', 'docx'];
            if ($version === 2 || in_array($status, ['Submitted','Approved','Returned'], true)) {
                $filesForPaper[] = ['Marking Scheme', 'pdf'];
            }
        }

        foreach ($filesForPaper as $fp) {
            [$fileType, $ext] = $fp;
            $abbr = $typeToAbbr[$fileType];
            $gen  = "{$courseCode}_{$sesSlug}_{$semSlug}_{$examSlug}_V{$version}_{$abbr}.{$ext}";
            $info = seedDummyFile($bucket, $gen, $ext);
            $orig = $fileType . ' - ' . $courseCode . ' v' . $version . '.' . $ext;
            $fileStmt->execute([
                $versionId, $fileType, $orig, $gen, $ext,
                $info['mime'], $info['size'], $info['sha256'], $info['path'], $lecturerId
            ]);
        }
        echo "[+] Seeded paper_version #$versionId (paper #$paperId v$version $status) with "
             . count($filesForPaper) . " file(s) -> bucket $bucket\n";
    }

    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n=== Seeded successfully! ===\n";
    echo "All users have password: Password123!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
