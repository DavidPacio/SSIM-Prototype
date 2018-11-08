<?php /* \Pacio\Development\SSIM Proto\Admin\root\XBRL-Taxonomies\ElementsLookup.php

Use the Taxonomy DB to display info about an element or elements

ToDo djh??
----
Read Roles first

Implement the previous start/end procedures via $StartEndTxIdsGA etc

Fix Clean call skip for $input re double quoted searches

History:
2018.10.24 Started based on the SIM version
           Tuple stuff removed

*/
require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';

# # List of Tx Elements which have StartEnd values. See Doc/Tx/UK-IFRS-DPL/StartEndPeriodNotes.txt for its derivation.
# $StartEndTxIdsGA=[788, 1070, 1697, 2235, 2247, 4626, 4633, 4742, 4746, 5783, 6036, 7915, 8524];
$StartEndTxIdsGA=[]; # Temporary to prevent errors until done properly or replaced.

Head("Lookup Elements: $TxName", true);

if (!isset($_POST['Input'])) {
  echo "<h2 class=c>Lookup of $TxName Taxonomy Element(s)</h2>\n";
  $TypeB = true;
  Form('');
  exit;
}

$DataTypeFiltersA = [];
#Clean($_POST['Input'], FT_STR, true, $input);
$input    = Clean($_POST['Input'], FT_STR);
$TypeB    = isset($_POST['Type']);
$ExpandOnlyOwnToTreeB = isset($_POST['ExpandOnlyOwnToTree']);
$FromTreeB= isset($_POST['FromTree']);
$TreeChoice=Clean($_POST['TreeChoice'], FT_INT);
$NameB    = isset($_POST['Name']);
#SpacesB  = isset($_POST['Spaces']);
$SearchAB = isset($_POST['SearchA']);
$DataTypeFiltersA[TETN_Money]    = isset($_POST['Money']);
$DataTypeFiltersA[TETN_String]   = isset($_POST['String']);
$DataTypeFiltersA[TETN_Boolean]  = isset($_POST['Bool']);
$DataTypeFiltersA[TETN_Date]     = isset($_POST['Date']);
$DataTypeFiltersA[TETN_Decimal]  = isset($_POST['Decimal']);
$DataTypeFiltersA[TETN_Percent]  = isset($_POST['Percent']);
$DataTypeFiltersA[TETN_Share]    = isset($_POST['Share']);
$DataTypeFiltersA[TETN_PerShare] = isset($_POST['PerShare']);
#$ShowBrosB = isset($_POST['ShowBros']);
#$ExclBrosB = isset($_POST['ExclBros']);

$FilterByTypeB = 0;
foreach ($DataTypeFiltersA as $i)
  $FilterByTypeB += $i;
if ($FilterByTypeB) $TypeB = true;

$NumAbstract  = 0;
$AbstractElsA = [];
# if ($ShowBrosB || $ExclBrosB) {
#   if ($ExclBrosB) $ShowBrosB = false;
#   # Build ElementsUsedByBrosA as [TxId => 1]
#   # Does not handle different hypercubes and tuples like Concrete Elements
#   $ElementsUsedByBrosA = [];
#   $res = $DB->ResQuery('Select TupId,TxId from BroInfo Where TxId is not null');
#   while ($o = $res->fetch_object()) {
#     $ElementsUsedByBrosA[(int)$o->TxId] = 1;
#     if ($o->TupId)
#       $ElementsUsedByBrosA[$TupIdToTupTxIdA[(int)$o->TupId]] = 1; # tupId -> TxId
#   }
#   $res->free();
# }

$origInput = $input;
if ($input>'') {
  $inputsA = [];
  $sA = explode($c=DQ, $input);
  while (count($sA) > 2) {
    $t = $sA[1];
    $inputsA[] = trim($t);
    $input = str_replace("$c$t$c", '', $input);
    $sA = explode($c, $input);
  }
  $sA = explode(',', trim($input, ','));
  foreach ($sA as $t)
    if ($t = trim($t))
    $inputsA[] = $t;
}else{
  echo "<h2 class=c>Lookup of $TxName Taxonomy Element(s)</h2>\n";
  Form($origInput);
}

foreach ($inputsA as $input)
  Lookup($input);

Form($origInput);
# --------

