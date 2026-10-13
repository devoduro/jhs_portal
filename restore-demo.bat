@echo off
rem Puts the demo database back exactly as saved in database\demo\jhs_portal_demo.sql
rem (only touches jhs_portal_demo - never your real jhs_portal_db).
cd /d "%~dp0"
C:\xampp\mysql\bin\mysql.exe -uroot -e "DROP DATABASE IF EXISTS jhs_portal_demo; CREATE DATABASE jhs_portal_demo;"
C:\xampp\mysql\bin\mysql.exe -uroot jhs_portal_demo < database\demo\jhs_portal_demo.sql
echo Demo data restored.
