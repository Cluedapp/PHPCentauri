@echo off

echo PHPCentauri API scaffolder
echo By Cluedapp

setlocal

if "%1" == "" (
	echo.
	echo Usage: scaffold [new API directory name]
	echo.
	echo Where [new API directory name] is the directory name in which to set up the scaffold
	goto :eof
)

set new_api_dir=%1
md %new_api_dir%
cd %new_api_dir%
xcopy /e %~dp0\scaffold . >nul
del cache\*.* /q <nul >nul
del public\README.md /q <nul >nul

echo.
echo Scaffolding complete. Please configure the %new_api_dir%\public directory to be exposed as your API's endpoint, in order to access it over the web.
echo.
echo Enjoy PHPCentauri!

endlocal
