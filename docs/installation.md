# Installation guide

### Installation

1. Download the extension [here](../../../releases). It is an OCMOD zip file with the following name scheme: `opencart-scanpay-x.y.z.ocmod.zip`.
2. Open your OpenCart admin area and navigate to `Extensions > Installer`.
3. Click *"upload"* to upload the zip file.
4. Navigate to `Extensions > Extensions` and change the extension type to *"payments"*.
6. Find *"scanpay"* and press *"install"*. An *"edit"* button should appear.
7. Click the *"edit"* button. This will take you to the extension settings.
8. Insert your Scanpay API key in the *"API key"* field. You can generate an API key in our dashboard [here](https://dashboard.scanpay.dk/settings/api).
9. Save the changes.
10. A yellow box should appear below the *"API key"* field. Follow the link in the box to the scanpay dashboard.
11. Now, in the scanpay dashboard, save the *"ping URL"*. The system will perform an initial synchronization and show you the result.
12. Your OpenCart should now be connected and synchronized with Scanpay. When ready, change the status from *"Disabled"* to *"Enabled"* to enable the extension in your checkout.

### Update the extension

[!NOTE] Sometimes, we will ask you to uninstall the extension before updating to a new version. If this is the case, please follow the uninstall steps before updating.

1. Download the updated extension [here](../../../releases).
2. Open your OpenCart admin area and navigate to `Extensions > Installer`.
3. Remove the old scanpay extension in the *"Install History"* table. You have now removed the extension, not its stored data or configuration.
4. Click *"upload"* to upload the new extension.

### Uninstall the extension

[!NOTE] This will remove the extension and its stored data.

1. In your OpenCart admin area, navigate to `Extensions > Extensions` and change the extension type to *"payments"*.
2. Find *"scanpay"* in the list and click the *"uninstall"* button. You have now removed the extension's stored data, including the configuration.
3. Navigate to `Extensions > Installer`.
4. Remove the scanpay extension in the *"Install History"* table.
