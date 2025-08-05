/* eslint-disable react-hooks/rules-of-hooks */
import React, { useState, useEffect } from "react";
import { Row, Col } from "react-bootstrap";
import { useHistory } from "react-router-dom";
import { TypeReturn } from "modules/type";
import moment from "moment";
import { differenceInDays } from "date-fns";
import { useMediaPredicate } from "react-media-hook";
import { useDispatch, useSelector } from "react-redux";
import Skeleton from "react-loading-skeleton";
import "modules/quotesPage/quotePage.scss";
import { useForm } from "react-hook-form";
import _ from "lodash";
import { useIdleTimer } from "react-idle-timer";
import {
  GridCard,
  GridSkeleton,
} from "modules/quotesPage/quoteCard/gridCard/gridCard";
import {
  QuoteCard,
  QuoteSkelton,
} from "modules/quotesPage/quoteCard/defaultCard/quoteCard";
import { AddOnsCard } from "modules/quotesPage/addOnCard/addOnCard";
import SendQuotes from "components/Popup/sendQuote/SendQuotes";
import KnowMorePopup from "./quotesPopup/knowMorePopup/knowMore/knowMorePopup";
import ClaimModal from "modules/quotesPage/quotesPopup/renewal-claim/renewal-claim";
//prettier-ignore
import { FloatButton, ToasterOla, Toaster, Loader} from "components";
//prettier-ignore
import { scrollToTop, toDate, AccessControl, fetchToken, Decrypt } from "utils";
import { useLocation } from "react-router";
//prettier-ignore
import { getQuotesData, getPremData, clear, shortTermType,
         setBuyNowSingleQuoteUpdate, SetLoadingCancelled, CancelAll,
         shortTerm, selectedTab, setzdAvailablity, getPayAsYourDrive
       } from "modules/quotesPage/quote.slice";
//prettier-ignore
import { setTempData } from "./filterConatiner/quoteFilter.slice";
import { CompareContainer } from "./compare-container/compare-container";
//prettier-ignore
import { set_temp_data, tabClick as TabClick, gstStatus, LinkTrigger, clear as clrHome, share } from "modules/Home/home.slice";
import PrevInsurerPopup2 from "modules/quotesPage/quotesPopup/prevInsurerPopup/prevInsurerPopup2";
import TimeoutPopup from "./AbiblPopup/TimeoutPopup";
//prettier-ignore
import { Url, set_temp_data as setProposalTemp } from "modules/proposal/proposal.slice";
import Styled from "./quotesStyle";
import { MobileBottomDrawer } from "./Mobile/MobileBottomDrawer";
import AddonsandOther from "./Mobile/AddonsandOther";
import Tabs from "./Mobile/Tabs";
import SortButton from "./component/SortButton";
import CardView from "./component/CardView";
//prettier-ignore
import { sortOptions } from "./quoteUtil";
import { FilterContainer } from "./filterConatiner/filterConatiner";
import { Filters } from "./filterConatiner/Filters";
import { quoteFetch_construct } from "modules/quotesPage/quote-constructor";
//prettier-ignore
import { FetchCompare, _fetchTerm,
         _calculateAddons, previousPolicyTypeIdentifierCode,
         relevance, GetValidAdditionalKeys
       } from "modules/quotesPage/quote-logic";
//prettier-ignore
import { useViewHook, useB2CAuth, usePdfExpiry, useInvalidatePromise,
         useLinkDelivery, useBreakinTransitions, useAccessControl,
         useAddonConfig, useRefetch, usePostTransaction,
         usePrefill, useZDCoverPrefill, useJourneyProcess,
         useDuplicateEnquiry, useLogoMaster, 
         useEnquiryOrBreakinCheck, useQuoteInitialiation,
         usePolicyTypePrefill, useToaster_PreviousPolicyType,
         useToaster_AddonPrefill, useToaster_ExipryAssumption,
         useFetch_Comprehensive, useFetch_ThirdParty, useFetch_ShortTerm,
         useQuoteLoadProgress, useSingleQuoteError, useGrouping,
         useGroupingShortTerm, useShareDrawer, useRenewalTPSelection,
         useZeroDepError, useMaxInbuiltAddonsCount, useKnowMoreSetter,
         useOnPopupCloseReload, useQuotePageTracking, useErrorHandling
        } from './custom-hooks/quote-page-hooks/quote-page-hook';
import { useSelectedSorting } from "./custom-hooks/sorting/selected-sorting";
//prettier-ignore
import { handleViewExt, extPath, NoOfDays } from "./quote-helper";
import { _planTracking } from "analytics/quote-page/quote-tracking";
import { _discount, _filterTpTenure } from "modules/quotesPage/quote-logic";
import Progressbar from "./_component/progressbar";
import { showingErrors } from "./_component/quote-error";
import { QuotesLength } from "./_component/total-quotes";
import { useProfileTracking } from "modules/proposal/proposal-hooks";
import { useComprehensiveSorting } from "./custom-hooks/sorting/comprehensive-sorting/comprehensive-sorting";
import { useThirdPartySorting } from "./custom-hooks/sorting/tp-sorting/tp-sorting";
import { useShortTerm3Sorting } from "./custom-hooks/sorting/short-term-sorting/short-term-three";
import { useShortTerm6Sorting } from "./custom-hooks/sorting/short-term-sorting/short-term-six";

