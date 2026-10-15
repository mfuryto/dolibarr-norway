# Norway 0.60.0

External module foundation for Dolibarr 24.0.1 / PHP 8.2 or newer.
Copy `norway/` into `htdocs/custom/`, enable **Norwegian localisation** in
Setup → Modules, then open its setup page. Advanced accounting is a dependency.
NOK is the only supported currency for new sales documents. Cheque payments and
deposit pages are disabled. Norwegian terminology uses customers and suppliers.
Activation configures these restrictions and document languages; an indicative
EUR calculator is separate from accounting. Historical documents are retained.
Deactivation leaves accounting data intact. Read-only setup diagnostics cover the selected chart, default accounts and journals.
CSV chart preflight highlights existing, inactive, conflicting and historical accounts.
Explicit confirmation can create missing accounts atomically, preserving existing accounts.
The pinned LGPL-3 Odoo Norwegian chart (745 accounts) can be explicitly installed.
New native sales and purchase postings retain source observations and
separate explicit dated tax-code setup. Sales additionally retain
registration-date comparisons. Selected allocation profiles can compare
retained sources with the ledger. The native supplier invoice card includes
an optional comparison for explicitly selected full, partial or no deduction.
Reasoned deduction assessments can be explicitly previewed and saved with
line amounts and revision history. Saving does not change postings or
establish entitlement. New purchase postings retain the observed assessment
revision and source agreement at creation time. Older observations are not
backfilled. Confirmed first posting applies full, partial or no deduction to
supported domestic expense purchases and credits using the native ledger API.
Changed deduction shares can be corrected with full reversal and a replacement
voucher, retaining originals. Only unchanged invoice sources, the same calendar
month and bases not used in a submitted VAT return are supported. Line-specific
shares and reasons support mixed 25/15/12% purchases and credits, with separate
VAT account controls and preserved version-one assessments.
The comparison does not change postings or infer/approve deduction entitlement. Qualified tax/reporting mapping remains future work. See data/THIRD-PARTY-NOTICES.md.
A manual VAT registry lookup compares current status without changing settings.
Customer/supplier codes have independent five-digit counters and optional
prefixes. Prefix changes preserve existing codes and continue the counter.
Activation does not install a chart,
change VAT rates or produce SAF-T. Provisional module ID: 510173; check collisions
in System information and reserve with upstream before public distribution.

GPL-3.0-or-later. This is a development foundation, not a production accounting solution.

Core menu extensions expose ledger and period checks to internal ledger readers.
Invoice diagnostics remain administrator-only and have a native invoice tab.
Daily control links have been removed from the setup wizard. Configuration and
write permissions remain restricted; this is the first menu integration step.

Version 0.59.0 adds scoped product/service account tabs, an invoice evidence
tab and ordinary VAT setup/catalogue/proposal-pricing entries. Existing
configuration and evidence write guards remain unchanged. Tab definitions
refresh during upgrades; invalid product IDs never broaden report scope.

Version 0.60.0 groups Norwegian accounting configuration under core accounting
setup. VAT children expand inside that group and remain hidden during daily
ledger work. The redundant Bank shortcut is removed; the one-time bank
mapping action remains available from accounting configuration.
