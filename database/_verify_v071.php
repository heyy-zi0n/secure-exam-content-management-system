<?php
/**
 * Standalone integrity verification for v0.7.1
 * Verifies: paper_versions, paper_files rows, UNIQUE keys, hashes, disk files, filenames
 */
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

echo PHP_EOL . "========== v0.7.1 INTEGRITY VERIFICATION ==========" . PHP_EOL . PHP_EOL;

$pass = 0; $fail = 0;
function check($label, $cond) { global $pass, $fail;
    $ok = (bool)$cond;
    echo ($ok ? "[PASS]" : "[FAIL]") . " {$label}" . PHP_EOL;
    if ($ok) $pass++; else $fail++;
    return $ok;
}

echo "--- TABLE EXISTENCE ---" . PHP_EOL;
$tbls = $db->query("SHOW TABLES LIKE 'paper_%'")->fetchAll(PDO::FETCH_COLUMN);
check("paper_versions table exists", in_array('paper_versions', $tbls));
check("paper_files table exists", in_array('paper_files', $tbls));

echo PHP_EOL . "--- ROW COUNTS ---" . PHP_EOL;
$pvCount = $db->query("SELECT COUNT(*) FROM paper_versions")->fetchColumn();
$pfCount = $db->query("SELECT COUNT(*) FROM paper_files")->fetchColumn();
check("paper_versions >= 8 (seeded)", $pvCount >= 8);
echo "    paper_versions row count = {$pvCount}" . PHP_EOL;
check("paper_files >= 15 (seeded)", $pfCount >= 15);
echo "    paper_files row count = {$pfCount}" . PHP_EOL;

echo PHP_EOL . "--- VERSIONING UNIQUENESS ---" . PHP_EOL;
$dupVer = $db->query("SELECT examination_paper_id, version_number, COUNT(*) c FROM paper_versions GROUP BY examination_paper_id, version_number HAVING c > 1")->rowCount();
check("No duplicate (paper, version) pairs", $dupVer === 0);

echo PHP_EOL . "--- FILE HASH LENGTH ---" . PHP_EOL;
$badHash = $db->query("SELECT COUNT(*) FROM paper_files WHERE CHAR_LENGTH(sha256_hash) <> 64")->fetchColumn();
check("All sha256_hash fields are exactly 64 chars", (int)$badHash === 0);

echo PHP_EOL . "--- DUPLICATE HASH PER (VERSION, TYPE) ---" . PHP_EOL;
$dupFile = $db->query("SELECT paper_version_id, file_type, sha256_hash, COUNT(*) c FROM paper_files GROUP BY paper_version_id, file_type, sha256_hash HAVING c > 1")->rowCount();
check("No duplicate (version, file_type, hash) rows", $dupFile === 0);

echo PHP_EOL . "--- ENTERPRISE FILENAME PATTERN ---" . PHP_EOL;
$stmt = $db->query("SELECT id, generated_filename, file_extension FROM paper_files");
$pat = '/^[A-Z0-9]+_\d{4}-\d{4}_\w+_\w+_V\d+_(QUESTION|MARKING|PRACTICAL|INSTRUCTIONS)\.(pdf|docx|zip)$/';
$badName = 0; $totalName = 0;
foreach ($stmt as $r) {
    $totalName++;
    if (!preg_match($pat, $r['generated_filename'])) { $badName++; echo "    BAD NAME id#{$r['id']}: {$r['generated_filename']}" . PHP_EOL; }
}
check("All {$totalName} generated filenames match enterprise pattern", $badName === 0);

echo PHP_EOL . "--- DISK FILE PRESENCE + HASH INTEGRITY ---" . PHP_EOL;
$projectRoot = realpath(__DIR__ . '/../');
$files = $db->query("SELECT id, paper_version_id, file_type, generated_filename, storage_path, sha256_hash, file_size FROM paper_files");
$missingDisk = 0; $hashMismatch = 0; $sizeMismatch = 0; $totalFiles = 0;
foreach ($files as $f) {
    $totalFiles++;
    $raw = $f['storage_path'];
    // If storage_path already absolute, use as-is; else resolve relative to project root
    if (preg_match('/^[A-Z]:[\\\\\/]/i', $raw) || str_starts_with($raw, '/')) {
        $full = $raw;
    } else {
        $rel = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), DIRECTORY_SEPARATOR);
        $full = $projectRoot . DIRECTORY_SEPARATOR . $rel;
    }
    $full = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full);
    if (!file_exists($full) || !is_readable($full)) { $missingDisk++; echo "    MISSING id#{$f['id']}: {$full}" . PHP_EOL; continue; }
    if (filesize($full) !== (int)$f['file_size']) { $sizeMismatch++; echo "    SIZE MISMATCH id#{$f['id']}: disk=" . filesize($full) . " db={$f['file_size']}" . PHP_EOL; }
    $diskHash = hash_file('sha256', $full);
    if (!hash_equals($f['sha256_hash'], $diskHash)) { $hashMismatch++; echo "    HASH MISMATCH id#{$f['id']}: disk=" . substr($diskHash,0,12) . "... db=" . substr($f['sha256_hash'],0,12) . "..." . PHP_EOL; }
}
check("All {$totalFiles} file storage_path exist on disk", $missingDisk === 0);
check("All file sizes on disk match DB file_size", $sizeMismatch === 0);
check("All SHA-256 on disk match DB fingerprints", $hashMismatch === 0);

