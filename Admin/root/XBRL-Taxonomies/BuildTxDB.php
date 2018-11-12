<?php /* \Admin\root\XBRL-Taxonomies\BuildTxDB.php

Reads the Taxonomy xsd and xml files and stores the info in the XBRL Taxonomies DB.
Builds SSIM specific Tx based tables.

XBRL Specifications: https://specifications.xbrl.org/specifications.html

Assumes no Tuples
If ever Tuples are encountered see the 2013 work.

History:
2018.10.12 Started based on UK-IFRS-DPL version

ToDo djh??
====
Add Schema <annotation><documentation> elements concatenated
Expand LinkBaseRefs to include full info even tho not used
Check calculation arc weight range

See ///// Functions which are candidates for removal

*/

# Taxonomy Arc From/To Type enums                                  From re Link.TltN    To type
const TAFTT_Element = 1; # Arc From or To is an element            All ex TLTN_GenLink  TLTN_Definition | TLTN_Presentation | TLTN_Calculation
const TAFTT_Label   = 2; # Arc         To is a label resource      -                    TLTN_Label      | TLTN_GenLink /-ArcroleId = 7 TARId_ElementLabel
const TAFTT_Ref     = 3; # Arc         To is a reference resource  -                    TLTN_Reference  | TLTN_GenLink |           = 8 TARId_ElementRef
const TAFTT_Role    = 4; # Arc From       is a role                TLTN_GenLink  -

require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';
require "../../inc/tx/$TxName/BuildTxDB.inc"; # taxonomy specific stuff

Head("Build $TxName DB");

if (!isset($_POST['Sure']) || strtolower($_POST['Sure']) != 'yes') {
  echo <<< FORM
<h2 class=c>Build the $TxName XBRL Taxonomy DB Entries</h2>
<p class=c>Running this will delete all $TxName DB data and then rebuild it from the XML Taxonomy. The process will take minutes.<br>Once started, the process should be allowed to run to completion.</p>
<form method=post>
<div class=c>Sure? (Enter Yes if you are.) <input name=Sure size=3 value=Yes> <button class=on>Go</button></div>
</form>
FORM;
  $CentredB = true;
  Footer(false, false); # no time, no top btn
  exit;
}

set_time_limit(120); # was taking 133 secs to run for IFRS 2018 anyway but this is only script time not DB etc time on Linux.

# Type      Name ends with capital letter
# ----
# int       I or nothing as the default
# int       X array index from 0 up
# bool      B Also sometimes used for functions returning a bool
# string    S Also used for functions returning a string
# enum      N
# array     A array only used with A or M as second last letter meaning an array of an array or arrays
#
# Second Last Letter or last if default type int with no "I"
# A  int key array
# M  alpha key array (map) - should always have a following letter
$RolesMA      = # [RoleS       => [Id, usedOn, definition, FileIds, Uses]]
$ArcRolesMA   = # [ArcsrcroleS => [Id, usedOnN, definition, PacioDef, cyclesAllowed, FileIds, Uses]]
$NamespacesMA = # [namespaceS => [NsId, Prefix, FileIds, Num]
#NamesMI      = # [NsId.name   =>  ElId]
$XidsMI       = # [xidS  => ElId]  Not written to a table
#TextMA       = # [Text  => [TextId, Uses]]
$LinkbasesMA  = []; # [location => 1]

$tablesA = [
  'Arcroles',
  'Arcs',
  'Elements',
  'Imports',
  'Namespaces',
  'Resources',
  'Roles',
#  'Hypercubes',      /- done by BuildHypercubesDimensions.php
#  'Dimensions',      |
#  'DimensionMembers' |
];

# Add some of the common XBRL Roles to get them in the desired Id order to match the TRId_ constants
#                                    Role              UsedOn
# const TRId_StdLabel          =  1; # standardLabel     label
# const TRId_VerboseLabel      =  2; # verboseLabel      label
# const TRId_TerseLabel        =  3; # terseLabel        label
# const TRId_Documentation     =  4; # documentation     label
# const TRId_NetLabel          =  5; # netLabel          label
# const TRId_TotalLabel        =  6; # totalLabel        label
# const TRId_NegLabel          =  7; # negatedLabel      label
# const TRId_NegTerseLabel     =  8; # negatedTerseLabel label
# const TRId_NegTotalLabel     =  9; # negatedTotalLabel label
# const TRId_PeriodStartLabel  = 10; # periodStartLabel  label
# const TRId_PeriodEndLabel    = 11; # periodEndLabel    label
# const TRId_Reference         = 12; # reference         reference
# const TRId_DisclosureRef     = 13; # disclosureRef     reference
# const TRId_ExampleRef        = 14; # exampleRef        reference
# const TRId_CommonPracticeRef = 15; # commonPracticeRef reference
# const TRId_Link              = 16; # link              link,labelLink,referenceLink

$rolesAA = [ # role       usedOn       definition
  ['label',             'label',     'Standard Label'],
  ['verboseLabel',      'label',     'Verbose Label'],
  ['terseLabel',        'label',     'Terse Label'],
  ['documentation',     'label',     'Documentation'],
  ['netLabel',          'label',     'Net Label'],
  ['totalLabel',        'label',     'Total Label'],
  ['negatedLabel',      'label',     'Negative Label'],
  ['negatedTerseLabel', 'label',     'Negative Terse Label'],
  ['negatedTotalLabel', 'label',     'Negative Total Label'],
  ['periodStartLabel',  'label',     'Period Start Label'],
  ['periodEndLabel',    'label',     'Period End Label'],
  ['reference',         'reference', 'Reference'],
  ['disclosureRef',     'reference', 'Recommended Disclosure Reference'],
  ['exampleRef',        'reference', 'Example Reference'],
  ['commonPracticeRef', 'reference', 'Common Practice Reference'],
  ['link',              'link,labelLink,referenceLink', 'Link, Label Link, Reference Link'],
];
$RoleId=0;
foreach ($rolesAA as $role) {
  list($role, $usedOn, $definition) = $role;
  $RolesMA[$role] = ['Id' => ++$RoleId, 'usedOn' => $usedOn, 'definition' => $definition, 'FileIds' => NULL, 'Uses' => 0]; # [RoleS => [Id, usedOn, definition, FileIds, Uses]]
}
unset($rolesAA);

# Predfine the main XBRL Arcroles to get them in the desired Id order to match the TARId_ constants which are in TLTN_ sequence, with Pacio short definition added

# # Taxonomy Arcrole Id (Arcroles.Id) constants which are in descending TLTN_ order because of US GAAP adding lots more declaration ones
# # -------------------                                 /- TLTN_* arc (link) type
# const TARId_ElementRef    =  1; # element-reference   6  From element has To reference
# const TARId_ElementLabel  =  2; # element-label       6  From element has To label
# const TARId_ConceptRef    =  3; # concept-reference   5  From element has To reference
# const TARId_ConceptLabel  =  4; # concept-label       4  From element has To label
# const TARId_SummationItem =  5; # summation-item      3  From element sums To element
# const TARId_ParentChild   =  6; # parent-child        2  From parent To child
# const TARId_FirstDeclarationArcole = 7; #             1
# const TARId_HypercubeDim  =  7; # hypercube-dimension 1  From hypercube To dimension in the hypercube               Source (a hypercube) contains the target (a dimension) among others.
# const TARId_DimDomain     =  8; # dimension-domain    1  From dimension To first dimension member of the dimension  Source (a dimension) has only the target (a domain) as its domain.
# const TARId_DomainMember  =  9; # domain-member       1  From domain contains To member                             Source (a domain) contains the target (a member).
# const TARId_DimAll        = 10; # all                 1  From source requires dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) to appear in the context of the primary item.
# const TARId_DimNotAll     = 11; # notAll              1  From source excludes dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) not to appear in the context of the primary item.
# const TARId_DimDefault    = 12; # dimension-default   1  From dimension To default dimension member                 Source (a dimension) declares that there is a default member that is the target of the arc (a member).
# const TARId_EssenceAlias  = 13; # essence-alias       1  To is Alias of From used by US GAAP for one of their deprecated series of arcroles
# #onst TARId_DepConcepts   = 14; # dep-aggregateConcept-deprecatedPartConcept 1 From aggregate concept To deprecated part concept'], etc added by build for US GAAP
$arcrolesAA = [
  ['element-reference',   TLTN_GenLink,      'From element has To reference'],
  ['element-label',       TLTN_GenLink,      'From element has To label'],
  ['concept-reference',   TLTN_Reference,    'From element has To reference'],
  ['concept-label',       TLTN_Label,        'From element has To label'],
  ['summation-item',      TLTN_Calculation,  'From element sums To element'],
  ['parent-child',        TLTN_Presentation, 'From parent To child'],
  ['hypercube-dimension', TLTN_Definition,   'From hypercube To dimension in the hypercube'],
  ['dimension-domain',    TLTN_Definition,   'From dimension To first dimension member of the dimension'],
  ['domain-member',       TLTN_Definition,   'From domain contains To member'],
  ['all',                 TLTN_Definition,   'From source requires dimension members in the To hypercube'],
  ['notAll',              TLTN_Definition,   'From source excludes dimension members in the To hypercube - not used'],
  ['dimension-default',   TLTN_Definition,   'From dimension To default dimension member'],
  ['essence-alias',       TLTN_Definition,   'To is Alias of From']
];

$ArcroleId=0;
foreach ($arcrolesAA as $arcroleA) # [ArcsrcroleS => [Id, usedOnN, definition, PacioDef, cyclesAllowed, FileIds, Uses]]
  $ArcRolesMA[$arcroleA[0]] = ['Id' => ++$ArcroleId, 'usedOnN' => $arcroleA[1], 'definition' => NULL, 'PacioDef' => $arcroleA[2], 'cyclesAllowed' => NULL, 'FileIds' => NULL, 'Uses' => 0];
unset($arcrolesAA);

# Predfine the XBRL Namesspaces to avoid getting them intermingled with taxonomy specific ones re saving only taxonomy elements to the Elements table
# $NamespacesMA [namespaceS => [NsId, Prefix, FileIds, Num]
$namespacesAA = [
  ['http://www.w3.org/1999/xlink',      'xlink'],        # 1
  ['http://www.w3.org/2001/XMLSchema',  'xsd'],          # 2
  ['http://www.w3.org/2001/XMLSchema-instance', 'xsi'],  # 3
  ['http://www.xbrl.org/2003/instance', 'xbrli'],        # 4
  ['http://www.xbrl.org/2003/linkbase', 'link'],         # 5
  ['http://www.xbrl.org/2003/XLink',    'xl'],           # 6
  ['http://xbrl.org/2005/xbrldt',       'xbrldt'],       # 7
  ['http://www.xbrl.org/2006/ref',      'ref'],          # 8
  ['http://xbrl.org/2008/label',        'label'],        # 9
  ['http://xbrl.org/2008/generic',      'gen'],          # 10
  ['http://xbrl.org/2008/reference',    'reference'],    # 11
  ['http://www.xbrl.org/dtr/type/numeric', 'num'],       # 12
  ['http://www.xbrl.org/dtr/type/non-numeric', 'nonnum'],# 13
  ['http://xbrl.iasb.org/info',         'info']          # 14
];
const MaxStdXbrlNsId = 14;

$NsId=0;
foreach ($namespacesAA as $nsA) # $NamespacesMA  [namespaceS => [NsId, Prefix, FileIds, Num]
  $NamespacesMA[$nsA[0]] = ['NsId' => ++$NsId, 'Prefix' => $nsA[1], 'FileIds' => NULL, 'Num' => 0];
unset($namespacesAA);

$XR = new XMLReader();

echo "<h2 class=c>Building the $TxName Database</h2>
<b>Truncating DB tables</b><br>
";
foreach ($tablesA as $table) {
  echo $table, '<br>';
  $DB->StQuery("Truncate Table `$table`");
}

# Start with $EntryPountUrl set in the BuildTxDB.inc include

# The B code defined $roleA and $arcrolesAA here. Might need to bring the equivalent back?

$DB->autocommit(false);
###########
# Schemas #
###########
echo '<br><b>Importing Schemas</b><br>';
$TotalNodes = $FileId = 0;
Schema($entryPointUrl);

# Tuples Removed 2018.10.23 For code to build the Tuples table see older wip versions

