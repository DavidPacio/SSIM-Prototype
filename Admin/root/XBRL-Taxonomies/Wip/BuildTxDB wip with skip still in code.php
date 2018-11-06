<?php /* \Admin\root\XBRL-Taxonomies\BuildTxDB.php

Reads the Taxonomy xsd and xml files and stores the info in the XBRL Taxonomies DB.
Builds SSIM specific Tx based tables and structs.

Uses taxonomy from Data\XBRL\Taxonomies\IFRS-2018\IFRST_2018-03-16\
              from https://www.ifrs.org/issued-standards/ifrs-taxonomy/ifrs-taxonomy-2018/

https://www.ifrs.org/issued-standards/ifrs-taxonomy/#2018
https://www.ifrs.org/issued-standards/ifrs-taxonomy/#browsing
https://www.ifrs.org/-/media/feature/standards/taxonomy/general-resources/ifrs-taxonomy-illustrated-guide.pdf?la=en
https://www.ifrs.org/issued-standards/ifrs-taxonomy/ifrs-taxonomy-illustrated/#illustrated2018
https://www.ifrs.org/-/media/feature/standards/taxonomy/2018/translations/russian/taxonomy-en-r-2018.pdf?la=en1000000
https://www.ifrs.org/issued-standards/ifrs-taxonomy/ifrs-taxonomy-illustrated/#illustrated2018

http://eifrs.ifrs.org/eifrs/taxonomy/guide.pdf

XBRL Specifications: https://specifications.xbrl.org/specifications.html

History:
2018.10.12 Started based on UK-IFRS-DPL version

ToDo djh??
====
Add Schema <annotation><documentation> elements concatenated
Expans LinkBaseRefs to include full info even tho not used

See ///// Functions which are candidates for removal

*/

require '../../inc/BaseTx.inc';
require '../../inc/tx/ConstantsTx.inc';
#equire '../../inc/tx/BuildTxStructs.inc';
require "../../inc/tx/$TxName/BuildTxDB.inc"; # taxonomy specific stuff

Head("Build $TxName DB");

if (!isset($_POST['Sure']) || strtolower($_POST['Sure']) != 'yes') {
  echo <<< FORM
<h2 class=c>Build the $TxName XBRL Taxonomy DB Entries</h2>
<p class=c>Running this will delete all $TxName DB data and then rebuild it from the XML Taxonomy. The process will take tens of seconds to minutes.<br>Once started, the process should be allowed to run to completion.</p>
<form method=post>
<div class=c>Sure? (Enter Yes if you are.) <input name=Sure size=3 value=Yes> <button class=on>Go</button></div>
</form>
FORM;
  $CentredB = true;
  Footer(false);
  exit;
}

#<p class=c><input class=radio type=checkbox name=Skip value=0> Skip the list of elements not needed for $TxName, otherwise build the full $TxName.</p>
# $SkipB = isset($_POST['Skip']);
# if (!$SkipB) $TxElementsSkippedA = null; # so can just test on $TxElementsSkippedA

set_time_limit(60);
$RolesAA    = # [Role    => [Id, usedOn, definition, ElId, FileId, Uses]]
$ArcRolesAA = # [Arcsrcrole => [Id, usedOn, definition, cyclesAllowed, FileId, Uses]]
$NsMapA    =     # [namespace => [NsId, Prefix, File, Num]
$NamesMapA =     # [NsId.name =>  ElId]
$XidsMapA  =     # [xid   => ElId]  Not written to a table
$DocMapA   =     # [Label => [ElId, ArcId, DocId]] where label is the To of Arcs and the Label of Labels and references; ElId is the FromId of the Arc.
$TextMapA  =     # [Text  => [TextId, Uses]]
$TuplesA   = []; # [tupleName => complexContent for the tuple element in $complexA]

$XR = new XMLReader();

$tablesA = [
  'Arcroles',
  'Arcs',
  'AttributeGroups',
  'Attributes',
  'Doc',
  'Elements',
  'LinkbaseRefs',
  'Labels',
  'Namespaces',
  'References',
  'SimpleTypes',
  'ComplexTypes',
  'Roles',
  'Schemas',
  'Text',
  'Unions',
  'Imports',
#  'Hypercubes',
#  'Dimensions',
#  'DimensionMembers',
#  'TuplePairs'
];

$RefsA = [
  'Name' => 0,              # ref:Name
  'Number' => 0,            # ref:Number
  'Paragraph' => 0,         # ref:Paragraph
  'Subparagraph' => 0,      # ref:Subparagraph
  'Section' => 0,           # ref:Section
  'Subsection' => 0,        # ref:Subsection
  'Clause' => 0,            # ref:Clause
  'Appendix' => 0,          # ref:Appendix
  'Publisher' => 0,         # ref:Publisher
  'IssueDate' => 0,         # ref:IssueDate  uk-ifrs
  'Example' => 0,           # ref:Example    uk-ifrs
  'Schedule' => '',         # uk-gaap-ref:Schedule
 #'Part' => '',             # uk-gaap-ref:Part
  'Abstract' => '',         # uk-gaap-ref:Abstract
  'Year' => '',             # uk-gaap-ref:Year
  'ISOName' => 0,           # uk-cd-ref:ISOName
  'Code' => 0,              # uk-cd-ref:Code
  'AlternativeCode' => 0,   # uk-cd-ref:AlternativeCode
  'Date' => 0,              # uk-cd-ref:Date
  'Description' => 0,       # uk-cd-ref:Description
  'ExchangeAcronym' => 0,   # uk-cd-ref:ExchangeAcronym
  'HomeCity' => 0,          # uk-cd-ref:HomeCity
  'HomeCountry' => 0,       # uk-cd-ref:HomeCountry
  'HomeCountryCode' => 0,   # uk-cd-ref:HomeCountryCode
  'MarketIdentificationCode' # uk-cd-ref:MarketIdentificationCode
  => 0];

echo '<h2 class=c>Building the '.($SkipB? "$TxName less unwanted Elements" : "Full $TxName").' Database</h2>
<b>Truncating DB tables</b><br>
';
foreach ($tablesA as $table) {
  echo $table, '<br>';
  $DB->StQuery("Truncate Table `$table`");
}

# Start with $EntryPountUrl set in the BuildTxDB.inc include

# The B code defined $roleA and $arcrolesA here. Might need to bring the equivalent back?

$DB->autocommit(false);
###########
# Schemas #
###########
echo '<br><b>Importing Schemas</b><br>';
$TotalNodes = $FileId = 0;
Schema($EntryPointUrl);

