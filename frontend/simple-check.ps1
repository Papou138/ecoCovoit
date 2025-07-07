# Script simple de verification des pages HTML
Write-Host "Verification des pages HTML en cours..." -ForegroundColor Green

$frontendPath = "."
$htmlFiles = Get-ChildItem "*.html" | Where-Object { $_.Name -notlike "_*" }

Write-Host "Pages trouvees: $($htmlFiles.Count)" -ForegroundColor Cyan

foreach ($file in $htmlFiles) {
    $pageName = $file.BaseName
    $content = Get-Content $file.FullName -Raw

    Write-Host "`nPage: $pageName" -ForegroundColor Yellow

    # Verification CSS
    $cssOK = $true
    $requiredCSS = @("_commun.css", "_header.css", "_footer.css")
    foreach ($css in $requiredCSS) {
        if ($content -notmatch [regex]::Escape($css)) {
            Write-Host "  Manque: $css" -ForegroundColor Red
            $cssOK = $false
        }
    }

    # Verification header
    if ($content -match "header-content" -and $content -match "nav-list") {
        Write-Host "  Header: OK" -ForegroundColor Green
    } else {
        Write-Host "  Header: NOK" -ForegroundColor Red
    }

    # Verification footer
    if ($content -match 'class="footer"') {
        Write-Host "  Footer: OK" -ForegroundColor Green
    } else {
        Write-Host "  Footer: NOK" -ForegroundColor Red
    }

    if ($cssOK) {
        Write-Host "  CSS: OK" -ForegroundColor Green
    }
}

Write-Host "`nVerification terminee!" -ForegroundColor Green
