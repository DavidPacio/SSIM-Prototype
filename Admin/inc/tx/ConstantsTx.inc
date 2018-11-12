<?php /* \SSIM Proto\Admin\inc\tx\ConstantsTx.inc

XBRL taxonomy constants, globals, and to string functions

History:
2018.10.12 Started based on UK-IFRS-DPL version
*/

# Taxonomy Constants
# ==================
# Taxonomy Role Id (Roles.Id) for the XBRL roles
# ----------------                   Role              UsedOn
const TRId_StdLabel          =  1; # label             label
const TRId_VerboseLabel      =  2; # verboseLabel      label
const TRId_TerseLabel        =  3; # terseLabel        label
const TRId_Documentation     =  4; # documentation     label
const TRId_NetLabel          =  5; # netLabel          label
const TRId_TotalLabel        =  6; # totalLabel        label
const TRId_NegLabel          =  7; # negatedLabel      label
const TRId_NegTerseLabel     =  8; # negatedTerseLabel label
const TRId_NegTotalLabel     =  9; # negatedTotalLabel label
const TRId_PeriodStartLabel  = 10; # periodStartLabel  label
const TRId_PeriodEndLabel    = 11; # periodEndLabel    label     <=== end of labels
const TRId_Reference         = 12; # reference         reference /- References
const TRId_DisclosureRef     = 13; # disclosureRef     reference |
const TRId_ExampleRef        = 14; # exampleRef        reference |
const TRId_CommonPracticeRef = 15; # commonPracticeRef reference |
const TRId_Link              = 16; # link              link,labelLink,referenceLink

# Taxonomy Arcrole Id (Arcroles.Id) constants which are in descending TLTN_ order because of US GAAP adding lots more declaration ones
# -------------------                                 /- TLTN_* arc (link) type
const TARId_ElementRef    =  1; # element-reference   6  From element has To reference
const TARId_ElementLabel  =  2; # element-label       6  From element has To label
const TARId_ConceptRef    =  3; # concept-reference   5  From element has To reference
const TARId_ConceptLabel  =  4; # concept-label       4  From element has To label
const TARId_SummationItem =  5; # summation-item      3  From element sums To element
const TARId_ParentChild   =  6; # parent-child        2  From parent To child
const TARId_FirstDeclarationArcole = 7; #             1
const TARId_HypercubeDim  =  7; # hypercube-dimension 1  From hypercube To dimension in the hypercube               Source (a hypercube) contains the target (a dimension) among others.
const TARId_DimDomain     =  8; # dimension-domain    1  From dimension To first dimension member of the dimension  Source (a dimension) has only the target (a domain) as its domain.
const TARId_DomainMember  =  9; # domain-member       1  From domain contains To member                             Source (a domain) contains the target (a member).
const TARId_DimAll        = 10; # all                 1  From source requires dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) to appear in the context of the primary item.
const TARId_DimNotAll     = 11; # notAll              1  From source excludes dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) not to appear in the context of the primary item.
const TARId_DimDefault    = 12; # dimension-default   1  From dimension To default dimension member                 Source (a dimension) declares that there is a default member that is the target of the arc (a member).
const TARId_EssenceAlias  = 13; # essence-alias       1  To is Alias of From used by US GAAP for one of their deprecated series of arcroles
#onst TARId_DepConcepts   = 14; # dep-aggregateConcept-deprecatedPartConcept 1 From aggregate concept To deprecated part concept'], etc added by build for US GAAP

# Taxonomy Link Type enums  Arcs.TltN LinkTypeToStr() gives string form
# ------------------------
const TLTN_Definition   = 1; # <definitionLink
const TLTN_Presentation = 2; # <presentationLink
const TLTN_Calculation  = 3; # <calculationLink
const TLTN_Label        = 4; # <labelLink
const TLTN_Reference    = 5; # <referenceLink
const TLTN_GenLink      = 6; # <gen:link
#onst TLTN_Footnote     = x; # <footnoteLink Note: Footnotes are not contained in separate linkbases. The role is used only within instance documents.

