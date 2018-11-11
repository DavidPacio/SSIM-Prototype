<?php /* Admin\root\XBRL-Taxonomies\BuildDimensionMembers.php

Split off from BuildHypercubesDimensions.php to try building the DimensionMembers table a different way since
for IFRS:

There are 2897 TARId_DomainMember arcs but BuildHypercubesDimensions.php creates only 1452 DiMes.

This check processes the arcs as they come, not via trees, but the result is the same: 1452 DiMes.

For coding ease this module assumed 291 dimension, one per Dim El and role pair.
Does not actually output tables.

History:
2018.11.03 Written

*/

require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';

Head("Build Dimension Members for $TxName");

#$tablesA = [
#  'DimensionMembers2',
#];

echo "<h2 class=c>Dimension Members for $TxName</h2>
<b>Truncating DB tables</b><br>
";
#foreach ($tablesA as $table) {
#  echo $table, '<br>';
#  $DB->StQuery("Truncate Table `$table`");
#}
$DB->autocommit(false);

# ################
# ## Dimensions ## Table built by BuildHypercubesDimensions.php
# ################
# CREATE TABLE Dimensions (
#   Id      smallint unsigned not null auto_increment,
#   ElId    smallint unsigned not null, # Elements.Id of the dimension
#   RoleIds tinytext          not null, # Roles.Ids of the dimension's parent role(s) from Arcs.PRoleId. In IFRS anyway there can be multiple roles per dimension
#   Primary Key (Id)
# ) CHARSET=utf8;
#
# ######################
# ## DimensionMembers ## Table built by BuildHypercubesDimensions.php
# ######################
# CREATE TABLE DimensionMembers (
#   Id      smallint unsigned not null auto_increment, # Used as DiMeId
#   DimId   smallint unsigned not null, # Dimensions.Id of the dimension
#   ElId    smallint unsigned not null, # Elements.Id of the dimension member
#   RoleId  smallint unsigned not null, # Roles.Id of the dimension member - re possible multiple roles per dimension
#   Bits    tinyint  unsigned not null, # Dimension Member bits: DMB_Default ... defined ConstantsRgUK-GAAP
#   Level   tinyint  unsigned not null, # Level of the DiMe from 0 upwards re Dimension Map and summing
#   Primary Key (Id),
#           Key (DimId) # not unique
# ) CHARSET=utf8;

echo "<br><b>Building Dimension Members Table</b><br>\n";
$NumDiMes = $dimId = 0;
$DimsA       = []; # [dimId => [dimElId, roleId]]
$DimsByRoleA = []; # [roleId => [X => dimId]]
$DiMesA      = []; # [dimId => [level => [X => [elId, n]]]
$res = $DB->ResQuery(sprintf('Select A.*,E.name From Arcs A Join Elements E on E.Id=A.FromId Where A.ArcroleId=%d Order by A.FromId,A.PRoleId', TARId_DimDomain));
# Gives 291 for IFRS rather than 131, the number of TESGN_Dimension elements and also the number of TARId_DimDefault arcs, because of multiple roles per dimension
# Treat each as a dimension + 291 dimensions
while ($o = $res->fetch_object()) {
  $fromId  = (int)$o->FromId;  # Dimension el Id
  $toId    = (int)$o->ToId;    # First Dimension Member el Id
  $pRoleId = (int)$o->PRoleId; # the role
  # if ($DB->InsertQuery("Insert Dimensions Set ElId=$currentDimElId,RoleIds='$roleIdsS'") != $dimId) DieNow("Dimension insert Id not $dimId as expected");
  $dimId++;
  echo "Dim $dimId El $fromId $o->name, Role $pRoleId, First DiMe $toId<br>\n";
  $DimsA[$dimId] = [$fromId, $pRoleId];
  $DimsByRoleA[$pRoleId][] = $dimId;
  RecordDimensionMember($dimId, $toId, 0); # level 0
}
$res->free();
$numDims = $dimId;

# Now read all the TARId_DomainMember arcs and find a home for them
#res = $DB->ResQuery(sprintf('Select FromId,ToId,PRoleId from Arcs where ArcroleId=%d Order by FromId,ToId,PRoleId', TARId_DomainMember));
$res = $DB->ResQuery(sprintf('Select FromId,ToId,PRoleId from Arcs where ArcroleId=%d', TARId_DomainMember));
/* Give 2897 results starting....
Id   TltN  FromId ToId PRoleId ArcroleId ArcOrder
 9956   1   21    22   51      3         10000000
10229   1   21    22   53      3         10000000
12256   1   21    22   82      3         10000000
12324   1   21    22   83      3         10000000
12675   1   21    22   86      3         10000000
12882   1   21    22   87      3         10000000
 9974   1   21    27   51      3         20000000
10234   1   21    27   53      3         20000000
12271   1   21    27   82      3         20000000
12338   1   21    27   83      3         20000000
12686   1   21    27   86      3         20000000
12894   1   21    27   87      3         20000000
17981   1   33    32   147     3         50000000
*/
echo "<br>$res->num_rows TARId_DomainMember arcs being processed<br>\n";
$noMatches = 0;
$arcsA = [];
while ($o = $res->fetch_object())
  $arcsA[] = [(int)$o->FromId, (int)$o->ToId, (int)$o->PRoleId];
