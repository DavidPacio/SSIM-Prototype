<?php /* \Pacio\Development\SSIM Proto\Admin\root\XBRL-Taxonomies\RolesTx.php

List the Taxonomy Roles

History:
2018.10.21 Started based on the SIM one

*/
require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';

Head("Roles: $TxName", true);

# Roles
echo "<h2 class=c>$TxName Roles</h2>
<table class=mc>
";
#res = $DB->ResQuery('Select R.*,E.name From Roles R Left Join Elements E on E.Id=R.ElId');
$res = $DB->ResQuery('Select * From Roles');
$n = 0;
while ($o = $res->fetch_object()) {
  if (!($n%50))
    echo "<tr class='b bg0 c'><td>Id</td><td>Role</td><td>Used On</td><td>Definition{/Label{/Reference}}</td><td>Associated Dimension(s) and/or Hypercube(s) if any</td></tr>\n";
  $roleId = (int)$o->Id;
  $usedOn = str_replace(',', ', ', $o->usedOn); # insert spaces after commas re folding
 #$definition = FoldOnSpace($o->definition, 80);
  $definition = $o->definition;
  # add label and reference via gen:link
                     # Select R.Text,A.ArcroleId from Resources R Join Arcs A on A.ToId=R.Id where A.TltN=TLTN_GenLink and A.FromId=%d
  $res2 = $DB->ResQuery(sprintf('Select R.Text,A.ArcroleId from Resources R Join Arcs A on A.ToId=R.Id Where A.TltN=%d and A.FromId=%d', TLTN_GenLink, $roleId));
  if ($res2->num_rows)
    while ($o2 = $res2->fetch_object()) {
      if ($o2->ArcroleId == TARId_ElementLabel)
        $definition .= BR.$o2->Text;
      else # Reference like {"Name":"IAS","Number":"27","IssueDate":"2018-01-01"} so zap the {} and "s
        $definition .= BR.str_replace(['":"', '","', '{', '}', '"'], [': ', ', ', ''], $o2->Text);
    }
  echo "<tr><td class='r top'>$roleId</td><td class=top>$o->Role</td><td class=top>$usedOn</td><td class=top>$definition</td>";
  # Associated Dimensions
  $tdS = '';                   # Select E.Id, E.name from Arcs A Join Elements E on E.Id=A.FromId Where E.TesgN=2 and A.ArcroleId<=6 and A.PRoleId=38 Group by A.FromId                    # last definition arcrole Id
  $res2 = $DB->ResQuery(sprintf('Select E.Id, E.name from Arcs A Join Elements E on E.Id=A.FromId Where E.TesgN=%d And A.ArcroleId<=%d and A.PRoleId=%d Order by A.FromId', TESGN_Dimension, TARId_EssenceAlias, $roleId));
  if ($res2->num_rows)
    while ($o2 = $res2->fetch_object())
      $tdS .= "<br>Dim El:$o2->Id ".Fold($o2->name, 100);
                               # Select E.Id, E.name from Arcs A Join Elements E on E.Id=A.FromId Where E.TesgN=3 and A.ArcroleId=1 and A.PRoleId=38 Group by A.FromId Order by A.FromId
                               # Group by A.FromId because there can be multiple hypercube arcs ArcroleId 1 TARId_HypercubeDim for a given FromId to the hypercube members in ToId
  # Associated Hypercubes
  $res2 = $DB->ResQuery(sprintf('Select E.Id, E.name from Arcs A Join Elements E on E.Id=A.FromId Where E.TesgN=%d and A.ArcroleId=%d and A.PRoleId=%d Group by A.FromId Order by A.FromId', TESGN_Hypercube, TARId_HypercubeDim, $roleId));
  if ($res2->num_rows)
    while ($o2 = $res2->fetch_object())
      $tdS .= "<br>Hy El:$o2->Id ".Fold($o2->name, 100); // Id == E.Id == A.FromId
  if (strlen($tdS))
    $tdS = substr($tdS, 4);
  echo "<td class=top>$tdS</td></tr>\n";
  $n++;
}
$res->free();
echo "</table>
";
Footer(true,true);
exit;

/*
Arc From type can be deduced from Arcs.TltN, for IFRS anyway:
TltN                From Type
TLTN_Definition   /- element
TLTN_Presentation |
TLTN_Calculation  |
TLTN_Label        |
TLTN_Reference    |
TLTN_GenLink      -  role

Arc To type can be deduced from Arcs.TltN and Arcs.ArcroleId as follows:
TltN                To Type
TLTN_Definition   /- element
TLTN_Presentation |
TLTN_Calculation  |
TLTN_Label        -  label resource
TLTN_Reference    -  reference resource
TLTN_GenLink      -  label or reference resource according to arcroleId TARId_ElementLabel or TARId_ElementRef
*/
