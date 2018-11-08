<?php /* SSIM Proto\Admin\inc\BaseTx.inc

Base for Admin Taxonomy Non Ajax modules

History:
2018.10.12 Started based on UK-GAAP-DPL version
2018.11.07 Session handling added
*/

$AppAid = 'Admin';
require 'Constants.inc';
$AppEnum = APP_Admin;

# Funcs
require 'ErrorSA.inc';  # The Stand Alone, non Ajax, version of Error()
require 'ClassBDB.inc'; # Database, Log, and some time functions
require 'FuncsGen.inc'; # Logging, Time, ChrList and Misc functions
require 'tx/FuncsTx.inc';
#equire 'tx/FuncsTx2.inc';
require 'FuncsServer.inc';
require 'Session.inc';

$DB = new BDB(DB_SSIM);
# Expect a session to exist on for example a return here from running a module
if (is_null($jsonData = SessionOpen()))
  die('Session error - go back please');
unset($DB);

# phpinfo();
# DumpExport('jsondata decode with true',json_decode($jsonData, true));
#list('OntId' => $OntId, 'TxName' => $TxName) = json_decode($jsonData, true); # Requires PHP 7.1
#$jsonDataA = json_decode($jsonData, true);
#$OntId  = $jsonDataA['OntId'];
#$TxName = $jsonDataA['TxName'];
list($OntId, $TxName) = json_decode($jsonData, true); # ok when array stored via json is X indexed
$AppName = 'Admin '.$TxName;
define('LOG_FILE', Root_Path."Logs/$AppAid-$TxName.log");

$DB = new BDB(DB_Prefix.$TxName);