# Taxonomy Elements.TetN enum Had 26 types for IFRS
# ---------------------------                              Num    UK GAAP Num
#onst TETN_None             0; # NULL                              181
const TETN_Integer       =  1; # xbrli:integerItemType               9
const TETN_PositiveInteger= 2; # xbrli:positiveIntegerItemType
const TETN_Money         =  3; # xbrli:monetaryItemType           3247
const TETN_Decimal       =  4; # xbrli:decimalItemType              46            decimal   1
const TETN_NonZeroDecimal=  5; # xbrli:nonZeroDecimal                1
const TETN_String        =  6; # xbrli:stringItemType             1362            string   55
const TETN_Boolean       =  7; # xbrli:booleanItemType              28
const TETN_Date          =  8; # xbrli:dateItemType                 36
const TETN_MonthDay      =  9; # xbrli:gMonthDayItemType
const TETN_Year          = 10; # xbrli:gYearItemType
const TETN_YearMonth     = 11; # xbrli:gYearMonthItemType
const TETN_Duration      = 12; # xbrli:durationItemType
const TETN_Share         = 13; # xbrli:sharesItemType               35
const TETN_Area          = 14; # num:areaItemType
const TETN_Energy        = 15; # num:energyItemType
const TETN_Mass          = 16; # num:massItemType
const TETN_Percent       = 17; # num:percentItemType
const TETN_PerShare      = 18; # num:perShareItemType
const TETN_Volume        = 19; # num:volumeItemType
const TETN_DomainItem    = 20; # nonnum:domainItemType     562
const TETN_TextBlock     = 21; # nonnum:textBlockItemType
const TETN_PureItem      = 22; # xbrli:pureItemType
const TETN_Uri           = 23; # xbrli:anyURIItemType                4            anyURI    1
const TETN_Any           = 24; # anyType                             2
const TETN_QName         = 25; # QName                               2
const TETN_Arc           = 26; # xl:arcType                          5
const TETN_Doc           = 27; # xl:documentationType                2
const TETN_Extended      = 28; # xl:extendedType                     1
const TETN_Locator       = 29; # xl:locatorType                      2
const TETN_Resource      = 30; # xl:resourceType                     1
const TETN_Simple        = 31; # xl:simpleType                       2            anySimpleType   1
const TETN_Title         = 32; # xl:titleType                        1
const TETN_GenArc        = 33; # gen:genericArcType
const TETN_Link          = 34; # gen:linkTypeWithOpenAttrs
# US GAAP ones from http://xbrl.sec.gov/dei/2018/dei-2018-01-31.xsd
const TETN_IndexKey              = 35; # dei:centralIndexKeyItemType       Restriction: 10 digits
const TETN_CountryCode           = 36; # dei:countryItemType      ISO code Restriction: 2 characters, 1st is an uppercase letter, 2nd is an upper case letter or digit
const TETN_CurrencyCode          = 37; # dei:currencyItemType     ISO code Restriction: 3 letter code, all uppercase
const TETN_FileNumber            = 38; # dei:fileNumberItemType            Pattern [0-9]{1,3}-[0-9]{1,8}(-.{1,4})?
const TETN_FilerCategory         = 39; # dei:filerCategoryItemType         Enum "Large Accelerated Filer", "Accelerated Filer", "Non-accelerated Filer, Smaller Reporting Company"
const TETN_FiscalPeriod          = 40; # dei:fiscalPeriodItemType          Enum "FY", "Q1", "Q2", "Q3", "Q4", "H1", "H2", "M9", "T1", "T2"," T3", "M8", "CY"
const TETN_InvCompanyCode        = 41; # dei:invCompanyType                Enum "N-1A", "N-1", "N-2", "N-3", "N-4", "N-5", "N-6", "S-1 or S-3", "S-6"
const TETN_LegalEntityIdentifier = 42; # dei:legalEntityIdentifierItemType Pattern: "[A-Z0-9]{20}"
const TETN_NineDigitCode         = 43; # dei:nineDigitItemType             Restriction: 9 digits
const TETN_SubmissionType        = 44; # dei:submissionTypeItemType        Enum "10-K", "10-KT", "10-Q"," 10-QT"," 20-F", "20-FT", "40-F", "485BPOS", "497"", 6-K"," 8-K"," F-1"," F-3", "F-4," "F-9", "F-10," "N-CSR", "N-CSR", "N-Q, ", "POS AM", "S-1", "S-3," "S-4", "S-11"," Other"," N-1A", "NCSR,"" 10"
const TETN_YesNo                 = 45; # dei:yesNoItemType                 Enum "Yes" or "No"
# US GAAP one from http://xbrl.sec.gov/invest/2013/invest-2013-01-31.xsd
const TETN_BuySell               = 46; # invest:foreignCurrencyContractTransactionItemType  Enum "Buy" or "Sell"
# US GAAP one from http://xbrl.fasb.org/srt/2018/elts/srt-2018-01-31.xsd
const TETN_ExtensibleList        = 47; # srt-types:extensibleListItemType  Std label suffix [Extensible List]
# US GAAP us-types
const TETN_YearList                      = 48; # us-types:gYearListItemType        Allows a list of years e.g. "2015 2016 2017" rather than one as for TETN_Year xbrli:gYearItemType
const TETN_PerUnit                       = 49; # us-types:perUnitItemType
const TETN_ThreeDigits                   = 50; # us-types:threeDigitItemType
const TETN_NineDigits                    = 51; # us-types:nineDigitItemType
const TETN_AuthorizedUnlimited           = 52; # us-types:authorizedUnlimitedItemType    Enum Unlimited
const TETN_FlowRate                      = 53; # us-types:flowItemType
const TETN_DistributionsReceivedApproach = 54; # us-types:distributionsReceivedApproach  Enum "Cumulative earnings", "Nature of distribution"
const TETN_InterestRateType              = 55; # us-types:interestRateItemType           Enum "Floating", "Fixed"
const TETN_RestrictedInvestmentType      = 56; # us-types:restrictedInvestmentItemType   Enum "Restricted Investment", "Restricted Investment Exempt from Registration", "Restricted Investment Not Exempt from Registration"
const TETN_InvestmentPledgedType         = 57; # us-types:investmentPledgedItemType      Enum "Investment Pledged", "Entire Investment Pledged", "Partial Investment Pledged"
const TETN_InvestmentOnLoanForShortSales = 58; # us-types:investmentOnLoanForShortSalesItemType  Enum "Investment on Loan", "Entire Investment on Loan", "Partial Investment on Loan"
const TETN_MalpracticeInsuranceClaims    = 59; # us-types:MalpracticeInsurance-OccurrenceOrClaims-madeItemType  Enum for "Occurrence", "Claims-made"
const TETN_FundedStatus                  = 60; # us-types:fundedStatusItemType Enum "Less than 65 percent", "Between 65 and less than 80 percent", "At least 80 percent", "NA"
const TETN_FundingImprovementAndRehabilitationPlan = 61; # us-types:fundingImprovementAndRehabilitationPlanItemType  Enum "No", "Pending", "Implemented", "Other", "NA"
const TETN_ZoneStatus                    = 62; # us-types:zoneStatusItemType   Enum "Green", "Yellow", "Orange", "Red", "Other", "NA"
const TETN_SurchargeType                 = 63; # us-types:surchargeItemType    Enum "No", "Yes", "NA"
const TETN_ForfeitureMethod              = 64; # us-types:forfeitureMethod     Enum "Estimating expected forfeitures", "Recognizing forfeitures when they occur"
const TETN_ElementListType               = 65; # tin-part:elementListItemType  Pattern \s*(([\i-[:]][\c-[:]]*:)?[\i-[:]][\c-[:]]*(\s+([\i-[:]][\c-[:]]*:)?[\i-[:]][\c-[:]]*)*)?\s*
const TETN_TransitionOptionList          = 65; # tin-part:TransitionOptionList Enum "Retrospective", "Prospective", "Modified Retrospective", "Modified Prospective"
const TETN_AsuNumber                     = 65; # tin-part:AsuNumber            Pattern [0-9]{4}-[0-9]{2}