#############
# Linkbases # http://www.datypic.com/sc/xbrl21/e-link_linkbase.html
#############
/*
<link:linkbase xmlns:gen="http://xbrl.org/2008/generic" xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:ref="http://www.xbrl.org/2006/ref" xmlns:reference="http://xbrl.org/2008/reference" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.xbrl.org/2006/ref http://www.xbrl.org/2006/ref-2006-02-27.xsd http://www.xbrl.org/2003/linkbase http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd http://xbrl.org/2008/reference http://www.xbrl.org/2008/generic-reference.xsd http://xbrl.org/2008/generic http://www.xbrl.org/2008/generic-link.xsd http://www.w3.org/1999/xlink http://www.xbrl.org/2003/xlink-2003-12-31.xsd">
  <link:roleRef roleURI="http://www.xbrl.org/2008/role/link" xlink:href="http://www.xbrl.org/2008/generic-link.xsd#standard-link-role" xlink:type="simple"/>
  <link:roleRef roleURI="http://www.xbrl.org/2008/role/reference" xlink:href="http://www.xbrl.org/2008/generic-reference.xsd#standard-reference" xlink:type="simple"/>
  <link:arcroleRef arcroleURI="http://xbrl.org/arcrole/2008/element-reference" xlink:href="http://www.xbrl.org/2008/generic-reference.xsd#element-reference" xlink:type="simple"/>
  <gen:link xlink:role="http://www.xbrl.org/2008/role/link" xlink:type="extended">

  <link:presentationLink xlink:role="http://xbrl.ifrs.org/role/ifrs/ias_2_2018-03-16_role-826380" xlink:type="extended">

*/
echo '<br><b>Importing Linkbases</b><br>';
$FileId = $MaxSchemaId;
$LinkbaseId = 0;
foreach ($LinkbasesMA as $href => $t) {
  $LinkbaseId++;
  $FileId++;
  $File   = $href;
  $NodeX  = -1;
  $NumNodes = ReadNodes($File);
  $TotalNodes += $NumNodes;
  echo "LinkbaseId $LinkbaseId FileId $FileId $File, $NumNodes nodes read -> Total nodes = ", number_format($TotalNodes), '<br>';
  flush();
  $node = $NodesA[++$NodeX]; # linkbase node
  #DumpExport("Node $NodeX", $node);
  # Add/update the namespaces
  # <link:linkbase xmlns:gen="http://xbrl.org/2008/generic" xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:ref="http://www.xbrl.org/2006/ref" xmlns:reference="http://xbrl.org/2008/reference" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.xbrl.org/2006/ref http://www.xbrl.org/2006/ref-2006-02-27.xsd http://www.xbrl.org/2003/linkbase http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd http://xbrl.org/2008/reference http://www.xbrl.org/2008/generic-reference.xsd http://xbrl.org/2008/generic http://www.xbrl.org/2008/generic-link.xsd http://www.w3.org/1999/xlink http://www.xbrl.org/2003/xlink-2003-12-31.xsd">
  # <link:linkbase xml:lang="en-US" xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.xbrl.org/2003/linkbase http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd">
  foreach ($node['attributes'] as $a => $v) {
    if (strpos($a, 'xmlns') === 0)
      # xmlns:link
      # xmlns:xlink
      # xmlns:ref
      # xmlns:reference
      # xmlns:xsi
      # xmlns:gen="http://xbrl.org/2008/generic"
      AddNamespace($a, $v);
    else switch ($a) {
      case 'xsi:schemaLocation':
        # ... xsi:schemaLocation="http://www.xbrl.org/2006/ref http://www.xbrl.org/2006/ref-2006-02-27.xsd http://www.xbrl.org/2003/linkbase http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd http://xbrl.org/2008/reference http://www.xbrl.org/2008/generic-reference.xsd http://xbrl.org/2008/generic http://www.xbrl.org/2008/generic-link.xsd http://www.w3.org/1999/xlink http://www.xbrl.org/2003/xlink-2003-12-31.xsd">
        # schemaLocation
        # space separated namespace | xsd, either once or multiple times
        $A = explode(' ', trim($v));
        $n = count($A);
        #Dump("xsi:schemaLocation $n", $A);
        for ($i=0; $i < $n; ) {
          AddNamespace('', $A[$i++]); # no prefix
          $loc = FileAdjustRelative($A[$i++]);
          # Expect these schema to have been imported. If not, the schema needs to added to the missing schema list in $MissedScemasAA
          if ($o = $DB->OptObjQuery("Select Id,FileIds From Imports where Location='$loc'"))
            $DB->StQuery("Update Imports Set Num=Num+1,FileIds='".$o->FileIds.','.$FileId."' Where Id=$o->Id");
          else
           #DieNow("Schema $loc referenced in the schemaLocation of &lt;link:linkbase at node $NodeX of LinkbaseId $LinkbaseId $File has not been imported"); # http://www.xbrl.org/2006/ref-2006-02-27.xsd
            echo "Schema $loc referenced in the schemaLocation of &lt;link:linkbase at node $NodeX of LinkbaseId $LinkbaseId $File has not been imported<br>";
        }
        break;
      case 'xml:lang':
        break;
      defaultt:
        DieNow("Unknown linkbase attribute $a");
    }
  }
  while (++$NodeX < $NumNodes) {
    $node = $NodesA[$NodeX];
    #DumpExport("Node $NodeX", $node);
   #$tag = str_replace('link:', '', $node['tag']); # strip leading link: if present
    $tag = $node['tag'];
    switch ($tag) {
      case 'link:roleRef':          break; # RoleRef($node);    /- Not in service as these doesn't seem useful since the roles and arcroles themselves handle the need. ?
      case 'link:arcroleRef':       break; # ArcroleRef($node); |
      case 'link:definitionLink':   XLink(TLTN_Definition);   break; # plus <loc and <definitionArc
      case 'link:presentationLink': XLink(TLTN_Presentation); break; # plus <loc and <presentationArc  (link:documentation is not used by UK GAAP)
      case 'link:calculationLink':  XLink(TLTN_Calculation);  break; # plos <loc and <calculationArc
      case 'link:labelLink':        XLink(TLTN_Label);        break; # plus <loc and <labelArc
      case 'link:referenceLink':    XLink(TLTN_Reference);    break; # plus <loc and <referenceArc
     #case 'link:footnoteLink':     footnote extended link element definition
      case 'gen:link':              XLink(TLTN_GenLink);      break;
      default: DieNow("unknown linkbase tag $tag<br>");
    }
  }
} # End of Linkbase loop
$NodeX  = -1;
unset($NodesA);

echo "<br>Elements and Arcs inserted.<br>";

# Insert the Roles
# $RolesMA [RoleS => [Id, usedOn, definition, FileIds, Uses]]
foreach ($RolesMA as $role => $roleA) { # ElId Elements.Id of the associated hypercube if applicable set by BuildHypercubesDimensions.php
  $id = $DB->InsertQuery("Insert Roles Set Role='$role',usedOn='$roleA[usedOn]',definition='$roleA[definition]',FileIds='$roleA[FileIds]',Uses=$roleA[Uses]");
  if ($id != $roleA['Id']) DieNow("Id $id on Role Insert not $roleA[Id] as expected");
}
echo "Roles inserted.<br>";

# Insert the Arcroles
# $ArcRolesMA [ArcsrcroleS => [Id, usedOnN, definition, PacioDef, cyclesAllowed, FileIds, Uses]]
foreach ($ArcRolesMA as $arcrole => $arcroleA) {
  $set = "Arcrole='$arcrole',UsedOnN=$arcroleA[usedOnN]";
  if ($arcroleA['PacioDef'])      $set .= ",PacioDef='$arcroleA[PacioDef]'";
  if ($arcroleA['definition'])    $set .= ",definition='$arcroleA[definition]'";
  if ($arcroleA['cyclesAllowed']) $set .= ",cyclesAllowed='$arcroleA[cyclesAllowed]'";
  if ($arcroleA['FileIds'])       $set .= ",FileIds='$arcroleA[FileIds]'";
  if ($arcroleA['Uses'])          $set .= ",Uses=$arcroleA[Uses]";
  $id = $DB->InsertQuery("Insert Arcroles Set $set");
  if ($id != $arcroleA['Id']) DieNow("Id $id on Arcrole Insert not $arcroleA[Id] as expected");
}
echo "Arcroles inserted.<br>";

# Insert the Namespaces
# $NamespacesMA [namespace => [NsId, Prefix, FileIds, Num]
foreach ($NamespacesMA as $ns => $nsA) {
  $id = $DB->InsertQuery("Insert Namespaces Set namespace='$ns',Prefix='$nsA[Prefix]',FileIds='$nsA[FileIds]',Num=$nsA[Num]");
  if ($id != $nsA['NsId']) DieNow("Id $id on Namespaces Insert not $nsA[NsId] as expected");
}
echo "Namespaces inserted.<br>";

/*

Element StdLabels

Select R.Id Rid,R.Text,E.Id Eid,A.* from Elements E Join Arcs A on A.TltN=4 and E.Id=A.FromId Join Resources R on R.Id=A.ToId and R.RoleId=1 order by Eid
-> 4992 rows                     TLTN_Label                                                       TRId_StdLabel
Update Elements E Join Arcs A on A.TltN=4 and E.Id=A.FromId Join Resources R on R.Id=A.ToId and R.RoleId=1 set E.StdLabel=R.Text
-> 4992 rows

*/
echo "<br><b>Updating Elements to add Standard and Terse Labels to simplify and speed label queries</b><br>";
# Update Elements.StdLabel
$DB->StQuery(sprintf('Update Elements E Join Arcs A on A.TltN=%d and E.Id=A.FromId Join Resources R on R.Id=A.ToId and R.RoleId=%d Set E.StdLabel=R.Text', TLTN_Label, TRId_StdLabel));
echo  $DB->OneQuery('Select count(*) from Elements where StdLabel is not NULL').' Elements.StdLabel updated.'.BRNL;
# Update Elements.TerseLabel
$DB->StQuery(sprintf('Update Elements E Join Arcs A on A.TltN=%d and E.Id=A.FromId Join Resources R on R.Id=A.ToId and R.RoleId=%d Set E.TerseLabel=R.Text', TLTN_Label, TRId_TerseLabel));
echo  $DB->OneQuery('Select count(*) from Elements where TerseLabel is not NULL').' Elements.TerseLabel updated.'.BRNL;

# Insert Text
# foreach ($TextMA as $text => $textA) { # array Text => [TextId, Uses]
#   $id = $DB->InsertQuery("Insert Text Set Text='$text',Uses=$textA[Uses]");
#   if ($id != $textA['TextId']) DieNow("Id $id on Text Insert not $textA[TextId] as expected");
# }

echo "<br>$MaxSchemaId Schemas and $LinkbaseId Linkbases imported ($FileId files) comprising $TotalNodes nodes.<br>";

$DB->commit();

/////////////////////////
// Post Main Build Ops // Moved into BuildHypercubesDimensions.php
/////////////////////////

Footer();
#########
#########

######################
## Schema functions ##
######################

function Schema($loc, $reentrantB=false) {
  static $sSchemaStackA=[], // stack of schemas for a re-entrant call
         $sStackDepth;
  global $DB,
         $NodeX,       // the NodesA index of the node being processed, pre incremented i.e. starts at -1
         $NumNodes,    // number of nodes read from $loc
         $NodesA,      // the nodes read from $loc
         $SchemaId,    // Id of current schema which can be can be < $MaxSchemaId after a return from a re-entrant call
         $MaxSchemaId, // running schema Id from 1 upwards - $SchemaId for the current schema can be < $MaxSchemaId after a return from a re-entrant call
         $TotalNodes,  // count of total number of nodes read
         $File,        // current schema or linkbase file
         $FileId,      // a running file Id for schemas and linkbases = Imports.Id
         $MissedScemasAA; # X => [SchemaId at which the missing schema is to be imported, url]

  if ($reentrantB) {
    # This is a re-entrant call
    # Check to see if this schema has already been processed
    if ($o = $DB->OptObjQuery("Select Id,FileIds From Imports where Location='$loc'")) {
      // Yep
      $DB->StQuery("Update Imports Set Num=Num+1,FileIds='" . $o->FileIds . ',' . $SchemaId . "' Where Id=$o->Id");
      echo "Schema $loc import not performed as it has already been processed<br>";
      return;
    }
    // Stack the previous schema being processed
    array_push($sSchemaStackA, [$SchemaId, $File, $NodeX, $NumNodes, $NodesA]);
    $sStackDepth++;
    echo "Schema $SchemaId node $NodeX stacked, depth $sStackDepth<br>";
    #for ($j=0; $j<$sStackDepth; $j++) {
    #  echo "Schema Stack $j SchemaId: {$sSchemaStackA[$j][0]}<br>";
    #  echo "Schema Stack $j File: {$sSchemaStackA[$j][1]}<br>";
    #  echo "Schema Stack $j NodeX: {$sSchemaStackA[$j][2]}<br>";
    #  echo "Schema Stack $j NumNodes: {$sSchemaStackA[$j][3]}<br>";
    #}
  }
  # Not already processed
  while (count($MissedScemasAA) && $MissedScemasAA[0][0] == ($MaxSchemaId+1)) {
    # Missed schema is due to be imported here
    list( , $missedLoc) = array_shift($MissedScemasAA);
    Schema($missedLoc);
  }
  $SchemaId =
  $FileId   = ++$MaxSchemaId; // $MaxSchemaId only ever increases
  $File     = $loc;
  $NodeX    = -1;
  $NumNodes = ReadNodes($loc);
  $TotalNodes += $NumNodes;
  echo "Schema $SchemaId $loc, $NumNodes nodes read -> Total nodes = ", number_format($TotalNodes), '<br>';
  flush();
  # Process the Schema file
  $node = $NodesA[++$NodeX]; # schema node
  #DumpExport("Node $NodeX", $node);
 #$tag  = $node['tag']; # could have a prefix e.g. xs:schema
  $set  = "Location='$loc'";
  $nsId = AddNamespace('', $node['attributes']['targetNamespace']);
  foreach ($node['attributes'] as $a => $v) {
    if (strpos($a, 'xmlns') === 0) { # namespace
      AddNamespace($a, $v);    # 'xmlns' => 'http://www.w3.org/2001/XMLSchema',
      continue;             # 'xmlns:uk-gaap-all' => 'http://www.xbrl.org/uk/gaap/core-all',
    }
    if (!strlen($v)) {
      echo "Ignoring empty attribute '$a' in NodeX $NodeX in Schema $File <br>";
      continue;
    }
    $set .= ",$a='$v'";
  }
  # if ($SchemaId != Insert('Schemas', $set)) DieNow("SchemaId $SchemaId != insert Id");
  while (++$NodeX < $NumNodes) {
    #DumpExport("Node $NodeX of $NumNodes", $NodesA[$NodeX]);
    switch (StripPrefix($NodesA[$NodeX]['tag'])) {
      case 'annotation':
        while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > 1) { # <annotation has a depth of 1
          $NodeX++;
          # DumpExport("Node $NodeX of $NumNodes", $NodesA[$NodeX]);
          switch (StripPrefix($NodesA[$NodeX]['tag'])) {
            case 'appinfo':
              while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > 2) { # <appinfo has a depth of 2
                $node = $NodesA[++$NodeX];
                #DumpExport("Node $NodeX", $node);
                switch ($node['tag']) {
                  case 'link:linkbaseRef': LinkbaseRef($node); break;
                  case 'link:roleType':    RoleType();    break;
                  case      'arcroleType':
                  case 'link:arcroleType': ArcroleType(); break;
                  default: DieNow("unknown schema annotation appinfo tag {$NodesA[$NodeX]['tag']}<br>");
                }
              }
              break;
            case 'documentation': break; # skip
            default: DieNow("unknown schema annotation tag {$NodesA[$NodeX]['tag']}<br>");
          }
        }
        break;
      case 'attribute':      Attribute();    break;
      case 'attributeGroup': AttributeGroup(); break;
      case 'complexType':    ComplexType();  break;
      case 'element':        Element($nsId); break;
      case 'import':
        # <import namespace="http://www.xbrl.org/2003/XLink" schemaLocation="xl-2003-12-31.xsd"/>
        # <xs:import namespace="http://fasb.org/stm/com/2018-01-31" schemaLocation="../stm/us-gaap-stm-com-2018-01-31.xsd"/>
        $node = $NodesA[$NodeX];
        $ns  = $node['attributes']['namespace'];
        $loc = $node['attributes']['schemaLocation'];
        AddNamespace('', $ns);
        Schema(FileAdjustRelative($loc), true); # true for rentrant
        break;
      case 'simpleType':     SimpleType();   break;
      default: DieNow("unknown schema tag {$NodesA[$NodeX]['tag']}<br>");
    }
  }
  // Finished this Schema
  echo "Finished Schema $SchemaId<br>";
  if ($reentrantB) {
    // Had nodes stacked via [$SchemaId, $File, $NodeX, $NumNodes, $NodesA];
    #for ($j=0; $j<$sStackDepth; $j++) {
    #  echo "Schema Stack $j SchemaId: {$sSchemaStackA[$j][0]}<br>";
    #  echo "Schema Stack $j File: {$sSchemaStackA[$j][1]}<br>";
    #  echo "Schema Stack $j NodeX: {$sSchemaStackA[$j][2]}<br>";
    #  echo "Schema Stack $j NumNodes: {$sSchemaStackA[$j][3]}<br>";
    #}
    $sStackDepth--;
    list($SchemaId, $File, $NodeX, $NumNodes, $NodesA) = array_pop($sSchemaStackA);
    echo "Back to Schema $SchemaId node $NodeX of $NumNodes nodes<br>";
    $FileId = $SchemaId;
  }
} // End Schema()

