@echo off

cd /d %~dp0

DEL YandexKassa20.zip
"C:\Program Files\7-Zip\7z.exe" a -r YandexKassa20.zip .\src\*
