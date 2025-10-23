@echo off
setlocal enabledelayedexpansion

REM SPLP Infrastructure Health Check Script
REM Usage: check-infra.bat [dev|prod] [options]

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."

REM Change to project root directory
cd /d "%PROJECT_ROOT%"

REM Parse command line arguments
set "ENVIRONMENT=prod"
set "VERBOSE=false"
set "WAIT_TIME=0"

:parse_args
if "%~1"=="" goto :execute_check
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
if /i "%~1"=="--verbose" (
    set "VERBOSE=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--wait" (
    set "WAIT_TIME=%~2"
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--help" (
    goto :show_help
)
echo Unknown argument: %~1
goto :show_help

:execute_check
echo.
echo ========================================
echo SPLP Infrastructure Health Check
echo ========================================
echo Environment: %ENVIRONMENT%
echo Verbose: %VERBOSE%
if %WAIT_TIME% gtr 0 (
    echo Wait Time: %WAIT_TIME% seconds
)
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

set "COMPOSE_CMD=docker compose -f %COMPOSE_FILE% -p %PROJECT_NAME%"

REM Wait if requested
if %WAIT_TIME% gtr 0 (
    echo Waiting %WAIT_TIME% seconds before checking...
    timeout /t %WAIT_TIME% /nobreak >nul
    echo.
)

REM Check container status
echo Checking container status...
%COMPOSE_CMD% ps
echo.

REM Initialize health check results
set "ALL_HEALTHY=true"
set "KAFKA_HEALTHY=false"
set "CASSANDRA_HEALTHY=false"
set "ZOOKEEPER_HEALTHY=false"

REM Check Zookeeper
echo Checking Zookeeper health...
docker exec %PROJECT_NAME%-zookeeper-1 nc -z localhost 2181 >nul 2>&1
if %errorlevel% equ 0 (
    echo [✓] Zookeeper is healthy
    set "ZOOKEEPER_HEALTHY=true"
) else (
    echo [✗] Zookeeper is not responding
    set "ALL_HEALTHY=false"
)

REM Check Kafka
echo Checking Kafka health...
docker exec %PROJECT_NAME%-kafka-1 kafka-broker-api-versions --bootstrap-server localhost:9092 >nul 2>&1
if %errorlevel% equ 0 (
    echo [✓] Kafka is healthy
    set "KAFKA_HEALTHY=true"
) else (
    echo [✗] Kafka is not responding
    set "ALL_HEALTHY=false"
)

REM Check Cassandra
echo Checking Cassandra health...
docker exec %PROJECT_NAME%-cassandra-1 cqlsh -e "describe keyspaces" >nul 2>&1
if %errorlevel% equ 0 (
    echo [✓] Cassandra is healthy
    set "CASSANDRA_HEALTHY=true"
) else (
    echo [✗] Cassandra is not responding
    set "ALL_HEALTHY=false"
)

REM Additional checks for development environment
if "%ENVIRONMENT%"=="dev" (
    echo.
    echo Checking development services...
    
    REM Check Kafka UI
    powershell -Command "try { $response = Invoke-WebRequest -Uri 'http://localhost:8080' -TimeoutSec 5 -UseBasicParsing; if ($response.StatusCode -eq 200) { exit 0 } else { exit 1 } } catch { exit 1 }" >nul 2>&1
    if %errorlevel% equ 0 (
        echo [✓] Kafka UI is accessible
    ) else (
        echo [✗] Kafka UI is not accessible
    )
    
    REM Check Cassandra Web
    powershell -Command "try { $response = Invoke-WebRequest -Uri 'http://localhost:3000' -TimeoutSec 5 -UseBasicParsing; if ($response.StatusCode -eq 200) { exit 0 } else { exit 1 } } catch { exit 1 }" >nul 2>&1
    if %errorlevel% equ 0 (
        echo [✓] Cassandra Web is accessible
    ) else (
        echo [✗] Cassandra Web is not accessible
    )
)

echo.

REM Verbose output
if "%VERBOSE%"=="true" (
    echo ========================================
    echo DETAILED HEALTH INFORMATION
    echo ========================================
    echo.
    
    echo Container Resource Usage:
    docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}" %PROJECT_NAME%-zookeeper-1 %PROJECT_NAME%-kafka-1 %PROJECT_NAME%-cassandra-1 2>nul
    echo.
    
    if "%KAFKA_HEALTHY%"=="true" (
        echo Kafka Topics:
        docker exec %PROJECT_NAME%-kafka-1 kafka-topics --bootstrap-server localhost:9092 --list 2>nul
        echo.
    )
    
    if "%CASSANDRA_HEALTHY%"=="true" (
        echo Cassandra Keyspaces:
        docker exec %PROJECT_NAME%-cassandra-1 cqlsh -e "describe keyspaces" 2>nul
        echo.
    )
    
    echo Recent Container Logs (last 10 lines):
    echo --- Zookeeper ---
    docker logs --tail 10 %PROJECT_NAME%-zookeeper-1 2>nul
    echo.
    echo --- Kafka ---
    docker logs --tail 10 %PROJECT_NAME%-kafka-1 2>nul
    echo.
    echo --- Cassandra ---
    docker logs --tail 10 %PROJECT_NAME%-cassandra-1 2>nul
    echo.
)

