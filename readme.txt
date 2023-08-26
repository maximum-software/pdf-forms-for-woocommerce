=== PDF Forms Filler for WPForms ===
Version: 1.0.0
Stable tag: 1.0.0
Requires at least: 5.4
Tested up to: 6.3
Requires PHP: 5.5
Tags: pdf, form, filler, woocommerce, attach, email, download
Plugin URI: https://pdfformsfiller.org/
Author: Maximum.Software
Author URI: https://maximum.software/
Contributors: maximumsoftware
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Automatically fill PDF forms with WooCommerce orders. Attach filled PDFs to orders and order email notifications.

== Description ==

This plugin allows WooCommerce store owners to add PDF attachment/download features to their WooCommerce store.
An existing PDF can be set up to be filled with customer information when an order is placed or processed.
The filled PDF is updated as the order status updates.
You can have your customers receive order email notifications with PDF attachments with customer order data.
You can allow your customers to download the filled PDF on their order page.
You can also set up PDF files with image embedding, supplied by the WooCommerce variables.
The filled PDF files can be saved in a custom uploads subdirectory on your web server.

What makes this plugin special is its approach to preparing PDF files. It is not generating PDF documents from scratch.
It modifies the original PDF document that was prepared using third party software and supplied to the plugin.
This allows users the freedom to design exactly what they need or use their pre-existing documents.

Possible uses:
 * Automated creation of tickets for events
 * Automated creation of certificates for certifications requiring payment
 * Automated creation of official documents that require payment
 * Automated warranty document creation based on date of purchase
 * Automated creation of PDFs that assist with order fulfillment

An external web API (https://pdf.ninja) is used for filling PDF forms (free usage has limitations).

Requirements:
 * PHP 5.5 or newer
 * WordPress 5.4 or newer
 * WooCommerce 5.6.0 or newer
 * Chrome 60, Firefox 56 (or equivalent) or newer

Known problems:
* [Imagify](https://wordpress.org/plugins/imagify/) (strips forms from PDF files)
* [ShortPixel Image Optimizer](https://wordpress.org/plugins/shortpixel-image-optimiser/) (strips forms from PDF files)

Special thanks to the following sponsors of this plugin:
 * [BrowserStack](https://www.browserstack.com/)

## Installation

1. Install the [WooCommerce](https://wordpress.org/plugins/woocommerce/) plugin.
2. Upload this plugin's folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Start using the 'PDF Forms' section in the WPForms editor under settings.
