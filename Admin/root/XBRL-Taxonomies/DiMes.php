<?php /* \Pacio\Development\SSIM Proto\Admin\root\XBRL-Taxonomies\DiMes.php

Lists Dimensions and Dimension Member info.

History:
2018.10.23 Started based on SIM version

ToDo
----

*/
require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';
#equire "../../structs/$TxName/DiMesA.inc";     # $DimNamesA
#equire "../../structs/$TxName/DimNamesA.inc";  # $DimNamesA
#equire "../../structs/$TxName/DiMeNamesA.inc"; # $DiMeNamesA

Head("DiMes: $TxName", true);

if (!isset($_POST['Sel'])) {
  echo "<h2 class=c>$TxName Dimension Members Listing</h2>\n";
  $ShortChecked = ' checked'; # short as the initial default
  Form();
  Footer(false, false); # no time, not top btn
  exit;
}

switch (isset($_POST['Sel']) ? Clean($_POST['Sel'], FT_INT) : 1) {  #default to Short first time in
  case 1:
    $ShortB = true;
    $titleExtra = 'Short';
    break;
  case 2:
    $ShortB = false;
    $titleExtra = 'Full';
}

# $NotesB = isset($_POST['Notes']);

echo "<h2 class=c>$TxName Dimension Members $titleExtra Listing</h2>
<p class=c>For a simple list of Dimensions without the Dimension Member detail see <a href='Dimensions.php'>Dimensions</a>.</p>
<table class=mc>
";
# ($NotesB ? "For Notes on the meaning of the columns and codes see the end of the report." : "For Notes on the meaning of the columns and codes check the 'Include Notes' option at the end and repeat the report."),

# For IFRS DimId 13
# diMesAA = array (
#   147 => array (
#     35 => array (0 => 2324, 1 => 0, 2 => 0, 3 => 'Entity\'s total for business combinations [member]',),
#     36 => array (0 =>  372, 1 => 0, 2 => 0, 3 => 'Business combinations [member]',),
#     37 => array (0 =>  180, 1 => 1, 2 => 0, 3 => 'Aggregated individually immaterial business combinations [member]',),
#   ),
#   148 => array (
#     38 => array (0 => 2324, 1 => 0, 2 => 0, 3 => 'Entity\'s total for business combinations [member]',),
#     39 => array (0 => 372,  1 => 0, 2 => 0, 3 => 'Business combinations [member]',),
#     40 => array (0 => 180,  1 => 1, 2 => 0, 3 => 'Aggregated individually immaterial business combinations [member]',),
#   ),
#   149 => array (
#     41 => array (0 => 2324, 1 => 0, 2 => 0, 3 => 'Entity\'s total for business combinations [member]',),
#     42 => array (0 => 372,  1 => 0, 2 => 0, 3 => 'Business combinations [member]',),
#     43 => array (0 => 180,  1 => 1, 2 => 0, 3 => 'Aggregated individually immaterial business combinations [member]',),
#   ),
#   150 => array (
#     44 => array (0 => 2324, 1 => 0, 2 => 0, 3 => 'Entity\'s total for business combinations [member]',),
#     45 => array (0 => 372,  1 => 0, 2 => 0, 3 => 'Business combinations [member]',),
#     46 => array (0 => 180,  1 => 1, 2 => 0, 3 => 'Aggregated individually immaterial business combinations [member]',),
#   ),
#   151 => array (
#     47 => array (0 => 2324, 1 => 0, 2 => 0, 3 => 'Entity\'s total for business combinations [member]',),
#     48 => array (0 => 372,  1 => 0, 2 => 0, 3 => 'Business combinations [member]',),
#     49 => array (0 => 180,  1 => 1, 2 => 0, 3 => 'Aggregated individually immaterial business combinations [member]',
#     ),
#   ),
# )
# results in $RepeatForAllRolesB being false