$prevNumDiMes = 0;
$loop = 0;
while ($NumDiMes > $prevNumDiMes) {
  $loop++;
  $prevNumDiMes = $NumDiMes;
  foreach ($arcsA as $arcX => $arcA) {
    $fromId = $arcA[0]; # (int)$o->FromId;  # Domain el Id
    $toId   = $arcA[1]; #(int)$o->ToId;    # Member el Id
    $roleId = $arcA[2]; #(int)$o->PRoleId; # role
    # expect fromId to be in one of the dims
    if (isset($DimsByRoleA[$roleId])) {
      $matchB=false;
      foreach ($DimsByRoleA[$roleId] as $dimId) { # [roleId => [X => dimId]]
        # through the dims with the role of the arc
        foreach ($DiMesA[$dimId] as $level => $diMesA) { # [dimId => [level => [X => [elId, n]]]
          # through DiMes of the dim by level
          foreach ($diMesA as $jX => $diMeA) {
            # through the DiMes of the level
            if ($diMeA[0] == $fromId) {
              # domain el match
              if (!$jX)
                # no level change as this is from the 1st domain at this level
                RecordDimensionMember($dimId, $toId, $level);
              else
                # the from isn't the first at this level so start a new level
                RecordDimensionMember($dimId, $toId, $level+1);
              $matchB = true;
              unset($arcsA[$arcX]);
              break 3;
            }
          }
        }
      }
      #if (!$matchB) {
      #  echo "No match for arc fromId $fromId toId $toId role $roleId<br>";
      #  $noMatches++;
      #}
    }else{
      echo "No match for arc fromId $fromId toId $toId role $roleId as there is no dim with that role<br>";
      # $noMatches++;
    }
  }
  echo "$NumDiMes dimension members added by loop $loop with Arcs count :" . count($arcsA) . "<br>"; # with $noMatches arcs not matched<br>\n";
}
$res->free();

echo "<br>$numDims dimensions and $NumDiMes dimension members added in $loop loops<br>"; # with $noMatches arcs not matched<br>\n";

# IFRS result same as
# 1452 dimension members added by loop 3 with Arcs count :1736
# 1452 dimension members added by loop 4 with Arcs count :1736
#
# 291 dimensions and 1452 dimension members added in 4 loops
#
# Done in 38 msecs

# DumpExport('$DimsA', $DimsA);
# echo "<br><br>";
# DumpExport('$DimsByRoleA', $DimsByRoleA);
# echo "<br><br>";
# DumpExport('$DiMesA', $DiMesA);

$DB->commit();

Footer();
#########
#########

function RecordDimensionMember($dimId, $elId, $level) {
  global $NumDiMes, $DiMesA; # [dimId => [level => [X => [elId, n]]]
  $NumDiMes++;
  $DiMesA[$dimId][$level][] = [$elId, $NumDiMes];
  echo "DiMe Dim $dimId, level $level El $elId, n $NumDiMes<br>";
}

# InsertDimensionMembers($dimId)
# ----------------------
# Called from the TARId_DimDomain Arcs loop after DiMes have been built up in $DiMesA
function InsertDimensionMembers($dimId) {
  global $DB, $DiMesA;
  $insertedElsAA = [[]]; # [roleId => [ElId => 1]]
  foreach ($DiMesA as $i => $diMeA) { # [level, name, elId, roleId]
    extract($diMeA); # -> $level, $name, $elId, $roleId
    if (isset($insertedElsAA[$roleId][$elId]))
      echo "***$name $elId already inserted for Dim $dimId role $roleId<br>";
    else{
     $bits = DiMeB_Normal; # 0
     # See old B code for where various levels were fudged for the UK taxonomies
     $diMeId = $DB->InsertQuery("Insert DimensionMembers2 Set DimId=$dimId,ElId=$elId,Bits=$bits,Level=$level,RoleId=$roleId");
     for ($i=0; $i <= $level; $i++)
       echo '&nbsp;&nbsp;';
    #echo "$diMeId $level $name $elId<br>";
     echo "DiMeId $diMeId level $level role $roleId El $elId $name<br>";
     $insertedElsAA[$roleId][$elId] = 1;
    }
  }
}

# DieNow()
# ======
# Crude error exit - die a sudden death but commit first re viewing progress
function DieNow($msg) {
  global $DB;
  $DB->commit();
  die("Die - $msg");
}

