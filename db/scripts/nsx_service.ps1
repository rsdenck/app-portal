# NSX Collector Service Watchdog
# This script ensures the NSX data is always fresh by running the collector in a loop.

$phpPath = "php"
$nsxScript = "C:\Users\ranlens.denck\Documents\portal\scripts\nsx_collector.php"
$bgpScript = "C:\Users\ranlens.denck\Documents\portal\scripts\bgp_collector.php"
$vcenterScript = "C:\Users\ranlens.denck\Documents\portal\scripts\vcenter_collector.php"
$logPath = "C:\Users\ranlens.denck\Documents\portal\scripts\nsx_service.log"
$intervalSeconds = 300 # 5 minutes

Write-Host "Starting Network Intelligence Service..." -ForegroundColor Cyan
Write-Output "$(Get-Date): Service Started" | Out-File -FilePath $logPath -Append

while ($true) {
    try {
        Write-Host "$(Get-Date): Running NSX Collector..." -ForegroundColor Yellow
        Start-Process $phpPath -ArgumentList "-d extension_dir=`"C:\Program Files\php\ext`" -d extension=pdo_mysql -d extension=curl -d extension=mbstring -d extension=openssl `"$nsxScript`"" -Wait -NoNewWindow
        
        Write-Host "$(Get-Date): Running BGP Collector..." -ForegroundColor Cyan
        Start-Process $phpPath -ArgumentList "-d extension_dir=`"C:\Program Files\php\ext`" -d extension=pdo_mysql -d extension=curl -d extension=mbstring -d extension=openssl `"$bgpScript`"" -Wait -NoNewWindow

        Write-Host "$(Get-Date): Running vCenter Collector..." -ForegroundColor Magenta
        Start-Process $phpPath -ArgumentList "-d extension_dir=`"C:\Program Files\php\ext`" -d extension=pdo_mysql -d extension=curl -d extension=mbstring -d extension=openssl `"$vcenterScript`"" -Wait -NoNewWindow

        Write-Host "$(Get-Date): Collection cycle finished." -ForegroundColor Green
        Write-Output "$(Get-Date): Success" | Out-File -FilePath $logPath -Append
    }
    catch {
        Write-Host "$(Get-Date): Error executing collector: $($_.Exception.Message)" -ForegroundColor Red
        Write-Output "$(Get-Date): Exception: $($_.Exception.Message)" | Out-File -FilePath $logPath -Append
    }

    Write-Host "Waiting $intervalSeconds seconds before next run..."
    Start-Sleep -Seconds $intervalSeconds
}
