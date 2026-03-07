<?php
/**
 * Reconciliation UI — run GL↔subledger checks for a chosen snapshot series.
 */
require_once __DIR__ . '/../../src/Reconciler.php';
require_once __DIR__ . '/../../src/SnapshotBuilder.php';

use FA\Sanity\Reconciler;
use FA\Sanity\SnapshotBuilder;

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

$series = $_GET['series'] ?? null;
$builder = new SnapshotBuilder();
$seriesList = $builder->listSeries();

$results = null;
if ($series) {
    $rec = new Reconciler();
    $results = $rec->runAllChecks($series);
}

?><!doctype html>
<html><head><meta charset="utf-8"><title>Reconciliations</title></head><body>
<h1>Reconciliations</h1>
<form method="get">
  <label>Snapshot series:
    <select name="series">
      <option value="">--select--</option>
      <?php foreach ($seriesList as $s): ?>
        <option value="<?php echo htmlspecialchars($s); ?>" <?php if ($s===$series) echo 'selected';?>><?php echo htmlspecialchars($s);?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit">Run</button>
</form>

<?php if ($results !== null): ?>
  <h2>Bank Exceptions</h2>
  <?php if (empty($results['bank'])): ?><p>No bank exceptions detected.</p><?php else: ?>
    <table border="1"><tr><th>Bank Act</th><th>Bank Sum</th><th>GL Sum</th><th>Diff</th></tr>
    <?php foreach ($results['bank'] as $r): ?><tr><td><?php echo htmlspecialchars($r['bank_act']);?></td><td><?php echo htmlspecialchars($r['bank_sum']);?></td><td><?php echo htmlspecialchars($r['gl_sum']);?></td><td><?php echo htmlspecialchars($r['diff']);?></td></tr><?php endforeach; ?></table>
  <?php endif; ?>

  <h2>AR Exceptions</h2>
  <?php if (empty($results['ar'])): ?><p>No AR exceptions detected.</p><?php else: ?>
    <table border="1"><tr><th>Debtor</th><th>Subledger</th><th>GL Sum</th><th>Diff</th></tr>
    <?php foreach ($results['ar'] as $r): ?><tr><td><?php echo htmlspecialchars($r['debtor_no']);?></td><td><?php echo htmlspecialchars($r['ar_subledger']);?></td><td><?php echo htmlspecialchars($r['gl_sum']);?></td><td><?php echo htmlspecialchars($r['diff']);?></td></tr><?php endforeach; ?></table>
  <?php endif; ?>

  <h2>Inventory Exceptions</h2>
  <?php if (empty($results['inventory'])): ?><p>No inventory exceptions detected.</p><?php else: ?>
    <table border="1"><tr><th>Stock</th><th>Snapshot Value</th><th>GL Sum</th><th>Diff</th></tr>
    <?php foreach ($results['inventory'] as $r): ?><tr><td><?php echo htmlspecialchars($r['stock_id']);?></td><td><?php echo htmlspecialchars($r['snapshot_value']);?></td><td><?php echo htmlspecialchars($r['gl_sum']);?></td><td><?php echo htmlspecialchars($r['diff']);?></td></tr><?php endforeach; ?></table>
  <?php endif; ?>

<?php endif; ?>

</body></html>
