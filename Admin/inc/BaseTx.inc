<?php /* SSIM Proto\Admin\inc\BaseTx.inc

Base for Admin Taxonomy Non Ajax modules

Requires OntId and TxName to be passed via the url

History:
2018.10.12 Started based on UK-GAAP-DPL version
*/

$OntId  = 1;           # $_GET['OntId'];                                      /- Temporary fudge until a session system of some sort is added
$TxName = 'IFRS-2018'; # $_GET['TxName']; # e.g. IFRS-2018 and IFRS-SME-2018  |
$AppAid = 'Admin';
require 'Constants.inc';
$AppEnum = APP_Admin;
$AppName = 'Admin '.$TxName;

#efine('DB_Tx', DB_Prefix.'XBRL-Taxonomies');
#efine('DB_Tx', DB_Prefix.str_replace('-', '_', $TxName)); Not needed as '-' can be used in DB names
define('DB_Tx', DB_Prefix.$TxName);

const DB_Default = DB_Tx;

define('LOG_FILE', Root_Path."Logs/$AppAid-$TxName.log");

# Funcs
require 'ErrorSA.inc';  # The Stand Alone, non Ajax, version of Error()
require 'ClassBDB.inc'; # Database, Log, and some time functions
require 'FuncsGen.inc'; # Logging, Time, ChrList and Misc functions
require 'tx/FuncsTx.inc';
#equire 'tx/FuncsTx2.inc';
# No server or session funcs
# No Globals

$DB = new BDB();
