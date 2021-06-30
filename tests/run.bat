@echo off
:begin
::php -f %~nx0
php -f phptest.php
if "%1" neq "once" (
	pause
	echo.
	goto begin
)
