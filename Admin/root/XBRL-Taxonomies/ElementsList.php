<?php /* \Pacio\Development\SSIM Proto\Admin\root\XBRL-Taxonomies\ElementsList.php

Lists Taxonomy Elements

History:
2018.10.24 Started based on the SIM version

*/

require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';
#equire "../../structs/$TxName/NamespacesRgA.inc"; # $NamespacesRgA

Head("List Elements: $TxName", true);

if (!isset($_POST['Ewhere'])) {
  echo "<h2 class=c>List $TxName Taxonomy Element(s)</h2>\n";
  $Ewhere = 'limit 100';
  Form();
  ######
}

$Ewhere = Clean($_POST['Ewhere'], FT_STR);

// Used by Error() if called
$ErrorHdr="List Elements errored with:";

$where = trim(str_replace("%\%", "%\\\\\\%", $Ewhere)); // Re Hypercube \

if (empty($where))
  $Ewhere = $where = 'limit 10';

if (strncasecmp($where, 'limit', 5) && strncasecmp($where, 'where', 5) && strncasecmp($where, 'order', 5))
  $where = 'where ' . $where;

// Elements
$n = $DB->OneQuery('Select count(*) from Elements');
if ($res = $DB->ResQuery("Select * From Elements $where")) {
  echo "<h2 class=c>$TxName Elements ($res->num_rows of $n with '$Ewhere')</h2>\n<table class=mc>\n";
  $n = 0;
  while ($o = $res->fetch_object()) {
    $ns    = ShortNamespace((int)$o->NsId);
    $name  = Fold($o->name, 100);
    $label = FoldOnSpace($o->StdLabel, 105);
    if (!($n%50))
      echo "<tr class='b c bg0'><td class=mid>Id</td><td>Short<br>NS</td><td class=mid>Name / Standard Label</td><td class=mid>Hypercubes</td><td>Abstract<br>Concrete</td><td class=mid>Type</td><td>Subst.<br>Group</td><td class=mid>Period</td><td>Sign</td><td>Nill<br>able</td></tr>\n"; // <td>Sch<br>ema</td></tr>\n";
    echo "<tr><td>$o->Id</td><td>$ns</td><td>$name<br>$label</td><td class=c>$o->Hypercubes",
      sprintf('</td><td class=c>%s</td><td class=c>%s</td><td class=c>%s</td><td class=c>%s</td><td class=c>%s</td><td class=c>%s</td></tr>'.NL,
        $o->abstract ? 'Abstract' : 'Concrete',
        ElementTypeToStr($o->TetN),
        SubstGroupToStr($o->TesgN),
        PeriodTypeToStr($o->PeriodN),
        SignToStr($o->SignN),
        $o->nillable);
    $n += 2;
  }
  $res->free();
  echo "</table>\n";
}else
  Echo "<br><br>\n";
Form($n);
######

function Form($n=0) {
global $Ewhere, $n, $TxName;
echo <<< FORM
<div class=mc style='width:900px'>
<p>Enter Where, Limits, and/or Grouping clause(s) for an Elements table listing.<br><br>
Examples:<br>
<span class=inlb style=width:175px>Limit the list size:</span><span class=sinf>limit 100</span><br>
<span class=inlb style=width:175px>To list Dimensions:</span><span class=sinf>TesgN = 2</span><br>
<span class=inlb style=width:175px>or:</span><span class=sinf>name like '%Axis' order by name</span><br>
<span class=inlb style=width:175px>To list Hypercubes:</span><span class=sinf>TesgN = 3</span><br>
<span class=inlb style=width:175px>To list a set of Elements:</span><span class=sinf>Id in (3361,3358,4063,4031,5282,5283)</span><br>
<span class=inlb style=width:175px>To list Money Elements:</span><span class=sinf>TetN=1</span><br>
<span class=inlb style=width:175px>Elements not in definition or presentation arcs:</span><span class=sinf>Id not in (select ToId from Arcs where TltN in (1,2))</span><br>
<span class=inlb style=width:175px>Elements in Hypercube 110:</span><span class=sinf>Hypercubes like '%110%'</span><br>
<span class=inlb style=width:175px>Member elements:</span><span class=sinf>StdLabel like '%[member]'</span><br><br>
See phpMyAdmin or Pacio\Development\SSIM Proto\Docs\Taxonomy DB.txt for column (field) names<br>and see Pacio\Development\SSIM Proto\Admin\inc\\tx\ConstantsTx.inc for Taxonomy related constants.<br><br>
<b>Warnings:</b><br>
- Use single quotes for strings as in the final example above<br>
- No validity checking is performed i.e. invalid SQL will cause an error but no problems.</p>
<form method=post>
<input type=text name=Ewhere size=75 maxlength=300 value="$Ewhere"> <button class=on>List Elements</button>
</form>
<br>
</div>
FORM;

Footer(true, $n>50);
exit;
}

function ErrorCallBack($err, $errS) {
  global $TxName;
  echo "<h2 class=c>List $TxName Elements</h2>\n<p class=c>$errS</p>\n";
  Form();
}
