<?php /* \Admin\root\XBRL-Taxonomies\BuildHypercubesDimensions.php

Split off from BuildTxDB.php to allow this to be done without having to run the slow DB build again.

History:
2018.10.21 Started

ToDo djh??
====

*/

require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';

Head("Build Hypercubes and Dimensions for $TxName");

$tablesA = [
  'Hypercubes',
  'Dimensions',
  'DimensionMembers',
];

echo "<h2 class=c>Hypercubes and Dimensions for $TxName</h2>
<b>Truncating DB tables</b><br>
";
foreach ($tablesA as $table) {
  echo $table, '<br>';
  $DB->StQuery("Truncate Table `$table`");
}
$DB->autocommit(false);

# Reset the columns in other tables which this module updates
$DB->StQuery('Update Elements set Hypercubes = NULL');

/////////////////////////
// Post Main Build Ops //
/////////////////////////

# The UK taxonomy builds in 2013 included various taxonomy fixups. See the 2013 code for them.

# Build Dimension Tables
# ======================
################
## Dimensions ##
################
# CREATE TABLE Dimensions (
#   Id      smallint unsigned not null auto_increment,
#   ElId    smallint unsigned not null, # Elements.Id of the dimension
#   RoleIds tinytext          not null, # Roles.Ids of the dimension's parent role(s) from Arcs.PRoleId. In IFRS anyway there can be multiple roles per dimension
#   Primary Key (Id)
# ) CHARSET=utf8;
echo "<br><b>Building Dimension Tables</b><br>\n";
$currentDimElId = $currentPRoleId = $n = $dimId = 0;
# B code did both TARId_DimDefault and TARId_DimDomain in this loop but in IFRS TARId_DimDefault had a different role - role 17
# Here use just TARId_DimDomain (From dimension To first dimension member of the dimension) and then to come back later to do the defaults with role ignored
#                             Select A.* From Arcs A Join Elements E on E.Id=A.FromId Where E.TesgN=2 and A.ArcroleId=8 Order by A.FromId,A.PRoleId /- same ie the TesgN=2 is redundant as all TARId_DimDomain ArcroleId
#                             Select A.* From Arcs A Join Elements E on E.Id=A.FromId Where A.ArcroleId=8 Order by A.FromId,A.PRoleId               |  arcs are TesgN TESGN_Dimension 2 ones
$res = $DB->ResQuery(sprintf('Select A.* From Arcs A Join Elements E on E.Id=A.FromId Where A.ArcroleId=%d Order by A.FromId,A.PRoleId', TARId_DimDomain));
# Gives 291 for IFRS rather than 131, the number of TESGN_Dimension elements and also the number of TARId_DimDefault arcs, because of multiple roles per dimension
while ($o = $res->fetch_object()) {
  $fromId  = (int)$o->FromId;
  $toId    = (int)$o->ToId;
  $pRoleId = (int)$o->PRoleId;
  # echo "* Dim loop fromId $fromId, toId $toId, pRoleId $pRoleId, currentDimElId $currentDimElId, currentPRoleId $currentPRoleId<br>";
  if ($fromId != $currentDimElId) {
    # echo "* Dim loop fromId != currentDimElId<br>";
    if ($currentDimElId) {
      if ($DB->InsertQuery("Insert Dimensions Set ElId=$currentDimElId,RoleIds='$roleIdsS'") != $dimId) DieNow("Dimension insert Id not $dimId as expected");
      echo "Dim $dimId El $currentDimElId ", ElName($currentDimElId), ", Roles: $roleIdsS<br>\n";
      InsertDimensionMembers($dimId);
    }
    $currentDimElId = $fromId;
    $currentPRoleId = $pRoleId;
    $roleIdsS = "$pRoleId";
    $DiMeIdsA = [];
    $dimId++;
    $n++;
  }
  if ($pRoleId != $currentPRoleId) {
    # echo "* Dim loop pRoleId != currentPRoleId<br>";
    $roleIdsS .= ",$pRoleId";
    $currentPRoleId = $pRoleId;
  }
  $name = ElName($toId);
  # From dimension To first dimension member of the dimension | Source (a dimension) has only the target (a domain) as its domain.
  RecordDimensionMember(0, $name, $toId, $currentPRoleId); # level 0
  # Then call FromTrees2 based on the ElementsLookup function of the same name to find the other members of the dimensions, and their levels
  FromTrees2($toId, $pRoleId, -1); # -1 for level which is incremented to 0 in FromTrees2()
}
if ($DB->InsertQuery("Insert Dimensions Set ElId=$currentDimElId,RoleIds='$roleIdsS'") != $dimId) DieNow("Dimension insert Id not $dimId as expected");
echo "Dim $dimId El $$currentDimElId ", ElName($currentDimElId), ", Roles: $roleIdsS<br>\n";
InsertDimensionMembers($dimId);
$res->free();

