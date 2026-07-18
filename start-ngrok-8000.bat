@echo off
title Ngrok HTTP 8000
color 0A

echo ==========================================
echo     Menjalankan ngrok untuk port 8000
echo ==========================================
echo.

where ngrok >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Perintah ngrok tidak ditemukan.
    echo Pastikan ngrok sudah terinstal dan masuk ke PATH Windows.
    echo.
    echo Alternatif: ganti baris "ngrok http 8000" dengan lokasi penuh,
    echo contoh:
    echo "C:\ngrok\ngrok.exe" http 8000
    echo.
    pause
    exit /b 1
)

echo Tunnel akan dihentikan jika jendela ini ditutup.
echo Tekan Ctrl+C untuk menghentikan ngrok.
echo.

ngrok http http://192.168.11.196:80

echo.
echo Ngrok telah berhenti.
pause
