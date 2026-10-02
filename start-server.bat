@echo off
rem Starts the SpaaLita API with PHP 8.2+ (Laravel 12 does not run on XAMPP's PHP 7).
rem Usage: start-server.bat [port]        (default port: 5000)
rem PHP is taken from SPAALITA_PHP, then C:\php82\php.exe, then "php" on the PATH.
setlocal

set "PORT=%~1"
if "%PORT%"=="" set "PORT=5000"

set "PHP=%SPAALITA_PHP%"
if "%PHP%"=="" if exist "C:\php82\php.exe" set "PHP=C:\php82\php.exe"
if "%PHP%"=="" set "PHP=php"

"%PHP%" -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);" 2>nul
if errorlevel 1 (
    echo PHP 8.2 or newer is required. Install it in C:\php82 or set SPAALITA_PHP to its php.exe.
    exit /b 1
)

cd /d "%~dp0"
"%PHP%" artisan serve --port=%PORT%
