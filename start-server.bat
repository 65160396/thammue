@echo off
set DOCROOT=D:\New folder (3)\thammue
taskkill /IM php.exe /F >nul 2>&1
cd /d "%DOCROOT%"
echo Serving from: %cd%
php -S localhost:8000 -t "%DOCROOT%"