# Called from Schema()
# <element id="xml-gen-arc" name="arc" substitutionGroup="xl:arc" type="gen:genericArcType"/>
function Element($nsId) {
  global $NodesA, $NumNodes, $NodeX, $XidsMI, $FileId; # , $NamesMI
  static $sElId=0;
  $node = $NodesA[$NodeX];
  $depth = $node['depth'];
  $set = "NsId='$nsId'";
  $name = $xidS = $TesgN = 0; # $tuple = 0; # $tuple is set to '$nsId.$name' for the tuple case for passing to ComplexType()
  foreach ($node['attributes'] as $a => $v) {
    switch ($a) {
      case 'id':   $xidS = $v; continue 2; # SetIdDef($xidS=$v, $set); continue 2; # IdId
      case 'name': $name = $v; continue 2;
      case 'type':
        $a = 'TetN';
        switch ($v) {
          case 'xbrli:integerItemType':    $v = TETN_Integer; break;
          case 'xbrli:positiveIntegerItemType': $v = TETN_PositiveInteger; break;
          case 'xbrli:monetaryItemType':        $v = TETN_Money; break;
          case 'decimal':
          case 'xs:decimal':                                          # used by US GAAP
          case 'xbrli:decimalItemType':    $v = TETN_Decimal; break;
          case 'xbrli:nonZeroDecimal':     $v = TETN_NonZeroDecimal; break;
          case 'string':
          case 'xs:string':                                           # used by US GAAP
          case 'xbrli:normalizedStringItemType':                      # used by US GAAP
          case 'xbrli:stringItemType':     $v = TETN_String;   break;
          case 'xbrli:booleanItemType':    $v = TETN_Boolean;  break;
          case 'xs:date':                                             # used by US GAAP
          case 'xbrli:dateUnion':                                     # US GAAP union of xsd:date and xsd:dateTime
          case 'xbrli:dateItemType':       $v = TETN_Date;     break;
          case 'xbrli:gMonthDayItemType':  $v = TETN_MonthDay; break;
          case 'xbrli:gYearItemType':      $v = TETN_Year;     break;
          case 'xs:gYearMonth':                                       # used by US GAAP
          case 'xbrli:gYearMonthItemType': $v = TETN_YearMonth;break;
          case 'xbrli:durationItemType':   $v = TETN_Duration; break;
          case 'xbrli:sharesItemType':     $v = TETN_Share;    break;
          case 'num:areaItemType':         $v = TETN_Area;     break;
          case 'num:energyItemType':       $v = TETN_Energy;   break;
          case 'num:massItemType':         $v = TETN_Mass;     break;
          case 'num:percentItemType':      $v = TETN_Percent;  break;
          case 'num:perShareItemType':     $v = TETN_PerShare; break;
          case 'num:volumeItemType':       $v = TETN_Volume;   break;
          case 'nonnum:domainItemType':    $v = TETN_DomainItem; break;
          case 'nonnum:textBlockItemType': $v = TETN_TextBlock;  break;
          case 'xbrli:pureItemType':       $v = TETN_PureItem; break;
          case 'xs:anyURI':                                           # used by US GAAP
          case 'anyURI':                   $v = TETN_Uri;      break;
          case 'xbrli:anyURIItemType':     $v = TETN_Uri;      break;
          case 'anyType':                  $v = TETN_Any;      break;
          case 'xs:QName':                                            # used by US GAAP
          case 'QName':                    $v = TETN_QName;    break;
          case 'xl:arcType':               $v = TETN_Arc;      break;
          case 'xl:documentationType':     $v = TETN_Doc;      break;
          case 'xl:extendedType':          $v = TETN_Extended; break;
          case 'xl:locatorType':           $v = TETN_Locator;  break;
          case 'xl:resourceType':          $v = TETN_Resource; break;
          case 'anySimpleType':
          case 'xl:simpleType':            $v = TETN_Simple; break;
          case 'xl:titleType':             $v = TETN_Title;  break;
          case 'gen:genericArcType':       $v = TETN_GenArc; break;
          case 'gen:linkTypeWithOpenAttrs':$v = TETN_Link; break;
          # US GAAP ones from http://xbrl.sec.gov/dei/2018/dei-2018-01-31.xsd
          case 'dei:centralIndexKeyItemType':       $v = TETN_IndexKey;       break;
          case 'dei:countryItemType':               $v = TETN_CountryCode;    break;
          case 'dei:currencyItemType':              $v = TETN_CurrencyCode;   break;
          case 'dei:fileNumberItemType':            $v = TETN_FileNumber;     break;
          case 'dei:filerCategoryItemType':         $v = TETN_FilerCategory;  break;
          case 'dei:fiscalPeriodItemType':          $v = TETN_FiscalPeriod;   break;
          case 'dei:invCompanyType':                $v = TETN_InvCompanyCode; break;
          case 'dei:legalEntityIdentifierItemType': $v = TETN_LegalEntityIdentifier; break;
          case 'dei:nineDigitItemType':             $v = TETN_NineDigitCode;  break;
          case 'dei:submissionTypeItemType':        $v = TETN_SubmissionType; break;
          case 'us-types:yesNoItemType':
          case 'dei:yesNoItemType':                 $v = TETN_YesNo;          break;
          # US GAAP one from http://xbrl.sec.gov/invest/2013/invest-2013-01-31.xsd
          case 'invest:foreignCurrencyContractTransactionItemType': $v = TETN_BuySell; break; # Enumeration: "Buy" or "Sell"
          # US GAAP one from http://xbrl.fasb.org/srt/2018/elts/srt-2018-01-31.xsd
          case 'srt-types:extensibleListItemType':  $v = TETN_ExtensibleList; break;
          # US GAAP us-types
          case 'us-types:gYearListItemType':             $v = TETN_YearList;    break;
          case 'us-types:perUnitItemType':               $v = TETN_PerUnit;     break;
          case 'us-types:threeDigitItemType':            $v = TETN_ThreeDigits; break;
          case 'us-types:nineDigitItemType':             $v = TETN_NineDigits;  break;
          case 'us-types:authorizedUnlimitedItemType':   $v = TETN_AuthorizedUnlimited; break;
          case 'us-types:flowItemType':                  $v = TETN_FlowRate;    break;
          case 'us-types:distributionsReceivedApproach': $v = TETN_DistributionsReceivedApproach; break; # Enum "Cumulative earnings", "Nature of distribution"
          case 'us-types:interestRateItemType':          $v = TETN_InterestRateType; break; #  Enum "Floating", "Fixed"
          case 'us-types:restrictedInvestmentItemType':  $v = TETN_RestrictedInvestmentType; break; #  Enum "Restricted Investment", "Restricted Investment Exempt from Registration", "Restricted Investment Not Exempt from Registration"
          case 'us-types:investmentPledgedItemType':     $v = TETN_InvestmentPledgedType;    break; #  Enum "Investment Pledged", "Entire Investment Pledged", "Partial Investment Pledged"
          case 'us-types:investmentOnLoanForShortSalesItemType': $v = TETN_InvestmentOnLoanForShortSales; break; # Enum "Investment on Loan", "Entire Investment on Loan", "Partial Investment on Loan"
          case 'us-types:MalpracticeInsurance-OccurrenceOrClaims-madeItemType': $v = TETN_MalpracticeInsuranceClaims; break; # Enum for "Occurrence", "Claims-made"
          case 'us-types:fundedStatusItemType':          $v = TETN_FundedStatus;     break; # Enum for "Less than 65 percent", "Between 65 and less than 80 percent", "At least 80 percent", "NA"
          case 'us-types:fundingImprovementAndRehabilitationPlanItemType': $v = TETN_FundingImprovementAndRehabilitationPlan; break; # Enum "No", "Pending", "Implemented", "Other", "NA"
          case 'us-types:zoneStatusItemType':            $v = TETN_ZoneStatus;       break; # Enum "Green", "Yellow", "Orange", "Red", "Other", "NA"
          case 'us-types:surchargeItemType':             $v = TETN_SurchargeType;    break; # Enum "No", "Yes", "NA"
          case 'us-types:forfeitureMethod':              $v = TETN_ForfeitureMethod; break; # Enum "Estimating expected forfeitures", "Recognizing forfeitures when they occur"
          case 'tin-part:elementListItemType':           $v = TETN_ElementListType;  break; # Pattern \s*(([\i-[:]][\c-[:]]*:)?[\i-[:]][\c-[:]]*(\s+([\i-[:]][\c-[:]]*:)?[\i-[:]][\c-[:]]*)*)?\s*
          case 'tin-part:TransitionOptionList':          $v = TETN_TransitionOptionList; break; # Enum "Retrospective", "Prospective", "Modified Retrospective", "Modified Prospective"
          case 'tin-part:AsuNumber':                     $v = TETN_AsuNumber; break; # Pattern [0-9]{4}-[0-9]{2}
          default: DieNow("unknown element type $v");
        }
        break;
      case 'substitutionGroup':
        $a = 'TesgN';
        switch ($v) {
          case 'xbrli:item'          : $v = TESGN_Item;     break; # 1
          case 'xbrldt:dimensionItem': $v = TESGN_Dimension;break; # 2
          case 'xbrldt:hypercubeItem': $v = TESGN_Hypercube;break; # 3
          case 'link:part'           : $v = TESGN_LinkPart; break;
          case 'xl:arc'              : $v = TESGN_Arc;      break;
          case 'xl:documentation'    : $v = TESGN_Doc;      break;
          case 'xl:extended'         : $v = TESGN_Extended; break;
          case 'xl:locator'          : $v = TESGN_Locator;  break;
          case 'xl:resource'         : $v = TESGN_Resource; break;
          case 'xl:simple'           : $v = TESGN_Simple;   break;
          #ase 'xbrli:tuple'         : $v = TESGN_Tuple; $tuple="$nsId.$name"; break;
          default: DieNow("unknown element substitutionGroup $v");
        }
        $TesgN = $v;
        break;
      case 'xbrli:periodType':
        $a = 'PeriodN';
        switch ($v) {
          case 'instant':  $v = TEPTN_Instant;  break;
          case 'duration': $v = TEPTN_Duration; break;
          default: DieNow("unknown element periodType $v");
        }
        break;
      case 'xbrli:balance':
        $a = 'SignN';
        switch ($v) {
          case 'debit':  $v = TESN_Dr; break;
          case 'credit': $v = TESN_Cr; break;
          default: DieNow("unknown element balance $v");
        }
        break;
      case 'abstract':
      case 'nillable':
        if ($v === 'false') continue 2; # default so skip it
        if ($v !== 'true') DieNow("$a=|$v| in $name when true or false expected");
        $v=1;
        break;
      case 'info:creationID':
        # IFRS versioning attribute www.eurofiling.info/.../HPhilippXBRLVersioningForIFRSTaxonomy.ppt
        # Skipped as at 28.06.13
        continue 2;
      # <xs:element name="NameChangeEventDateAxis" id="dei_NameChangeEventDateAxis" type="xbrli:stringItemType" xbrli:periodType="duration" abstract="true" nillable="true" substitutionGroup="xbrldt:dimensionItem" xbrldt:typedDomainRef="#dei_eventDateTime"/>
      case 'xbrldt:typedDomainRef':
        $a = 'typedDomainRef';
        break;
      default:
        DieNow("unknown element attribute $a");
    }
    $set .= ",$a='$v'";
  }
  # <xs:element id="tin-part_PublishDate" name="PublishDate" substitutionGroup="link:part" type="xs:gYearMonth">
  #   <xs:annotation>
  #     <xs:documentation xml:lang="en">
  #     Publish date for Taxonomy Implementation Note in [YYYY-MM] format
  #     </xs:documentation>
  #   </xs:annotation>
  # </xs:element>
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > $depth) {
    switch (StripPrefix($NodesA[++$NodeX]['tag'])) {
      case 'annotation':    break; # / - skip as spec says not required to show doc other than via labels
      case 'documentation': break; # |
      #ase 'complexType':   ComplexType($tuple); break; # $set .= (',ComplexTypeId=' . ComplexType()); break;
      case 'complexType':   ComplexType(); break; # $set .= (',ComplexTypeId=' . ComplexType()); break;
      case 'simpleType':    SimpleType();  break; # $set .= (',SimpleTypeId='  . SimpleType());  break;
      default: DieNow("unknown element tag {$NodesA[$NodeX]['tag']}");
    }
  }

  if ($nsId <= MaxStdXbrlNsId) return;  # Don't store std XBRL elements - just the taxonomy ones
  if (!$TesgN || $TesgN>=TESGN_LinkPart) return;  # Don't store elements no SG or other than Item to LinkPart
    # #onst TESGN_None        0; # NULL                  Num     Elements with no SG are not stored in the Elements table
    # const TESGN_Item      = 1; # xbrli:item           4717 /- only store items with these SGHs
    # const TESGN_Dimension = 2; # xbrldt:dimensionItem  131 |
    # const TESGN_Hypercube = 3; # xbrldt:hypercubeItem  146 |
    # const TESGN_LinkPart  = 4; # link:part
    # const TESGN_Arc       = 5; # xl:arc
    # const TESGN_Doc       = 6; # xl:documentation
    # const TESGN_Extended  = 7; # xl:extended
    # const TESGN_Locator   = 8; # xl:locator
    # const TESGN_Resource  = 9; # xl:resource
    # const TESGN_Simple    =10; # xl:simple
    # #onst TESGN_Tuple     =11; # xbrli:tuple

  ++$sElId;
  # $NamesMI [NsId.name => ElId]
  if (!$name) DieNow('no name for element');
  # $nsname = "$nsId.$name";
  # if (isset($NamesMI[$nsname])) DieNow("Duplicate NsId.name $nsname");
  # $NamesMI[$nsname] = $sElId;
  if ($xidS) $XidsMI[$xidS] = $sElId;
  $set .= ",name='$name',FileId=$FileId";
  if (Insert('Elements', $set) != $sElId) DieNow("Elements insert didn't give expected Id of $sElId");
} // End Element()