function Lookup($input) {
  global $DB, $TxName, $TypeB, $SearchAB, $FilterByTypeB, $DataTypeFiltersA, $FromTreeB, $TreeChoice; #, $ShowBrosB, $ExclBrosB;
  $pHdg = '<p class=c>';
  if ($FilterByTypeB) {
    $list = '';
    foreach ($DataTypeFiltersA as $k => $v)
      if ($v) $list  .= ', ' . ElementTypeToStr($k);
    $pHdg .= 'Concrete tree elements filtered by Data Type to include only those of type' . (strlen($list)>10 ? 's ' : ' ') . substr($list, 2) . ' plus the rarely used ones.<br>';
  }
  #if ($ExclBrosB) $pHdg .= 'Concrete tree elements used in Bros are excluded.<br>';

  if ($FromTreeB || $TreeChoice) {
    if ($FromTreeB)
      $pHdg .= "'From' Trees Only i.e. No 'To' trees.";
    if ($TreeChoice)
      $pHdg .= LinkTypeToStr($TreeChoice).' Trees Only.';
    $pHdg .= '<br>';
  }
  $pHdg .= 'Tree element Types are indicated by: [A] = Abstract, [C] = Concrete'
   #. ($ShowBrosB ? ' or * if used in Bros' : '')
    . ',<br>followed by the El Id, and when applicable [H &amp; the Hy Id(s)]'
    . ($TypeB ? ' [Data Type &lt;Ns|Dr|Cr> &amp; Period]' : '') . ' {[&lt;Start Label|End Label|StartEnd>]}.</p>';
  if (is_numeric($input)) {
    # assume E.id
    echo "\n<h2 class=c>Lookup of $TxName Taxonomy Concept Element with Id $input</h2>\n$pHdg\n";
    if ($o = $DB->OptObjQuery("Select * From Elements where Id='$input'"))
      ElementInfo($o);
    else
      NoGo("Id $input not found");
  }else{
    # First search by name. Can have more than one match
    $res = $DB->ResQuery("Select * From Elements where name='$input'");
    if ($res->num_rows) {
      echo "\n<h2 class=c>Lookup of $TxName Taxonomy Concept Element(s) with Name '$input'</h2>\n<p>$pHdg\n";
      while ($o = $res->fetch_object())
        ElementInfo($o);
      $res->free();
    }else{
      # try search
      if ($SearchAB) # Search Abstract as well as Concrete
         $qry = "Select * From Elements Where name like '%$input%' or StdLabel like '%$input%'";
      else # only concrete
         $qry = "Select * From Elements Where abstract is null and (name like '%$input%' or StdLabel like '%$input%')";
      $res = $DB->ResQuery($qry);
      if ($res->num_rows) {
        echo "\n<h2 class=c>Lookup of $TxName Taxonomy " . ($SearchAB ? '' : 'Concrete ') . "Element(s) with Search String '$input'</h2>\n$pHdg\n<p class=c>" .
        $res->num_rows . ' Elements were found:';
        $elsA = [];
        while ($o = $res->fetch_object()) {
          echo " $o->Id";
          $elsA[] = $o;
        }
        $res->free();
        echo "</p>\n";
        foreach ($elsA as $o)
          ElementInfo($o);
      }else
        NoGo("No element found for '$input'.");
    }
  }
}

