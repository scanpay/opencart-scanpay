# Scanpay for OpenCart

[![Latest Stable Version](https://img.shields.io/github/v/release/scanpay/opencart-scanpay?cacheSeconds=600)](https://github.com/scanpay/opencart-scanpay/releases)
[![License](https://img.shields.io/github/license/scanpay/opencart-scanpay?cacheSeconds=6000)](https://github.com/scanpay/opencart-scanpay/blob/master/LICENSE)
[![CodeFactor](https://www.codefactor.io/repository/github/scanpay/opencart-scanpay/badge)](https://www.codefactor.io/repository/github/scanpay/opencart-scanpay)

Opencart is a free and open-source eCommerce platform. We have developed a payment extension for Opencart that allows you to accept payments on your Opencart store with Scanpay's payment platform. 

This plugin is compatible with all versions of Opencart 3. We are actively working on a new plugin for Opencart 4.0.

If you have any questions, concerns or ideas, please do not hesitate to e-mail us at [support@scanpay.dk](mailto:support@scanpay.dk). Feel free to join our IRC server `irc.scanpay.dev:6697 #support` or chat with us at [chat.scanpay.dev](https://chat.scanpay.dev).

## Requirements

* Opencart 3 *(tested up to opencart 3.0.3.9)*
* PHP version >= 7.4
* php-curl (libcurl >= 7.60.0)


## Installation

1. Download the latest *opencart-scanpay* zip file [here](../../releases).
2. Enter the admin and navigate to `Extensions > Installer`.
3. Click *"upload"* and upload the zip file.
4. Navigate to `Extensions > Extensions` and change extension type to *"payments"*.
5. Find *"scanpay"* and press *"install"*.

### Configuration
Before you begin, you need to generate an API key in our dashboard ([here](https://dashboard.scanpay.dk/settings/api)). Always keep your API key private and secure.

1. Enter the admin, navigate to `Extensions > Extensions` and change extension type to *"payments"*.
2. Find *"scanpay"* and press *"edit"*.
3. Set status to *"enabled"*.
4. Insert your API key in the *"API-key"* field.
5. Copy the contents of the *"Ping URL"* field into the corresponding field in our dashboard ([here](https://dashboard.scanpay.dk/settings/api)). After saving, press the *"Test Ping"* button.
6. You have now completed the installation and configuration of our OpenCart extension. We recommend performing a test order to ensure that everything is working as intended.
