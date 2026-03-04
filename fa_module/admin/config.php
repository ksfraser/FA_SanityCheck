<?php
// Simple Admin config UI for FA Sanity Check module
// This page will use FA helper functions when available (db_query, display_heading, start_form), otherwise falls back to PDO.
require __DIR__ . '/../src/ItemTrace.php';

if (!function_exists('db_query')) {
  echo "This admin page must be run from inside FrontAccounting (db_query helper missing).";
  exit;
}

function getConfig($key) {
  $sql = "SELECT config_value FROM sanity_config WHERE config_key='".db_escape($key)."' LIMIT 1";
  $res = db_query($sql);
  $row = db_fetch($res);
  return $row ? json_decode($row['config_value'], true) : null;
}
function saveConfig($key, $value) {
  $j = db_escape(json_encode($value));
  $sql = "INSERT INTO sanity_config (config_key, config_value) VALUES ('".db_escape($key)."', '$j') ON DUPLICATE KEY UPDATE config_value = '$j'";
  db_query($sql);
}
$accRes = db_query("SELECT account_code AS id, bank_account_name FROM bank_accounts ORDER BY bank_account_name");
$acc = [];
while ($r = db_fetch($accRes)) $acc[] = $r;

if (isset($_POST) && count($_POST)>0) {
  $final = isset($_POST['final_cash_accounts']) ? $_POST['final_cash_accounts'] : [];
  $proc = isset($_POST['processor_accounts']) ? $_POST['processor_accounts'] : [];
  $ship = isset($_POST['shipping_allocation_mode']) ? $_POST['shipping_allocation_mode'] : 'proportional_by_value';
  $tolerances = [ 'currency_tolerance' => floatval($_POST['currency_tolerance'] ?? 0.05), 'percent_tolerance' => floatval($_POST['percent_tolerance'] ?? 0.5) ];
  saveConfig('final_cash_accounts', $final);
  saveConfig('processor_accounts', $proc);
  saveConfig('shipping_allocation_mode', $ship);
  saveConfig('anomaly_tolerances', $tolerances);
  echo "<div style='color:green'>Saved</div>";
}

$final_sel = getConfig('final_cash_accounts') ?: [];
$proc_sel = getConfig('processor_accounts') ?: [];
$ship_sel = getConfig('shipping_allocation_mode') ?: 'proportional_by_value';
$tols = getConfig('anomaly_tolerances') ?: ['currency_tolerance'=>0.05,'percent_tolerance'=>0.5];

?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Sanity Check - Admin</title></head><body>
<h2>Sanity Check — Admin Configuration</h2>
<form method="post">
  <label>Final Cash Accounts (ctrl+click to multi-select)</label><br>
  <select name="final_cash_accounts[]" multiple size="6">
    <?php foreach($acc as $a): $aid = isset($a['id']) ? $a['id'] : $a['account_code']; ?>
      <option value="<?=htmlspecialchars($aid)?>" <?=in_array($aid,$final_sel) ? 'selected':''?>><?=htmlspecialchars($a['bank_account_name'])?></option>
    <?php endforeach; ?>
  </select><br><br>

  <label>Processor Accounts (ctrl+click)</label><br>
  <select name="processor_accounts[]" multiple size="6">
    <?php foreach($acc as $a): $aid = isset($a['id']) ? $a['id'] : $a['account_code']; ?>
      <option value="<?=htmlspecialchars($aid)?>" <?=in_array($aid,$proc_sel) ? 'selected':''?>><?=htmlspecialchars($a['bank_account_name'])?></option>
    <?php endforeach; ?>
  </select><br><br>

  <label>Shipping allocation mode</label><br>
  <select name="shipping_allocation_mode">
    <option value="proportional_by_value" <?= $ship_sel==='proportional_by_value'?'selected':''?>>Proportional by line value (default)</option>
    <option value="use_dimensions" <?= $ship_sel==='use_dimensions'?'selected':''?>>Use product dimensions/volumetric weight</option>
  </select><br><br>

  <label>Currency tolerance (absolute)</label>
  <input name="currency_tolerance" value="<?=htmlspecialchars($tols['currency_tolerance'])?>"><br>
  <label>Percent tolerance (%)</label>
  <input name="percent_tolerance" value="<?=htmlspecialchars($tols['percent_tolerance'])?>"><br><br>

  <button type="submit">Save</button>
</form>
</body></html>
