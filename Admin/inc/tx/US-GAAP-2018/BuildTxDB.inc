<?php /* \Admin\inc\tx\US-GAAP-2018\BuildTxDB.inc

Taxonomy specific include for US GAAP 2018

History:
2018.11.09 Started

From
https://fasb.org/cs/ContentServer?c=Page&cid=1176169699514&d=&pagename=FASB%2FPage%2FSectionPage
downloaded \Pacio\Data\XBRL-Taxonomies\US-GAAP-2018\us-gaap-2018-01-31.zip
and        \Pacio\Data\XBRL-Taxonomies\US-GAAP-2018\US_GAAP_Taxonomy_2018.zip  Excel version

From https://xbrl.us/xbrl-taxonomy/2018-us-gaap/
Recommended Namespace Prefix: us-gaap
Core schema and standard labels: http://xbrl.fasb.org/us-gaap/2018/elts/us-gaap-std-2018-01-31.xsd

Or http://xbrl.fasb.org/us-gaap/2018/entire/us-gaap-entryPoint-all-2018-01-31.xsd
by looking at thedownloaded taxonomy


For available sec taxononomies see https://www.sec.gov/info/edgar/edgartaxonomies.shtml

For entry points see figure 13 page 17 in:
\Pacio\Data\XBRL-Taxonomies\US-GAAP-2018\2018_USGAAP_Technical_Guide_Final.pdf

us-gaap-entryPoint-all-wotmp-2018-01-31.xsd   DTS includes all components in all folders except Taxonomy Disclosure Templates

*/



#entryPointUrl = 'http://xbrl.fasb.org/us-gaap/2018/elts/us-gaap-std-2018-01-31.xsd';
$entryPointUrl = 'http://xbrl.fasb.org/us-gaap/2018/entire/us-gaap-entryPoint-all-2018-01-31.xsd';
#entryPointUrl = 'http://xbrl.fasb.org/us-gaap/2018/entire/us-gaap-entryPoint-std-2018-01-31.xsd';

# X => [SchemaId at which the missing schema is to be imported, url]
#MissedScemasAA = [[22, 'https://xbrl.sec.gov/currency/2017/currency-2017-01-31.xsd']]; # 21 is http://xbrl.sec.gov/country/2017/country-2017-01-31.xsd  <== Needed with std ep not all
$MissedScemasAA = [[NULL]];

/*

IFRS vs US GAAP
https://www.ifrs.com/overview/General/differences.html

typed dimension
extensible list item type
multiple to arcs

8 deprecated element types See M:\Pacio\Data\XBRL-Taxonomies\US-GAAP-2018\GAAP-Taxonomy-ReleaseNotes-2018-01-31_Final.pdf
4.1.1 Deprecated Element Types
In addition to the CN, deprecated elements are included in a definition linkbase relationship to further assist preparers in identifying replacement elements. Deprecated

Uses https://xbrl.sec.gov/currency/2017/currency-2017-01-31.xsd which is not included in the schema list.
It is in the SEC May be explicitly imported list at https://www.sec.gov/info/edgar/edgartaxonomies.shtml

*/
