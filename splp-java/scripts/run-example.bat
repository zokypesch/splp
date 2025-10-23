@echo off
setlocal enabledelayedexpansion

REM SPLP Java Example Runner Script
REM Usage: run-example.bat [service1] [options]

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "JAVA_OPTS=-Xmx1g -XX:+UseG1GC"

REM Change to project root directory
cd /d "%PROJECT_ROOT%"

REM Parse command line arguments
set "SERVICE=service1"
set "PROFILE=default"
set "DEBUG=false"
set "WAIT_FOR_INFRA=true"

:parse_args
if "%~1"=="" goto :execute_run
if /i "%~1"=="service1" (
    set "SERVICE=service1"
    shift
    goto :parse_args
)
if /i "%~1"=="--profile" (
    set "PROFILE=%~2"
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--debug" (
    set "DEBUG=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--no-wait" (
    set "WAIT_FOR_INFRA=false"
    shift
    goto :parse_args
)
if /i "%~1"=="--help" (
    goto :show_help
)
echo Unknown argument: %~1
goto :show_help

:execute_run
echo.
echo ========================================
echo SPLP Java Example Runner
echo ========================================
echo Service: %SERVICE%
echo Profile: %PROFILE%
echo Debug Mode: %DEBUG%
echo Wait for Infrastructure: %WAIT_FOR_INFRA%
echo ========================================
echo.

REM Check if Java is installed
where java >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Java is not installed or not in PATH
    echo Please install Java 11 or higher and ensure it's in your PATH
    exit /b 1
)

REM Check if project is built
if not exist "target\classes" (
    echo Project not built. Building now...
    call "%SCRIPT_DIR%\build.bat" package --skip-tests
    if %errorlevel% neq 0 (
        echo Failed to build project
        exit /b 1
    )
)

REM Check infrastructure if needed
if "%WAIT_FOR_INFRA%"=="true" (
    echo Checking infrastructure status...
    call "%SCRIPT_DIR%\check-infra.bat"
    if %errorlevel% neq 0 (
        echo Infrastructure not ready. Starting infrastructure...
        call "%SCRIPT_DIR%\start-infra.bat"
        if %errorlevel% neq 0 (
            echo Failed to start infrastructure
            exit /b 1
        )
        
        echo Waiting for infrastructure to be ready...
        timeout /t 30 /nobreak >nul
        
        call "%SCRIPT_DIR%\check-infra.bat"
        if %errorlevel% neq 0 (
            echo Infrastructure still not ready after startup
            echo Please check Docker containers manually
            exit /b 1
        )
    )
    echo Infrastructure is ready!
    echo.
)

REM Set up environment variables
set "KAFKA_BOOTSTRAP_SERVERS=localhost:9092"
set "CASSANDRA_CONTACT_POINTS=localhost"
set "CASSANDRA_PORT=9042"
set "CASSANDRA_KEYSPACE=splp"
set "ENCRYPTION_KEY=your-32-character-encryption-key"

REM Set debug options if enabled
if "%DEBUG%"=="true" (
    set "JAVA_OPTS=%JAVA_OPTS% -agentlib:jdwp=transport=dt_socket,server=y,suspend=n,address=*:5005"
    echo Debug mode enabled. Connect debugger to port 5005
    echo.
)

REM Build classpath
set "CLASSPATH=target\classes"
for %%f in (target\dependency\*.jar) do (
    set "CLASSPATH=!CLASSPATH!;%%f"
)

REM Determine main class based on service
if /i "%SERVICE%"=="service1" (
    set "MAIN_CLASS=com.perlinsos.splp.examples.service1.Service1Application"
) else (
    echo Unknown service: %SERVICE%
    goto :show_help
)

echo Starting %SERVICE%...
echo Main Class: %MAIN_CLASS%
echo Java Options: %JAVA_OPTS%
echo.

REM Run the application
java %JAVA_OPTS% -cp "%CLASSPATH%" %MAIN_CLASS%
set "RUN_EXIT_CODE=%errorlevel%"

echo.
if %RUN_EXIT_CODE% equ 0 (
    echo ========================================
    echo APPLICATION COMPLETED SUCCESSFULLY
    echo ========================================
) else (
    echo ========================================
    echo APPLICATION FAILED
    echo ========================================
    echo Exit code: %RUN_EXIT_CODE%
    echo.
    echo Troubleshooting tips:
    echo - Check if Kafka and Cassandra are running
    echo - Verify environment variables are set correctly
    echo - Check application logs for error details
    echo - Ensure encryption key is properly configured
)

exit /b %RUN_EXIT_CODE%

:show_help
echo.
echo SPLP Java Example Runner Script
echo.
echo Usage: run-example.bat [service] [options]
echo.
echo Services:
echo   service1       Run Service 1 - Dukcapil Service (default)
echo.
echo Options:
echo   --profile NAME    Use specific configuration profile
echo   --debug          Enable debug mode (opens port 5005 for debugger)
echo   --no-wait        Don't wait for infrastructure to be ready
echo   --help           Show this help message
echo.
echo Environment Variables:
echo   KAFKA_BOOTSTRAP_SERVERS    Kafka bootstrap servers (default: localhost:9092)
echo   CASSANDRA_CONTACT_POINTS   Cassandra contact points (default: localhost)
echo   CASSANDRA_PORT            Cassandra port (default: 9042)
echo   CASSANDRA_KEYSPACE        Cassandra keyspace (default: splp)
echo   ENCRYPTION_KEY            32-character encryption key
echo.
echo Examples:
echo   run-example.bat                    # Run service1 with default settings
echo   run-example.bat service1 --debug  # Run service1 with debug mode
echo   run-example.bat --no-wait         # Run without checking infrastructure
echo.
echo Prerequisites:
echo   - Java 11 or higher installed
echo   - Project built (will auto-build if needed)
echo   - Kafka and Cassandra running (will auto-start if needed)
echo.
exit /b 0