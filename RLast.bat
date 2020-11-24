rem @echo %1;
cd %cd%
call cmd /c php RLast.php
IF %ERRORLEVEL% EQU 0 start "" https://pitverwaltung.de/Accounting/Imports/List