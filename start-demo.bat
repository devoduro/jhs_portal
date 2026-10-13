@echo off
rem Starts the demo school (database jhs_portal_demo) at http://127.0.0.1:8002
rem Logins: admin@demo.school / accounts@demo.school / teacher@demo.school  -  password: password
cd /d "%~dp0public"
set DB_DATABASE=jhs_portal_demo
php -S 127.0.0.1:8002 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
