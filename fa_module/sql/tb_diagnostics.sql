-- Trial Balance Diagnostics SQL helpers

-- 1) Unposted journals
SELECT id, tran_date, amount, narrative, posted FROM gl_trans WHERE posted = 0 ORDER BY tran_date DESC LIMIT 200;

-- 2) Per-account debit/credit diffs (detect rounding drift)
SELECT account, SUM(IF(amount>0,amount,0)) AS debit_sum, SUM(IF(amount<0,amount*-1,0)) AS credit_sum, (SUM(IF(amount>0,amount,0)) - SUM(IF(amount<0,amount*-1,0))) AS diff FROM gl_trans GROUP BY account HAVING ABS(diff) > 0.01 ORDER BY ABS(diff) DESC LIMIT 200;

-- 3) Bank reconciliation sample: compare bank_trans to GL sums
SELECT b.bank_act, SUM(b.amount) AS bank_sum, (SELECT SUM(amount) FROM gl_trans WHERE account = b.bank_act) AS gl_sum FROM bank_trans b GROUP BY b.bank_act LIMIT 50;

-- 4) AR sample: compare debtor_trans totals to GL per debtor
SELECT dt.debtor_no, SUM(dt.ov_amount + dt.ov_freight + dt.ov_gst) AS ar_subledger, (SELECT SUM(amount) FROM gl_trans WHERE person_type_id = 10 AND person_id = dt.debtor_no) AS gl_sum FROM debtor_trans dt GROUP BY dt.debtor_no LIMIT 50;
