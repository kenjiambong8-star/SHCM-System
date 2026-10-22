<?php
require_once __DIR__ . '/includes/functions.php';
if (!empty($_SESSION['user_id'])) { header('Location: /pages/dashboard.php'); exit; }
?>
<!doctype html>
<html lang="en">
<head>
<link rel="icon" href="/assets/img/logo.png">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SHCM System - Student Health Clearance and Monitoring</title>
<meta name="description" content="Collect health requirements, track expiry dates and print clearance slips for every student.">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;800&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/landing.css">
</head>
<body>
<div class="wrap">
  <header class="nav">
    <a class="brand" href="/"><img class="logo" src="/assets/img/logo.png" alt="" width="40" height="40"><span>SHCM System</span></a>
    <nav aria-label="Main">
      <a class="lnk" href="#how">How it works</a>
      <a class="lnk" href="#features">Features</a>
      <a class="lnk" href="#security">Security</a>
      <a class="btn btn-s" href="/auth/login.php">Sign in</a>
    </nav>
  </header>

  <main>
    <section class="hero">
      <div>
        <h1>Know who is cleared before the deadline, not after it.</h1>
        <p class="lead">SHCM System helps the school clinic collect health requirements, watch expiry dates and print a clearance slip for every student.</p>
        <div class="cta">
          <a class="btn btn-p" href="/auth/login.php">Sign in</a>
          <a class="btn btn-s" href="/auth/register.php">Create account</a>
        </div>
      </div>

      <article class="slip" aria-label="Sample clearance slip">
        <header>
          <div><span class="name">Juan Dela Cruz</span><br><small>BSIT, Year 2 &middot; 2026-0101</small></div>
          <small>Sample record</small>
        </header>
        <ul>
          <li>Physical examination <span class="badge ok">Compliant</span></li>
          <li>Chest X-ray <span class="badge ok">Compliant</span></li>
          <li>Dental checkup <span class="badge ok">Compliant</span></li>
          <li>Vaccination record <span class="badge wait">Expires in 12 days</span></li>
          <li>Laboratory test <span class="badge bad">Missing</span></li>
        </ul>
        <footer>
          <div class="meter" role="img" aria-label="3 of 5 requirements valid"><i></i></div>
          <strong>Not yet cleared</strong>
        </footer>
      </article>
    </section>

    <section id="how" class="sec">
      <h2>From first checkup to printed clearance</h2>
      <ol class="steps">
        <li><span class="n">1</span><h3>Add the student</h3><p>Record the student number, course and year level once.</p></li>
        <li><span class="n">2</span><h3>Log each requirement</h3><p>Enter the checkup date, result and the date it stops being valid.</p></li>
        <li><span class="n">3</span><h3>Watch what is missing</h3><p>The dashboard lists students who are incomplete, pending or about to expire.</p></li>
        <li><span class="n">4</span><h3>Print the slip</h3><p>When every requirement is valid, the student shows as cleared and the slip is ready to print.</p></li>
      </ol>
    </section>

    <section id="features" class="sec">
      <h2>Everything the clinic needs in one place</h2>
      <dl class="feat">
        <div><dt>Dashboard</dt><dd>Total students, cleared and not cleared counts, records expiring in 30 days, and charts of compliance by requirement.</dd></div>
        <div><dt>Student and health records</dt><dd>Add, view, edit and delete students and their checkups from one list.</dd></div>
        <div><dt>Filters</dt><dd>Narrow any list by date, requirement, status, course, year level or clearance.</dd></div>
        <div><dt>Reports and print preview</dt><dd>Generate a clearance summary or a health records report, check it on screen, then print.</dd></div>
        <div><dt>Staff and admin roles</dt><dd>Staff encode records. Only admins delete data, manage users and run backups.</dd></div>
        <div><dt>Activity log</dt><dd>See who did what and when, for every sign in, edit and deletion.</dd></div>
      </dl>
    </section>
  </main>

  <section id="security" class="sec band">
    <h2>Built to protect student health data</h2>
    <ul class="sec-list">
      <li>HTTPS encrypts everything between the browser and the server.</li>
      <li>A math question and a 15-minute lock after 5 failed attempts stop password guessing.</li>
      <li>Passwords are stored as one-way hashes, never as plain text.</li>
      <li>New accounts stay inactive until an administrator approves them.</li>
      <li>Sessions end automatically after 30 minutes of inactivity.</li>
      <li>Database and file backups can be downloaded and restored by an admin.</li>
    </ul>
  </section>

  <section class="sec final">
    <h2>Ready to check your students' clearance?</h2>
    <div class="cta">
      <a class="btn btn-p" href="/auth/login.php">Sign in</a>
      <a class="btn btn-s" href="/auth/register.php">Create account</a>
    </div>
  </section>

  <footer class="foot">
    <span>SHCM System: Student Health Clearance and Monitoring System</span>
    <span>&copy; <?= date('Y') ?></span>
  </footer>
</div>
</body>
</html>