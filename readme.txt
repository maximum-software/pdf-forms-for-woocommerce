=== PDF Forms Filler for WooCommerce ===
Version: 1.0.5
Stable tag: 1.0.5
Requires at least: 5.4
Tested up to: 6.7
Requires PHP: 5.5
Tags: pdf, form, woocommerce, email, download
Plugin URI: https://pdfformsfiller.org/
Author: Maximum.Software
Author URI: https://maximum.software/
Contributors: maximumsoftware
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Automatically fill PDF forms with WooCommerce orders and attach generated PDFs to email notifications and order downloads.

== Description ==

This plugin allows WooCommerce store owners to add automatic PDF form filling features for email notification attachments and order downloads to their WooCommerce store.

An existing PDF can be set up to be filled with customer and order information when an order is placed or processed. Images can also be downloaded from a dynamic URL and embedded into the PDF. You can then have your customers receive order email notifications with PDF attachments containing customer order data. You can also allow your customers to download the filled PDF on their order page via the downloadable files feature of WooCommerce. The filled PDF files can be saved in a custom uploads subdirectory on your web server.

What makes this plugin special is its approach to preparing PDF files. It is not generating PDF documents from scratch. It modifies the original PDF document that was prepared using third party software and supplied to the plugin. This allows users the freedom to design exactly what they need and use their pre-existing documents.

Possible uses:
 * Automated creation of tickets for events
 * Automated creation of certificates for certifications requiring payment
 * Automated creation of official documents that require payment
 * Automated warranty document creation based on date of purchase
 * Automated creation of PDFs that assist with order fulfillment

An [external web API](https://pdf.ninja) is used for working with PDF files (free usage has limitations). The plugin comminicates with the external service to create an API key, upload your blank PDF files, retrieve information about your PDF files and eventually add your user information to your PDF files. Please see privacy policy at [https://pdf.ninja](https://pdf.ninja).

Please see [Pdf.Ninja Terms of Use](https://pdf.ninja/#terms) and [Pdf.Ninja Privacy Policy](https://pdf.ninja/#privacy).

Requirements:
 * PHP 5.5 or newer
 * WordPress 5.4 or newer
 * WooCommerce 5.6.0 or newer
 * Chrome 60, Firefox 56 (or equivalent) or newer

Known incompatible plugins:
 * [Imagify](https://wordpress.org/plugins/imagify/) (strips forms from PDF files)
 * [ShortPixel Image Optimizer](https://wordpress.org/plugins/shortpixel-image-optimiser/) (strips forms from PDF files)

Special thanks to the following sponsors of this plugin:
 * [BrowserStack](https://www.browserstack.com/)

## Installation

1. Install the [WooCommerce](https://wordpress.org/plugins/woocommerce/) plugin.
2. Upload this plugin's folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Start using the 'PDF Forms' section on the WooCommerce product editor page.

== Changelog ==

= 1.0.5 =

* Release date: October 26, 2024

* Verified support for WC 9.4 and WP 6.7
* Fixed a bug with insecure connection notice
* Other minor updates

= 1.0.4 =

* Release date: July 17, 2024

* Switched to replacing non-valid placeholders with an empty string
* Fixed placeholder matching

= 1.0.3 =

* Release date: June 2, 2024

* Fixed multiple issues with placeholder processor and added support for more placeholders
* Bug fix (product setting change requires focus out event to be saved)

= 1.0.2 =

* Release date: January 16, 2024

* Fixed possible issues with API communication caused by non-alphanumeric characters in request boundary
* Other minor improvements

= 1.0.1 =

* Release date: January 2, 2024

* Plugin review related changes
* Fixed an issue with UTF-8 not being base64-decoded properly
* Other minor fixes and improvements

= 1.0.0 =

* Release date: October 1, 2023

* Initial release

== Frequently Asked Questions ==

= Does this plugin allow my website users to edit PDF files? =

No. This plugin adds UI features to the [WooCommerce](https://wordpress.org/plugins/woocommerce/) interface in the WordPress Admin Panel only.

= Does this plugin require special software installation on the web server? =

No. The plugin uses core WordPress and WooCommerce features only. No special software or PHP extensions are needed. Working with PDF files is done through [Pdf.Ninja API](https://pdf.ninja). It is recommended to have a working SSL/TLS certificate validation with cURL.

= How are WooCommerce placeholders mapped to PDF form fields? =

The field mapper tool allows you to map fields individually. Combinations of placeholders with custom text can be mapped to a PDF field. Mappings can be associated with a specific PDF attachment or all PDF attachments. Field value mappings can also be created, allowing filled PDF fields to be filled with content that differs from the source values.

= My fields are not getting filled, what is wrong? =

Make sure the mapping exists in the list of mappings and the field names match.

If you attached an updated PDF file and your mappings were for the old attachment ID then those mappings will be deleted and you will need to recreate them.

Sometimes PDF form fields have validation scripts which prevent value with an incorrect format to be filled in. Date PDF fields must be filled with correctly formatted date strings.

= How do I update the attached PDF file without attaching a new version and losing attachment ID related mappings and embeds? =

Try using the [Enable Media Replace plugin](https://wordpress.org/plugins/enable-media-replace/) to replace the PDF file in-place in the Media Library.

= My checkboxes and/or radio buttons are not getting filled, what is wrong? =

Make sure your PDF checkbox/radio field's exported value matches the value that is mapped to it. Usually, it is "On" or "Yes". If you have a different value in the WooCommerce placeholder, you will need to create a value mapping so that your placeholder value gets changed to your PDF checkbox export value.

Some PDF viewers don't render checkboxes correctly in some PDF files. You may be able to solve this issue by recreating the PDF in a different PDF editor. If you are using Pdf.Ninja API v1, switching to v2 may resolve your issue.

= How do I remove the watermark in the filled PDF files? =

Please see the [Pdf.Ninja API website](https://pdf.ninja).

== Screenshots ==

1. PDF Forms section on product edit page
2. An example event ticket product configuration with field mappings and an image embed
3. An example filled event ticket PDF with embedded QR code image and a barcode font field
4. An example order details page with a downloadable warranty certificate PDF
5. An example order notification message with a warranty certificate PDF attachment and a downloadable file link