function ElementInfo($o) {
  global $DB, $TargetId, $FromTreeB, $StartEndTxIdsGA;
  $elId = (int)$o->Id;
  $name = $o->name;
  $substGroupN = (int)$o->TesgN;
  $nsO = $DB->ObjQuery("Select * from Namespaces where Id=$o->NsId");
  if (!$o->TetN) return NoGo("Element $elId with Name $name exists but is not a concept (it has no type) so no info on it is available here");
  # Element
  $abstract = (int)$o->abstract;
  # ElementsInfo is used in Site.css
  echo "<table class='mc ElementsInfo'>\n<tr class='b bg0'><td colspan=2>Properties</td></tr>
<tr class=b><td>Property</td><td>Value</td></tr>
<tr><td>Elements.Id</td><td>$elId</td></tr>
<tr><td>Name</td><td>$o->name</td></tr>
<tr><td>Type</td><td>",($abstract ? 'Abstract' : 'Concrete'), "</td></tr>
<tr><td>Substitution Group</td><td>", SubstGroupToStr($substGroupN), '</td></tr>
';

  $hypercubes = HypercubesFromList($o->Hypercubes);
  echo '<tr><td>Data Type</td><td>', ElementTypeToStr($o->TetN), '</td></tr>';
  if ($o->TetN == TETN_Money)
    echo '<tr><td>Sign</td><td>', SignToStr($o->SignN), '</td></tr>'.NL;
  echo '<tr><td>Period</td><td>',  PeriodTypeToStr($o->PeriodN), in_array($elId, $StartEndTxIdsGA) ? ' with StartEnd Period use available' : '', "</td></tr>
<tr><td>Hypercube(s)<td>$hypercubes</td></tr>\n";

  echo "<tr><td>Namespace</td><td>$nsO->Prefix ($nsO->namespace)</td></tr>
<tr><td>Tag</td><td>$nsO->Prefix:$name</td></tr>
<tr><td>Nillable</td><td>",BoolToStr($o->nillable), '</td></tr>'.NL;

  # Labels and References
  echo "<tr class='b bg0'><td colspan=2>Labels</td></tr>\n<tr class=b><td>Type</td><td>Label</td></tr>\n";
  # Have StdLabel and TerseLabel available in Elements but here run a query in case of other labels
  # Taxonomy Role Id (Roles.Id) for the XBRL roles. NB! These need to be checked after a Taxonomy DB Build
  # ----------------                 Role              UsedOn
  # const TRId_StdLabel          =  1; # label             label
  # const TRId_VerboseLabel      =  2; # verboseLabel      label
  # const TRId_TerseLabel        =  3; # terseLabel        label
  # const TRId_Documentation     =  4; # documentation     label
  # const TRId_NetLabel          =  5; # netLabel          label
  # const TRId_TotalLabel        =  6; # totalLabel        label
  # const TRId_NegLabel          =  7; # negatedLabel      label
  # const TRId_NegTerseLabel     =  8; # negatedTerseLabel label
  # const TRId_NegTotalLabel     =  9; # negatedTotalLabel label
  # const TRId_PeriodStartLabel  = 10; # periodStartLabel  label
  # const TRId_PeriodEndLabel    = 11; # periodEndLabel    label     <=== end of labels
  # const TRId_Reference         = 12; # reference         reference /- References
  # const TRId_DisclosureRef     = 13; # disclosureRef     reference |
  # const TRId_ExampleRef        = 14; # exampleRef        reference |
  # const TRId_CommonPracticeRef = 15; # commonPracticeRef reference |
                     # Select R.Id Rid,R.RoleId,R.Text from Arcs A Join Resources R on R.Id=A.ToId where A.TltN in (4,5) and A.FromId=407 Order by R.RoleId,R.Id
                     # Select R.Id Rid,R.RoleId,R.Text from Arcs A Join Resources R on R.Id=A.ToId where A.ArcroleId in (9,10) and A.FromId=407 Order by R.RoleId,R.Id
  $res = $DB->ResQuery(sprintf('Select R.RoleId,R.Text from Arcs A Join Resources R on R.Id=A.ToId where A.TltN in (%d,%d) and A.FromId=%d Order by R.RoleId,R.Id', TLTN_Label, TLTN_Reference, $elId));
  $firstRefB = true;
  if ($res->num_rows) {
    while ($o = $res->fetch_object()) {
      $role = Role($o->RoleId);
      if ($o->RoleId >= TRId_Reference) {
        # Reference
        if ($firstRefB) {
          echo "<tr class='b bg0'><td colspan=2>References</td></tr>\n<tr class=b><td>Type</td><td>Reference</td></tr>\n";
          $firstRefB = false;
        }
        $ref = '';
        foreach (json_decode($o->Text, true) as $a => $v)
          $ref .= "<br><span>$a</span>$v";
        $ref = substr($ref, 4);
        echo "<tr><td class=mid>$role</td><td class=Ref>$ref</td></tr>\n"; # the Ref class in an ElementsInfo table defines span {display:inline-block;width:145px}
      }else # Label
        echo "<tr><td>$role</td><td>$o->Text</td></tr>\n";
    }
  }else
    echo '<td colspan="2">None</td></tr>';
  $res->free();

  # Trees
  echo "<tr class='b bg0'><td colspan=2>Trees</td></tr>
<tr class=b><td>Type</td><td>Tree</td></tr>
";
  $TargetId = $elId; # Global for use by TreeElement()
  if (!$FromTreeB)
    ToTrees($elId);
  FromTrees($elId);
  echo "</table>\n";
} // End ElementInfo($o)

