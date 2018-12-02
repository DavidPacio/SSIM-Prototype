<?php /* \SSIM Proto\Admin\inc\tx\IFRS-2018\ConstantsTx.inc

XBRL IFRS 2018 taxonomy specific stuff

Plus currently unused stuff from the 2013 work

Plus some counts and comments on IFRS 2018

See also for IFRS numbers etc:
https://www.ifrs.org/-/media/feature/standards/taxonomy/2018/ifrs-taxonomy-illustrated/ifrs_taxonomy_architecture_2018.pdf

History:
2018.10.27 Started
2018.11.08 DimId_Max and HyId_Max taken OoS
*/

# DiMeIds
# const DiMeId_PyaAmount           =  23;
# const DiMeId_PyaPriorPeriodIncr  =  24; # IncrDecr
# const DiMeId_PyaAcctPolicyIncr   =  25;
# const DiMeId_PyaMaterialErrIncr  =  26;
# const DiMeId_PyaOriginalAmount   =  27;
# const DiMeId_Max                 = 1718; # Max
# const DiMeId_Unallocated         = 9999; # Pseudo DiMe for summing use when
                                         # - a base only (no DiMes) Bro is added to a target which has DiMes so that the base of the target will = the sum of the primary postings
                                         # - a summed DiMe is not available in the target
##############
# Dimensions #
##############
# Dimension Ids or DimIds
# const DimId_Group         =  1;
# const DimId_ConDis        =  2;
# const DimId_Restated      =  3;
# const DimId_Excepts       =  4;
# #           EquityClasses    5
# #           OpSegs           6
# #           ProdsServs       7
# #           MajCusts         8
# #           BizCombos        9
# const DimId_PPEClasses    = 10;
# const DimId_IFAClasses    = 11;
# #           FIs             15
# #     DimId_MPeriods        20
# const DimId_ProvClasses   = 22;
# #           PensionSchemes  23
# #           Subsidiaries    28
# #           ShareClasses    34
# #           ShareTypes      35
# #           TPAType         37
# #           TPAStatus       38
# #           Countries       42
# #           Currencies      43
# #           Exchanges       44
# #           Languages       45
# #           Activity        46 /- DPL ones
# #           ExpenseType     47 |
# #           ExceptNon       48 |
# #           Analysis        49 |
# #           GroupTrans      50 |
# const DimId_Max            131

# Hypercube Ids
#onst HY_Id_Basic     =  1;
#onst HY_Id_Income    =  2;
#onst HY_Id_Officers  = 36;
#onst HY_Id_TPAs      = 39;
#onst HY_Id_Countries = 40;
#onst HyId_Max        = 150 ; # Excl Empty

# #onst MType_TFA_LandBldg= 2; # TFAClasses.LandBuildings - Buildings    -> TFAOwnership.OwnedOrFreehold, ShortLeaseholdProperties, and LongLeaseholdProperties (185, 187, 188)
# #onst MType_TFA_Other   = 3; # TFAClasses.InvestProperties - Others    -> TFAOwnership.OwnedOrFreehold, Leased (185, 186)
# #onst MType_Director    = 4; # Officers.Director1       - Director40   -> Officers.Chairman, CEO, ChairmanCEO, CoSecDirector, OfficerType.Executive, OfficerType.Nonexecutive  (419, 420, 421, 424, 425, 487, 488)
# #onst MType_PartnerLLP  = 5; # Officers.PartnerLLP1     - PartnerLLP20 -> Officers.ChiefPartnerLLP (422)
#
# # Inner indices for $DiMesA, array of Dimension Member Info by DiMeId -> [DimId, DiMeBits, PropSet, ETypeList, MuxList, SumList]
# const DiMeI_DimId     = 0; # DimId Dimension Id
# const DiMeI_Bits      = 1; # Bits  Dimension Member Bits
# const DiMeI_MuxListA  = 2; # Array of DiMeIds with which this DiMe is Mutually Exclusive - DiMeB_muX bit is set when this applies
# const DiMeI_SumListA  = 3; # Array of DiMeIds to be summed to this DiMe                  - DiMeB_Sum bit is set when this applies or has children

# DimensionMembers.Bits
# ---------------------
# Constants from ConstantsRg.inc used

