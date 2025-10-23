@echo off
setlocal enabledelayedexpansion

REM SPLP Infrastructure Startup Script
REM Usage: start-infra.bat [dev|prod] [options]

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."

REM Change to project root directory
cd /d "%PROJECT_ROOT%"

REM Parse command line arguments
set "ENVIRONMENT=prod"
set "DETACHED=true"
set "PULL_IMAGES=false"
set "RECREATE=false"

:parse_args
if "%~1"=="" goto :execute_start
if /i "%~1"=="dev" (
    set "ENVIRONMENT=dev"
    shift
    goto :parse_args
)
if /i "%~1"=="prod" (
    set "ENVIRONMENT=prod"
    shift
    goto :parse_args
)
if /i "%~1"=="--foreground" (
    set "DETACHED=false"
    shift
    goto :parse_args
)
if /i "%~1"=="--pull" (
    set "PULL_IMAGES=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--recreate" (
    set "RECREATE=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--help" (
    goto :show_help
)
echo Unknown argument: %~1
goto :show_help

:execute_start
echo.
echo ========================================
echo SPLP Infrastructure Startup
echo ========================================
echo Environment: %ENVIRONMENT%
echo Detached Mode: %DETACHED%
echo Pull Images: %PULL_IMAGES%
echo Recreate Containers: %RECREATE%
echo ========================================
echo.

REM Check if Docker is installed and running
where docker >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker is not installed or not in PATH
    echo Please install Docker Desktop and ensure it's running
    exit /b 1
)

REM Check if Docker Compose is available
docker compose version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker Compose is not available
    echo Please ensure Docker Desktop is running and Docker Compose is installed
    exit /b 1
)

REM Check if Docker daemon is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker daemon is not running
    echo Please start Docker Desktop
    exit /b 1
)

REM Determine compose file
if "%ENVIRONMENT%"=="dev" (
    set "COMPOSE_FILE=docker-compose.dev.yml"
    set "PROJECT_NAME=splp-dev"
) else (
    set "COMPOSE_FILE=docker-compose.yml"
    set "PROJECT_NAME=splp"
)

if not exist "%COMPOSE_FILE%" (
    echo ERROR: Compose file %COMPOSE_FILE% not found
    echo Please ensure you're in the correct directory
    exit /b 1
)

echo Using compose file: %COMPOSE_FILE%
echo Project name: %PROJECT_NAME%
echo.

REM Build Docker Compose command
set "COMPOSE_CMD=docker compose -f %COMPOSE_FILE% -p %PROJECT_NAME%"

REM Pull images if requested
if "%PULL_IMAGES%"=="true" (
    echo Pulling latest images...
    %COMPOSE_CMD% pull
    if %errorlevel% neq 0 (
        echo Failed to pull images
        exit /b 1
    )
    echo.
)

REM Stop existing containers if recreate is requested
if "%RECREATE%"=="true" (
    echo Stopping existing containers...
    %COMPOSE_CMD% down
    echo.
)

REM Start infrastructure
echo Starting infrastructure containers...

if "%DETACHED%"=="true" (
    %COMPOSE_CMD% up -d
) else (
    echo Running in foreground mode. Press Ctrl+C to stop.
    %COMPOSE_CMD% up
)

set "START_EXIT_CODE=%errorlevel%"

if %START_EXIT_CODE% equ 0 (
    if "%DETACHED%"=="true" (
        echo.
        echo ========================================
        echo INFRASTRUCTURE STARTED SUCCESSFULLY
        echo ========================================
        echo.
        
        REM Show running containers
        echo Running containers:
        %COMPOSE_CMD% ps
        echo.
        
        REM Show service URLs
        echo Service URLs:
        echo - Kafka: localhost:9092
        echo - Cassandra: localhost:9042
        if "%ENVIRONMENT%"=="dev" (
            echo - Kafka UI: http://localhost:8080
            echo - Cassandra Web: http://localhost:3000
            echo - Prometheus: http://localhost:9090
            echo - Grafana: http://localhost:3001 (admin/admin)
            echo - Jaeger: http://localhost:16686
        )
        echo.
        
        echo Infrastructure is starting up...
        echo Use 'check-infra.bat' to verify when services are ready
        echo Use 'stop-infra.bat' to stop all services
        echo Use 'logs-infra.bat' to view logs
    )
) else (
    echo ========================================
    echo INFRASTRUCTURE STARTUP FAILED
    echo ========================================
    echo Exit code: %START_EXIT_CODE%
    echo.
    echo Troubleshooting tips:
    echo - Check if Docker Desktop is running
    echo - Ensure ports are not already in use
    echo - Check Docker logs: docker compose -f %COMPOSE_FILE% logs
    echo - Try recreating containers: start-infra.bat --recreate
)

exit /b %START_EXIT_CODE%

:show_help
echo.
echo SPLP Infrastructure Startup Script
echo.
echo Usage: start-infra.bat [environment] [options]
echo.
echo Environments:
echo   prod          Production environment (default)
echo   dev           Development environment with additional tools
echo.
echo Options:
echo   --foreground     Run in foreground (default: detached)
echo   --pull          Pull latest images before starting
echo   --recreate      Stop and recreate containers
echo   --help          Show this help message
echo.
echo Examples:
echo   start-infra.bat                    # Start production environment
echo   start-infra.bat dev                # Start development environment
echo   start-infra.bat --pull --recreate  # Pull images and recreate containers
echo   start-infra.bat dev --foreground   # Start dev environment in foreground
echo.
echo Services (Production):
echo   - Zookeeper (port 2181)
echo   - Kafka (port 9092, 9093)
echo   - Cassandra (port 9042, 7000)
echo.
echo Additional Services (Development):
echo   - Kafka UI (port 8080)
echo   - Cassandra Web (port 3000)
echo   - Redis (port 6379)
echo   - Prometheus (port 9090)
echo   - Grafana (port 3001)
echo   - Jaeger (port 16686)
echo.
echo Prerequisites:
echo   - Docker Desktop installed and running
echo   - Docker Compose available
echo   - Sufficient system resources (4GB+ RAM recommended)
echo.
exit /b 0