##########
# Tuples # Now the Tuples table
##########
Dump('TuplesMap',$TuplesA);
$tupId = 0;
foreach ($TuplesA as $tuple => $complexA) { # array NsId.tupleName => complexContent for the tuple element in $complexA
++$tupId;
  if (!isset($NamesMapA[$tuple])) DieNow("Tuple $tuple not in NamesMapA as expected");
  $tupTxId = $NamesMapA[$tuple];
  # if ($TxElementsSkippedA && in_array($tupTxId, $TxElementsSkippedA)) continue; # Skip adding the tuple
  #$elsA = []; # in order to insert with the element Ids in ascending order. The tupleIds are sorted as they come but not the elements.
                    # Sorting by member TxId removed with addition of Ordr - leave sorted by that
  $order = 1;
  foreach ($complexA as $nodeA) {
    if ($nodeA['tag'] == 'element') {
      $attributesA = $nodeA['attributes'];
      $ref       = $attributesA['ref']; # uk-gaap:DescriptionChangeInAccountingPolicyItsImpact, uk-direp:ExercisePriceOption
      $minOccurs = $attributesA['minOccurs']; # 0 or 1           Only ever have 0,1         => O
      $maxOccurs = $attributesA['maxOccurs']; # 1 or unbounded                  1,1            M
      if ($maxOccurs == 'unbounded') { #                                        1,unbounded    U
        $maxOccurs = 255;
        $TUCN = TUC_U; # 3 U Optional Unbounded corresponding to Taxonomy minOccurs=0 and maxOccurs=unbounded
      }else{
        if ($minOccurs)
          $TUCN = TUC_M; # 2 M Mandatory once if tuple used corresponding to Taxonomy minOccurs=1 and maxOccurs=1
        else
          $TUCN = TUC_O; # 1 O, Optional once corresponding to Taxonomy minOccurs=0 and maxOccurs=1
      }
     #$elName = substr($ref, strpos($ref, ':')+1);  # after uk-gaap:, uk-direp:
      $elNameSegsA = explode(':', $ref); # ns and name
      $nsId=0;
      foreach ($NsMapA as $nsA)
        if ($nsA['Prefix'] == $elNameSegsA[0]) {
          $nsId = $nsA['NsId'];
          break;
        }
      if (!$nsId) DieNow("No namespace found for Tuple member ref $ref");
      $elName = "$nsId.$elNameSegsA[1]";
      if (!isset($NamesMapA[$elName])) DieNow("Tuple $tuple not in NamesMapA as expected");
      $memTxId = $NamesMapA[$elName];
      # if ($TxElementsSkippedA && in_array($memTxId, $TxElementsSkippedA)) continue; # Skip adding the member
      $DB->StQuery("Insert TuplePairs Set TupId=$tupId,TupTxId=$tupTxId,MemTxId=$memTxId,Ordr=$order,minOccurs=$minOccurs,maxOccurs=$maxOccurs,TUCN=$TUCN");
      #$elsA[$memTxId] = array($order, $minOccurs, $maxOccurs);
      ++$order;
    }
  }
  #ksort($elsA);
  #foreach ($elsA as $memTxId => $propsA)
  #  $DB->StQuery("Insert Tuples Set TupTxId=$tupTxId,MemTxId=$memTxId,Ordr=$propsA[0],minOccurs=$propsA[1],maxOccurs=$propsA[2]");
}
/*
#############
# Linkbases # http://www.datypic.com/sc/xbrl21/e-link_linkbase.html
#############
link:linkbase
     Definition of the linkbase element.  Used to
     contain a set of zero or more extended link elements.

Element information
Namespace: http://www.xbrl.org/2003/linkbase

Schema document: xbrl-linkbase-2003-12-31.xsd
Type: Anonymous

Properties: Global, Qualified
Content

    Choice [0..*]
        link:documentation     Concrete element to use for documentation of extended links and linkbases.
        link:roleRef           Definition of the roleRef element - used to link to resolve xlink:role attribute values to the roleType element declaration.
        link:arcroleRef        Definition of the roleRef element - used to link to resolve xlink:arcrole attribute values to the arcroleType element declaration.
        from subst. group xl:extended
        link:presentationLink   presentation extended link element definition.
        link:definitionLink     definition extended link element definition
        link:calculationLink    calculation extended link element definition
        link:labelLink          label extended link element definition
        link:referenceLink      reference extended link element definition
        link:footnoteLink       footnote extended link element definition

Attributes
Name  Occ Type  Description Notes
id  [0..1]  xsd:ID
Any attribute [0..*]    Namespace: http://www.w3.org/XML/1998/namespace, Process Contents: lax
Sample instance

<link:linkbase>
   <link:documentation>string</link:documentation>
</link:linkbase>

Plus http://www.xbrl.org/Specification/gnl/REC-2009-06-22/gnl-REC-2009-06-22.html
re gen:link
in \IFRS-2018\IFRST_2018-03-16\ifrs_for_smes\dimensions\gla_ifrs_for_smes-dim_2018-03-16-en.xml
<link:linkbase xmlns:gen="http://xbrl.org/2008/generic" xmlns:label="http://xbrl.org/2008/label" xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.xbrl.org/2003/linkbase http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd http://xbrl.org/2008/label http://www.xbrl.org/2008/generic-label.xsd http://xbrl.org/2008/generic http://www.xbrl.org/2008/generic-link.xsd http://www.w3.org/1999/xlink http://www.xbrl.org/2003/xlink-2003-12-31.xsd">
  <link:roleRef roleURI="http://www.xbrl.org/2008/role/link" xlink:href="http://www.xbrl.org/2008/generic-link.xsd#standard-link-role" xlink:type="simple"/>
  <link:roleRef roleURI="http://www.xbrl.org/2008/role/label" xlink:href="http://www.xbrl.org/2008/generic-label.xsd#standard-label" xlink:type="simple"/>
  <link:arcroleRef arcroleURI="http://xbrl.org/arcrole/2008/element-label" xlink:href="http://www.xbrl.org/2008/generic-label.xsd#element-label" xlink:type="simple"/>
  <gen:link xlink:role="http://www.xbrl.org/2008/role/link" xlink:type="extended">
    <label:label xlink:label="res_1" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[913000] Axis - Consolidated, combined and separate financial statements</label:label>
    <link:loc xlink:href="rol_ifrs_for_smes-dim_2018-03-16.xsd#ifrs_for_smes-dim_role-913000" xlink:label="loc_1" xlink:type="locator"/>
    <gen:arc xlink:arcrole="http://xbrl.org/arcrole/2008/element-label" xlink:from="loc_1" xlink:to="res_1" xlink:type="arc"/>
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

*/
echo '<br><b>Importing Linkbases</b><br>';
$res = $DB->ResQuery("Select Id,href From LinkbaseRefs");
while ($o = $res->fetch_object()) {
  $LinkbaseId = (int)$o->Id;
  $FileId = $LinkbaseId + 100; # for namespaces table
  $File   = $o->href;
  $NodeX  = -1;
  $NumNodes = ReadNodes($File);
  $TotalNodes += $NumNodes;
  echo "LinkbaseId $LinkbaseId, FileId $FileId, File '$File', $NumNodes nodes read -> Total nodes = ", number_format($TotalNodes), '<br>';
  flush();
  $node = $NodesA[++$NodeX]; # linkbase node
  DumpExport("Node $NodeX", $node);
  # No insert for linkbase itself as already have location info in LinkbaseRefs
  # Just update the namespaces. (Info only as no new ones.)
  foreach ($node['attributes'] as $a => $v) {
    if (strpos($a, 'xmlns') === 0)
      AddNamespace($a, $v);
    else if ($a == 'xsi:schemaLocation') {
      # space separated namespace | xsd, either once or multiple times
      $A = explode(' ', trim($v));
      $n = count($A);
      #Dump("xsi:schemaLocation $n", $A);
      for ($i=0; $i < $n; ) {
        AddNamespace('', $A[$i++]);
        $loc = FileAdjustRelative($A[$i++]);
        $set = "Location='$loc'";
        InsertOrUpdate('Imports', 'Location', $loc, $set);
      }
    }else
      DieNow("Unknown <linkbase attribute $a");
  }
  while (++$NodeX < $NumNodes) {
    $node = $NodesA[$NodeX];
    DumpExport("Node $NodeX", $node);
   #$tag = str_replace('link:', '', $node['tag']); # strip leading link: if present
    $tag = $node['tag'];
    switch ($tag) {
      case 'link:documentation': echo 'link:documentation <==========<br>'; break; # Ignored. There is only 1 of these: <documentation>Entity Information</documentation>   djh?? add back
                                      # in uk-gaap-2009-09-01/cd/business/2009-09-01/uk-bus-2009-09-01-presentation.xml
      case 'link:roleRef':       echo 'link:roleRef<br>'; break;  # RoleRef();     break; # Skipped as of 31.03.11 djh?? add back
      case 'link:arcroleRef':    echo 'link:arcroleRef<br>';  break;  # ArcroleRef();  break; # Skipped as of 31.03.11
      case 'link:presentationLink': XLink(TLT_Presentation); break; # plus <loc and <presentationArc  (link:documentation is not used by UK GAAP)
      case 'link:definitionLink':   XLink(TLT_Definition);   break; # plus <loc and <definitionArc
      case 'link:calculationLink':  XLink(TLT_Calculation);  break;
      case 'link:labelLink':        XLink(TLT_Label);        break; # plus <loc and <labelArc
      case 'link:referenceLink':    XLink(TLT_Reference);    break; # plus <loc and <referenceArc
     #case 'link:footnoteLink':     footnote extended link element definition
      case 'gen:link':              XLink(TLT_GenLink);      break;
      default: DieNow("unknown linkbase tag $tag<br>");
    }
  }
}
$res->free();


echo "<br>Elements and Arcs inserted.<br>";

# Insert the Roles
# $RolesAA [Role => [Id, usedOn, definition, ElId, FileId, Uses]]
foreach ($RolesAA as $role => $roleA) {
  $id = $DB->InsertQuery("Insert Roles Set Role='$role',usedOn='$roleA[usedOn]',definition='$roleA[definition]',ElId=$roleA[ElId],FileId=$roleA[FileId],Uses=$roleA[Uses]");
  if ($id != $roleA['Id']) DieNow("Id $id on Role Insert not $roleA[Id] as expected");
}

# Insert the Arcroles
# $ArcRolesAA [Arcrole => [Id, usedOn, definition, cyclesAllowed, FileId, Uses]]
foreach ($ArcRolesAA as $arcrole => $arcroleA) {
  $set = "Arcrole='$arcrole',FileId=$arcroleA[FileId],Uses=$arcroleA[Uses]";
  if ($arcroleA['usedOn'])        $set .= ",usedOn='$arcroleA[usedOn]'";
  if ($arcroleA['definition'])    $set .= ",definition='$arcroleA[definition]'";
  if ($arcroleA['cyclesAllowed']) $set .= ",cyclesAllowed='$arcroleA[cyclesAllowed]'";
  $id = $DB->InsertQuery("Insert Arcroles Set $set");
  if ($id != $arcroleA['Id']) DieNow("Id $id on Arcrole Insert not $arcroleA[Id] as expected");
}

