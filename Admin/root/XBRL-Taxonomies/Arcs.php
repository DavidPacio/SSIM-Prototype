<?php /* Pacio\Development\SSIM Proto\Admin\root\XBRL-Taxonomies\Arcs.php

Lists Arcs

History:
2018.10.21 Started based on the SIM version

*/
require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';

Head("Arcs: $TxName", true);

$defaultQuery = 'Select A.*,E.name From Arcs A Join Elements E on E.Id=A.FromId limit 100';

if (!isset($_POST['Query'])) {
  echo "<h2 class=c>List $TxName Arcs</h2>\n";
  $Query = $defaultQuery;
  Form();
  exit;
}

// Used by Error() if called
$ErrorHdr="List Arcs errored with:";

$Query = trim(Clean($_POST['Query'], FT_STR));
if (empty($Query) || InStrA(['insert', 'update', 'delete', 'drop'], $Query))
  $Query = $defaultQuery;

# Arcs
$n = $DB->OneQuery('Select count(*) from Arcs');
if ($res = $DB->ResQuery($Query)) {
  echo '<h2 class=c>'.FoldOnSpace("$TxName Arcs ($res->num_rows of $n from query '$Query'", 120)."</h2>\n<table class=mc>\n";
  $n = 0;
  while ($o = $res->fetch_object()) {
    if (!($n%50))
      echo "<tr class='b c bg0 mid'><td>Id</td><td>Arc Type</td><td class=mid>Arc Role</td><td class=mid>Parent Role</td><td>From</td><td>To Element (E.), Label (L.), or Reference (R.)</td><td>Or<br>der</td><td class=mid>Use</td><td>Prio<br>rity</td><td>Pref. Label</td><td>Clo<br>sed</td><td>Cont<br>ext</td><td>Usa<br>ble</td><td>Target<br>Role</td></tr>\n";
    echo sprintf('<tr><td class=c>%d</td><td class=c>%s</td><td>%s</td><td>%s</td><td>%s</td><td style=width:150px>%s</td><td>%s</td><td class=c>%s</td><td class=c>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>
',  $o->Id, LinkTypeToStr($o->TltN), Arcrole($o->ArcroleId), FoldOnSpace(Role($o->PRoleId), 50),
    Fold("E.$o->FromId $o->name", 50), To($o->TltN, $o->ToId, $o->ArcroleId),
        Order($o->ArcOrder),  UseTypeToStr($o->ArcUseN), $o->priority>0 ? "$o->priority" : '',
  # str_replace(' Label', '', Role($o->PrefLabelRoleId)),
    Role($o->PrefLabelRoleId),
    BoolToStr($o->ClosedB), ContextTypeToStr($o->ContextN),
    BoolToStr($o->UsableB), Role($o->TargetRoleId));
    $n++;
  }
  $res->free();
  echo "</table>\n";
}else
  Echo "<br><br>\n";
Form();
####

# To types can be deduced from Arcs.TltN and Arcs.ArcroleId as follows:
# TltN                 To Type
# TLTN_Definition   /- element
# TLTN_Presentation |
# TLTN_Calculation  |
# TLTN_Label        -  label resource
# TLTN_Reference    -  reference resource
# TLTN_GenLink      -  label or reference resource according to arcroleId TARId_ElementLabel or TARId_ElementRef

function To($tltN, $toId, $arcroleId) {
  switch ($tltN) {
    case TLTN_Definition:
    case TLTN_Presentation:
    case TLTN_Calculation: return "E.$toId " . ElName($toId);
    case TLTN_Label:
    case TLTN_Reference:   return Resource($toId, $tltN);
    case TLTN_GenLink:
      switch ($arcroleId) {
        case TARId_ElementLabel: return Resource($toId, TLTN_Label);
        case TARId_ElementRef:   return Resource($toId, TLTN_Reference);
      }
  }
  return $id;
}

function Resource($toId, $tltN) {
  global $DB;
  $text = $DB->StrOneQuery("Select Text from Resources where Id=$toId");
  if ($tltN == TLTN_Label)
    return "L.$toId $text";
  # else Reference like {"Name":"IAS","Number":"27","IssueDate":"2018-01-01"} so zap the {} and "s
  #                     {"Name":"IAS","Number":"10","IssueDate":"2018-01-01","Paragraph":"22","Subparagraph":"g",
  #                       "URI":"http:\/\/eifrs.ifrs.org\/eifrs\/XBRL?type=IAS&num=10&date=2018-03-01&anchor=para_22_g&doctype=Standard","URIDate":"2018-03-16"}
  return "R.$toId " . str_replace(['":"', '","', '\/','{', '}', '"'], [': ', ', ', '/', ''], $text);
}

function Order($order) {
  if (!$order) {
    if ($order === '0') return '0';
    return '';
  }
  return $order/1000000;
}

function Form() {
  global $Query;
  echo <<< FORM
<div class='mc c' style=width:900px>
<p>Edit SQL query as desired.<br>
Invalid SQL will cause an error, but no problems.</p>
<form method=post>
<input type=text name=Query size=200 maxlength=300 value="$Query"><br><br>
<button class=on>List Arcs</button>
</form>
<br></div>
FORM;
Footer(true,true);
exit;
}

function ErrorCallBack($err, $errS) {
  global $TxName;
  echo "<h2 class=c>List $TxName Arcs</h2>\n<p class=c>$errS</p>\n";
  Form();
}
