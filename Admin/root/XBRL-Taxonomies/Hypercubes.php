<?php /* \Pacio\Development\SSIM Proto\Admin\root\XBRL-Taxonomies\Hypercubes.php

Lists Hypercubes plus Dimensions and optionally the Dimension Elements

History:
2018.10.23 Started based on the SIM version

To Do
-----
Fix use on E.name rather than StLabel

Fix constants...

*/
require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';
#equire "../../inc/tx/$TxName/ConstantsTx.inc"; # taxonomy specific stuff

Head("Hypercubes List $TxName", true);

if (!isset($_POST['Sel'])) {
  echo "<h2 class=c>$TxName Hypercubes Listing</h2>\n";
  $MediumB = true; # Default to Hypercubes and Dimensions without Dimension Members
  Form();
  Footer(false, false); # no time, not top btn
  exit;
}

$ShortB = $GraphicalB = $MediumB = $FullB = $ShortenedDiMesListsB = false;

$sel = isset($_POST['Sel']) ? Clean($_POST['Sel'], FT_INT) : 3; #default to Short first time in

switch ($sel) {
  case 1: # Short Version with Dims as just Ids, plus Subset Info
    $ShortB = true;
    $titleExtra = ' Short Listing with Dims as just Ids, plus Subset Info';
    break;
  case 2: # 'Graphical' View of the Dims
    $GraphicalB = true;
    $titleExtra = " Listing with a 'Graphical' View of the Dims";
    $maxDimId = $DB->OneQuery('Select count(*) from Dimensions');
    break;
  case 3: # Hypercubes and Dimensions without Dimension Members
    $MediumB = true;
    $titleExtra = ' without Dimension Members';
    break;
  case 4: # Full Listing including All Dimension Members
  case 5: # As above with Dimension Members in Shortened List form
    $FullB = true;
    if ($sel === 5) $ShortenedDiMesListsB = true;
    $titleExtra = ' Including Dimension Members' . ($ShortenedDiMesListsB ? ' (Shortened Lists)' : '');
}
echo "<h2 class=c>$TxName Hypercubes and Dimensions$titleExtra</h2>
<table class=mc>
";
# CREATE TABLE Hypercubes (
#   Id         smallint unsigned not null auto_increment,
#   ElId       smallint unsigned not null, # Elements.Id of the hypercube  toId of hypercube arc where FromId is the Id of the Hypercube element
#   RoleIds    tinytext          not null, # CSV list of PRoleId of the hypercube arcs. Can have multiple roles for the same hypercube dimension
#   Dimensions tinytext          not null, # CSV list of dimensions for this hypercube as Dimensions.Id
#   Primary Key (Id)                       #  Max number of dimensions per hypercube is 9 djh??. Only one, the empty hypercube (our ID=djh??) has none.
# ) CHARSET=utf8;
$res = $DB->ResQuery('Select H.*,E.name,E.StdLabel From Hypercubes H Join Elements E on E.Id=H.ElId');
$tot = 0;
$n = 50; // just for headings output purposes
while ($o = $res->fetch_object()) {
  if ($n >= 50) {
    $n = 0;
    if ($ShortB) # Short Version with Dims as just Ids, plus Subset Info
      echo "<tr class='b bg0'><td class=c>Hypercube<br>Id</td><td class=c>Dimension Ids</td><td class=c>Hypercube is Subset of Hypercubes:<br>(Not working currently)</td><td class=c>Hypercube has Hypercube Subsets:<br>(Not working currently)</td></tr>\n";
    else if ($GraphicalB) {
      $hdg = "<tr class='b bg0'><td class=c>Hypercubes</td><td colspan=$maxDimId class=c>Dimension Ids</td></tr>\n<tr class='b bg0'><td class=c>Id</td>";
      for ($i=1;$i<=$maxDimId; ++$i)
        $hdg .= "<td>$i</td>";
      echo $hdg."</tr>\n";
    }else if ($MediumB) # Hypercubes and Dimensions without Dimension Members
      echo "<tr class='b bg0'><td colspan=3 class=c>Hypercubes</td><td colspan=4 class=c>Dimensions</td></tr>\n",
           "<tr class='b bg0'><td class=c>Id</td><td>TxId</td><td>Tx Name / Tx Standard Label / Role</td><td>Id</td><td>Tx Id</td><td>Label</td><td>Role(s)<br>The *'ed role is the role matching the hypercube's role.<br>Other&nbsp;roles&nbsp;if&nbsp;any&nbsp;apply&nbsp;to&nbsp;the&nbsp;dimension&nbsp;but&nbsp;not&nbsp;this&nbsp;hypercube.</td></tr>\n";
    else # Full with either full or shortened DiMes lists
      echo "<tr class='b bg0'><td colspan=2 class=c>Hypercubes</td><td colspan=2 class=c>Dimensions</td></tr>\n",
           "<tr class='b bg0'><td class=c>Id</td><td>Tx Id Name / Label / Role</td><td>Id</td><td> Tx Id Name / Label / Role / Dimension Members as DiMeId TxId Label</td></tr>\n";
  }
  $hyId   = (int)$o->Id;
  $dimsA  = explode(COM, $o->Dimensions);
  $nDims  = count($dimsA);
  $txName = Fold($o->name, 80);
 #$stdLabel = $o->StdLabel;
  $stdLabel = FoldOnSpace($o->StdLabel, 80);
  $hyRoleId = (int)$o->RoleId;
  $hyRoleS  = Role($o->RoleId, true);
  if ($nDims) {
    if ($ShortB || $GraphicalB) {
      echo "<tr><td class=c>$hyId</td>";
    }else{
      echo "<tr><td rowspan=$nDims class='c top'>$hyId</td><td rowspan=$nDims ";
      if ($MediumB)
        echo "class='r top'>$o->ElId</td><td rowspan=$nDims class=top>$txName<br>$stdLabel<br>$hyRoleS</td>";
      else # Full
        echo "class=top>$o->ElId $txName<br>$stdLabel<br>$hyRoleS</td>";
    }
    // Dimensions
    if ($GraphicalB) {
      $ds = '';
      for ($i=0,$d=1; $i<$nDims; $i++) {
        $dimId = $dimsA[$i];
        while ($d < $dimId) {
          $ds .= '<td></td>';
          ++$d;
        }
        $ds .= "<td>#</td>";
        ++$d;
      }
      for ( ;$d<=$maxDimId; ++$d)
        $ds .= '<td></td>';
      echo $ds."</tr>\n";
      $n++;
    }else{
      # Not Graphical
      if ($ShortB)
        echo '<td>';
      foreach ($dimsA as $i => $dimId) {
        if ($ShortB)
          echo ($i ? ', ' : '') . $dimId;
        else{ # Medium and Full
          $d = $DB->ObjQuery("Select D.*,E.name,E.StdLabel From Dimensions D Join Elements E on E.Id=D.ElId Where D.Id=$dimId");
         #$stdLabel = FoldOnSpace($d->StdLabel, 100);
          $stdLabel = $d->StdLabel;
          if ($MediumB) {
            foreach(explode(COM, $d->RoleIds) as $j => $roleId) {
              $role = Role($roleId, true);
              if ($roleId == $hyRoleId)
                $role .= ' *';
              #else
              #  $role = '('.$role.')';
              if (!$j)
                $rolesS = $role;
              else
                $rolesS .= BR.$role;
              $n++;
            }
            echo ($i ? '<tr>' : '') . "<td class=r>$dimId</td><td class=r>$d->ElId</td><td>$stdLabel</td><td>$rolesS";
          }else{ # Full
            $txName   = "$d->ElId ".Fold($d->name, 100);
            echo ($i ? '<tr>' : '') . "<td class='r top'>$dimId</td><td class=top>$txName<br>$stdLabel<br>$hyRoleS";
            $firstB = true;
            $r3 = $DB->ResQuery("Select M.*,E.StdLabel From DimensionMembers M Join Elements E on E.Id=ElId Where M.DimId=$dimId and M.RoleId=$hyRoleId");
            $numEles = $r3->num_rows;
            $ne = 0;
            while ($m = $r3->fetch_object()) {
              $bits = (int)$m->Bits;
              if ($firstB && !($bits & DiMeB_Default)) echo '<br>&nbsp;&nbsp;&nbsp;No default';
              if (!$ShortenedDiMesListsB || $ne < 4 || $numEles - $ne < 4) {
                echo "<br>&nbsp;&nbsp;&nbsp;$m->Id $m->ElId $m->StdLabel";
              }else if ($ne == 4)
                echo '<br>&nbsp;&nbsp;&nbsp;....';
              $firstB = false;
              $ne++;
              $n++;
              $tot++;
            }
            $r3->free();
          }
          echo "</td></tr>\n";
        }
      } # end of dimensions loop
      if ($ShortB) {
        $subOf = $hasSubs = '';
        #for ($i=1; $i<=HyId_Max; ++$i) If this code gets reinstated use a count on the Hypercubes table rather than a constant
        #  if ($i != $hyId) {
        #    if (IsHypercubeSubset($hyId, $i)) $subOf   .= ", $i"; # is hyId a subset of i?
        #    if (IsHypercubeSubset($i, $hyId)) $hasSubs .= ", $i"; # is i a subset of hyId?
        #  }
        #$subOf = substr($subOf, 2);
        #$hasSubs = substr($hasSubs, 2);
        echo "</td><td style='width:216px'>$subOf</td><td style='width:216px'>$hasSubs</td></tr>\n";
        $n++;
      }
    } # end of not graphical
  }else{ # empty hypercube
    if ($ShortB || $GraphicalB)
      echo "<tr><td class=c>$hyId</td><td>$role</td><td colspan=50></td></tr>\n";
    else{
      echo "<tr><td class='c top'>$hyId</td>";
      if ($MediumB)
        echo "<td>$o->ElId</td><td>$txName</td><td>None</td><td colspan=4></td></tr>\n";
      else
        echo "<td>$o->ElId $txName<br>$stdLabel<br>$role</td><td colspan=2></td></tr>\n";
    }
  }
}
$res->free();
echo "</table>\n";
if ($FullB)
  echo "<p class=c>Total number of Hypercubes -> Dimensions -> Members = $tot</p>\n";
