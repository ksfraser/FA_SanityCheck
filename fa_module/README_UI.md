# UI integration notes

Files added:
- `admin/config.php` — Admin page to select `final_cash_accounts`, `processor_accounts`, shipping allocation mode and tolerances.
- `pages/trace.php` — Trace viewer for an item at a location (querystring: `?stock_id=XXX&location_id=YYY`).
- `hooks.php` — install and menu-registration stubs.
- `sql/create_sanity_config_table.sql` — create `sanity_config` table used by the module.

Integration instructions (brief):
1) Run `fa_module/sql/create_sanity_config_table.sql` in UAT to create the config table.
2) Hook `fa_module/admin/config.php` into FA admin menu and secure with same permissions as P&L report.
3) Hook `fa_module/pages/trace.php` into Reports menu; developers should replace the PDO connection with FA's DB API and use FA's menu/permission helpers.
4) Replace placeholder DB queries where necessary to match your FA database naming and relationships.

Quick test:
- Load `fa_module/admin/config.php`, select accounts, save.
- Insert `tests/sample_data.sql` data into UAT DB.
- Browse to `fa_module/pages/trace.php?stock_id=ITEM-001&location_id=LOC1` to see FIFO assignments and payment follow results.

Export:
- Use the Export CSV link on the trace view to download a CSV of FIFO assignments. The export endpoint is `fa_module/pages/trace_export.php?stock_id=XXX&location_id=YYY`.
- For PDF exports, use `fa_module/pages/trace_export_pdf.php?stock_id=XXX&location_id=YYY`. This endpoint attempts to use `wkhtmltopdf` (path can be set via `sanity_config` key `wkhtmltopdf_path`) or falls back to Dompdf if installed via Composer.

XLSX/Excel export:
- Use `fa_module/pages/trace_export_xlsx.php?stock_id=XXX&location_id=YYY`. If `phpoffice/phpspreadsheet` is installed (via Composer), the endpoint will return a native `.xlsx` file. Otherwise it falls back to CSV.

Composer / installation:
- From `fa_module` directory run:

```bash
composer install
```

- This installs `dompdf/dompdf` and `phpoffice/phpspreadsheet` used by the PDF and XLSX exports.

Integration note:
- This module now requires running inside FrontAccounting 2.3.X (the code uses FA DB helper functions `db_query`, `db_fetch`, `db_escape`). The previous PDO fallbacks were removed — ensure the module files are executed from the FA environment (menu integration) rather than directly via PHP CLI or a standalone webserver.
