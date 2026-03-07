-- Reconciliation helper queries (examples)

-- Bank: compare bank_trans sums to GL sums for the snapshot period
SELECT b.bank_act, SUM(b.amount) AS bank_sum, (SELECT COALESCE(SUM(amount),0) FROM gl_trans WHERE account = b.bank_act) AS gl_sum
FROM bank_trans b
WHERE b.transaction_date BETWEEN @snapshot_start AND @snapshot_end
GROUP BY b.bank_act;

-- AR: per-debtor subledger vs GL
SELECT dt.debtor_no, SUM(dt.ov_amount + dt.ov_freight + dt.ov_gst) AS ar_subledger, (SELECT COALESCE(SUM(amount),0) FROM gl_trans WHERE person_type_id = 10 AND person_id = dt.debtor_no) AS gl_sum
FROM debtor_trans dt
WHERE dt.tran_date BETWEEN @snapshot_start AND @snapshot_end
GROUP BY dt.debtor_no;

-- Inventory: snapshot aggregated by stock
SELECT s.stock_id, SUM(s.qty * COALESCE(s.cost,0)) AS snapshot_value, (SELECT COALESCE(SUM(amount),0) FROM gl_trans WHERE account = (SELECT inventory_account FROM stock_master WHERE stock_id = s.stock_id LIMIT 1)) AS gl_sum
FROM sanity_stock_moves_snapshot s
WHERE s.snapshot_series = @snapshot_series
GROUP BY s.stock_id;