# Insert the Namespaces
# $NsMapA [namespace => [NsId, Prefix, File, Num]
foreach ($NsMapA as $ns => $nsA) {
  $id = $DB->InsertQuery("Insert Namespaces Set namespace='$ns',Prefix='$nsA[Prefix]',File='$nsA[File]',Num=$nsA[Num]");
  if ($id != $nsA['NsId']) DieNow("Id $id on Namespaces Insert not $nsA[NsId] as expected");
}

# Insert Text
foreach ($TextMapA as $text => $textA) { # array Text => [TextId, Uses]
  $id = $DB->InsertQuery("Insert Text Set Text='$text',Uses=$textA[Uses]");
  if ($id != $textA['TextId']) DieNow("Id $id on Text Insert not $textA[TextId] as expected");
}

# Update the Arc Label and Reference ToIds
# $DocMapA [Label => [ElId, ArcId, DocId]]
foreach ($DocMapA as $docMapA) # [ElId, ArcId, DocId]
  if ($docMapA['ArcId']) # 0 for skipped label/ref arcs that are in $docMapA re skipping the labels/refs
    $DB->StQuery("Update Arcs Set ToId=$docMapA[DocId] Where Id=$docMapA[ArcId]");


echo "<br>Roles, Arcroles, Namespaces, and Text inserted; Arc From and To fields updated.<br>";
$DB->commit();

/////////////////////////
// Post Main Build Ops //
/////////////////////////

# echo '<br><b>Taxonomy Fixups</b><br>';
# echo 'None so far for UK-IFRS<br>';

# See old code for fixups
# See old code re:
# Build Dimension Tables
# ======================
# Build Hypercubes Table
# ======================
# Add Hypercube Lists to Concrete Item Elements
# =============================================

# Build the Taxonomy Based Structs
## BuildTxBasedStructs();

Footer();
#########
#########

# DieNow()
# ======
# Crude error exit - die a sudden death but commit first re viewing progress
function DieNow($msg) {
  global $DB, $NodeX, $NodesA, $File, $FileId, $LinkbaseId;
  $DB->commit();
  if ($NodeX >= 0) {
    if ($LinkbaseId > 0)
      DumpExport("Dying at Node $NodeX in Linkbase $LinkbaseId $File", $NodesA[$NodeX]);
    else
      DumpExport("Dying at Node $NodeX in Schema $FileId $File", $NodesA[$NodeX]);
  }
  die("Die - $msg");
}

######################
## Schema functions ##
######################

