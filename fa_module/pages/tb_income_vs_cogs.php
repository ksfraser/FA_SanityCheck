<?php
/**
 * TB Income vs COGS diagnostic UI
 */
require_once __DIR__ . '/../../src/IncomeVsCogsDiagnostic.php';

use FA\Sanity\IncomeVsCogsDiagnostic;

if (!function_exists('db_query')) {
    echo "<p>This page must be run inside FrontAccounting (db_query missing).</p>";
    exit;
}

// Internal module navigation
$menuSnippet = __DIR__ . '/../integration/menu_snippet.php';
if (file_exists($menuSnippet)) {
  include_once $menuSnippet;
  if (function_exists('sanity_render_nav')) sanity_render_nav();
}

$diag = new IncomeVsCogsDiagnostic();
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$tol = isset($_GET['tolerance']) ? floatval($_GET['tolerance']) : 0.0;
$results = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['run'])) {
    $res = $diag->incomeVsCogs($start, $end, $tol);
    if (isset($res['error'])) $error = $res['error']; else $results = $res;
}

?><!doctype html>
<html><head><meta charset="utf-8"><title>Income vs COGS Diagnostic</title></head><body>
<h1>Income vs COGS Diagnostic</h1>
<form method="get">
  <label>Start date: <input type="date" name="start" value="<?php echo htmlspecialchars($start); ?>"></label>
  <label>End date: <input type="date" name="end" value="<?php echo htmlspecialchars($end); ?>"></label>
  <label>Tolerance: <input name="tolerance" value="<?php echo htmlspecialchars($tol); ?>"></label>
  <button name="run" value="1">Run</button>
</form>

<?php if ($error): ?><p style="color:red"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<?php if (is_array($results)): ?>
  <h2>Findings (<?php echo count($results); ?>)</h2>
  <table border="1" cellpadding="6"><tr><th>Date</th><th>Type</th><th>Type No</th><th>Income Acc</th><th>Income</th><th>COGS Acc</th><th>COGS</th><th>Flag</th></tr>
  <?php foreach ($results as $r): ?>
    <tr>
      <td><?php echo htmlspecialchars($r['tran_date']); ?></td>
      <td><?php echo htmlspecialchars($r['type']); ?></td>
      <td><?php echo htmlspecialchars($r['type_no']); ?></td>
      <td><?php echo htmlspecialchars($r['income_account']); ?></td>
      <td><?php echo htmlspecialchars($r['income_amount']); ?></td>
      <td><?php echo htmlspecialchars($r['cogs_account'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($r['cogs_amount'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($r['flag']); ?></td>
    </tr>
  <?php endforeach; ?></table>
<?php endif; ?>

</body></html>
