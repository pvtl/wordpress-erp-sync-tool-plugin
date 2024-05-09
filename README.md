# Wordpress Plugin for the PVTL ERP Sync Tool

This plugin provides a settings screen within the WP Admin area and also enables data to be synced to/from the ERP Sync Tool.


## How it works
The [ERP Sync Tool is a Laravel application](https://github.com/pvtl/erp-sync-tool) that synchronises data between two systems. Typically one system is ERP software (like Pacsoft or Unleashed), while the other side is a retail/wholesale store built on WooCommerce.

At regular intervals, the (Laravel) Sync Tool will connect to the ERP software and fetch the latest changes to products, customers, orders etc. It also fetches the same data from WooCommerce. Then it compares what changes have occurred, and selectively synchronises those changes back to their respective systems.

This plugin provides the necessary customisations to WordPress/WooCommerce to enable the sync to work.


## Git Tags

Git will automatically tag a commit whenever a change to the version number is detected inside `erp-sync-tool.php`.

This behaviour is handled by `.github/workflows/version-update.yml`.
