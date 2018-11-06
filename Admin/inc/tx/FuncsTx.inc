<?php /* \SSIM Proto\Admin\inc\tx\FuncsTx.inc

SSIM Admin XBRL Taxonomy functions

History:
26.06.13 Started based on UK-GAAP-DPL version

ToDo djh??
====

string  DimsChrListToSrce($dims, $srceB=false)    Convert a Dims list in Chr List form to CS short name source form. Requires DimNamesA.inc to have been loaded for $DimNamesA
string  BuildTuplesList()                         Generates CS list of Tuple Ids for the Tuples that $txId is a member of
string  HypercubesFromList($hypercubesCsvList)    Generate a list of Hypercubes, using shortened role names, from a CSV list of Hypercubes.Ids, as used in Elements.Hypercubes

void Head($title, $centerB=false)
void Footer($timeB=true, $topB=false, $notCentredB=false)

*/

$StartUtime= utime();

function ElName($id) {
  global $DB;
  return $DB->StrOneQuery("Select name From Elements Where Id=$id");
}

function Text($id) {
  global $DB;
  return $id ? $DB->StrOneQuery("Select Text from Text where Id=$id") : '';
}

function Role($id, $appendIdB=true) {
  global $DB;
  static $sRolesAS;
  # On first call read and cache the Roles table
  if (!$sRolesAS) {
    $sRolesAS = [NULL];
    $res = $DB->ResQuery('Select definition From Roles');
    while ($o = $res->fetch_object())
      $sRolesAS[] = $o->definition;
    $res->free();
  }
  if (!isset($sRolesAS[$id]))
    return '';
  $ret = $sRolesAS[$id]; # $DB->StrOneQuery("Select definition from Roles where Id=$id");
  if ($appendIdB)
    $ret .= " [R&nbsp;$id]";
  return $ret;
}

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
function Arcrole($id, $fullB=false) {
  global $DB;          #         0        1        2
  static $sArcRolesAA; # [id => [Arcrole, UsedOnN, PacioDef]]
  # On first call read and cache the ArcRoles table
  if (!$sArcRolesAA) {
    $sArcRolesAA = [[NULL]]; # the 0 one
    $res = $DB->ResQuery('Select Arcrole,UsedOnN,PacioDef From Arcroles');
    while ($o = $res->fetch_object())
      $sArcRolesAA[] = [$o->Arcrole, $o->UsedOnN, $o->PacioDef];
    $res->free();
  }
  if (!isset($sArcRolesAA[$id])) return "Error: Unknown ArcroleId $id";
  $arcroleA = $sArcRolesAA[$id];
  if ($fullB)
    return sprintf('%sArc<br>%s<br>%s', LinkTypeToStr($arcroleA[1]), $arcroleA[0], $arcroleA[2]);
  return $arcroleA[0]; # Arcrole
}

function ShortNamespace($nsId) {
  global $DB;
  static $sNamespacesAS;
  # On first call read and cache the $sNamespacesAS table
  if (!$sNamespacesAS) {
    $sNamespacesAS = [NULL]; # the 0 one
    $res = $DB->ResQuery('Select Prefix From Namespaces');
    while ($o = $res->fetch_object())
      $sNamespacesAS[] = $o->Prefix;
    $res->free();
  }
  return !isset($sNamespacesAS[$nsId]) ? '' : $sNamespacesAS[$nsId];
}

function SignToStr($N) { # Used by both Tx and Rg modules so here
  static $a = ['Not set', 'Debit', 'Credit', 'Either'];
  return $a[(int)$N];
}

# # DimsChrListToSrce($dims, $srceB=false)
# # Convert a Dims list in Chr List form to CS short name source form
# # Requires DimNamesA.inc to have been loaded for $DimNamesA
# function DimsChrListToSrce($dims, $srceB=false) {
#   if ($srceB) {
#     global $DimNamesA;
#     if (!$dims || !($n = strlen($dims))) return '';
#     $r = '';
#     for ($i=0; $i<$n; ++$i)
#       $r .= ',' . $DimNamesA[(ord($dims[$i]) - 48)];
#     return substr($r, 1);
#   }
#   return ChrListToCsList($dims);
# }