else
echo "<br>\n";

Form();
Footer(); # time, top, centred
##################

function Form() {
  global $ShortB, $GraphicalB, $MediumB, $FullB, $ShortenedDiMesListsB;
echo "<div class=mc style=width:450px>
<form method=post>
<input id=i1 type=radio class=radio name=Sel value=1";
if ($ShortB) echo " checked";
echo "> <label for=i1>Short Version with Dims as just Ids, plus Subset Info</label><br>
<input id=i2 type=radio class=radio name=Sel value=2";
if ($GraphicalB) echo " checked";
echo "> <label for=i2>'Graphical' View of the Dims</label><br>
<input id=i3 type=radio class=radio name=Sel value=3";
if ($MediumB) echo " checked";
echo "> <label for=i3>Hypercubes and Dimensions without Dimension Members</label><br>
<input id=i4 type=radio class=radio name=Sel value=4";
if ($FullB) echo " checked";
echo "> <label for=i4>Full Listing including All Dimension Members</label><br>
<input id=i5 type=radio class=radio name=Sel value=5";
if ($ShortenedDiMesListsB) echo " checked";
echo "> <label for=i5>As above with Dimension Members in Shortened List form</label><br>
<p class=c><button class='on m05'>List Hypercubes</button></p>
</form>
</div>
";
}
