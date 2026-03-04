# FA Sanity Check — PHP module

Quick start (PHP 7.3)

1) Configure DB connection in `fa_module/tests/test_item_trace.php`.
2) Load sample test data in `tests/sample_data.sql` into your UAT DB.
3) Run the test script:

```bash
php fa_module/tests/test_item_trace.php
```

Files:
- `src/ItemTrace.php` — app-layer FIFO and payment-following functions (PDO based)
- `tests/test_item_trace.php` — simple CLI test harness

Notes:
- Adjust table/column names in `ItemTrace.php` to match your FA installation if necessary.
- For production integration, wrap these methods into an FA module and reuse FA's DB connector and permission checks.
