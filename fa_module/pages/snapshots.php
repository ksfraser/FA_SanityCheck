<?php
/**
 * Snapshots UI — create and list snapshot series for reconciliation runs.
 */
require_once __DIR__ . '/../../src/SnapshotBuilder.php';

use FA\Sanity\SnapshotBuilder;

if (!function_exists('db_query')) {
    echo "<p>This page must be run inside FrontAccounting (db_query missing).</p>";
    exit;
}

$builder = new SnapshotBuilder();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['series'])) {
    $series = trim($_POST['series']);
    $start = $_POST['start_date'] ?? null;
    $end = $_POST['end_date'] ?? null;
    $userId = $_SESSION['wa_current_user'] ?? null;
    try {
        $res = $builder->createSnapshot($series, $start, $end, $userId);
        $message = "Snapshot created: series={$res['series']} rows={$res['rows_inserted']}";
    } catch (\Throwable $e) {
        $message = 'Snapshot failed: ' . $e->getMessage();
    }
}

$seriesList = $builder->listSeries();

?><!doctype html>
<html><head><meta charset="utf-8"><title>Sanity Snapshots</title></head><body>
<h1>Sanity Snapshots</h1>
<?php if ($message): ?><p><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="post">
  <label>Series (e.g. 2026Q1): <input name="series" required></label><br>
  <label>Start date (YYYY-MM-DD): <input name="start_date" type="date"></label><br>
  <label>End date (YYYY-MM-DD): <input name="end_date" type="date"></label><br>
  <button type="submit">Create Snapshot</button>
</form>

<h2>Existing Series</h2>
<ul>
<?php foreach ($seriesList as $s): ?>
  <li><?php echo htmlspecialchars($s); ?></li>
<?php endforeach; ?>
</ul>

</body></html>
