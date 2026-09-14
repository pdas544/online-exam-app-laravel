#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Exposes the WSL2 exam stack to the LAN and (optionally) boots it.
.DESCRIPTION
  WSL2 NATs the Linux VM behind a virtual NIC whose IP changes on every
  reboot, so static portproxies go stale. This script re-reads the current
  WSL IP and re-creates v4tov4 proxies for the web port (80) and the Reverb
  websocket port (8081), ensures the Windows Firewall allows both inbound on
  Private/Domain profiles, and with -StartServices boots nginx, php-fpm,
  redis, postgres, supervisor and cron inside the distro.
  Run at every boot via Task Scheduler (see docs/windows-wsl-deploy.md).
  Must run elevated (netsh + firewall require Administrator).
.PARAMETER WslDistro
  The WSL distro name (default "Ubuntu").
.PARAMETER WebPort
  nginx port inside WSL (default 80).
.PARAMETER ReverbPort
  Reverb ws port inside WSL (default 8081).
.PARAMETER StartServices
  Also boot the Linux services (nginx, php8.4-fpm, redis-server,
  postgresql, supervisor, cron) inside the distro.
#>
param(
    [string]$WslDistro = "Ubuntu",
    [int]$WebPort = 80,
    [int]$ReverbPort = 8081,
    [switch]$StartServices
)

$ErrorActionPreference = "Stop"

$wslIp = (wsl -d $WslDistro -- hostname -I).Split(" ")[0].Trim()
if (-not $wslIp) { throw "Could not read WSL IP for distro '$WslDistro'." }
Write-Host "WSL IP: $wslIp"

foreach ($port in @($WebPort, $ReverbPort)) {
    netsh interface portproxy delete v4tov4 listenport=$port listenaddress=0.0.0.0 | Out-Null
    netsh interface portproxy add v4tov4 listenport=$port listenaddress=0.0.0.0 connectport=$port connectaddress=$wslIp
    Write-Host "portproxy :$port -> ${wslIp}:$port"
}

foreach ($rule in @(
    @{ Name = "Exam HTTP"; Port = $WebPort },
    @{ Name = "Exam Reverb WS"; Port = $ReverbPort }
)) {
    if (-not (Get-NetFirewallRule -DisplayName $rule.Name -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName $rule.Name -Direction Inbound `
            -LocalPort $rule.Port -Protocol TCP -Action Allow `
            -Profile Private,Domain | Out-Null
        Write-Host "firewall rule created: $($rule.Name) (TCP $($rule.Port))"
    } else {
        Write-Host "firewall rule exists: $($rule.Name)"
    }
}

if ($StartServices) {
    # service(8) fallback covers distros without systemd; passwordless sudo
    # for these six service commands is set up in the deploy guide.
    $services = @("nginx", "php8.4-fpm", "redis-server", "postgresql", "supervisor", "cron")
    foreach ($svc in $services) {
        wsl -d $WslDistro -- sudo service $svc start
    }
    Write-Host "Linux services started."
}

Write-Host "Done. Verify from a student machine: http://<server-lan-ip>/up"
