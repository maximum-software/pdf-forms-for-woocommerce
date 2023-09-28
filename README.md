# PDF Forms Filler for WooCommerce

Automatically fill PDF forms with WooCommerce orders and attach generated PDFs to email notifications and order downloads.

## Description

This plugin allows WooCommerce store owners to add automatic PDF form filling features for email notification attachments and order downloads to their WooCommerce store.
An existing PDF can be set up to be filled with customer and order information when an order is placed or processed.
Images can also be downloaded from a dynamic URL and embedded into the PDF.
You can then have your customers receive order email notifications with PDF attachments containing customer order data.
You can also allow your customers to download the filled PDF on their order page via the downloadable files feature of WooCommerce.
The filled PDF files can be saved in a custom uploads subdirectory on your web server.

What makes this plugin special is its approach to preparing PDF files. It is not generating PDF documents from scratch.
It modifies the original PDF document that was prepared using third party software and supplied to the plugin.
This allows users the freedom to design exactly what they need and use their pre-existing documents.

Possible uses:
* Automated creation of tickets for events
* Automated creation of certificates for certifications requiring payment
* Automated creation of official documents that require payment
* Automated warranty document creation based on date of purchase
* Automated creation of PDFs that assist with order fulfillment

An [external web API](https://pdf.ninja) is used for filling PDF forms (free usage has limitations).

Requirements:
* PHP 5.5 or newer
* WordPress 5.4 or newer
* WooCommerce 5.6.0 or newer
* Chrome 60, Firefox 56 (or equivalent) or newer

Known incompatible plugins:
* [Imagify](https://wordpress.org/plugins/imagify/) (strips forms from PDF files)
* [ShortPixel Image Optimizer](https://wordpress.org/plugins/shortpixel-image-optimiser/) (strips forms from PDF files)

## Installation

1. Install the [WooCommerce](https://wordpress.org/plugins/woocommerce/) plugin.
2. Upload this plugin's folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Start using the 'PDF Forms' section on the WooCommerce product editor page.

## Screenshots

![PDF Forms section on product edit page](assets/screenshot-1.png?raw=true)

![An example event ticket product configuration with field mappings and an image embed](assets/screenshot-2.png?raw=true)

![An example filled event ticket PDF with embedded QR code image and a barcode font field](assets/screenshot-3.png?raw=true)

![An example order details page with a downloadable warranty certificate PDF](assets/screenshot-4.png?raw=true)

![An example order notification message with a warranty certificate PDF attachment and a downloadable file link](assets/screenshot-5.png?raw=true)

## Special Thanks

Special thanks to the following sponsors of this plugin,

[![BrowserStack](assets/BrowserStack.png)](https://www.browserstack.com/)

[BrowserStack](https://www.browserstack.com/)