# Set the default Dimension Members
# djh?? add check on these updates working
# TARId_DimDefault  From dimension To default dimension member FromId: Dimension el Id  ToId: Dimension member el Id
$updateQryS = sprintf('Update DimensionMembers M Join Dimensions D on M.DimId = D.Id Set Bits=%d Where D.ElId=%%d and M.ElId=%%d', DiMeB_Default); # can just set Bits for now as DiMeB_Default is the only bit currently in use
$res = $DB->ResQuery(sprintf('Select FromId,ToId From Arcs Where ArcroleId=%d', TARId_DimDefault));
while ($o = $res->fetch_object()) {
  # echo sprintf($updateQryS, (int)$o->FromId, (int)$o->ToId).BRNL;
  # Update DimensionMembers Set Bits=1 Where DimId=866 and ElId=4526
  $DB->StQuery(sprintf($updateQryS, (int)$o->FromId, (int)$o->ToId));
}
echo "<br>$res->num_rows default dimension members set<br>";


# Braiins Dimensions
/* 21.04.13 Removed
$dummyElId = TxElId_NotApplicable; # 5339 Used in inserts below just to enable queries with joins to give all dimensions/dimension members. The values are not ever used.
# Ageing
$dimId = $DB->InsertQuery("Insert Dimensions Set ElId=$dummyElId,RoleId=78"); # 122 - Dimension - Ageing
echo "Dim $dimId Braiins Ageing Dimension (122 - Dimension - Ageing)<br>";
$DB->StQuery("Insert DimensionMembers Set DimId=$dimId,ElId=$dummyElId,Level=0"); # 0 Ageing.All
$DB->StQuery("Insert DimensionMembers Set DimId=$dimId,ElId=$dummyElId,Level=1"); # 1   Ageing.WithinOneYear
$DB->StQuery("Insert DimensionMembers Set DimId=$dimId,ElId=$dummyElId,Level=1"); # 1   Ageing.AfterOneYear
$DB->StQuery("Insert DimensionMembers Set DimId=$dimId,ElId=$dummyElId,Level=2"); # 2     Ageing.BetweenOneFiveYears
$DB->StQuery("Insert DimensionMembers Set DimId=$dimId,ElId=$dummyElId,Level=3"); # 3       Ageing.BetweenOneTwoYears
$DB->StQuery("Insert DimensionMembers Set DimId=$dimId,ElId=$dummyElId,Level=3"); # 3       Ageing.BetweenTwoFiveYears
$DB->StQuery("Insert DimensionMembers Set DimId=$dimId,ElId=$dummyElId,Level=2"); # 2     Ageing.MoreThanFiveYears
$n+=1;

Braiins Dimensions
Dim Ageing
Ageing.All
  Ageing.WithinOneYear
  Ageing.AfterOneYear
    Ageing.BetweenOneFiveYears
      Ageing.BetweenOneTwoYears
      Ageing.BetweenTwoFiveYears
    Ageing.MoreThanFiveYears

*/

