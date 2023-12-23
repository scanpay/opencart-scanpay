#!/bin/bash
set -e

compress() {
    echo -n "compressing $1..."
    zopfli --i100 "$1"
    brotli --best -w 0 -o "$1.br" "$1"
    touch -r "$1" -c "$1".{br,gz}
    [[ $(stat -c%s "$1.gz") -ge $(stat -c%s "$1.br") ]] || rm "$1.br"
    printf '\033[0;32mOK\033[0m\n'
}

DIR="$( cd "$(dirname "$0")" ; pwd -P )"
mkdir -p "$DIR/.ocmod"
rsync -rptgD --delete "$DIR/upload/" "$DIR/.ocmod/upload"

# Insert version number
read -rp "Write the version number (x.y.z): " version

if [ -z "$version" ]; then
    version='x.y.z'
    echo "No version number. Using '$version' and the test environment"
    sed -i 's/api\.scanpay\.dk/api\.scanpay\.dev/g' ".ocmod/upload/system/library/scanpay/client.php"
    find .ocmod/ -type f -name "*.php" -exec sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' {} +
    find .ocmod/ -type f -name "*.twig" -exec sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' {} +
    find .ocmod/ -type f -name "*.js" -exec sed -i 's/dashboard\.scanpay\.dk/dashboard\.scanpay\.dev/g' {} +
fi;

# Replace EXTENSION_VERSION with $version
find .ocmod/ -type f -name "*.php" -exec sed -i "s/EXTENSION_VERSION/$version/g" {} +
find .ocmod/ -type f -name "*.js" -exec sed -i "s/EXTENSION_VERSION/$version/g" {} +

# Minify JavaScript
find -L .ocmod -iname '*.js' | while read -r f; do
    node_modules/.bin/terser "$f" --mangle -o "$f"
    compress "$f"
done

# Minify stylesheet (Sass)
find -L .ocmod -iname '*.scss' | while read -r f; do
    node_modules/.bin/sass "${f%.*}.scss" "${f%.*}.css"
    compress "${f%.*}.css"
done

cd "$DIR/.ocmod/"
zip -r "$DIR/opencart-scanpay-$version.ocmod.zip" "upload/"
