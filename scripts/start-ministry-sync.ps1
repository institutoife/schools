$projectPath = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$phpPath = (Get-Command php -ErrorAction Stop).Source
$statusPath = Join-Path $projectPath 'storage/app/importaciones/ministerio/ejecutor.json'
if (Test-Path -LiteralPath $statusPath) {
    $workerStatus = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
    $existingProcess = Get-Process -Id $workerStatus.pid -ErrorAction SilentlyContinue
    if ($existingProcess -and $existingProcess.ProcessName -eq 'php' -and $workerStatus.actualizado_en -gt [DateTimeOffset]::UtcNow.ToUnixTimeSeconds() - 180) {
        Write-Output 'El ejecutor del Ministerio ya está activo.'
        return
    }
}
$logDirectory = Join-Path $projectPath 'storage/logs'
New-Item -ItemType Directory -Force -Path $logDirectory | Out-Null
$workerProcess = Start-Process -FilePath $phpPath -ArgumentList 'artisan','schools:process-ministry','--watch' -WorkingDirectory $projectPath -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDirectory 'ministry-worker-output.log') -RedirectStandardError (Join-Path $logDirectory 'ministry-worker-error.log') -PassThru
Write-Output "PID del ejecutor: $($workerProcess.Id)"
Write-Output 'Ejecutor del Ministerio iniciado en segundo plano. No consulta fichas hasta que se carga un Excel en el panel.'