# const ElId_NotApplicable  = 4815;
# const ElId_EmptyHypercube = 4459;
#
# # List of Tx Elements which have StartEnd values. See Doc/Tx/UK-IFRS-DPL/StartEndPeriodNotes.txt for its derivation.
# $StartEndTxIdsGA=[788, 1070, 1697, 2235, 2247, 4626, 4633, 4742, 4746, 5783, 6036, 7915, 8524];

/*

###################################
# Notes and comments re IFRS 2018 #
###################################

4,992 Elements.StdLabel updated.
19 Elements.TerseLabel updated.

54 Schemas and 270 Linkbases imported (324 files) comprising 88,688 nodes.

Done in 135.4 secs


Longest element name: 234 characters
Id 3203
IncreaseDecreaseThroughReclassificationAdjustmentsInPeriodReserveOfGainsAndLossesOnFinancialAssetsMeasuredAtFairValueThroughOtherComprehensiveIncomeRelatedToInsuranceContractsToWhichParagraphsC18bC19bC24bAndC24cOfIFRS17HaveBeenApplied

Longest role 31 characters
Id 105
ifrs_12_2018-03-16_role-825700a
[825700a] Notes - Interests in other entities

and 39 similar 31 character long ones

---

SELECT ElId FROM DimensionMembers group by ElId    546
SELECT * FROM Elementswhere Hypercubes is not NULL 536

SELECT * FROM DimensionMembers where ElId not in (SELECT Id FROM Elements where Hypercubes is not NULL)

Id  DimId ElId  RoleId  Bits  Level
18    7   261   23  1   0
19    7   259   23  0   0
21    7   2248  23  0   1
567   38  573   18  1   0
568   38  4662  18  0   0
572   40  175   22  0   0
574   40  2248  22  0   1
600   45  838   21  1   0
637   48  4475  20  0   0
638   48  3027  20  0   0
1164  102   3024  19  0   3

due to roles presumably


# Roles as they came in IFRS without any order rearrangement:
#  Id  Role              UsedOn                      Uses  FileIds
#   1  label             label                       5186  8,63,71,89,110,116,122,128,134,138,142,146,151,156,161,166,170,175,179,184,190,196,202,208,216,224,228,232,237,242,248,254,260,266,272,278,284,290,296,300,306,311,316,321
#   2  verboseLabel      label                          1  8
#   3  terseLabel        label                         57  8,71,100,218,219
#   4  documentation     label                          1  8
#   5  link              link,labelLink,referenceLink 127  9,63,64,71,89,90,108,110,111,113,116,117,119,122,123,125,128,129,131,134,135,137,138,139,141,142,143,145,146,147,149,151,152,154,156,157,159,161,162,164,166,167,169,170,171,173,175,176,178,179,180,182,184,185,187,190,191,193,196,197,199,202,203,205,208,209,211,216,217,222,224,225,227,228,229,231,232,233,235,237,238,240,242,243,245,248,249,251,254,255,257,260,261,263,266,267,269,272,273,275,278,279,281,284,285,287,290,291,293,296,297,299,300,301,303,306,307,309,311,312,314,316,317,319,321,322,324
#   6  reference         reference                     46 10,64,90,111,117,123,129,135,139,143,147,152,157,162,167,171,176,180,185,191,197,203,209,217,225,229,233,238,243,249,255,261,267,273,279,285,291,297,301,307,312,317,322
# 200  negatedLabel      label                        403 71,92,93,94,95,96,97,98,99,100,118,124,136,163,192,198,204,210,218,219,221,250,256,268,274,286,292,308
# 201  periodStartLabel  label                         87 71,98,106,118,124,136,163,192,198,204,210,218,219,221,250,256,262,274,280,286,292,308
# 202  periodEndLabel    label                         87 71,98,106,118,124,136,163,192,198,204,210,218,219,221,250,256,262,274,280,286,292,308
# 203  totalLabel        label                        443 71,92,93,94,95,96,97,98,99,100,106,107,118,124,130,136,153,163,177,192,198,204,210,219,221,250,256,262,268,274,280,286,292,308
# 204  negatedTotalLabel label                         22 71,97,100,124,136,198,204,308
# 205  negatedTerseLabel label                         66 71,94,95,163,218,219
# 206  netLabel          label                         92 71,94,99,100,118,136,204,218,219,274,286,298,308,313
# 207  disclosureRef     reference                   1589 108,113,119,125,131,137,141,145,149,154,159,164,169,173,178,182,187,193,199,205,211,222,227,231,235,240,245,251,257,263,269,275,281,287,293,299,303,309,314,319,324
# 208  exampleRef        reference                    335 108,113,119,125,137,159,178,187,193,199,211,222,227,240,245,251,257,263,269,275,281,287,293,299,309,314
# 209  commonPracticeRef reference                    111 108,119,125,131,137,141,145,149,178,187,193,199,205,211,222,240,257,275,281,287,293,299,309,314


/*
IFRS 2018 numbers:
Arcs with ArcroleId  =                                     FromId             ToId
  TARId_HypercubeDim 7 :  285  Hypercubes -> Dimensions       Hypercube el Id    Dimension el Id
  TARId_DimDomain    8 :  291  First Dimension members        Dimension el Id    DiMe el Id - not 131 because of different roles. If Dimensions table used only one role per dim then there would be 291 dima rather than 131.
  TARId_DomainMember 9 : 2897  From domain contains To member Domain    el Id    Member el Id           - vs 1452 found in building DiMes
  TARId_DimAll      10 :  150  One for each Hypercube         Abstract El Id     Hypercube el Id e.g. Earnings per share [abstract] -> Earnings per share [table]. Not 146 because of 4 with 2 roles.
  TARId_DimNotAll   11 :    0  Not used
  TARId_DimDefault  12 :  131  From dimension To default dimension member Dimension el Id    Dimension member el Id Same # as # of Elements with TesgN = TESGN_Dimension = all dimensions have a default

TARId_DomainMember numbers:
select * from Arcs where ArcroleId=9 Order by FromId,ToId,PRoleId
2897 results starting as below but only roughly half of these (1452) are dimension members. The others are for notes blocks but not in hypercube/dimension form.
Id   TltN  FromId ToId PRoleId ArcroleId ArcOrder
 9956   1   21    22   51      9         10000000
10229   1   21    22   53      9         10000000
12256   1   21    22   82      9         10000000
12324   1   21    22   83      9         10000000
12675   1   21    22   86      9         10000000
12882   1   21    22   87      9         10000000
 9974   1   21    27   51      9         20000000
10234   1   21    27   53      9         20000000
12271   1   21    27   82      9         20000000
12338   1   21    27   83      9         20000000
12686   1   21    27   86      9         20000000
12894   1   21    27   87      9         20000000
17981   1   33    32   147     9         50000000

select * from Arcs where ArcroleId=9 group by FromId Order by FromId
gives 579 results as also does:
select * from Elements where Id in (select FromId from Arcs where ArcroleId=3) order by Id

Elements with TesgN  =
  TESGN_Dimension    2: 131   names end with Axis  and std labels end with [axis]
  TESGN_Hypercube    3: 146   "              Table "                       [table]

  Members: 562 for std labels ending with [member] -   query: StdLabel like '%[member]'
               (name ending Member gives 561 re 3325 name: InsuranceContractsMember2017 fir which StdLabel is Insurance contracts [member]
                query to find StdLabel like '%[member]' and not name like '%member'
  But the number of domain members from DimensionMembers grouped by ElId is 546 leaving 16 other "member" elements? As below:

  select * from Elements where StdLabel like '%[member]' and Id not in (select ElId from DimensionMembers group by ElId)

Id  NsId TetN TesgN name                                           StdLabel
  31  2   12  1   AccumulatedOtherComprehensiveIncomeMember        Accumulated other comprehensive income [member]
  82  2   12  1   AdditionalPaidinCapitalMember                    Additional paid-in capital [member]
 377  2   12  1   CapitalRedemptionReserveMember                   Capital redemption reserve [member]
 381  2   12  1   CapitalReserveMember                             Capital reserve [member]
3505  2   12  1   LaterThanFourMonthsMember                        Later than four months [member]
3507  2   12  1   LaterThanOneMonthAndNotLaterThanSixMonthsMember  Later than one month and not later than six months...
3516  2   12  1   LaterThanSixMonthsMember                         Later than six months [member]
3519  2   12  1   LaterThanThreeMonthsAndNotLaterThanFourMonthsMembe...   Later than three months and not later than four mo...
3525  2   12  1   LaterThanThreeYearsMember                        Later than three years [member]
3528  2   12  1   LaterThanTwoYearsAndNotLaterThanFiveYearsMember  Later than two years and not later than five years...
3659  2   12  1   MergerReserveMember                              Merger reserve [member]
3702  2   12  1   MiscellaneousOtherReservesMember                 Miscellaneous other reserves [member]
3915  2   12  1   OnDemandMember                                   On demand [member]
4498  2   12  1   ReserveOfEquityComponentOfConvertibleInstrumentsMe...   Reserve of equity component of convertible instrum...
4519  2   12  1   ReserveOfOverlayApproachMember                   Reserve of overlay approach [member]
4771  2   12  1   StatutoryReserveMember                           Statutory reserve [member]


Elements query with StdLabel like '%[member]' and Id not in (select ToId from Arcs where ArcroleId=2)
gives 433
562 - 433 = 129

select * from Arcs where ArcroleId=6 and ToId not in (select ToId from Arcs where ArcroleId=2)
-> zero i.e. all default members are in TARId_DimDomain

select * from Arcs where ArcroleId=9 TARId_DomainMember
Id  TltN FromId  ToId  PRoleId ArcroleId ArcOrder  ArcUseN priority  Weight  PrefLabelRoleId ClosedB ContextN  UsableB TargetRoleId  FileId
1   2     4526    4157  19     9         10000000  NULL  NULL  NULL  NULL  NULL  NULL  NULL  NULL  56
2   2     3025    2699  19     9         10000000  NULL  NULL  NULL  NULL  NULL  NULL  NULL  NULL  56


select * from Elements where Id in (4526, 4157)
Id  NsId TetN  TesgN name                    StdLabel  PeriodN         SignN abstract  nillable  Hypercubes  FileId
4526  2  12    1     RestatedMember          Currently stated [member]   1   NULL  1   1   NULL  53
4157  2  12    1     PreviouslyStatedMember  Previously stated [member]  1   NULL  1   1   NULL  53
         |     |- TESGN_Item
         |- TETN_DomainItem
*/