# Called from Schema()
# <link:linkbaseRef xlink:arcrole="http://www.w3.org/1999/xlink/properties/linkbase" xlink:href="full_ifrs/dimensions/dim_full_ifrs_2018-03-16_role-901000.xml" xlink:role="http://www.xbrl.org/2003/role/definitionLinkbaseRef" xlink:type="simple"/>
function LinkbaseRef($node) {
  global $LinkbasesMA;
  if (@$node['attributes']['xlink:type'] != 'simple')    DieNow('LinkbaseRef type not simple');
  if (@$node['attributes']['xlink:arcrole'] != 'http://www.w3.org/1999/xlink/properties/linkbase') DieNow('LinkbaseRef arcrole not http://www.w3.org/1999/xlink/properties/linkbase');
  #$set = '';
  #foreach ($node['attributes'] as $a => $v) {
  #  $a = str_replace('xlink:', '', $a); # strip xlink: prefix
  #  switch ($a) {
  #    case 'type':             # skip as always simple
  #    case 'arcrole':          # skip as always http://www.w3.org/1999/xlink/properties/linkbase
  #    case 'role': continue 2; # skip as doesn't provide any useful info, just presentationLinkbaseRef etc which we don't need
  #    case 'href':  $v = FileAdjustRelative($v); break;
  #    case 'title': $v = addslashes($v); break;
  #    default: DieNow("unknown linkbaseref attribute $a");
  #  }
  #  $set .= ",$a='$v'";
  #}
  #Insert('LinkbaseRefs', $set);
  # Only interested in href for location
  foreach ($node['attributes'] as $a => $v)
    if (StripPrefix($a) == 'href') {
      $loc = FileAdjustRelative($v);
      if (isset($LinkbasesMA[$loc]))
        echo "Linkbase $loc repeated - not processed<br>";
      else
         $LinkbasesMA[$loc] = 1;
      return;
    }
  DieNow('No href in linbaseRef');
}

/*    <link:roleType roleURI="http://www.xbrl.org/2008/role/label" id="standard-label">
        <link:usedOn>label:label</link:usedOn>
      </link:roleType>
      <link:roleType id="ifrs-dim_role-990000" roleURI="http://xbrl.ifrs.org/role/ifrs/ifrs-dim_role-990000">
        <link:definition>[990000] Axis - Defaults</link:definition>
        <link:usedOn>link:calculationLink</link:usedOn>
        <link:usedOn>link:definitionLink</link:usedOn>
        <link:usedOn>link:presentationLink</link:usedOn>
      </link:roleType>
      <link:roleType id="ifrs_for_smes-dim_role-913000" roleURI="http://xbrl.ifrs.org/role/ifrs/ifrs_for_smes-dim_role-913000">
        <link:definition>[913000] Axis - Consolidated, combined and separate financial statements</link:definition>
        <link:usedOn>link:calculationLink</link:usedOn>
        <link:usedOn>link:definitionLink</link:usedOn>
        <link:usedOn>link:presentationLink</link:usedOn>
      </link:roleType>
*/
# Called from Schema()
# for link:roleType
function RoleType() {
  global $NodesA, $NumNodes, $NodeX;
  $node = $NodesA[$NodeX];
  if (!@$roleURI=$node['attributes']['roleURI']) DieNow('roleType roleURI missing');
  if (!@$idS=$node['attributes']['id'])          DieNow('roleType id missing');
  # Expect id to be in the uri
  # Now expect
  #  <link:definition>10 - Profit and Loss Account</link:definition> -- optional
  #  <link:usedOn>link:presentationLink</link:usedOn>
  $node = $NodesA[++$NodeX];
  if ($node['tag'] == 'link:definition') {
    $definition = addslashes($node['txt']);
    $node = $NodesA[++$NodeX];
  }else
    $definition = NULL;
  if ($node['tag'] != 'link:usedOn')     DieNow("{$NodesA[$NodeX]['tag']} tag found rather than expected link:usedOn");
  $usedOn = StripPrefix($node['txt']); # strip link: or label: prefix
  # Can have more link:usedOn's as above...
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['tag'] == 'link:usedOn') {
    $NodeX++;
    $usedOn .= ',' . StripPrefix($NodesA[$NodeX]['txt']); # CS the extra one(s)
  }
  if (!strpos($roleURI, $idS)) {
    # $idS is not in the uri
    $role = substr($roleURI, strrpos($roleURI, '/')+1);
    # No. Left roles as is re later reference to the role form e.g. <label:label xlink:label="res_3" xlink:role="http://www.xbrl.org/2008/role/label"
    # #    $idS            $role
    # # standard-label     label
    # # verbose-label      verboseLabel
    # # terse-label        terseLabel
    # # standard-link-role link
    # # standard-reference reference
    # $changedB = false;
    # switch ($role) {
    #   case 'label':     $changedB = true; $usingRole = 'standardLabel'; break;
    #   case 'link':      $changedB = true; $usingRole = 'standardLink';  break;
    #   case 'reference': $changedB = true; $usingRole = 'standardReference'; break;
    #   default: $usingRole = $role; break;
    # }
    # echo "roleType at node $NodeX of the Schema has an id of '$idS' which differs from the role '$role' from the roleURI '$roleURI'. Using $usingRole<br>";
    # if ($changedB)
    #   # change $rolURI so that UpdateRole() will get $usingRole
    #   $roleURI = str_replace($role, $usingRole, $roleURI);
    echo "roleType at node $NodeX of the Schema has an id of '$idS' which differs from the role '$role' from the roleURI '$roleURI'. Using $role<br>";
  }
  UpdateRole($roleURI, $usedOn, $definition);
}

# Called from Schema()
# for link:arcroleType
#    <link:arcroleType id="element-label" cyclesAllowed="undirected" arcroleURI="http://xbrl.org/arcrole/2008/element-label">
#      <link:definition>element has label</link:definition>
#      <link:usedOn>gen:arc</link:usedOn>
#    </link:arcroleType>
function ArcroleType() {
  global $NodesA, $NumNodes, $NodeX;
  $node = $NodesA[$NodeX];
  #DumpNode('ArcroleType 1');
  if (!@$arcroleURI=$node['attributes']['arcroleURI'])       DieNow('arcroleType arcroleURI missing');
  if (!@$id=$node['attributes']['id'])                       DieNow('arcroleType id missing');
  if (!@$cyclesAllowed=$node['attributes']['cyclesAllowed']) DieNow('arcroleType cyclesAllowed missing');
  # Now expect
  #  <definition></definition>
  #  <usedOn>definitionArc</usedOn>
  $node = $NodesA[++$NodeX];
  #DumpNode('ArcroleType 2');
  if (StripPrefix($node['tag']) != 'definition')      DieNow("{$node['tag']} tag found rather than definition");
  $definition = addslashes($node['txt']);
  $node = $NodesA[++$NodeX];
  #DumpNode('ArcroleType 3');
  if (StripPrefix($node['tag']) != 'usedOn')          DieNow("{$node['tag']} tag found rather than expected usedOn");
  if (!($usedOnN = Match($node['txt'], ['definitionArc', 'presentationArc', 'calculationArc', 'labelArc', 'referenceArc', 'gen:arc'])))
    DieNow("No ArcroleType match on {$node['txt']}");
  UpdateArcrole($arcroleURI, $usedOnN, $definition, $cyclesAllowed);
}

########################
## Linkbase functions ##
########################

# roleRef
# The roleRef    element is used to resolve xlink:role attribute values to the roleType element declaration.
# The arcroleRef element is used to resolve xlink:arcrole attribute values to the arcroleType element declaration.

# Not in service as these doesn't seem useful since the roles and arcroles themselves handle the need. ?

#<link:linkbase xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:xbrldt="http://xbrl.org/2005/xbrldt" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.xbrl.org/2003/linkbase http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd">
#  <link:roleRef roleURI="http://xbrl.ifrs.org/role/ifrs/ifrs-dim_role-901000" xlink:href="rol_full_ifrs-dim_2018-03-16.xsd#ifrs-dim_role-901000" xlink:type="simple"/>
#  <link:arcroleRef arcroleURI="http://xbrl.org/int/dim/arcrole/dimension-domain" xlink:href="http://www.xbrl.org/2005/xbrldt-2005.xsd#dimension-domain" xlink:type="simple"/>
#  <link:arcroleRef arcroleURI="http://xbrl.org/int/dim/arcrole/domain-member" xlink:href="http://www.xbrl.org/2005/xbrldt-2005.xsd#domain-member" xlink:type="simple"/>

# function RoleRef($node) {
#   if (@$node['attributes']['xlink:type'] != 'simple') DieNow('roleRef type not simple');
#   if (!isset($node['attributes']['xlink:href']))      DieNow('roleRef xlink:href attribute not set');
#   if (!isset($node['attributes']['roleURI']))         DieNow('roleRef roleURI attribute not set');
#   $set = '';
#   foreach ($node['attributes'] as $a => $v) {
#     $a = str_replace('xlink:', '', $a); // strip xlink: prefix
#     switch ($a) {
#       case 'type':    break; // always simple as tested for above
#       case 'href':    InsertHref($v, $set, 'RoleRefs'); break;
#       case 'roleURI': InsertUri ($v, $set, 'RoleUri');  break;
#       default: DieNow("unknown roleRef attribute $a");
#     }
#   }
#   InsertFromLinkbase('RoleRefs', $set);
# }

# function ArcroleRef($node) {
#   if (@$node['attributes']['xlink:type'] != 'simple') DieNow('arcroleRef type not simple');
#   if (!isset($node['attributes']['xlink:href']))      DieNow('arcroleRef xlink:href attribute not set');
#   if (!isset($node['attributes']['arcroleURI']))      DieNow('arcroleRef arcroleURI attribute not set');
#   $set = '';
#   foreach ($node['attributes'] as $a => $v) {
#     $a = str_replace('xlink:', '', $a); // strip xlink: prefix
#     switch ($a) {
#       case 'type': break; // skip
#       case 'href':       InsertHref($v, $set, 'ArcroleRefs'); break;
#       case 'arcroleURI': InsertUri ($v, $set, 'ArcroleUri');  break;
#       default: DieNow("unknown arcroleRef attribute $a");
#     }
#   }
#   InsertFromLinkbase('ArcroleRefs', $set);
# }

# Called from the Linkbase loop for:
#   case 'link:definitionLink':   XLink(TLTN_Definition);   break; # plus <loc and <definitionArc
#   case 'link:presentationLink': XLink(TLTN_Presentation); break; # plus <loc and <presentationArc  (link:documentation is not used by UK GAAP)
#   case 'link:calculationLink':  XLink(TLTN_Calculation);  break;
#   case 'link:labelLink':        XLink(TLTN_Label);        break; # plus <loc and <labelArc
#   case 'link:referenceLink':    XLink(TLTN_Reference);    break; # plus <loc and <referenceArc
#  #case 'link:footnoteLink':     footnote extended link element definition

