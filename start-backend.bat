@echo off
REM Sa'adatul D.I.Y - start the PHP backend for Live Server
REM Keep this window open while using the website.
cd /d "%~dp0"
echo Starting PHP backend on http://127.0.0.1:8000 ...
echo API check: http://127.0.0.1:8000/api/products.php
echo.
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t "%~dp0"
pause
