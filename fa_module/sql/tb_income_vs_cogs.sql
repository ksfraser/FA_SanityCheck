-- Template SQL for Income vs COGS check
-- This selects income GL lines and left-joins to COGS GL lines by transaction (type/type_no)
-- Replace IN lists with your configured accounts or use the PHP helper to populate them.

SELECT gi.type, gi.type_no, gi.tran_date, gi.account AS income_account, gi.amount AS income_amount,
       gc.account AS cogs_account, gc.amount AS cogs_amount
FROM gl_trans gi
LEFT JOIN gl_trans gc ON gc.type = gi.type AND gc.type_no = gi.type_no AND gc.account IN (/* cogs accounts */)
WHERE gi.account IN (/* income accounts */)
  AND gi.tran_date BETWEEN '2026-01-01' AND '2026-12-31'
ORDER BY gi.tran_date DESC;

-- Use this as a template; the PHP diagnostic builds the IN lists from `sanity_config` keys
'income_accounts' and 'cogs_accounts'.