# Notes
# -----
# For types see https://www.xbrl.org/utr/utr.xml
# TETN_String is also used for 'string' and 'xbrli:normalizedStringItemType' as used by US GAAP, pending discerning any need for different string types
# A list of data types used by US GAAP, and the UK specials too, is given here: https://docs.oracle.com/cd/E36352_01/epm.1112/disclosure_mgmt_user/frameset.htm?ch02s03s01s04.html

# Taxonomy Element SubstGroup enums re Elements.TesgN
# ---------------------------------                IFRS
#onst TESGN_None        0; # NULL                  Num     Elements with no SG are not stored in the Elements table
const TESGN_Item      = 1; # xbrli:item           4717
const TESGN_Dimension = 2; # xbrldt:dimensionItem  131
const TESGN_Hypercube = 3; # xbrldt:hypercubeItem  146
const TESGN_LinkPart  = 4; # link:part         /- Elements with these SGs are not stored in the Elements table
const TESGN_Arc       = 5; # xl:arc            |
const TESGN_Doc       = 6; # xl:documentation  |
const TESGN_Extended  = 7; # xl:extended       |
const TESGN_Locator   = 8; # xl:locator        |
const TESGN_Resource  = 9; # xl:resource       |
const TESGN_Simple    =10; # xl:simple         |
#onst TESGN_Tuple     =11; # xbrli:tuple       |