# <link:presentationLink xlink:role="http://xbrl.ifrs.org/role/ifrs/ias_2_2018-03-16_role-826380" xlink:type="extended">
# For presentationLink, definitionLink, calculationLink labelLink, referenceLink
#     TLTN_Definition  TLTN_Presentation  TLTN_Calculation TLTN_Label  TLTN_Reference
function XLink($tltN) {
  global $NodesA, $NumNodes, $NodeX, $LocLabelToIdA, $XidsMI, $RolesMA;
  $LocLabelToIdA = []; # [label => [[id, TAFTT_Element | TAFTT_Label | TAFTT_Ref | TAFTT_Role]]] can have multiple entries for the same label. idS = locator idS, id == resource (Resources table) Id
  $node = $NodesA[$NodeX];
  #DumpExport("Node $NodeX in XLink()", $node);
  if (@$node['attributes']['xlink:type'] != 'extended') DieNow('...link type not extended');
  if (!($role = @$node['attributes']['xlink:role']))    DieNow('...link xlink:role attribute not set');
  $roleId = UpdateRole($role, StripPrefix($node['tag'])); # (uri, 'presentationLink')
  $depth1 = $node['depth']+1;
  # Arcs can have forward references to labels and references so do the arcs in a second pass
  $startNodeX = $NodeX;
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] === $depth1) {
    $node = $NodesA[++$NodeX];
    #DumpExport("Node $NodeX in XLink()", $node);
   #$tag = StripPrefix($NodesA[$NodeX]['tag']); # strip leading link: or label: in the gen:link case
    $tag = $node['tag'];
    switch ($tag) {
      case 'link:loc':
        # Locator
        # <link:loc xlink:href="../../full_ifrs-cor_2018-03-16.xsd#ifrs-full_DividendsProposedOrDeclaredBeforeFinancialStatementsAuthorisedForIssueButNotRecognisedAsDistributionToOwners" xlink:label="loc_1" xlink:type="locator
        if ($node['attributes']['xlink:type'] != 'locator') DieNow('loc type not locator');
        if (!$href = $node['attributes']['xlink:href'])     DieNow('loc xlink:href attribute not set');
        if (!$label = $node['attributes']['xlink:label'])   DieNow('loc xlink:label attribute not set');
        if (!$p = strpos($href, '#'))                       DieNow("No # in locator href $href");
        # The #... of the href is the id of the element with the href part up to the # being the xsd that defines the element
        # For UK-GAAP the id was always the same as the locator label as used by Arcs and so the from and to of Arcs could be used directly as ids.
        # But this is not necessarily so for in general and isn't for IFRS
        $idS = substr($href, $p+1); # Locator Label = Element IdS or RoleS
        if (isset($XidsMI[$idS]))                # $XidsMI  = [xidS  => ElId]
          $idA = [$XidsMI[$idS], TAFTT_Element];
        else if (isset($RolesMA[$idS]))          # $RolesMA = [RoleS => [Id, usedOn, definition, FileIds, Uses]]
          $idA = [$RolesMA[$idS]['Id'], TAFTT_Role];
        else
          DieNow("No match found for locator idS $idS in XLink()");
        $LocLabelToIdA[$label][] = $idA; # [label => [[id, TAFTT_Element | TAFTT_Label | TAFTT_Ref | TAFTT_Role]]]
        break;
      case 'link:definitionArc':
      case 'link:presentationArc':
      case 'link:calculationArc':
      case 'link:labelArc':
      case 'link:referenceArc':
      case 'gen:arc':
        break; # skip arcs, leaving them to the second pass
      case 'link:label':
      case 'label:label':
        Label($node);
        break;
      case 'link:reference':
      case 'reference:reference':
        Reference();
        break;
      default: DieNow("unknown xlink tag $tag<br>");
    }
  }
  # Now the arcs
  $endNodeX = $NodeX;
  $NodeX = $startNodeX;
  #echo "Now the arcs from NodeX = $startNodeX to endNodeX $endNodeX<br>";
  for ($NodeX = $startNodeX; $NodeX < $endNodeX; ) {
    $NodeX++;
    #echo "NodeX $NodeX<br>";
    switch ($NodesA[$NodeX]['tag']) {
      case 'link:definitionArc':
      case 'link:presentationArc':
      case 'link:calculationArc':
      case 'link:labelArc':
      case 'link:referenceArc':
      case 'gen:arc':
        #echo "NodeX $NodeX arc {$NodesA[$NodeX]['tag']}<br>";
        Arc($tltN, $roleId);
        break;
    }
  }
} # End XLink()

/*
\XBRL-Taxonomies\IFRS-2018\IFRST_2018-03-16\full_ifrs\labels\lab_full_ifrs-en_2018-03-16.xml
0 <link:linkbase xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.xbrl.org/2003/linkbase http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd">
1  <link:roleRef roleURI="http://www.xbrl.org/2009/role/negatedLabel" xlink:href="http://www.xbrl.org/lrr/role/negated-2009-12-16.xsd#negatedLabel" xlink:type="simple"/>
2  <link:roleRef roleURI="http://www.xbrl.org/2009/role/negatedTerseLabel" xlink:href="http://www.xbrl.org/lrr/role/negated-2009-12-16.xsd#negatedTerseLabel" xlink:type="simple"/>
3  <link:roleRef roleURI="http://www.xbrl.org/2009/role/negatedTotalLabel" xlink:href="http://www.xbrl.org/lrr/role/negated-2009-12-16.xsd#negatedTotalLabel" xlink:type="simple"/>
4  <link:roleRef roleURI="http://www.xbrl.org/2009/role/netLabel" xlink:href="http://www.xbrl.org/lrr/role/net-2009-12-16.xsd#netLabel" xlink:type="simple"/>
5  <link:labelLink xlink:role="http://www.xbrl.org/2003/role/link" xlink:type="extended">
6    <link:label id="ifrs-full_AccountingProfit_label" xlink:label="res_1" xlink:role="http://www.xbrl.org/2003/role/label" xlink:type="resource" xml:lang="en">Accounting profit</link:label>
7    <link:loc xlink:href="../full_ifrs-cor_2018-03-16.xsd#ifrs-full_AccountingProfit" xlink:label="loc_1" xlink:type="locator"/>
8    <link:labelArc xlink:arcrole="http://www.xbrl.org/2003/arcrole/concept-label" xlink:from="loc_1" xlink:to="res_1" xlink:type="arc"/>
9    <link:label id="ifrs-full_AccumulatedChangesInFairValueOfFinancialLiabilityAttributableToChangesInCreditRiskOfLiability_label" xlink:label="res_2" xlink:role="http://www.xbrl.org/2003/role/label" xlink:type="resource" xml:lang="en">Accumulated increase (decrease) in fair value of financial liability, attributable to changes in credit risk of liability</link:label>
A    <link:loc xlink:href="../full_ifrs-cor_2018-03-16.xsd#ifrs-full_AccumulatedChangesInFairValueOfFinancialLiabilityAttributableToChangesInCreditRiskOfLiability" xlink:label="loc_2" xlink:type="locator"/>
B    <link:labelArc xlink:arcrole="http://www.xbrl.org/2003/arcrole/concept-label" xlink:from="loc_2" xlink:to="res_2" xlink:type="arc"/>
*/
## For spec and notes on Arcs see Pacio\Development\SSIM Proto\Docs\Taxonomy DB.txt

# Called from XLink()
# For presentationLink, definitionLink calculationLink labelLink referenceLink gen:link
# ->  presentationArc,  definitionArc  ?               labelArc  referenceArc  gen:arc
#     TLTN_Definition TLTN_Presentation TLTN_Calculation TLTN_Label TLTN_Reference TLTN_GenLink  TLTN_Footnote
function Arc($tltN, $pRoleId) {
  global $DB, $NodesA, $NodeX, $XidsMI, $LocLabelToIdA; # $XidsMI [xidS  => ElId], $LocLabelToIdA [label => [[id, TAFTT_Element | TAFTT_Label | TAFTT_Ref | TAFTT_Role]]] can have multiple entries for the same label
  static $sNumFrom =1, $sNumTo = 1;
  $node = $NodesA[$NodeX];
  #DumpExport("Node $NodeX in Arc()", $node);
  if ($node['attributes']['xlink:type'] != 'arc')   DieNow('arc type not arc');
  if (!isset($node['attributes']['xlink:from']))    DieNow('arc xlink:from attribute not set');
  if (!isset($node['attributes']['xlink:to']))      DieNow('arc xlink:to attribute not set');
  if (!isset($node['attributes']['xlink:arcrole'])) DieNow('arc xlink:arcrole attribute not set');
  $set = "TltN=$tltN,PRoleId=$pRoleId";
  foreach ($node['attributes'] as $a => $v) {
    $a = str_replace('xlink:', '', $a); # strip xlink: prefix
    switch ($a) {
      case 'type': continue 2; # skip
      case 'from':
        if (!isset($LocLabelToIdA[$v])) DieNow("Arc From=$v not set in \$LocLabelToIdA['$v'])");
        $fromIdsA = $LocLabelToIdA[$v];
        if (count($fromIdsA) > $sNumFrom)
          #DumpNode(sprintf('Multiple (%d) Arc Froms <==========', $sNumFrom = count($fromIdsA)));
          echo sprintf('%d multiple From %s Arcs in node %d <==========<br>', $sNumFrom = count($fromIdsA), $v, $NodeX);
        continue 2;
      case 'to':
        if (!isset($LocLabelToIdA[$v])) DieNow("Arc To=$v not set in \$LocLabelToIdA['$v'])");
        $toIdsA = $LocLabelToIdA[$v];
        # To types can be deduced from Arcs.TltN and ArcroleId but might not have the arcroleId yet so do this check later
        if (count($toIdsA) > $sNumTo)
          #DumpNode(sprintf('Multiple (%d) Arc Tos <==========', $sNumTo = count($toIdsA)));
          echo sprintf('%d multiple To %s Arcs in node %d <==========<br>', $sNumTo = count($toIdsA), $v, $NodeX);
        continue 2;
      case 'arcrole':           $a = 'ArcroleId';       $v = $arcroleId = UpdateArcrole($v, $tltN); break;
      case 'preferredLabel':    $a = 'PrefLabelRoleId'; $v = UpdateRole($v);    break; #, $node['tag']);
      case 'xbrldt:targetRole': $a = 'TargetRoleId';    $v = UpdateRole($v);    break;
     #case 'title': SetText(str_replace('definition: ', '', $v), $set, 'Title'); continue 2; # 'definition: ' stripped from Arc titles. Taken out of use 08.10.12
      case 'title': # if ($v) echo "Arc title $v<br>"; None in IFRS
        continue 2; # skip
      case 'order':  $a = 'ArcOrder'; $v *= 1000000; break; # * 1000000 for storage as int with up to 6 decimals e.g. 1.999795
      case 'weight': $a = 'Weight';   $v *= 1000000; break; # * 1000000 for storage as int with up to 6 decimals e.g. 1.999795
      case 'use':
        switch ($v) {
          case 'optional':   $v = TAUN_Optional;   break;
          case 'prohibited': $v = TAUN_Prohibited; break;
          default: DieNow("unknown use value $v");
        }
        $a = 'ArcUseN';  break;
      case 'priority':   break;
      case 'xbrldt:closed':
        if ($v != 'true')    DieNow("'xbrldt:closed' ($v) not true");
        $a = 'ClosedB';  $v = 1;  break;
      case 'xbrldt:contextElement': # required values segment | scenario
        $a = 'ContextN';
        if ($v == 'segment')
          $v = TCN_Segment;
        else if ($v == 'scenario')
          $v = TCN_Scenario;
        else
          DieNow("'xbrldt:contextElement' ($v) not segment | scenario");
        break;
      case 'xbrldt:usable':
        if ($v != 'false')   DieNow("'xbrldt:usable' ($v) not false");
        $a = 'UsableB';  $v = 0;  break;
      default: DieNow("unknown arc attribute $a");
    }
    $set .= ",$a='$v'";
  }
  # Work out expected from and to types
  # From type can be deduced from Arcs.TltN, for IFRS anyway:
  # TltN                From Type
  # TLTN_Definition   /- element
  # TLTN_Presentation |
  # TLTN_Calculation  |
  # TLTN_Label        |
  # TLTN_Reference    |
  # TLTN_GenLink      -  role
  $fromTypeN = $tltN == TLTN_GenLink ? TAFTT_Role : TAFTT_Element;
  # To types can be deduced from Arcs.TltN and ArcroleId
  # TltN                To Type
  # TLTN_Definition   /- element
  # TLTN_Presentation |
  # TLTN_Definition   |
  # TLTN_Calculation  |
  # TLTN_Label        -  label resource
  # TLTN_Reference    -  reference resource
  # TLTN_GenLink      -  label or reference resource according to arcroleId TARId_ElementLabel or TARId_ElementRef
  switch ($tltN) {
    case TLTN_Definition:
    case TLTN_Presentation:
    case TLTN_Calculation:  $toTypeN = TAFTT_Element; break;
    case TLTN_Label:        $toTypeN = TAFTT_Label;   break;
    case TLTN_Reference:    $toTypeN = TAFTT_Ref;     break;
    case TLTN_GenLink:
      # expect arcroleId to be TARId_ElementLabel or TARId_ElementRef
      switch ($arcroleId) {
        case TARId_ElementLabel: $toTypeN = TAFTT_Label; break;
        case TARId_ElementRef:   $toTypeN = TAFTT_Ref;   break;
        default: DieNow(sprintf('In gen:link Arc arcroleId %d is not TARId_ElementLabel (%d) or TAFTT_Ref (%d) as expected', $arcroleId, TARId_ElementLabel, TAFTT_Ref));
      }
  }
  # Can have multiple table entries if multiple froms or tos or both
  $baseSet = $set;
  # Insert the arcs without updating Resources.ElId
  foreach ($fromIdsA as $j => $idA) {
    if ($idA[1] != $fromTypeN)
      DieNow(sprintf('In %s Arc from type is %d not %d as expected', LinkTypeToStr($tltN), $idA[1], $fromTypeN));
    $fromSet = $baseSet.",FromId={$idA[0]},ToId=";
    foreach ($toIdsA as $j => $idA) {
      if ($idA[1] != $toTypeN)
        DieNow(sprintf('In %s Arc to type is %d not %d as expected', LinkTypeToStr($tltN), $idA[1], $toTypeN));
      InsertFromLinkbase('Arcs', $fromSet.$idA[0]);
    }
  }
} # End Arc()

# Called from XLink()
# <link:label id="ifrs-full_AccountingProfit_label" xlink:label="res_1" xlink:role="http://www.xbrl.org/2003/role/label" xlink:type="resource" xml:lang="en">Accounting profit</link:label>
# <label:label xlink:label="res_1" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[913000] Axis - Consolidated, combined and separate financial statements</label:label>
# No id with label:label
# CREATE TABLE Resources (
#   Id      smallint unsigned not null auto_increment,
#   RoleId  smallint unsigned not null, # Roles.Id for xlink:role  [0..1] Anonymous
#   TextId  smallint unsigned not null, # Text.Id of content of the label or Json for the Ref
#   FileId  smallint unsigned not null, # Imports.Id of the linkbase file where defined - info purposes only
#   Primary Key (Id)
# ) CHARSET=utf8;
function Label($node) {
  global $LocLabelToIdA; # [label => [[id, TAFTT_Element | TAFTT_Label | TAFTT_Ref | TAFTT_Role]]] can have multiple entries for the same label
  if (@$node['attributes']['xlink:type'] != 'resource') DieNow('label type not resource');
  if (!@$label = $node['attributes']['xlink:label'])    DieNow('label xlink:label attribute not set');
  if (!($txt = addslashes($node['txt'])))               DieNow('label txt not set');
  # $set = '';
  # SetText($txt, $set);
  $set = "Text='$txt'";
  foreach ($node['attributes'] as $a => $v) {
    switch ($a) {
      case 'xlink:type':
      case 'xml:lang':
      case 'xlink:title':
      case 'xlink:label': # set to $label above
        break; # skip
      case 'id':
        break; # SetIdDef($v, $set);break; # Removed 08.10.12 as not useful
      case 'xlink:role':
        SetRole($v, $set, 'label'); # SetRole($roleURI, &$callingSet, $usedOn) ==> RoleId=RoleId# in $set
        break;
     #case 'xlink:title': SetText($v, $set, 'Title'); break; # Removed 08.10.12 as not useful
      default: DieNow("unknown label attribute $a");
    }
  }
  $id = InsertFromLinkbase('Resources', $set);
    $LocLabelToIdA[$label][] = [$id, TAFTT_Label]; # [label => [[id, TAFTT_Element | TAFTT_Label | TAFTT_Ref | TAFTT_Role]]] can have multiple entries for the same label
} // End Label()

