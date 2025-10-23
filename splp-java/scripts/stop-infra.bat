@echo off
setlocal enabledelayedexpansion

REM SPLP Infrastructure Stop Script
REM Usage: stop-infra.bat [dev|prod] [options]

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."

REM Change to project root directory
cd /d "%PROJECT_ROOT%"

REM Parse command line arguments
set "ENVIRONMENT=prod"
set "REMOVE_VOLUMES=false"
set "REMOVE_IMAGES=false"

:parse_args
if "%~1"=="" goto :execute_stop
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
if /i "%~1"=="--remove-volumes" (
    set "REMOVE_VOLUMES=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--remove-images" (
    set "REMOVE_IMAGES=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--help" (
    goto :show_help
)
echo Unknown argument: %~1
goto :show_help

:execute_stop
echo.
echo ========================================
echo SPLP Infrastructure Stop
echo ========================================
echo Environment: %ENVIRONMENT%
echo Remove Volumes: %REMOVE_VOLUMES%
echo Remove Images: %REMOVE_IMAGES%
echo ========================================
echo.

REM Check if Docker is available
where docker >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker is not installed or not in PATH
    exit /b 1
)

docker compose version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker Compose is not available
    exit /b 1
)

REM Determine compose file and project name
if "%ENVIRONMENT%"=="dev" (
    set "COMPOSE_FILE=docker-compose.dev.yml"
    set "PROJECT_NAME=splp-dev"
) else (
    set "COMPOSE_FILE=docker-compose.yml"
    set "PROJECT_NAME=splp"
)

if not exist "%COMPOSE_FILE%" (
    echo ERROR: Compose file %COMPOSE_FILE% not found
    exit /b 1
)

echo Using compose file: %COMPOSE_FILE%
echo Project name: %PROJECT_NAME%
echo.

REM Build Docker Compose command
set "COMPOSE_CMD=docker compose -f %COMPOSE_FILE% -p %PROJECT_NAME%"

REM Check if containers are running
%COMPOSE_CMD% ps -q >nul 2>&1
if %errorlevel% neq 0 (
    echo No containers found for project %PROJECT_NAME%
    echo Infrastructure may already be stopped
    exit /b 0
)

REM Show current status
echo Current container status:
%COMPOSE_CMD% ps
echo.

REM Stop containers
echo Stopping containers...
%COMPOSE_CMD% stop
set "STOP_EXIT_CODE=%errorlevel%"

if %STOP_EXIT_CODE% neq 0 (
    echo Failed to stop containers gracefully, forcing shutdown...
    %COMPOSE_CMD% kill
)

REM Remove containers
echo Removing containers...
if "%REMOVE_VOLUMES%"=="true" (
    %COMPOSE_CMD% down -v
    echo Volumes removed
) else (
    %COMPOSE_CMD% down
)

set "DOWN_EXIT_CODE=%errorlevel%"

REM Remove images if requested
if "%REMOVE_IMAGES%"=="true" (
    echo Removing images...
    %COMPOSE_CMD% down --rmi all
    if %errorlevel% equ 0 (
        echo Images removed
    ) else (
        echo Warning: Some images could not be removed
    )
)

REM Clean up orphaned containers
echo Cleaning up orphaned containers...
docker container prune -f >nul 2>&1

REM Clean up unused networks
echo Cleaning up unused networks...
docker network prune -f >nul 2>&1

echo.
if %DOWN_EXIT_CODE% equ 0 (
    echo ========================================
    echo INFRASTRUCTURE STOPPED SUCCESSFULLY
    echo ========================================
    echo.
    echo All containers have been stopped and removed
    if "%REMOVE_VOLUMES%"=="true" (
        echo All volumes have been removed
        echo WARNING: All data has been deleted!
    ) else (
        echo Data volumes preserved
        echo Use --remove-volumes to delete all data
    )
    
    if "%REMOVE_IMAGES%"=="true" (
        echo All images have been removed
    ) else (
        echo Images preserved for faster startup
        echo Use --remove-images to delete all images
    )
) else (
    echo ========================================
    echo INFRASTRUCTURE STOP FAILED
    echo ========================================
    echo Exit code: %DOWN_EXIT_CODE%
    echo.
    echo Some containers may still be running
    echo Try running the command again or check Docker Desktop
)

exit /b %DOWN_EXIT_CODE%

:show_help
echo.
echo SPLP Infrastructure Stop Script
echo.
echo Usage: stop-infra.bat [environment] [options]
echo.
echo Environments:
echo   prod              Production environment (default)
echo   dev               Development environment
echo.
echo Options:
echo   --remove-volumes     Remove all data volumes (WARNING: deletes all data)
echo   --remove-images      Remove all Docker images
echo   --help              Show this help message
echo.
echo Examples:
echo   stop-infra.bat                      # Stop production environment
echo   stop-infra.bat dev                  # Stop development environment
echo   stop-infra.bat --remove-volumes     # Stop and delete all data
echo   stop-infra.bat dev --remove-images  # Stop dev and remove images
echo.
echo What this script does:
echo   1. Stops all running containers gracefully
echo   2. Removes containers (but preserves volumes by default)
echo   3. Cleans up orphaned containers and networks
echo   4. Optionally removes volumes and images
echo.
echo Data Preservation:
echo   - By default, data volumes are preserved
echo   - Use --remove-volumes to delete all data (Kafka topics, Cassandra data, etc.)
echo   - Use --remove-images to delete Docker images (slower next startup)
echo.
echo Prerequisites:
echo   - Docker Desktop running
echo   - Docker Compose available
echo.
exit /b 0