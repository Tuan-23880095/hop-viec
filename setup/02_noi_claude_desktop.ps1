# 02 - Noi Gemini MCP + Antigravity MCP vao Claude Desktop
# - Hoi GEMINI_API_KEY (ban tu dan vao, khong hien ra man hinh)
# - Backup roi cap nhat %APPDATA%\Claude\claude_desktop_config.json
# - Luu GEMINI_API_KEY vao bien moi truong nguoi dung (cho cac script khac dung)
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$logDir = Join-Path $root "logs"
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$log = Join-Path $logDir "02_claude_desktop.txt"
"==== $(Get-Date) ====" | Out-File $log

function Log($m) { $m | Tee-Object -FilePath $log -Append }

# 1. Tim npm global prefix (noi chua gemini-mcp.cmd, mcp-server-google-antigravity.cmd)
$prefix = (& npm prefix -g).Trim()
Log "npm prefix -g = $prefix"
$geminiCmd = Join-Path $prefix "gemini-mcp.cmd"
$agyMcpCmd = Join-Path $prefix "mcp-server-google-antigravity.cmd"
if (-not (Test-Path $geminiCmd)) { Log "THIEU $geminiCmd -> chay lai 01_kiem_tra_va_cai.cmd"; exit 1 }
if (-not (Test-Path $agyMcpCmd)) { Log "THIEU $agyMcpCmd -> chay lai 01_kiem_tra_va_cai.cmd"; exit 1 }

# 2. agy co chua?
$agy = Get-Command agy -ErrorAction SilentlyContinue
if ($agy) { Log "agy = $($agy.Source)" } else { Log "CANH BAO: chua thay agy trong PATH. Cai: npm install -g @google/antigravity-cli ; roi chay 'agy' mot lan de dang nhap." }

# 3. Hoi API key (khong hien)
Write-Host ""
Write-Host "Dan GEMINI_API_KEY (lay tai https://aistudio.google.com/app/apikey) roi Enter."
Write-Host "Ky tu se khong hien ra. De trong neu da co bien moi truong GEMINI_API_KEY."
$sec = Read-Host -AsSecureString "GEMINI_API_KEY"
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec)
$key = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
if ([string]::IsNullOrWhiteSpace($key)) {
  $key = [Environment]::GetEnvironmentVariable("GEMINI_API_KEY", "User")
  if ([string]::IsNullOrWhiteSpace($key)) { Log "Khong co API key. Dung."; exit 1 }
  Log "Dung GEMINI_API_KEY san co trong bien moi truong."
} else {
  [Environment]::SetEnvironmentVariable("GEMINI_API_KEY", $key, "User")
  Log "Da luu GEMINI_API_KEY vao bien moi truong nguoi dung (do dai $($key.Length))."
}

# 4. Doc / tao config Claude Desktop
$cfgDir = Join-Path $env:APPDATA "Claude"
$cfgPath = Join-Path $cfgDir "claude_desktop_config.json"
New-Item -ItemType Directory -Force -Path $cfgDir | Out-Null
if (Test-Path $cfgPath) {
  $stamp = Get-Date -Format "yyyyMMdd_HHmmss"
  Copy-Item $cfgPath (Join-Path $cfgDir "claude_desktop_config.backup_$stamp.json")
  Log "Da backup config cu."
  $cfg = Get-Content $cfgPath -Raw | ConvertFrom-Json
} else {
  $cfg = [pscustomobject]@{}
  Log "Chua co config, tao moi."
}
if (-not $cfg.PSObject.Properties["mcpServers"]) {
  $cfg | Add-Member -NotePropertyName mcpServers -NotePropertyValue ([pscustomobject]@{})
}
$servers = $cfg.mcpServers

function SetServer($name, $obj) {
  if ($servers.PSObject.Properties[$name]) { $servers.PSObject.Properties.Remove($name) }
  $servers | Add-Member -NotePropertyName $name -NotePropertyValue $obj
}

# Gemini API (on dinh nhat) - Claude Desktop tren Windows can boc qua cmd /c
SetServer "gemini" ([pscustomobject]@{
  command = "cmd"
  args    = @("/c", $geminiCmd)
  env     = [pscustomobject]@{ GEMINI_API_KEY = $key }
})

# Antigravity (agy) - job bat dong bo, timeout dai
SetServer "antigravity" ([pscustomobject]@{
  command = "cmd"
  args    = @("/c", $agyMcpCmd)
  env     = [pscustomobject]@{ AGY_AUTO_APPROVE = "true" }
  timeout = 900000
})

$cfg | ConvertTo-Json -Depth 10 | Set-Content -Path $cfgPath -Encoding UTF8
Log "Da ghi $cfgPath"
Log "Servers hien co: $($servers.PSObject.Properties.Name -join ', ')"
Log ""
Log "BUOC CUOI: thoat han Claude Desktop (chuot phai icon khay -> Quit) roi mo lai."
Log "Sau do trong Claude Desktop vao Settings > Developer de thay 'gemini' va 'antigravity' dang chay."
Write-Host ""
Write-Host "Xong. Nhan Enter de dong."
Read-Host | Out-Null