echo PHP_EOL . "--- FK JOIN INTEGRITY (paper_files → paper_versions → examination_papers → users) ---" . PHP_EOL;
$orphanF = $db->query("SELECT COUNT(*) FROM paper_files pf LEFT JOIN paper_versions pv ON pv.id = pf.paper_version_id WHERE pv.id IS NULL")->fetchColumn();
check("No orphan paper_files (no matching paper_version)", (int)$orphanF === 0);
$orphanV = $db->query("SELECT COUNT(*) FROM paper_versions pv LEFT JOIN examination_papers ep ON ep.id = pv.examination_paper_id WHERE ep.id IS NULL")->fetchColumn();
check("No orphan paper_versions (no matching examination_paper)", (int)$orphanV === 0);
$orphanCreator = $db->query("SELECT COUNT(*) FROM paper_versions pv LEFT JOIN users u ON u.id = pv.created_by WHERE u.id IS NULL")->fetchColumn();
check("All paper_versions have valid created_by user", (int)$orphanCreator === 0);

echo PHP_EOL . "--- STATUS DISTRIBUTION (seeded) ---" . PHP_EOL;
$statusRows = $db->query("SELECT pv.submission_status, COUNT(DISTINCT pv.id) versions, COUNT(pf.id) files, ROUND(SUM(pf.file_size)/1024,1) kb
    FROM paper_versions pv LEFT JOIN paper_files pf ON pf.paper_version_id = pv.id
    GROUP BY pv.submission_status ORDER BY pv.submission_status")->fetchAll(PDO::FETCH_ASSOC);
foreach ($statusRows as $s) echo "    {$s['submission_status']}: versions={$s['versions']}, files={$s['files']}, size={$s['kb']} KB" . PHP_EOL;

echo PHP_EOL . "--- CURSOR-SPECIFIC CHECKS (CSC410 v2, SWE203 v2 multi-version) ---" . PHP_EOL;
$multis = $db->query("SELECT c.course_code, COUNT(DISTINCT pv.version_number) vcount FROM paper_versions pv
    JOIN examination_papers ep ON ep.id = pv.examination_paper_id
    JOIN courses c ON c.id = ep.course_id
    GROUP BY ep.id HAVING vcount > 1 ORDER BY c.course_code")->fetchAll(PDO::FETCH_ASSOC);
$multiCount = count($multis);
check("At least 2 multi-version papers seeded (CSC410 + SWE203)", $multiCount >= 2);
foreach ($multis as $m) echo "    {$m['course_code']}: {$m['vcount']} versions" . PHP_EOL;

echo PHP_EOL . "--- ALLOWED FORMATS + MIME CHECK ---" . PHP_EOL;
$badExt = $db->query("SELECT COUNT(*) FROM paper_files WHERE file_extension NOT IN ('pdf','docx','zip')")->fetchColumn();
check("All file_extension ∈ {pdf, docx, zip}", (int)$badExt === 0);
$mimeOk = $db->query("SELECT COUNT(*) FROM paper_files WHERE
    (file_extension = 'pdf'  AND mime_type LIKE '%pdf%') OR
    (file_extension = 'docx' AND (mime_type LIKE '%wordprocessingml%' OR mime_type LIKE '%zip%' OR mime_type LIKE '%officedocument%')) OR
    (file_extension = 'zip'  AND (mime_type LIKE '%zip%' OR mime_type LIKE '%octet-stream%'))")->fetchColumn();
check("All rows have MIME consistent with extension", (int)$mimeOk === $totalFiles);

echo PHP_EOL . "========== SUMMARY: PASS={$pass}  FAIL={$fail}  TOTAL=" . ($pass+$fail) . " ==========" . PHP_EOL;
exit($fail === 0 ? 0 : 1);
