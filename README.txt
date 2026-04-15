=== WooFlow Exporter ===
Contributors: mariankohn
Tags: woocommerce, export, csv, automation, orders, api
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.6.0
License: GPLv2 or later

Export WooCommerce orders to CSV securely and automate your workflow.

== Description ==

Manually exporting WooCommerce orders?

WooFlow Exporter helps you automate and simplify your workflow — without compromising security.

Export orders to CSV instantly or generate files for later use. Built with real-world use cases in mind: integrations, automation, and data handling.

This plugin focuses on:

- performance
- simplicity
- security (no public data exposure)

Perfect for:

- store owners
- agencies
- developers building integrations
- anyone tired of manual exports

== Features ==

- Manual CSV export (instant download)
- Generate CSV files to private storage
- Secure file downloads (no public access)
- Order filtering (status + date range)
- Daily cron export (automation)
- Generated files overview in admin
- Delete exported files from admin
- Clean and lightweight architecture

== Installation ==

1. Upload the plugin to /wp-content/plugins/
2. Activate the plugin
3. Go to WooCommerce → WooFlow Exporter

== Usage ==

1. Configure export filters (optional)
2. Click "Download CSV Now" for instant export
3. Or click "Generate CSV File Now" to store the file
4. Access generated files in the admin interface
5. Enable cron export for daily automation

== FAQ ==

= Is this plugin free? =

Yes, the core version is free. Advanced integration features may be added in future versions.

= Does it support automation? =

Yes. You can enable daily automatic exports using WordPress cron.

= Are exported files publicly accessible? =

No. All generated files are stored in a private directory and can only be accessed via secure admin actions.

= Can I delete exported files? =

Yes. Files can be deleted directly from the admin interface.

== Changelog ==

= 0.6.0 =
- Added private storage for exports (security improvement)
- Implemented secure file download via admin-post
- Added generated files list in admin
- Added delete file functionality
- Added daily cron export
- Improved plugin architecture (modular structure)

= 0.4.0 =
- Initial refactor and improvements

= 0.1.0 =
- Initial release
- Manual CSV export
- Basic settings