function ToTrees($toId) {
  global $DB, $TargetId, $ToArcrolesA, $TreeChoice, $StartEndTxIdsGA, $ExpandOnlyOwnToTreeB; # Target = $toId
  $ToArcrolesA = []; # to record arcs used by ToTrees() to exclude them from FromTrees() processing
  # Fetch arcs to $fromId for all arcroles.
  # 07.11.11 Added Distinct re duplicates e.g. for 4062. But note comments below vs Distinct and Group by.
  # Taxonomy Arcrole Id (Arcroles.Id) constants which are in TLTN_ sequence
  # -------------------                                 /- TLTN_* arc (link) type
  #onst TARId_HypercubeDim  =  1; # hypercube-dimension 1  From hypercube To dimension in the hypercube               Source (a hypercube) contains the target (a dimension) among others.
  #onst TARId_DimDomain     =  2; # dimension-domain    1  From dimension To first dimension member of the dimension  Source (a dimension) has only the target (a domain) as its domain.
  #onst TARId_DomainMember  =  3; # domain-member       1  From domain contains To member                             Source (a domain) contains the target (a member).
  #onst TARId_DimAll        =  4; # all                 1  From source requires dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) to appear in the context of the primary item.
  #onst TARId_DimNotAll     =  5; # notAll              1  From source excludes dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) not to appear in the context of the primary item.
  #onst TARId_DimDefault    =  6; # dimension-default   1  From dimension To default dimension member                 Source (a dimension) declares that there is a default member that is the target of the arc (a member).
  #onst TARId_ParentChild   =  7; # parent-child        2  From parent To child
  #onst TARId_SummationItem =  8; # summation-item      3  From element sums To element
  switch ($TreeChoice) { # All, Definition, Presentation, Calculation
    case 0: $qry = "Select ArcroleId,FromId,PRoleId,TargetRoleId from Arcs Where ArcroleId<9 and ToId=$toId Order by ArcroleId,PRoleId,TargetRoleId,ArcOrder"; break; # all trees
    case 1: $qry = "Select ArcroleId,FromId,PRoleId,TargetRoleId from Arcs Where ArcroleId<7 and ToId=$toId Order by ArcroleId,PRoleId,TargetRoleId,ArcOrder"; break; # Definition only
    case 2: $qry = "Select ArcroleId,FromId,PRoleId,TargetRoleId from Arcs Where ArcroleId=7 and ToId=$toId Order by PRoleId,TargetRoleId,ArcOrder"; break;           # Presentation only
    case 3: $qry = "Select ArcroleId,FromId,PRoleId,TargetRoleId from Arcs Where ArcroleId=8 and ToId=$toId Order by PRoleId,TargetRoleId,ArcOrder"; break;           # Calculation only
  }
  $res = $DB->ResQuery($qry);
  if ($res->num_rows) {
    $rn = 0;
    $prevPRoleId = $pArcroleId = 0;
    while ($o = $res->fetch_object()) {
      #echo "T Arc FromId $o->FromId => $toId, ArcroleId $o->ArcroleId, PRoleId $o->PRoleId rn=$rn of $res->num_rows<br>";
      $arcroleId = (int)$o->ArcroleId;
      $ToArcrolesA[$arcroleId] = 1;
      if ($arcroleId != $pArcroleId) {
        OutputAbstractElements();
        echo ($rn ? "</td></tr>\n" : ''), "<tr><td class=top>", Arcrole($pArcroleId = $arcroleId, true), '</td><td>';
        ++$rn;
        $prevPRoleId = $n = 0;
      }
      $id       = (int)$o->FromId;
      $pRoleId  = (int)$o->PRoleId;
      if ($pRoleId != $prevPRoleId) {
        OutputAbstractElements();
        echo ($n? '<br>' : ''), '<b>', Role($pRoleId), '</b><br>';
      }
      $prevPRoleId = $pRoleId;
      $nt = 1; # number of trees
      $trees = [[$id]]; # trees[n][arrays of element Id]
      $lasti = [0];     # last index (number of nodes -1) per tree
      $doneB = [false]; # set when tree is finished
      $anyB  = true;
      while($anyB) {
        $anyB = false;
        for ($ti=0; $ti<$nt; $ti++) {
          if (!$doneB[$ti]) {
            $id = $trees[$ti][$lasti[$ti]];
           # Distinct did not avoid a duplicate in the case of 5121 whereas Group by ToId did. Order and Priority are different tho not in the select list?
           # 25.04.13 But using Group by caused loss of a wanted branch in the 4788 case. Whereas all was as wanted with the Group by removed. And this made no difference to 5121. ??
           #$r2 = $DB->ResQuery("Select Distinct FromId from Arcs Where ArcroleId=$arcroleId and ToId=$id and PRoleId=$pRoleId");# Order by ArcOrder");
           #$r2 = $DB->ResQuery("Select FromId from Arcs Where ArcroleId=$arcroleId and ToId=$id and PRoleId=$pRoleId Group by ToId");# Order by ArcOrder");
            $r2 = $DB->ResQuery("Select FromId from Arcs Where ArcroleId=$arcroleId and ToId=$id and PRoleId=$pRoleId");
            if ($r2->num_rows) {
              $anyB = true;
              $a = $r2->fetch_object(); # for the $ti tree
             #$rn2 = 1; # tmp
             #echo "T2 Arc $a->Id, FromId $a->FromId => ToId $id, ArcroleId $arcroleId, PRoleId $pRoleId, $rn2 of $r2->num_rows, nt=$nt<br>";
              $fromTi = $a->FromId;
              if ($r2->num_rows > 1) {
                while ($a = $r2->fetch_object()) {
                  $trees[] = $trees[$ti];
                  $doneB[] = false;
                  $trees[$nt][] = $a->FromId;
                  $lasti[$nt] = $lasti[$ti]+1;
                  $nt++;
                 #$rn2++; # tmp
                 #$echo "T2 2 Arc $a->Id, FromId $a->FromId => ToId $id, ArcroleId $arcroleId, PRoleId $pRoleId, $rn2 of $r2->num_rows, nt=$nt<br>"; # tmp
                }
              }
              $trees[$ti][] = $fromTi;
              $lasti[$ti]++;
            }else
              $doneB[$ti] = true;
            $r2->free();
          }
        }
      }
      # Now backwards thru the trees arrays
      for ($ti=0; $ti<$nt; $ti++) {
        $inset = $p = 10; # p just to declare it
        if ($ti) $p = $lasti[$ti-1]; # previous
        $startedB = false;
        for ($i = $lasti[$ti]; $i>=0; $i--,$p-- ) {
          $id = $trees[$ti][$i];
          if ($startedB || !$ti || $p<0 || $id != $trees[$ti-1][$p]) {
            TreeElement($id, $inset); # No PrefLabelRoleId as this is a FromId
            $startedB = true;
          }
          $inset += 12;
        }
        # from Group by PrefLabelRoleId
       #echo "from 348 Select ToId,PrefLabelRoleId,TargetRoleId from Arcs Where ArcroleId=$arcroleId and PRoleId=$pRoleId and FromId=$id Order by TargetRoleId,ArcOrder<br>";
        $r2 = $DB->ResQuery("Select ToId,PrefLabelRoleId,TargetRoleId from Arcs Where ArcroleId=$arcroleId and PRoleId=$pRoleId and FromId=$id Order by TargetRoleId,ArcOrder");
        if ($r2->num_rows) {
          $startEndElementsA = [];
          while ($a = $r2->fetch_object()) {
            $id = (int)$a->ToId;
            if ($arcroleId > TLTN_Presentation && isset($startEndElementsA[$id]))
              continue;
            if (in_array($id, $StartEndTxIdsGA))
              $startEndElementsA[$id] = 1;
            TreeElement($id, $inset, $a->PrefLabelRoleId, $a->TargetRoleId); # With PrefLabelRoleId as this is a ToId
            if (!$ExpandOnlyOwnToTreeB || $id === $TargetId)
              # Continue for target element if there are from arcs
              # echo "FromTrees2($id, $arcroleId, $pRoleId, $inset) call at 367<br>";
              FromTrees2($id, $arcroleId, $pRoleId, $inset);
          }
        }else
          die("Die - Target element not found in final To tree list as expected");
        $r2->free();
      }
      $n++;
    }
    OutputAbstractElements();
    echo "</td></tr>\n";
  }
  $res->free();
}

