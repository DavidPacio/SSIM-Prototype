<?php /*

Admin BaseSSIM.inc
Admin Base for Non Ajax modules

History:
2018.10.07 Changed for Pacio SSIM Proto Admin
*/

$AppAid = 'Admin';
require 'Constants.inc';
$AppEnum = APP_Admin;
$AppName = 'Pacio SSIM Proto Admin SSIM';
const DB_Default = DB_SSIM;

define('LOG_FILE', Root_Path."Logs/$AppAid.log");

# App Constants, Funcs
require 'ConstantsSSIM.inc';
require 'ErrorSA.inc';  # The Stand Alone, non Ajax, version of Error()
require 'ClassBDB.inc'; # Database, Log, and some time functions
require 'FuncsGen.inc'; # Logging, Time, ChrList and Misc functions
require 'Funcs.inc';
# No server or session funcs
# No Globals

$DB = new BDB();

