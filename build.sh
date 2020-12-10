#!/bin/bash

GIT_HUB_PROJECT_PATH="$( cd "$(dirname "$0")" ; pwd )"
ARCHIVE_FILE_NAME=cms-joomshopping.zip

if [ -f "$ARCHIVE_FILE_NAME" ]; then
    rm -rf "$ARCHIVE_FILE_NAME";
fi;

cd src
"C:\Program Files\7-Zip\7z.exe" a -r "../cms-joomshopping.zip" ./*

cd ../
rsync -av --delete src "$GIT_HUB_PROJECT_PATH";
rsync -av --delete CHANGELOG.md "$GIT_HUB_PROJECT_PATH""/CHANGELOG.md";
rsync -av --delete LICENSE "$GIT_HUB_PROJECT_PATH""/LICENSE";
rsync -av --delete README.md "$GIT_HUB_PROJECT_PATH""/README.md";

mv "$ARCHIVE_FILE_NAME" "$GIT_HUB_PROJECT_PATH""/""$ARCHIVE_FILE_NAME";