# BuildTuplesList()
# Generates CS list of Tuple Ids for the Tuples that $txId is a member of
#function BuildTuplesList($txId, $hyId) {
#  global $DB, $TupIdsByMemberTxIdAndHyIdGA; # Array of Tuples for Members by TxId which are Members of Multiple Tuples whose use can be narrowed down by Hypercube defined in ConstantsTx.inc
#  static $TupleListsA = []; # to cache results mainly for ElementLookup() where this can be called repeatedly for the same txId
#  if ($hyId && isset($TupIdsByMemberTxIdAndHyIdGA[$txId])) { # [TupleMemberTxId (TuplePairs.MemTxId) => [HyId => TuMeId]]
#    $tA = $TupIdsByMemberTxIdAndHyIdGA[$txId]; # [HyId => TupId]
#    if (isset($tA[$hyId]))
#      return $tA[$hyId]; # the TupId
#    die("Die - Call to BuildTuplesList(txId $txId, hyId $hyId) with txId $txId in TupIdsByMemberTxIdAndHyIdA but no entry for HyId $hyId as expected");
#  }
#  if (!isset($TupleListsA[$txId])) {
#    $res = $DB->ResQuery("Select TupId From TuplePairs Where MemTxId=$txId Order by Id");
#    $TupIds = ''; # varchar(24) CS list of Tuples (TupIds from TuplePairs.TupId) which can be used with the Bro, if any. Longest is 6.
#    while ($o = $res->fetch_object())
#      $TupIds .= ",$o->TupId";
#    $res->free();
#    $TupleListsA[$txId] = substr($TupIds, 1);
#  }
#  return $TupleListsA[$txId];
#}

# Generate a list of Hypercubes, using shortened role names, from a CSV list of Hypercubes.Ids, as used in Elements.Hypercubes
function HypercubesFromList($hypercubesCsvList) {
  global $DB;
  static $rolesA;
  $ret = '';
  if (strlen($hypercubesCsvList))
    foreach (explode(COM, $hypercubesCsvList) as $hyId) {
      if (!isset($rolesA[$hyId]))
        $rolesA[$hyId] = ", [$hyId] ".$DB->StrOneQuery("Select R.definition from Roles R Join Hypercubes H on H.RoleId=R.Id Where H.Id=$hyId");
      $ret .= $rolesA[$hyId];
    }
  return substr($ret, 2);
}

############################
# Utils Modules Output Fns #
############################

function Fold($txt, $len) {
  $remain = $txt;
  $folded = '';
  while (strlen($remain) > $len) {
    $folded .= substr($remain, 0, $len) . '<br>';
    $remain  = '&nbsp;&nbsp;'.substr($remain, $len);
  }
  return $folded . $remain;
}

function FoldOnSpace($txt, $len) {
  $segsA = explode(' ', $txt);
  $folded = '';
  $lenThis = 0; # length of this line
  foreach ($segsA as $i => $seg) {
    $lenSeg = strlen($seg);
    if ($lenThis + $lenSeg + 1 > $len) {
      $folded = (!$i ? '' : $folded.'<br>&nbsp;&nbsp;').Fold($seg, $len);
      $lenThis = $lenSeg;
    }else{
      $folded .= ' '.$seg;
      $lenThis += $lenSeg + 1;
    }
  }
  return $folded;
}

function Head($title, $centerB=false) {
  global $CentredB;
  $CentredB = $centerB;
  echo <<< HEAD
<!DOCTYPE html>
<html lang=en>
<head>
<title>$title</title>
<meta charset=utf-8>
<link rel='shortcut icon' href=../favicon.png>
<link rel=apple-touch-icon sizes=180x180 href=../apple-touch-icon.png>
<link rel=icon type=image/png sizes=32x32 href=../favicon-32x32.png>
<link rel=icon type=image/png sizes=16x16 href=../favicon-16x16.png>
<link rel=stylesheet type=text/css href=../css/Site.css>
</head>
<body>
HEAD;
}

function Footer($vTimeB=true, $vTopB=true, $vCentredB=true) {
  global $StartUtime, $CentredB;
  $class=($CentredB && $vCentredB ? ' class=c' : '');
  if ($vTimeB)
    echo "<p$class>Done in ", ElapsedTime($StartUtime), "</p>\n";
  else
    echo '<br>';
  if ($vTopB)
    echo "<div class=topB onclick=window.scrollTo(0,0)>Top</div><br>\n";
  echo "<p$class><a href=./>Home</a></p>
<br><br>
<div class=topl><a href=./>Home</a></div>
<div class=topr><a href=./>Home</a></div>
";
  echo "</body>\n</html>\n";
  exit;
}

function Abort($msg) {
  echo "\n<p>$msg</p>";
  Footer(false);
  #exit;
}
