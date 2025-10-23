@echo off
echo ===============================================================================
echo                        Creating SplpNet NuGet Package
echo ===============================================================================
echo.

REM Create nupkgs directory if it doesn't exist
if not exist "nupkgs" mkdir nupkgs

echo Building SplpNet project...
cd splp-net

REM Clean previous builds
dotnet clean --configuration Release
if %ERRORLEVEL% neq 0 (
    echo Clean failed!
    pause
    exit /b 1
)

REM Restore packages
echo Restoring packages...
dotnet restore
if %ERRORLEVEL% neq 0 (
    echo Restore failed!
    pause
    exit /b 1
)

REM Build the project
echo Building project...
dotnet build --configuration Release --no-restore
if %ERRORLEVEL% neq 0 (
    echo Build failed!
    pause
    exit /b 1
)

REM Create NuGet package
echo Creating NuGet package...
dotnet pack --configuration Release --no-build --output ..\nupkgs
if %ERRORLEVEL% neq 0 (
    echo Pack failed!
    pause
    exit /b 1
)

cd ..

echo.
echo ===============================================================================
echo NuGet package created successfully!
echo ===============================================================================
echo.
echo Package location: %~dp0nupkgs\
dir nupkgs\*.nupkg
echo.
echo To install the package locally:
echo   dotnet add package SplpNet --source %~dp0nupkgs
echo.
echo To publish to NuGet.org:
echo   dotnet nuget push nupkgs\SplpNet.1.0.0.nupkg --api-key YOUR_API_KEY --source https://api.nuget.org/v3/index.json
echo.
pause
