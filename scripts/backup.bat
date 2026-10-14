@echo off
REM ======================================================================
REM  Backup diario do banco (RNF07) - ver nota 11
REM  O banco roda no Docker, entao o mysqldump e executado DENTRO do conteiner.
REM  Agendar (todo dia as 22:30):
REM    schtasks /create /tn "Backup Loja" /tr "C:\caminho\codigo\scripts\backup.bat" /sc daily /st 22:30
REM  Restaurar:
REM    docker compose exec -T db sh -c "mysql -u root -p\"$MYSQL_ROOT_PASSWORD\" loja_db" < backups\loja_AAAA-MM-DD.sql
REM ======================================================================
cd /d "%~dp0.."
if not exist backups mkdir backups

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"') do set HOJE=%%i

docker compose exec -T db sh -c "mysqldump -u root -p\"$MYSQL_ROOT_PASSWORD\" --single-transaction --routines --triggers loja_db 2>/dev/null" > backups\loja_%HOJE%.sql
if errorlevel 1 (
  echo ERRO: backup falhou. O Docker e o banco estao ligados?
  exit /b 1
)

REM Retencao de 30 dias: apaga backups mais antigos
forfiles /p backups /m loja_*.sql /d -30 /c "cmd /c del @path" 2>nul

echo Backup salvo em backups\loja_%HOJE%.sql