$n = 0;
$res = $DB->ResQuery('Select D.*,E.name,E.StdLabel From Dimensions D Join Elements E on E.Id=D.ElId');
while ($o = $res->fetch_object()) {
  if ($n <= 0) {
    $n = 50;
    echo "<tr class='b bg0'><td>Id</td><td>Dimension<br>TxId Name / Label / Role(s)</td><td>Dimension Member Label</td><td class=c>Lev<br>el</td><td class=c>DiMe<br>Type</td><td class=c>DiMe<br>Id</td><td class=c>E.Id</td></tr>\n";
  }
  $dimId  = (int)$o->Id;
  $txName = Fold("$o->ElId $o->name", 100);
  $label  = FoldOnSpace($o->StdLabel, 100);
  #$dimShortName = $DimNamesA[$dimId];
  $dimRoleIdsA = explode(COM, $o->RoleIds);
  $numDimRoles = count($dimRoleIdsA);
  foreach ($dimRoleIdsA as $j => $roleId)
    if (!$j)
      $rolesS = Role($roleId, true); # true = trailing [id]
    else
      $rolesS .= '<br>' . Role($roleId, true);
  $re2 = $DB->ResQuery("Select M.Id,M.ElId,M.Level,M.Bits,M.RoleId,E.StdLabel From DimensionMembers M Join Elements E on E.Id=M.ElId Where DimId=$dimId");
  $numRows = $re2->num_rows;
  # Can have multiple roles
  $diMesAA = []; # [roleId => [diMeId => [elId, level, bits, StdLabel]]]
  while ($o2 = $re2->fetch_object())
    $diMesAA[$o2->RoleId][$o2->Id] = [(int)$o2->ElId, (int)$o2->Level, (int)$o2->Bits, $o2->StdLabel];
  $re2->free();
  # DumpExport('diMesAA', $diMesAA);
  # expect count of $diMesAA to == $numDimRoles
  if (count($diMesAA) != $numDimRoles) die('count($diMesAA) '.count($diMesAA)." != \$numDimRoles $numDimRoles");
  $RepeatForAllRolesB = false; # set to true if have > 1 role and not all diMes occur for all roles
  if ($numDimRoles > 1) {
    # have multiple roles so check to see if all diMes occur for all roles
    $numDiMes = 0;
    foreach ($diMesAA as $rId => $diMesA) { # [diMeId => [elId, level, bits, StdLabel]]]
      # loop through roles
      if (!$numDiMes)
        $numDiMes = count($diMesA);
      else{
        if ($numDiMes != count($diMesA)) {
          $RepeatForAllRolesB = true; # as all diMes don't occur for all roles
          # echo "\$numDiMes $numDiMes != count(\$diMesA) ". count($diMesA)." for role $rId<br>";
          # DumpExport("Dim $dimId diMesAA", $diMesAA);
        }
      }
    }
    if (!$RepeatForAllRolesB)
      $numRows = $numDiMes;
  }
  $numDiMesRows = $numRows;
  if ($ShortB && $numDiMesRows>12)
    $numRows = 7; # 3 at the start + ... + 3 at the end
  $numDiMesProcessed = 0;
  $firstB = true;
  foreach ($diMesAA as $rId => $diMesA) { # [diMeId => [elId, level, bits, StdLabel]]]
    # loop through roles
    foreach ($diMesA as $diMeId => $diMeA) { # [elId, level, bits, StdLabel]
      # loop through diMes
      $numDiMesProcessed++;
      if ($ShortB && $numRows < $numDiMesRows && $numDiMesProcessed>3) {
        if ($numDiMesProcessed==4) {
          $indent = str_pad('', $level*6*2, '&nbsp;'); # 2 nb spaces per level
          echo "$tr<td class=l colspan=5>$indent....</td></tr>\n";
          $n--;
        }
        if ($numDiMesProcessed < $numDiMesRows-2)
          continue;
      }
      list($dmElId, $level, $bits, $diMeLabel) = $diMeA;
      if ($RepeatForAllRolesB)
        $diMeLabel = "R $rId: ".$diMeLabel;
      $type      = DiMeInfo($bits);
      $diMeLabel = FoldOnSpace($diMeLabel, 100);
      if ($bits & DiMeB_Default) $diMeLabel = "Default [$diMeLabel]"; # label in []s if the default
      $indent   = str_pad('', $level*6*2, '&nbsp;'); # 2 nb spaces per level
      # $muxList  = ($diMeA[DiMeI_MuxListA] ? implode(',', $diMeA[DiMeI_MuxListA]) : '');
      # $sumList  = '';
      # if ($bits & DiMeB_SumList)
      #   $sumList = implode(',', $diMeA[DiMeI_SumListA]);
      # else if ($bits & DiMeB_SumKids)  # Don't ever have both
      #   $sumList .= 'Kids';
      if ($firstB) {
        $firstB = false;
        echo "<tr class='c brdt2'><td rowspan=$numRows class=top>$dimId</td><td rowspan=$numRows class='l top'>$txName<br>$label<br>$rolesS</td>";
        $tr = '';
      }else
        $tr = '<tr class=c>';
      #cho "$tr<td class=l>$indent$diMeName</td><td>$level</td><td>$type</td><td>$diMeId</td><td>$sumList</td><td>$muxList</td><td class=r>$dmElId</td></tr>\n";
      echo "$tr<td class=l>$indent$diMeLabel</td><td>$level</td><td>$type</td><td>$diMeId</td><td class=r>$dmElId</td></tr>\n";
      $n--;
    } # end diMes loop
    if (!$RepeatForAllRolesB)
      break; # out of the roles loop foreach ($diMesAA as $rId => $diMesA) # [diMeId => [elId, level, bits, StdLabel]]]
  }
}

