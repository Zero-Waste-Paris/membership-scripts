#!/bin/bash -ex
# Just for local development and testing
if [ -L "$0" ] && [ -x "$(which readlink)" ]; then
	THIS_FILE="$(readlink -mn "$0")"
else
	THIS_FILE="$0"
fi
THIS_DIR="$(dirname "$THIS_FILE")"
PHP_PUBLIC_PATH="$THIS_DIR/../symfony-server/public"

pushd "$THIS_DIR"
#./dl_styles.sh
ng build -c development
popd

rm -f "$PHP_PUBLIC_PATH"/{index.html,main.*.js,polyfills.*.js,runtime.*.js,styles.*.css,*map}
DIST_DIR="$THIS_DIR/dist/angular-front/browser"
rm "$DIST_DIR/favicon.ico"
cp -r "$DIST_DIR"/* "$PHP_PUBLIC_PATH"
