# FA Sanity Check Module - Project Docs

This directory contains BABOK-style work products for the FrontAccounting (FA) Sanity Check module.

- Target FA version: 2.3.X
- Target PHP: 7.3
- Purpose: Provide traceability and sanity checks for Profit & Loss (P&L) and Assets & Liabilities (A&L) related to inventory movements, purchases, sales, payments, fees, taxes and shipping.

Primary documents:
- Use Cases - scenarios and acceptance criteria
- Business Requirements - business goals, scope, rules and constraints
- Stakeholders - roles and responsibilities

Development guidance: follow the AGENTS-TECH.md conventions in the repository for coding standards, commits, and review process.

Next steps:
- Expand use cases with detailed flows and alternate paths
- Convert business rules into traceability matrix and data model
- Design DB schema and FA integration hooks

Developer setup:
- To enable PDF/XLSX exports, `cd fa_module` and run `composer install` to install `dompdf/dompdf` and `phpoffice/phpspreadsheet`.
- After installing, configure `wkhtmltopdf_path` (optional) and `company_logo_url` in `sanity_config` via Admin UI.
