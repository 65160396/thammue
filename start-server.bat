@echo off
set DOCROOT=N:\ProjectJob\ThamMue   ← ต้องเป็นโฟลเดอร์รากเดียวกัน
taskkill /IM php.exe /F >nul 2>&1
cd /d "%DOCROOT%"
php -S localhost:8000 -t "%DOCROOT%"