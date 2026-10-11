# Norwegian chart source and licensing

The account dataset is derived from Odoo's official `l10n_no` localisation,
branch 19.0, commit `2ff84495182bc2d6e87b616731f865677a12b213`.

Source: https://github.com/odoo/odoo/blob/2ff84495182bc2d6e87b616731f865677a12b213/addons/l10n_no/data/template/account.account-no.csv

The module declares LGPL-3. Authors credited by its manifest include Rolv Råen,
Bringsvor Consulting AS and Odoo contributors. The original manifest, unmodified
source CSV and full upstream LICENSE are included alongside this notice.
The derived dataset retains the upstream LGPL-3 terms; the surrounding project
uses GPL-3.0-or-later. No proprietary NS 4102 publication or restricted SAF-T
standard-account dataset is copied.

Modification: `code` and `name@nb_NO` are converted to `account_number` and
`label` in UTF-8 semicolon CSV, preserving all 745 records. Account types,
reconciliation flags and Odoo tags remain in the original source for traceability
but are not installed as Dolibarr mappings. No account numbers or names are
invented or silently filtered. Source and derived SHA-256 hashes are recorded
in `norwegian-chart-source.json`.

This is an established Norwegian software chart, not a claim of certification
by Standard Norge or approval of the accounting implementation. An account
label mentioning VAT does not implement tax treatment or reporting.
