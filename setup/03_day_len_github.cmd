@echo off
REM 03 - Day thu muc hop-viec len GitHub (Tuan-23880095/hop-viec). Chay lai bao nhieu lan cung duoc.
setlocal EnableDelayedExpansion
cd /d "%~dp0.."
set "GIT=git"
where git >nul 2>&1
if errorlevel 1 (
  for /d %%D in ("%LOCALAPPDATA%\GitHubDesktop\app-*") do if exist "%%D\resources\app\git\cmd\git.exe" set "GIT=%%D\resources\app\git\cmd\git.exe"
)
echo Dung git: %GIT%
"%GIT%" --version || (echo Khong tim thay git. Cai Git for Windows: https://git-scm.com/download/win & pause & exit /b 1)

if not exist ".git" (
  "%GIT%" init -b main
  "%GIT%" remote add origin https://github.com/Tuan-23880095/hop-viec.git
)
"%GIT%" config user.name >nul 2>&1 || "%GIT%" config user.name "Tuan"
"%GIT%" config user.email >nul 2>&1 || "%GIT%" config user.email "dqtuanhcmus@gmail.com"
"%GIT%" add -A
"%GIT%" commit -m "Hop viec doi agent: web app PHP+SQLite, REST API, MCP server, script cai MCP" || echo (khong co gi moi de commit)
echo.
echo Dang push len GitHub... (lan dau se mo trinh duyet de dang nhap)
"%GIT%" push -u origin main
if errorlevel 1 (
  echo.
  echo Push that bai. Neu bao "rejected": repo tren GitHub da co file; chay: "%GIT%" pull --rebase origin main  roi chay lai script nay.
) else (
  echo.
  echo XONG: https://github.com/Tuan-23880095/hop-viec
)
pause
