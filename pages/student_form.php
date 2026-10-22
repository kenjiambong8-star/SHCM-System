<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Student'; $active = 'students';

$id = (int)($_GET['id'] ?? 0);
$row = [
    'student_no' => '', 'first_name' => '', 'last_name' => '',
    'course' => '', 'year_level' => 1, 'sex' => '', 'birthdate' => '', 'contact' => '',
];
if ($id) {
    $s = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $s->execute([$id]);
    $found = $s->fetch();
    if (!$found) { http_response_code(404); exit('Student not found.'); }
    $row = $found;
    $pageTitle = 'Edit student';
} else {
    $pageTitle = 'Add student';
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $row = [
        'student_no' => trim($_POST['student_no'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name'] ?? ''),
        'course'     => trim($_POST['course'] ?? ''),
        'year_level' => (int)($_POST['year_level'] ?? 0),
        'sex'        => ($_POST['sex'] ?? '') === 'Female' ? 'Female' : ((($_POST['sex'] ?? '') === 'Male') ? 'Male' : ''),
        'birthdate'  => trim($_POST['birthdate'] ?? ''),
        'contact'    => trim($_POST['contact'] ?? ''),
    ];
    if ($row['student_no'] === '' || strlen($row['student_no']) > 30) $errors[] = 'Enter a student number (max 30 characters).';
    if ($row['first_name'] === '' || strlen($row['first_name']) > 60) $errors[] = 'Enter a first name.';
    if ($row['last_name'] === '' || strlen($row['last_name']) > 60) $errors[] = 'Enter a last name.';
    if ($row['course'] === '' || strlen($row['course']) > 50) $errors[] = 'Enter a course.';
    if ($row['year_level'] < 1 || $row['year_level'] > 5) $errors[] = 'Year level must be 1–5.';
    if ($row['birthdate'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['birthdate'])) $errors[] = 'Birthdate must be YYYY-MM-DD.';
    if ($row['birthdate'] === '') $row['birthdate'] = null;
    if ($row['sex'] === '') $row['sex'] = null;

    if (!$errors) {
        $dup = $pdo->prepare("SELECT id FROM students WHERE student_no = ? AND id <> ?");
        $dup->execute([$row['student_no'], $id]);
        if ($dup->fetch()) $errors[] = 'That student number is already registered.';
    }

    if (!$errors) {
        if ($id) {
            $pdo->prepare("UPDATE students SET student_no=?, first_name=?, last_name=?, course=?, year_level=?, sex=?, birthdate=?, contact=? WHERE id=?")
                ->execute([$row['student_no'], $row['first_name'], $row['last_name'], $row['course'], $row['year_level'], $row['sex'], $row['birthdate'], $row['contact'], $id]);
            log_activity('UPDATE_STUDENT', $row['student_no']);
            $_SESSION['flash'] = ['ok', 'Student updated.'];
            header('Location: /pages/student_view.php?id=' . $id);
        } else {
            $pdo->prepare("INSERT INTO students (student_no, first_name, last_name, course, year_level, sex, birthdate, contact) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$row['student_no'], $row['first_name'], $row['last_name'], $row['course'], $row['year_level'], $row['sex'], $row['birthdate'], $row['contact']]);
            $newId = (int)$pdo->lastInsertId();
            log_activity('ADD_STUDENT', $row['student_no']);
            $_SESSION['flash'] = ['ok', 'Student added.'];
            header('Location: /pages/student_view.php?id=' . $newId);
        }
        exit;
    }
}

include __DIR__ . '/../includes/layout_top.php';
?>
<div class="top">
  <h1><?= e($pageTitle) ?></h1>
  <a class="bs" href="/pages/students.php">Back</a>
</div>
<?php if ($errors): ?><div class="err"><?php foreach ($errors as $m) echo '<p>' . e($m) . '</p>'; ?></div><?php endif; ?>

<div class="card formcard">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="f"><label for="student_no">Student no.</label>
      <input id="student_no" name="student_no" value="<?= e($row['student_no']) ?>" required maxlength="30"></div>
    <div class="f"><label for="last_name">Last name</label>
      <input id="last_name" name="last_name" value="<?= e($row['last_name']) ?>" required maxlength="60"></div>
    <div class="f"><label for="first_name">First name</label>
      <input id="first_name" name="first_name" value="<?= e($row['first_name']) ?>" required maxlength="60"></div>
    <div class="f"><label for="course">Course</label>
      <input id="course" name="course" value="<?= e($row['course']) ?>" required maxlength="50" placeholder="e.g. BSIT"></div>
    <div class="f"><label for="year_level">Year level</label>
      <select id="year_level" name="year_level" class="sel" style="width:100%;height:46px">
        <?php for ($y = 1; $y <= 5; $y++): ?>
          <option value="<?= $y ?>" <?= (int)$row['year_level'] === $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select></div>
    <div class="f"><label for="sex">Sex</label>
      <select id="sex" name="sex" class="sel" style="width:100%;height:46px">
        <option value="">—</option>
        <option value="Male" <?= ($row['sex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
        <option value="Female" <?= ($row['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
      </select></div>
    <div class="f"><label for="birthdate">Birthdate</label>
      <input id="birthdate" type="date" name="birthdate" value="<?= e($row['birthdate'] ?? '') ?>"></div>
    <div class="f"><label for="contact">Contact</label>
      <input id="contact" name="contact" value="<?= e($row['contact'] ?? '') ?>" maxlength="30"></div>
    <button class="bp" type="submit"><?= $id ? 'Save changes' : 'Add student' ?></button>
  </form>
</div>
<?php include __DIR__ . '/../includes/layout_bottom.php'; ?>
