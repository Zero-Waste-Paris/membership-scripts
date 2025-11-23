#!/bin/bash
set -e

# Find out directory of current script
# to make it possible to run this script from any location
if [ -L "$0" ] && [ -x $(which readlink) ]; then
  THIS_FILE="$(readlink -mn "$0")"
else
  THIS_FILE="$0"
fi
SCRIPT_DIR="$( realpath "$(dirname "$THIS_FILE")")"


export APP_ENV=test

WIREMOCK_VERSION=3.13.2
BIN_DIR="$SCRIPT_DIR/../bin"
WIREMOCK_JAR="$BIN_DIR/wiremock.$WIREMOCK_VERSION.jar"
WIREMOCK_PORT=8081
mkdir -p "$BIN_DIR"
if [ ! -e "$WIREMOCK_JAR" ]; then
  echo "Downloading wiremock-standalone jar"
  curl "https://repo1.maven.org/maven2/org/wiremock/wiremock-standalone/$WIREMOCK_VERSION/wiremock-standalone-$WIREMOCK_VERSION.jar" > "$WIREMOCK_JAR"
fi

curl http://localhost:$WIREMOCK_PORT/__admin/shutdown || true # try to kill an old process if any
java -jar "$WIREMOCK_JAR" --port "$WIREMOCK_PORT" &

cd "$SCRIPT_DIR"/../symfony-server/

echo "Creating the test database"
php bin/console --env=test doctrine:database:create
echo "Dropping the schema of the test database"
php bin/console --env=test doctrine:schema:drop --force
echo "Creating the schema of the test database"
php bin/console --env=test doctrine:schema:create
echo "Initialize some values in the test database"
php bin/console doctrine:database:initialize-last-successful-run-date 2022-01-01
echo testpassword | php bin/console user:add testuser

APP_ENV=test XDEBUG_MODE=coverage php bin/phpunit --coverage-html coverage $@
