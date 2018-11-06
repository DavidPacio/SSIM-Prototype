<?php /* \Pacio\Development\SSIM Proto\Admin\root\XBRL-Taxonomies\Dimensions.php

Lists The Taxonomy Dimensions

History:
2018.10.24 Started based on the SIM version

*/
require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';

Head("Dimensions List: $TxName", true);

echo "<h2 class=c>$TxName Dimensions</h2>
<p class=c>For Dimension Members see <a href=DiMes.php>Dimension Members</a>.</p>
<table class=mc>
";

# CREATE TABLE Dimensions (
#   Id      smallint unsigned not null auto_increment,
#   ElId    smallint unsigned not null, # Elements.Id of the dimension
#   RoleId  smallint unsigned not null, # Roles.Id    of the dimension = Arcs.PRoleId
#   Primary Key (Id)
# ) CHARSET=utf8;

$n=0;
# Select D.*,E.name,R.Text From Dimensions D Join Elements E on E.Id=D.ElId Join Arcs A on A.TltN=4 and A.FromId=D.ElId Join Resources R on R.Id=A.ToId and R.RoleId=1
# don't actually need the "and R.RoleId=1" here presumably because dimensions only have standard labels, but include it for the general case of fetching std labels
#res = $DB->ResQuery(sprintf('Select D.*,E.name,R.Text From Dimensions D Join Elements E on E.Id=D.ElId Join Arcs A on A.TltN=%d and A.FromId=D.ElId Join Resources R on R.Id=A.ToId and R.RoleId=%d', TLTN_Label, TRId_StdLabel));
$res = $DB->ResQuery('Select D.*,E.name,E.StdLabel From Dimensions D Join Elements E on E.Id=D.ElId');
while ($o = $res->fetch_object()) {
  if (!($n%50))
    echo "<tr class='b bg0'><td>Id</td><td class=c>Tx<br>Id</td><td>Tx Name</td><td>Standard Label</td><td>Role(s)</td></tr>\n";
  $dimId = (int)$o->Id;
  $name  = Fold($o->name, 80);
 #$stdLabel = FoldOnSpace($o->Text, 100); # StdLabel
  $stdLabel = FoldOnSpace($o->StdLabel, 100); # StdLabel
  foreach (explode(COM, $o->RoleIds) as $j => $roleId)
    if (!$j)
      $rolesS = Role($roleId, true); # true = trailing [id]
    else
      $rolesS .= '<br>' . Role($roleId, true);
  echo "<tr><td class=r>$dimId</td><td class=r>$o->ElId</td><td>$name</td><td>$stdLabel</td><td>$rolesS</td></tr>\n";
  $n++;
}
echo '</table>
';
Footer();
########