function Schema($loc) {
  static $SchemaStackA=[], // stack of schemas for a re-entrant call
         $StackDepth,
         $MaxSchemaId; // running schema Id from 1 upwards - $SchemaId for the current schema can be < $MaxSchemaId after a return from a re-entrant call
  global $DB,
         $NodeX,       // the NodesA index of the node being processed, pre incremented i.e. starts at -1
         $NumNodes,    // number of nodes read from $loc
         $NodesA,      // the nodes read from $loc
         $SchemaId,    // Id of current schema which can be can be < $MaxSchemaId after a return from a re-entrant call
         $SchemaId,    // Schema Id which increases from 1 for each new schema
         $TotalNodes,  // count of total number of nodes read
         $File,        // current schema or linkbase file
         $FileId;      // a running file Id for schemas and linkbases

  if ($SchemaId > 0) {
    # This is a re-entrant call
    # Check to see if this schema has already been processed
    if ($o = $DB->OptObjQuery("Select Id,FileIds From Imports where Location='$loc'")) {
      // Yep
      $DB->StQuery("Update Imports Set Num=Num+1,FileIds='" . $o->FileIds . ',' . $SchemaId . "' Where Id=$o->Id");
      echo "Schema $loc import not performed as it has already been processed<br>";
      return;
    }
    // Stack the previous schema being processed
    array_push($SchemaStackA, [$SchemaId, $File, $NodeX, $NumNodes, $NodesA]);
    $StackDepth++;
    echo "Schema $SchemaId '$File' Node $NodeX stacked, depth $StackDepth<br>";
    #for ($j=0; $j<$StackDepth; $j++) {
    #  echo "Schema Stack $j SchemaId: {$SchemaStackA[$j][0]}<br>";
    #  echo "Schema Stack $j File: {$SchemaStackA[$j][1]}<br>";
    #  echo "Schema Stack $j NodeX: {$SchemaStackA[$j][2]}<br>";
    #  echo "Schema Stack $j NumNodes: {$SchemaStackA[$j][3]}<br>";
    #}
  }
  # Not already processed
  $SchemaId =
  $FileId   = ++$MaxSchemaId; // only ever increases
  $File = $loc;
  $NodeX  = -1;
  $NumNodes = ReadNodes($loc);
  $TotalNodes += $NumNodes;
  echo "Schema $SchemaId: $loc, $NumNodes nodes read -> Total nodes = ", number_format($TotalNodes), '<br>';
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
      continue;                # 'xmlns:uk-gaap-all' => 'http://www.xbrl.org/uk/gaap/core-all',
    }
    if (!strlen($v)) {
      echo "Ignoring empty attribute '$a' in NodeX $NodeX in Schema $File <br>";
      continue;
    }
    $set .= ",$a='$v'";
  }
  if ($SchemaId != Insert('Schemas', $set)) DieNow("SchemaId $SchemaId != insert Id");
  while (++$NodeX < $NumNodes) {
    # DumpExport("Node $NodeX", $NodesA[$NodeX]);
    switch (LessPrefix($NodesA[$NodeX]['tag'])) {
      case 'annotation':
        while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > 1) { # <annotation has a depth of 1
          $NodeX++;
          # DumpExport("Node $NodeX", $NodesA[$NodeX]);
          switch (LessPrefix($NodesA[$NodeX]['tag'])) {
            case 'appinfo':
              while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > 2) { # <appinfo has a depth of 2
                $node = $NodesA[++$NodeX];
                #DumpExport("Node $NodeX", $node);
                switch (LessPrefix($node['tag'])) {
                  case 'linkbaseRef': LinkbaseRef($node); break;
                  case 'roleType':    RoleType();    break;
                  case 'arcroleType': ArcroleType(); break;
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
        $node = $NodesA[$NodeX];
        $ns  = $node['attributes']['namespace'];
        $loc = $node['attributes']['schemaLocation'];
        AddNamespace('', $ns);
        Schema(FileAdjustRelative($loc));
        break;
      case 'simpleType':     SimpleType();   break;
      default: DieNow("unknown schema tag {$NodesA[$NodeX]['tag']}<br>");
    }
  }
  // Finished this Schema
  echo "Finished Schema $SchemaId '$File'<br>";
  if ($StackDepth > 0) {
    // Had nodes stacked via [$SchemaId, $File, $NodeX, $NumNodes, $NodesA];
    #for ($j=0; $j<$StackDepth; $j++) {
    #  echo "Schema Stack $j SchemaId: {$SchemaStackA[$j][0]}<br>";
    #  echo "Schema Stack $j File: {$SchemaStackA[$j][1]}<br>";
    #  echo "Schema Stack $j NodeX: {$SchemaStackA[$j][2]}<br>";
    #  echo "Schema Stack $j NumNodes: {$SchemaStackA[$j][3]}<br>";
    #}
    $StackDepth--;
    list($SchemaId, $File, $NodeX, $NumNodes, $NodesA) = array_pop($SchemaStackA);
    echo "Back to Schema $SchemaId '$File', Node $NodeX<br>";
    $FileId = $SchemaId;
  }
} // End Schema()

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

# <element id="xml-gen-arc" name="arc" substitutionGroup="xl:arc" type="gen:genericArcType"/>
function Element($nsId) {
  global $NodesA, $NumNodes, $NodeX, $XidsMapA, $NamesMapA; #, $TxElementsSkippedA;
  static $ElIdS=0; # re skipping elements and preserving full build element Ids
  ++$ElIdS;
  $node = $NodesA[$NodeX];
  $depth = $node['depth'];
  $set = "Id=$ElIdS,NsId='$nsId'";
  $name = $xid = $SubstGroupN = $tuple = 0; # $tuple is set to '$nsId.$name' for the tuple case for passing to ComplexType()
  foreach ($node['attributes'] as $a => $v) {
    switch ($a) {
      case 'id':   $xid =$v; continue 2; # SetIdDef($xid=$v, $set); continue 2; # IdId
      case 'name': $name=$v; continue 2;
      case 'type':
        $a = 'TypeN';
        switch ($v) {
          case 'gen:genericArcType':        $v = 171; break;          # djh?? allocate a constant
          case 'gen:linkTypeWithOpenAttrs': $v = 172; break;          # djh?? allocate a constant
          case 'nonnum:domainItemType':     $v = 173; break;          # djh?? allocate a constant
          case 'num:percentItemType':       $v = 174; break;          # djh?? allocate a constant
          case 'nonnum:textBlockItemType':  $v = 175; break;          # djh?? allocate a constant
          case 'num:areaItemType':          $v = 176; break;          # djh?? allocate a constant
          case 'num:perShareItemType':      $v = 177; break;          # djh?? allocate a constant
          case 'xbrli:pureItemType':        $v = 178; break;          # djh?? allocate a constant
          # djh?? Check all of the ones below
          case 'xbrli:monetaryItemType':    $v = TET_Money; break;
          case 'string':
          case 'xbrli:stringItemType':      $v = TET_String; break;
         #case 'xbrli:booleanItemType':     $v = TET_Boolean;break;
          case 'xbrli:dateItemType':        $v = TET_Date;   break;
          case 'decimal':
          case 'xbrli:decimalItemType':     $v = TET_Decimal; break;
         #case 'xbrli:integerItemType':     $v = TET_Integer; break;
          case 'xbrli:nonZeroDecimal':      $v = TET_NonZeroDecimal; break;
          case 'xbrli:sharesItemType':      $v = TET_Share; break;
         #case 'anyURI':
         #case 'xbrli:anyURIItemType':      $v = TET_Uri;    break;
         #case 'uk-types:domainItemType':   $v = TET_Domain; break;
         #case 'uk-types:entityAccountsTypeItemType': $v = TET_EntityAccounts; break;
         #case 'uk-types:entityFormItemType':  $v = TET_EntityForm;  break;
         #case 'uk-types:fixedItemType':       $v = TET_Fixed;       break;
         #case 'uk-types:percentItemType':     $v = TET_Percent;     break;
         #case 'uk-types:perShareItemType':    $v = TET_PerShare;    break;
         #case 'uk-types:reportPeriodItemType':$v = TET_ReportPeriod;break;
          case 'anyType':             $v = TET_Any;   break;
          case 'QName':               $v = TET_QName; break;
          case 'xl:arcType':          $v = TET_Arc;   break;
          case 'xl:documentationType':$v = TET_Doc;   break;
          case 'xl:extendedType':     $v = TET_Extended; break;
          case 'xl:locatorType':      $v = TET_Locator;  break;
          case 'xl:resourceType':     $v = TET_Resource; break;
          case 'anySimpleType':
          case 'xl:simpleType':       $v = TET_Simple; break;
          case 'xl:titleType':        $v = TET_Title;  break;
          # UK-IFRS
         #case 'uk-ifrs:investmentPropertyMeasurementItemType': $v = TET_investmentPropertyMeasurement; break;
          default: DieNow("unknown element type $v");
        }
        break;
      case 'substitutionGroup':
        $a = 'SubstGroupN';
        switch ($v) {
          case 'xbrli:item'          : $v = TSG_Item;     break;
          case 'xbrli:tuple'         : $v = TSG_Tuple; $tuple="$nsId.$name"; break;
          case 'xbrldt:dimensionItem': $v = TSG_Dimension;break;
          case 'xbrldt:hypercubeItem': $v = TSG_Hypercube;break;
          case 'link:part'           : $v = TSG_LinkPart; break;
          case 'xl:arc'              : $v = TSG_Arc;      break;
          case 'xl:documentation'    : $v = TSG_Doc;      break;
          case 'xl:extended'         : $v = TSG_Extended; break;
          case 'xl:locator'          : $v = TSG_Locator;  break;
          case 'xl:resource'         : $v = TSG_Resource; break;
          case 'xl:simple'           : $v = TSG_Simple;   break;
          default: DieNow("unknown element substitutionGroup $v");
        }
        $SubstGroupN = $v;
        break;
      case 'xbrli:periodType':
        $a = 'PeriodN';
        switch ($v) {
          case 'instant':  $v = TPT_Instant;  break;
          case 'duration': $v = TPT_Duration; break;
          default: DieNow("unknown element periodType $v");
        }
        break;
      case 'xbrli:balance':
        $a = 'SignN';
        switch ($v) {
          case 'debit':  $v = TS_Dr; break;
          case 'credit': $v = TS_Cr; break;
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
      default:
        DieNow("unknown attribute $a");
    }
    $set .= ",$a='$v'";
  }
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > $depth) {
    switch ($NodesA[++$NodeX]['tag']) {
      case 'annotation':    break; # / - skip as spec says not required to show doc other than via labels
      case 'documentation': break; # |
      case 'complexType':   ComplexType($tuple); break; # $set .= (',ComplexTypeId=' . ComplexType()); break;
      case 'simpleType':    SimpleType();  break; # $set .= (',SimpleTypeId='  . SimpleType());  break;
      default: DieNow("unknown element tag {$NodesA[$NodeX]['tag']}");
    }
  }

  if (!$SubstGroupN || $SubstGroupN>=TSG_LinkPart) return; # 10.10.12 Taken out of build as not needed
  /*const TSG_LinkPart  = 5; # link:part            56  /- Removed from DB build 10.10.12
    const TSG_Arc       = 6; # xl:arc                6  |
    const TSG_Doc       = 7; # xl:documentation      1  |
    const TSG_Extended  = 8; # xl:extended           6  |
    const TSG_Locator   = 9; # xl:locator            1  |
    const TSG_Resource  =10; # xl:resource           3  |
    const TSG_Simple    =11; # xl:simple             4  | */

  # $NamesMapA [NsId.name => ElId]
  if (!$name) DieNow('no name for element');
  $nsname = "$nsId.$name";
  if (isset($NamesMapA[$nsname])) DieNow("Duplicate NsId.name $nsname");
  $NamesMapA[$nsname] = $ElIdS;
  if ($xid)  $XidsMapA[$xid] = $ElIdS;

  #if ($TxElementsSkippedA && in_array($ElIdS, $TxElementsSkippedA)) return; # Skip adding the element

  $set .= ",name='$name'";
  InsertFromSchema('Elements', $set);
} // End Element

function LinkbaseRef($node) {
  if (@$node['attributes']['xlink:type'] != 'simple')    DieNow('LinkbaseRef type not simple');
  if (@$node['attributes']['xlink:arcrole'] != 'http://www.w3.org/1999/xlink/properties/linkbase') DieNow('LinkbaseRef arcrole not http://www.w3.org/1999/xlink/properties/linkbase');
  $set = '';
  foreach ($node['attributes'] as $a => $v) {
    $a = str_replace('xlink:', '', $a); # strip xlink: prefix
    switch ($a) {
      case 'type':             # skip as always simple
      case 'arcrole':          # skip as always http://www.w3.org/1999/xlink/properties/linkbase
      case 'role': continue 2; # skip as doesn't provide any useful info, just presentationLinkbaseRef etc which we don't need
      case 'href':  $v = FileAdjustRelative($v); break;
      case 'title': $v = addslashes($v); break;
      default: DieNow("unknown linkbaseref attribute $a");
    }
    $set .= ",$a='$v'";
  }
  InsertFromSchema('LinkbaseRefs', $set);
}

/*
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
function RoleType() {
  global $NodesA, $NumNodes, $NodeX;
  $node = $NodesA[$NodeX];
  if (!@$roleURI=$node['attributes']['roleURI']) DieNow('roleType roleURI missing');
  if (!@$id=$node['attributes']['id'])           DieNow('roleType id missing');
  # Now expect
  #  <link:definition>10 - Profit and Loss Account</link:definition> -- optional
  #  <link:usedOn>link:presentationLink</link:usedOn>
  $node = $NodesA[++$NodeX];
  if ($node['tag'] == 'link:definition') {
    $definition = addslashes($node['txt']);
    $node = $NodesA[++$NodeX];
  }else
    $definition = 0;
  if ($node['tag'] != 'link:usedOn')     DieNow("{$NodesA[$NodeX]['tag']} tag found rather than expected link:usedOn");
  $usedOn = str_replace('link:', '', $node['txt']); # strip link: prefix
  # Can have more link:usedOn's as above...
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['tag'] == 'link:usedOn') {
    $NodeX++;
    $usedOn .= ',' . str_replace('link:', '', $NodesA[$NodeX]['txt']); # CS the extra one(s)
  }
  UpdateRole($roleURI, $usedOn, $definition);
}

# Called from Schema

function ArcroleType() {
  global $NodesA, $NumNodes, $NodeX;
  $node = $NodesA[$NodeX];
  if (!@$arcroleURI=$node['attributes']['arcroleURI'])       DieNow('arcroleType arcroleURI missing');
  if (!@$id=$node['attributes']['id'])                       DieNow('arcroleType id missing');
  if (!@$cyclesAllowed=$node['attributes']['cyclesAllowed']) DieNow('arcroleType cyclesAllowed missing');
  # Now expect
  #  <definition></definition>
  #  <usedOn>definitionArc</usedOn>
  $node = $NodesA[++$NodeX];
  if (LessPrefix($node['tag']) != 'definition')      DieNow("{$node['tag']} tag found rather than definition");
  $definition = addslashes($node['txt']);
  $node = $NodesA[++$NodeX];
  if (LessPrefix($node['tag']) != 'usedOn')          DieNow("{$node['tag']} tag found rather than expected usedOn");
  $usedOn = $node['txt'];
  UpdateArcrole($arcroleURI, $usedOn, $definition, $cyclesAllowed);
}

########################
## Linkbase functions ##
########################

/* Removed as of 31.03.11. See Wip 8 if required again
function RoleRef() {
function ArcroleRef() {
*/

#   case 'link:presentationLink': XLink(TLT_Presentation); break; # plus <loc and <presentationArc  (link:documentation is not used by UK GAAP)
#   case 'link:definitionLink':   XLink(TLT_Definition);   break; # plus <loc and <definitionArc
#   case 'link:calculationLink':  XLink(TLT_Calculation);  break;
#   case 'link:labelLink':        XLink(TLT_Label);        break; # plus <loc and <labelArc
#   case 'link:referenceLink':    XLink(TLT_Reference);    break; # plus <loc and <referenceArc
#  #case 'link:footnoteLink':     footnote extended link element definition
#   case 'gen:link':              XLink(TLT_GenLink);      break;

# For presentationLink, definitionLink, calculationLink labelLink, referenceLink, gen:link
#     TLT_Presentation  TLT_Definition  TLT_Calculation TLT_Label  TLT_Reference  TLT_GenLink
function XLink($typeN) {
  global $NodesA, $NumNodes, $NodeX, $LocLabelToIdA;
  $LocLabelToIdA = [];
  $node = $NodesA[$NodeX];
  if (@$node['attributes']['xlink:type'] != 'extended') DieNow('...link type not extended');
  if (!($role = @$node['attributes']['xlink:role']))    DieNow('...link xlink:role attribute not set');
  $roleId = UpdateRole($role, $node['tag']);
  $depth1 = $node['depth']+1;
  # For Label and Resource arcs need to make sure the Arcs are processed first re the Label() and Reference() use of $DocMapA info.
  # Arcs came first in GAAP but not DPL.
  # So just do loc and Arcs first, which is everything for Presentation and Definition Arcs.
#  $startNodeX = $NodeX;
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] === $depth1) {
    $node = $NodesA[++$NodeX];
   #$tag = str_replace('link:', '', $NodesA[$NodeX]['tag']); # strip leading link: if present
   #$tag = LessPrefix($NodesA[$NodeX]['tag']); # strip leading link: or label: in the gen:link case
    $tag = $node['tag'];
    switch ($tag) {
      case 'link:loc':
        # Locator
        if ($node['attributes']['xlink:type'] != 'locator') DieNow('loc type not locator');
        if (!$href = $node['attributes']['xlink:href'])     DieNow('loc xlink:href attribute not set');
        if (!$label = $node['attributes']['xlink:label'])   DieNow('loc xlink:label attribute not set');
        if (!$p = strpos($href, '#'))                       DieNow("No # in locator href $href");
        # The #... of the href is the id of the element with the href part up to the # being the xsd that defines the element
        # For UK-GAAP the id was always the same as the locator label as used by Arcs and so the from and to of Arcs could be used directly as ids.
        # Not necessarily so for UK-IFRS (or in general) so need to go Arc From -> Locator Label -> Element Id
        $LocLabelToIdA[$label] = substr($href, $p+1); # Locator Label = Element Id
        break;
      case 'link:presentationArc':
      case 'link:definitionArc':
      case 'link:labelArc':
      case 'link:referenceArc':
      case 'gen:arc':
        Arc($typeN, $roleId);
        break;
      case 'link:label':
      case 'label:label':
        Label($node);
        break;
      case 'link:reference':
      case 'reference:reference':
        Reference();
        break;
        # # step over the ref:Name etc tags
        # for (++$NodeX; ($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] == $depth1+1; ++$NodeX)
        #   ;
        break;
      default: DieNow("unknown xlink tag $tag<br>");
    }
  }
  #if ($typeN == TLT_Label || $typeN == TLT_Reference) {
  #  # Now the Labels and References
  #  $NodeX = $startNodeX;
  #  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] === $depth1) {
  #    $node = $NodesA[++$NodeX];
  #    switch ($node['tag']) {
  #      case 'link:label':
  #      case 'label:label':
  #        Label($node); break;
  #      case 'link:reference':
  #      case 'reference:reference':
  #     #case 'reference':
  #        Reference(); break;
  #      default: DieNow("unknown xlink tag $tag in the Labels and References loop<br>");
  #    }
  #  }
  #}
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