/*
Typed Dimensions and typedDomainRef as used by US GAAP
===================================
   Id NsId TetN TesgN name                                                  StdLabel                                        TerseLabel PeriodN SignN abstract  nillable  Hypercubes  typedDomainRef                                                                              FileId
  143   19  6    2    NameChangeEventDateAxis                               Name Change Event Date [Axis]                         NULL  1      NULL  1         1         NULL        #dei_eventDateTime                                                                               9
15138   24  6    2    RevenueRemainingPerformanceObligationExpectedTimin... Revenue, Remaining Performance Obligation, Expecte... NULL  1      NULL  1         1         NULL        #us-gaap_RevenueRemainingPerformanceObligationExpectedTimingOfSatisfactionStartDateAxis.domain  16
                      RevenueRemainingPerformanceObligationExpectedTimingOfSatisfactionStartDateAxis
                      Revenue, Remaining Performance Obligation, Expected Timing of Satisfaction, Start Date [Axis]
Doc:
  143 For a sequence of name change event related facts, use this typed dimension to distinguish them.  The axis members are restricted to be a valid for xml schema 'date' or 'datetime' data type.
15138 Year in which remaining performance obligation is expected to be recognized, in CCYY format.
Start date of time band for expected timing of satisfaction of remaining performance obligation, in CCYY-MM-DD format.

The XBRL spec
http://www.xbrl.org/specification/dimensions/rec-2012-01-25/dimensions-rec-2006-09-18+corrected-errata-2012-01-25-clean.html#sec-typed-dimensions

From \Pacio\Data\XBRL-Taxonomies\US-GAAP-2018\2018_USGAAP_Technical_Guide_Final.pdf
2.4 Typed Dimensions
A typed dimension was included in the 2017 Taxonomy Update in the group “606000 - Disclosure - Revenue from Contract with Customer.” The Taxonomy uses this typed dimension to indicate the start date for the period when the remaining performance obligation will be satisfied. It is restricted to a dateItemType, meaning the members must appear in CCYY-MM-DD format. The Taxonomy Implementation Guide for Revenue from Contracts with Customers illustrates usage of this structure. The Taxonomy is likely to utilize more typed dimensions in future Taxonomy releases.
The typed dimension is identified in the Taxonomy by the presence of the xbrldt:typedDomainRef attribute on the particular dimension.

in Schema 9 http://xbrl.sec.gov/dei/2018/dei-2018-01-31.xsd, 296 nodes read -> Total nodes = 1,181
<xs:element name="NameChangeEventDateAxis" id="dei_NameChangeEventDateAxis" type="xbrli:stringItemType" xbrli:periodType="duration" abstract="true" nillable="true" substitutionGroup="xbrldt:dimensionItem"
  xbrldt:typedDomainRef="#dei_eventDateTime"/>
<xs:element name="eventDateTime" id="dei_eventDateTime" type="xbrli:dateUnion" nillable="true"/>

In Schema 16 http://xbrl.fasb.org/us-gaap/2018/elts/us-gaap-2018-01-31.xsd, 17044 nodes read -> Total nodes = 20,506
<xs:element abstract="true" id="us-gaap_RevenueRemainingPerformanceObligationExpectedTimingOfSatisfactionStartDateAxis" name="RevenueRemainingPerformanceObligationExpectedTimingOfSatisfactionStartDateAxis" nillable="true"
 substitutionGroup="xbrldt:dimensionItem" type="xbrli:stringItemType" xbrldt:typedDomainRef="#us-gaap_RevenueRemainingPerformanceObligationExpectedTimingOfSatisfactionStartDateAxis.domain" xbrli:periodType="duration"/>
<xs:element id="us-gaap_RevenueRemainingPerformanceObligationExpectedTimingOfSatisfactionStartDateAxis.domain" name="RevenueRemainingPerformanceObligationExpectedTimingOfSatisfactionStartDateAxis.domain" nillable="true" type="xs:date"/>

2018.11.13 Notes only. Nothing is yet being done with the above info.

*/
echo "<br>$n dimensions and ".$DB->OneQuery('select count(*) from DimensionMembers'). " dimension members added<br>\n";

