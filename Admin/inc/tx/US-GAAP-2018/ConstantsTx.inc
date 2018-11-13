<?php /* \SSIM Proto\Admin\inc\tx\US-GAAP-2018\ConstantsTx.inc

XBRL US GAAP 2018 taxonomy specific stuff

History:
2018.11.08 Started empty
*/

/*
Notes and comments re US GAAP 2018

# of elements 19,494

19494 Elements.StdLabel updated.
0 Elements.TerseLabel updated.

196 Schemas and 351 Linkbases imported (547 files) comprising 385595 nodes.


42,225 rows in the Definition tab of \Pacio\Data\XBRL-Taxonomies\US-GAAP-2018\US_GAAP_Taxonomy_2018.xlsx


Max role length 193
id 431
CompensationRelatedCostsGeneralScheduleOfDeferredCompensationArrangementWithIndividualExcludingShareBasedPaymentsAndPostretirementBenefitsByTitleOfIndividualAndByTypeOfDeferredCompensationTable

\XBRL-Taxonomies\US-GAAP-2018\us-gaap-2018-01-31\elts\us-roles-2018-01-31.xsd

<link:roleType id="CompensationRelatedCostsGeneralScheduleOfDeferredCompensationArrangementWithIndividualExcludingShareBasedPaymentsAndPostretirementBenefitsByTitleOfIndividualAndByTypeOfDeferredCompensationTable" roleURI="http://fasb.org/us-gaap/role/disclosure/CompensationRelatedCostsGeneralScheduleOfDeferredCompensationArrangementWithIndividualExcludingShareBasedPaymentsAndPostretirementBenefitsByTitleOfIndividualAndByTypeOfDeferredCompensationTable">
  <link:definition>
    705000 - Disclosure - Compensation Related Costs, General, Schedule of Deferred Compensation Arrangement with Individual, Excluding Share-based Payments and Postretirement Benefits, by Title of Individual and by Type of Deferred Compensation [Table]
  </link:definition>
<link:usedOn>link:definitionLink</link:usedOn>
</link:roleType>

--

Max element name length 185:

Id 14204

name
QualitativeAndQuantitativeInformationAssetsOrLiabilitiesForTransferorsContinuingInvolvementInSecuritizationOrAssetbackedFinancingArrangementNotPreviouslyRequiredFinancialSupportProvided

StdLabel
Qualitative and Quantitative Information, Transferor's Continuing Involvement, Not Previously Required Financial Support (Deprecated 2016-01-31)

---

2 empty hypercubes
191 and 192

Id  ElId  RoleId  Dimensions
191 15133   587
192 15133   588


===========

Arcs with ArcroleId  =                                                FromId             ToId
  TARId_HypercubeDim 7 :    894   285  Hypercubes -> Dimensions       Hypercube el Id    Dimension el Id
  TARId_DimDomain    8 :    892   291  First Dimension members        Dimension el Id    DiMe el Id - not 131 because of different roles. If Dimensions table used only one role per dim then there would be 291 dima rather than 131.
  TARId_DomainMember 9 : 25,728 2,897  From domain contains To member Domain    el Id    Member el Id           - vs 1452 found in building DiMes
  TARId_DimAll      10 :    383   150  One for each Hypercube         Abstract El Id     Hypercube el Id e.g. Earnings per share [abstract] -> Earnings per share [table]. Not 146 because of 4 with 2 roles.
  TARId_DimNotAll   11 :            0  Not used
  TARId_DimDefault  12 :    892   131  From dimension To default dimension member Dimension el Id    Dimension member el Id Same # as # of Elements with TesgN = TESGN_Dimension = all dimensions have a default




Elements with TesgN  =  US G  IFRS
  TESGN_Dimension    2 : 307  131   names end with Axis  and std labels end with [axis]      2 of the US GAAP ones are typed dimensions
  TESGN_Hypercube    3:  327  146   "              Table "                       [table]


################
# US GAAP ToDo #
################

• handle typed dimensions with typedDomainRef
  See notes in BuildHypercubesDimensions.php

• handle deprecated elements
  See 2018_USGAAP_Technical_Guide_Final.pdf
  section 7 Deprecated Element Relationships

