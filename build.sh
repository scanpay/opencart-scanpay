#!/bin/bash
set -e

DIR="$( cd "$(dirname "$0")" ; pwd -P )/"
rm -rf ".ocmod/"
mkdir ".ocmod"
cp -r "upload/" ".ocmod/upload"

read -p "Use test environment (api.scanpay.dev)? (y/N): " testing
if [[ $testing =~ [yY] ]]; then
    sed -i 's/api\.scanpay\.dk/api\.scanpay\.dev/g' ".ocmod/upload/system/library/scanpay/client.php"
fi; echo

cd ".ocmod/"
zip -r "$DIR/opencart-scanpay-2.0.0-alpha5.ocmod.zip" "upload/"
rm -rf ".ocmod/"
