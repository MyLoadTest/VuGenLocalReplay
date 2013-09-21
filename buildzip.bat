:: This batch file will build the VuGenLocalReplay zip file for distribution.
::
:: To run this, you will first need to download and install 7-Zip for Windows.
::   -> http://www.7-zip.org/download.html

SET output_file=.\zip\VuGenLocalReplay.zip
:: Note that the output zip file should only include php files and the .htacess
:: file. The script files in the ./vugen folder will be ignored.

:: Remove the output zip file in case it contains files which have been deleted.
del %output_file%

:: 7-Zip program arguments: 
::   -> 7z <command> [<switches>...] <archive_name> [<file_names>...]
:: <command>
::   -> a: Add files to archive
:: <switches>
::   -> 
::   -> -r: Recurse subdirectories
::   -> -t{Type}: Set type of archive e.g. -tzip
"%ProgramFiles%\7-Zip\7z" a -r -tzip %output_file% *.php -i!*.htaccess

:: Press any key to continue . . .
pause