# Build Hypercubes Table
# ======================
echo "<br><b>Building Hypercube Table</b><br>\n<p>Dimensions listed apply to the Hypercube which comes after them in the list.</p>";
# # Read the Dimensions and build:
$ElIdToDimIdAI = []; # dimensions by Elements.Id
$res = $DB->ResQuery('Select * From Dimensions');
while ($o = $res->fetch_object())
  $ElIdToDimIdAI[(int)$o->ElId] = (int)$o->Id;
$res->free();
/*
CREATE TABLE Hypercubes (
  Id         smallint unsigned not null auto_increment,
  ElId       smallint unsigned not null, # Elements.Id of the hypercube
  RoleId     smallint unsigned not null, # Roles.Id    of the hypercube = Arcs.PRoleId.  Can have multiple roles for the same hypercube dimension  - see notes below
  Dimensions tinytext          not null, # List of dimensions for this hypercube as Dimensions.Id in csv form.
  Primary Key (Id)                       #  Max number of dimensions per hypercube is 9 djh??. Only one, the empty hypercube (our ID=djh??) has none.
) CHARSET=utf8;

Re RoleId
Usually the RoleId is the same for all dimensions of a hypercube, but in IFRS there are 4 cases where there are two roles as below:

                         TARId_HypercubeDim
Select * From Arcs Where ArcroleId=1 Order by FromId,PRoleId,ToId,TargetRoleId

For IFRS gives in part:
Id  TltN FromId ToId PRoleId ArcroleId ArcOrder ....
6382  2   2296   531   27     1  ...
6394  2   2296   531   28     1  ...

TltN 2 = TLTN_Definition
Hy El:2296 EarningsPerShareTable
Dim El:531 ClassesOfOrdinarySharesAxis

PRoleId 27 [310000] Statement of comprehensive income, profit or loss, by function of expense
        28 [320000] Statement of comprehensive income, profit or loss, by nature of expense

ArcroleId 1 = TARId_HypercubeDim

Have dim Id 531 twice for hypercube id 2296 distinguished by PRole

Cope with this by having two entries in the Hypercubes table with the same ElId and Dimensions string

The 4 IFRS cases are:
* Same hypercube ElId 1942 but pRoleId has changed from 68 to 108
  86 1942 DisclosureOfJointVenturesTable ([825480] Notes - Separate financial statements [R 68])
* Same hypercube ElId 2175 but pRoleId has changed from 70 to 106
  132 2175 DisclosureOfSignificantInvestmentsInAssociatesTable ([825480c] Notes - Separate financial statements [R 70])
* Same hypercube ElId 2179 but pRoleId has changed from 69 to 104
  134 2179 DisclosureOfSignificantInvestmentsInSubsidiariesTable ([825480a] Notes - Separate financial statements [R 69])
* Same hypercube ElId 2296 but pRoleId has changed from 27 to 28
  148 2296 EarningsPerShareTable ([310000] Statement of comprehensive income, profit or loss, by function of expense [R 27])
*/
# Hypercubes => Dimensions

