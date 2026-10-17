$ErrorActionPreference = 'Stop'
$arduinoCli = Join-Path $env:LOCALAPPDATA 'Programs\Arduino IDE\resources\app\lib\backend\resources\arduino-cli.exe'
if (-not (Test-Path -LiteralPath $arduinoCli)) { throw 'Arduino CLI not found. Update the arduinoCli path in this script.' }
$testRoot = [IO.Path]::GetFullPath((Join-Path ([IO.Path]::GetTempPath()) ('EcoSenseFirmware-' + [guid]::NewGuid().ToString('N'))))
$expectedRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\') + '\'
if (-not $testRoot.StartsWith($expectedRoot, [StringComparison]::OrdinalIgnoreCase)) { throw 'Invalid temporary path' }
try {
    $sketchPath = Join-Path $testRoot 'ecosense_esp8266'
    New-Item -ItemType Directory -Path $sketchPath -Force | Out-Null
    $firmwareText = Get-Content -LiteralPath (Join-Path $PSScriptRoot '..\device\ecosense_esp8266.ino') -Raw
    $exampleConfig = (Get-Content -LiteralPath (Join-Path $PSScriptRoot '..\device\esp8266_local.h.example') -Raw).Replace('#pragma once', '')
    $inlineStart = $firmwareText.IndexOf('#else')
    $inlineEnd = $firmwareText.IndexOf('#endif', $inlineStart)
    if ($inlineStart -lt 0 -or $inlineEnd -lt 0) { throw 'Inline configuration block not found' }
    $firmwareText = $firmwareText.Substring(0, $inlineStart) + "#else`n" + $exampleConfig + "`n" + $firmwareText.Substring($inlineEnd)
    [IO.File]::WriteAllText((Join-Path $sketchPath 'ecosense_esp8266.ino'), $firmwareText)
    & $arduinoCli compile --fqbn esp8266:esp8266:nodemcuv2 --build-path (Join-Path $testRoot 'build') $sketchPath
    if ($LASTEXITCODE -ne 0) { throw 'ESP8266 compilation failed' }
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot '..\device\esp8266_local.h.example') -Destination (Join-Path $sketchPath 'esp8266_local.h')
    & $arduinoCli compile --fqbn esp8266:esp8266:nodemcuv2 --build-path (Join-Path $testRoot 'build') $sketchPath
    if ($LASTEXITCODE -ne 0) { throw 'ESP8266 compilation with local configuration failed' }
    Write-Output 'PASS: ESP8266 NodeMCU firmware compiles using placeholder credentials.'
} finally {
    if ($testRoot.StartsWith($expectedRoot, [StringComparison]::OrdinalIgnoreCase) -and (Split-Path $testRoot -Leaf).StartsWith('EcoSenseFirmware-')) {
        Remove-Item -LiteralPath $testRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}
