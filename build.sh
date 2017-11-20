#!/bin/bash

GIT_HUB_PROJECT_PATH=/home/agmolchanov/work/github/yandex-money-cms-v2-joomshopping
ARCHIVE_FILE_NAME=yandex-money-cms-v2-joomshopping.zip

if [ -f "$ARCHIVE_FILE_NAME" ]; then
    rm -rf "$ARCHIVE_FILE_NAME";
fi;

cd src
zip -9 -r ../yandex-money-cms-v2-joomshopping.zip ./*

cd ../
cp -r src "$GIT_HUB_PROJECT_PATH""/";
cp CHANGELOG.md "$GIT_HUB_PROJECT_PATH""/CHANGELOG.md";
cp LICENSE "$GIT_HUB_PROJECT_PATH""/LICENSE";
cp README.md "$GIT_HUB_PROJECT_PATH""/README.md";

mv "$ARCHIVE_FILE_NAME" "$GIT_HUB_PROJECT_PATH""/""$ARCHIVE_FILE_NAME";