# Called from XLink()
function Reference() {
  global $NodesA, $NumNodes, $NodeX;
  global $LocLabelToIdA; # [label => [[id, TAFTT_Element | TAFTT_Label | TAFTT_Ref | TAFTT_Role]]] can have multiple entries for the same label
  $node = $NodesA[$NodeX];
  if (@$node['attributes']['xlink:type'] != 'resource') DieNow('reference type not resource');
  if (!@$label = $node['attributes']['xlink:label'])    DieNow('reference xlink:label attribute not set');
  if (@$txt = $node['txt'])                             DieNow('reference txt is set');
  $set = '';
  foreach ($node['attributes'] as $a => $v) {
    switch ($a) {
      case 'xlink:type':  break; # skip
      case 'id':          break; # SetIdDef($v, $set); break; # IdId  Removed 08.10.12 as not used
      case 'xlink:label': # is set to $label above
        break; # skip
      case 'xlink:role':  SetRole($v, $set, 'reference'); break; # SetRole($roleURI, &$callingSet, $usedOn) ==>  RoleId=RoleId# in $set
      default: DieNow("unknown reference attribute $a");
    }
  }
  $depth1 = $node['depth']+1;
  #$refsA = $RefsA; # copy of the possible references with those elements that have values to be kept for jasonising, others to be zapped
  #while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] == $depth1) {
  #  $node = $NodesA[++$NodeX];
  #  $tag = $node['tag'];
  #  if (($p=strpos($tag, ':')) === false) DieNow("Reference subnode without expected :");
  #  $tag = substr($tag, $p+1);
  #  if (!isset($refsA[$tag])) DieNow("unknown reference subnode $tag from ".$NodesA[$NodeX]['tag']);
  #  if (isset($node['txt']))
  #    $refsA[$tag] .= ', ' . $node['txt']; # addslashes() only to the completed json via SetText() or any \ gets slashed
  #}
  #foreach ($refsA as $a => $v)
  #  if ($v)
  #    $refsA[$a] = substr($v,2);
  #  else
  #    unset($refsA[$a]);
  # 2018.10.21 Changed to accept any reference subnodes
  $refsA = [];
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] == $depth1) {
    $node = $NodesA[++$NodeX];
    if (isset($node['txt'])) {
      $tag = $node['tag'];
      if (($p=strpos($tag, ':')) === false) DieNow("Reference subnode $tag without expected :");
      $tag = substr($tag, $p+1);
      if (isset($refsA[$tag]))
        $refsA[$tag] .= ', ' . $node['txt']; # addslashes() only to the completed json via SetText() or any \ gets slashed
      else
        $refsA[$tag] = $node['txt'];
    }
  }
  # SetText(json_encode($refsA), $set); # associative array is encoded as an object
  $set .= ",Text='".addslashes(json_encode($refsA))."'";
  $id = InsertFromLinkbase('Resources', $set);
  $LocLabelToIdA[$label][] = [$id, TAFTT_Ref]; # [label => [[id, TAFTT_Element | TAFTT_Label | TAFTT_Ref | TAFTT_Role]]] can have multiple entries for the same label
} # End Reference()

/*

Plus http://www.xbrl.org/Specification/gnl/REC-2009-06-22/gnl-REC-2009-06-22.html
re gen:link
in \IFRS-2018\IFRST_2018-03-16\ifrs_for_smes\dimensions\gla_ifrs_for_smes-dim_2018-03-16-en.xml
0 <link:linkbase xmlns:gen="http://xbrl.org/2008/generic" xmlns:label="http://xbrl.org/2008/label" xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.xbrl.org/2003/linkbase http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd http://xbrl.org/2008/label http://www.xbrl.org/2008/generic-label.xsd http://xbrl.org/2008/generic http://www.xbrl.org/2008/generic-link.xsd http://www.w3.org/1999/xlink http://www.xbrl.org/2003/xlink-2003-12-31.xsd">
1   <link:roleRef roleURI="http://www.xbrl.org/2008/role/link" xlink:href="http://www.xbrl.org/2008/generic-link.xsd#standard-link-role" xlink:type="simple"/>
2   <link:roleRef roleURI="http://www.xbrl.org/2008/role/label" xlink:href="http://www.xbrl.org/2008/generic-label.xsd#standard-label" xlink:type="simple"/>
3   <link:arcroleRef arcroleURI="http://xbrl.org/arcrole/2008/element-label" xlink:href="http://www.xbrl.org/2008/generic-label.xsd#element-label" xlink:type="simple"/>
4   <gen:link xlink:role="http://www.xbrl.org/2008/role/link" xlink:type="extended">
5     <label:label xlink:label="res_1" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[913000] Axis - Consolidated, combined and separate financial statements</label:label>
6     <link:loc xlink:href="rol_ifrs_for_smes-dim_2018-03-16.xsd#ifrs_for_smes-dim_role-913000" xlink:label="loc_1" xlink:type="locator"/>
7     <gen:arc xlink:arcrole="http://xbrl.org/arcrole/2008/element-label" xlink:from="loc_1" xlink:to="res_1" xlink:type="arc"/>
      <label:label xlink:label="res_2" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[901000] Axis - Retrospective application and retrospective restatement</label:label>
      <link:loc xlink:href="rol_ifrs_for_smes-dim_2018-03-16.xsd#ifrs_for_smes-dim_role-901000" xlink:label="loc_2" xlink:type="locator"/>
      <gen:arc xlink:arcrole="http://xbrl.org/arcrole/2008/element-label" xlink:from="loc_2" xlink:to="res_2" xlink:type="arc"/>
      <label:label xlink:label="res_3" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[901500] Axis - Creation date</label:label>
      <link:loc xlink:href="rol_ifrs_for_smes-dim_2018-03-16.xsd#ifrs_for_smes-dim_role-901500" xlink:label="loc_3" xlink:type="locator"/>
      <gen:arc xlink:arcrole="http://xbrl.org/arcrole/2008/element-label" xlink:from="loc_3" xlink:to="res_3" xlink:type="arc"/>
      <label:label xlink:label="res_4" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[990000] Axis - Defaults</label:label>
      <link:loc xlink:href="rol_ifrs_for_smes-dim_2018-03-16.xsd#ifrs_for_smes-dim_role-990000" xlink:label="loc_4" xlink:type="locator"/>
      <gen:arc xlink:arcrole="http://xbrl.org/arcrole/2008/element-label" xlink:from="loc_4" xlink:to="res_4" xlink:type="arc"/>
    </gen:link>
  </link:linkbase>

Die - Arc FromId['loc_1'][0] = ifrs-dim_role-913000 is not in $XidsMI
*/

# Step over annotation & documentation nodes
/*
function StepOver() {
  global $NodesA, $NumNodes, $NodeX;
  while ($NodeX < $NumNodes && strpos('annotation,documentation', $NodesA[$NodeX]['tag']) !== false)
    $NodeX++;
}

function GetDoc() {
  global $NodesA, $NumNodes, $NodeX;
  $doc = '';
  $depth = $NodesA[$NodeX]['depth']; # current depth e.g. 0 for schema, 1 for element
  for ($n=$NodeX+1; $n < $NumNodes; $n++) {
    $node = $NodesA[$n];
    if ($node['depth'] == $depth) # back to depth of the parent tag
      break;
    if ($node['tag'] == 'documentation' && $node['depth'] == $depth+2)
     $doc .= '; ' . $node['txt'];
  }
  return ($doc > '' ? ("Doc='" . addslashes(substr($doc, 2)) . SQ) : '');
} */

function AddNamespace($prefix, $ns) {
  global $NsId, $NamespacesMA, $FileId; # $NamespacesMA [namespace => [NsId, Prefix, FileIds, Num]
  if (isset($NamespacesMA[$ns])) {
    if (!InStr(",$FileId,", ",{$NamespacesMA[$ns]['FileIds']},"))
      $NamespacesMA[$ns]['FileIds'] .= ",$FileId";
    $NamespacesMA[$ns]['Num']++;
    return $NamespacesMA[$ns]['NsId'];
  }
  $prefix = ($prefix > 'xmlns' && ($colon = strpos($prefix, ':')) > 0) ? substr($prefix, $colon+1) : '';
  if (!strlen($prefix))
    # if no "prefix" or short form of the NS use the last url segment that isn't a date
    $prefix = LastNonDateSegment($ns);
  $NamespacesMA[$ns] = ['NsId' => ++$NsId, 'Prefix'=>$prefix, 'FileIds'=> "$FileId", 'Num' => 1];
  return $NsId;
}

function Insert($table, $set) {
  global $DB;
  if ($set[0] == ',') # $set may or may not have a leading comma
    $set = substr($set,1);
  return $DB->InsertQuery("Insert `$table` Set $set");
}

function InsertFromLinkbase($table, $set) {
  global $DB, $FileId;
  if ($set[0] == ',')
    $set = substr($set,1);
  return $DB->InsertQuery("Insert `$table` Set $set,FileId=$FileId");
}

# Schema: Called from RoleType(uri, usedOn, definition) which is called from Schema() Also has an idS
#         <link:roleType id="ifrs-dim_role-990000" roleURI="http://xbrl.ifrs.org/role/ifrs/ifrs-dim_role-990000">
#           <link:definition>[990000] Axis - Defaults</link:definition>
#           <link:usedOn>link:calculationLink</link:usedOn>
#           <link:usedOn>link:definitionLink</link:usedOn>
#           <link:usedOn>link:presentationLink</link:usedOn>
#         </link:roleType>
# Linkbase loop:
# **      roleRef
# **      <link:roleRef roleURI="http://xbrl.ifrs.org/role/ifrs/ias_19_2018-03-16_role-834480a" xlink:href="rol_ias_19_2018-03-16.xsd#ias_19_2018-03-16_role-834480a" xlink:type="simple"/>
# Linkbase loop -> XLink()
#      -> XLink() -> UpdateRole(uri, 'presentationLink')
#         <link:presentationLink xlink:role="http://xbrl.ifrs.org/role/ifrs/ias_2_2018-03-16_role-826380" xlink:type="extended">
#         <link:definitionLink  xlink:role="http://xbrl.ifrs.org/role/ifrs/ias_19_2018-03-16_role-834480a" xlink:type="extended">
#         and XLink() -> Arc()
#         Arc(uri) -> UpdateRole(uri) re preferredLabel  and PrefLabelRoleId
#                                        PrefLabelRoleId     TargetRoleId
#         <link:presentationArc order="10.0" preferredLabel="http://www.xbrl.org/2009/role/negatedLabel" xlink:arcrole="http://www.xbrl.org/2003/arcrole/parent-child" xlink:from="loc_6" xlink:to="loc_5" xlink:type="arc"/>
#         <definitionArc xlink:type="arc" xlink:arcrole="http://xbrl.org/int/dim/arcrole/hypercube-dimension" xlink:from="uk-gaap_BasicHypercube" xlink:to="uk-gaap_RestatementsDimension" order="1" use="optional" xbrldt:targetRole="http://www.xbrl.org/uk/role/Dimension-Restatements" />
# ** not in use currently
# See also the XBRL Registry https://specifications.xbrl.org/registries/lrr-2.0/index.html
function UpdateRole($roleURI, $usedOn=NULL, $definition=NULL) {
  global $RoleId, $FileId, $RolesMA; # $RolesMA [RoleS => [Id, usedOn, definition, FileIds, Uses]]
  # http://www.xbrl./uk/role/ProftAndLossAccount => uk/ProftAndLossAccount
  # http://www.govtalk.gov.uk/uk/fr/tax/dpl-gaap/2012-10-01/role/Hypercube-DetailedProfitAndLossReserve => 'dpl-gaap/Hypercube-DetailedProfitAndLossReserve'
  # http://xbrl.ifrs.org/role/ifrs/ias_19_2018-03-16_role-834480a =>
  if (strpos($roleURI, 'http://') !== 0)   DieNow("non uri $roleURI passed to UpdateRole()");
 ##$role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', 'role/', 'int/', 'org/', 'govtalk.gov.uk/uk/fr/tax/','2013-02-01/'], '',  $roleURI); # strip http:// etc 'org/' for the anomoly of uk/org/role/BalanceSheetStatement
 #$role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', '2008/', 'role/', 'int/'], '', $roleURI); # strip http:// etc 'org/' for the anomoly of uk/org/role/BalanceSheetStatement
  # use the final uri segment i.e. after the final /
  $role = substr($roleURI, strrpos($roleURI, '/')+1);
  if (!isset($RolesMA[$role]))
    $roleA = ['Id' => ++$RoleId, 'usedOn' => $usedOn, 'definition' => $definition, 'FileIds' => "$FileId", 'Uses' => 1];
  else{
    $roleA = $RolesMA[$role];
    if (!is_null($usedOn)) {
      if (is_null($roleA['usedOn']))
        $roleA['usedOn'] = $usedOn;
      else if (!InStr($usedOn, $roleA['usedOn']))
        $roleA['usedOn'] .= ",$usedOn";
    }
    if (is_null($roleA['FileIds']))
      $roleA['FileIds'] = "$FileId";
    else if (!InStr(",$FileId,", ",{$roleA['FileIds']},"))
      $roleA['FileIds'] .= ",$FileId";
    $roleA['Uses']++;
  }
  $RolesMA[$role] = $roleA;
  return $roleA['Id'];
}

