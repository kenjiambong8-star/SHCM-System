<?php
/**
 * One-time setup: creates missing SHCM tables using a MySQL account that can CREATE
 * (usually root — the same login you use in MySQL Workbench).
 */
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$done = false;
$errors = [];
$created = [];

function tables_ok(PDO $pdo): bool {
    foreach (['students', 'requirements', 'health_records'] as $t) {
        if (!$pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn()) return false;
    }
    return true;
}

// Already set up with the app user?
try {
    $app = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=shcm_system;charset=utf8mb4',
        'shcm_user',
        'Shcm2026Secure',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    if (tables_ok($app)) {
        header('Location: /pages/dashboard.php');
        exit;
    }
} catch (Throwable $e) {
    $errors[] = 'App database login failed: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? 'root');
    $pass = (string)($_POST['password'] ?? '');
    if ($user === '') $errors[] = 'Enter a MySQL username (usually root).';

    if (!$errors) {
        try {
            $admin = new PDO(
                'mysql:host=127.0.0.1;port=3306;dbname=shcm_system;charset=utf8mb4',
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $admin->exec("CREATE TABLE IF NOT EXISTS requirements (
              id INT AUTO_INCREMENT PRIMARY KEY,
              name VARCHAR(100) NOT NULL UNIQUE,
              description VARCHAR(255) NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $created[] = 'requirements';

            $admin->exec("CREATE TABLE IF NOT EXISTS students (
              id INT AUTO_INCREMENT PRIMARY KEY,
              student_no VARCHAR(30) NOT NULL UNIQUE,
              first_name VARCHAR(60) NOT NULL,
              last_name VARCHAR(60) NOT NULL,
              course VARCHAR(50) NOT NULL,
              year_level TINYINT NOT NULL,
              sex ENUM('Male','Female') NULL,
              birthdate DATE NULL,
              contact VARCHAR(30) NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $created[] = 'students';

            $admin->exec("CREATE TABLE IF NOT EXISTS health_records (
              id INT AUTO_INCREMENT PRIMARY KEY,
              student_id INT NOT NULL,
              requirement_id INT NOT NULL,
              status ENUM('Compliant','Pending','Non-compliant') NOT NULL DEFAULT 'Pending',
              checkup_date DATE NULL,
              valid_until DATE NULL,
              ref_no VARCHAR(50) NULL,
              notes VARCHAR(255) NULL,
              recorded_by INT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT fk_hr_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
              CONSTRAINT fk_hr_requirement FOREIGN KEY (requirement_id) REFERENCES requirements(id),
              CONSTRAINT fk_hr_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
              INDEX idx_hr_student (student_id),
              INDEX idx_hr_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $created[] = 'health_records';

            $admin->exec("INSERT IGNORE INTO requirements (id, name, description) VALUES
              (1, 'Medical certificate', 'Valid school medical certificate'),
              (2, 'Chest X-ray', 'Chest X-ray clearance'),
              (3, 'Dental clearance', 'Dental examination clearance'),
              (4, 'Vaccination record', 'Updated immunization record'),
              (5, 'Physical exam', 'Annual physical examination')");

            try {
                $admin->exec("GRANT CREATE, ALTER, INDEX, DROP, REFERENCES ON shcm_system.* TO 'shcm_user'@'localhost'");
                $admin->exec('FLUSH PRIVILEGES');
            } catch (Throwable $e) {
                // Non-fatal if grants are not allowed
            }

            if (!tables_ok($admin)) {
                $errors[] = 'Tables were not created. Check that this MySQL user has CREATE privilege.';
            } else {
                $done = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'Could not create tables: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SHCM setup</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="panel" style="min-height:100vh">
  <div class="box">
    <p class="brand" style="color:#0F4D3D;margin:0 0 8px">SHCM System</p>
    <h2>Database setup</h2>
    <p class="muted">Your app login works, but the student / health tables are missing.
      Enter the same MySQL user you use in <strong>MySQL Workbench</strong> (usually <code>root</code>).</p>

    <?php if ($done): ?>
      <div class="ok">
        <p>Tables created: <?= htmlspecialchars(implode(', ', $created)) ?>.</p>
        <p>You can delete <code>setup.php</code> after this.</p>
      </div>
      <p><a class="btn" style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none;width:auto;padding:0 24px" href="/pages/dashboard.php">Open dashboard</a></p>
    <?php else: ?>
      <?php if ($errors): ?><div class="err"><?php foreach ($errors as $m) echo '<p>' . htmlspecialchars($m) . '</p>'; ?></div><?php endif; ?>
      <form method="post">
        <div class="f">
          <label for="user">MySQL username</label>
          <input id="user" name="user" value="<?= htmlspecialchars($_POST['user'] ?? 'root') ?>" required>
        </div>
        <div class="f">
          <label for="password">MySQL password</label>
          <input id="password" type="password" name="password" value="" placeholder="Workbench root password" autofocus>
        </div>
        <button class="btn" type="submit">Create tables</button>
      </form>
      <p class="muted" style="margin-top:18px">This only creates <code>students</code>, <code>requirements</code>, and <code>health_records</code> inside <code>shcm_system</code>. Your existing users stay intact.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
