:begin

::@echo off

:: This script expects an environment variable called "error_file" to be set, before this script starts. If the file referred to by "error_file" exists after this script ends, it means the tests failed
if "%error_file%" neq "" if exist "%error_file%" del "%error_file%"

:: Do syntax check using PHP lint (Cluedapp Programs)
for /f "tokens=1,2,*" %%i in ('lint.bat 2^>nul') do (
	if "%%i %%j" == "Errors parsing" (
		echo %%i %%j %%k
		goto fail
	)
)

:: Run unit tests
cd tests
for /f "tokens=1" %%i in ('run.bat once 2^>nul') do (
	setlocal enableextensions enabledelayedexpansion
	set result=%%i
	if "!result:FAIL=!" neq "%%i" (
		endlocal
		call run.bat once
		cd..
		goto fail
	)
	endlocal
)
cd ..

echo PHPCentauri tests successful
goto end

:fail
if "%error_file%" neq "" (
	echo. >"%error_file%"
)
echo.
echo PHPCentauri tests failed

:end
if "%error_file%" neq "" if exist "%error_file%" (
	pause
)
if "%1" neq "once" (
	pause
	goto begin
)
