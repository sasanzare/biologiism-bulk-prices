# Knowledge Update Log

## 2026-10-03

* **Live price update**: Cleared 88 lifetime variation sale prices; post-update preview found 89 lifetime variations and no remaining sale-price changes. Backup: 06cbb3b4477d49fda9f034f9d7e7ed33.
* **Scope note**: Product 13538 is a simple product with a sale price and no license selector; it remains outside the lifetime-variation operation.
* **Live preview**: The activated plugin scanned 120 published products and 226 variations; it found 89 lifetime values, with 88 sale prices to clear and one already unchanged. Every proposed row showed the lifetime label; no installment or free rows appeared.
* **Deployment**: WordPress reported version 0.1.0 installed and activated.
* **Implementation**: Added the plugin bootstrap, Persian variation classifier, percentage calculator, WooCommerce preview/apply/restore service, and capability-gated admin page. The plugin source passed PHP syntax checks and its eight focused smoke checks.
* **Safety**: Documented the source-level preview and backup workflow.
* **Initialization**: Created the initial project knowledge bundle using OKF 0.2.
* **Documentation**: Recorded the storefront variation labels and the observed lifetime variation pricing for product 14550.