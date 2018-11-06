<?php /* Pacio\Development\SSIM Proto\Admin\root\XBRL-Taxonomies\Arcroles.php

Lists the Taxonomy Roles

History:
2018.10.21 Started based on the SIM version

*/
require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';

Head("Arcroles: $TxName", true);

# CREATE TABLE Arcroles (
#   Id            tinyint  unsigned not null auto_increment,
#   Arcrole       tinytext          not null, # Arcrole uri with http://, www. etc stripped to leave only what is necessary to identify the arcrole. max lngth 23
#   UsedOnN       tinyint  unsigned not null, # link:usedOn [1..*]   TLTN_Definition ....
#   definition    tinytext              null, # 10 - Profit and Loss Account  max length 155
#   PacioDef      tinytext          not null, # Short Pacio definition
#   cyclesAllowed tinytext              null, # cyclesAllowed [1..1] Anonymous max length  10 none | undirected  (any not used in UK GAAP)
#   Uses          smallint unsigned not null, # Number of uses
#   FileIds       text              not null, # csv list of Imports.Id(s) of file(s) where defined
#   Primary Key (Id)
# ) CHARSET=utf8;

# Arcroles
echo "<h2 class=c>$TxName Arcroles</h2>
<p class=c>These are the Arcroles from arcroleType elements and arcrole uris.</p>
<table class=mc>
";
$res = $DB->ResQuery('Select * From Arcroles');
echo "<tr class='b bg0'><td>Id</td><td>Arcrole</td><td>Arc Type<br>Used On</td><td>Pacio Definition</td><td>XBRL Definition</td><td>Cycles<br>Allowed</td></tr>\n";
while ($o = $res->fetch_object()) {
  $usedOnS = LinkTypeToStr($o->UsedOnN);
  echo "<tr><td>$o->Id</td><td>$o->Arcrole</td><td>$usedOnS</td><td>$o->PacioDef</td><td>$o->definition</td><td>$o->cyclesAllowed</td></tr>\n";
}
$res->free();
echo "</table><br><p class=c>Note: 'From' and 'To' in the Pacio Definitions refer to the From and To attributes of Arcs.</p><br>\n";
Footer(true, false); # false for no top btn
exit;
