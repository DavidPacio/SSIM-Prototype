<?php /*

/inc/ErrorSA.inc

This module provides the version of Error() used by Stand Alone or non Ajax modules.

The standard version of Error() is in Error.inc

Error() logs an error and sends an error email. It may be passed an error enum or an error string.

Error() does not return, unless $ret = 0 is passed

Calls ErrorCallBack if the module includes one, for clean ups specific to the module.

The differences from the std Error() in Error.inc are:
  - does not produce a client version of the error message for possible return to the client.
  - exits directly, not via AjaxReturn(), if $ret isn't set

History:
10.02.11 Created from the Tari/LL version
07.40.11 Upgraded to match Error.inc revisions, and renamed from ErrorB.inc
30.04.11 Changed ret > 0 to mean don't send email, = 1 to mean return, != 1 to mean exit
11.01.12 Changed to use DEV only re sending email; removed use of $ErrRet
03.02.12 Added ERR_Unclean
18.10.12 Changed to use of global $AppName
13.03.13 Errors revised; BDB Lock handling added
*/

function Error($err, $ret=0) {
  global $DB, $AppName, $ErrorHdr;

  $appName  = 'Pacio '.$AppName;

  if (is_numeric($err)) {
    # numeric error code
    switch ($err) {
      case NOT_LOGGEDIN:      $errS = 'Not logged in as expected. Please reload Pacio.'; break;
      case NOP_LOCK:          $errS = 'Sorry, the requested operation could not be performed due to Pacio maintenance. Load Pacio.com in another tab to see how long the expected wait is.'; break;
      case ERR_CONNECT_DB:    $errS = "Sorry $AppName was unable to connect to the database server. Please try again in a while."; break; # $DB is NULL in this case
      case ERR_NOT_AUTH:      $errS = 'Authorisation failure - access denied'; break;
     #case ERR_AgentLoginNok: $errS = 'The current Agent login is no longer valid. Please logout of the Pacio Desktop.'; break;
     #case ERR_EntityLoginNok:$errS = 'The current Entity login is no longer valid. Please use Entities -> Change to change to another Entity.'; break;
      # Error text only returned to client if DEV is set, o'wise 'Sorry, a data, communications, or server processing error has occurred, and been reported to Pacio for correction. Please try again later.'
      case ERR_NoDatPosted: $errS = 'Invalid Posted Dat'; break;   # No $_POST['Dat'] or only 1 character (Op) of $_POST['Dat']
     #case ERR_SessionInfo: $errS = 'Invalid Session Info'; break; # Error in/with nnVi, the first Dat field after the Op code
     #case ERR_Session:     $errS = 'Invalid Session - VisId from client no good'; break; # Invalid Session = no VisId on invalid VisId when a good VisId is expected to be passed from client
      case ERR_Op:          $errS = 'Invalid or unexpected Op code'; break;
      case ERR_Unclean:     $errS = 'Data from client failed security check'; break;
      case ERR_CLIENT_DATA: $errS = 'Data from client failed a validity check'; break;
      case ERR_DB_DATA:     $errS = 'Error or inconsistency in data retrieved from DB'; break;
      default: $errS = "Unrecognised error number $err"; $err = ERR_USE_MODULE_ErrS;
    }
  }else
    $errS = $err;  # Actual error text passed rather than error enum.

  $body = "Application: $appName\n\n";

  if (isset($ErrorHdr)) { # Optionally set by module.
    $ErrorHdr .= NL;
    $body .= "$ErrorHdr\n";
  }else
    $ErrorHdr = '';

  if (isset($DB)) {
    if ($t = $DB->error) # must come before the following mysql queries
      $errS .= "\nMysql error = '$t'";
    $body .= $errS;
    $DB->commit(); # commit what we have got in case the module was running with $DB->autocommit(false);
    # Rollback any transaction in progress
    $DB->rollback();
    # Release lock if any
    #$DB->RelBDbLocks();
    # Get an mysql cooperative lock to cancel any other such lock that might have been in place, and then release it again.
    # $DB->query('Select GET_LOCK("Error",5)');
    # $DB->query('Select RELEASE_LOCK("Error")');

    # $body .= "\n\nDB->MemId = $DB->MemId\n";
    # if ($DB->VisId) {
    #   if ($o = $DB->OptObjQuery("Select * From Visits Where Id=$DB->VisId")) {
    #     $body .= "Session Data:\n VisId = $DB->VisId\n MemId = $o->MemId\n LoginN = $o->LoginN\n Inst = $o->Inst\n Bits = $o->Bits\n TnS = $DB->TnS\n";
    #     foreach ($DB->SessionA as $k => $v)
    #       $body .= " $k = $v\n";
    #   }else
    #     $body .= " VisId = $DB->VisId\n";
    # }
  }else
    $body .= $errS . "\n\n";
  foreach ($_POST as $k => $v)
    $body .= "Post[$k] = $v\n";

  $remoteAddr = 0;
  foreach (array('REQUEST_URI','SCRIPT_FILENAME','HTTP_REFERER','HTTP_USER_AGENT','REMOTE_ADDR','SERVER_PROTOCOL','REQUEST_METHOD','QUERY_STRING','HTTP_COOKIE') as $entry) {
    if (isset($_SERVER[$entry])) {
      $t = $_SERVER[$entry];
      $body .= str_pad($entry, 16) . "= $t\n";
      if ($entry == 'REMOTE_ADDR')
        $remoteAddr = $t;
    }
  }

  $headers = "From: $appName<Error@braiins.com>\r\nX-Priority: 1\r\n"; # Urgent message!

  if (!DEV) # Only send the email if DEV is not set
    mail(WEBMASTER_EMAIL, "Critical $appName Error", $body, $headers);

  # Log it
  $msg = $ErrorHdr . $errS;
  LogIt(str_replace("\n\n", NL, $msg));

  if (function_exists('ErrorCallBack'))
    ErrorCallBack($err, str_replace(NL, '<br>', $msg)); # which could exit
  else
   echo $msg;

  # Return to calling module if ret==1
  if ($ret==1) return;

  exit;
}

