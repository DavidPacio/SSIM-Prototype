<?php /* Copyright 2011-2013 Braiins Ltd

BuildRoles.php

Temporary to build a start to SIM.Roles based on UK-IFRS-DPL Roles

History:
19.03.13 Written
02.07.13 Changed from B R L to SIM

*/
require '../inc/BaseSSIM.inc';

Head('Build SIM Roles', true);

/*
CREATE TABLE Roles (
  Id      tinyint unsigned not null auto_increment,
  Role    varchar(70)      not null, #
  RoleN   tinyint unsigned not null, # SSIM_Roles_RoleN_Report, SSIM_Roles_RoleN_Note, SSIM_Roles_RoleN_Prop, SSIM_Roles_RoleN_Folio
  Primary Key (Id),
  Unique Key (Role)
) CHARSET=utf8;

Max length Role = 61


*/
echo "<br><b>Building the SIM Roles table</b><br>";

$file = 'RolesI.txt';
$linesA = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
# Role, Definition

$maxLenSName = $maxLenRole = 0;

$DB->StQuery("Truncate Roles");

foreach ($linesA as $line) {
  if (InStr('31 - Full Detailed Profit and Loss', $line)) continue;
  if (InStr('98 - General Purpose Contact Information', $line)) continue;
  $roleA = explode(TAB, $line);
 #$name  = $roleA[0];
  $role = $roleA[1];
 #if (strlen($name)  > 40) die("Name $name too long");
  if (strlen($role) > 70) die("Label $role too long");
 #$maxLenName  = max($maxLenName,  strlen($name));
  $maxLenRole = max($maxLenRole, strlen($role));
  $num = (int)$role;
  if ($num < 100)
    $roleN = SSIM_Roles_RoleN_Report;
  else if ($num >= 600)
    $roleN = SSIM_Roles_RoleN_Folio;
  else
    $roleN = SSIM_Roles_RoleN_Prop;
  $DB->StQuery("Insert into Roles Values(Null, \"$role\", $roleN)");
  echo "$role<br>";
}
echo "<br>Done<br>Max length Role = $maxLenRole<br>";

Footer();

