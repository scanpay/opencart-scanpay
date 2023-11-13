# Installation guide

1. Download the extension [here](../../../releases). It is an OCMOD zip file.
2. Open the OpenCart admin area and navigate to `Extensions > Installer`.
3. Click *"upload"* and upload the zip file.
4. Navigate to `Extensions > Extensions` and change extension type to *"payments"*.
6. Find *"scanpay"* and press *"install"*.

## Configuration
Before you begin, you need to generate an API key in our dashboard ([here](https://dashboard.scanpay.dk/settings/api)). Always keep your API key private and secure.

1. Enter the admin, navigate to `Extensions > Extensions` and change extension type to *"payments"*.
2. Find *"scanpay"* and press *"edit"*.
3. Set status to *"enabled"*.
4. Insert your API key in the *"API-key"* field.
5. Copy the contents of the *"Ping URL"* field into the corresponding field in our dashboard ([here](https://dashboard.scanpay.dk/settings/api)). After saving, press the *"Test Ping"* button.
6. You have completed installing and configuring our OpenCart extension. We recommend performing a test order to ensure everything works as intended.
