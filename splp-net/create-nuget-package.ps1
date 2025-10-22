#!/usr/bin/env pwsh

Write-Host "===============================================================================" -ForegroundColor Cyan
Write-Host "                        Creating SplpNet NuGet Package" -ForegroundColor Cyan
Write-Host "===============================================================================" -ForegroundColor Cyan
Write-Host ""

# Create nupkgs directory if it doesn't exist
if (-not (Test-Path "nupkgs")) {
    New-Item -ItemType Directory -Path "nupkgs" | Out-Null
    Write-Host "Created nupkgs directory" -ForegroundColor Green
}

Write-Host "Building SplpNet project..." -ForegroundColor Yellow
Set-Location "splp-net"

try {
    # Clean previous builds
    Write-Host "Cleaning previous builds..." -ForegroundColor Cyan
    dotnet clean --configuration Release
    if ($LASTEXITCODE -ne 0) {
        throw "Clean failed!"
    }

    # Restore packages
    Write-Host "Restoring packages..." -ForegroundColor Cyan
    dotnet restore
    if ($LASTEXITCODE -ne 0) {
        throw "Restore failed!"
    }

    # Build the project
    Write-Host "Building project..." -ForegroundColor Cyan
    dotnet build --configuration Release --no-restore
    if ($LASTEXITCODE -ne 0) {
        throw "Build failed!"
    }

    # Create NuGet package
    Write-Host "Creating NuGet package..." -ForegroundColor Cyan
    dotnet pack --configuration Release --no-build --output "../nupkgs"
    if ($LASTEXITCODE -ne 0) {
        throw "Pack failed!"
    }

    Set-Location ".."

    Write-Host ""
    Write-Host "===============================================================================" -ForegroundColor Green
    Write-Host "NuGet package created successfully!" -ForegroundColor Green
    Write-Host "===============================================================================" -ForegroundColor Green
    Write-Host ""
    
    Write-Host "Package location: " -NoNewline -ForegroundColor Yellow
    Write-Host "$(Get-Location)\nupkgs\" -ForegroundColor White
    
    $packages = Get-ChildItem -Path "nupkgs\*.nupkg"
    foreach ($package in $packages) {
        Write-Host "  📦 $($package.Name)" -ForegroundColor Green
        Write-Host "     Size: $([math]::Round($package.Length / 1KB, 2)) KB" -ForegroundColor Gray
        Write-Host "     Created: $($package.CreationTime)" -ForegroundColor Gray
    }
    
    Write-Host ""
    Write-Host "To install the package locally:" -ForegroundColor Yellow
    Write-Host "  dotnet add package SplpNet --source $(Get-Location)\nupkgs" -ForegroundColor White
    Write-Host ""
    Write-Host "To publish to NuGet.org:" -ForegroundColor Yellow
    Write-Host "  dotnet nuget push nupkgs\SplpNet.1.0.0.nupkg --api-key YOUR_API_KEY --source https://api.nuget.org/v3/index.json" -ForegroundColor White
    Write-Host ""
    
    # Show package details
    Write-Host "Package Details:" -ForegroundColor Cyan
    $latestPackage = $packages | Sort-Object CreationTime -Descending | Select-Object -First 1
    if ($latestPackage) {
        Write-Host "  Name: SplpNet" -ForegroundColor White
        Write-Host "  Version: 1.0.0" -ForegroundColor White
        Write-Host "  Target Frameworks: net8.0, net6.0, netstandard2.1" -ForegroundColor White
        Write-Host "  Description: Kafka Request-Reply Messaging Library for .NET" -ForegroundColor White
    }
}
catch {
    Write-Host ""
    Write-Host "❌ Error: $($_.Exception.Message)" -ForegroundColor Red
    Set-Location ".."
    exit 1
}

Write-Host ""
Read-Host "Press Enter to exit"
