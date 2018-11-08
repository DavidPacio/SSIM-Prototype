<?php /* \Pacio\Development\SSIM Proto\Admin\inc\Session.inc

Session functions to provide a simple ip based session system for temporary SSIM Proto development use.
For a fancier system using client local storage see the prior LeLotto, NMS, TARI, and Braiins work.

History:
2018.11.07 Started

ToDo
----
/*

Local Function
--------------
void   NewSession($actionN, $inst=0, $scrRes=0, $tzo=0, $ssRef=0, $bVer=0)

Public Functions
----------------
void           SessionStart($jsonData)
jsondata/false SessionOpen()
*/

####################
# Public Functions #
####################

# SessionStart
# ------------
# Called to start a session with jsonised data to be stored for the session
function SessionStart($jsonData) { # data from client
  global $DB, $IpG, $IpN;
  GetIp(); # Sets Ip and Source (server var) in globals $IpG and $IpN
  if (($id = $DB->OneQuery(sprintf('Select Id from %s.Visits where Ip=\'%s\' and IpN=%d', DB_SSIM, $IpG, $IpN))) > 0)
    # Have a Visits record so update it
    $DB->StQuery(sprintf('Update %s.Visits Set Data=\'%s\' where Id=%d', DB_SSIM, $jsonData, $id));
  else
    # No Visits record for this ip so add one
    $DB->StQuery(sprintf('Insert %s.Visits Set Ip=\'%s\',IpN=%d,Data=\'%s\'', DB_SSIM, $IpG, $IpN, $jsonData));
} # end of SessionStart

# SessionOpen
# -----------
# Called when a session is expected to exist.
# Returns the json session data if a record for the ip exists, otherwise NULL as returned by StrOneQuery on no record found
function SessionOpen() {
  global $DB, $IpG, $IpN;
  GetIp();
  return $DB->StrOneQuery(sprintf('Select Data from %s.Visits where Ip="%s" and IpN=%d', DB_SSIM, $IpG, $IpN)); # returns NULL on no match
} # end of SessionOpen