/*
3.5.3.9  Arcs  http://www.xbrl.org/Specification/XBRL-2.1/REC-2003-12-31/XBRL-2.1-REC-2003-12-31+corrected-errata-2013-02-20.html#_3.5.3.9

All XBRL extended links MAY contain arcs. Arcs document relationships between resources identified by locators in extended links
or occurring as resources in extended links.

Arcs represent relationships between the XML fragments referenced by their [XLINK] attributes: xlink:from and xlink:to. The xlink:from
and the xlink:to attributes represent each side of the arc.  These two attributes contain the xlink:label attribute values of locators
and resources within the same extended link as the arc itself.  For a locator, the referenced XML fragments comprise the set of XML
elements identified by the xlink:href attribute on the locator. For a resource, the referenced XML fragment is the resource element itself.

An arc MAY reference multiple XML fragments on each side (“from” and “to”) of the arc. This can occur if there are multiple locators and/or
resources in the extended link with the same xlink:label attribute value identified by the xlink:from or xlink:to attribute of the arc.
Such arcs represent a set of one-to-one relationships between each of the XML fragments on their “from” side and each of the XML fragments
on their “to” side.

Example 2. One-to-One arc relationships [XLINK] [Simplified with unnec stuff removed]
---------------------
This presentation link contains an arc that relates one XBRL concept to one other XBRL concept.
The XML fragment on the “from” side is the conceptA element definition, found in the example.xsd taxonomy schema.
The XML fragment on the “to” side is the conceptB element definition, also found in the example.xsd taxonomy schema.

<presentationLink  role="link">
  <loc type="locator" label="a" href="example.xsd#conceptA"/>
  <loc type="locator" label="b" href="example.xsd#conceptB"/>
  <presentationArc from="a" to="b" arcrole="parent-child" order="1"/>
</presentationLink>

<gen:link role="link">
  <label:label xlink:label="res_1" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[913000] Axis - Consolidated, combined and separate financial statements</label:label>
  <link:loc xlink:href="rol_ifrs_for_smes-dim_2018-03-16.xsd#ifrs_for_smes-dim_role-913000" xlink:label="loc_1" xlink:type="locator"/>
  <gen:arc from="loc_1" to="res_1" xlink:type="arc"/>
*/
/*
0<link:linkbase xmlns:gen="http://xbrl.org/2008/generic" xmlns:label="http://xbrl.org/2008/label" xmlns:link="http://www.xbrl.org/2003/linkbase" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.xbrl.org/2003/linkbase http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd http://xbrl.org/2008/label http://www.xbrl.org/2008/generic-label.xsd http://xbrl.org/2008/generic http://www.xbrl.org/2008/generic-link.xsd http://www.w3.org/1999/xlink http://www.xbrl.org/2003/xlink-2003-12-31.xsd">
1  <link:roleRef roleURI="http://www.xbrl.org/2008/role/link" xlink:href="http://www.xbrl.org/2008/generic-link.xsd#standard-link-role" xlink:type="simple"/>
2  <link:roleRef roleURI="http://www.xbrl.org/2008/role/label" xlink:href="http://www.xbrl.org/2008/generic-label.xsd#standard-label" xlink:type="simple"/>
3  <link:arcroleRef arcroleURI="http://xbrl.org/arcrole/2008/element-label" xlink:href="http://www.xbrl.org/2008/generic-label.xsd#element-label" xlink:type="simple"/>
4  <gen:link xlink:role="http://www.xbrl.org/2008/role/link" xlink:type="extended">
5    <label:label xlink:label="res_1" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[913000] Axis - Consolidated, combined and separate financial statements</label:label>
6    <link:loc xlink:href="rol_ifrs_for_smes-dim_2018-03-16.xsd#ifrs_for_smes-dim_role-913000" xlink:label="loc_1" xlink:type="locator"/>
7    <gen:arc xlink:arcrole="http://xbrl.org/arcrole/2008/element-label" xlink:from="loc_1" xlink:to="res_1" xlink:type="arc"/>
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

Dying at Node 5 in Linkbase 8 http://xbrl.ifrs.org/taxonomy/2018-03-16/full_ifrs/dimensions/gla_full_ifrs-dim_2018-03-16-en.xml = array (
  'tag' => 'label:label',
  'depth' => 2,
  'attributes' =>
  array (
    'xlink:label' => 'res_1',
    'xlink:role' => 'http://www.xbrl.org/2008/role/label',
    'xlink:type' => 'resource',
    'xml:lang' => 'en',
  ),
  'txt' => '[913000] Axis - Consolidated and separate financial statements',
)
Die - $DocMapA['res_1'] not set in Label()

Dying at Node 7 in Linkbase 8 http://xbrl.ifrs.org/taxonomy/2018-03-16/full_ifrs/dimensions/gla_full_ifrs-dim_2018-03-16-en.xml = array (
  'tag' => 'gen:arc',
  'depth' => 2,
  'attributes' =>
  array (
    'xlink:arcrole' => 'http://xbrl.org/arcrole/2008/element-label',
    'xlink:from' => 'loc_1',  rol_ifrs_for_smes-dim_2018-03-16.xsd#ifrs_for_smes-dim_role-913000
    'xlink:to' => 'res_1',
    'xlink:type' => 'arc',
  ),
Die - $XidsMapA['loc_1']) not set for Arc From=loc_1 -> ElId=ifrs-dim_role-913000
*/