# echo '<tr class=c><td></td><td></td><td class=l>Unallocated (Pseudo Dimension Member)</td><td></td><td>Z</td><td>9999</td><td></td><td></td><td></td></tr>
echo '</table>
';

# if ($NotesB) {
#   echo "<div class=mc style=width:1215px>
# <h3>Notes</h3>
# <p>The Dimension{.Dimension Member} References in the third column above are used in formats if a dimension is involved,<br>
# by appending them to a Bro name after a comma e.g. PL.Revenue.GeoSeg.ByDestination,Countries.UK</p>
# <p>The first part of the reference is the Dimension short name. The 2nd part, if included, is the normalised, short version of the Dimension Member Taxonomy Name.<br>
# Entries with no {.Dimension Member} component to their references are defaults, where just the Dimension short name gives the default. The actual default name is shown in []s.</p>
# <p class=mb0><b>Dimension Member Type Codes</b>, if present, mean:</p>
# <table class=itran>
# <tr><td>D</td><td>Default in Taxonomy</td></tr>
# <tr><td>B</td><td>Braiins dimension (member)</td></tr>
# <tr><td>R</td><td>Report type - only for Reporting use e.g. of summed values. Thus is Non-Posting</td></tr>
# <tr><td>Y</td><td>prior Year adjustment (Restated) type</td></tr>
# <tr><td>Z</td><td>Reserved for Braiins internal use</td></tr>
# </table>
#
# <p class=mt05>Dimension members without  DiMe and Prop Type codes may be used with any Bro whose Hypercube includes the Dim in question, unless excluded by other settings.</p>
#
# <p><b>Sum</b>: 'Kids' or list of DiMeIds to be summed. Kids means the children of this Dimension Member i.e. those below it that have a higher level number and are indented.</p>
#
# <p><b>Mux List</b>: List of mutually exclusive DiMeIds i.e. a DiMe with a Mux List cannot be used if one the DiMes in the Mux List is already in use.</p>
# ";
# }

$ShortChecked = ($ShortB  ? ' checked' : '');
$FullChecked  = (!$ShortB ? ' checked' : '');
#$NotesChecked = ($NotesB  ? ' checked' : '');

Form();
Footer(); # time, top, centred
##################


function DiMeInfo($bits) {
  # Only DiMeB_Default is in use currently
  return $bits == DiMeB_Default ? 'D' : '';
  #$type = '';`
  #for ($b=1; $b<=DiMeB_Zilch; $b*=2) {`
  #  if ($bits & $b)`
  #  switch ($b) {`
  #    case DiMeB_Default:$type  = 'D,'; break;`
  #    case DiMeB_BD:     $type .= 'B,'; break;`
  #    case DiMeB_RO:     $type .= 'RO,'; break;`
  #    case DiMeB_SumKids:`
  #    case DiMeB_SumList:`
  #    case DiMeB_muX:                   break;`
  #    case DiMeB_pYa:    $type .= 'Y,'; break;`
  #    case DiMeB_Zilch:  $type .= 'Z,'; break;`
  #    default:           $type .= 'Unknown type,'; break;`
  #  }`
  #}
  #return substr($type, 0, -1);
}

function Form() {
  global $ShortChecked, $FullChecked; # $NotesChecked;
echo "<div class=mc style=width:430px>
<form method=post>
<input id=i1 type=radio class=radio name=Sel value=1$ShortChecked> <label for=i1>Short Listing with Dimension Members in Shortened List form</label><br>
<input id=i2 type=radio class=radio name=Sel value=2$FullChecked> <label for=i2>Full Listing including All Dimension Members</label><br>
<p class='c mb0'><button class='on m05'>Dimension Members</button></p>
</form>
</div>
";
}

# <input id=i3 type=checkbox class=radio name=Notes value=1$NotesChecked> <label for=i3>Include Notes</label><br>
