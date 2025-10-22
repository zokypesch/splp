#!/usr/bin/env pwsh

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "                SplpNet Encryption Key Generator" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# Generate 32 random bytes for AES-256
$bytes = [System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)
$key = [System.BitConverter]::ToString($bytes).Replace('-', '').ToLower()

Write-Host "Generated AES-256 Key:" -ForegroundColor Yellow
Write-Host $key -ForegroundColor Green
Write-Host ""

Write-Host "Set this as environment variable:" -ForegroundColor Yellow
Write-Host "ENCRYPTION_KEY=$key" -ForegroundColor White
Write-Host ""

Write-Host "PowerShell:" -ForegroundColor Yellow
Write-Host "`$env:ENCRYPTION_KEY = `"$key`"" -ForegroundColor White
Write-Host ""

Write-Host "CMD:" -ForegroundColor Yellow
Write-Host "set ENCRYPTION_KEY=$key" -ForegroundColor White
Write-Host ""

Write-Host "⚠️  IMPORTANT: All services must use the same key!" -ForegroundColor Red
Write-Host "============================================================" -ForegroundColor Cyan

# Optionally set it in current session
$choice = Read-Host "Set this key in current PowerShell session? (y/N)"
if ($choice -eq 'y' -or $choice -eq 'Y') {
    $env:ENCRYPTION_KEY = $key
    Write-Host "✅ Environment variable set for current session!" -ForegroundColor Green
    Write-Host "Current value: $env:ENCRYPTION_KEY" -ForegroundColor Gray
} else {
    Write-Host "💡 Remember to set the environment variable before running services!" -ForegroundColor Yellow
}
