# Script PowerShell pour verifier et standardiser toutes les pages HTML
# ecoCovoit - Verification des headers, footers et liaisons CSS

Write-Host "🔧 Debut de la verification de toutes les pages HTML..." -ForegroundColor Green

# Définition des chemins
$frontendPath = "c:\DEV\ecoCovoit\frontend"
$cssPath = "$frontendPath\assets\css"

# Fonction pour vérifier les liaisons CSS requises
function Test-CSSLinks {
  param([string]$FilePath, [string]$PageName)

  $content = Get-Content $FilePath -Raw
  $requiredCSS = @("_commun.css", "_header.css", "_footer.css", "$PageName.css")
  $missing = @()

  foreach ($css in $requiredCSS) {
    if ($content -notmatch [regex]::Escape($css)) {
      $missing += $css
    }
  }

  if ($missing.Count -gt 0) {
    Write-Host "  ❌ $PageName - CSS manquants: $($missing -join ', ')" -ForegroundColor Red
    return $false
  } else {
    Write-Host "  ✅ $PageName - Toutes les liaisons CSS presentes" -ForegroundColor Green
    return $true
  }
}

# Fonction pour vérifier la structure du header
function Test-HeaderStructure {
  param([string]$FilePath, [string]$PageName)

  $content = Get-Content $FilePath -Raw

  $headerElements = @(
    "header-content",
    "logo-container",
    "menu-toggle",
    "main-nav",
    "nav-list"
  )

  $missing = @()
  foreach ($element in $headerElements) {
    if ($content -notmatch [regex]::Escape($element)) {
      $missing += $element
    }
  }

  if ($missing.Count -gt 0) {
    Write-Host "  ❌ $PageName - Éléments header manquants: $($missing -join ', ')" -ForegroundColor Red
    return $false
  } else {
    Write-Host "  ✅ $PageName - Structure header complète" -ForegroundColor Green
    return $true
  }
}

# Fonction pour vérifier la structure du footer
function Test-FooterStructure {
  param([string]$FilePath, [string]$PageName)

  $content = Get-Content $FilePath -Raw

  if ($content -match 'class="footer"' -and $content -match 'footer-content' -and $content -match 'footer-bottom') {
    Write-Host "  ✅ $PageName - Structure footer complète" -ForegroundColor Green
    return $true
  } else {
    Write-Host "  ❌ $PageName - Structure footer incomplète" -ForegroundColor Red
    return $false
  }
}

# Obtenir toutes les pages HTML
$htmlFiles = Get-ChildItem "$frontendPath\*.html" | Where-Object { $_.Name -ne "_header-template.html" -and $_.Name -ne "_footer-template.html" }

Write-Host "`n📋 Vérification de $($htmlFiles.Count) pages HTML..." -ForegroundColor Cyan

$results = @{
  "TotalPages"     = $htmlFiles.Count
  "CSSProblems"    = 0
  "HeaderProblems" = 0
  "FooterProblems" = 0
  "FullyCompliant" = 0
}

foreach ($file in $htmlFiles) {
  $pageName = $file.BaseName
  Write-Host "`n🔍 Vérification de $pageName.html..." -ForegroundColor Yellow

  $cssOK = Test-CSSLinks -FilePath $file.FullName -PageName $pageName
  $headerOK = Test-HeaderStructure -FilePath $file.FullName -PageName $pageName
  $footerOK = Test-FooterStructure -FilePath $file.FullName -PageName $pageName

  if (-not $cssOK) { $results.CSSProblems++ }
  if (-not $headerOK) { $results.HeaderProblems++ }
  if (-not $footerOK) { $results.FooterProblems++ }
  if ($cssOK -and $headerOK -and $footerOK) { $results.FullyCompliant++ }
}

# Résumé
Write-Host "`n📊 RÉSUMÉ DE LA VÉRIFICATION:" -ForegroundColor Cyan
Write-Host "=" * 50
Write-Host "📄 Pages totales analysées: $($results.TotalPages)" -ForegroundColor White
Write-Host "✅ Pages entièrement conformes: $($results.FullyCompliant)" -ForegroundColor Green
Write-Host "🎨 Pages avec problèmes CSS: $($results.CSSProblems)" -ForegroundColor Yellow
Write-Host "📌 Pages avec problèmes Header: $($results.HeaderProblems)" -ForegroundColor Yellow
Write-Host "📌 Pages avec problèmes Footer: $($results.FooterProblems)" -ForegroundColor Yellow

$complianceRate = [math]::Round(($results.FullyCompliant / $results.TotalPages) * 100, 1)
Write-Host "`n🎯 Taux de conformité: $complianceRate%" -ForegroundColor $(if ($complianceRate -ge 80) { "Green" } elseif ($complianceRate -ge 60) { "Yellow" } else { "Red" })

if ($results.FullyCompliant -eq $results.TotalPages) {
  Write-Host "`n🎉 FÉLICITATIONS ! Toutes les pages sont conformes au standard !" -ForegroundColor Green
} else {
  Write-Host "`n🔧 Actions recommandées:" -ForegroundColor Cyan
  Write-Host "  1. Corriger les liaisons CSS manquantes" -ForegroundColor White
  Write-Host "  2. Standardiser les headers non conformes" -ForegroundColor White
  Write-Host "  3. Uniformiser les footers" -ForegroundColor White
  Write-Host "  4. Re-exécuter ce script pour vérifier les corrections" -ForegroundColor White
}

Write-Host "`n✨ Verification terminee !" -ForegroundColor Green
