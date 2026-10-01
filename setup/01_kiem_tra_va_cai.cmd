@echo off
REM 01 - Kiem tra moi truong va cai 2 goi MCP (Gemini API, Antigravity)
REM Log ghi vao: setup\logs\01_check.txt
setlocal
set "ROOT=%~dp0"
set "LOG=%ROOT%logs\01_check.txt"
if not exist "%ROOT%logs" mkdir "%ROOT%logs"
echo ==== %date% %time% ==== > "%LOG%"

echo [1] PATH tools >> "%LOG%"
for %%T in (node npm npx git agy nlm uv uvx python claude code) do (
  echo --- where %%T >> "%LOG%"
  where %%T >> "%LOG%" 2>&1
)

echo. >> "%LOG%"
echo [2] Versions >> "%LOG%"
call node -v >> "%LOG%" 2>&1
call npm -v >> "%LOG%" 2>&1
call git --version >> "%LOG%" 2>&1
call agy --version >> "%LOG%" 2>&1
call nlm --version >> "%LOG%" 2>&1
call uv --version >> "%LOG%" 2>&1
call python --version >> "%LOG%" 2>&1

echo. >> "%LOG%"
echo [3] npm global list (truoc khi cai) >> "%LOG%"
call npm ls -g --depth=0 >> "%LOG%" 2>&1

echo. >> "%LOG%"
echo [4] Cai 2 goi MCP (bo qua neu da co) >> "%LOG%"
call npm install -g mcp-server-google-antigravity @rlabs-inc/gemini-mcp >> "%LOG%" 2>&1

echo. >> "%LOG%"
echo [5] npm global list (sau khi cai) >> "%LOG%"
call npm ls -g --depth=0 >> "%LOG%" 2>&1

echo. >> "%LOG%"
echo [6] Claude Desktop config hien tai >> "%LOG%"
if exist "%APPDATA%\Claude\claude_desktop_config.json" (
  type "%APPDATA%\Claude\claude_desktop_config.json" >> "%LOG%" 2>&1
) else (
  echo KHONG CO FILE claude_desktop_config.json >> "%LOG%"
)

echo. >> "%LOG%"
echo [7] npm prefix >> "%LOG%"
call npm prefix -g >> "%LOG%" 2>&1

echo. >> "%LOG%"
echo [8] nlm login --check >> "%LOG%"
call nlm login --check >> "%LOG%" 2>&1

echo. >> "%LOG%"
echo ==== XONG ==== >> "%LOG%"
echo Da xong. Log: %LOG%
echo Cua so nay se tu dong tat sau 5 giay.
timeout /t 5 >nul
endlocal
