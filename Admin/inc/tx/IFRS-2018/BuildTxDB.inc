<?php /* \Admin\inc\tx\IFRS-2018\BuildTxDB.inc

Taxonomy specific include for IFRS-2018

https://www.ifrs.org/issued-standards/ifrs-taxonomy/ifrs-taxonomy-2018/#entry-points

History:
2018.10.13 Started

*/
#entryPointUrl = 'http://xbrl.ifrs.org/taxonomy/2018-03-16/full_ifrs_entry_point_2018-03-16.xsd';
$entryPointUrl = 'http://xbrl.ifrs.org/taxonomy/2018-03-16/full_ifrs_doc_entry_point_2018-03-16.xsd';

# $MissedScemasAA = [[7, 'http://www.xbrl.org/2006/ref-2006-02-27.xsd']]; # X => [SchemaId at which the missing schema is to be imported, url]
# 2018.11.12 Removed as though referenced the schema does not appear to be used.
# Build rep[orts:
# Schema http://www.xbrl.org/2006/ref-2006-02-27.xsd referenced in the schemaLocation of <link:linkbase at node 0 of LinkbaseId 9 http://xbrl.ifrs.org/taxonomy/2018-03-16/full_ifrs/dimensions/gre_full_ifrs-dim_2018-03-16.xml has not been imported
# but does not error
$MissedScemasAA = [[NULL]];