# Arcs with ArcroleId                               FromId             ToId
#  TARId_HypercubeDim 1:   Hypercubes -> Dimensions Hypercube el Id    Dimension el Id
$currentHyElId = $currentPRoleId = 0;
$dimsS = ''; # ArcroleId TARId_HypercubeDim - From hypercube To dimension in the hypercube
$res = $DB->ResQuery('Select * From Arcs Where ArcroleId='.TARId_HypercubeDim.' Order by FromId,PRoleId,ToId,TargetRoleId');
while ($o = $res->fetch_object()) {
  $fromId  = (int)$o->FromId;
  $toId    = (int)$o->ToId;
  $pRoleId = (int)$o->PRoleId;
  if ($fromId != $currentHyElId || $pRoleId != $currentPRoleId) {
    # hypercube elid or prole has changed
    if ($fromId == $currentHyElId && $pRoleId != $currentPRoleId)
      echo "* Same hypercube ElId $currentHyElId but pRoleId has changed from $currentPRoleId to $pRoleId<br>";
    if ($currentHyElId) {
      # insert the hypercube that was being assembled
      $dimsS = substr($dimsS, 1);
      $hId = $DB->InsertQuery("Insert Hypercubes Set ElId=$currentHyElId,RoleId=$currentPRoleId,Dimensions='$dimsS'");
      echo "$hId $currentHyElId ", ElName($currentHyElId), ' (', Role($currentPRoleId), ")<br>\n";
    }
    # start the next hypercube
    $currentHyElId  = $fromId;
    $currentPRoleId = $pRoleId;
    $dimsS = '';
  }
  if ($o->TargetRoleId)
    echo "&nbsp;&nbsp;&nbsp;&nbsp;$toId ", ElName($toId), ' (Target role: ', Role($o->TargetRoleId), ")<br>\n";
  else
    echo "&nbsp;&nbsp;&nbsp;&nbsp;$toId ", ElName($toId), " (No target role)<br>\n";
  $dimsS .= ','.$ElIdToDimIdAI[$toId];
}
$res->free();
$dimsS = substr($dimsS, 1);
$hId = $DB->InsertQuery("Insert Hypercubes Set ElId=$currentHyElId,RoleId=$currentPRoleId,Dimensions='$dimsS'");
echo "$hId $currentHyElId ", ElName($currentHyElId), ' (', Role($currentPRoleId), ")<br>\n";
# $hId = $DB->InsertQuery("Insert Hypercubes Set ElId=".ElId_EmptyHypercube.",RoleId=0,Dimensions=''"); # The Empty Hypercube
# echo "$hId ",ElId_EmptyHypercube,' ', ElName(ElId_EmptyHypercube), ' (', Role(0), ")<br>\n";

# Add Braiins Dimension Ageing to Hypercubes
/*
$t = '';
foreach (array(1,13,21) as $hyId) {
  $dimsS = $DB->StrOneQuery("Select Dimensions from Hypercubes Where Id=$hyId");
  $dimsS .= '`'; # ` = Dim 48 = Ageing
  $DB->StQuery("Update Hypercubes Set Dimensions='$dimsS' Where Id=$hyId");
  $t .= ",$hyId";
}
echo '<br>Braiins Dimension Ageing added to Hypercubes ', substr($t,1), '<br>
'; */

# Add Hypercube Lists to Concrete Item Elements
# =============================================
echo "<br><b>Updating Elements.Hypercubes</b></br>\n";
# The 2013 code for this worked via parent roles and arcs but included use of TRId_FirstHypercubeRoleId which is not valid in general and not for IFRS,
# so here will use a simple brute force method.
/*
CREATE TABLE DimensionMembers (
  Id      smallint unsigned not null auto_increment, # Used as DiMeId
  DimId   smallint unsigned not null, # Dimensions.Id of the dimension
  ElId    smallint unsigned not null, # Elements.Id of the dimension member
  RoleId  smallint unsigned not null, # Roles.Id of the dimension member - re possible multiple roles per dimension
  Bits    tinyint  unsigned not null, # Dimension Member bits: DMB_Default ... defined ConstantsRgUK-GAAP
  Level   tinyint  unsigned not null, # Level of the DiMe from 0 upwards re Dimension Map and summing
  Primary Key (Id),
          Key (DimId) # not unique
) CHARSET=utf8;

CREATE TABLE Hypercubes (
  Id         smallint unsigned not null auto_increment,
  ElId       smallint unsigned not null, # Elements.Id of the hypercube
  RoleId     smallint unsigned not null, # Roles.Id    of the hypercube = Arcs.PRoleId.  Can have multiple roles for the same hypercube dimension  - see notes below
  Dimensions tinytext          not null, # List of dimensions for this hypercube as Dimensions.Id in csv form.
  Primary Key (Id)                       #  Max number of dimensions per hypercube is 9 for IFRS
) CHARSET=utf8;
*/
# Build
$elementsToHysA = []; # [elId => [hyIds]]
$res = $DB->ResQuery('Select Id,RoleId,Dimensions from Hypercubes');
while ($o = $res->fetch_object()) {
  $hyId   = (int)$o->Id;
  $roleId = (int)$o->RoleId;
  if ($o->Dimensions) # 2 empty ones in US GAAP. Re typedDomainRef?
    foreach (explode(COM, $o->Dimensions) as $dimId) {
      $re2 = $DB->ResQuery("Select ElId from DimensionMembers where DimId=$dimId and RoleId=$roleId");
      while ($o2 = $re2->fetch_object())
        $elementsToHysA[(int)$o2->ElId][] = $hyId;
    }
}
foreach ($elementsToHysA as $elId => $hyIdsA)
  $DB->StQuery(sprintf('Update Elements Set Hypercubes="%s" where Id=%d', implode(COM, $hyIdsA), $elId));

