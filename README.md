# PDF Forms Filler for WPForms

Automatically fill PDF forms with WooCommerce orders. Attach filled PDFs to orders and order email notifications.

## Description

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

Requirements:
* PHP 5.5 or newer
* WordPress 5.4 or newer
* WooCommerce 5.6.0 or newer
* Chrome 60, Firefox 56 (or equivalent) or newer

Known problems:
* Some third party plugins may break the functionality of this plugin (see a list below). Try troubleshooting the problem by disabling likely plugins that may cause issues, such as plugins that modify WordPress or WPForms in radical ways.
* Some image optimization plugins optimize PDFs and strip PDF forms from PDF files. This may cause your existing forms to break at a random point in the future (when PDF file cache times out at the API).

Known incompatible plugins:
* [Imagify](https://wordpress.org/plugins/imagify/) (strips forms from PDF files)
* [ShortPixel Image Optimizer](https://wordpress.org/plugins/shortpixel-image-optimiser/) (strips forms from PDF files)

## Installation

1. Install the [WooCommerce](https://wordpress.org/plugins/woocommerce/) plugin.
2. Upload this plugin's folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Start using the 'PDF Forms' section on the WooCommerce product editor page.

## Special Thanks

Special thanks to the following sponsors of this plugin,

[![BrowserStack](assets/BrowserStack.png)](https://www.browserstack.com/)

[BrowserStack](https://www.browserstack.com/)
