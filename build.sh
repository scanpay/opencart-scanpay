#!/bin/bash
set -e

DIR="$( cd "$(dirname "$0")" ; pwd -P )"
mkdir -p "$DIR/.ocmod"
rsync -av "$DIR/upload/" "$DIR/.ocmod/upload"

read -rp "Use test environment (api.scanpay.dev)? (y/N): " testing
if [[ $testing =~ [yY] ]]; then
    sed -i 's/api\.scanpay\.dk/api\.scanpay\.dev/g' ".ocmod/upload/system/library/scanpay/client.php"
    sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' ".ocmod/upload/admin/controller/extension/payment/scanpay.php"
    sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' ".ocmod/upload/admin/view/template/extension/payment/scanpay/settings.twig"
    sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' ".ocmod/upload/admin/view/template/extension/payment/scanpay/settings_applepay.twig"
    sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' ".ocmod/upload/admin/view/template/extension/payment/scanpay/settings_mobilepay.twig"
    sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' ".ocmod/upload/admin/view/javascript/scanpay/order.js"
fi; echo

cd "$DIR/.ocmod/"
zip -r "$DIR/opencart-scanpay-2.1.1-alpha6.ocmod.zip" "upload/"
