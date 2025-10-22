@echo off
echo ===============================================================================
echo                        SPLP-NET Example Services Runner
echo ===============================================================================
echo.
echo This script will start all services in separate windows:
echo   1. Command Center (Message Router)
echo   2. Service 1 (Dukcapil - Population Verification)
echo   3. Service 2 (Kemensos - Final Decision)
echo   4. Publisher (Social Assistance Request)
echo.
echo Make sure you have:
echo   - Kafka running on localhost:9092
echo   - Cassandra running on localhost:9042
echo   - ENCRYPTION_KEY environment variable set
echo.

REM Check if ENCRYPTION_KEY is set
if "%ENCRYPTION_KEY%"=="" (
    echo WARNING: ENCRYPTION_KEY environment variable is not set!
    echo Generating a new key for this session...
    
    REM Generate a simple key (in production, use proper key generation)
    set ENCRYPTION_KEY=abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789
    echo Generated key: %ENCRYPTION_KEY%
    echo.
    echo IMPORTANT: Use this same key for all services in production!
    echo.
)

echo Using ENCRYPTION_KEY: %ENCRYPTION_KEY%
echo.

REM Build the solution first
echo Building solution...
dotnet build
if %ERRORLEVEL% neq 0 (
    echo Build failed! Please fix compilation errors.
    pause
    exit /b 1
)
echo Build successful!
echo.

echo Starting services...
echo.

REM Start Command Center
echo Starting Command Center...
start "Command Center" cmd /k "cd /d %~dp0CommandCenter && echo Starting Command Center... && dotnet run"
timeout /t 3 /nobreak >nul

REM Start Service 1
echo Starting Service 1 (Dukcapil)...
start "Service 1 - Dukcapil" cmd /k "cd /d %~dp0Service1 && echo Starting Service 1 (Dukcapil)... && dotnet run"
timeout /t 3 /nobreak >nul

REM Start Service 2
echo Starting Service 2 (Kemensos)...
start "Service 2 - Kemensos" cmd /k "cd /d %~dp0Service2 && echo Starting Service 2 (Kemensos)... && dotnet run"
timeout /t 3 /nobreak >nul

echo.
echo All services are starting...
echo Wait for all services to show "ready" status before running the publisher.
echo.
echo Press any key to start the Publisher (or Ctrl+C to cancel)...
pause >nul

REM Start Publisher
echo Starting Publisher...
start "Publisher - Kemensos Request" cmd /k "cd /d %~dp0Publisher && echo Starting Publisher... && dotnet run"

echo.
echo ===============================================================================
echo All services have been started in separate windows:
echo   - Command Center: Routes messages between services
echo   - Service 1: Dukcapil population verification
echo   - Service 2: Kemensos final decision
echo   - Publisher: Sends social assistance requests
echo.
echo Check each window for service status and message flow.
echo Close individual windows to stop services, or close this window to exit.
echo ===============================================================================
echo.
pause
