#!/bin/bash -e
if [ -L "$0" ] && [ -x "$(which readlink)" ]; then
	THIS_FILE="$(readlink -mn "$0")"
else
	THIS_FILE="$0"
fi
THIS_DIR="$(dirname "$THIS_FILE")"
cd "$THIS_DIR"

ASSETS_DIR="$THIS_DIR/src/assets"
mkdir -p "$ASSETS_DIR"
STYLES_DIR="$THIS_DIR"/src/styles
mkdir -p "$STYLES_DIR"
STYLES_DIR_ORIG="$THIS_DIR"/src/styles.orig
mkdir -p "$STYLES_DIR_ORIG"
ROBOTO_SCSS_URL='https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap'
ROBOTO_SCSS_FILENAME=roboto.scss
ROBOTO_SCSS_FILE="$STYLES_DIR/$ROBOTO_SCSS_FILENAME"
ROBOTO_SCSS_FILE_ORIG="$STYLES_DIR_ORIG/$ROBOTO_SCSS_FILENAME"
MATERIAL_SCSS_URL='https://fonts.googleapis.com/icon?family=Material+Icons'
MATERIAL_SCSS_FILENAME=material.scss
MATERIAL_SCSS_FILE="$STYLES_DIR/$MATERIAL_SCSS_FILENAME"
MATERIAL_SCSS_FILE_ORIG="$STYLES_DIR_ORIG/$MATERIAL_SCSS_FILENAME"


function dl_if_needed {
	URL=$1
	DEST=$2

	if [ -e "$DEST" ]; then
		echo "File $DEST already there, we don't download it again"
	else
		echo "Going to download from $URL to $DEST"
		curl "$URL" > "$DEST"
	fi
}

function dl_ttf {
	SCSS_FILE=$1
	for TTF_URL in $(grep 'src: url' "$SCSS_FILE" | cut -d '(' -f2 | cut -d ')' -f1); do
		echo "Required resource: $TTF_URL"
		TTF_FILENAME=$(basename "$TTF_URL")
		TTF_FILE="$ASSETS_DIR/$TTF_FILENAME"
		dl_if_needed "$TTF_URL" "$TTF_FILE"

		sed -i "s\\$TTF_URL\\../assets/$TTF_FILENAME\\" "$SCSS_FILE" # violation of DRY principle because it's annoying to compute relative paths otherwise
	done

}

dl_if_needed "$ROBOTO_SCSS_URL" "$ROBOTO_SCSS_FILE_ORIG"
cp "$ROBOTO_SCSS_FILE_ORIG" "$ROBOTO_SCSS_FILE"
dl_if_needed "$MATERIAL_SCSS_URL" "$MATERIAL_SCSS_FILE_ORIG"
cp "$MATERIAL_SCSS_FILE_ORIG" "$MATERIAL_SCSS_FILE"

dl_ttf "$ROBOTO_SCSS_FILE"
dl_ttf "$MATERIAL_SCSS_FILE"
