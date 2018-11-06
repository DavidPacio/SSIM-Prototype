<?php /*

Admin/root/Properties.php

Lists the SSIM Properties

History:
2018.10.09 Started based on the SIM version which was based on the UK-GAAP-DPL version

*/
require '../inc/BaseSSIM.inc';
Head('SSIM Props', true);

echo "<h2 class=c>SSIM Properties</h2>
<p class=c>For SSIM Property Members see <a href=Members.php>Property Members</a>.</p>
<table class=mc>
<tr class='b bg0'><td>Id</td><td>Name</td><td>Label</td><td>Role</td></tr>
";

$res = $DB->ResQuery("Select P.Id,P.Name,P.Label,R.Role as Role From Properties P Left Join Roles R on R.Id=P.RoleId Order by Id");
while ($o = $res->fetch_object()) {
  $propId= (int)$o->Id;
  $name  = $o->Name;
  $label = $o->Label;
  $role  = $o->Role;
  echo "<tr><td class=r>$propId</td><td>$name</td><td>$label</td><td>$role</td></tr>\n";
}
echo '</table>
';
Footer(true, true);
########

