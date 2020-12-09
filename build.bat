@echo off

cd /d %~dp0

DEL YooKassa20.zip
"C:\Program Files\7-Zip\7z.exe" a -r YooKassa20.zip .\src\*