$n = $DB->OneQuery('Select count(*) from Elements where Hypercubes is not NULL');
echo "Done for $n elements.<br><br>"; # 536 for IFRS
# Check that all the updated elements were concrete
# All are abstract for IFRS yet all were concrete for the UK taxonomies!
# $res = $DB->ResQuery('Select Id,StdLabel From Elements Where Hypercubes is not NULL and (abstract is not null or TesgN !='.TESGN_Item.')');
# if ($res->num_rows) {
#   echo "Elements updated for hypercubes which are not concrete:<br>";
#   while ($o = $res->fetch_object())
#     echo "$o->Id $o->StdLabel<br>";
# }

$DB->commit();

# Build the Taxonomy Based Structs
# BuildTxBasedStructs(); Done via BuildTxStructs.php

Footer();
#########
#########

# FromTrees2($fromId, $pRoleId, $level)
# ----------
# Called with the first DiMe and role of a Dimension
# Based on the ElementsLookup function of the same name to find the other members of the dimension, and their levels,
#  using TARId_DomainMember 3 domain-member | From domain contains To member arcs
function FromTrees2($fromId, $pRoleId, $level) {
  global $DB;                                                          #    3
  $res = $DB->ResQuery('Select ToId,PRoleId from Arcs Where ArcroleId='.TARId_DomainMember." and FromId=$fromId and PRoleId=$pRoleId Order by ArcroleId,TargetRoleId,ArcOrder");
  if ($res->num_rows) {
    ++$level;
    while ($o = $res->fetch_object()) {
      $toId = (int)$o->ToId;
      $name = ElName($toId);
      RecordDimensionMember($level, $name, $toId, $pRoleId);
      FromTrees2($toId, $pRoleId, $level);
    }
  }
  $res->free();
}

function RecordDimensionMember($level, $name, $elId, $pRoleId) {
  global $DiMeIdsA;
  $DiMeIdsA[] = ['level' => $level, 'name' => $name, 'elId' => $elId, 'roleId' => $pRoleId];
}

# InsertDimensionMembers($dimId)
# ----------------------
# Called from the TARId_DimDomain Arcs loop after DiMes have been built up in $DiMeIdsA
function InsertDimensionMembers($dimId) {
  global $DB, $DiMeIdsA;
  $insertedElsAA = [[]]; # [roleId => [ElId => 1]]
  foreach ($DiMeIdsA as $i => $diMeA) { # [level, name, elId, roleId]
    extract($diMeA); # -> $level, $name, $elId, $roleId
    if (isset($insertedElsAA[$roleId][$elId]))
      echo "***$name $elId already inserted for Dim $dimId role $roleId<br>";
    else{
     $bits = DiMeB_Normal; # 0
     # See old B code for where various levels were fudged for the UK taxonomies
     $diMeId = $DB->InsertQuery("Insert DimensionMembers Set DimId=$dimId,ElId=$elId,Bits=$bits,Level=$level,RoleId=$roleId");
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

