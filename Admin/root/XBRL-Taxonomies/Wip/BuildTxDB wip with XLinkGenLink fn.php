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
Expand LinkBaseRefs to include full info even tho not used
Check $RolesMA elements - definition?

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

set_time_limit(60);

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
$RolesMA    = # [Role    => [Id, usedOn, definition, ElId, FileId, Uses]]
$ArcRolesMA = # [Arcsrcrole => [Id, usedOn, definition, cyclesAllowed, FileId, Uses]]
$NsMA       = # [namespace => [NsId, Prefix, File, Num]
$NamesMI    = # [NsId.name =>  ElId]
$XidsMI     = # [xidS  => ElId]  Not written to a table
#$DocMapA   =     # [Label => [ElId, ArcId, DocId]] where label is the To of Arcs and the Label of Labels and references; ElId is the FromId of the Arc.
#$TextMapA  =     # [Text  => [TextId, Uses]]
$TuplesA   = []; # [tupleName => complexContent for the tuple element in $complexA] djh? Remove?

$XR = new XMLReader();

$tablesA = [
  'Arcroles',
  'Arcs',
  'AttributeGroups',
  'Attributes',
  'Elements',
  'LinkbaseRefs',
  'Namespaces',
  'SimpleTypes',
  'ComplexTypes',
  'Resources',
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

$RefsA = [ # djh??
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

echo '<h2 class=c>Building the $TxName Database</h2>
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
  if (!isset($NamesMI[$tuple])) DieNow("Tuple $tuple not in NamesMI as expected");
  $tupTxId = $NamesMI[$tuple];
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
      foreach ($NsMA as $nsA)
        if ($nsA['Prefix'] == $elNameSegsA[0]) {
          $nsId = $nsA['NsId'];
          break;
        }
      if (!$nsId) DieNow("No namespace found for Tuple member ref $ref");
      $elName = "$nsId.$elNameSegsA[1]";
      if (!isset($NamesMI[$elName])) DieNow("Tuple $tuple not in NamesMI as expected");
      $memTxId = $NamesMI[$elName];
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
      case 'link:calculationLink':  XLink(TLT_Calculation);  break; # plos <loc and <calculationArc
      case 'link:labelLink':        XLink(TLT_Label);        break; # plus <loc and <labelArc
      case 'link:referenceLink':    XLink(TLT_Reference);    break; # plus <loc and <referenceArc
     #case 'link:footnoteLink':     footnote extended link element definition
     #case 'gen:link':              XLinkGenLink();          break;
      case 'gen:link':              XLink(TLT_GenLink);      break;
      default: DieNow("unknown linkbase tag $tag<br>");
    }
  }
} # End of Linkbase loop
$res->free();

echo "<br>Elements and Arcs inserted.<br>";

# Insert the Roles
# $RolesMA [Role => [Id, usedOn, definition, ElId, FileId, Uses]]
foreach ($RolesMA as $role => $roleA) {
  $id = $DB->InsertQuery("Insert Roles Set Role='$role',usedOn='$roleA[usedOn]',definition='$roleA[definition]',ElId=$roleA[ElId],FileId=$roleA[FileId],Uses=$roleA[Uses]");
  if ($id != $roleA['Id']) DieNow("Id $id on Role Insert not $roleA[Id] as expected");
}

# Insert the Arcroles
# $ArcRolesMA [Arcrole => [Id, usedOn, definition, cyclesAllowed, FileId, Uses]]
foreach ($ArcRolesMA as $arcrole => $arcroleA) {
  $set = "Arcrole='$arcrole',FileId=$arcroleA[FileId],Uses=$arcroleA[Uses]";
  if ($arcroleA['usedOn'])        $set .= ",usedOn='$arcroleA[usedOn]'";
  if ($arcroleA['definition'])    $set .= ",definition='$arcroleA[definition]'";
  if ($arcroleA['cyclesAllowed']) $set .= ",cyclesAllowed='$arcroleA[cyclesAllowed]'";
  $id = $DB->InsertQuery("Insert Arcroles Set $set");
  if ($id != $arcroleA['Id']) DieNow("Id $id on Arcrole Insert not $arcroleA[Id] as expected");
}

