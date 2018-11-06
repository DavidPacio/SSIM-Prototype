<?php /* Copyright 2011-2013 Braiins Ltd

Admin/www/UK-IFRS-DPL/BuildTxStructs.php

Build the Taxonomy Based Structs

History:
29.06.13 Created

*/
require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';
require '../../inc/tx/BuildTxStructs.inc';

Head("Build $TxName Structs");

echo "<h2 class=c>Build the $TxName Taxonomy Based Structs</h2>
";

BuildTxBasedStructs();

Footer();

?>