# Fetch arcs from $fromId for all arcroles not already processed via ToTrees()
function FromTrees($fromId) {
  global $DB, $ToArcrolesA, $TreeChoice, $StartEndTxIdsGA;
  $arcrolesA = [];
  # Taxonomy Arcrole Id (Arcroles.Id) constants which are in TLTN_ sequence
  # -------------------                                 /- TLTN_* arc (link) type
  #onst TARId_HypercubeDim  =  1; # hypercube-dimension 1  From hypercube To dimension in the hypercube               Source (a hypercube) contains the target (a dimension) among others.
  #onst TARId_DimDomain     =  2; # dimension-domain    1  From dimension To first dimension member of the dimension  Source (a dimension) has only the target (a domain) as its domain.
  #onst TARId_DomainMember  =  3; # domain-member       1  From domain contains To member                             Source (a domain) contains the target (a member).
  #onst TARId_DimAll        =  4; # all                 1  From source requires dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) to appear in the context of the primary item.
  #onst TARId_DimNotAll     =  5; # notAll              1  From source excludes dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) not to appear in the context of the primary item.
  #onst TARId_DimDefault    =  6; # dimension-default   1  From dimension To default dimension member                 Source (a dimension) declares that there is a default member that is the target of the arc (a member).
  #onst TARId_ParentChild   =  7; # parent-child        2  From parent To child
  #onst TARId_SummationItem =  8; # summation-item      3  From element sums To element
  switch ($TreeChoice) {
    case 0: $i=TARId_HypercubeDim; $end = TARId_SummationItem; break; # All
    case 1: $i=TARId_HypercubeDim; $end = TARId_DimDefault;    break; # Definition
    case 2: $i=TARId_ParentChild;  $end = TARId_ParentChild;   break; # Presentation
    case 3: $i=TARId_SummationItem;$end = TARId_SummationItem; break; # Caculation
  }
  for ( ; $i <= $end; $i++)
    if (!isset($ToArcrolesA[$i]))
      $arcrolesA[] = $i;
  if (!count($arcrolesA)) return;
  $res = $DB->ResQuery("Select ArcroleId,ToId,PRoleId,PrefLabelRoleId,TargetRoleId from Arcs Where ArcroleId In(" .
                         implode(',', $arcrolesA) . ") and FromId=$fromId Order by ArcroleId,PRoleId,TargetRoleId,ArcOrder");
  if ($res->num_rows) {
    $rn = 0;
    $prevPRoleId = $pArcroleId = 0;
    $startEndElementsA = [];
    while ($o = $res->fetch_object()) {
      #echo "F Arc $o->Id, ToId $o->ToId, ArcroleId $o->ArcroleId";
      $arcroleId = (int)$o->ArcroleId;
      if ($arcroleId != $pArcroleId) {
        OutputAbstractElements();
        echo ($rn ? "</td></tr>\n" : ''), "<tr><td class=top>", Arcrole($pArcroleId = $arcroleId, true), '</td><td>';
        ++$rn;
        $prevPRoleId = $n = 0;
        $startEndElementsA = [];
      }
      $id      = (int)$o->ToId;
      $pRoleId = (int)$o->PRoleId;
      if ($pRoleId != $prevPRoleId) {
        OutputAbstractElements();
        echo ($n? '<br>' : ''), '<p><b>', Role($pRoleId), '</b></p>';
        TreeElement($fromId, 10); # inset 10 No PrefLabelRoleId as this is a FromId
      }
      $prevPRoleId = $pRoleId;
      if ($arcroleId > TLTN_Presentation && isset($startEndElementsA[$id]))
        continue;
      if (in_array($id, $StartEndTxIdsGA))
        $startEndElementsA[$id] = 1;
      TreeElement($id, 22, $o->PrefLabelRoleId, $o->TargetRoleId); # With PrefLabelRoleId as this is a ToId
      FromTrees2($id, $arcroleId, $pRoleId, 24);
      $n++;
    }
    OutputAbstractElements();
    echo "</td></tr>\n";
  }
  $res->free();
}