# Insert the Namespaces
# $NsMA [namespace => [NsId, Prefix, File, Num]
foreach ($NsMA as $ns => $nsA) {
  $id = $DB->InsertQuery("Insert Namespaces Set namespace='$ns',Prefix='$nsA[Prefix]',File='$nsA[File]',Num=$nsA[Num]");
  if ($id != $nsA['NsId']) DieNow("Id $id on Namespaces Insert not $nsA[NsId] as expected");
}

# Insert Text
foreach ($TextMapA as $text => $textA) { # array Text => [TextId, Uses]
  $id = $DB->InsertQuery("Insert Text Set Text='$text',Uses=$textA[Uses]");
  if ($id != $textA['TextId']) DieNow("Id $id on Text Insert not $textA[TextId] as expected");
}

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

# Called from Schema()
# <element id="xml-gen-arc" name="arc" substitutionGroup="xl:arc" type="gen:genericArcType"/>
function Element($nsId) {
  global $NodesA, $NumNodes, $NodeX, $XidsMI, $NamesMI;
  static $sElId=0; # re skipping elements and preserving full build element Ids
  ++$sElId;
  $node = $NodesA[$NodeX];
  $depth = $node['depth'];
  $set = "Id=$sElId,NsId='$nsId'";
  $name = $xidS = $SubstGroupN = $tuple = 0; # $tuple is set to '$nsId.$name' for the tuple case for passing to ComplexType()
  foreach ($node['attributes'] as $a => $v) {
    switch ($a) {
      case 'id':   $xidS = $v; continue 2; # SetIdDef($xidS=$v, $set); continue 2; # IdId
      case 'name': $name = $v; continue 2;
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

  # $NamesMI [NsId.name => ElId]
  if (!$name) DieNow('no name for element');
  $nsname = "$nsId.$name";
  if (isset($NamesMI[$nsname])) DieNow("Duplicate NsId.name $nsname");
  $NamesMI[$nsname] = $sElId;
  if ($xidS) $XidsMI[$xidS] = $sElId;
  $set .= ",name='$name'";
  if (InsertFromSchema('Elements', $set) != $sElId) DieNow("Elements insert didn't give expected Id of $sElId");
} // End Element()

# Called from Schema()
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
# Called from Schema()
function RoleType() {
  global $NodesA, $NumNodes, $NodeX;
  $node = $NodesA[$NodeX];
  if (!@$roleURI=$node['attributes']['roleURI']) DieNow('roleType roleURI missing');
  if (!@$idS=$node['attributes']['id'])          DieNow('roleType id missing');
  # Expect id to be in the uri
  if (!strpos($roleURI, $idS)) DumpExport("For Node $NodeX roleType id $idS is not in the uri $roleURI", $node);
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
  UpdateRole($idS, $usedOn, $definition);
}

# Called from Schema()
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

# Called from the Linkbase loop for:
#   case 'link:presentationLink': XLink(TLT_Presentation); break; # plus <loc and <presentationArc  (link:documentation is not used by UK GAAP)
#   case 'link:definitionLink':   XLink(TLT_Definition);   break; # plus <loc and <definitionArc
#   case 'link:calculationLink':  XLink(TLT_Calculation);  break;
#   case 'link:labelLink':        XLink(TLT_Label);        break; # plus <loc and <labelArc
#   case 'link:referenceLink':    XLink(TLT_Reference);    break; # plus <loc and <referenceArc
#  #case 'link:footnoteLink':     footnote extended link element definition

# For presentationLink, definitionLink, calculationLink labelLink, referenceLink
#     TLT_Presentation  TLT_Definition  TLT_Calculation TLT_Label  TLT_Reference
function XLink($typeN) {
  global $NodesA, $NumNodes, $NodeX, $LocLabelToIdA, $XidsMI, $RolesMA;
  $LocLabelToIdA = []; # [label => [idS | id]] can have multiple entries for the same label. idS = locator idS, id == resource (Resources table) Id
  $node = $NodesA[$NodeX];
  DumpExport("Node $NodeX in XLink()", $node);
  if (@$node['attributes']['xlink:type'] != 'extended') DieNow('...link type not extended');
  if (!($role = @$node['attributes']['xlink:role']))    DieNow('...link xlink:role attribute not set');
  $roleId = UpdateRole($role, $node['tag']);
  $depth1 = $node['depth']+1;
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] === $depth1) {
    $node = $NodesA[++$NodeX];
    DumpExport("Node $NodeX in XLink()", $node);
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
        # But this is not necessarily so for in general and isn't for IFRS
        $idS = substr($href, $p+1); # Locator Label = Element or Role IdS
        if (isset($XidsMI[$idS]))                # $XidsMI  = [xidS  => ElId]
          $idA = [$XidsMI[$idS], TAFTT_Element];
        else if (isset($RolesMA[$idS]))          # $RolesMA = [Role => [Id, usedOn, definition, ElId, FileId, Uses]]
          $idA = [$RolesMA[$idS]['Id'], TAFTT_Role];
        else
          DieNow("No match found for locator idS $ids in XLink()");
        $LocLabelToIdA[$label][] = $idA;
        break;
      case 'link:presentationArc':
      case 'link:definitionArc':
      case 'link:calculationArc':
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
#     TLT_Presentation  TLT_Definition TLT_Calculation TLT_Label TLT_Reference TLT_GenLink  TLT_Footnote
/*
CREATE TABLE IF NOT EXISTS Arcs (
  Id           smallint unsigned not null auto_increment,
  TypeN        tinyint  unsigned not null, # TLT_Presentation | TLT_Definition | TLT_Calculation | TLT_Label | TLT_Reference | TLT_GenLink | TLT_Footnote
  FromId       smallint unsigned not null, # Elements.Id | Resources.Id | Roles.Id   [1..1] xsd:NCName
  FromTypeN    tinyint  unsigned not null, # Type of the Arc FromId: TAFTT_Element | TAFTT_Label | TAFTT_Reference | TAFTT_Role      = 4; # Arc From or To is a role
  ToId         smallint unsigned not null, # Elements.Id | Resources.Id | Roles.Id   [1..1] xsd:NCName
  ToTypeN      tinyint  unsigned not null, # Type of the Arc ToId: TAFTT_Element | TAFTT_Label | TAFTT_Reference | TAFTT_Role      = 4; # Arc From or To is a role
  PRoleId      tinyint  unsigned not null, # Roles.Id of the parent <presentationLink, <definitionLink etc
  ArcroleId    tinyint  unsigned not null, # Roles.Id    for xlink:arcrole [1..1] Anonymous
  TitleId      smallint unsigned     null, # Text.Id     for xlink:title   [0..1] xsd:string
  ArcOrder     int      unsigned     null, # for order [0..1] xsd:decimal * 1000000 re use of 6 decimals e.g. 1.999795         /- Name change mainly because order & use are MySQL reserved words
  ArcUseN      tinyint               null, # for use   [0..1] xl:useEnum   | NULL 11892 | TU_Optional 13911 | TU_Prohibited 50 |
  priority     tinyint unsigned      null, # priority  [0..1] xsd:integer
  PrefLabelRoleId  tinyint  unsigned null, # Roles.Id   for preferredLabel [0..1] Anonymous, these actually being roles e.g. http://www.xbrl.org/2003/role/label | http://www.xbrl.org/2003/role/periodStartLabel etc
#                                          Any attribute   [0..*] Some 'any' follow
  ClosedB      tinyint  unsigned     null, # xbrldt:closed         "true" stored as 1
  ContextN     tinyint  unsigned     null, # for xbrldt:contextElement       | NULL 25807 | TC_Segment 46
  UsableB      tinyint  unsigned     null, # for xbrldt:usable default true  | NULL 25832 | false 21
  TargetRoleId tinyint  unsigned     null, # Roles.Id for xbrldt:targetRole  <=== Used only with Hypercubes. All Arcs with ArcroleId=7 TA_HypercubeDim, 191 of them
  LinkbaseId   tinyint  unsigned not null, # LinkbaseRefs.Id of file where defined
  Primary Key (Id),
#         Key (TypeN),     # Not needed as a key since ArcroleId covers it. See the TR_ constants. Remove from table?
          Key (FromId),
          Key (ToId),
          Key (PRoleId),
          Key (ArcroleId),
          Key (ArcOrder)
) Engine = InnoDB DEFAULT CHARSET=utf8;
*/
function Arc($typeN, $pRoleId) {
  global $NodesA, $NodeX, $LocLabelToIdA, $XidsMI; # $XidsMI [xidS  => ElId]
  global $LocLabelToIdA; # [label => [[id, typeN]]] can have multiple entries for the same label
  $node = $NodesA[$NodeX];
  DumpExport("Node $NodeX in Arc()", $node);
  if ($node['attributes']['xlink:type'] != 'arc')   DieNow('arc type not arc');
  if (!isset($node['attributes']['xlink:from']))    DieNow('arc xlink:from attribute not set');
  if (!isset($node['attributes']['xlink:to']))      DieNow('arc xlink:to attribute not set');
  if (!isset($node['attributes']['xlink:arcrole'])) DieNow('arc xlink:arcrole attribute not set');
  $set = "TypeN=$typeN,PRoleId=$pRoleId";
  foreach ($node['attributes'] as $a => $v) {
    $a = str_replace('xlink:', '', $a); # strip xlink: prefix
    switch ($a) {
      case 'type': continue 2; # skip
      case 'from':
        if (!isset($LocLabelToIdA[$v])) DieNow("Arc From=$v not set in \$LocLabelToIdA['$v'])");
        $fromIdsA = $LocLabelToIdA[$v];
        if (count($fromIdsA) > 1)
          DumpExport('LocLabelToIdA in Arc() - Multiple Arc Froms <==========', $LocLabelToIdA);
        continue 2;
      case 'to':
        if (!isset($LocLabelToIdA[$v])) DieNow("Arc To=$v not set in \$LocLabelToIdA['$v'])");
        $toIdsA = $LocLabelToIdA[$v];
        if (count($toIdsA) > 1)
          DumpExport('LocLabelToIdA in Arc() - Multiple Arc Tos <==========', $LocLabelToIdA);
        continue 2;
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
  # Can have multiple table entries if multiple froms or tos or both
  $baseSet = $set;
  foreach ($fromIdsA as $j => $idA) {
    $fromSet = $baseSet.",FromId={$idA[0]},FromTypeN={$idA[1]},ToId=";
    foreach ($toIdsA as $j => $idA)
      InsertFromLinkbase('Arcs', $fromSet.$idA[0].",ToTypeN={$idA[1]}");
  }
} # End Arc()

# Called from XLink()
# <label:label xlink:label="res_1" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[913000] Axis - Consolidated, combined and separate financial statements</label:label>
function Label($node) {
  global $LocLabelToIdA; # [label => [[id, typeN]]] can have multiple entries for the same label
  if (@$node['attributes']['xlink:type'] != 'resource') DieNow('label type not resource');
  if (!@$label = $node['attributes']['xlink:label'])    DieNow('label xlink:label attribute not set');
  if (!($txt = $node['txt']))                           DieNow('label txt not set');
  $set = 'TypeN='.TLT_Label;
  SetText($txt, $set);
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
        SetRole($v, $set, 'label');
        break;
     #case 'xlink:title': SetText($v, $set, 'Title'); break; # Removed 08.10.12 as not useful
      default: DieNow("unknown label attribute $a");
    }
  }
  $id = InsertFromLinkbase('Resources', $set);
  $LocLabelToIdA[$label][] = [$id, TAFTT_Label]; # [label => [[id, typeN]]] can have multiple entries for the same label
} // End Label()

# Called from XLink()
function Reference() {
  global $NodesA, $NumNodes, $NodeX, $RefsA;
  global $LocLabelToIdA; # [label => [[id, typeN]]] can have multiple entries for the same label
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
      case 'xlink:label': # set to $label above
        break; # skip
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
  foreach ($refsA as $a => $v)
    if ($v)
      $refsA[$a] = substr($v,2);
    else
      unset($refsA[$a]);
  SetText(json_encode($refsA), $set); # associative array is encoded as an object

  $id = InsertFromLinkbase('Resources', $set);
  $LocLabelToIdA[$label][] = [$id, TAFTT_Reference]; # [label => [[id, typeN]]] can have multiple entries for the same label
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
# Called from the Linkbase loop for
# For gen:link
#     TLT_GenLink
function XLinkGenLink() {
  global $NodesA, $NumNodes, $NodeX, $LocLabelToIdA, $XidsMI, $RolesMA;
  $LocLabelToIdA = []; # [label => [idS | id]] can have multiple entries for the same label. idS = locator idS, id == resource (Resources table) Id
  $node = $NodesA[$NodeX];
  DumpExport("Node $NodeX in XLinkGenLink()", $node);
  if (@$node['attributes']['xlink:type'] != 'extended') DieNow('...link type not extended in XLinkGenLink()');
  if (!($role = @$node['attributes']['xlink:role']))    DieNow('...link xlink:role attribute not set in XLinkGenLink()');
  $roleId = UpdateRole($role, $node['tag']);
  $depth1 = $node['depth']+1;
  while (($NodeX+1) < $NumNodes && $NodesA[$NodeX+1]['depth'] === $depth1) {
    $node = $NodesA[++$NodeX];
    DumpExport("Node $NodeX in XLinkGenLink()", $node);
    $tag = $node['tag'];
    switch ($tag) {
      case 'link:loc':
        # Locator
        # <link:loc xlink:href="rol_ifrs_for_smes-dim_2018-03-16.xsd#ifrs_for_smes-dim_role-913000" xlink:label="loc_1" xlink:type="locator"/>
        if ($node['attributes']['xlink:type'] != 'locator') DieNow('loc type not locator in XLinkGenLink()');
        if (!$href = $node['attributes']['xlink:href'])     DieNow('loc xlink:href attribute not set in XLinkGenLink()');
        if (!$label = $node['attributes']['xlink:label'])   DieNow('loc xlink:label attribute not set in XLinkGenLink()');
        if (!$p = strpos($href, '#'))                       DieNow("No # in locator href $href in XLinkGenLink()");
        # The #... of the href is the id of the element with the href part up to the # being the xsd that defines the element
        $idS = substr($href, $p+1); # Locator Label = Element or Role IdS
        if (isset($XidsMI[$idS]))                # $XidsMI  = [xidS  => ElId]
          $idA = [$XidsMI[$idS], TAFTT_Element];
        else if (isset($RolesMA[$idS]))          # $RolesMA = [Role => [Id, usedOn, definition, ElId, FileId, Uses]]
          $idA = [$RolesMA[$idS]['Id'], TAFTT_Role];
        else{
          DumpExport('RolesMA DumpExport', $RolesMA);
          DieNow("No match found for locator idS $idS in XLink()");
        }
        $LocLabelToIdA[$label][] = $idA;
        break;
      case 'label:label':
        # <label:label xlink:label="res_1" xlink:role="http://www.xbrl.org/2008/role/label" xlink:type="resource" xml:lang="en">[913000] Axis - Consolidated, combined and separate financial statements</label:label>
        if (@$node['attributes']['xlink:type'] != 'resource') DieNow('gen:link label type not resource in XLinkGenLink()');
        if (!@$label = $node['attributes']['xlink:label'])    DieNow('gen:link label xlink:label attribute not set in XLinkGenLink()');
        if (!($txt = $node['txt']))                           DieNow('gen:link label txt not set in XLinkGenLink()');
        $set = 'TypeN='.TLT_Label;
        SetText($txt, $set);
        foreach ($node['attributes'] as $a => $v) {
          switch ($a) {
            case 'xlink:type':
            case 'xml:lang':
            case 'xlink:title':
            case 'xlink:label': # set to $label above
              break;
            case 'xlink:role': SetRole($v, $set, 'label');
              break;
            default: DieNow("unknown label attribute $a in XLinkGenLink()");
          }
        }
        $id = InsertFromLinkbase('Resources', $set);
        $LocLabelToIdA[$label][] = [$id, TAFTT_Label]; # [label => [idS | id]] can have multiple entries for the same label. idS = locator idS, id == resource (Resources table) Id
        break;
      case 'gen:arc':
        Arc(TLT_GenLink, $roleId);
        break;
      default: DieNow("unknown gen:link tag $tag<br>");
    }
  }
} # End XLinkGenLink()


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
  global $FileId, $NsMA; # $NsMA [namespace => [NsId, Prefix, File, Num]
  static $sNsId = 0;
  if (isset($NsMA[$ns])) {
    $NsMA[$ns]['File'] .= ",$FileId";
  ++$NsMA[$ns]['Num'];
    return $NsMA[$ns]['NsId'];
  }
  $prefix = ($prefix > 'xmlns' && ($colon = strpos($prefix, ':')) > 0) ? substr($prefix, $colon+1) : '';
  $NsMA[$ns] = ['NsId' => ++$sNsId, 'Prefix'=>$prefix, 'File'=>$FileId, 'Num'=>1];
  return $sNsId;
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

# Called from RoleType( with the idS not the uri) which is called from Schema()
#             XLink(uri)
#             Arc(uri) re preferredLabel and PrefLabelRoleId
#                         targetRole         TargetRoleId
# <link:roleType id="ifrs_for_smes-dim_role-913000" roleURI="http://xbrl.ifrs.org/role/ifrs/ifrs_for_smes-dim_role-913000">
function UpdateRole($vRole, $usedOn=0, $definition=0) {
  global $RoleId, $FileId, $RolesMA; # $RolesMA [Role => [Id, usedOn, definition, ElId, FileId, Uses]]
  # http://www.xbrl./uk/role/ProftAndLossAccount => uk/ProftAndLossAccount
  # http://www.govtalk.gov.uk/uk/fr/tax/dpl-gaap/2012-10-01/role/Hypercube-DetailedProfitAndLossReserve => 'dpl-gaap/Hypercube-DetailedProfitAndLossReserve'
 #if (strpos($role, 'http://') !== 0)   DieNow("non uri $role passed to UpdateRole()");
 #$role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', 'role/', 'int/', 'org/', 'govtalk.gov.uk/uk/fr/tax/','2013-02-01/'], '',  $role); # strip http:// etc 'org/' for the anomoly of uk/org/role/BalanceSheetStatement
  $role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', '2008/', 'role/', 'int/'], '',  $vRole); # strip http:// etc 'org/' for the anomoly of uk/org/role/BalanceSheetStatement
  if ($role != $vRole)
    echo "UpdateRole role $vRole<br>UpdateRole role $role<br>";
  #f (!isset($RolesMA[$role])) DieNow("Role $role not defined on UpdateRole() call as expected");
  if (!isset($RolesMA[$role]))
    $roleA = ['Id' => ++$RoleId, 'usedOn' => $usedOn, 'definition' => $definition, 'ElId' => 0, 'FileId' => $FileId, 'Uses' => 1];
  else{
    $roleA = $RolesMA[$role];
    if (!$roleA['usedOn']) $roleA['usedOn'] = $usedOn;
    if (!$roleA['FileId']) $roleA['FileId'] = $FileId;
    ++$roleA['Uses'];
  }
  $RolesMA[$role] = $roleA;
  return $roleA['Id'];
}

# Called from Label()
#             Reference()
#             XLinkGenLink()
function SetRole($role, &$callingSet, $usedOn) {
  global $RoleId, $FileId, $RolesMA; # $RolesMA [Role => [Id, usedOn, definition, ElId, FileId, Uses]]
  # http://www.xbrl.org/uk/role/ProftAndLossAccount => uk/ProftAndLossAccount
  if (strpos($role, 'http://') !== 0)   DieNow("non uri $role passed to SetRole()");
 #$role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', 'role/', 'int/', 'org/'], '',  $role); # strip http:// etc 'org/' for the anomoly of uk/org/role/BalanceSheetStatement
  $role = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', '2008/', 'role/', 'int/'], '',  $role); # strip http:// etc
  #f (!isset($RolesMA[$role])) DieNow("Role $role not defined on SetRole() call as expected");
  if (!isset($RolesMA[$role]))
    $roleA = ['Id' => ++$RoleId, 'usedOn' => $usedOn, 'definition' => 0, 'ElId' => 0, 'FileId' => $FileId, 'Uses' => 1];
  else{
    $roleA = $RolesMA[$role];
    if (!$roleA['usedOn']) $roleA['usedOn'] = $usedOn;
    if (!$roleA['FileId']) $roleA['FileId'] = $FileId;
    ++$roleA['Uses'];
  }
  $RolesMA[$role] = $roleA;
  $callingSet .= ",RoleId={$roleA['Id']}";
}

function UpdateArcrole($arcrole, $usedOn=0, $definition=0, $cyclesAllowed=0) {
  global $FileId, $ArcRolesMA; # $ArcRolesMA [Arcrole => [Id, usedOn, definition, cyclesAllowed, FileId, Uses]]
  static $arId;
  # http://www.xbrl.org/2003/arcrole/parent-child       => parent-child
  # http://xbrl.org/int/dim/arcrole/hypercube-dimension => dim/hypercube-dimension
  if (strpos($arcrole, 'http://') !== 0)   DieNow("non uri $arcrole passed to UpdateArcrole()");
  $arcrole = str_replace(['http://', 'www.', 'xbrl.org/', '2003/', 'arcrole/', 'int/'], '',  $arcrole); # strip http:// etc
  #f (!isset($ArcRolesMA[$arcrole])) DieNow("Role $arcrole not defined on UpdateArcrole() call as expected"); From when $ArcRolesMA was predefined in code to get the desired order
  if (!isset($ArcRolesMA[$arcrole]))
    $arcroleA = ['Id' => ++$arId, 'usedOn' => $usedOn, 'definition' => $definition, 'cyclesAllowed' => $cyclesAllowed, 'FileId' => $FileId, 'Uses' => 1];
  else{
    $arcroleA = $ArcRolesMA[$arcrole];
    if ($usedOn        && !$arcroleA['usedOn'])        $arcroleA['usedOn']        = $usedOn;
    if ($definition    && !$arcroleA['definition'])    $arcroleA['definition']    = $definition;
    if ($cyclesAllowed && !$arcroleA['cyclesAllowed']) $arcroleA['cyclesAllowed'] = $cyclesAllowed;
    if                   (!$arcroleA['FileId'])        $arcroleA['FileId']        = $FileId;
    $arcroleA['Uses']++;
  }
  $ArcRolesMA[$arcrole] = $arcroleA;
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
    $TextMapA[$text] = ['TextId'=>$id, 'Uses'=>1];
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
