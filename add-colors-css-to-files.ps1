# PowerShell script to add colors.css link to all PHP files that use Bootstrap but don't have colors.css yet

$adminFiles = @(
    'add_machine.php',
    'add_user.php',
    'admin_notifications.php',
    'claimed_rewards.php',
    'booking_schedules.php',
    'edit_user.php',
    'gcash_requests-management.php',
    'manage_inventory.php',
    'manage_machines.php',
    'manage_users.php',
    'reports.php',
    'payment_requests-management.php',
    'queue_management.php',
    'transaction_report.php'
)

$staffFiles = @(
    'add-inventory-staff.php',
    'add-machine-staff.php',
    'booking-schedules-staff.php',
    'claimed-rewards-staff.php',
    'delete-inventory-staff.php',
    'delete-machine-staff.php',
    'edit-inventory-staff.php',
    'edit-machine-staff.php',
    'gcash-requests-staff.php',
    'manage-inventory-staff.php',
    'manage-machines-staff.php',
    'queue-management-staff.php',
    'sales-report-staff.php',
    'staff-home.php'
)

$userFiles = @(
    'user-profile.php',
    'manage-booking.php',
    'manage-gcash.php',
    'manage-rewards.php'
)

function Add-ColorsCss-ToFile {
    param([string]$filePath)
    
    $content = Get-Content $filePath -Raw
    
    # Check if colors.css is already present
    if ($content -match 'colors\.css') {
        Write-Host "Skipping $filePath - colors.css already present" -ForegroundColor Yellow
        return
    }
    
    # Pattern 1: After all.min.css for admin files (with roboto-fonts)
    if ($content -match '<link rel="stylesheet" href="../assets/lib/css/all.min.css">.*?<link href="../assets/lib/fonts/roboto-fonts.css"') {
        $newContent = $content -replace '(<link href="../assets/lib/fonts/roboto-fonts.css" rel="stylesheet">)', "<link rel=`"stylesheet`" href=`"../assets/css/colors.css`">`n    `$1"
        Set-Content -Path $filePath -Value $newContent
        Write-Host "Updated: $filePath (pattern 1 - with roboto-fonts)" -ForegroundColor Green
        return
    }
    
    # Pattern 2: After all.min.css for admin files (without roboto-fonts)
    if ($content -match '<link rel="stylesheet" href="../assets/lib/css/all.min.css">.*?<link rel="stylesheet" href="../assets/css/') {
        $newContent = $content -replace '(<link rel="stylesheet" href="../assets/lib/css/all.min.css">)', "`$1`n    <link rel=`"stylesheet`" href=`"../assets/css/colors.css`">"
        Set-Content -Path $filePath -Value $newContent
        Write-Host "Updated: $filePath (pattern 2 - standard admin)" -ForegroundColor Green
        return
    }
    
    # Pattern 3: For staff files with different structure
    if ($content -match '<link rel="stylesheet" href="../assets/lib/css/all.min.css">' -and -not($content -match 'colors\.css')) {
        $newContent = $content -replace '(<link rel="stylesheet" href="../assets/lib/css/all.min.css">)', "`$1`n    <link rel=`"stylesheet`" href=`"../assets/css/colors.css`">"
        Set-Content -Path $filePath -Value $newContent
        Write-Host "Updated: $filePath (pattern 3 - staff/user)" -ForegroundColor Green
        return
    }
    
    Write-Host "Could not match pattern for $filePath - manual update may be needed" -ForegroundColor Red
}

# Process admin files
Write-Host "`n=== Processing Admin Files ===" -ForegroundColor Cyan
foreach ($file in $adminFiles) {
    $filePath = "C:\xampp\htdocs\jorishlaundry\admin\$file"
    if (Test-Path $filePath) {
        Add-ColorsCss-ToFile -filePath $filePath
    } else {
        Write-Host "File not found: $file" -ForegroundColor Red
    }
}

# Process staff files
Write-Host "`n=== Processing Staff Files ===" -ForegroundColor Cyan
foreach ($file in $staffFiles) {
    $filePath = "C:\xampp\htdocs\jorishlaundry\staff\$file"
    if (Test-Path $filePath) {
        Add-ColorsCss-ToFile -filePath $filePath
    } else {
        Write-Host "File not found: $file" -ForegroundColor Red
    }
}

# Process user files
Write-Host "`n=== Processing User Files ===" -ForegroundColor Cyan
foreach ($file in $userFiles) {
    $filePath = "C:\xampp\htdocs\jorishlaundry\user\$file"
    if (Test-Path $filePath) {
        Add-ColorsCss-ToFile -filePath $filePath
    } else {
        Write-Host "File not found: $file" -ForegroundColor Red
    }
}

Write-Host "`n=== Done ===" -ForegroundColor Green
