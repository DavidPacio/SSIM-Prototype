<?php /* \Admin\inc\tx\IFRS-2018\BuildTxDB.inc

Taxonomy specific include for IFRS-2018

History:
2018.10.13 Started

ToDo djh??
====

*/

$entryPointUrl = 'http://xbrl.ifrs.org/taxonomy/2018-03-16/full_ifrs_entry_point_2018-03-16.xsd';

$MissedScemasAA = [[7, 'http://www.xbrl.org/2006/ref-2006-02-27.xsd']]; # X => [SchemaId at which the missing schema is to be imported, url]

const TaxonomyElementNsId = 2; # NS ifrs-full. Only store elements with this namespace in the Elements table.
