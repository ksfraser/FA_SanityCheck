# Implementation: SQL pseudocode and examples

This file provides example SQL and stored-procedure pseudocode for FIFO consumption and payment-following logic. These are implementation sketches — prefer implementing procedural FIFO in the app layer or a stored procedure for determinism and performance.

Files:
- `scripts/fifo_consumption.sql` - stored-proc skeleton to compute FIFO batch assignments per `location_id`.
- `scripts/payment_following.sql` - stored-proc skeleton to follow payments from processor accounts to final cash accounts and extract fees.
- `tests/sample_data.sql` - small dataset to validate logic.

Use these artifacts as starting points for development and unit tests.