# Called from XLinbk()
# For presentationLink, definitionLink calculationLink labelLink referenceLink gen:link
# ->  presentationArc,  definitionArc  ?               labelArc  referenceArc  gen:arc
function Arc($typeN, $pRoleId) {
  global $NodesA, $NodeX, $LocLabelToIdA, $XidsMapA, $DocMapA; #, $TxElementsSkippedA; # $XidsMapA [XId  => ElId], $DocMapA [Label => [ElId, ArcId, DocId]]
 #global $LinkbaseId;
  global $File; # only for debug DumpExport() call
  $node = $NodesA[$NodeX];
  if ($node['attributes']['xlink:type'] != 'arc')   DieNow('arc type not arc');
  if (!isset($node['attributes']['xlink:from']))    DieNow('arc xlink:from attribute not set');
  if (!isset($node['attributes']['xlink:to']))      DieNow('arc xlink:to attribute not set');
  if (!isset($node['attributes']['xlink:arcrole'])) DieNow('arc xlink:arcrole attribute not set');
  $set = "TypeN='$typeN',PRoleId='$pRoleId'";
  foreach ($node['attributes'] as $a => $v) {
    $a = str_replace('xlink:', '', $a); # strip xlink: prefix
    switch ($a) {
      case 'type': continue 2; # skip
      case 'from':
       #SetElId($v, $set, 'From');
        if (!isset($LocLabelToIdA[$v])) DieNow("Locator label \$LocLabelToIdA['$v']) not set for Arc From=$v");
        $elId = $LocLabelToIdA[$v];
       #if (!isset($XidsMapA[$elId])) DieNow("\$XidsMapA['$v']) not set for Arc From=$v -> ElId=$elId");
        if (!isset($XidsMapA[$elId])) {
          echo "\$XidsMapA['$v']) not set for Arc From=$v -> ElId=$elId; fromId set to -1 <===========<br>";
          DumpExport("Node $NodeX in $File", $node);
          $fromId=-1;
        }else
          $fromId = $XidsMapA[$elId];
        $set .= ",FromId=$fromId";
        continue 2;
      case 'to':
        switch ($typeN) {
          case TLT_Presentation:
          case TLT_Definition:#  SetElId($v, $set, 'To'); continue 3; # Expect 'to' to be a concept (element)
            if (!isset($LocLabelToIdA[$v])) DieNow("Locator label \$LocLabelToIdA['$v']) not set for Arc To=$v");
            $elId = $LocLabelToIdA[$v];
            if (!isset($XidsMapA[$elId])) DieNow("\$XidsMapA['$v']) not set for Arc To=$v -> ElId=$elId");
            $toId = $XidsMapA[$elId];
            $set .= ",ToId=$toId";
            continue 3;
          case TLT_Label:
          case TLT_Reference:
          case TLT_GenLink: # djh?? Correct?
            $toLabel = $v; continue 3; # forward name use so have to resolve later # SetNameUse($v, $set); continue 3; # All label and references tos are name use
        }
        DieNow("typeN $typeN unknown in Arc()");
      case 'arcrole':           $a = 'ArcroleId';       $v = UpdateArcrole($v); break;
      case 'preferredLabel':    $a = 'PrefLabelRoleId'; $v = UpdateRole($v);    break; #, $node['tag']);
      case 'xbrldt:targetRole': $a = 'TargetRoleId';    $v = UpdateRole($v);    break;
     #case 'title': SetText(str_replace('definition: ', '', $v), $set, 'Title'); continue 2; # 'definition: ' stripped from Arc titles. Taken out of use 08.10.12
      case 'title': continue 2; # skip
      case 'order': $a = 'ArcOrder'; $v *= 1000000; break; # * 1000000 for storage as int with up to 6 decimals e.g. 1.999795
      case 'use':
        switch ($v) {
          case 'optional':   $v = TU_Optional;   break;
          case 'prohibited': $v = TU_Prohibited; break;
          default: DieNow("unknown use value $v");
        }
        $a = 'ArcUseN';  break;
      case 'priority':   break;
      case 'xbrldt:closed':
        if ($v != 'true')    DieNow("'xbrldt:closed' ($v) not true");
        $a = 'ClosedB';  $v = 1;  break;
      case 'xbrldt:contextElement':
        if ($v != 'segment') DieNow("'xbrldt:contextElement' ($v) not segment");
        $a = 'ContextN'; $v = TC_Segment; break;
      case 'xbrldt:usable':
        if ($v != 'false')   DieNow("'xbrldt:usable' ($v) not false");
        $a = 'UsableB';  $v = 0;  break;
      default: DieNow("unknown arc attribute $a");
    }
    $set .= ",$a='$v'";
  }

  # if ($TxElementsSkippedA &&
  #     (in_array($fromId, $TxElementsSkippedA) ||                     # Skip adding the Arc if its FromId is in the skip list
  #      ($typeN <= TLT_Definition && in_array($toId, $TxElementsSkippedA)))) { # Skip adding Presentation or Definition Arcs if their ToId is in the skip list
  #   if ($typeN  >= TLT_Label)
  #    #$DocMapA[$toLabel.$LinkbaseId] = ['ElId' => $fromId, 'ArcId'=>0, 'DocId'=>0]; # So label/ref can get $fromId and skip
  #     $DocMapA[$toLabel] = ['ElId' => $fromId, 'ArcId'=>0, 'DocId'=>0]; # So label/ref can get $fromId and skip
  #   return;
  # }

  $id = InsertFromLinkbase('Arcs', $set);
  switch ($typeN) {
    case TLT_Label:
    case TLT_Reference:
     #$DocMapA[$toLabel.$LinkbaseId] = ['ElId' => $fromId, 'ArcId'=>$id, 'DocId'=>0]; # $DocMapA [Label => [ElId, ArcId, DocId]]
      $DocMapA[$toLabel] = ['ElId' => $fromId, 'ArcId'=>$id, 'DocId'=>0]; # $DocMapA [Label => [ElId, ArcId, DocId]]
  }
} # End Arc()

# Called from XLink()
function Label($node) {
  global $LinkbaseId, $DocMapA; #, $TxElementsSkippedA;
  if (@$node['attributes']['xlink:type'] != 'resource') DieNow('label type not resource');
  if (!@$label = $node['attributes']['xlink:label'])    DieNow('label xlink:label attribute not set');
  if (!($txt = $node['txt']))                           DieNow('label txt not set');
 #$label=$label.$LinkbaseId; #Re same label used in different linkbase files e.g. for CountriesDimension verbose label added by DPL
  $set = 'TypeN='.TLT_Label;
  SetText($txt, $set);
  foreach ($node['attributes'] as $a => $v) {
    switch ($a) {
      case 'xlink:type':
      case 'xml:lang':    break; # skip
      case 'id':          break; # SetIdDef($v, $set);break; # Removed 08.10.12 as not useful
      case 'xlink:label':
        if (!isset($DocMapA[$label])) DieNow("\$DocMapA['$label'] not set in Label()");
        $elId = $DocMapA[$label]['ElId'];
        break;
      case 'xlink:role':  SetRole($v, $set, 'label'); break;
     #case 'xlink:title': SetText($v, $set, 'Title'); break; # Removed 08.10.12 as not useful
      case 'xlink:title': break; # skip
      default: DieNow("unknown label attribute $a");
    }
  }
  # if ($TxElementsSkippedA && in_array($elId, $TxElementsSkippedA)) return; # Skip adding the Label
  $set .= ",ElId=$elId";
  $id = InsertFromLinkbase('Doc', $set);
  # $DocMapA [Label => [ElId, ArcId, DocId]]
  if (!isset($DocMapA[$label])) DieNow("\$DocMapA[$label] not set in Label()");
  $DocMapA[$label]['DocId'] = $id;
} // End Label()