# Taxonomy Element PeriodType enums
# ---------------------------------
#onst TEPTN_None       0; # NULL      258
const TEPTN_Duration = 1; # duration  5098
const TEPTN_Instant  = 2; # instant   1066

# Taxonomy Elements.SignN enums replacing Elements.balance
# -----------------------------
#onst TESN_None   0; # NULL   3711
const TESN_Dr   = 1; # debit  1835
const TESN_Cr   = 2; # credit  876

# Taxonomy Arcs useEnum enums
# ------------------------
#onst TAUN_None         0; # Null      11892
const TAUN_Optional   = 1; # optional  13911
const TAUN_Prohibited = 2; # prohibited   50

# Taxonomy Context enums
# ----------------------
#onst TCN_None         0; # NULL    25807
const TCN_Segment    = 1; # segment
const TCN_Scenario   = 2; # scenario

# DimensionMembers.Bits
#onst DiMeB_None    =   0; Bit
const DiMeB_Normal  =   0; #   N  Normal i.e. no settings
const DiMeB_Default =   1; # 0 D  Taxonomy default
#onst DiMeB_BD      =   2; # 1 B  Braiins Dimension
#onst DiMeB_M       =   4; # 2 M# Mandatory (in most cases) with specific details and # as per DiMesA[DiMeId][DiMeI_MType]. See table below.
#onst DiMeB_Prop    =   8; # 3 P  Property type for use with an M DiMe e.g. Officers.Chairman, Officers.CEO....
#onst DiMeB_RO      =  16; # 4 R  Report Only = usable for reporting but not for posting
#onst DiMeB_SumKids =  32; # 5 S  DiMe kids Sum - one level down
#onst DiMeB_SumList =  64; # 6 S  DiMe list Sum
#onst DiMeB_muX     = 128; # 7 X  Mutually exclusive parent/child conditions apply
#onst DiMeB_pYa     = 256; # 8 Y  PYA (Restated)
#onst DiMeB_Zilch   = 512; # 9 Z  Not ever user selectable being reserved for Braiins RG generation e.g. Officers.AllEntityOfficers. As per DiMeB_RO with extra restriction os not being usable in a format.
#

