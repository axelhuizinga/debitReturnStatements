@echo off
cd %cd%
call cmd /c php RLast.php
@echo off
start "" https://pitverwaltung.de/Accounting/Imports/List

PAUSE