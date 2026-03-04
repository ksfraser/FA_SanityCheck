<?php
// Trace view page for FA Sanity Check — FA-aware with PDO fallback

require __DIR__ . '/../src/ItemTrace.php';

if (!function_exists('db_query')) {
    echo "This page must be run inside FrontAccounting (db_query helper missing).";
    exit;
}

$it = new FA\Sanity\ItemTrace();

$stock = isset($_GET['stock_id']) ? $_GET['stock_id'] : null;
$loc = isset($_GET['location_id']) ? $_GET['location_id'] : null;

if (!$stock || !$loc) {
    echo "Provide stock_id and location_id in querystring.";
    exit;
}

$assignments = $it->fifoConsume($stock, $loc);

// gather sales invoices referenced in assignments
$sales = [];
foreach ($assignments as $a) {
    if (!empty($a['out_doc_no'])) $sales[$a['out_doc_no']] = $a['out_doc_no'];
}

// find payments allocated to those sales invoices
$payments = [];
if ($sales) {
    if ($use_fa) {
        $escaped = array_map(function($s){ return db_escape($s); }, array_values($sales));
        $inList = "'".implode("','", $escaped)."'";
        $sql = "SELECT ca.payment_id, ca.amount, bt.account_id FROM cust_allocations ca JOIN bank_trans bt ON ca.payment_id = bt.id WHERE ca.trans_no IN ($inList)";
        $res = db_query($sql);
        while ($r = db_fetch($res)) $payments[] = $r;
    } else {
        $inList = implode(',', array_map(function($s){ return "'".addslashes($s)."'"; }, array_values($sales)));
        $sql = "SELECT ca.payment_id, ca.amount, bt.account_id FROM cust_allocations ca JOIN bank_trans bt ON ca.payment_id = bt.id WHERE ca.trans_no IN ($inList)";
        foreach ($pdo->query($sql) as $r) { $payments[] = $r; }
    }
}

// load config
$cfg = [];
if ($use_fa) {
    $cfgStmt = db_query("SELECT config_key, config_value FROM sanity_config WHERE config_key IN ('final_cash_accounts','processor_accounts')");
    while ($c = db_fetch($cfgStmt)) $cfg[$c['config_key']] = json_decode($c['config_value'], true);
} else {
    $cfgStmt = $pdo->prepare("SELECT config_key, config_value FROM sanity_config WHERE config_key IN ('final_cash_accounts','processor_accounts')");
    $cfgStmt->execute();
    foreach ($cfgStmt->fetchAll(PDO::FETCH_ASSOC) as $c) { $cfg[$c['config_key']] = json_decode($c['config_value'], true); }
}

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item Trace: <?=htmlspecialchars($stock)?></title>
<style>.red{background:#f8d7da}.yellow{background:#fff3cd}table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px}</style>
</head><body>
<h2>Trace for <?=htmlspecialchars($stock)?> at <?=htmlspecialchars($loc)?></h2>
<p><a href="trace_export.php?stock_id=<?=urlencode($stock)?>&location_id=<?=urlencode($loc)?>">Export CSV</a></p>
<h3>FIFO Assignments</h3>
<table>
<tr><th>Inbound Doc</th><th>In Date</th><th>Assigned Qty</th><th>Outbound Doc</th><th>Out Date</th></tr>
<?php foreach($assignments as $a): ?>
  <tr>
    <td><?=htmlspecialchars($a['in_doc_no']?:'')?></td>
    <td><?=htmlspecialchars($a['in_date']?:'')?></td>
    <td><?=htmlspecialchars($a['assigned_qty'])?></td>
    <td><?=htmlspecialchars($a['out_doc_no']?:'UNASSIGNED')?></td>
    <td><?=htmlspecialchars($a['out_date']?:'')?></td>
  </tr>
<?php endforeach; ?>
</table>

<h3>Payments and Settlement</h3>
<?php if (!$payments) { echo '<p>No payments found for these sales.</p>'; } else { ?>
  <table>
  <tr><th>Payment ID</th><th>Amount</th><th>Account</th><th>Follow Result</th></tr>
  <?php foreach($payments as $p): $res = $it->followPayment($p['payment_id'], [
        'final_cash_accounts'=>$cfg['final_cash_accounts'] ?? [], 'processor_accounts'=>$cfg['processor_accounts'] ?? [], 'processor_follow_window_days'=>30
    ]); ?>
    <tr class="<?=($res['status']==='settled'?'':'yellow')?>">
      <td><?=htmlspecialchars($p['payment_id'])?></td>
      <td><?=htmlspecialchars($p['amount'])?></td>
      <td><?=htmlspecialchars($p['account_id'])?></td>
      <td><?=htmlspecialchars(json_encode($res))?></td>
    </tr>
  <?php endforeach; ?>
  </table>
<?php } ?>

</body></html>