export const QuotesPage = (props) => {
  const lessthan993 = useMediaPredicate("(max-width: 993px)");
  const lessthan1350 = useMediaPredicate("(max-width: 1350px)");
  const lessthan360 = useMediaPredicate("(max-width: 360px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan376 = useMediaPredicate("(max-width: 376px)");
  const lessthan420 = useMediaPredicate("(max-width: 420px)");
  const lessthan413 = useMediaPredicate("(max-width: 413px)");

  //Home-States
  const { temp_data, prefillLoading, tabClick, theme_conf, encryptUser } =
    useSelector((state) => state.home);

  const [tab, setTab] = useState("tab1");
  const [mobileComp, setMobileComp] = useState(false);
  //long term policies
  const [longTerm2, setLongterm2] = useState(false);
  const [longTerm3, setLongterm3] = useState(false);
  //relevance quote toggle
  const [isRelevant, setRelevant] = useState(false);
  //relevance quote toggle
  const [renewalFilter, setRenewalFilter] = useState(
    import.meta.env.VITE_BROKER === "BAJAJ" &&
      !(
        import.meta.env.VITE_BROKER === "BAJAJ" &&
        import.meta.env.VITE_BASENAME === "general-insurance"
      )
  );

  const { typeAccess } = useSelector((state) => state.login);
  const { saveQuote, tempData, error, errorSpecific } = useSelector(
    (state) => state.quoteFilter
  );
  const { duplicateEnquiry } = useSelector((state) => state.proposal);
  //prettier-ignore
  const { quotesList, quoteComprehesive, quotetThirdParty, quoteShortTerm,
          versionId, loading, errorIcBased, saveQuoteLoader, updateQuoteLoader,
          masterLogos, quotesLoaded, addOnsAndOthers, buyNowSingleQuoteUpdate,
          loadingCancelled, saveQuoteError, addonConfig, interimLoading,
        } = useSelector((state) => state.quotes);

  const history = useHistory();
  const dispatch = useDispatch();
  const location = useLocation();
  let query = new URLSearchParams(location.search);

  const enquiry_id = query.get("enquiry_id");
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const { type } = props?.match?.params;
  const typeId = query.get("typeid");
  const journey_type = query.get("journey_type");
  const keyTrigger = query.get("key");
  const shared = query.get("shared");
  const decryptShare = shared && !_.isEmpty(shared) && Decrypt(shared);

  const _stToken = fetchToken();

  const [selectedGarage, setSelectedGarage] = useState(null);
  const [openGarageModal, setOpenGarageModal] = useState(false);

  const ConfigNcb =
    theme_conf?.broker_config?.ncbconfig === "Yes" ;

  const date = query.get("expiryDate");
  let userAgent = navigator.userAgent;
  let isMobileIOS = false; //initiate as false
  // IOS device detection
  if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream && lessthan767) {
    isMobileIOS = true;
  }

  const checkSellerType = !_.isEmpty(temp_data?.agentDetails)
    ? temp_data?.agentDetails?.map((seller) => seller.sellerType)
    : [];

  //switching the screen orientation
  const isStoreView = localStorage.getItem("view");
  const [view, setView] = useState(isStoreView ? isStoreView : "grid");
  const handleView = (view) => handleViewExt(view, setView);

  //states used for progressPercent of the quote loading indicator
  const [progressPercent, setProgressPercent] = useState(false);
  const [quotesLoadingComplted, setQuotesLoadingCompleted] = useState(false);
  const [quotesLoadingInitiated, setQuotesLoadingInitiated] = useState(false);

  //setting card-view | grid card | horizontal card
  useViewHook(theme_conf, setView);

  //Block B2C Journey in some conditions.
  useB2CAuth(temp_data, checkSellerType, token, journey_type);

  //Link-Click & Delivery
  useLinkDelivery(dispatch, keyTrigger, LinkTrigger);

  //Analytics | user profile tracking
  useProfileTracking(dispatch, temp_data, encryptUser);
  useQuotePageTracking(temp_data);

  //pdf expiry
  let b = moment().format("DD-MM-YYYY");
  let diffDays = date && differenceInDays(toDate(b), toDate(date));

  //Pdf expiry hook
  //prettier-ignore
  usePdfExpiry(date, diffDays, NoOfDays, enquiry_id, token, journey_type, typeId, shared);

  //Cancel API promises.
  useInvalidatePromise(dispatch, SetLoadingCancelled, setProposalTemp);

  useEffect(() => {
    if (loadingCancelled) {
      setTimeout(() => {
        dispatch(SetLoadingCancelled(false));
      }, 2000);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loadingCancelled]);
  //Clear enquiry state
  useEffect(() => {
    dispatch(clrHome("enquiry_id"));
  }, []);

  //Access-Control
  useAccessControl(AccessControl, typeAccess, type, history);

  //scroll to top
  useEffect(() => {
    import.meta.env.VITE_BROKER !== "ABIBL" && scrollToTop();
  }, []);

  const [initalExecution, setExecution] = useState(false);

  //This Hook is used for following conditions.
  /*
  a) Rollover to Breakin Transition (excluding breakin journey's and bike product).
  b) New Bussiness Breaking Block.
  */
  //prettier-ignore
  useBreakinTransitions(dispatch, temp_data, initalExecution, setExecution, enquiry_id, token, type)

  //---------------------Prefill & addon config Api-----------------------
  //Addon-config | preselect LL paid for chola
  useAddonConfig(dispatch, enquiry_id);

  //Prefill data after addon-config | Prefill API along with addon config
  useRefetch(dispatch, enquiry_id, addonConfig);

  //Prefill API without addon-config
  usePrefill(dispatch, enquiry_id);

  //Error Handling | SaveQuoteRequestData Error Swal
  useErrorHandling(
    dispatch,
    error,
    tempData,
    enquiry_id,
    errorSpecific,
    _stToken
  );

  //set zdAvailablity
  useEffect(() => {
    dispatch(setzdAvailablity([]));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  //zero-dep multi cover marker state
  const [zdlp, setZdlp] = useState(
    temp_data?.quoteLog?.premiumJson?.claimsCovered
      ? temp_data?.quoteLog?.premiumJson?.claimsCovered
      : "ONE"
  );
  const [claimList, setClaimList] = useState([]);
  //zero-dep multi cover marker state for PAYD
  const [zdlp_gdd, setZdlp_gdd] = useState(
    temp_data?.quoteLog?.premiumJson?.claimsCovered
      ? temp_data?.quoteLog?.premiumJson?.claimsCovered
      : "ONE"
  );
  const [claimList_gdd, setClaimList_gdd] = useState([]);

  //zerodep multiple covers | prefill | Go digit
  useZDCoverPrefill(temp_data, setZdlp, setZdlp_gdd);

  /* 
   This hook in used for following conditions
      a) To update journey URL | updateJourneyUrl - API
      b) In case when journey stage is "Payment Initiated" or "Payment Failed"
         a duplicated enquiry ID is generated.
  */
  const [limiter, setLimiter] = useState(0);
  useJourneyProcess(dispatch, enquiry_id, temp_data, limiter, setLimiter, Url);

  //Payment Incomplete
  //This hook is used to reload page with newly generated enquiry ID.
  useDuplicateEnquiry(
    dispatch,
    duplicateEnquiry,
    typeId,
    journey_type,
    _stToken,
    type,
    token,
    shared
  );

  //Post transaction handling | Used when journey is already completed
  usePostTransaction(dispatch, temp_data, enquiry_id, _stToken);

  //Fetch LOGO Master (IC)
  useLogoMaster(dispatch, masterLogos, location, type, enquiry_id);

  /* 
   This hook in used for following conditions
      a) To check the enquiry ID | This action also cancels all the APIS of product
      b) To check if breakin ID is created for this journey | This action also cancels all the APIS of product.
   Both of the above conditions result in redirection of the user to lead page/dashboard
  */
  //prettier-ignore
  useEnquiryOrBreakinCheck(dispatch, location, type, temp_data, enquiry_id, history, token, typeId, journey_type, _stToken, shared)

  const { register, watch, control } = useForm({});

  //Share quotes states
  const [sendQuotes, setSendQuotes] = useState(false);
  const [sendPdf, setSendPdf] = useState(false);
  //States used for storing the policy IDs of the quotes selected for compare
  const [compare, setCompare] = useState(false);

  //This hook is used to set selected sorting type.
  const { sortBy, setSortBy } = useSelectedSorting({
    temp_data,
    quoteComprehesive,
    quotetThirdParty,
    quoteShortTerm,
    quotesLoaded,
    watch,
    enquiry_id,
  });

  //quote loading initiatialization. This is used to show the progressbar.
  useQuoteInitialiation(quotesLoaded, setQuotesLoadingInitiated);

  //Auto selection of Third party if previously selected.
  usePolicyTypePrefill(temp_data, tab);

  //-----------------calling the basic getProductApi--------------------------
  //incase of renewal
  const [showClaimModal, setClaimModal] = useState(false);
  //Toaster- Claim
  const [callToasterClaim, setCallToasterClaim] = useState(false);
  //Toaster- Renewal/Addons
  const [callToasterAddon, setCallToasterAddon] = useState(false);
  //Toaster- prev popup
  const [callToasterPreIc, setCallToasterPreIc] = useState(false);
  //Expiry Modification
  const [callToasterExpiry, setCallToasterExpiry] = useState(false);

  /* This hook is used to display a toaster that notifies the user that
  previous policy type was assumed as "Comprehensive" */
  useToaster_PreviousPolicyType(temp_data, setCallToasterPreIc);

  /* This hook is used to display a toaster that notifies the user that
  addons from previous policy has been prefilled & More addons can be added
  from the left addon panel */
  //prettier-ignore
  useToaster_AddonPrefill(dispatch, temp_data, setCallToasterPreIc, setCallToasterAddon, enquiry_id)

  /* This hook is used to display a toaster that notifies the user that
  previous policy expiry date has been assumed on basis of the vehicle registration
  date or selected previous policy type. */
  useToaster_ExipryAssumption(
    dispatch,
    temp_data,
    TypeReturn(type),
    setCallToasterExpiry
  );
  //Fetch all products ( Quote List ) | quotelist's length must be 0 to execute this.
  useEffect(() => {
    if (
      _.isBoolean(saveQuote) &&
      quotesList?.length === 0 &&
      !buyNowSingleQuoteUpdate
    ) {
      setQuotesLoadingInitiated(true);
      dispatch(clear());
      //prettier-ignore
      dispatch(getQuotesData(quoteFetch_construct(temp_data, tempData, TypeReturn(type), enquiry_id,shared, theme_conf), decryptShare));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [saveQuote, quotesList?.length, buyNowSingleQuoteUpdate]);

  //Fetch Quotes
  //Comprehensive
  //prettier-ignore
  const FetchQuotes = (listArray, policyType, quotesType, isPayD, isTowing, multiPolicies) => {
    // Separate renewal and non-renewal quotes
    const renewalQuotes = listArray.filter((x) => x.isRenewal === "Y");
    const nonRenewalQuotes = listArray.filter((x) => x.isRenewal !== "Y");
    const quotes = [...renewalQuotes, ...nonRenewalQuotes];
    let allPackages = isPayD && listArray?.map((i) => i?.policyId);
    // Iterate through each quote and dispatch the data
    quotes.forEach((el) => {
      // Prepare data for dispatch
      const data = {
        // Use temp_data?.enquiry_id if available, otherwise use enquiry_id
        enquiryId: temp_data?.enquiry_id || enquiry_id,
        //prettier-ignore
        policyId: multiPolicies ? el.policyId : isPayD ? isTowing ? allPackages[0] : allPackages : el.policyId,
        // Include redirection_url and companyLogo if available
        ...(el.redirection_url && {
          redirection_url: el.redirection_url,
          companyLogo: el.companyId?.logo,
        }),
        // Include is_renewal if it's a renewal quote
        ...(el.isRenewal === "Y" && { is_renewal: "Y" }),
        // Include addons if available
        ...(el.addons && { addons: el.addons }),
        //Short Term 3/6  Marker
        ...(el?.premiumTypeCode && {premiumTypeCode: el.premiumTypeCode }),
        //Pay As You Drive
        ...(isPayD && {
          distance: isPayD,
          multiQuote: "Y",
          company_alias: el?.companyAlias,
        }),
        // Blocked quotes with message || This is temporary logic. This will be moved to backend later.
        ...(el.blockedMessage && { blockedMessage: el.blockedMessage }),
        //Push commission data.
        ...(el?.commission && { commission: el.commission }),
      };

      // Extract companyAlias and companyId for cleaner code
      const { companyAlias: ic, companyId: icId } = el;
      const typePolicy = policyType;
      // Get typeUrl using TypeReturn function
      const typeUrl = TypeReturn(type);

      // Dispatch data with a delay of 100 milliseconds
      //remove the policy ids for PAYD quotes of the selected IC
      setTimeout(() => {
        //prettier-ignore
        !isPayD 
        ? dispatch(getPremData(ic, icId, data, typePolicy, quotesType, typeUrl)) 
        : dispatch(getPayAsYourDrive(ic, icId, data, typePolicy, quotesType, typeUrl));
      }, 100);
    });
  };

  /* --- Fetch Quotes --- */
  //Fetch Comprehensive Quotes
  //prettier-ignore
  useFetch_Comprehensive(FetchQuotes, quotesList, quoteComprehesive, buyNowSingleQuoteUpdate);

  //Fetch Third party Quotes
  //prettier-ignore
  useFetch_ThirdParty(FetchQuotes, quotesList, quotetThirdParty, buyNowSingleQuoteUpdate);

  //Fetch Short Term Quotes
  //prettier-ignore
  useFetch_ShortTerm(FetchQuotes, quotesList, quoteShortTerm, buyNowSingleQuoteUpdate);
  /* -x- Fetch Quotes -x- */

  // filter compare data
  const [shortTerm3, setShortTerm3] = useState(false);
  const [shortTerm6, setShortTerm6] = useState(false);
  //prevInsPopup
  const [prevPopup2, setPrevPopup2] = useState(false);
  const [selectedId, setSelectedId] = useState(false);
  const [selectedCompanyName, setSelectedCompanyName] = useState(false);
  const [selectedCompanyAlias, setSelectedCompanyAlias] = useState(false);
  const [selectedIcId, setSelectedIcId] = useState(false);
  const [applicableAddonsLits, setApplicableAddonsLits] = useState(false);
  const [quoteData, setQuoteData] = useState(false);

  /* This hook is used to calculate and store the percentage 
  of total quotes that loads on the page in real time */
  useQuoteLoadProgress(
    dispatch,
    quotesList,
    quotesLoaded,
    loading,
    setQuotesLoadingCompleted,
    setProgressPercent,
    updateQuoteLoader
  );

  //Closing "prevPopuptwo" on specific errors. | This happens when journey stage is payment in initiated.
  useSingleQuoteError(dispatch, saveQuoteError, enquiry_id, history, _stToken);

  //Grouping addon based for private car
  //States are used to store the best match of each IC.
  const [quoteComprehesiveGrouped, setQuoteComprehesiveGrouped] =
    useState(quoteComprehesive);
  const [quoteComprehesiveGrouped1, setQuoteComprehesiveGrouped1] = useState(
    []
  );
  const [quoteTpGrouped1, setQuoteTpGrouped1] = useState([]);

  /* Grouping Logic
  List of operations: 
  All these are done for a normal quote and PAYD quote seperately
  a) Group By IC
  b) Fetch list of markers ( Zerodep claims )
  c) Get selected marker.
  d) Get Best match.
  */
  //State for multi update quotes
  const [multiUpdateQuotes, setMultiUpdateQuotes] = useState({});
  //Grouped params
  const longtermParams = { longTerm2, longTerm3 };
  useGrouping(
    addOnsAndOthers,
    quoteComprehesive,
    setClaimList,
    setClaimList_gdd,
    zdlp,
    zdlp_gdd,
    setQuoteComprehesiveGrouped,
    isRelevant,
    sortBy,
    tab,
    longtermParams,
    setMultiUpdateQuotes
  );

  /* ------------ handling short term ------------ */
  const [ungroupedQuoteShortTerm3, setUngroupedQuoteShortTerm3] =
    useState(quoteShortTerm);
  const [ungroupedQuoteShortTerm6, setUngroupedQuoteShortTerm6] =
    useState(quoteShortTerm);
  const [groupedQuoteShortTerm3, setGroupedQuoteShortTerm3] = useState([]);
  const [groupedQuoteShortTerm6, setGroupedQuoteShortTerm6] = useState([]);
  const [quoteShortTerm3State, setQuoteShortTerm3] = useState([]);
  const [quoteShortTerm6State, setQuoteShortTerm6] = useState([]);

  //setting short term priority
  const quoteShortTerm3 = !_.isEmpty(quoteShortTerm3State)
    ? quoteShortTerm3State
    : groupedQuoteShortTerm3;
  const quoteShortTerm6 = !_.isEmpty(quoteShortTerm6State)
    ? quoteShortTerm6State
    : groupedQuoteShortTerm6;

  let SelectedPlans = watch("checkmark");
  SelectedPlans = !_.isEmpty(SelectedPlans)
    ? SelectedPlans?.map((item) => Number(item))
    : [];

  const CompareData = FetchCompare(
    tab,
    shortTerm3,
    quoteShortTerm3,
    shortTerm6,
    quoteShortTerm6,
    quoteComprehesiveGrouped1,
    quoteTpGrouped1
  )?.filter(
    (elem) =>
      !_.isEmpty(SelectedPlans) &&
      SelectedPlans?.includes(Number(elem?.policyId))
  );

  useEffect(() => {
    if (!_.isEmpty(CompareData)) {
      setCompare(true);
    } else {
      setCompare(false);
    }
  }, [CompareData]);

  //sorting comprehensive quotes
  useComprehensiveSorting({
    quoteComprehesiveGrouped,
    quotesLoadingComplted,
    quotesLoaded,
    addOnsAndOthers,
    type,
    temp_data,
    sortBy,
    setQuoteComprehesiveGrouped1,
    zdlp,
    zdlp_gdd,
  });

  //storing flag in redux for send quote.
  useEffect(() => {
    dispatch(shortTerm(shortTerm3 ? 3 : shortTerm6 ? 6 : ""));
    dispatch(selectedTab(tab));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [shortTerm3, shortTerm6, tab]);

  //separating short term policies by tenure of 3/6
  useEffect(() => {
    if (quoteShortTerm) {
      setUngroupedQuoteShortTerm6(_fetchTerm(quoteShortTerm, 6));
      setUngroupedQuoteShortTerm3(_fetchTerm(quoteShortTerm, 3));
    }
  }, [quoteShortTerm]);

  //short term 3/6 best match
  useGroupingShortTerm(
    addOnsAndOthers,
    shortTerm3,
    ungroupedQuoteShortTerm3,
    shortTerm6,
    ungroupedQuoteShortTerm6,
    isRelevant,
    setGroupedQuoteShortTerm3,
    setGroupedQuoteShortTerm6
  );

  //dispatching short term 6 or short term 3 state
  useEffect(() => {
    dispatch(
      shortTermType(
        (shortTerm3 && !_.isEmpty(quoteShortTerm3)) ||
          (shortTerm6 && !_.isEmpty(quoteShortTerm6))
          ? shortTerm3
            ? quoteShortTerm3
            : quoteShortTerm6
          : false
      )
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [shortTerm3, shortTerm6, quoteShortTerm3, quoteShortTerm6]);

  useShortTerm6Sorting({
    quoteShortTerm,
    quotesLoadingComplted,
    quotesLoaded,
    groupedQuoteShortTerm6,
    addOnsAndOthers,
    sortBy,
    isRelevant,
    longTerm2,
    longTerm3,
    temp_data,
    type,
    setQuoteShortTerm6,
  });

  useShortTerm3Sorting({
    quoteShortTerm,
    quotesLoadingComplted,
    quotesLoaded,
    groupedQuoteShortTerm3,
    addOnsAndOthers,
    sortBy,
    isRelevant,
    longTerm2,
    longTerm3,
    temp_data,
    type,
    setQuoteShortTerm3,
  });

  useThirdPartySorting({
    quotetThirdParty,
    quotesLoadingComplted,
    quotesLoaded,
    isRelevant,
    addOnsAndOthers,
    temp_data,
    longtermParams,
    setQuoteTpGrouped1,
    sortBy,
  });

  useEffect(() => {
    dispatch(
      setTempData({
        quoteComprehesiveGrouped: quoteComprehesiveGrouped1,
        quoteThirdParty: isRelevant
          ? relevance(
              quotetThirdParty,
              addOnsAndOthers,
              GetValidAdditionalKeys,
              true,
              temp_data?.ownerTypeId === 2
            )
          : quotetThirdParty,
      })
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [quoteComprehesiveGrouped1, quotetThirdParty, isRelevant]);

  //Analytics | All Plans
  useEffect(() => {
    if (window?.webengage && window.webengage.user) {
      if (quotesLoadingComplted && !quotesLoaded) {
        let comp =
          !_.isEmpty(quoteComprehesiveGrouped1) && quoteComprehesiveGrouped1[0]
            ? _.uniqBy(quoteComprehesiveGrouped1, "modifiedAlias")
            : _.uniqBy(quoteComprehesiveGrouped, "modifiedAlias");
        let shortTerm = [
          ...(!_.isEmpty(quoteShortTerm3) && quoteShortTerm3[0]
            ? _.uniqBy(quoteShortTerm3, "company_alias")
            : []),
          ...(!_.isEmpty(quoteShortTerm6) && quoteShortTerm6[0]
            ? _.uniqBy(quoteShortTerm6, "company_alias")
            : []),
        ];
        let tp =
          !_.isEmpty(quoteTpGrouped1) && quoteTpGrouped1[0]
            ? _.uniqBy(quoteTpGrouped1, "policyId")
            : isRelevant
            ? _.uniqBy(quoteTpGrouped1, "policyId")
            : _.uniqBy(quotetThirdParty, "policyId");
        let all = [...comp, ...shortTerm, ...tp];
        _planTracking(
          tab === "tab1" ? [...comp, ...shortTerm] : tab === "tab2" ? tp : all,
          temp_data,
          TypeReturn(type),
          addOnsAndOthers?.selectedAddons
        );
      }
    }
  }, [quotesLoaded, quotesLoadingComplted, tab]);

  const BrokerList = !_.isEmpty(theme_conf?.broker_config?.gst)
    ? theme_conf?.broker_config?.gst === "Yes"
      ? true
      : false
    : false;

  // master ON/OFF condition for config
  const [gstToggle, setGstToggle] = useState(BrokerList ? true : false);
  const [daysToExpiry, setDaysToExpiry] = useState(false);

  //------------single reload------------
  useEffect(() => {
    if (prevPopup2 === false) {
      dispatch(setBuyNowSingleQuoteUpdate(false));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [prevPopup2]);

  //---------------reload quotes on popup close-------------
  //For Previous Insurer Popup 2 - used in case of COMP journey
  useOnPopupCloseReload(dispatch, temp_data, prevPopup2);
  //-------------know more logic--------------------
  const [knowMore, setKnowMore] = useState(false);
  const [knowMoreObject, setKnowMoreObject] = useState({});
  const [selectedKnowMore, setSelectedKnowMore] = useState(false);
  const [knowMoreQuote, setKnowMoreQuote] = useState(false);

  useEffect(() => {
    setKnowMore(false);
  }, [tab]);

  //This hook is used to set know more object.
  //pretter-ignore
  useKnowMoreSetter(
    knowMoreObject,
    setKnowMoreQuote,
    tab,
    addOnsAndOthers,
    quoteComprehesiveGrouped1
  );

  useEffect(() => {
    if (!knowMore) {
      setKnowMoreObject(false);
      setKnowMoreQuote(false);
    }
  }, [knowMore]);

  //------------------finding max of inbuilt--------------------
  const [maxAddonsMotor, setMaxAddonsMotor] = useState(0);

  //This hook iterates over all quotes and finds out the maximum number of inbuilt addons present
  useMaxInbuiltAddonsCount(
    shortTerm3,
    quoteShortTerm3,
    shortTerm6,
    quoteShortTerm6,
    quoteComprehesiveGrouped1,
    quoteComprehesiveGrouped,
    addOnsAndOthers,
    setMaxAddonsMotor,
    zdlp,
    zdlp_gdd
  );

  //---------- toast ola conditions---------------
  const [toasterShown, setToasterShown] = useState(true);
  const [callToaster, setCallToaster] = useState(false);
  const [shareQuotesFromToaster, setShareQuotesFromToaster] = useState(false);
  const [toasterLimit, setToasterLimit] = useState(0);
  let ut =
    //home state
    temp_data?.agentDetails &&
    !_.isEmpty(temp_data?.agentDetails) &&
    !_.isEmpty(temp_data?.agentDetails?.find((o) => o?.sellerType === "E"));

  useEffect(() => {
    if (
      (temp_data?.expiry || temp_data?.newCar) &&
      toasterShown &&
      import.meta.env.VITE_BROKER === "OLA" &&
      token &&
      ut &&
      !toasterLimit
    ) {
      setToasterLimit(1);
      setTimeout(() => {
        setCallToaster(true);
      }, 3000);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.expiry, toasterShown, temp_data?.newCar]);

  //------------------- addon drawer for mobile ui------------------------
  const [addonDrawer, setAddonDrawer] = useState(false);
  const toggleDrawer = (anchor, open) => (event) => {
    if (
      event?.type === "keydown" &&
      (event?.key === "Tab" || event?.key === "Shift")
    ) {
      return;
    }
    setAddonDrawer({ ...addonDrawer, [anchor]: open });
  };

  // ----------------Showing Errors---------------------
  const errorCondition =
    shortTerm3 || shortTerm6 ? "shortTerm" : "comprehensive";
  const [ErrorComprehensive, setErrorComprehensive] = useState(
    errorIcBased
      .filter((id) => id.type === errorCondition)
      .filter((id) => id.zeroDepError !== true)
  );

  //-----------filtering zero dep error when zero dep is selected-------------
  //prettier-ignore
  useZeroDepError(addOnsAndOthers, setErrorComprehensive, errorIcBased, errorCondition, shortTerm3, shortTerm6)

  let ErrorTp = errorIcBased.filter((id) => id.type === "third_party");
  let finalErrorComp = ErrorComprehensive.map(function (obj) {
    return obj.ic;
  });

  let groupedQuoteList = (
    !shortTerm3 && !shortTerm6
      ? quoteComprehesiveGrouped1 || []
      : shortTerm3
      ? quoteShortTerm3
      : shortTerm6
      ? quoteShortTerm6
      : [] || []
  )?.map((item) => item?.company_alias);

  var filterErrorComp = _.difference(finalErrorComp, groupedQuoteList);
  let finalErrorTp = ErrorTp.map(function (obj) {
    return obj.ic;
  });

  const getIcLogoUrl = (ic) => {
    let Logo = _.filter(masterLogos, {
      companyAlias: ic,
    }).map((v) => v.logoUrl);
    return Logo;
  };

  const getErrorMsgComp = (item) => {
    let errorName = ErrorComprehensive.filter((x) => x.ic === item);
    let errorMessage = errorName[0]?.message;

    // if (errorMessage && errorMessage?.length > 100) {
    //   errorMessage = errorMessage?.slice(0, 100) + "...";
    // }
    return errorMessage;
  };

  const getErrorMsgTp = (item) => {
    let errorName = ErrorTp.filter((x) => x.ic === item);
    let errorMessage = errorName[0]?.message;

    // if (errorMessage && errorMessage?.length > 100) {
    //   errorMessage = errorMessage?.slice(0, 100) + "...";
    // }
    return errorMessage;
  };

  //Activity Timeout
  const [timerShow, setTimerShow] = useState(false);

  const handleOnIdle = () => {
    setTimerShow(true);
  };

  const { getRemainingTime, getLastActiveTime } = useIdleTimer({
    timeout:
      (theme_conf?.broker_config?.time_out * 1
        ? theme_conf?.broker_config?.time_out * 1
        : 15) *
      1000 *
      60,
    onIdle: handleOnIdle,
    debounce: 500,
  });

  // checking any popup open or not
  const [popupOpen, setPopupOpen] = useState(false);
  useEffect(() => {
    if (prevPopup2 || sendQuotes || compare) {
      setPopupOpen(true);
    } else {
      setPopupOpen(false);
    }
  }, [prevPopup2, sendQuotes, compare]);

  const [assistedMode, setAssistedMode] = useState(false);
  const [homeStateData, setHomeStateData] = useState(false);
  const [filterStateData, setFilterStateData] = useState(false);
  //NCB config
  const showPrevPopUp = (homeStateDataPar, filterStateDataPar) => {
    setHomeStateData(homeStateDataPar);
    setFilterStateData(filterStateDataPar);
    setAssistedMode(true);
    setPrevPopup2(true);
  };

  const onCloseAssisted = () => [
    setAssistedMode(false),
    setPrevPopup2(false),
    setHomeStateData(false),
    setFilterStateData(false),
  ];

  //renewal third party click
  useEffect(() => {
    if (tabClick) {
      dispatch(TabClick(false));
      document.getElementById("tab2") &&
        document.getElementById("tab2").click();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tabClick]);

  useEffect(() => {
    dispatch(gstStatus(gstToggle));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [gstToggle]);

  //Renewal third-party click when redirected from backend url
  useRenewalTPSelection(temp_data);

  //This hook is used to maintain position and display states of share drawer
  useShareDrawer(sendQuotes, addonDrawer, prevPopup2);

  //Policy type code identifier (3+3/5+5 are excluded)
  const policyTypeCode = () =>
    previousPolicyTypeIdentifierCode(tempData, temp_data, TypeReturn(type));

  //Filter renewal quotes
  const filterRenewal = (item) => {
    return (
      (renewalFilter && item?.isRenewal === "Y") ||
      !renewalFilter ||
      temp_data?.corporateVehiclesQuoteRequest?.isRenewal !== "Y" ||
      temp_data?.corporateVehiclesQuoteRequest?.frontendTags
    );
  };

  return (
    <>
      <TimeoutPopup
        enquiry_id={enquiry_id}
        show={timerShow}
        onClose={() => setTimerShow(false)}
        type={TypeReturn(type)}
        TempData={temp_data}
      />
      <Styled.MainContainer id={"mainContainerQuotes"}>
        {lessthan993 && (
          <MobileBottomDrawer
            popupOpen={popupOpen}
            addonDrawer={addonDrawer}
            compare={compare}
            toggleDrawer={toggleDrawer}
            setSendQuotes={setSendQuotes}
            theme_conf={theme_conf}
            quoteComprehesiveGrouped1={quoteComprehesiveGrouped1}
            tab={tab}
            setMobileComp={setMobileComp}
            quoteComprehesive={quoteComprehesive}
            quotetThirdParty={quotetThirdParty}
            quoteShortTerm={quoteShortTerm}
            quotesLoaded={quotesLoaded}
          />
        )}

        <ToasterOla
          callToaster={callToaster}
          setCall={setCallToaster}
          setToasterShown={setToasterShown}
          setShareQuotesFromToaster={setShareQuotesFromToaster}
          setEdit={setSendQuotes}
          type={type}
        />
        <Toaster
          Theme={{}}
          callToaster={callToasterClaim}
          setCall={setCallToasterClaim}
          content={
            "We have assumed that no claims were made in your previous policy."
          }
          buttonText={"Edit"}
          setEdit={() => setClaimModal(true)}
          type={type}
        />
        <Toaster
          Theme={{}}
          callToaster={callToasterPreIc}
          setCall={setCallToasterPreIc}
          content={`We have assumed your previous policy as Comprehensive. Click on edit to change your previous policy type.`}
          buttonText={"Edit"}
          setEdit={() =>
            document.getElementById("policyPopupId") &&
            document.getElementById("policyPopupId")?.click()
          }
          type={type}
        />
        <Toaster
          Theme={{}}
          callToaster={callToasterAddon}
          setCall={setCallToasterAddon}
          content={`We've fetched the add-ons as per your previous policy. You can add more from the left side bar`}
          buttonText={"Okay"}
          setEdit={() => {}}
          type={type}
          noButton
        />
        <Toaster
          Theme={{}}
          callToaster={callToasterExpiry}
          setCall={setCallToasterExpiry}
          content={
            callToasterExpiry === "assumption"
              ? `We have assumed your previous policy expiry date w.r.t to the vehicle invoice year & previous policy type.`
              : callToasterExpiry === "registration"
              ? `We have assumed your previous policy expiry date w.r.t your selected vehicle invoice date`
              : `Changes detected in previous policy type. Please verify your previous policy expiry date.`
          }
          buttonText={"Okay"}
          setEdit={() => {}}
          type={type}
          noButton
        />
        <Row>
          <FilterContainer
            type={type}
            typeId={typeId}
            quote={
              tab === "tab1"
                ? shortTerm3
                  ? quoteShortTerm3
                  : shortTerm6
                  ? quoteShortTerm6
                  : TypeReturn(type) !== "cv"
                  ? quoteComprehesiveGrouped1
                  : quoteComprehesive
                : quotetThirdParty
            }
            allQuoteloading={
              !quotesLoadingComplted &&
              quotesLoaded > 0 &&
              !quotesLoadingInitiated
            }
            setPopupOpen={setPopupOpen}
            isMobileIOS={isMobileIOS}
            assistedMode={assistedMode}
            showPrevPopUp={showPrevPopUp}
            ConfigNcb={ConfigNcb}
            policyTypeCode={policyTypeCode}
            theme_conf={theme_conf}
          />
        </Row>
        <Styled.NonStickyRows>
          <Row>
            <Col lg={12} md={12}>
              <Filters
                compare={compare}
                quote={
                  tab === "tab1"
                    ? shortTerm3
                      ? quoteShortTerm3
                      : shortTerm6
                      ? quoteShortTerm6
                      : TypeReturn(type) !== "cv"
                      ? quoteComprehesiveGrouped1
                      : quoteComprehesive
                    : quotetThirdParty
                }
                setSortBy={setSortBy}
                gstToggle={gstToggle}
                setGstToggle={setGstToggle}
                daysToExpiry={daysToExpiry}
                setDaysToExpiry={setDaysToExpiry}
                allQuoteloading={!quotesLoadingComplted && quotesLoaded > 0}
                setPopupOpen={setPopupOpen}
                loadingNTooltip={
                  !quotesLoadingComplted &&
                  quotesLoaded > 0 &&
                  !quotesLoadingInitiated
                }
              />
            </Col>
          </Row>
          {lessthan993 && (
            <AddonsandOther
              temp_data={temp_data}
              addOnsAndOthers={addOnsAndOthers}
              lessthan360={lessthan360}
              toggleDrawer={toggleDrawer}
              addonDrawer={addonDrawer}
              setAddonDrawer={setAddonDrawer}
              tab={tab}
              type={type}
              setShortTerm3={setShortTerm3}
              setShortTerm6={setShortTerm6}
              policyTypeCode={policyTypeCode}
              longTerm2={longTerm2}
              longTerm3={longTerm3}
              setLongterm2={setLongterm2}
              setLongterm3={setLongterm3}
              setRelevant={setRelevant}
              isRelevant={isRelevant}
              setRenewalFilter={setRenewalFilter}
              renewalFilter={renewalFilter}
              setQuoteComprehesiveGrouped={setQuoteComprehesiveGrouped}
              setQuoteComprehesiveGrouped1={setQuoteComprehesiveGrouped1}
              setUngroupedQuoteShortTerm3={setUngroupedQuoteShortTerm3}
              setUngroupedQuoteShortTerm6={setUngroupedQuoteShortTerm6}
              setGroupedQuoteShortTerm3={setGroupedQuoteShortTerm3}
              setGroupedQuoteShortTerm6={setGroupedQuoteShortTerm6}
              setQuoteShortTerm3={setQuoteShortTerm3}
              setQuoteShortTerm6={setQuoteShortTerm6}
              setQuoteTpGrouped1={setQuoteTpGrouped1}
              gstToggle={gstToggle}
              setGstToggle={setGstToggle}
            />
          )}
          <Row>
            <Tabs
              prefillLoading={prefillLoading}
              updateQuoteLoader={updateQuoteLoader}
              temp_data={temp_data}
              setTab={setTab}
              tab={tab}
              type={type}
              isMobileIOS={isMobileIOS}
              lessthan993={lessthan993}
              lessthan376={lessthan376}
              lessthan413={lessthan413}
              lessthan420={lessthan420}
            />
            <SortButton
              quotesLoaded={quotesLoaded}
              control={control}
              sortOptions={sortOptions(extPath)}
              sortBy={sortBy}
              setSortBy={setSortBy}
              extPath={extPath}
            />
            {import.meta.env.VITE_PROD !== "YES" &&
              !theme_conf?.broker_config?.defaultquote && (
                <CardView
                  quotesLoaded={quotesLoaded}
                  lessthan767={lessthan767}
                  lessthan993={lessthan993}
                  handleView={handleView}
                  view={view}
                />
              )}
          </Row>
          <Row
            style={{
              padding: lessthan993
                ? lessthan993
                  ? " 10px 35px"
                  : "10px 50px 10px 50px"
                : "10px 40px 10px 90px",
              ...(lessthan993 && { position: "relative", bottom: "100px" }),
            }}
          >
            {!lessthan993 && (
              <Col lg={3} md={12}>
                <div
                  style={{
                    pointerEvents:
                      prefillLoading || updateQuoteLoader ? "none" : "",
                  }}
                >
                  <AddOnsCard
                    tab={tab}
                    type={TypeReturn(type)}
                    setShortTerm3={setShortTerm3}
                    setShortTerm6={setShortTerm6}
                    policyTypeCode={policyTypeCode}
                    setRelevant={setRelevant}
                    isRelevant={isRelevant}
                    setRenewalFilter={setRenewalFilter}
                    renewalFilter={renewalFilter}
                    setSortBy={setSortBy}
                    sortBy={sortBy}
                    longTerm2={longTerm2}
                    longTerm3={longTerm3}
                    setLongterm2={setLongterm2}
                    setLongterm3={setLongterm3}
                    setQuoteComprehesiveGrouped={setQuoteComprehesiveGrouped}
                    setQuoteComprehesiveGrouped1={setQuoteComprehesiveGrouped1}
                    setUngroupedQuoteShortTerm3={setUngroupedQuoteShortTerm3}
                    setUngroupedQuoteShortTerm6={setUngroupedQuoteShortTerm6}
                    setGroupedQuoteShortTerm3={setGroupedQuoteShortTerm3}
                    setGroupedQuoteShortTerm6={setGroupedQuoteShortTerm6}
                    setQuoteShortTerm3={setQuoteShortTerm3}
                    setQuoteShortTerm6={setQuoteShortTerm6}
                    setQuoteTpGrouped1={setQuoteTpGrouped1}
                    gstToggle={gstToggle}
                    setGstToggle={setGstToggle}
                  />
                </div>
              </Col>
            )}
            <Col lg={9} md={12} className="quoteConatinerCards">
              {prefillLoading || updateQuoteLoader ? (
                <Styled.FilterTopBoxTitle
                  compare={compare}
                  align={"center"}
                  exp={true}
                >
                  <Skeleton
                    width={115}
                    height={30}
                    style={{
                      position: "relative",
                      top: "6px",
                      display: "inline-block",
                    }}
                  ></Skeleton>
                </Styled.FilterTopBoxTitle>
              ) : (
                <QuotesLength
                  compare={compare}
                  tab={tab}
                  shortTerm3={shortTerm3}
                  quoteShortTerm3={quoteShortTerm3}
                  shortTerm6={shortTerm6}
                  quoteShortTerm6={quoteShortTerm6}
                  quoteComprehesiveGrouped={quoteComprehesiveGrouped}
                  renewalFilter={renewalFilter}
                  isRelevant={isRelevant}
                  temp_data={temp_data}
                  quotesList={quotesList}
                  quotesLoaded={quotesLoaded}
                  filterErrorComp={filterErrorComp}
                  finalErrorTp={finalErrorTp}
                  quotetThirdParty={
                    isRelevant
                      ? relevance(
                          quotetThirdParty,
                          addOnsAndOthers,
                          GetValidAdditionalKeys,
                          true,
                          temp_data?.ownerTypeId === 2
                        )
                      : quotetThirdParty
                  }
                />
              )}

              {(!quotesLoadingComplted ||
                quotesLoaded <
                  (quotesList?.third_party
                    ? quotesList?.third_party?.length
                    : 0) +
                    (quotesList?.comprehensive
                      ? quotesList?.comprehensive?.length
                      : 0) +
                    (quotesList?.short_term
                      ? quotesList?.short_term?.length
                      : 0)) &&
                quotesLoaded > 0 && (
                  <Progressbar progressPercent={progressPercent} />
                )}
              <Row
                style={{
                  width: lessthan993 ? "unset" : lessthan1350 ? "99%" : "97%",
                  marginTop: "35px",
                }}
              >
                {tab === "tab1" && !shortTerm3 && !shortTerm6 && (
                  <>
                    {(!_.isEmpty(quoteComprehesiveGrouped1) &&
                    quoteComprehesiveGrouped1[0]
                      ? _.uniqBy(quoteComprehesiveGrouped1, "modifiedAlias")
                      : _.uniqBy(quoteComprehesiveGrouped, "modifiedAlias")
                    )?.map(
                      (item, index) =>
                        filterRenewal(item) && (
                          <>
                            {view === "grid" ? (
                              <QuoteCard
                                quote={
                                  quoteComprehesiveGrouped1[index] ||
                                  quoteComprehesiveGrouped[index]
                                }
                                progressPercent={progressPercent}
                                CompareData={CompareData}
                                date={date}
                                NoOfDays={NoOfDays}
                                diffDays={diffDays}
                                register={register}
                                index={index}
                                compare={compare}
                                lessthan767={lessthan767}
                                length={CompareData?.length}
                                watch={watch}
                                onCompare={!_.isEmpty(CompareData)}
                                type={type}
                                setPrevPopup={setPrevPopup2}
                                setQuoteData={setQuoteData}
                                prevPopup={prevPopup2}
                                setSelectedId={setSelectedId}
                                popupCard={false}
                                setSelectedCompanyName={setSelectedCompanyName}
                                setSelectedCompanyAlias={
                                  setSelectedCompanyAlias
                                }
                                setSelectedIcId={setSelectedIcId}
                                gstToggle={gstToggle}
                                maxAddonsMotor={maxAddonsMotor}
                                setKnowMoreObject={setKnowMoreObject}
                                setKnowMore={setKnowMore}
                                knowMore={knowMore}
                                setSelectedKnowMore={setSelectedKnowMore}
                                quoteComprehesiveGrouped={
                                  quoteComprehesiveGrouped1
                                    ? quoteComprehesiveGrouped1
                                    : quoteComprehesiveGrouped
                                }
                                knowMoreCompAlias={
                                  knowMoreObject?.quote?.modifiedAlias ||
                                  knowMoreObject?.quote?.companyAlias
                                }
                                allQuoteloading={
                                  !quotesLoadingComplted && quotesLoaded > 0
                                }
                                sendQuotes={sendQuotes}
                                setApplicableAddonsLits={
                                  setApplicableAddonsLits
                                }
                                setSendQuotes={setSendQuotes}
                                typeId={typeId}
                                isMobileIOS={isMobileIOS}
                                journey_type={journey_type}
                                setZdlp={setZdlp}
                                zdlp={zdlp}
                                claimList={_.compact(claimList)}
                                setZdlp_gdd={setZdlp_gdd}
                                zdlp_gdd={zdlp_gdd}
                                claimList_gdd={_.compact(claimList_gdd)}
                                mobileComp={mobileComp}
                                setMobileComp={setMobileComp}
                                loadingNTooltip={
                                  !quotesLoadingComplted &&
                                  quotesLoaded > 0 &&
                                  !quotesLoadingInitiated
                                }
                                filterRenewal={filterRenewal}
                                renewalFilter={renewalFilter}
                                FetchQuotes={FetchQuotes}
                                multiUpdateQuotes={multiUpdateQuotes}
                              />
                            ) : (
                              <GridCard
                                quote={
                                  quoteComprehesiveGrouped1[index] ||
                                  quoteComprehesiveGrouped[index]
                                }
                                CompareData={CompareData}
                                date={date}
                                progressPercent={progressPercent}
                                NoOfDays={NoOfDays}
                                diffDays={diffDays}
                                register={register}
                                index={index}
                                compare={compare}
                                lessthan767={lessthan767}
                                length={CompareData?.length}
                                watch={watch}
                                onCompare={!_.isEmpty(CompareData)}
                                type={type}
                                setPrevPopup={setPrevPopup2}
                                setQuoteData={setQuoteData}
                                prevPopup={prevPopup2}
                                setSelectedId={setSelectedId}
                                popupCard={false}
                                setSelectedCompanyName={setSelectedCompanyName}
                                setSelectedCompanyAlias={
                                  setSelectedCompanyAlias
                                }
                                setSelectedIcId={setSelectedIcId}
                                gstToggle={gstToggle}
                                maxAddonsMotor={maxAddonsMotor}
                                setKnowMoreObject={setKnowMoreObject}
                                setKnowMore={setKnowMore}
                                knowMore={knowMore}
                                setSelectedKnowMore={setSelectedKnowMore}
                                quoteComprehesiveGrouped={
                                  quoteComprehesiveGrouped1
                                    ? quoteComprehesiveGrouped1
                                    : quoteComprehesiveGrouped
                                }
                                knowMoreCompAlias={
                                  knowMoreObject?.quote?.modifiedAlias ||
                                  knowMoreObject?.quote?.companyAlias
                                }
                                allQuoteloading={
                                  !quotesLoadingComplted && quotesLoaded > 0
                                }
                                sendQuotes={sendQuotes}
                                setApplicableAddonsLits={
                                  setApplicableAddonsLits
                                }
                                setSendQuotes={setSendQuotes}
                                typeId={typeId}
                                isMobileIOS={isMobileIOS}
                                journey_type={journey_type}
                                setZdlp={setZdlp}
                                zdlp={zdlp}
                                claimList={_.compact(claimList)}
                                setZdlp_gdd={setZdlp_gdd}
                                zdlp_gdd={zdlp_gdd}
                                claimList_gdd={_.compact(claimList_gdd)}
                                mobileComp={mobileComp}
                                setMobileComp={setMobileComp}
                                filterRenewal={filterRenewal}
                                renewalFilter={renewalFilter}
                                FetchQuotes={FetchQuotes}
                                multiUpdateQuotes={multiUpdateQuotes}
                              />
                            )}
                          </>
                        )
                    )}
                    {_.isEmpty(quoteComprehesive) &&
                      progressPercent === 100 &&
                      (!prefillLoading || !updateQuoteLoader) &&
                      !loading &&
                      quotesLoaded >=
                        (quotesList?.third_party
                          ? quotesList?.third_party?.length
                          : 0) +
                          (quotesList?.comprehensive
                            ? quotesList?.comprehensive?.length
                            : 0) &&
                      _.isEmpty(temp_data) &&
                      quotesLoadingInitiated &&
                      interimLoading &&
                      !date && (
                        <Styled.NoQuote>
                          <img
                            src={`${extPath}/assets/images/nodata3.png`}
                            alt="nodata"
                            height="200"
                            width="200"
                            className="mx-auto"
                          />
                          <label
                            className="text-secondary text-center mt-1"
                            style={{ fontSize: "16px" }}
                          >
                            No Quote Found
                          </label>
                        </Styled.NoQuote>
                      )}
                  </>
                )}
                {tab === "tab1" && shortTerm3 && (
                  <>
                    {(!_.isEmpty(quoteShortTerm3) && quoteShortTerm3[0]
                      ? _.uniqBy(quoteShortTerm3, "company_alias")
                      : []
                    )?.map(
                      (item, index) =>
                        filterRenewal(item) && (
                          <>
                            {view === "grid" ? (
                              <QuoteCard
                                CompareData={CompareData}
                                progressPercent={progressPercent}
                                quote={quoteShortTerm3[index]}
                                NoOfDays={NoOfDays}
                                date={date}
                                diffDays={diffDays}
                                register={register}
                                index={index}
                                compare={compare}
                                lessthan767={lessthan767}
                                length={CompareData?.length}
                                watch={watch}
                                onCompare={!_.isEmpty(CompareData)}
                                type={type}
                                setPrevPopup={setPrevPopup2}
                                setQuoteData={setQuoteData}
                                prevPopup={prevPopup2}
                                setSelectedId={setSelectedId}
                                popupCard={false}
                                setSelectedCompanyName={setSelectedCompanyName}
                                setSelectedCompanyAlias={
                                  setSelectedCompanyAlias
                                }
                                setSelectedIcId={setSelectedIcId}
                                gstToggle={gstToggle}
                                maxAddonsMotor={maxAddonsMotor}
                                setKnowMoreObject={setKnowMoreObject}
                                setKnowMore={setKnowMore}
                                knowMore={knowMore}
                                setSelectedKnowMore={setSelectedKnowMore}
                                quoteComprehesiveGrouped={
                                  quoteComprehesiveGrouped1
                                    ? quoteComprehesiveGrouped1
                                    : quoteComprehesiveGrouped
                                }
                                knowMoreCompAlias={
                                  knowMoreObject?.quote?.companyAlias
                                }
                                allQuoteloading={
                                  !quotesLoadingComplted && quotesLoaded > 0
                                }
                                sendQuotes={sendQuotes}
                                setApplicableAddonsLits={
                                  setApplicableAddonsLits
                                }
                                setSendQuotes={setSendQuotes}
                                typeId={typeId}
                                isMobileIOS={isMobileIOS}
                                journey_type={journey_type}
                                setZdlp={setZdlp}
                                zdlp={zdlp}
                                claimList={_.compact(claimList)}
                                setZdlp_gdd={setZdlp_gdd}
                                zdlp_gdd={zdlp_gdd}
                                claimList_gdd={_.compact(claimList_gdd)}
                                mobileComp={mobileComp}
                                setMobileComp={setMobileComp}
                                filterRenewal={filterRenewal}
                                renewalFilter={renewalFilter}
                                FetchQuotes={FetchQuotes}
                                multiUpdateQuotes={multiUpdateQuotes}
                              />
                            ) : (
                              <GridCard
                                CompareData={CompareData}
                                progressPercent={progressPercent}
                                quote={quoteShortTerm3[index]}
                                NoOfDays={NoOfDays}
                                date={date}
                                diffDays={diffDays}
                                register={register}
                                index={index}
                                compare={compare}
                                lessthan767={lessthan767}
                                length={CompareData?.length}
                                watch={watch}
                                onCompare={!_.isEmpty(CompareData)}
                                type={type}
                                setPrevPopup={setPrevPopup2}
                                setQuoteData={setQuoteData}
                                prevPopup={prevPopup2}
                                setSelectedId={setSelectedId}
                                popupCard={false}
                                setSelectedCompanyName={setSelectedCompanyName}
                                setSelectedCompanyAlias={
                                  setSelectedCompanyAlias
                                }
                                setSelectedIcId={setSelectedIcId}
                                gstToggle={gstToggle}
                                maxAddonsMotor={maxAddonsMotor}
                                setKnowMoreObject={setKnowMoreObject}
                                setKnowMore={setKnowMore}
                                knowMore={knowMore}
                                setSelectedKnowMore={setSelectedKnowMore}
                                quoteComprehesiveGrouped={
                                  quoteComprehesiveGrouped1
                                    ? quoteComprehesiveGrouped1
                                    : quoteComprehesiveGrouped
                                }
                                knowMoreCompAlias={
                                  knowMoreObject?.quote?.companyAlias
                                }
                                allQuoteloading={
                                  !quotesLoadingComplted && quotesLoaded > 0
                                }
                                sendQuotes={sendQuotes}
                                setApplicableAddonsLits={
                                  setApplicableAddonsLits
                                }
                                setSendQuotes={setSendQuotes}
                                typeId={typeId}
                                isMobileIOS={isMobileIOS}
                                journey_type={journey_type}
                                setZdlp={setZdlp}
                                zdlp={zdlp}
                                claimList={_.compact(claimList)}
                                setZdlp_gdd={setZdlp_gdd}
                                zdlp_gdd={zdlp_gdd}
                                claimList_gdd={_.compact(claimList_gdd)}
                                mobileComp={mobileComp}
                                setMobileComp={setMobileComp}
                                filterRenewal={filterRenewal}
                                renewalFilter={renewalFilter}
                                FetchQuotes={FetchQuotes}
                                multiUpdateQuotes={multiUpdateQuotes}
                              />
                            )}
                          </>
                        )
                    )}
                    {_.isEmpty(quoteShortTerm3) &&
                      progressPercent === 100 &&
                      (!prefillLoading || !updateQuoteLoader) &&
                      !loading &&
                      quotesLoaded >=
                        (quotesList?.third_party
                          ? quotesList?.third_party?.length
                          : 0) +
                          (quotesList?.comprehensive
                            ? quotesList?.comprehensive?.length
                            : 0) &&
                      _.isEmpty(temp_data) &&
                      quotesLoadingInitiated &&
                      interimLoading &&
                      !date && (
                        <Styled.NoQuote>
                          <img
                            src={`${extPath}/assets/images/nodata3.png`}
                            alt="nodata"
                            height="200"
                            width="200"
                            className="mx-auto"
                          />
                          <label
                            className="text-secondary text-center mt-1"
                            style={{ fontSize: "16px" }}
                          >
                            No Quote Found
                          </label>
                        </Styled.NoQuote>
                      )}
                  </>
                )}
                {tab === "tab1" && shortTerm6 && (
                  <>
                    {(!_.isEmpty(quoteShortTerm6) && quoteShortTerm6[0]
                      ? _.uniqBy(quoteShortTerm6, "company_alias")
                      : []
                    )?.map(
                      (item, index) =>
                        filterRenewal(item) && (
                          <>
                            {view === "grid" ? (
                              <QuoteCard
                                CompareData={CompareData}
                                progressPercent={progressPercent}
                                quote={quoteShortTerm6[index]}
                                NoOfDays={NoOfDays}
                                date={date}
                                diffDays={diffDays}
                                register={register}
                                index={index}
                                compare={compare}
                                lessthan767={lessthan767}
                                length={CompareData?.length}
                                watch={watch}
                                onCompare={!_.isEmpty(CompareData)}
                                type={type}
                                setPrevPopup={setPrevPopup2}
                                setQuoteData={setQuoteData}
                                prevPopup={prevPopup2}
                                setSelectedId={setSelectedId}
                                popupCard={false}
                                setSelectedCompanyName={setSelectedCompanyName}
                                setSelectedCompanyAlias={
                                  setSelectedCompanyAlias
                                }
                                setSelectedIcId={setSelectedIcId}
                                gstToggle={gstToggle}
                                maxAddonsMotor={maxAddonsMotor}
                                setKnowMoreObject={setKnowMoreObject}
                                setKnowMore={setKnowMore}
                                knowMore={knowMore}
                                setSelectedKnowMore={setSelectedKnowMore}
                                quoteComprehesiveGrouped={
                                  quoteComprehesiveGrouped1
                                    ? quoteComprehesiveGrouped1
                                    : quoteComprehesiveGrouped
                                }
                                knowMoreCompAlias={
                                  knowMoreObject?.quote?.companyAlias
                                }
                                allQuoteloading={
                                  !quotesLoadingComplted && quotesLoaded > 0
                                }
                                sendQuotes={sendQuotes}
                                setApplicableAddonsLits={
                                  setApplicableAddonsLits
                                }
                                setSendQuotes={setSendQuotes}
                                typeId={typeId}
                                isMobileIOS={isMobileIOS}
                                journey_type={journey_type}
                                setZdlp={setZdlp}
                                zdlp={zdlp}
                                claimList={_.compact(claimList)}
                                setZdlp_gdd={setZdlp_gdd}
                                zdlp_gdd={zdlp_gdd}
                                claimList_gdd={_.compact(claimList_gdd)}
                                mobileComp={mobileComp}
                                setMobileComp={setMobileComp}
                                filterRenewal={filterRenewal}
                                renewalFilter={renewalFilter}
                                FetchQuotes={FetchQuotes}
                                multiUpdateQuotes={multiUpdateQuotes}
                              />
                            ) : (
                              <GridCard
                                CompareData={CompareData}
                                progressPercent={progressPercent}
                                quote={quoteShortTerm6[index]}
                                NoOfDays={NoOfDays}
                                date={date}
                                diffDays={diffDays}
                                register={register}
                                index={index}
                                compare={compare}
                                lessthan767={lessthan767}
                                length={CompareData?.length}
                                watch={watch}
                                onCompare={!_.isEmpty(CompareData)}
                                type={type}
                                setPrevPopup={setPrevPopup2}
                                setQuoteData={setQuoteData}
                                prevPopup={prevPopup2}
                                setSelectedId={setSelectedId}
                                popupCard={false}
                                setSelectedCompanyName={setSelectedCompanyName}
                                setSelectedCompanyAlias={
                                  setSelectedCompanyAlias
                                }
                                setSelectedIcId={setSelectedIcId}
                                gstToggle={gstToggle}
                                maxAddonsMotor={maxAddonsMotor}
                                setKnowMoreObject={setKnowMoreObject}
                                setKnowMore={setKnowMore}
                                knowMore={knowMore}
                                setSelectedKnowMore={setSelectedKnowMore}
                                quoteComprehesiveGrouped={
                                  quoteComprehesiveGrouped1
                                    ? quoteComprehesiveGrouped1
                                    : quoteComprehesiveGrouped
                                }
                                knowMoreCompAlias={
                                  knowMoreObject?.quote?.companyAlias
                                }
                                allQuoteloading={
                                  !quotesLoadingComplted && quotesLoaded > 0
                                }
                                sendQuotes={sendQuotes}
                                setApplicableAddonsLits={
                                  setApplicableAddonsLits
                                }
                                setSendQuotes={setSendQuotes}
                                typeId={typeId}
                                isMobileIOS={isMobileIOS}
                                journey_type={journey_type}
                                setZdlp={setZdlp}
                                zdlp={zdlp}
                                claimList={_.compact(claimList)}
                                setZdlp_gdd={setZdlp_gdd}
                                zdlp_gdd={zdlp_gdd}
                                claimList_gdd={_.compact(claimList_gdd)}
                                mobileComp={mobileComp}
                                setMobileComp={setMobileComp}
                                filterRenewal={filterRenewal}
                                renewalFilter={renewalFilter}
                                FetchQuotes={FetchQuotes}
                                multiUpdateQuotes={multiUpdateQuotes}
                              />
                            )}
                          </>
                        )
                    )}
                    {_.isEmpty(quoteShortTerm6) &&
                      progressPercent === 100 &&
                      (!prefillLoading || !updateQuoteLoader) &&
                      !loading &&
                      quotesLoaded >=
                        (quotesList?.third_party
                          ? quotesList?.third_party?.length
                          : 0) +
                          (quotesList?.comprehensive
                            ? quotesList?.comprehensive?.length
                            : 0) &&
                      _.isEmpty(temp_data) &&
                      quotesLoadingInitiated &&
                      interimLoading &&
                      !date && (
                        <Styled.NoQuote>
                          <img
                            src={`${extPath}/assets/images/nodata3.png`}
                            alt="nodata"
                            height="200"
                            width="200"
                            className="mx-auto"
                          />
                          <label
                            className="text-secondary text-center mt-1"
                            style={{ fontSize: "16px" }}
                          >
                            No Quote Found
                          </label>
                        </Styled.NoQuote>
                      )}
                  </>
                )}
                {tab === "tab2" && (
                  <>
                    {(!_.isEmpty(quoteTpGrouped1) && quoteTpGrouped1[0]
                      ? _.uniqBy(quoteTpGrouped1, "policyId")
                      : isRelevant
                      ? _.uniqBy(quoteTpGrouped1, "policyId")
                      : _.uniqBy(
                          _filterTpTenure(quotetThirdParty, longtermParams),
                          "policyId"
                        )
                    )?.map(
                      (item, index) =>
                        filterRenewal(item) && (
                          <>
                            {view === "grid" ? (
                              <QuoteCard
                                CompareData={CompareData}
                                progressPercent={progressPercent}
                                quote={
                                  quoteTpGrouped1[index] ||
                                  quotetThirdParty[index]
                                }
                                NoOfDays={NoOfDays}
                                date={date}
                                diffDays={diffDays}
                                register={register}
                                index={index}
                                compare={compare}
                                lessthan767={lessthan767}
                                length={CompareData?.length}
                                watch={watch}
                                onCompare={!_.isEmpty(CompareData)}
                                type={type}
                                setPrevPopup={setPrevPopup2}
                                setQuoteData={setQuoteData}
                                prevPopup={prevPopup2}
                                setSelectedId={setSelectedId}
                                popupCard={false}
                                setSelectedCompanyName={setSelectedCompanyName}
                                setSelectedCompanyAlias={
                                  setSelectedCompanyAlias
                                }
                                setSelectedIcId={setSelectedIcId}
                                gstToggle={gstToggle}
                                maxAddonsMotor={maxAddonsMotor}
                                setKnowMoreObject={setKnowMoreObject}
                                setKnowMore={setKnowMore}
                                knowMore={knowMore}
                                setSelectedKnowMore={setSelectedKnowMore}
                                quoteComprehesiveGrouped={
                                  quoteComprehesiveGrouped1
                                    ? quoteComprehesiveGrouped1
                                    : quoteComprehesiveGrouped
                                }
                                knowMoreCompAlias={
                                  knowMoreObject?.quote?.companyAlias
                                }
                                allQuoteloading={
                                  !quotesLoadingComplted && quotesLoaded > 0
                                }
                                sendQuotes={sendQuotes}
                                setApplicableAddonsLits={
                                  setApplicableAddonsLits
                                }
                                setSendQuotes={setSendQuotes}
                                typeId={typeId}
                                isMobileIOS={isMobileIOS}
                                journey_type={journey_type}
                                setZdlp={setZdlp}
                                zdlp={zdlp}
                                claimList={_.compact(claimList)}
                                setZdlp_gdd={setZdlp_gdd}
                                zdlp_gdd={zdlp_gdd}
                                claimList_gdd={_.compact(claimList_gdd)}
                                mobileComp={mobileComp}
                                setMobileComp={setMobileComp}
                                filterRenewal={filterRenewal}
                                renewalFilter={renewalFilter}
                                FetchQuotes={FetchQuotes}
                                multiUpdateQuotes={multiUpdateQuotes}
                              />
                            ) : (
                              <GridCard
                                CompareData={CompareData}
                                progressPercent={progressPercent}
                                quote={
                                  quoteTpGrouped1[index] ||
                                  quotetThirdParty[index]
                                }
                                NoOfDays={NoOfDays}
                                date={date}
                                diffDays={diffDays}
                                register={register}
                                index={index}
                                compare={compare}
                                lessthan767={lessthan767}
                                length={CompareData?.length}
                                watch={watch}
                                onCompare={!_.isEmpty(CompareData)}
                                type={type}
                                setPrevPopup={setPrevPopup2}
                                setQuoteData={setQuoteData}
                                prevPopup={prevPopup2}
                                setSelectedId={setSelectedId}
                                popupCard={false}
                                setSelectedCompanyName={setSelectedCompanyName}
                                setSelectedCompanyAlias={
                                  setSelectedCompanyAlias
                                }
                                setSelectedIcId={setSelectedIcId}
                                gstToggle={gstToggle}
                                maxAddonsMotor={maxAddonsMotor}
                                setKnowMoreObject={setKnowMoreObject}
                                setKnowMore={setKnowMore}
                                knowMore={knowMore}
                                setSelectedKnowMore={setSelectedKnowMore}
                                quoteComprehesiveGrouped={
                                  quoteComprehesiveGrouped1
                                    ? quoteComprehesiveGrouped1
                                    : quoteComprehesiveGrouped
                                }
                                knowMoreCompAlias={
                                  knowMoreObject?.quote?.companyAlias
                                }
                                allQuoteloading={
                                  !quotesLoadingComplted && quotesLoaded > 0
                                }
                                sendQuotes={sendQuotes}
                                setApplicableAddonsLits={
                                  setApplicableAddonsLits
                                }
                                setSendQuotes={setSendQuotes}
                                typeId={typeId}
                                isMobileIOS={isMobileIOS}
                                journey_type={journey_type}
                                setZdlp={setZdlp}
                                zdlp={zdlp}
                                claimList={_.compact(claimList)}
                                setZdlp_gdd={setZdlp_gdd}
                                zdlp_gdd={zdlp_gdd}
                                claimList_gdd={_.compact(claimList_gdd)}
                                mobileComp={mobileComp}
                                setMobileComp={setMobileComp}
                                filterRenewal={filterRenewal}
                                renewalFilter={renewalFilter}
                                FetchQuotes={FetchQuotes}
                                multiUpdateQuotes={multiUpdateQuotes}
                              />
                            )}
                          </>
                        )
                    )}
                    {(_.isEmpty(quoteTpGrouped1) &&
                      progressPercent === 100 &&
                      !loading &&
                      quotesLoaded >=
                        (quotesList?.third_party
                          ? quotesList?.third_party?.length
                          : 0) +
                          (quotesList?.comprehensive
                            ? quotesList?.comprehensive?.length
                            : 0) +
                          (quotesList?.short_term
                            ? quotesList?.short_term?.length
                            : 0) &&
                      _.isEmpty(temp_data) &&
                      quotesLoadingInitiated &&
                      interimLoading &&
                      !date) ||
                      (quotesList?.third_party?.length === 0 &&
                        tab === "tab2" && (
                          <Styled.NoQuote>
                            <img
                              src={`${extPath}/assets/images/nodata3.png`}
                              alt="nodata"
                              height="200"
                              width="200"
                              className="mx-auto"
                            />
                            <label
                              className="text-secondary text-center mt-1"
                              style={{ fontSize: "16px" }}
                            >
                              No Quote Found
                            </label>
                          </Styled.NoQuote>
                        ))}
                  </>
                )}
                {((quotesLoaded &&
                  quotesLoaded <
                    (quotesList?.third_party
                      ? quotesList?.third_party?.length
                      : 0) +
                      (quotesList?.comprehensive
                        ? quotesList?.comprehensive?.length
                        : 0) +
                      (quotesList?.short_term
                        ? quotesList?.short_term?.length
                        : 0)) ||
                  loading ||
                  (quotesLoadingInitiated && interimLoading && true) ||
                  (quotesLoadingInitiated && loading && true) ||
                  "") && (
                  <>
                    {view === "grid" ? (
                      <QuoteSkelton
                        popupCard={false}
                        type={type}
                        lessthan767={lessthan767}
                        maxAddonsMotor={maxAddonsMotor}
                      />
                    ) : (
                      <GridSkeleton
                        popupCard={false}
                        type={type}
                        lessthan767={lessthan767}
                        maxAddonsMotor={maxAddonsMotor}
                      />
                    )}
                  </>
                )}
              </Row>
              <Row
                style={{
                  display: !quotesLoadingComplted ? "none" : "flex",
                }}
              >
                <Styled.ErrorContainer>
                  {showingErrors(
                    errorIcBased,
                    filterErrorComp,
                    finalErrorTp,
                    tab,
                    versionId,
                    lessthan767,
                    temp_data,
                    getIcLogoUrl,
                    getErrorMsgComp,
                    getErrorMsgTp,
                    token
                  )}
                </Styled.ErrorContainer>
              </Row>
            </Col>
          </Row>
        </Styled.NonStickyRows>
        {(!lessthan993 || import.meta.env.VITE_BROKER === "BAJAJ") && (
          <FloatButton />
        )}
      </Styled.MainContainer>

      {compare && (
        <CompareContainer
          CompareData={CompareData}
          addOnsAndOthers={addOnsAndOthers}
          type={type}
          setMobileComp={setMobileComp}
        />
      )}
      {sendQuotes && (
        <SendQuotes
          show={sendQuotes}
          onClose={setSendQuotes}
          sendPdf={sendPdf}
          setSendPdf={setSendPdf}
          type={type}
          shareQuotesFromToaster={shareQuotesFromToaster}
          setShareQuotesFromToaster={setShareQuotesFromToaster}
          premiumBreakuppdf="premiumBreakuppdf"
          comparepdf="comparepdf"
        />
      )}
      {openGarageModal && selectedGarage && (
        <SendQuotes
          show={openGarageModal}
          onClose={setOpenGarageModal}
          selectedGarage={selectedGarage}
          openGarageModal={openGarageModal}
          type={type}
          garage
        />
      )}
      {(saveQuoteLoader || prefillLoading || updateQuoteLoader) && <Loader />}
      {prevPopup2 && (
        <PrevInsurerPopup2
          show={prevPopup2}
          onClose={setPrevPopup2}
          selectedId={selectedId}
          type={type}
          selectedCompanyName={selectedCompanyName}
          selectedCompanyAlias={selectedCompanyAlias}
          selectedIcId={selectedIcId}
          applicableAddonsLits={applicableAddonsLits}
          lessthan767={lessthan767}
          lessthan993={lessthan993}
          typeId={typeId}
          journey_type={journey_type}
          homeStateData={homeStateData}
          setHomeStateData={setHomeStateData}
          filterStateData={filterStateData}
          setFilterStateData={setFilterStateData}
          assistedMode={assistedMode}
          setAssistedMode={setAssistedMode}
          onCloseAssisted={onCloseAssisted}
          shortTerm3={shortTerm3}
          shortTerm6={shortTerm6}
          isComprehensive={tab === "tab1"}
        />
      )}

      {knowMore && knowMoreQuote && (
        <KnowMorePopup
          quote={knowMoreQuote}
          show={knowMore}
          onClose={setKnowMore}
          selectedKnow={selectedKnowMore}
          totalAddon={knowMoreObject?.totalAddon}
          totalPremium={knowMoreObject?.totalPremium}
          gst={knowMoreObject?.gst}
          finalPremium={knowMoreObject?.finalPremium}
          totalPremiumA={knowMoreObject?.totalPremiumA}
          totalPremiumB={knowMoreObject?.totalPremiumB}
          totalPremiumC={knowMoreObject?.totalPremiumC}
          applicableAddons={knowMoreObject?.applicableAddons}
          type={knowMoreObject?.type}
          prevInsName={knowMoreObject?.prevInsName}
          addonDiscount={knowMoreObject?.addonDiscount}
          addonDiscountPercentage={knowMoreObject?.addonDiscountPercentage}
          revisedNcb={knowMoreObject?.revisedNcb}
          otherDiscounts={knowMoreObject?.otherDiscounts}
          popupCard={knowMoreObject?.popupCard}
          setPrevPopup={knowMoreObject?.setPrevPopup}
          prevPopup={knowMoreObject?.prevPopup}
          setSelectedId={setSelectedId}
          setSelectedCompanyName={setSelectedCompanyName}
          setSelectedCompanyAlias={setSelectedCompanyAlias}
          totalOthersAddon={knowMoreObject?.totalOthersAddon}
          totalApplicableAddonsMotor={
            knowMoreObject?.totalApplicableAddonsMotor
          }
          uwLoading={knowMoreObject?.uwLoading}
          setSendQuotes={setSendQuotes}
          setSendPdf={setSendPdf}
          sendQuotes={sendQuotes}
          allQuoteloading={!quotesLoadingComplted && quotesLoaded > 0}
          setQuoteData={setQuoteData}
          displayAddress={knowMoreObject?.icAddress}
          claimList={_.compact(claimList)}
          setZdlp={setZdlp}
          zdlp={zdlp}
          claimList_gdd={_.compact(claimList_gdd)}
          setZdlp_gdd={setZdlp_gdd}
          zdlp_gdd={zdlp_gdd}
          setApplicableAddonsLits={setApplicableAddonsLits}
          setSelectedIcId={setSelectedIcId}
          extraLoading={knowMoreObject?.extraLoading}
          setSelectedGarage={setSelectedGarage}
          setOpenGarageModal={setOpenGarageModal}
        />
      )}
      {showClaimModal && (
        <ClaimModal
          set_temp_data={set_temp_data}
          dispatch={dispatch}
          show={showClaimModal}
          CancelAll={CancelAll}
          onHide={() => setClaimModal(false)}
        />
      )}
      <Styled.GlobalStyle />
    </>
  );
};
