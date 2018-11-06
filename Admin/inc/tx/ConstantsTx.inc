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

# Taxonomy Arcrole Id (Arcroles.Id) constants which are in TLTN_ sequence
# -------------------                                 /- TLTN_* arc (link) type
const TARId_HypercubeDim  =  1; # hypercube-dimension 1  From hypercube To dimension in the hypercube               Source (a hypercube) contains the target (a dimension) among others.
const TARId_DimDomain     =  2; # dimension-domain    1  From dimension To first dimension member of the dimension  Source (a dimension) has only the target (a domain) as its domain.
const TARId_DomainMember  =  3; # domain-member       1  From domain contains To member                             Source (a domain) contains the target (a member).
const TARId_DimAll        =  4; # all                 1  From source requires dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) to appear in the context of the primary item.
const TARId_DimNotAll     =  5; # notAll              1  From source excludes dimension members in the To hypercube Source (a primary item declaration) requires a combination of dimension members of the target (hypercube) not to appear in the context of the primary item.
const TARId_DimDefault    =  6; # dimension-default   1  From dimension To default dimension member                 Source (a dimension) declares that there is a default member that is the target of the arc (a member).
const TARId_ParentChild   =  7; # parent-child        2  From parent To child
const TARId_SummationItem =  8; # summation-item      3  From element sums To element
const TARId_ConceptLabel  =  9; # concept-label       4  From element has To label
const TARId_ConceptRef    = 10; # concept-reference   5  From element has To reference
const TARId_ElementLabel  = 11; # element-label       6  From element has To label
const TARId_ElementRef    = 12; # element-reference   6  From element has To reference


# Taxonomy Link Type enums  Arcs.TltN LinkTypeToStr() gives string form
# ------------------------
const TLTN_Definition   = 1; # <definitionLink
const TLTN_Presentation = 2; # <presentationLink
const TLTN_Calculation  = 3; # <calculationLink
const TLTN_Label        = 4; # <labelLink
const TLTN_Reference    = 5; # <referenceLink
const TLTN_GenLink      = 6; # <gen:link
#onst TLTN_Footnote     = x; # <footnoteLink Note: Footnotes are not contained in separate linkbases. The role is used only within instance documents.

# Taxonomy Elements.TetN enum
# ---------------------------                              Num    UK GAAP Num
#onst TETN_None             0; # NULL                              181
const TETN_Money         =  1; # xbrli:monetaryItemType           3247
const TETN_String        =  2; # xbrli:stringItemType             1362            string   55
const TETN_Boolean       =  3; # xbrli:booleanItemType              28
const TETN_Date          =  4; # xbrli:dateItemType                 36
const TETN_Decimal       =  5; # xbrli:decimalItemType              46            decimal   1
const TETN_Integer       =  6; # xbrli:integerItemType               9
const TETN_NonZeroDecimal=  7; # xbrli:nonZeroDecimal                1
const TETN_Share         =  8; # xbrli:sharesItemType               35
const TETN_PerShare      =  9; # num:perShareItemType
const TETN_Percent       = 10; # num:percentItemType
const TETN_Area          = 11; # num:areaItemType
const TETN_DomainItem    = 12; # nonnum:domainItemType     562
const TETN_TextBlock     = 13; # nonnum:textBlockItemType
const TETN_PureItem      = 14; # xbrli:pureItemType
const TETN_Uri           = 15; # xbrli:anyURIItemType                4            anyURI    1
const TETN_Any           = 16; # anyType                             2
const TETN_QName         = 17; # QName                               2
const TETN_Arc           = 18; # xl:arcType                          5
const TETN_Doc           = 19; # xl:documentationType                2
const TETN_Extended      = 20; # xl:extendedType                     1
const TETN_Locator       = 21; # xl:locatorType                      2
const TETN_Resource      = 22; # xl:resourceType                     1
const TETN_Simple        = 23; # xl:simpleType                       2            anySimpleType   1
const TETN_Title         = 24; # xl:titleType                        1
const TETN_GenArc        = 25; # gen:genericArcType
const TETN_Link          = 26; # gen:linkTypeWithOpenAttrs

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
#############

function ElementTypeToStr($tetN) {
  static $a = ['None', 'Money', 'String', 'Boolean', 'Date', 'Decimal', 'Integer', 'NonZeroDecimal', 'Share', 'PerShare',
    'Percent', 'Area', 'DomainItem', 'TextBlock', 'PureItem', 'Uri', 'Any', 'QName', 'Arc', 'Doc', 'Extended',
    'Locator', 'Resource', 'Simple', 'Title', 'GenArc', 'Link'];
  return $a[(int)$tetN];
}

# const TESGN_Item      = 1; # xbrli:item
# const TESGN_Dimension = 2; # xbrldt:dimensionItem
# const TESGN_Hypercube = 3; # xbrldt:hypercubeItem
function SubstGroupToStr($tesgN) {
  static $a = [NULL, 'Item', 'Dimension', 'Hypercube']; # Only the ones used
  return $a[(int)$tesgN];
}

# const TLTN_Definition   = 1; # <definitionLink
# const TLTN_Presentation = 2; # <presentationLink
# const TLTN_Calculation  = 3; # <calculationLink
# const TLTN_Label        = 4; # <labelLink
# const TLTN_Reference    = 5; # <referenceLink
# const TLTN_GenLink      = 6; # <gen:link
function LinkTypeToStr($tltN) {
  static $a = [NULL, 'Definition', 'Presentation', 'Calculation', 'Label', 'Reference', 'gen:link']; #, 'Footnote'
  return $a[$tltN];
}

function PeriodTypeToStr($N) {
  static $a = ['', 'Duration', 'Instant'];
  return $a[(int)$N];
}

function UseTypeToStr($N) {
  static $a = ['', 'Optional', 'Prohibited'];
  return $a[(int)$N];
}

function ContextTypeToStr($N) {
  static $a = ['', 'Segment, Scenario'];
  return $a[(int)$N];
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