# Called from Label()
#             Reference()
# Called from XLink() as (uri, $set, 'label' | 'reference')
# <label:label xlink:label="res_1" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[913000] Axis - Consolidated, combined and separate financial statements</label:label>
function SetRole($roleURI, &$callingSet, $usedOn) {
  global $RoleId, $FileId, $RolesMA; # $RolesMA [Role => [Id, usedOn, definition, FileIds, Uses]]
  # http://www.xbrl.org/uk/role/ProftAndLossAccount => uk/ProftAndLossAccount
  if (strpos($roleURI, 'http://') !== 0)   DieNow("non uri $roleURI passed to SetRole()");
  ##$role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', 'role/', 'int/', 'org/'], '',  $role); # strip http:// etc 'org/' for the anomoly of uk/org/role/BalanceSheetStatement
  # $role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', '2008/', 'role/', 'int/'], '',  $role); # strip http:// etc
  # use the final uri segment i.e. after the final /
  $role = substr($roleURI, strrpos($roleURI, '/')+1);
  if (!isset($RolesMA[$role]))
    $roleA = ['Id' => ++$RoleId, 'usedOn' => $usedOn, 'definition' => NULL, 'FileIds' => "$FileId", 'Uses' => 1];
  else{
    $roleA = $RolesMA[$role];
    if (is_null($roleA['usedOn']))
      $roleA['usedOn'] = $usedOn;
    else if (!InStr($usedOn, $roleA['usedOn']))
      $roleA['usedOn'] .= ",$usedOn";
    if (is_null($roleA['FileIds']))
      $roleA['FileIds'] = "$FileId";
    else if (!InStr(",$FileId,", ",{$roleA['FileIds']},"))
      $roleA['FileIds'] .= ",$FileId";
    $roleA['Uses']++;
  }
  $RolesMA[$role] = $roleA;
  $callingSet .= ",RoleId={$roleA['Id']}";
}

# See also the XBRL Registry https://specifications.xbrl.org/registries/lrr-2.0/index.html
# Called from:
# Schema() -> ArcroleType() for link:arcroleType
#   as UpdateArcrole($arcroleURI, $usedOnN, $definition, $cyclesAllowed)
#   <link:arcroleType id="element-label" cyclesAllowed="undirected" arcroleURI="http://xbrl.org/arcrole/2008/element-label">
#     <link:definition>element has label</link:definition>
#     <link:usedOn>gen:arc</link:usedOn>
#   </link:arcroleType>
# Linkbase loop -> XLink() -> Arc(uri) -> UpdateArcrole(uri,tltN) re arcrole -> ArcroleId
#   as UpdateArcrole(uri, tltN)
#         <link:presentationArc order="10.0" preferredLabel="http://www.xbrl.org/2009/role/negatedLabel" xlink:arcrole="http://www.xbrl.org/2003/arcrole/parent-child" xlink:from="loc_6" xlink:to="loc_5" xlink:type="arc"/>
#         <definitionArc xlink:type="arc" xlink:arcrole="http://xbrl.org/int/dim/arcrole/hypercube-dimension" xlink:from="uk-gaap_BasicHypercube" xlink:to="uk-gaap_RestatementsDimension" order="1" use="optional" xbrldt:targetRole="http://www.xbrl.org/uk/role/Dimension-Restatements" />
# Expect all arcroles to have been predefined
function UpdateArcrole($arcroleURI, $usedOnN, $definition=NULL, $cyclesAllowed=NULL) {
  global $FileId, $ArcRolesMA, $ArcroleId; # [ArcsrcroleS => [Id, usedOnN, definition, PacioDef, cyclesAllowed, FileIds, Uses]]
  # http://www.xbrl.org/2003/arcrole/parent-child       => parent-child
  # http://xbrl.org/int/dim/arcrole/hypercube-dimension => hypercube-dimension
  # http://xbrl.org/arcrole/2008/element-label"         => element-label
  if (strpos($arcroleURI, 'http://') !== 0)   DieNow("non uri $arcroleURI passed to UpdateArcrole()");
  #$arcrole = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', '2008/', 'arcrole/', 'int/'], '', $arcroleURI); # strip http:// etc
  # use the final uri segment i.e. after the final /
  $arcrole = substr($arcroleURI, strrpos($arcroleURI, '/')+1);
  if (!isset($ArcRolesMA[$arcrole])) {
    $ArcRolesMA[$arcrole] = ['Id' => ++$ArcroleId, 'usedOnN' => $usedOnN, 'definition' => $definition, 'PacioDef' => NULL, 'cyclesAllowed' => $cyclesAllowed, 'FileIds' => "$FileId", 'Uses' => 1];
    return $ArcroleId;
  }
  $arcroleA = $ArcRolesMA[$arcrole];
  if ($usedOnN != $arcroleA['usedOnN'])
    DieNow("arcroleA['usedOnN'] {$arcroleA['usedOnN']} != $usedOnN");
  if (is_null($arcroleA['FileIds']))
    $arcroleA['FileIds'] = "$FileId";
  else if (!InStr(",$FileId,", ",{$arcroleA['FileIds']},"))
    $arcroleA['FileIds'] .= ",$FileId";
  if ($definition    && !$arcroleA['definition'])    $arcroleA['definition']    = $definition;
  if ($cyclesAllowed && !$arcroleA['cyclesAllowed']) $arcroleA['cyclesAllowed'] = $cyclesAllowed;
  $arcroleA['Uses']++;
  $ArcRolesMA[$arcrole] = $arcroleA;
  return $arcroleA['Id'];
}

###########
## Names ## For xsd:NCName
###########
# Elements.name    # name              [0..1] xsd:NCName
# Elements NameId  # Names.Id for name [0..1] xsd:NCName
# (xsd:NCName values in Labels, References, and Arc To fields for label and reference arcs are not stored but are just used during the build to link Labels and references to Elements.)
/* Taken OoS 10.10.12 with change to store name in the Elements table
function SetName($name, &$callingSet) {
  global $NamesMI; # $NamesMI [name => [NameId, ElId, Uses]]
  static $NamesIdS=0;
  $name = FixNameCase($name);
  if (isset($NamesMI[$name])) {
    $id = $NamesMI[$name]['NameId'];
    ++$NamesMI[$name]['Uses'];
  }else{
    $id = ++$NamesIdS;
    $NamesMI[$name] = array('NameId'=>$id, 'ElId'=>0, 'Uses'=>1);
  }
  $callingSet .= ",NameId=$id";
}
function FixNameCase($name) { Not needed after removal of LinkPart elements from build
  # re Footnote and footnote
  static $NameFixesSA = array('Footnote' => 'footnote', 'Part' => 'part');
  if (isset($NameFixesSA[$name])) return $NameFixesSA[$name];
  return $name;
} */

///// Functions which are candidates for removal
# Process re stepping over nodes but don't store
function Attribute() {
  global $NodesA, $NumNodes, $NodeX;
  $node = $NodesA[$NodeX];
  if (!@$name = $node['attributes']['name']) DieNow('no name for primary attribute');
  #$set = "name='$name'";
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > 1) { # <attribute has a depth of 1
    $NodeX++;
    switch ($NodesA[$NodeX]['tag']) {
      case 'annotation':
      case 'documentation':
        break;
      case 'simpleType': SimpleType(); #  $set .= (',SimpleTypeId=' . SimpleType());
        break;
      default: DieNow("unknown tag {$node['tag']} in <attribute<br>");
    }
  }
  #  Insert('Attributes', $set);
}

# Process re stepping over nodes but don't store
function AttributeGroup() {
  global $NodesA, $NumNodes, $NodeX;
  if (!$name = $NodesA[$NodeX]['attributes']['name']) DieNow('no name for attributeGroup');
  #$set = "name='$name'";
  #$attributesA = []; # there can be multiple <attribute subnodes
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > 1) {
    $node = $NodesA[++$NodeX];
    switch ($node['tag']) {
      case 'annotation':
      case 'documentation': break;
      case 'attribute':      # <attribute name="precision" type="xbrli:precisionType" use="optional" />
        #$attributesA[] = $node['attributes'];
        break;
      case 'attributeGroup': # <attributeGroup ref="xbrli:essentialNumericItemAttrs" />
        #$set .= ",ref='{$node['attributes']['ref']}'";
        break;
      case 'anyAttribute':
        #$set .= ",anyAttributeJson='" . json_encode($node['attributes']) . SQ;
        break;
      default: DieNow("unknown tag {$node['tag']} in <attributeGroup<br>");
    }
  }
  # if (count($attributesA))
  #    $set .= ",attributeJson='" . json_encode($attributesA) . SQ;
  # Insert('AttributeGroups', $set);  29.01.11 skip
}

/*
  <element name="arcroleType">
    <annotation>
      <documentation>
      The  arcroleType element definition - used to define custom
      arc role values in XBRL extended links.
      </documentation>
    </annotation>
    <complexType>
      <sequence>
        <element ref="link:definition" minOccurs="0"/>
        <element ref="link:usedOn" maxOccurs="unbounded"/>
      </sequence>
      <attribute name="arcroleURI" type="xl:nonEmptyURI" use="required"/>
      <attribute name="id" type="ID"/>
      <attribute name="cyclesAllowed" use="required">
        <simpleType>
          <restriction base="NMTOKEN">
            <enumeration value="any"/>
            <enumeration value="undirected"/>
            <enumeration value="none"/>
          </restriction>
        </simpleType>
      </attribute>
    </complexType>
  </element>

<!-- The per share item type indicates a monetary amount divided by a number of shares.  The per share item type has a Decimal base. -->
<complexType name="perShareItemType" xmlns="http://www.w3.org/2001/XMLSchema">
  <simpleContent>
    <restriction base="xbrli:decimalItemType"/>
  </simpleContent>
</complexType>

<xs:complexType name="invCompanyType">
  <xs:simpleContent>
    <xs:restriction base="xbrli:tokenItemType">
      <xs:enumeration value="N-1A"/>
      <xs:enumeration value="N-1"/>
      <xs:enumeration value="N-2"/>
      <xs:enumeration value="N-3"/>
      <xs:enumeration value="N-4"/>
      <xs:enumeration value="N-5"/>
      <xs:enumeration value="N-6"/>
      <xs:enumeration value="S-1 or S-3"/>
      <xs:enumeration value="S-6"/>
    </xs:restriction>
  </xs:simpleContent>
</xs:complexType>

*/
# Process re stepping over nodes but don't store
function ComplexType($tuple=0) {
  global $DB, $NodesA, $NumNodes, $NodeX, $SchemaId, $TuplesA;
  $node = $NodesA[$NodeX];
  #DumpExport("Node $NodeX in ComplexType() ", $node);
  $set = '';
  if (isset($node['attributes']['name'])) {
    $name = $node['attributes']['name'];
  }else
    $name = false;
  if (isset($node['attributes'])) {
    #if (!$name  = @$node['attributes']['name'])
    #  DieNow('No name for complexType with attributes');
    foreach ($node['attributes'] as $a => $v) {
      if ($a == 'mixed' && $v == 'true')
        $v = 1;
      $set .= ",$a='$v'";
    }
  }
  $depth = $node['depth']; # depth of the <complexType node - need this as ComplexType() is not called just when at depth 1
  $attributesA =     # for a set of <attribute tags
  $choiceA     =     # for a <choice list
  $complexA    =     # for <complexContent
  $simpleA     =     # for <simpleContent
  $sequenceA   = []; # for a <sequence list
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > $depth) {
    $node = $NodesA[++$NodeX];
    #DumpExport("Node $NodeX in ComplexType() ", $node);
    switch ( StripPrefix($node['tag'])) {
      case 'annotation':
      case 'documentation': break;
      case 'anyAttribute':  $set .= ",anyAttributeJson='" . json_encode($NodesA[$NodeX]['attributes']) . SQ;
        break;
      case 'attributeGroup': $set .= ",attributeGroupRef='{$NodesA[$NodeX]['attributes']['ref']}'";
        break;
      case 'attribute':      # <attribute name="id" type="ID" use="required" />
        $attributes = $node['attributes'];
        if ($NodesA[$NodeX+1]['tag'] == 'simpleType' && $NodesA[$NodeX+1]['depth'] == $depth+2) {
          $NodeX++;
          # attribute has simpleType subnode as for <element name="arcroleType"> ... <attribute name="cyclesAllowed" use="required"> <simpleType>
          # Add it to the json via $attributesA[]
          $attributes['simpleTypeId'] = SimpleType();
        }
        $attributesA[] = $attributes;
        break;
      case 'choice':
        while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > $depth+1)
          $choiceA[] = $NodesA[++$NodeX];
        break;
      case 'complexContent':
        while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > $depth+1)
          $complexA[] = $NodesA[++$NodeX];
        # if ($tuple) DumpExport("Tuple complex content complexA for $tuple", $complexA);
        # if ($tuple) $TuplesA[$tuple] = $complexA;
        break;
      case 'simpleContent':
        while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > $depth+1)
          $simpleA[] = $NodesA[++$NodeX];
        break;
      case 'sequence':
        while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > $depth+1)
          $sequenceA[] = $NodesA[++$NodeX];
        break;
      default: DieNow("unknown complex type tag {$NodesA[$NodeX]['tag']}");
    }
  }
  if (count($attributesA)) {
    #if (count($attributesA) > 1)
    #  echo 'comlexType attribute set count =' . count($attributesA) . '<br>';
    $set .= ",attributesJson='" . json_encode($attributesA) . SQ;
  }
  if (count($choiceA))
    $set .= ",choiceJson='" . json_encode($choiceA) . SQ;
  if (count($complexA))
    $set .= ",complexContentJson='" . json_encode($complexA) . SQ;
  if (count($simpleA))
    $set .= ",simpleContentJson='" . json_encode($simpleA) . SQ;
  if (count($sequenceA))
    $set .= ",sequenceJson='" . json_encode($sequenceA) . SQ;
  # # In the no name case use $set as a where clause with , => and to see if this complexType has already been defined
  # if (!$name) {
  #   if ($set[0] == ',')  # $set may have a leading comma
  #     $set = substr($set, 1); # djh?? SchemaId is a tinyint so can't hold a csv list
  #   if ($o = $DB->OptObjQuery('Select Id,SchemaId From ComplexTypes where ' . str_replace(',', ' and ', $set))) {
  #     $DB->StQuery("Update ComplexTypes Set SchemaId='" . $o->SchemaId . ',' . $SchemaId . "' Where Id=$o->Id");
  #     return $o->Id;
  #   }
  # }
  # return Insert('ComplexTypes', $set);
} // End ComplexType()

