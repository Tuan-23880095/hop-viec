@echo off
REM 04 - Kiem tra GEMINI_API_KEY (da luu boi script 02) co goi duoc Gemini API khong. Khong in key ra man hinh.
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$k=[Environment]::GetEnvironmentVariable('GEMINI_API_KEY','User'); if(-not $k){Write-Host 'Chua co GEMINI_API_KEY. Chay lai 02.';exit 1};" ^
  "$t=$k.Trim().Trim([char]34).Trim([char]39); Write-Host ('Do dai key: ' + $k.Length + ' (sau khi bo khoang trang/ngoac: ' + $t.Length + ')');" ^
  "if($t -ne $k){[Environment]::SetEnvironmentVariable('GEMINI_API_KEY',$t,'User'); Write-Host 'Da lam sach key va luu lai. Chay lai 02 de cap nhat config Claude Desktop.'};" ^
  "try{$r=Invoke-RestMethod -Uri ('https://generativelanguage.googleapis.com/v1beta/models?key=' + $t) -TimeoutSec 30; $n=($r.models | Measure-Object).Count; Write-Host ('OK: key hop le, thay ' + $n + ' model. Vi du: ' + (($r.models | Select-Object -First 3 -ExpandProperty name) -join ', '))}" ^
  "catch{Write-Host ('LOI: ' + $_.Exception.Message); Write-Host 'Key sai hoac chua bat Generative Language API. Tao key moi tai https://aistudio.google.com/app/apikey'}"
echo.
pause
