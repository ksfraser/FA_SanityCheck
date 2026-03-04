-- Payment following stored-procedure skeleton
-- Purpose: follow a payment from initial bank_trans through processors to final cash deposit

-- OUT schema suggestion: payment_id, initial_account, initial_date, initial_amount, is_processor_hold, processor_account, final_deposit_id, final_deposit_date, final_deposit_amount, fee_amount, status

-- Pseudocode:
-- 1) Locate initial bank_trans row for payment_id
-- 2) If initial account in final_cash_accounts -> status final_direct
-- 3) If initial account in processor_accounts -> search bank_trans entries from processor -> final cash within window
-- 4) If found, compute fee lines and return settled
-- 5) Otherwise return processor_hold
