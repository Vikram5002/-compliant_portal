param(
    [Parameter(Mandatory = $true)]
    [string]$InputDirectory,

    [Parameter(Mandatory = $true)]
    [string]$Region,

    [string]$AwsProfile
)

$ErrorActionPreference = 'Stop'

function Get-UnprocessedCount {
    param(
        [Parameter(Mandatory = $true)]
        [psobject]$ResponseObject
    )

    if ($null -eq $ResponseObject.UnprocessedItems) {
        return 0
    }

    $count = 0
    foreach ($property in $ResponseObject.UnprocessedItems.PSObject.Properties) {
        $count += @($property.Value).Count
    }

    return $count
}

if (-not (Test-Path -Path $InputDirectory)) {
    throw "Input directory not found: $InputDirectory"
}

$resolvedInput = (Resolve-Path -Path $InputDirectory).Path
$batchFiles = Get-ChildItem -Path $resolvedInput -Filter '*_batch_*.json' | Sort-Object -Property Name

if ($batchFiles.Count -eq 0) {
    Write-Host "No batch files found in: $resolvedInput"
    exit 0
}

Write-Host "Found $($batchFiles.Count) batch file(s)."

foreach ($file in $batchFiles) {
    Write-Host "Importing $($file.Name)..."

    $baseArgs = @(
        'dynamodb',
        'batch-write-item',
        '--request-items', "file://$($file.FullName)",
        '--region', $Region,
        '--output', 'json'
    )

    if ($AwsProfile) {
        $baseArgs += @('--profile', $AwsProfile)
    }

    $rawResponse = aws @baseArgs
    if ($LASTEXITCODE -ne 0) {
        throw "AWS CLI failed for file: $($file.Name)"
    }

    $response = $rawResponse | ConvertFrom-Json

    $unprocessed = Get-UnprocessedCount -ResponseObject $response
    $attempt = 0

    while ($unprocessed -gt 0 -and $attempt -lt 5) {
        $attempt++
        Write-Host "Retrying unprocessed items for $($file.Name), attempt $attempt (remaining: $unprocessed)..."

        $retryPayload = @{
            RequestItems = $response.UnprocessedItems
        }

        $tempRetryPath = Join-Path -Path $env:TEMP -ChildPath ("dynamo_retry_" + [Guid]::NewGuid().ToString() + ".json")
        $retryPayload | ConvertTo-Json -Depth 100 | Set-Content -Path $tempRetryPath -Encoding UTF8

        $retryArgs = @(
            'dynamodb',
            'batch-write-item',
            '--request-items', "file://$tempRetryPath",
            '--region', $Region,
            '--output', 'json'
        )

        if ($AwsProfile) {
            $retryArgs += @('--profile', $AwsProfile)
        }

        $retryRawResponse = aws @retryArgs
        Remove-Item -Path $tempRetryPath -ErrorAction SilentlyContinue

        if ($LASTEXITCODE -ne 0) {
            throw "AWS CLI retry failed for file: $($file.Name)"
        }

        $response = $retryRawResponse | ConvertFrom-Json
        $unprocessed = Get-UnprocessedCount -ResponseObject $response
    }

    if ($unprocessed -gt 0) {
        throw "Failed to process all items for $($file.Name). Remaining unprocessed items: $unprocessed"
    }

    Write-Host "Imported $($file.Name) successfully."
}

Write-Host "All batch files imported successfully."
