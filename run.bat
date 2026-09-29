@echo off
title NexusAI Task Master Launcher
echo ============================================================
echo           NexusAI Task Master - Starting Server
echo ============================================================
echo.

set PHP_EXE=php
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_EXE="C:\xampp\php\php.exe"
    ) else if exist "D:\xampp\php\php.exe" (
        set PHP_EXE="D:\xampp\php\php.exe"
    ) else if exist "C:\php\php.exe" (
        set PHP_EXE="C:\php\php.exe"
    ) else (
        echo [ERROR] PHP executable not found!
        echo Please ensure PHP or XAMPP is installed.
        pause
        exit /b 1
    )
)

echo [INFO] Using PHP from: %PHP_EXE%
echo [INFO] Starting server at http://localhost:8080 ...
echo [INFO] Press Ctrl+C in this window to stop the server.
echo.

start "" "http://localhost:8080"
%PHP_EXE% -S localhost:8080 -t "%~dp0"
pause
