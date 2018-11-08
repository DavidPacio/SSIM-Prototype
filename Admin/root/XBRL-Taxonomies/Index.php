<?php /* SSIM Taxonomies index.php

*/

require '../../inc/BaseSSIM.inc';
require '../../inc/FuncsSSIM.inc';
require '../../inc/FuncsServer.inc';
require '../../inc/Session.inc';

if (isset($_GET['OntId'])) {
  # Have an OntId in the url as when coming from SSIM-Proto index.htm
  $OntId  = (int)$_GET['OntId'];
  $TxName = OntStr($OntId);
 #SessionStart(json_encode(['OntId' => $OntId, 'TxName' => $TxName])); On deciding requires PHP 7.1 to use keys with list()
  SessionStart(json_encode([$OntId, $TxName])); # works with the normal list
}else{
  # Expect a session to exist on for example a return here from running a module
  if (is_null($jsonData = SessionOpen()))
    SessionError();
  #list('OntId' => $OntId, 'TxName' => $TxName) = json_decode($jsonData, true); # Requires PHP 7.1
  #$jsonDataA = json_decode($jsonData, true);
  #$OntId  = $jsonDataA['OntId'];
  #$TxName = $jsonDataA['TxName'];
  list($OntId, $TxName) = json_decode($jsonData, true);
  $AppName = 'Admin '.$TxName;
  #echo "OntId $OntId, TxName $TxName<br>";
}

echo <<< END
<!DOCTYPE html>
<html lang=en>
<head>
<title>$TxName</title>
<meta charset=utf-8>
<link rel=apple-touch-icon sizes=180x180 href=../apple-touch-icon.png>
<link rel=icon type=image/png sizes=32x32 href=../favicon-32x32.png>
<link rel=icon type=image/png sizes=16x16 href=../favicon-16x16.png>
<link rel=stylesheet type=text/css href=../css/Site.css>
<style>
span.s1 {display:inline-block;width:68px}
span.s2 {display:inline-block;width:55px}
span.s3 {display:inline-block;width:50px}
</style>
</head>
<body>
<h1 class=c>SSIM Admin XBRL Taxonomy $TxName</h1>
<div class=mc style=width:120px>
<ul>
<li><a href=../>SSIM Admin</a></li>
</ul>
</div>
<div class=mc style=width:570px>
<div class='fl m05' style=width:245px>
<h2>Taxonomy</h2>
<ul>
<li><span class=s1>Elements:</span><span class=s2><a href=ElementsList.php>List</a></span><a href=ElementsLookup.php>Lookup</a></li>
<!-- <li><span class=s1></span><a href=ConcreteElements.php>All Concrete</a></li> -->
<li><span class=s1></span>All Concrete</li>
<li><a href=Arcroles.php>Arcroles List</a></li>
<li><a href=RolesTx.php>Roles List</a></li>
<li><a href=Arcs.php>Arcs List</a></li>
<li><a href=Labels.php>Labels List</a></li>
<li><a href=References.php>References List</a></li>
<li><a href=Hypercubes.php>Hypercubes</a></li>
<!-- <li><a href=HypercubesSubset.php>Hypercubes Subset Check</a></li> -->
<li>Hypercubes Subset Check</li>
<li><a href=Dimensions.php>Dimensions</a></li>
<li><a href=DiMes.php>Dimension Members</a></li>
</ul>
</div>
<div class='fl m05'>
<h2>DB Stuff</h2>
<ul>
<li><a href=BuildTxDB.php>Build the Taxonomy DB</a></li>
<li><a href=BuildHypercubesDimensions.php>Build Hypercubes & Dims</a></li>
<li><a href=BuildDimensionMembers.php>Build Dimension Members Check</a></li>
<li><a href=../PhpMyAdmin/ target=_blank>DB via PhpMyAdmin</a></li>
<!-- <li><a href=BuildTxStructs.php>Build Taxonomy Based Structs</a></li> -->
<li>Build Taxonomy Based Structs</li>
</ul>
<h2>General</h2>
<ul>
<li><a href='' target=_blank>New $TxName Tab</a></li>
</ul>
</div>
</div>
</body>
</html>
END;

function SessionError() {
echo <<< ERROREND
<!DOCTYPE html>
<html lang=en>
<head>
<title>Error</title>
<link rel=stylesheet type=text/css href=../css/Site.css>
</head>
<body>
<h1 class=c>SSIM Admin XBRL Taxonomy</h1>
<p class=c>Session error. Please go back to SSIM Admin and return.<br>
<a href=../>SSIM Admin</a></p>
</body>
</html>
ERROREND;
exit;
}
