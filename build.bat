@echo off

cd /d %~dp0

DEL YandexKassa.zip
"C:\Program Files\7-Zip\7z.exe" a -r YandexKassa.zip .\src\*
