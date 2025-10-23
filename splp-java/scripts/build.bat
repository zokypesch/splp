@echo off
setlocal enabledelayedexpansion

REM SPLP Java Build Script
REM Usage: build.bat [clean|test|package|install|deploy] [options]

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "MAVEN_OPTS=-Xmx2g -XX:+UseG1GC"

REM Change to project root directory
cd /d "%PROJECT_ROOT%"

REM Parse command line arguments
set "BUILD_TARGET=package"
set "SKIP_TESTS=false"
set "CLEAN_BUILD=false"
set "VERBOSE=false"
set "PROFILE=default"

:parse_args
if "%~1"=="" goto :execute_build
if /i "%~1"=="clean" (
    set "CLEAN_BUILD=true"
    shift
    goto :parse_args
)
if /i "%~1"=="test" (
    set "BUILD_TARGET=test"
    shift
    goto :parse_args
)
if /i "%~1"=="package" (
    set "BUILD_TARGET=package"
    shift
    goto :parse_args
)
if /i "%~1"=="install" (
    set "BUILD_TARGET=install"
    shift
    goto :parse_args
)
if /i "%~1"=="deploy" (
    set "BUILD_TARGET=deploy"
    shift
    goto :parse_args
)
if /i "%~1"=="--skip-tests" (
    set "SKIP_TESTS=true"
    shift
    goto :parse_args
)
if /i "%~1"=="--verbose" (
    set "VERBOSE=true"
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

:execute_build
echo.
echo ========================================
echo SPLP Java Build Script
echo ========================================
echo Build Target: %BUILD_TARGET%
echo Skip Tests: %SKIP_TESTS%
echo Clean Build: %CLEAN_BUILD%
echo Profile: %PROFILE%
echo Verbose: %VERBOSE%
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

REM Check Java version
for /f "tokens=3" %%g in ('java -version 2^>^&1 ^| findstr /i "version"') do (
    set "JAVA_VERSION=%%g"
    set "JAVA_VERSION=!JAVA_VERSION:"=!"
)
echo Using Java version: %JAVA_VERSION%

REM Build Maven command
set "MVN_CMD=mvn"

if "%CLEAN_BUILD%"=="true" (
    set "MVN_CMD=!MVN_CMD! clean"
)

set "MVN_CMD=!MVN_CMD! %BUILD_TARGET%"

if "%SKIP_TESTS%"=="true" (
    set "MVN_CMD=!MVN_CMD! -DskipTests"
)

if "%VERBOSE%"=="true" (
    set "MVN_CMD=!MVN_CMD! -X"
) else (
    set "MVN_CMD=!MVN_CMD! -q"
)

if not "%PROFILE%"=="default" (
    set "MVN_CMD=!MVN_CMD! -P%PROFILE%"
)

REM Add additional Maven options
set "MVN_CMD=!MVN_CMD! -Dmaven.compiler.source=11 -Dmaven.compiler.target=11"

echo Executing: %MVN_CMD%
echo.

REM Execute Maven build
%MVN_CMD%
set "BUILD_EXIT_CODE=%errorlevel%"

echo.
if %BUILD_EXIT_CODE% equ 0 (
    echo ========================================
    echo BUILD SUCCESSFUL
    echo ========================================
    
    REM Show build artifacts
    if exist "target\*.jar" (
        echo.
        echo Build artifacts:
        dir /b target\*.jar
    )
    
    REM Show test results if tests were run
    if "%SKIP_TESTS%"=="false" (
        if exist "target\surefire-reports" (
            echo.
            echo Test results available in: target\surefire-reports
        )
    )
    
    echo.
    echo Build completed successfully!
) else (
    echo ========================================
    echo BUILD FAILED
    echo ========================================
    echo.
    echo Build failed with exit code: %BUILD_EXIT_CODE%
    echo Check the output above for error details.
    
    REM Show common troubleshooting tips
    echo.
    echo Troubleshooting tips:
    echo - Ensure all dependencies are available
    echo - Check if Docker containers are running for integration tests
    echo - Verify Java and Maven versions are compatible
    echo - Run with --verbose flag for detailed output
)

exit /b %BUILD_EXIT_CODE%

:show_help
echo.
echo SPLP Java Build Script
echo.
echo Usage: build.bat [target] [options]
echo.
echo Targets:
echo   clean      Clean the project
echo   test       Run tests only
echo   package    Compile and package (default)
echo   install    Install to local repository
echo   deploy     Deploy to remote repository
echo.
echo Options:
echo   --skip-tests    Skip running tests
echo   --verbose       Enable verbose output
echo   --profile NAME  Use specific Maven profile
echo   --help          Show this help message
echo.
echo Examples:
echo   build.bat                           # Package with tests
echo   build.bat clean package             # Clean and package
echo   build.bat test --verbose            # Run tests with verbose output
echo   build.bat install --skip-tests      # Install without running tests
echo   build.bat --profile integration     # Use integration profile
echo.
exit /b 0