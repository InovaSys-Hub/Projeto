@echo off

:: Garante que a pasta exista
if not exist "C:\temp" mkdir "C:\temp"

:: Cria arquivo com nome de usuário e data/hora
echo Usuário: %USERNAME% > C:\temp\log_sessao.txt
echo Data: %DATE% >> C:\temp\log_sessao.txt
echo Hora: %TIME% >> C:\temp\log_sessao.txt

:: Lista os processos em execução
tasklist >> C:\temp\log_sessao.txt

:: Pega histórico recente do Explorador
echo Arquivos Recentes: >> C:\temp\log_sessao.txt
dir "%APPDATA%\Microsoft\Windows\Recent" >> C:\temp\log_sessao.txt