# Called from XLink()
function Reference() {
  global $NodesA, $NumNodes, $NodeX, $RefsA, $DocMapA; #, $TxElementsSkippedA;
  $node = $NodesA[$NodeX];
  if (@$node['attributes']['xlink:type'] != 'resource') DieNow('reference type not resource');
  if (!@$label = $node['attributes']['xlink:label'])    DieNow('reference xlink:label attribute not set');
  if (@$txt = $node['txt'])                             DieNow('reference txt is set');
 #$label=$label.$LinkbaseId;
  $set = 'TypeN='.TLT_Reference;
  foreach ($node['attributes'] as $a => $v) {
    switch ($a) {
      case 'xlink:type':  break; # skip
      case 'id':          break; # SetIdDef($v, $set); break; # IdId  Removed 08.10.12 as not used
      case 'xlink:label':
        if (!isset($DocMapA[$label])) DieNow("\$DocMapA['$label'] not set in Reference()");
        $elId = $DocMapA[$label]['ElId'];
        break;
      case 'xlink:role':  SetRole($v, $set, 'reference'); break;
      default: DieNow("unknown reference attribute $a");
    }
  }
  $depth1 = $node['depth']+1;
  $refsA = $RefsA;
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] == $depth1) {
    $node = $NodesA[++$NodeX];
    $tag = $node['tag'];
    if (($p=strpos($tag, ':')) === false) DieNow("Ref subnode without expected :");
    $tag = substr($tag, $p+1);
    if (!isset($refsA[$tag])) DieNow("unknown reference subnode $tag from ".$NodesA[$NodeX]['tag']);
    if (isset($node['txt']))
      $refsA[$tag] .= ', ' . $node['txt']; # addslashes() only to the completed json via SetText() or any \ gets slashed
  }

  #if ($TxElementsSkippedA && in_array($elId, $TxElementsSkippedA)) return; # Skip adding the Label
  $set .= ",ElId=$elId";

  foreach ($refsA as $a => $v)
    if ($v)
      $refsA[$a] = substr($v,2);
    else
      unset($refsA[$a]);
  SetText(json_encode($refsA), $set); # associative array is encoded as an object

  $id = InsertFromLinkbase('Doc', $set);
  # $DocMapA [Label => [ElId, ArcId, DocId]]
  if (!isset($DocMapA[$label])) DieNow("\$DocMapA['$label'] not set in Reference()");
  $DocMapA[$label]['DocId'] = $id;
} # End Reference()

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
  global $FileId, $NsMapA; # $NsMapA [namespace => [NsId, Prefix, File, Num]
  static $NsIdS = 0;
  if (isset($NsMapA[$ns])) {
    $NsMapA[$ns]['File'] .= ",$FileId";
  ++$NsMapA[$ns]['Num'];
    return $NsMapA[$ns]['NsId'];
  }
  $prefix = ($prefix > 'xmlns' && ($colon = strpos($prefix, ':')) > 0) ? substr($prefix, $colon+1) : '';
  $NsMapA[$ns] = array('NsId' => ++$NsIdS, 'Prefix'=>$prefix, 'File'=>$FileId, 'Num'=>1);
  return $NsIdS;
}

function Insert($table, $set) {
  global $DB;
  # echo "Insert($table, $set)<br>";
  if ($set[0] == ',') # $set may or may not have a leading comma
    $set = substr($set,1);
  return $DB->InsertQuery("Insert `$table` Set $set");
}

function InsertOrUpdate($table, $key, $kv, $set) {
  global $DB, $FileId;
  if ($o = $DB->OptObjQuery("Select Id,FileIds From $table where $key='$kv'")) {
    $DB->StQuery("Update $table Set Num=Num+1,FileIds='" . $o->FileIds . ',' . $FileId . "' Where Id=$o->Id");
    # return $o->Id;
  }else
    # return Insert($table, $set . ",FileIs=$FileId");
    echo "InsertOrUpdate() insert on $key='$kv' <===========<br>";
}

function InsertFromSchema($table, $set) {
  global $DB;
  if ($set[0] == ',')
    $set = substr($set,1);
  return $DB->InsertQuery("Insert `$table` Set $set");
}

function InsertFromLinkbase($table, $set) {
  global $DB, $LinkbaseId;
  if ($set[0] == ',')
    $set = substr($set,1);
  return $DB->InsertQuery("Insert `$table` Set LinkbaseId=$LinkbaseId,$set");
}

function UpdateRole($role, $usedOn=0, $definition=0) {
  global $RoleId, $FileId, $RolesAA; # $RolesAA [Role => [Id, usedOn, definition, ElId, FileId, Uses]]
  # http://www.xbrl./uk/role/ProftAndLossAccount => uk/ProftAndLossAccount
  # http://www.govtalk.gov.uk/uk/fr/tax/dpl-gaap/2012-10-01/role/Hypercube-DetailedProfitAndLossReserve => 'dpl-gaap/Hypercube-DetailedProfitAndLossReserve'
  if (strpos($role, 'http://') !== 0)   DieNow("non uri $role passed to UpdateRole()");
 #$role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', 'role/', 'int/', 'org/', 'govtalk.gov.uk/uk/fr/tax/','2013-02-01/'], '',  $role); # strip http:// etc 'org/' for the anomoly of uk/org/role/BalanceSheetStatement
  $role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', '2008/', 'role/', 'int/'], '',  $role); # strip http:// etc 'org/' for the anomoly of uk/org/role/BalanceSheetStatement
  #f (!isset($RolesAA[$role])) DieNow("Role $role not defined on SetRole() call as expected");
  if (!isset($RolesAA[$role]))
    $roleA = ['Id' => ++$RoleId, 'usedOn' => $usedOn, 'definition' => $definition, 'ElId' => 0, 'FileId' => $FileId, 'Uses' => 1];
  else{
    $roleA = $RolesAA[$role];
    if (!$roleA['usedOn']) $roleA['usedOn'] = $usedOn;
    if (!$roleA['FileId']) $roleA['FileId'] = $FileId;
    ++$roleA['Uses'];
  }
  $RolesAA[$role] = $roleA;
  return $roleA['Id'];
}

function SetRole($role, &$callingSet, $usedOn) {
  global $RoleId, $FileId, $RolesAA; # $RolesAA [Role => [Id, usedOn, definition, ElId, FileId, Uses]]
  # http://www.xbrl.org/uk/role/ProftAndLossAccount => uk/ProftAndLossAccount
  if (strpos($role, 'http://') !== 0)   DieNow("non uri $role passed to SetRole()");
 #$role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', 'role/', 'int/', 'org/'], '',  $role); # strip http:// etc 'org/' for the anomoly of uk/org/role/BalanceSheetStatement
  $role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', '2008/', 'role/', 'int/'], '',  $role); # strip http:// etc
  #f (!isset($RolesAA[$role])) DieNow("Role $role not defined on SetRole() call as expected");
  if (!isset($RolesAA[$role]))
    $roleA = ['Id' => ++$RoleId, 'usedOn' => $usedOn, 'definition' => $definition, 'ElId' => 0, 'FileId' => $FileId, 'Uses' => 1];
  else{
    $roleA = $RolesAA[$role];
    if (!$roleA['usedOn']) $roleA['usedOn'] = $usedOn;
    if (!$roleA['FileId']) $roleA['FileId'] = $FileId;
    ++$roleA['Uses'];
  }
  $RolesAA[$role] = $roleA;
  $callingSet .= ",RoleId={$roleA['Id']}";
}

function UpdateArcrole($arcrole, $usedOn=0, $definition=0, $cyclesAllowed=0) {
  global $FileId, $ArcRolesAA; # $ArcRolesAA [Arcrole => [Id, usedOn, definition, cyclesAllowed, FileId, Uses]]
  static $arId;
  # http://www.xbrl.org/2003/arcrole/parent-child       => parent-child
  # http://xbrl.org/int/dim/arcrole/hypercube-dimension => dim/hypercube-dimension
  if (strpos($arcrole, 'http://') !== 0)   DieNow("non uri $arcrole passed to UpdateArcrole()");
  $arcrole = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', 'arcrole/', 'int/'], '',  $arcrole); # strip http:// etc
  #f (!isset($ArcRolesAA[$arcrole])) DieNow("Role $arcrole not defined on UpdateArcrole() call as expected"); From when $ArcRolesAA was predefined in code to get the desired order
  if (!isset($ArcRolesAA[$arcrole]))
    $arcroleA = ['Id' => ++$arId, 'usedOn' => $usedOn, 'definition' => $definition, 'cyclesAllowed' => $cyclesAllowed, 'FileId' => $FileId, 'Uses' => 1];
  else{
    $arcroleA = $ArcRolesAA[$arcrole];
    if ($usedOn        && !$arcroleA['usedOn'])        $arcroleA['usedOn']        = $usedOn;
    if ($definition    && !$arcroleA['definition'])    $arcroleA['definition']    = $definition;
    if ($cyclesAllowed && !$arcroleA['cyclesAllowed']) $arcroleA['cyclesAllowed'] = $cyclesAllowed;
    if                   (!$arcroleA['FileId'])        $arcroleA['FileId']        = $FileId;
    $arcroleA['Uses']++;
  }
  $ArcRolesAA[$arcrole] = $arcroleA;
  return $arcroleA['Id'];
}

