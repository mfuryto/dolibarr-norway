# Norway 0.2.1

External module foundation for Dolibarr 24.0.1 / PHP 8.2 or newer.
Copy `norway/` into `htdocs/custom/`, enable **Norwegian localisation** in
Setup → Modules, then open its setup page. Advanced accounting is a dependency.
NOK is the only supported currency for new sales documents. Cheque payments and
deposit pages are disabled. Norwegian terminology uses customers and suppliers.
Activation configures these restrictions and document languages; an indicative
EUR calculator is separate from accounting. Historical documents are retained.
Deactivation leaves accounting data intact. Activation does not install a chart,
change VAT rates or produce SAF-T. Provisional module ID: 510173; check collisions
in System information and reserve with upstream before public distribution.

GPL-3.0-or-later. This is a development foundation, not a production accounting solution.