REM Final status
echo ========================================
if "%ALL_HEALTHY%"=="true" (
    echo INFRASTRUCTURE STATUS: HEALTHY ✓
    echo ========================================
    echo.
    echo All core services are running and responding:
    echo - Zookeeper: Ready
    echo - Kafka: Ready  
    echo - Cassandra: Ready
    echo.
    echo Service endpoints:
    echo - Kafka Bootstrap: localhost:9092
    echo - Cassandra CQL: localhost:9042
    if "%ENVIRONMENT%"=="dev" (
        echo - Kafka UI: http://localhost:8080
        echo - Cassandra Web: http://localhost:3000
    )
    echo.
    echo Infrastructure is ready for applications!
    exit /b 0
) else (
    echo INFRASTRUCTURE STATUS: UNHEALTHY ✗
    echo ========================================
    echo.
    echo Some services are not responding:
    if "%ZOOKEEPER_HEALTHY%"=="false" echo - Zookeeper: Not Ready
    if "%KAFKA_HEALTHY%"=="false" echo - Kafka: Not Ready
    if "%CASSANDRA_HEALTHY%"=="false" echo - Cassandra: Not Ready
    echo.
    echo Troubleshooting steps:
    echo 1. Check if containers are running: docker compose -f %COMPOSE_FILE% ps
    echo 2. Check container logs: docker compose -f %COMPOSE_FILE% logs
    echo 3. Restart infrastructure: stop-infra.bat ^&^& start-infra.bat
    echo 4. Check system resources (CPU, memory, disk space)
    echo.
    exit /b 1
)

:show_help
echo.
echo SPLP Infrastructure Health Check Script
echo.
echo Usage: check-infra.bat [environment] [options]
echo.
echo Environments:
echo   prod          Production environment (default)
echo   dev           Development environment
echo.
echo Options:
echo   --verbose        Show detailed health information
echo   --wait SECONDS   Wait specified seconds before checking
echo   --help          Show this help message
echo.
echo Examples:
echo   check-infra.bat                    # Quick health check
echo   check-infra.bat --verbose          # Detailed health check
echo   check-infra.bat dev --wait 30      # Wait 30s then check dev environment
echo.
echo What this script checks:
echo   - Container status and health
echo   - Zookeeper connectivity (port 2181)
echo   - Kafka broker availability (port 9092)
echo   - Cassandra CQL interface (port 9042)
echo   - Web UIs accessibility (dev environment only)
echo.
echo Exit codes:
echo   0 - All services healthy
echo   1 - One or more services unhealthy
echo.
echo Prerequisites:
echo   - Docker Desktop running
echo   - Infrastructure containers started
echo.
exit /b 0