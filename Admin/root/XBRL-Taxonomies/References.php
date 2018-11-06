<?php /* Pacio\Development\SSIM Proto\Admin\root\XBRL-Taxonomies\References.php

Lists Taxonomy References

History:
2018.10.24 Started based on the SIM Version
2018.10.26 Working properly with Roles as well as Elements and with folding for long IFRS labels and element/role names

*/
require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';

Head("References: $TxName", true);

$defaultQuery = sprintf('Select R.Id,R.RoleId,R.Text,R.FileId,E.name,A.TltN,A.FromId,A.ArcroleId,A.PRoleId From Resources R Join Arcs A on A.ToId=R.Id Join Elements E on E.Id=A.FromId Where R.RoleId>=%d and R.RoleId<=%d limit 100',
                         TRId_Reference,TRId_CommonPracticeRef);

if (!isset($_POST['Query'])) {
  echo "<h2 class=c>List $TxName References</h2>\n";
  $Query = $defaultQuery;
  Form();
  exit;
}

// Used by Error() if called
$ErrorHdr="List References errored with:";

$Query = trim(Clean($_POST['Query'], FT_STR));
if (empty($Query) || InStrA(['insert', 'update', 'delete', 'drop'], $Query))
  $Query = $defaultQuery;

# References
# CREATE TABLE Resources (
#   Id      smallint unsigned not null auto_increment,
#   RoleId  smallint unsigned not null, # Roles.Id for xlink:role  [0..1] Anonymous
#   TextId  smallint unsigned not null, # Text.Id of content of the label or Json for the Ref
#   FileId  smallint unsigned not null, # Imports.Id of the linkbase file where defined - info purposes only
#   Primary Key (Id)
# ) CHARSET=utf8;
$n = $DB->OneQuery(sprintf('Select count(*) from Resources Where RoleId>=%d and RoleId<=%d limit 100', TRId_Reference, TRId_CommonPracticeRef));
# Select R.Id,R.RoleId,R.Text,R.FileId,E.name,A.TltN,A.FromId,A.ArcroleId,A.PRoleId From Resources R Join Arcs A on A.ToId=R.Id Join Elements E on E.Id=A.FromId Where R.RoleId>=%d and R.RoleId<=%d limit 100', TRId_Reference,TRId_CommonPracticeRef);
if ($res = $DB->ResQuery($Query)) {
  echo '<h2 class=c>'.FoldOnSpace("$TxName References ($res->num_rows of $n from query '$Query'", 120)."</h2>\n<table class=mc>\n";
  $n = 0;
  while ($o = $res->fetch_object()) {
    if (!($n%50))
      echo "<tr class='b c bg0'><td>Id</td><td>Role</td><td>Parent Role</td><td>Arcrole</td><td>Reference</td><td>Element (E.) or Role (R.)</td><td>File</td></tr>";
    echo sprintf('<tr><td class=c>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class=c>%d</td></tr>
',    $o->Id, Role($o->RoleId), Role($o->PRoleId), Arcrole($o->ArcroleId),
      str_replace(['":"', '","', '\/','{', '}', '"'], [': ', ', ', '/', ''], $o->Text),
      $o->TltN == TLTN_GenLink ? FoldOnSpace("R.$o->FromId: ".Role($o->FromId), 80) : Fold("E.$o->FromId: $o->name", 80),
      $o->FileId);
    $n++;
  }
  $res->free();
  echo "</table>\n";
}else
  Echo "<br><br>\n";
Form();
####

# From type can be deduced from Arcs.TltN, for IFRS anyway:
# TltN                From Type
# TLTN_Definition   /- element
# TLTN_Presentation |
# TLTN_Definition   |
# TLTN_Calculation  |
# TLTN_Label        |
# TLTN_Reference    |
# TLTN_GenLink      -  role

function Form() {
  global $Query;
  echo <<< FORM
<div class='mc c' style=width:900px>
<p>Edit SQL query as desired.<br>
Invalid SQL will cause an error, but no problems.</p>
<form method=post>
<input type=text name=Query size=200 maxlength=300 value="$Query"><br><br>
<button class=on>List References</button>
</form>
<br></div>
FORM;
Footer(true,true);
exit;
}

function ErrorCallBack($err, $errS) {
  global $TxName;
  echo "<h2 class=c>List $TxName References</h2>\n<p class=c>$errS</p>\n";
  Form();
}
