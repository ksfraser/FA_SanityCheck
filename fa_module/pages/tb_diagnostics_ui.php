<?php
/**
 * Trial Balance Diagnostics UI
 * Renders diagnostic findings and provides JSON export and simple drill-downs.
 */
require_once __DIR__ . '/../../src/TrialBalanceDiagnostics.php';

use FA\Sanity\TrialBalanceDiagnostics;

if (!function_exists('db_query')) {
    echo "<p>This page must be run inside FrontAccounting (db_query missing).</p>";
    exit;
}

$period = isset($_GET['period_no']) ? (int)$_GET['period_no'] : null;
$diag = new TrialBalanceDiagnostics();
$res = $diag->runDiagnostics($period);

// Output buffering and HTML rendering
ob_start();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Trial Balance Diagnostics</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:18px}
    table{border-collapse:collapse;width:100%;margin-bottom:18px}
    th,td{border:1px solid #ddd;padding:8px}
    th{background:#f4f4f4;text-align:left}
    .sev-red{background:#fdd}
    .sev-yellow{background:#ffd}
    .small{font-size:0.9em;color:#666}
    .actions{margin-bottom:12px}
  </style>
</head>
<body>
  <h1>Trial Balance Diagnostics</h1>
  <div class="actions">
    <a href="tb_diagnostics.php" target="_blank">Export JSON</a>
  </div>

  <h2>Unposted Journals (sample)</h2>
  <?php if (!empty($res['unposted_journals'])): ?>
  <table>
    <tr><th>ID</th><th>Date</th><th>Amount</th><th>Narration</th><th>Action</th></tr>
    <?php foreach ($res['unposted_journals'] as $row): ?>
      <tr>
        <td><?php echo htmlspecialchars($row['id']); ?></td>
        <td><?php echo htmlspecialchars($row['tran_date']); ?></td>
        <td><?php echo htmlspecialchars($row['amount']); ?></td>
        <td><?php echo htmlspecialchars($row['narration'] ?? $row['narrative'] ?? ''); ?></td>
        <td><a href="#" onclick="alert('Drill-down: open GL transaction '+<?php echo json_encode($row['id']); ?>);return false;">View</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
    <p class="small">No unposted journals found (sample limit).</p>
  <?php endif; ?>

  <h2>Rounding Drift (per-account diffs)</h2>
  <?php if (!empty($res['rounding_drift'])): ?>
  <table>
    <tr><th>Account</th><th>Debit</th><th>Credit</th><th>Diff</th></tr>
    <?php foreach ($res['rounding_drift'] as $r): ?>
      <tr>
        <td><?php echo htmlspecialchars($r['account'] ?? $r['Account'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($r['deb'] ?? $r['debit_sum'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($r['cred'] ?? $r['credit_sum'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($r['diff']); ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
    <p class="small">No significant rounding diffs detected.</p>
  <?php endif; ?>

  <h2>Out-of-Period Postings</h2>
  <?php if (!empty($res['out_of_period_postings'])): ?>
    <table>
      <tr><th>ID</th><th>Tran Date</th><th>Period No</th><th>Last Date In Period</th></tr>
      <?php foreach ($res['out_of_period_postings'] as $r): ?>
        <tr class="sev-red">
          <td><?php echo htmlspecialchars($r['id']); ?></td>
          <td><?php echo htmlspecialchars($r['tran_date']); ?></td>
          <td><?php echo htmlspecialchars($r['period_no']); ?></td>
          <td><?php echo htmlspecialchars($r['last_date_in_period'] ?? ''); ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?>
    <p class="small">No out-of-period postings detected.</p>
  <?php endif; ?>

  <h2>Sample GL↔Subledger Queries</h2>
  <div class="small">
    <p>Use these queries as starting points for reconciling GL to subledgers:</p>
    <pre><?php echo htmlspecialchars($res['gl_subledger_diff_sample']['bank_sql']); ?></pre>
    <pre><?php echo htmlspecialchars($res['gl_subledger_diff_sample']['ar_sql']); ?></pre>
  </div>

</body>
</html>
<?php
$html = ob_get_clean();
echo $html;
