#!/bin/bash
set -e

DIR="$( cd "$(dirname "$0")" ; pwd -P )/"
rm -rf ".ocmod/"
mkdir ".ocmod"
cp -r "upload/" ".ocmod/upload"

read -rp "Use test environment (api.scanpay.dev)? (y/N): " testing
if [[ $testing =~ [yY] ]]; then
    sed -i 's/api\.scanpay\.dk/api\.scanpay\.dev/g' ".ocmod/upload/system/library/scanpay/client.php"
    sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' ".ocmod/upload/admin/controller/extension/payment/scanpay.php"
    sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' ".ocmod/upload/admin/view/template/extension/payment/scanpay.twig"
    sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' ".ocmod/upload/admin/view/template/extension/payment/scanpay_order.twig"
fi; echo

cd ".ocmod/"
zip -r "$DIR/opencart-scanpay-2.0.0-alpha6.ocmod.zip" "upload/"
rm -rf ".ocmod/"