/*
<attribute name="type">
    <simpleType>
      <annotation>
        <documentation>
      Enumeration of values for the type attribute
      </documentation>
      </annotation>
      <restriction base="string">
        <enumeration value="simple"/>
        <enumeration value="extended"/>
        <enumeration value="locator"/>
        <enumeration value="arc"/>
        <enumeration value="resource"/>
        <enumeration value="title"/>
      </restriction>
    </simpleType>
  </attribute>

  <attribute name="role">
    <simpleType>
      <annotation>
        <documentation>
        A URI with a minimum length of 1 character.
        </documentation>
      </annotation>
      <restriction base="anyURI">
        <minLength value="1"/>
      </restriction>
  </simpleType>
  </attribute>

<xs:simpleType name="TransitionOptionList">
  <xs:restriction base="xs:string">
    <xs:enumeration value="Retrospective"/>
    <xs:enumeration value="Prospective"/>
    <xs:enumeration value="Modified Retrospective"/>
    <xs:enumeration value="Modified Prospective"/>
  </xs:restriction>
</xs:simpleType>

*/
# Process re stepping over nodes but don't store
function SimpleType() {
  global $DB, $NodesA, $NumNodes, $NodeX, $SchemaId;
  $node = $NodesA[$NodeX];
  #DumpExport("Node $NodeX in SimpleType() ", $node);
  $set = '';
  if (isset($node['attributes'])) {
    if (!$name = $node['attributes']['name']) DieNow('No name for SimpleType with attributes');
    $set .= ",name='$name'";
  }else
    $name = false;
  $depth = $node['depth']; # depth of the <simpleType node - need this as SimpleType() is not called just when at depth 1
  # Skip over any annotation & documentation nodes  djh?? add doc
  while (($NodeX+1) < $NumNodes && strpos('annotation,documentation', $NodesA[$NodeX+1]['tag']) !== false) {
    $NodeX++;
    #DumpExport("Node $NodeX in SimpleType() skip loop ", $NodesA[$NodeX]);
  }
  // move to next node after the simpleType and annotation & documentation nodes
  if (++$NodeX == $NumNodes) DieNow('hit the buffers in SimpleType()');
  $node  = $NodesA[$NodeX];
  #DumpExport("Node $NodeX in SimpleType() ", $node);
  switch (StripPrefix($node['tag'])) { # expect restriction or union
    case 'restriction':
      if (!$base = @$node['attributes']['base'])  DieNow('simpleType restriction base not found as expected');
      $set .= ",base='$base'";
      switch (StripPrefix($base)) {
        case 'anyURI': # expect minLength
          if (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] == $depth+2) { # +2 for restriction then minLength
            $NodeX++;
            $set .= ",{$NodesA[$NodeX]['tag']}={$NodesA[$NodeX]['attributes']['value']}";
          }
          break;
        case 'token':   # /- expect a set of enumeration values
        case 'NMTOKEN': # |         or a pattern
        case 'string':  # |
          if (($NodeX+1) < $NumNodes && StripPrefix($NodesA[$NodeX+1]['tag']) == 'pattern')
            $set .= ",Pattern='" . $NodesA[++$NodeX]['attributes']['value'] . "'";
          else{
            $enums = '';
            while (($NodeX+1) < $NumNodes && StripPrefix($NodesA[$NodeX+1]['tag']) == 'enumeration') {
              $enums .= ',' . $NodesA[++$NodeX]['attributes']['value'];
            }
            if (!($enums = substr($enums, 1))) DieNow("no enum list for simpleType base=$base");
            $set .= ",EnumList='$enums'";
          }
          break;
        case 'decimal': # expect nothing or minExclusive or maxExclusive. Put them straight it
          if (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] == $depth+2) { # +2 for restriction then minExclusive or maxExclusive
            $NodeX++;
            $set .= ",{$NodesA[$NodeX]['tag']}={$NodesA[$NodeX]['attributes']['value']}";
          }
          break;
        default: DieNow("SimpleType restriction base of $base not known");
      }
      #echo "set=$set<br>";
      break;

    case 'union':
      $set .= (',unionId=' . Union());
      break;
    default: DieNow('restriction or unions not found after simpleType as expected');
  }
  if (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > $depth) {
    #DumpExport("Node ". ($NodeX+1) . " next node after simpleType", $NodesA[$NodeX+1]);
    DieNow('SimpleType end not back to parent depth');
  }
  # # In the no name case use $set as a where clause with , => and to see if this simpleType has already been defined
  # if (!$name) {
  #   if ($set[0] == ',')  # $set may have a leading comma
  #     $set = substr($set, 1);
  #   if ($o = $DB->OptObjQuery('Select Id,SchemaId From SimpleTypes where ' . str_replace(',', ' and ', $set))) {
  #     $DB->StQuery("Update SimpleTypes Set SchemaId='" . $o->SchemaId . ',' . $SchemaId . "' Where Id=$o->Id");
  #     return $o->Id;
  #   }
  # }
  # return Insert('SimpleTypes', $set);
} // End SimpleType()

/*
  <simpleType name="nonZeroDecimal">
    <annotation>
      <documentation>
      As the name implies this is a decimal value that can not take
      the value 0 - it is used as the type for the denominator of a
      fractionItemType.
      </documentation>
    </annotation>
    <union>
      <simpleType>
        <restriction base="decimal">
          <minExclusive value="0"/>
        </restriction>
      </simpleType>
      <simpleType>
        <restriction base="decimal">
          <maxExclusive value="0"/>
        </restriction>
      </simpleType>
    </union>
  </simpleType>
*/
function Union() {
  global $DB, $NodesA, $NumNodes, $NodeX, $SchemaId;
  $node = $NodesA[$NodeX];
  #DumpExport("Node $NodeX in Union() ", $node);
  $set = '';
  if (isset($node['attributes'])) {
    if (!$memberTypes = $node['attributes']['memberTypes']) DieNow('No memberTypes for Union with attributes');
    $set .= ",memberTypes='$memberTypes'";
  }
  $depth = $node['depth']; # depth of the union
  # expect 0, 1 or 2 simpleTypes
  if (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['tag'] == 'simpleType') {
    $NodeX++;
    #DumpExport("Node $NodeX in Union() for simpleType 1", $NodesA[$NodeX]);
    $set .= ',SimpleType1Id=' . SimpleType();
  }
  if (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['tag'] == 'simpleType') {
    $NodeX++;
    #DumpExport("Node $NodeX in Union() for simpleType 2", $NodesA[$NodeX]);
    $set .= ',SimpleType2Id=' . SimpleType();
  }
  if (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > $depth) {
    #DumpExport("Node ". ($NodeX+1) . " next node after union", $NodesA[$NodeX+1]);
    DieNow('Union end not back to union depth');
  }
  # # See if this union has already been defined
  # $set = substr($set, 1);
  # if ($o = $DB->OptObjQuery('Select Id,SchemaId From Unions where ' . str_replace(',', ' and ', $set))) {
  #   $DB->StQuery("Update Unions Set SchemaId='" . $o->SchemaId . ',' . $SchemaId . "' Where Id=$o->Id");
  #   return $o->Id;
  # }
  # return Insert('Unions', $set);
} // End Union()

############
# Util Fns #
############

# ReadNodes()
# =========
# Reads nodes into $NodesA from file at $loc using the $XR instance of XMLReader
# Returns the number of nodes read
function ReadNodes($loc) {
  global $XR, $NodesA, $FileId;
  if (@$XR->open($loc) === false) DieNow("XMLReader unable to open $loc");
  $NodesA = [];
  $nodes = 0;
  $nX = -1;
  Insert('Imports', "Location='$loc',FileIds=$FileId,Num=1");
  # echo "ReadingNodes for file $FileId at $loc<br>";
  # echo "XR baseURI $XR->baseURI<br>";
  # echo "XR namespaceURI $XR->namespaceURI<br>"; # The URI of the namespace associated with the node
  # isDefault
  #     Indicates if attribute is defaulted from DTD
  # isEmptyElement
  #     Indicates if node is an empty element tag
  # prefix
  #     The prefix of the namespace associated with the node
  # localName
  #     The local name of the node
  # xmlLang
  #     The xml:lang scope which the node resides
  while($XR->read()) {
    #$nX++;
    #if ($XR->nodeType != XMLReader::SIGNIFICANT_WHITESPACE) { # lots of these - skip them
    #  $log = "XR node $nX (-> $nodes), type: $XR->nodeType, name '$XR->name', depth $XR->depth";
    #  if ($XR->attributeCount) $log .= ", attributeCount $XR->attributeCount";
    #  echo $log.'<br>';
    #}
    switch ($XR->nodeType) {
      case XMLReader::ELEMENT:   # 1
        # echo "Element start $XR->name<br>";
        $node = array('tag' => $XR->name, 'depth' => $XR->depth);
        if ($XR->hasAttributes)
          while($XR->moveToNextAttribute())
            $node['attributes'][$XR->name] = $XR->value;
        $NodesA[] = $node;
        $nodes++;
        break;
      case XMLReader::TEXT:      # 3
      case XMLReader::CDATA:     # 4
       #echo "Text value '$XR->value'<br>";
       #$NodesA[$nodes-1]['txt'] = trim(addslashes(preg_replace('/\s\s+/m', ' ', $XR->value)));
        $NodesA[$nodes-1]['txt'] = trim(preg_replace('/\s\s+/m', ' ', $XR->value)); # addslashes() removed to avoid probs with json_encode doing it also
        break;
      case XMLReader::COMMENT:            #   8
      case XMLReader::WHITESPACE:         # 13
      case XMLReader::SIGNIFICANT_WHITESPACE: # 14
      case XMLReader::END_ELEMENT: break; # 15
        break;
      case XMLReader::NONE:               #   0
      case XMLReader::ATTRIBUTE:          #   2
      case XMLReader::ENTITY_REF:         #   5
      case XMLReader::ENTITY:             #   6
      case XMLReader::PI:                 #   7
      case XMLReader::DOC:                #   9
      case XMLReader::DOC_TYPE:           #  10
      case XMLReader::DOC_FRAGMENT:       #  11
      case XMLReader::NOTATION:           #  12
      case XMLReader::END_ENTITY:         #  16
      case XMLReader::XML_DECLARATION:    #  17
        DieNow("XMLReader node type $XR->nodeType not processed");
    }
  }
  $XR->close();
  return $nodes;
}

/*
In $File: http://www.xbrl.org/2003/xbrl-instance-2003-12-31.xsd
have
<import namespace="http://www.xbrl.org/2003/linkbase" schemaLocation="xbrl-linkbase-2003-12-31.xsd"/>
want schemaLocation="xbrl-linkbase-2003-12-31.xsd
to become
http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd

In File  http://xbrl.fasb.org/us-gaap/2018/entire/us-gaap-entryPoint-std-2018-01-31.xsd
have
<xs:import namespace="http://fasb.org/stm/com/2018-01-31" schemaLocation="../stm/us-gaap-stm-com-2018-01-31.xsd"/>
want schemaLocation="../stm/us-gaap-stm-com-2018-01-31.xsd
to become
http://xbrl.fasb.org/us-gaap/2018/stm/us-gaap-stm-com-2018-01-31.xsd

*/
function FileAdjustRelative($loc) {
  global $File; # current Schema or Linkbase url
  # File: http://www.xbrl.org/2003/xbrl-instance-2003-12-31.xsd
  # loc:  xbrl-linkbase-2003-12-31.xsd
  # -->   http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd
  # echo "FileAdjustRelative()<br>File: $File<br>loc:  $loc<br>";
  if (strncmp($loc, 'http:', 5) === 0) {
    # if $loc starts with http: accept it as it is
    # echo 'loc unchanged<br>';
    return $loc;
  }
  if (strncmp($loc, '../', 3) === 0) {
    $fileBase = substr($File, 0, strrpos($File, '/')); # File stipped of last segment and
    # http://xbrl.fasb.org/us-gaap/2018/entire/us-gaap-entryPoint-std-2018-01-31.xsd => http://xbrl.fasb.org/us-gaap/2018/entire
    while (strncmp($loc, '../', 3) === 0) {
      $fileBase = substr($fileBase, 0, strrpos($fileBase, '/')); # File stipped of last segment and
      # http://xbrl.fasb.org/us-gaap/2018/entire => http://xbrl.fasb.org/us-gaap/2018
      $loc = substr($loc, 3);
    }
    return $fileBase . '/' . $loc;
  }
  # else replace last segment in $File
  return substr($File, 0, strrpos($File, '/')+1).$loc;
}

# Return tag stripped of prefix if any
function StripPrefix($tag) {
  if (($p = strpos($tag, ':')) > 0) # strip any prefix
    return substr($tag, $p+1);
  return $tag;
}

# Return last url segment of a uri which isn't a YYY-MM-DD date as used in uris
# e.g. for http://fasb.org/us-gaap-std/2018-01-31
#      return us-gaap-std
function LastNonDateSegment($uri) {
  $segsA = explode('/', $uri);
  $j= count($segsA) - 1;
  while ($j >= 0) {
    $ret = $segsA[$j];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ret))
      $j--;
    else
      return $ret;
  }
  return '';
}

# DieNow()
# ======
# Crude error exit - die a sudden death but commit first re viewing progress
function DieNow($msg) {
  global $DB, $NodeX, $NodesA, $NumNodes, $File, $FileId, $LinkbaseId;
  $DB->commit();
  if ($NodeX >= 0) {
    if ($LinkbaseId > 0)
      DumpExport("Dying at node $NodeX of $NumNodes in Linkbase $LinkbaseId $File", $NodesA[$NodeX]);
    else
      DumpExport("Dying at node $NodeX of $NumNodes in Schema $FileId $File", $NodesA[$NodeX]);
  }
  die("Die - $msg");
}

function DumpNode($msg) {
  global $NodeX, $NodesA, $NumNodes, $File;
  DumpExport("In node $NodeX of $NumNodes in $File $msg", $NodesA[$NodeX]);
}

##########################
## Out of Use Functions ##
##########################
/*
# Labels     TextId   # Text.Id  for the content of the label     /- Only these two as of 08.10.12
# References TextId   # Text.Id  for Refs content stored as json  |
function SetText($text, &$callingSet) {
  global $TextMA; # $TextMA text => [TextId, Uses]
  static $TextIdS=0;
  $text = addslashes($text);
  if (isset($TextMA[$text])) {
    $id = $TextMA[$text]['TextId'];
    ++$TextMA[$text]['Uses'];
  }else{
    $id = ++$TextIdS;
    $TextMA[$text] = ['TextId'=>$id, 'Uses'=>1];
  }
  $callingSet .= ",TextId=$id";
}
*/