# Labels     TextId   # Text.Id  for the content of the label     /- Only these two as of 08.10.12
# References TextId   # Text.Id  for Refs content stored as json  |
function SetText($text, &$callingSet) {
  global $TextMapA; # $TextMapA text => [TextId, Uses]
  static $TextIdS=0;
  $text = addslashes($text);
  if (isset($TextMapA[$text])) {
    $id = $TextMapA[$text]['TextId'];
    ++$TextMapA[$text]['Uses'];
  }else{
    $id = ++$TextIdS;
    $TextMapA[$text] = array('TextId'=>$id, 'Uses'=>1);
  }
  $callingSet .= ",TextId=$id";
}

###########
## Names ## For xsd:NCName
###########
# Elements.name    # name              [0..1] xsd:NCName
# Elements NameId  # Names.Id for name [0..1] xsd:NCName
# (xsd:NCName values in Labels, References, and Arc To fields for label and reference arcs are not stored but are just used during the build to link Labels and references to Elements.)
/* Taken OoS 10.10.12 with change to store name in the Elements table
function SetName($name, &$callingSet) {
  global $NamesMapA; # $NamesMapA [name => [NameId, ElId, Uses]]
  static $NamesIdS=0;
  $name = FixNameCase($name);
  if (isset($NamesMapA[$name])) {
    $id = $NamesMapA[$name]['NameId'];
    ++$NamesMapA[$name]['Uses'];
  }else{
    $id = ++$NamesIdS;
    $NamesMapA[$name] = array('NameId'=>$id, 'ElId'=>0, 'Uses'=>1);
  }
  $callingSet .= ",NameId=$id";
}
function FixNameCase($name) { Not needed after removal of LinkPart elements from build
  # re Footnote and footnote
  static $NameFixesSA = array('Footnote' => 'footnote', 'Part' => 'part');
  if (isset($NameFixesSA[$name])) return $NameFixesSA[$name];
  return $name;
} */

/*
In http://www.xbrl.org/2003/xbrl-instance-2003-12-31.xsd
have
<import namespace="http://www.xbrl.org/2003/linkbase" schemaLocation="xbrl-linkbase-2003-12-31.xsd"/>
want
schemaLocation="xbrl-linkbase-2003-12-31.xsd
to become
http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd
*/
function FileAdjustRelative($loc) {
  global $File; # current Schema or Linkbase url
  # File: http://www.xbrl.org/2003/xbrl-instance-2003-12-31.xsd
  # loc:  xbrl-linkbase-2003-12-31.xsd
  # -->   http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd
  # echo "FileAdjustRelative()<br>File: $File<br>loc:  $loc<br>";
  if (strncmp($loc, 'http:', 5) == 0) {
    # if $loc starts with http: accept it as it is
    # echo 'loc unchanged<br>';
    return $loc;
  }
  #$p = strrpos($File, '/'); // last '/' in $File
  #echo 'loc -> ' . substr($File, 0, $p+1).$loc . '<br>';
  #return substr($File, 0, $p+1).$loc;
  return substr($File, 0, strrpos($File, '/')+1).$loc;
}

# Return tag stripped of prefix if any
function LessPrefix($tag) {
  if (($p = strpos($tag, ':')) > 0) # strip any prefix
    $tag = substr($tag, $p+1);
  return $tag;
}

///// Functions which are candidates for removal
function Attribute() {
  global $NodesA, $NumNodes, $NodeX;
  $node = $NodesA[$NodeX];
  if (!@$name = $node['attributes']['name']) DieNow('no name for primary attribute');
  $set = "name='$name'";
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > 1) { # <attribute has a depth of 1
    $NodeX++;
    switch ($NodesA[$NodeX]['tag']) {
      case 'annotation':
      case 'documentation': break;
      case 'simpleType':    $set .= (',SimpleTypeId=' . SimpleType()); break;
      default: DieNow("unknown tag {$node['tag']} in <attribute<br>");
    }
  }
  InsertFromSchema('Attributes', $set);
}

function AttributeGroup() {
  global $NodesA, $NumNodes, $NodeX;
  if (!$name = $NodesA[$NodeX]['attributes']['name'])
    DieNow('no name for attributeGroup');
  $set = "name='$name'";
  $attributesA = []; # there can be multiple <attribute subnodes
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] > 1) {
    $node = $NodesA[++$NodeX];
    switch ($node['tag']) {
      case 'annotation':
      case 'documentation': break;
      case 'attribute':      # <attribute name="precision" type="xbrli:precisionType" use="optional" />
        $attributesA[] = $node['attributes'];
        break;
      case 'attributeGroup': # <attributeGroup ref="xbrli:essentialNumericItemAttrs" />
        $set .= ",ref='{$node['attributes']['ref']}'";
        break;
      case 'anyAttribute':
        $set .= ",anyAttributeJson='" . json_encode($node['attributes']) . SQ;
        break;
      default: DieNow("unknown tag {$node['tag']} in <attributeGroup<br>");
    }
  }
  if (count($attributesA))
    $set .= ",attributeJson='" . json_encode($attributesA) . SQ;
  # InsertFromSchema('AttributeGroups', $set);  29.01.11 skip
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

*/

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
    switch ($node['tag']) {
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
        if ($tuple) DumpExport("Tuple complex content complexA for $tuple", $complexA);
        if ($tuple) $TuplesA[$tuple] = $complexA;
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
  # In the no name case use $set as a where clause with , => and to see if this complexType has already been defined
  if (!$name) {
    if ($set[0] == ',')  # $set may have a leading comma
      $set = substr($set, 1); # djh?? SchemaId is a tinyint so can't hold a csv list
    if ($o = $DB->OptObjQuery('Select Id,SchemaId From ComplexTypes where ' . str_replace(',', ' and ', $set))) {
      $DB->StQuery("Update ComplexTypes Set SchemaId='" . $o->SchemaId . ',' . $SchemaId . "' Where Id=$o->Id");
      return $o->Id;
    }
  }
  return InsertFromSchema('ComplexTypes', $set);
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
*/
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
  switch (LessPrefix($node['tag'])) { # expect restriction or union
    case 'restriction':
      if (!$base = @$node['attributes']['base'])  DieNow('simpleType restriction base not found as expected');
      $set .= ",base='$base'";
      switch (LessPrefix($base)) {
        case 'anyURI': # expect minLength
          if (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] == $depth+2) { # +2 for restriction then minLength
            $NodeX++;
            $set .= ",{$NodesA[$NodeX]['tag']}={$NodesA[$NodeX]['attributes']['value']}";
          }
          break;
        case 'token':   # /- expect a set of enumeration values
        case 'NMTOKEN': # |
        case 'string':  # |
          $enums = '';
          while (($NodeX+1) < $NumNodes && LessPrefix($NodesA[$NodeX+1]['tag']) == 'enumeration') {
            $enums .= ',' . $NodesA[++$NodeX]['attributes']['value'];
          }
          if (!($enums = substr($enums, 1))) DieNow("no enum list for simpleType base=$base");
          $set .= ",EnumList='$enums'";
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
  # In the no name case use $set as a where clause with , => and to see if this simpleType has already been defined
  if (!$name) {
    if ($set[0] == ',')  # $set may have a leading comma
      $set = substr($set, 1);
    if ($o = $DB->OptObjQuery('Select Id,SchemaId From SimpleTypes where ' . str_replace(',', ' and ', $set))) {
      $DB->StQuery("Update SimpleTypes Set SchemaId='" . $o->SchemaId . ',' . $SchemaId . "' Where Id=$o->Id");
      return $o->Id;
    }
  }
  return InsertFromSchema('SimpleTypes', $set);
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
  # See if this union has already been defined
  $set = substr($set, 1);
  if ($o = $DB->OptObjQuery('Select Id,SchemaId From Unions where ' . str_replace(',', ' and ', $set))) {
    $DB->StQuery("Update Unions Set SchemaId='" . $o->SchemaId . ',' . $SchemaId . "' Where Id=$o->Id");
    return $o->Id;
  }
  return InsertFromSchema('Unions', $set);
} // End Union()
