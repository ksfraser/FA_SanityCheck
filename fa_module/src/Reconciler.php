<?php
namespace FA\Sanity;

/**
 * Reconciler
 *
 * Performs GL ↔ subledger reconciliation using snapshot tables produced by SnapshotBuilder.
 * The class is intentionally lightweight: it executes configurable queries and reports matched/unmatched items.
 */
class Reconciler
{
    /**
     * Run a set of reconciliation checks for a snapshot series.
     * Returns a map of check_name => results array.
     *
     * @param string $series
     * @param array $options
     * @return array
     */
    public function runAllChecks($series, array $options = [])
    {
        $out = [];
        $out['bank'] = $this->reconcileBank($series);
        $out['ar'] = $this->reconcileAR($series);
        $out['inventory'] = $this->reconcileInventory($series);
        return $out;
    }

    /**
     * Reconcile bank_trans sums to GL bank account sums using snapshot series.
     * @param string $series
     * @return array list of rows with bank_sum, gl_sum, diff
     */
    public function reconcileBank($series)
    {
        $q = "SELECT b.bank_act, SUM(b.amount) AS bank_sum, (SELECT COALESCE(SUM(amount),0) FROM gl_trans WHERE account = b.bank_act) AS gl_sum FROM bank_trans b WHERE b.transaction_date BETWEEN (SELECT MIN(transaction_date) FROM sanity_stock_moves_snapshot WHERE snapshot_series='" . db_escape($series) . "') AND (SELECT MAX(transaction_date) FROM sanity_stock_moves_snapshot WHERE snapshot_series='" . db_escape($series) . "') GROUP BY b.bank_act";
        $res = db_query($q);
        $rows = [];
        while ($r = db_fetch($res)) {
            $r['diff'] = round((float)$r['bank_sum'] - (float)$r['gl_sum'], 2);
            if (abs($r['diff']) > 0.005) $rows[] = $r;
        }
        Logger::info('reconcileBank', ['series'=>$series,'count'=>count($rows)]);
        return $rows;
    }

    /**
     * Reconcile AR sums (debtor_trans) to GL (person_type_id=10) for the snapshot period.
     */
    public function reconcileAR($series)
    {
        $q = "SELECT dt.debtor_no, SUM(dt.ov_amount + dt.ov_freight + dt.ov_gst) AS ar_subledger, (SELECT COALESCE(SUM(amount),0) FROM gl_trans WHERE person_type_id = 10 AND person_id = dt.debtor_no) AS gl_sum FROM debtor_trans dt WHERE dt.tran_date BETWEEN (SELECT MIN(transaction_date) FROM sanity_stock_moves_snapshot WHERE snapshot_series='" . db_escape($series) . "') AND (SELECT MAX(transaction_date) FROM sanity_stock_moves_snapshot WHERE snapshot_series='" . db_escape($series) . "') GROUP BY dt.debtor_no";
        $res = db_query($q);
        $rows = [];
        while ($r = db_fetch($res)) {
            $r['diff'] = round((float)$r['ar_subledger'] - (float)$r['gl_sum'], 2);
            if (abs($r['diff']) > 0.005) $rows[] = $r;
        }
        Logger::info('reconcileAR', ['series'=>$series,'count'=>count($rows)]);
        return $rows;
    }

    /**
     * Inventory reconciliation: compare snapshot stock value to GL inventory account (basic sample).
     */
    public function reconcileInventory($series)
    {
        $q = "SELECT s.stock_id, SUM(s.qty * COALESCE(s.cost,0)) AS snapshot_value, (SELECT COALESCE(SUM(amount),0) FROM gl_trans WHERE account = (SELECT inventory_account FROM stock_master WHERE stock_id = s.stock_id LIMIT 1)) AS gl_sum FROM sanity_stock_moves_snapshot s WHERE s.snapshot_series='" . db_escape($series) . "' GROUP BY s.stock_id";
        $res = db_query($q);
        $rows = [];
        while ($r = db_fetch($res)) {
            $r['diff'] = round((float)$r['snapshot_value'] - (float)$r['gl_sum'], 2);
            if (abs($r['diff']) > 0.005) $rows[] = $r;
        }
        Logger::info('reconcileInventory', ['series'=>$series,'count'=>count($rows)]);
        return $rows;
    }

}
