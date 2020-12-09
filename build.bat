@echo off

cd /d %~dp0

DEL YooKassa.zip
"C:\Program Files\7-Zip\7z.exe" a -r YooKassa.zip .\src\*
