@echo off
setlocal enabledelayedexpansion

REM SPLP Java Test Runner Script
REM Usage: test.bat [unit|integration|all] [options]

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "MAVEN_OPTS=-Xmx2g -XX:+UseG1GC"

REM Change to project root directory
cd /d "%PROJECT_ROOT%"

REM Parse command line arguments
set "TEST_TYPE=all"
set "VERBOSE=false"
set "COVERAGE=false"
set "PARALLEL=false"
set "FAIL_FAST=false"
set "PROFILE=default"

:parse_args
if "%~1"=="" goto :execute_test
if /i "%~1"=="unit" (
    set "TEST_TYPE=unit"
    shift
    goto :parse_args
)
if /i "%~1"=="integration" (
    set "TEST_TYPE=integration"
    shift
    goto :parse_args
)
if /i "%~1"=="all" (
    set "TEST_TYPE=all"
    shift
    goto :parse_args
)
if /i "%~1"=="--verbose" (
    set "VERBOSE=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--coverage" (
    set "COVERAGE=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--parallel" (
    set "PARALLEL=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--fail-fast" (
    set "FAIL_FAST=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--profile" (
    set "PROFILE=%~2"
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--help" (
    goto :show_help
)
echo Unknown argument: %~1
goto :show_help

:execute_test
echo.
echo ========================================
echo SPLP Java Test Runner
echo ========================================
echo Test Type: %TEST_TYPE%
echo Verbose: %VERBOSE%
echo Coverage: %COVERAGE%
echo Parallel: %PARALLEL%
echo Fail Fast: %FAIL_FAST%
echo Profile: %PROFILE%
echo ========================================
echo.

REM Check if Maven is installed
where mvn >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Maven is not installed or not in PATH
    echo Please install Maven and ensure it's in your PATH
    exit /b 1
)

REM Check if Java is installed
where java >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Java is not installed or not in PATH
    echo Please install Java 11 or higher and ensure it's in your PATH
    exit /b 1
)

REM Check infrastructure for integration tests
if "%TEST_TYPE%"=="integration" (
    echo Checking infrastructure for integration tests...
    call "%SCRIPT_DIR%\check-infra.bat" >nul 2>&1
    if %errorlevel% neq 0 (
        echo Infrastructure not ready. Starting infrastructure...
        call "%SCRIPT_DIR%\start-infra.bat" >nul
        if %errorlevel% neq 0 (
            echo Failed to start infrastructure
            exit /b 1
        )
        
        echo Waiting for infrastructure to be ready...
        timeout /t 30 /nobreak >nul
        
        call "%SCRIPT_DIR%\check-infra.bat" >nul 2>&1
        if %errorlevel% neq 0 (
            echo Infrastructure still not ready
            echo Please start infrastructure manually: start-infra.bat
            exit /b 1
        )
    )
    echo Infrastructure is ready!
    echo.
) else if "%TEST_TYPE%"=="all" (
    echo Checking infrastructure for integration tests...
    call "%SCRIPT_DIR%\check-infra.bat" >nul 2>&1
    if %errorlevel% neq 0 (
        echo Infrastructure not ready. Starting infrastructure...
        call "%SCRIPT_DIR%\start-infra.bat" >nul
        if %errorlevel% neq 0 (
            echo Failed to start infrastructure
            exit /b 1
        )
        
        echo Waiting for infrastructure to be ready...
        timeout /t 30 /nobreak >nul
        
        call "%SCRIPT_DIR%\check-infra.bat" >nul 2>&1
        if %errorlevel% neq 0 (
            echo Infrastructure still not ready
            echo Will skip integration tests
            set "TEST_TYPE=unit"
        )
    )
    echo.
)

REM Build Maven command
set "MVN_CMD=mvn"

REM Set test goals based on type
if "%TEST_TYPE%"=="unit" (
    set "MVN_CMD=!MVN_CMD! test"
    set "TEST_INCLUDES=-Dtest=**/*Test.java"
) else if "%TEST_TYPE%"=="integration" (
    set "MVN_CMD=!MVN_CMD! verify"
    set "TEST_INCLUDES=-Dtest=**/*IntegrationTest.java"
) else (
    set "MVN_CMD=!MVN_CMD! verify"
    set "TEST_INCLUDES="
)

REM Add test includes if specified
if not "%TEST_INCLUDES%"=="" (
    set "MVN_CMD=!MVN_CMD! %TEST_INCLUDES%"
)

REM Add coverage if requested
if "%COVERAGE%"=="true" (
    set "MVN_CMD=!MVN_CMD! jacoco:report"
)

REM Add verbose output
if "%VERBOSE%"=="true" (
    set "MVN_CMD=!MVN_CMD! -X"
) else (
    set "MVN_CMD=!MVN_CMD! -q"
)

REM Add parallel execution
if "%PARALLEL%"=="true" (
    set "MVN_CMD=!MVN_CMD! -T 1C"
)

REM Add fail fast
if "%FAIL_FAST%"=="true" (
    set "MVN_CMD=!MVN_CMD! -Dmaven.test.failure.ignore=false"
) else (
    set "MVN_CMD=!MVN_CMD! -Dmaven.test.failure.ignore=true"
)

REM Add profile
if not "%PROFILE%"=="default" (
    set "MVN_CMD=!MVN_CMD! -P%PROFILE%"
)

REM Add additional test properties
set "MVN_CMD=!MVN_CMD! -Dmaven.compiler.source=11 -Dmaven.compiler.target=11"
set "MVN_CMD=!MVN_CMD! -Djunit.jupiter.execution.parallel.enabled=true"
set "MVN_CMD=!MVN_CMD! -Djunit.jupiter.execution.parallel.mode.default=concurrent"

echo Executing: %MVN_CMD%
echo.

REM Execute tests
%MVN_CMD%
set "TEST_EXIT_CODE=%errorlevel%"

echo.
if %TEST_EXIT_CODE% equ 0 (
    echo ========================================
    echo TESTS PASSED ✓
    echo ========================================
    
    REM Show test results
    if exist "target\surefire-reports" (
        echo.
        echo Test Results Summary:
        for /f "tokens=*" %%i in ('dir /b target\surefire-reports\TEST-*.xml 2^>nul') do (
            echo - %%i
        )
    )
    
    REM Show coverage report if generated
    if "%COVERAGE%"=="true" (
        if exist "target\site\jacoco\index.html" (
            echo.
            echo Coverage Report: target\site\jacoco\index.html
            echo Opening coverage report...
            start "" "target\site\jacoco\index.html"
        )
    )
    
    echo.
    echo All tests completed successfully!
) else (
    echo ========================================
    echo TESTS FAILED ✗
    echo ========================================
    echo Exit code: %TEST_EXIT_CODE%
    echo.
    
    REM Show failed test information
    if exist "target\surefire-reports" (
        echo Failed Test Details:
        for /f "tokens=*" %%i in ('findstr /l "FAILURE\|ERROR" target\surefire-reports\*.txt 2^>nul') do (
            echo %%i
        )
        echo.
    )
    
    echo Troubleshooting tips:
    echo - Check test logs in target\surefire-reports
    echo - Ensure infrastructure is running for integration tests
    echo - Run with --verbose for detailed output
    echo - Check if all dependencies are available
    echo - Verify test environment configuration
)

exit /b %TEST_EXIT_CODE%

:show_help
echo.
echo SPLP Java Test Runner Script
echo.
echo Usage: test.bat [type] [options]
echo.
echo Test Types:
echo   unit          Run unit tests only
echo   integration   Run integration tests only
echo   all           Run all tests (default)
echo.
echo Options:
echo   --verbose        Enable verbose output
echo   --coverage       Generate code coverage report
echo   --parallel       Run tests in parallel
echo   --fail-fast      Stop on first test failure
echo   --profile NAME   Use specific Maven profile
echo   --help          Show this help message
echo.
echo Examples:
echo   test.bat                           # Run all tests
echo   test.bat unit                      # Run unit tests only
echo   test.bat integration --verbose     # Run integration tests with verbose output
echo   test.bat --coverage --parallel     # Run all tests with coverage and parallel execution
echo   test.bat unit --fail-fast          # Run unit tests, stop on first failure
echo.
echo Test Categories:
echo   Unit Tests:
echo     - Fast execution (no external dependencies)
echo     - Test individual components in isolation
echo     - Files: *Test.java
echo.
echo   Integration Tests:
echo     - Require infrastructure (Kafka, Cassandra)
echo     - Test end-to-end functionality
echo     - Files: *IntegrationTest.java
echo.
echo Infrastructure Requirements:
echo   - Integration tests require Kafka and Cassandra
echo   - Infrastructure will be auto-started if needed
echo   - Use 'start-infra.bat' to start manually
echo   - Use 'check-infra.bat' to verify status
echo.
echo Coverage Reports:
echo   - Generated in target/site/jacoco/
echo   - Automatically opened in browser when --coverage is used
echo   - Includes line, branch, and method coverage
echo.
echo Prerequisites:
echo   - Java 11 or higher installed
echo   - Maven installed and in PATH
echo   - Docker Desktop (for integration tests)
echo.
exit /b 0