function FromTrees2($fromId, $arcroleId, $pRoleId, $inset) {
  global $DB, $StartEndTxIdsGA;
  $res = $DB->ResQuery("Select ToId,PrefLabelRoleId from Arcs Where ArcroleId=$arcroleId and FromId=$fromId and PRoleId=$pRoleId Order by ArcroleId,TargetRoleId,ArcOrder");
  if ($res->num_rows) {
    $inset += 12;
    $startEndElementsA = [];
    while ($a = $res->fetch_object()) {
      $id = (int)$a->ToId;
      #echo "FT2  Arc $a->Id fromId $fromId ToId $id";
      if ($arcroleId > TLTN_Presentation && isset($startEndElementsA[$id]))
        continue;
      if (in_array($id, $StartEndTxIdsGA))
        $startEndElementsA[$id] = 1;
      TreeElement($id, $inset, $a->PrefLabelRoleId); # With PrefLabelRoleId as this is a ToId
      FromTrees2($id, $arcroleId, $pRoleId, $inset);
    }
  }
  $res->free();
}

# $prefLabelRoleId only applies in the case of ToId arcs intended to show Period Start and Period End where applicable
function TreeElement($id, $inset, $prefLabelRoleId=0, $targetRoleId = 0) {
  global $DB, $TypeB, $NameB, $AbstractElsA, $NumAbstract, $FilterByTypeB, $DataTypeFiltersA, $StartEndTxIdsGA; # , $ShowBrosB, $ExclBrosB, $ElementsUsedByBrosA,
  static $prevInset = 0;
  if ($NameB) # Show Names rather than Standard Labels in Trees
    $qry = "Select TetN,abstract,TesgN,PeriodN,SignN,Hypercubes,name from Elements Where Id=$id";
  else
    $qry = "Select TetN,abstract,TesgN,PeriodN,SignN,Hypercubes,StdLabel name from Elements Where Id=$id";
  if (!$o = $DB->OptObjQuery($qry))
    die("Die - element $id not found in TreeElement()");
  if ($FilterByTypeB && $inset < $prevInset) {
    # When filtering by type, on moving back up discard any abstract elements at a lower level which haven't been output
    #  as a result of a concrete element coming along or a forced output
    #echo "Moving up NumAbstract = $NumAbstract -> ";
    foreach ($AbstractElsA as $i => $aA)
      if ($aA[1] > $inset) {
        unset($AbstractElsA[$i]);
        $NumAbstract--;
      }
    #echo "$NumAbstract<br>";
  }
  $prevInset = $inset;
  if ($o->abstract) {
    # Abstract
    $AbstractElsA[] = [$id, $inset, "[A] $id $o->name", $targetRoleId];
    $NumAbstract++;
    #echo "$NumAbstract<br>";
  }else{
    # Concrete
    if ($FilterByTypeB && isset($DataTypeFiltersA[$tetN = (int)$o->TetN]) && !$DataTypeFiltersA[$tetN]) return;
   #if ($ExclBrosB && in_array($id, $ElementsUsedByBrosA)) return;
   #if ($ExclBrosB && isset($ElementsUsedByBrosA[$id])) return;
    OutputAbstractElements(true); # true = force output
    #if ($ShowBrosB)
    # #$b = (in_array($id, $ElementsUsedByBrosA) ? '* ' : '&nbsp; ');
    #  $b = (isset($ElementsUsedByBrosA[$id]) ? '* ' : '&nbsp; ');
    #else
    #  $b='';
    if ($TypeB) {
      if (($tetN = (int)$o->TetN) == TETN_Money)
        $typeS = ' [' . ElementTypeToStr($tetN) . ($o->SignN ? ($o->SignN==1? ' Dr ' : ' Cr ') : ' Ns ') . PeriodTypeToStr($o->PeriodN) . '] ';
      else
        $typeS = ' [' . ElementTypeToStr($tetN) . ' ' . PeriodTypeToStr($o->PeriodN) . '] ';
    }else
      $typeS = '';
    if ($prefLabelRoleId == TRId_PeriodStartLabel || $prefLabelRoleId == TRId_PeriodEndLabel)
     #$startEnd = ' [' . str_replace(' Label', '', Role($prefLabelRoleId)) . '] '; # To show Period Start and Period End on To elements where set
      $startEnd = $prefLabelRoleId == TRId_PeriodStartLabel ? ' [Start Label] ' : ' [End Label] '; # To show Start Period and End Period on To elements where set via PrefLabelRoleId - presentation trees only
    else
      $startEnd = in_array($id, $StartEndTxIdsGA) ? ' [StartEnd] ' : ''; # To show StartEnd possible for this element
    $hysS = $o->Hypercubes ? (' [H ' . ChrListToCsList($o->Hypercubes) . ']') : '';
   #$txt = "{$b}[C] $id$hysS$typeS$startEnd $o->name";
    $txt = "[C] $id$hysS$typeS$startEnd $o->name";
    OutputTreeElement($id, $inset, $txt, $targetRoleId);
  }
}