#############
# Functions #
#############     0       1
function ElementTypeToStr($tetN) { # 2                   3        4          5                   6         7          8       9                 10      11            12
  static $sAS = ['None', 'Integer', 'Positive integer', 'Money', 'Decimal', 'Non zero decimal', 'String', 'Boolean', 'Date', 'Month Day date', 'Year', 'Year Month', 'Duration',
   # 13       14      15        16      17         18          19        20            21           22          23     24     25       26     27     28
    'Share', 'Area', 'Energy', 'Mass', 'Percent', 'PerShare', 'Volume', 'DomainItem', 'TextBlock', 'PureItem', 'Uri', 'Any', 'QName', 'Arc', 'Doc', 'Extended',
   # 29         30          31        32       33        34
    'Locator', 'Resource', 'Simple', 'Title', 'GenArc', 'Link',
   # 35          36             37              38            39               40              41                42                       43               44
    'IndexKey', 'CountryCode', 'CurrencyCode', 'FileNumber', 'FilerCategory', 'FiscalPeriod', 'InvCompanyCode', 'LegalEntityIdentifier', 'NineDigitCode', 'SubmissionType',
   #  45       46         47                48          49         50             51            52                     53
     'YesNo', 'BuySell', 'ExtensibleList', 'YearList', 'PerUnit', 'ThreeDigits', 'NineDigits', 'AuthorizedUnlimited', 'FlowRate',
   #  54                               55                  56                          57                       58                                   59
     'DistributionsReceivedApproach', 'InterestRateType', 'RestrictedInvestmentType', 'InvestmentPledgedType', 'InvestmentOnLoanForShortSalesType', 'MalpracticeInsuranceClaimsMadeType',
   #  60              61                                             62            63               64                  65                 66                      67
     'FundedStatus', 'FundingImprovementAndRehabilitationPlanType', 'ZoneStatus', 'SurchargeType', 'ForfeitureMethod', 'ElementListType', 'TransitionOptionList', 'AsuNumber'];
  return $sAS[(int)$tetN];
}

# const TESGN_Item      = 1; # xbrli:item
# const TESGN_Dimension = 2; # xbrldt:dimensionItem
# const TESGN_Hypercube = 3; # xbrldt:hypercubeItem
function SubstGroupToStr($tesgN) {
  static $sAS = [NULL, 'Item', 'Dimension', 'Hypercube']; # Only the ones used
  return $sAS[(int)$tesgN];
}

# const TLTN_Definition   = 1; # <definitionLink
# const TLTN_Presentation = 2; # <presentationLink
# const TLTN_Calculation  = 3; # <calculationLink
# const TLTN_Label        = 4; # <labelLink
# const TLTN_Reference    = 5; # <referenceLink
# const TLTN_GenLink      = 6; # <gen:link
function LinkTypeToStr($tltN) {
  static $sAS = [NULL, 'Definition', 'Presentation', 'Calculation', 'Label', 'Reference', 'gen:link']; #, 'Footnote'
  return $sAS[$tltN];
}

function PeriodTypeToStr($N) {
  static $sAS = ['', 'Duration', 'Instant'];
  return $sAS[(int)$N];
}

function UseTypeToStr($N) {
  static $sAS = ['', 'Optional', 'Prohibited'];
  return $sAS[(int)$N];
}

function ContextTypeToStr($N) {
  static $sAS = ['', 'Segment, Scenario'];
  return $sAS[(int)$N];
}

function BoolToStr($B) {
  # if ($b === '') return ''; # null == default whether true or false
  return $B ? 'true' : 'false';
}

function TUCNStr($N) {
  static $cons = ' OMU';
  return $cons[(int)$N];
}

?>
