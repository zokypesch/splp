#!/usr/bin/env pwsh

Write-Host "===============================================================================" -ForegroundColor Cyan
Write-Host "                        SPLP-NET Example Services Runner" -ForegroundColor Cyan
Write-Host "===============================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "This script will start all services in separate windows:" -ForegroundColor Yellow
Write-Host "  1. Command Center (Message Router)" -ForegroundColor White
Write-Host "  2. Service 1 (Dukcapil - Population Verification)" -ForegroundColor White
Write-Host "  3. Service 2 (Kemensos - Final Decision)" -ForegroundColor White
Write-Host "  4. Publisher (Social Assistance Request)" -ForegroundColor White
Write-Host ""
Write-Host "Make sure you have:" -ForegroundColor Yellow
Write-Host "  - Kafka running on localhost:9092" -ForegroundColor White
Write-Host "  - Cassandra running on localhost:9042" -ForegroundColor White
Write-Host "  - ENCRYPTION_KEY environment variable set" -ForegroundColor White
Write-Host ""

# Check if ENCRYPTION_KEY is set
if (-not $env:ENCRYPTION_KEY) {
    Write-Host "WARNING: ENCRYPTION_KEY environment variable is not set!" -ForegroundColor Red
    Write-Host "Generating a new key for this session..." -ForegroundColor Yellow
    
    # Generate a proper 32-byte key
    $bytes = [System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)
    $env:ENCRYPTION_KEY = [System.BitConverter]::ToString($bytes).Replace("-", "").ToLower()
    
    Write-Host "Generated key: $($env:ENCRYPTION_KEY)" -ForegroundColor Green
    Write-Host ""
    Write-Host "IMPORTANT: Use this same key for all services in production!" -ForegroundColor Red
    Write-Host ""
}

Write-Host "Using ENCRYPTION_KEY: $($env:ENCRYPTION_KEY)" -ForegroundColor Green
Write-Host ""

# Build the solution first
Write-Host "Building solution..." -ForegroundColor Yellow
dotnet build
if ($LASTEXITCODE -ne 0) {
    Write-Host "Build failed! Please fix compilation errors." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "Build successful!" -ForegroundColor Green
Write-Host ""

Write-Host "Starting services..." -ForegroundColor Yellow
Write-Host ""

# Function to start service in new window
function Start-ServiceWindow {
    param(
        [string]$Title,
        [string]$Path,
        [string]$Description
    )
    
    Write-Host "Starting $Description..." -ForegroundColor Cyan
    
    if ($IsWindows -or $env:OS -eq "Windows_NT") {
        # Windows
        Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$Path'; Write-Host 'Starting $Description...'; dotnet run" -WindowStyle Normal
    } elseif ($IsLinux) {
        # Linux
        gnome-terminal --title="$Title" --working-directory="$Path" -- bash -c "echo 'Starting $Description...'; dotnet run; exec bash"
    } elseif ($IsMacOS) {
        # macOS
        osascript -e "tell app \"Terminal\" to do script \"cd '$Path' && echo 'Starting $Description...' && dotnet run\""
    } else {
        Write-Host "Unsupported platform. Starting in current terminal..." -ForegroundColor Yellow
        Start-Process dotnet -ArgumentList "run" -WorkingDirectory $Path -NoNewWindow
    }
    
    Start-Sleep -Seconds 3
}

# Get current directory
$currentDir = Get-Location

# Start Command Center
Start-ServiceWindow -Title "Command Center" -Path "$currentDir/CommandCenter" -Description "Command Center"

# Start Service 1
Start-ServiceWindow -Title "Service 1 - Dukcapil" -Path "$currentDir/Service1" -Description "Service 1 (Dukcapil)"

# Start Service 2
Start-ServiceWindow -Title "Service 2 - Kemensos" -Path "$currentDir/Service2" -Description "Service 2 (Kemensos)"

Write-Host ""
Write-Host "All services are starting..." -ForegroundColor Green
Write-Host "Wait for all services to show 'ready' status before running the publisher." -ForegroundColor Yellow
Write-Host ""
Write-Host "Press any key to start the Publisher (or Ctrl+C to cancel)..." -ForegroundColor Cyan
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

# Start Publisher
Write-Host ""
Write-Host "Starting Publisher..." -ForegroundColor Cyan
Start-ServiceWindow -Title "Publisher - Kemensos Request" -Path "$currentDir/Publisher" -Description "Publisher"

Write-Host ""
Write-Host "===============================================================================" -ForegroundColor Cyan
Write-Host "All services have been started in separate windows:" -ForegroundColor Green
Write-Host "  - Command Center: Routes messages between services" -ForegroundColor White
Write-Host "  - Service 1: Dukcapil population verification" -ForegroundColor White
Write-Host "  - Service 2: Kemensos final decision" -ForegroundColor White
Write-Host "  - Publisher: Sends social assistance requests" -ForegroundColor White
Write-Host ""
Write-Host "Check each window for service status and message flow." -ForegroundColor Yellow
Write-Host "Close individual windows to stop services, or close this window to exit." -ForegroundColor Yellow
Write-Host "===============================================================================" -ForegroundColor Cyan
Write-Host ""
Read-Host "Press Enter to exit"