function OutputTreeElement($id, $inset, $txt, $targetRoleId) {
  global $TargetId; #, $SpacesB;
  if ($targetRoleId)
    $txt .= ' (' . Role($targetRoleId) . ')';
  if ($id == $TargetId)
    $txt = '<span class="b navy">' . $txt . '</span>';

  #if ($SpacesB) {
  #  $pinset = 10;
  #  while ($pinset <= $inset) {
  #    echo '&nbsp;&nbsp;';
  #    $pinset += 12;
  #  }
  #  echo "$txt<br>\n";
  #}else
    echo "<p style='padding-left:{$inset}px'>$txt</p>\n";
}

function OutputAbstractElements($forceB = false) {
  global $AbstractElsA, $NumAbstract, $FilterByTypeB;
  #echo "OutputAbstractElements() NumAbstract=$NumAbstract, count=",count($AbstractElsA), '<br>';
  if ($NumAbstract) {
    if ($forceB || !$FilterByTypeB)
      foreach ($AbstractElsA as $aA)
        OutputTreeElement($aA[0], $aA[1], $aA[2], $aA[3]);
    $AbstractElsA = [];
    $NumAbstract = 0;
  }
}

function NoGo($msg) {
  echo "\n<p class='c b'>$msg</p>\n";
}

function Form($input) {
  global $TypeB, $FromTreeB, $ExpandOnlyOwnToTreeB, $TreeChoice, $NameB, $SearchAB, $DataTypeFiltersA; # , $SpacesB, $ShowBrosB, $ExclBrosB;
  $typeChecked     = ($TypeB    ? ' checked' : '');
  $expandOwnChecked= ($ExpandOnlyOwnToTreeB? ' checked' : '');
  $fromTreeChecked = ($FromTreeB? ' checked' : '');
  $TreeChoice0Checked = ($TreeChoice==0 ? ' checked' : '');
  $treeChoice1Checked = ($TreeChoice==1 ? ' checked' : '');
  $treeChoice2Checked = ($TreeChoice==2 ? ' checked' : '');
  $treeChoice3Checked = ($TreeChoice==3 ? ' checked' : '');
  $nameChecked     = ($NameB    ? ' checked' : '');
  #spacesChecked   = ($SpacesB  ? ' checked' : '');
  $searchAChecked  = ($SearchAB ? ' checked' : '');
  $moneyChecked    = ($DataTypeFiltersA[TETN_Money]    ? ' checked' : '');
  $stringChecked   = ($DataTypeFiltersA[TETN_String]   ? ' checked' : '');
  $boolChecked     = ($DataTypeFiltersA[TETN_Boolean]  ? ' checked' : '');
  $dateChecked     = ($DataTypeFiltersA[TETN_Date]     ? ' checked' : '');
  $decimalChecked  = ($DataTypeFiltersA[TETN_Decimal]  ? ' checked' : '');
  $percentChecked  = ($DataTypeFiltersA[TETN_Percent]  ? ' checked' : '');
  $shareChecked    = ($DataTypeFiltersA[TETN_Share]    ? ' checked' : '');
  $perShareChecked = ($DataTypeFiltersA[TETN_PerShare] ? ' checked' : '');
  #showBrosChecked = ($ShowBrosB ? ' checked' : '');
  #exclBrosChecked = ($ExclBrosB ? ' checked' : '');

echo <<< FORM
<div class=mc style=width:1000px>
<p class=mb0>Enter a Concept Name, an Elements.Id number, a search string (doubly quoted to include a comma - name and standard label are searched), or a comma separated list of any of these e.g. AccountingProfit or 21 or 407,2414,4501 or Dividends due or 2414,"Deferred tax" :</p>
<form method=post>
<table class=itran>
<tr><td class=r>Name, Elements.Id, Search string, or CS List:</td><td><input type=text name=Input size=75 maxlength=155 value='$input'></td></tr>
<tr><td class=r>Search Abstract Elements as well as Concrete Ones:</td><td><input class=radio type=checkbox name=SearchA value=1$searchAChecked> <span class=s>Applies when an entered value does not give an Id etc match and is used as a search string.</span></td></tr>
<tr><td class=r>Include Element Data Type and Duration in Trees:</td><td><input class=radio type=checkbox name=Type value=1$typeChecked></td></tr>
<tr><td class='r top'>Show only Concrete Elements of Checked Data Type(s) in Trees:</td><td><input class=radio type=checkbox name=Money value=1$moneyChecked> Money<input class=radio type=checkbox name='String' value=1$stringChecked> String<input class=radio type=checkbox name='Bool' value=1$boolChecked> Boolean<input class=radio type=checkbox name='Date' value=1$dateChecked> Date<input class=radio type=checkbox name='Decimal' value=1$decimalChecked> Decimal<input class=radio type=checkbox name='Percent' value=1$percentChecked> Percent<input class=radio type=checkbox name='Share' value=1$shareChecked> Share<input class=radio type=checkbox name='PerShare' value=1$perShareChecked> PerShare<br><span class=s>None checked means all. Rarely used types not listed above always appear if encountered.</span></td></tr>
<tr><td class=r>Expand only own branch not sibling ones in 'To' Trees:</td><td><input class=radio type=checkbox name=ExpandOnlyOwnToTree value=1$expandOwnChecked></td></tr>
<tr><td class=r>Show only 'From' Trees i.e. Omit 'To' Trees in following choice:</td><td><input class=radio type=checkbox name=FromTree value=1$fromTreeChecked></td></tr>
<tr><td class=r>All Trees:</td><td><input class=radio type=radio name=TreeChoice value=0$TreeChoice0Checked></td></tr>
<tr><td class=r>Only Definition Trees:</td><td><input class=radio type=radio name=TreeChoice value=1$treeChoice1Checked></td></tr>
<tr><td class=r>Only Presentation Trees:</td><td><input class=radio type=radio name=TreeChoice value=2$treeChoice2Checked></td></tr>
<tr><td class=r>Only CalculationTrees:</td><td><input class=radio type=radio name=TreeChoice value=3$treeChoice3Checked></td></tr>
<tr><td class=r>Show Names rather than Standard Labels in Trees:</td><td><input class=radio type=checkbox name=Name value=1$nameChecked></td></tr>
</table>
<p class='c mb0'><button class='c on m10'>Lookup</button></p>
</form>
</div>
FORM;
Footer();
exit;
}

# <tr><td class=r>Use Spaces rather than CSS for indenting Trees:</td><td><input class=radio type=checkbox name=Spaces value=1$spacesChecked></td></tr>
# <tr><td class=r>Mark concrete elements used in Bros with an * in Trees:</td><td><input class=radio type=checkbox name=ShowBros value=1$showBrosChecked></td></tr>
# <tr><td class=r>Exclude concrete elements used in Bros from Trees:</td><td><input class=radio type=checkbox name=ExclBros value=1$exclBrosChecked></td></tr>
