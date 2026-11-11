@echo off
chcp 65001 > nul
set "PATH=C:\Program Files\Git\cmd;C:\xampp\php;%PATH%"

echo ======================================
echo   PUSH KE GITHUB - WEB APP BAPENDA
echo ======================================
echo.

set BRANCH=master

:: Pastikan remote origin sudah terhubung
git remote get-url origin > nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Remote 'origin' GitHub belum diatur!
    echo Silakan hubungkan dulu dengan perintah:
    echo   git remote add origin https://github.com/USERNAME/NAMA-REPO.git
    echo.
    pause
    exit /b 1
)

:: Cek apakah ada perubahan lokal
git status --short
echo.

set COMMIT_MSG=
set /p COMMIT_MSG="Deskripsi perubahan (atau tekan Enter jika sudah di-commit): "

if not "%COMMIT_MSG%"=="" (
    echo.
    echo [1/3] Staging semua perubahan...
    git add --all

    echo.
    echo [2/3] Commit: %COMMIT_MSG%
    git commit -m "%COMMIT_MSG%"
    if %ERRORLEVEL% NEQ 0 (
        echo [INFO] Tidak ada perubahan baru untuk di-commit atau commit dibatalkan.
    )
) else (
    echo.
    echo [INFO] Melewati commit baru, langsung push commit yang ada...
)

echo.
echo [3/3] Push ke GitHub (origin/%BRANCH%)...
git push -u origin %BRANCH%

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Push ke GitHub gagal! Periksa koneksi internet atau login GitHub Anda.
    pause
    exit /b 1
)

echo.
echo ======================================
echo   BERHASIL PUSH KE GITHUB!
echo ======================================
echo.
echo  Branch : %BRANCH%
echo  GitHub : up-to-date
echo.
pause