# Label arcs have the role 'concept-label' which signifies it is always used to point from a concept a label. The arcs can therefore never describe cyclic relationships betwe en concepts.

/* As read from IFRS
Id  Arcrole              usedOn          definition                                             cyclesAllowed Uses FileId
1   hypercube-dimension  definitionArc   Source (a hypercube) contains the target (a dimens...   none          286 2,83,84,85,86,87,88,109,115,121,127,133,155,165,18...
2   dimension-domain     definitionArc   Source (a dimension) has only the target (a domain...   none          292 2,56,57,58,59,60,61,83,84,85,86,87,88,109,115,121,...
3   domain-member        definitionArc   Source (a domain) contains the target (a member).       undirected   2898 2,56,57,59,60,61,83,84,85,86,87,88,109,115,121,127...
4   all                  definitionArc   Source (a primary item declaration) requires a com...   undirected    151 2,83,84,85,86,87,88,109,115,121,127,133,155,165,18...
5   notAll               definitionArc   Source (a primary item declaration) requires a com...   undirected      1 2
6   dimension-default    definitionArc   Source (a dimension) declares that there is a defa...   none          132 2,62
7   element-label        gen:arc         element has label                                       undirected    194 8,63,89,110,116,122,128,134,138,142,146,151,156,16...
8   element-reference    gen:arc         element has reference                                   undirected    193 10,64,90,111,117,123,129,135,139,143,147,152,157,1...
9   parent-child                         NULL                                                    NULL         6020 65,66,67,68,69,70,91,92,93,94,95,96,97,98,99,100,1...
10  concept-label                        NULL                                                    NULL         5571 71
11  summation-item                       NULL                                                    NULL         1198 72,73,74,75,76,77,78,79,80,81,82,114,120,126,132,1...
12  concept-reference                    NULL                                                    NULL         5628 108,113,119,125,131,137,141,145,149,154,159,164,16...
*/


