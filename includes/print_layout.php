<?php
function print_start(string $title, string $subtitle = ''): void { ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="/assets/css/print.css">
</head><body>
<div class="bar noprint">
  <button class="pbtn" type="button" onclick="history.back()">Back</button>
  <button class="pbtn" type="button" onclick="window.print()">Print</button>
  <span>Print preview</span>
</div>
<div class="sheet">
<header class="rh">
  <h1>Student Health Clearance and Monitoring System</h1>
  <h2><?= e($title) ?></h2>
  <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
</header>
<?php }

function print_end(): void { ?>
<footer class="rf">
  <div class="sig"><div><span></span>Prepared by: <?= e($_SESSION['fullname'] ?? '') ?></div><div><span></span>Noted by</div></div>
  <p class="gen">Generated <?= e(date('F j, Y g:i A')) ?></p>
</footer>
</div></body></html>
<?php }