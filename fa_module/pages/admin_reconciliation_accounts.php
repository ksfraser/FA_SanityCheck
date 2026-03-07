<?php
/**
 * Admin UI — edit `reconciliation_accounts` mapping used by the reconciler.
 */
require_once __DIR__ . '/../../src/AdminConfig.php';

use FA\Sanity\AdminConfig;

if (!function_exists('db_query')) {
    echo "<p>This page must be run inside FrontAccounting (db_query missing).</p>";
    exit;
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reconciliation_json'])) {
    $raw = $_POST['reconciliation_json'];
    $decoded = json_decode($raw, true);
    if ($decoded === null && trim($raw) !== '') {
        $message = 'Invalid JSON provided.';
    } else {
        // empty => save empty map
        $map = is_array($decoded) ? $decoded : [];
        try {
            AdminConfig::saveReconciliationAccounts($map);
            $message = 'Saved.';
        } catch (Throwable $e) {
            $message = 'Save failed: ' . $e->getMessage();
        }
    }
}

$existing = AdminConfig::getReconciliationAccounts();
$jsonPretty = json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?><!doctype html>
<html><head><meta charset="utf-8"><title>Reconciliation Accounts</title></head><body>
<h1>Reconciliation Accounts</h1>
<?php if ($message): ?><p><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<p>This setting stores a JSON object mapping subledger keys to GL account codes. Example:
<pre>{
  "bank": "1010",
  "cash_on_hand": "1005",
  "sales": "4000"
}</pre>
Edit and click Save.</p>
<form method="post">
  <textarea name="reconciliation_json" rows="12" cols="80"><?php echo htmlspecialchars($jsonPretty); ?></textarea><br>
  <button type="submit">Save</button>
</form>

</body></html>
