<?php /*

Admin/root/Roles.php

Lists the SSIM Roles

History:
2018.10.09 Started based on SIM version

*/
require '../inc/BaseSSIM.inc';

Head('SSIM Roles', true);

/*
const SSIM_Roles_RoleN_Report = 1;
const SSIM_Roles_RoleN_Note   = 2;
const SSIM_Roles_RoleN_Prop   = 3;
const SSIM_Roles_RoleN_Folio  = 4; */

$usedWithA = [0,'Reports', 'Notes', 'Properties', 'Folios'];

echo "<h2 class=c>SSIM Roles</h2>
<p class=c>This is a set of roles used with SSIM for documentation purposes, mainly with Properties and Folios. They serve no functional purpose.<br>Multiple Properties or Folios may be assigned the same role of the right 'Used With' type.</p>
<table class=mc>
";
$res = $DB->ResQuery('Select * From Roles Order by Id');
$n = 0;
while ($o = $res->fetch_object()) {
  if (!($n%50))
    echo "<tr class='b bg0 c'><td rowspan=2>Id</td><td rowspan=2>The Role</td><td rowspan=2>Used With</td><td colspan=2>Associated Properties or Folios</td></tr><tr class='b bg0 c'><td>Id</td><td>Label</td></tr>\n";
  $id = (int)$o->Id;
  $roleN = (int)$o->RoleN;
  if ($id === 1) {
    # Fudge for temporary 16.04.13 use of Role 1 with property Objects
    $re2 = $DB->ResQuery("Select Id,Label From Properties Where RoleId=$id Order by Id");
    $numRows = $re2->num_rows;
    $roleN = SSIM_Roles_RoleN_Prop;
  }else
  switch ($roleN) {
    case SSIM_Roles_RoleN_Prop:  $re2 = $DB->ResQuery("Select Id,Label From Properties Where RoleId=$id Order by Id"); $numRows = $re2->num_rows; break;
    case SSIM_Roles_RoleN_Folio: $re2 = $DB->ResQuery("Select Id,Label From Folios Where RoleId=$id Order by Id"); $numRows = $re2->num_rows; break;
    default: $numRows = 0; break;
  }
  $usedWith = $usedWithA[$roleN];
  if ($numRows > 1)
    echo "<tr><td rowspan=$numRows class=r>$id</td><td rowspan=$numRows>$o->Role</td><td rowspan=$numRows>$usedWith</td>";
  else
    echo "<tr><td class=r>$id</td><td>$o->Role</td><td>$usedWith</td>";
  $tr = '';
  if ($numRows) {
    while ($o = $re2->fetch_object()) {
      echo "$tr<td class=r>$o->Id</td><td>$o->Label</td>";
      $tr = "</tr>\n<tr>";
    }
    $re2->free();
  }
  echo "</tr>\n";
  $n++;
}
$res->free();
echo "</table>
";
Footer(true,true);
exit;
