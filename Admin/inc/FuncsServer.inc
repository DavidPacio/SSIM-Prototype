<?php /* \Pacio\Development\SSIM Proto\Admin\inc\FuncsServer.inc

Server ip related funcs for use in a simple ip based session system.
Derived from old /Com/inc/FuncsServer.inc which included url, referrer, user agent etc functions also

History:
2018.11.07 Derived from old /Com/inc/FuncsServer.inc

ToDo
----
Change getIp() to return the values rather than using globals

/*

Server and IP functions
-----------------------
void   GetIp()             Returns Ip and Source (server var) in $IpG and $IpN
bool   CheckIP($ip)        Helper fn for GetIp()
string GetAndCheckIp($v)   "
void   GetOtherIp()        "
string GetServerVar($v)    "

*/

// GetIp as per the NMS work
// =====
// Derived from the phpMyAdmin function PMA_getIp() in phpMyAdmin//libraries/ip_allow_deny.lib.php
// See also getAddress() in The PHP Anthology Vol II page 224 which checks HTTP_CLIENT_IP and is also different in other ways.
// 11.06.09 Revised to use an improved version of code from http://www.grantburton.com/tag/http_client_ip/
//          and to return an array of the ip from REMOTE_ADDR and via the proxy look thru attempt, set as '=' if the same and > ''
// 15.06.09 Revised to return the source of the IP used for the other (proxy look thru attempt)
// 05.07.09 Revised to 'return' just one IP address in global $IpG with its source in global $IpS.
// 23.01.10 Revised to 'return' the source as an enum in global $IpN.
function GetIp() {
  global $IpG, $IpN;
  if (GetAndCheckIp('REMOTE_ADDR')) {
    $ipR  = $IpG;
    $iprN = IPS_REMOTE_ADDR;
  }else
    $iprN = 0;
  // See if we have an 'other' ip
  $ipoN = GetOtherIp(); // with other ip in $IpG
  if ($iprN) { // had a REMOTE_ADDR
    if (!$ipoN || $ipoN == $iprN) { // but no other or other is the same, so use REMOTE_ADDR
      $IpG = $ipR;
      $IpN = $iprN;
    }else // found an other which is different and already in $IpG so use it
      $IpN = $ipoN;
  }else // no REMOTE_ADDR so use other which is n $IpG and $ipoN even if '' and 0
    $IpN = $ipoN;
}

// Helper fn for GetIp()
function CheckIP($ip) {
  global $IpG;
  if (($IpG = trim($ip))) { /* && ($long = ip2long($IpG)) !== false) { 07.04.11 djh removed re local ip based identification and ipv6
    $private_ips = [
    [0,          50331647],   // 0.0.0.0       2.255.255.255
    [167772160,  184549375],  // 10.0.0.0      10.255.255.255
    [2130706432, 2147483647], // 127.0.0.0     127.255.255.255
    [2851995648, 2852061183], // 169.254.0.0   169.254.255.255
    [2886729728, 2887778303], // 172.16.0.0    172.31.255.255
    [3221225984, 3221226239], // 192.0.2.0     192.0.2.255
    [3232235520, 3232301055], // 192.168.0.0   192.168.255.255
    [4294967040, 4294967295]];// 255.255.255.0 255.255.255.255
    foreach ($private_ips as $r)
      if ($long >= $r[0] && $long <= $r[1])
        return false;
    */
    return true;
  }
  return false;
}

// Helper fn for GetIp()
function GetAndCheckIp($v) {
  return CheckIP(GetServerVar($v));
}

// Helper fn for GetIp()
function GetOtherIp() { // Other than REMOTE_ADDR
  global $IpG;
  if (GetAndCheckIp('HTTP_CLIENT_IP')) return IPS_HTTP_CLIENT_IP;
  foreach (explode(',', GetServerVar('HTTP_X_FORWARDED_FOR')) as $ip)
    if (CheckIP($ip)) return IPS_HTTP_X_FORWARDED_FOR;
  if (GetAndCheckIp('HTTP_X_FORWARDED'))         return IPS_HTTP_X_FORWARDED;
  if (GetAndCheckIp('HTTP_X_CLUSTER_CLIENT_IP')) return IPS_HTTP_X_CLUSTER_CLIENT_IP;
  if (GetAndCheckIp('HTTP_FORWARDED_FOR'))       return IPS_HTTP_FORWARDED_FOR;
  if (GetAndCheckIp('HTTP_FORWARDED'))           return IPS_HTTP_FORWARDED;
  $IpG = '';
  return 0;
}

function GetServerVar($v) {
  return isset($_SERVER[$v]) ? trim($_SERVER[$v]) : '';
}

?>
