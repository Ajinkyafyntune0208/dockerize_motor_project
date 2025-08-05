import { useEffect, useState } from "react";
//prettier-ignore
import { Disable_B2C, reloadPage, RedirectFn, journeyProcessQuotes,
         PaymentIncomplete, PostTransaction 
        } from 'utils';
import { pdfExpiry } from "../../quoteUtil";
import _ from "lodash";
import swal from "sweetalert";
import { rollover_breakin_constructor } from "modules/proposal/request-helper";
import {
  SaveQuoteData,
  clear,
} from "modules/quotesPage/filterConatiner/quoteFilter.slice";
//prettier-ignore
import { AddonConfig, addonConfig as clearAddonConfig, 
         CancelAll, MasterLogoList, UpdateQuotesData,
         setQuotesLoaded, clearSaveQuoteError
        } from 'modules/quotesPage/quote.slice';
import { Prefill } from "modules/Home/home.slice";
//prettier-ignore
import { DuplicateEnquiryId, clrDuplicateEnquiry,
         set_temp_data 
        } from 'modules/proposal/proposal.slice'
//prettier-ignore
import { GetValidAdditionalKeys, GroupByIC, NoOfClaims, 
         CreateMarker, Grouping, relevance, _filterTenure
        } from '../../quote-logic';
import { useSelector } from "react-redux";
import { _quotePageTracking } from "analytics/quote-page/quote-page-tracking";

export const useViewHook = (theme_conf, setView) => {
  useEffect(() => {
    if (theme_conf?.broker_config?.quoteview) {
      localStorage.setItem("view", theme_conf?.broker_config?.quoteview);
      setView(theme_conf?.broker_config?.quoteview);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [theme_conf?.broker_config]);
};

export const useB2CAuth = (temp_data, checkSellerType, token, journey_type) => {
  const { theme_conf } = useSelector((state) => state.home) || {};
  useEffect(() => {
    Disable_B2C(
      temp_data,
      checkSellerType,
      token,
      journey_type,
      true,
      theme_conf
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token, temp_data]);
};

export const useLinkDelivery = (dispatch, keyTrigger, LinkTrigger) => {
  useEffect(() => {
    keyTrigger && dispatch(LinkTrigger({ key: keyTrigger }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [keyTrigger]);
};

export const usePdfExpiry = (
  date,
  diffDays,
  NoOfDays,
  enquiry_id,
  token,
  journey_type,
  typeId,
  shared
) => {
  useEffect(() => {
    //prettier-ignore
    pdfExpiry(date, diffDays, NoOfDays, enquiry_id, token, journey_type, typeId, shared);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [date]);
};

export const useInvalidatePromise = (
  dispatch,
  SetLoadingCancelled,
  setProposalTemp
) => {
  useEffect(() => {
    dispatch(SetLoadingCancelled(false));
    dispatch(setProposalTemp("clearAll"));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
};

export const useAccessControl = (AccessControl, typeAccess, type, history) => {
  useEffect(() => {
    if (!_.isEmpty(typeAccess)) {
      AccessControl(type, typeAccess, history);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [typeAccess]);
};

export const useErrorHandling = (
  dispatch,
  error,
  temp_data,
  enquiry_id,
  errorSpecific,
  _stToken
) => {
  useEffect(() => {
    if (error) {
      swal({
        title: "Error",
        text: enquiry_id
          ? `${`Trace ID:- ${
              temp_data?.traceId ? temp_data?.traceId : enquiry_id
            }.\n Error Message:- ${error}`}`
          : error,
        icon: "error",
        buttons: {
          cancel: "Dismiss",
          ...(errorSpecific && {
            catch: {
              text: "See more details",
              value: "confirm",
            },
          }),
        },
        dangerMode: true,
      }).then((caseValue) => {
        switch (caseValue) {
          case "confirm":
            swal(
              "Error",
              enquiry_id
                ? `${`Trace ID:- ${
                    temp_data?.traceId ? temp_data?.traceId : enquiry_id
                  }.\n Error Message:- ${errorSpecific}`}`
                : errorSpecific,
              "error"
            );
        }
      });
    }
    return () => {
      dispatch(clear());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [error]);
};

export const useBreakinTransitions = (
  dispatch,
  temp_data,
  initalExecution,
  setExecution,
  enquiry_id,
  token,
  type
) => {
  const { theme_conf } = useSelector((state) => state.home);
  useEffect(() => {
    //excluding breakin journey s and bike product
    //Rollover to Breakin Transition.
    if (
      !_.isEmpty(temp_data) &&
      temp_data?.corporateVehiclesQuoteRequest?.businessType !== "breakin" &&
      temp_data?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate &&
      temp_data?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate !==
        "New" &&
      !initalExecution &&
      ![
        "Policy Issued",
        "Policy Issued, but pdf not generated",
        "Policy Issued And PDF Generated",
        "payment success",
      ].includes(
        ["payment success"].includes(
          temp_data?.journeyStage?.stage.toLowerCase()
        )
          ? temp_data?.journeyStage?.stage.toLowerCase()
          : temp_data?.journeyStage?.stage
      )
    ) {
      //comparing expiry date with current date
      let [day, month, year] =
        temp_data?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate?.split(
          "-"
        );
      let expDateObj = new Date(year, month * 1 - 1, day);
      if (
        expDateObj.setHours(0, 0, 0, 0).valueOf() <
        new Date().setHours(0, 0, 0, 0).valueOf()
      ) {
        setExecution(true);
        //changing bussinessType
        dispatch(
          SaveQuoteData({
            ...rollover_breakin_constructor(temp_data, enquiry_id, type),
          })
        );
        swal("Please Note", "This journey has been expired", "info").then(() =>
          reloadPage(window.location.href)
        );
      }
    }

    //New Bussiness Breaking Block
    if (
      !_.isEmpty(temp_data) &&
      temp_data?.corporateVehiclesQuoteRequest?.businessType ===
        "newbusiness" &&
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate &&
      !initalExecution
    ) {
      //comparing expiry date with current date
      let [day, month, year] =
        temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate?.split(
          "-"
        );

      let RegDateObj = new Date(year, month * 1 - 1, day);
      if (
        RegDateObj.setHours(0, 0, 0, 0).valueOf() <
        new Date().setHours(0, 0, 0, 0).valueOf()
      ) {
        setExecution(true);
        swal(
          "Please Note",
          "We are not accepting new business breakin case at the moment.",
          "info"
        ).then(() => {
          reloadPage(
            import.meta.env.VITE_BROKER === "HEROCARE"
              ? window.location.href.replace(/quotes/, "registration")
              : theme_conf?.broker_config?.broker_asset?.other_failure_url
                  ?.url || RedirectFn(token)
          );
        });
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.corporateVehiclesQuoteRequest]);
};

export const useAddonConfig = (dispatch, enquiry_id) => {
  useEffect(() => {
    if (
      enquiry_id &&
      (["ACE", "RB"]?.includes(import.meta.env.VITE_BROKER) ||
        import.meta.env.VITE_BROKER === "KAROINSURE" ||
        import.meta.env.VITE_BROKER === "INSTANTBEEMA")
    ) {
      dispatch(AddonConfig({ enquiryId: enquiry_id }));
    }
    //eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id]);
};

export const useRefetch = (dispatch, enquiry_id, addonConfig) => {
  useEffect(() => {
    if (
      enquiry_id &&
      (import.meta.env.VITE_BROKER === "ACE" ||
        import.meta.env.VITE_BROKER === "KAROINSURE" ||
        import.meta.env.VITE_BROKER === "INSTANTBEEMA") &&
      addonConfig
    ) {
      dispatch(Prefill({ enquiryId: enquiry_id }));
    }
    //clear config data
    return () => {
      dispatch(clearAddonConfig(null));
    };
    //eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id, addonConfig]);
};

export const usePrefill = (dispatch, enquiry_id) => {
  useEffect(() => {
    if (
      enquiry_id &&
      import.meta.env.VITE_BROKER !== "ACE" &&
      import.meta.env.VITE_BROKER !== "KAROINSURE" &&
      import.meta.env.VITE_BROKER !== "INSTANTBEEMA"
    ) {
      dispatch(Prefill({ enquiryId: enquiry_id }));
    }
    //eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id]);
};

export const useZDCoverPrefill = (temp_data, setZdlp, setZdlp_gdd) => {
  useEffect(() => {
    if (temp_data?.quoteLog?.premiumJson?.claimsCovered) {
      setZdlp(temp_data?.quoteLog?.premiumJson?.claimsCovered);
      setZdlp_gdd(temp_data?.quoteLog?.premiumJson?.claimsCovered);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.quoteLog?.premiumJson?.claimsCovered]);
};

export const useJourneyProcess = (
  dispatch,
  enquiry_id,
  temp_data,
  limiter,
  setLimiter,
  Url
) => {
  useEffect(() => {
    if (
      enquiry_id &&
      temp_data?.journeyStage?.stage &&
      temp_data?.userProposal?.isBreakinCase !== "Y" &&
      limiter < 1
    ) {
      //prettier-ignore
      journeyProcessQuotes(dispatch, Url, DuplicateEnquiryId, enquiry_id, temp_data, "Quote - Buy Now");
      setLimiter(1);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.journeyStage?.stage]);
};

export const useDuplicateEnquiry = (
  dispatch,
  duplicateEnquiry,
  typeId,
  journey_type,
  _stToken,
  type,
  token,
  shared
) => {
  useEffect(() => {
    if (duplicateEnquiry?.enquiryId) {
      dispatch(CancelAll(true));
      //prettier-ignore
      PaymentIncomplete(type, token, duplicateEnquiry?.enquiryId, typeId, journey_type, "quotes", _stToken, shared);
    }
    return () => {
      dispatch(clrDuplicateEnquiry());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [duplicateEnquiry]);
};

export const usePostTransaction = (
  dispatch,
  temp_data,
  enquiry_id,
  _stToken
) => {
  useEffect(() => {
    PostTransaction(temp_data, dispatch, CancelAll, enquiry_id, _stToken);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.journeyStage?.stage]);
};

export const useLogoMaster = (
  dispatch,
  masterLogos,
  location,
  type,
  enquiry_id
) => {
  const [masterLogo, setMasterLogo] = useState(false);
  useEffect(() => {
    if (
      !masterLogo &&
      masterLogos?.length === 0 &&
      location.pathname === `/${type}/quotes`
    ) {
      setMasterLogo(true);
      dispatch(MasterLogoList({ enquiryId: enquiry_id }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [masterLogos]);
};

export const useEnquiryOrBreakinCheck = (
  dispatch,
  location,
  type,
  temp_data,
  enquiry_id,
  history,
  token,
  typeId,
  journey_type,
  _stToken,
  shared
) => {
  const { theme_conf } = useSelector((state) => state.home);
  useEffect(() => {
    if (location.pathname === `/${type}/quotes`) {
      //Enquiry ID Check.
      if (temp_data?.enquiry_id || (enquiry_id && enquiry_id !== "null")) {
      } else {
        dispatch(CancelAll(true));
        swal("Info", "Enquiry id not found, redirecting to homepage", "info", {
          closeOnClickOutside: false,
        }).then(() =>
          history.replace(
            `/${type}/lead-page?enquiry_id=${enquiry_id}${
              token ? `&xutm=${token}` : ``
            }${typeId ? `&typeid=${typeId}` : ``}${
              journey_type ? `&journey_type=${journey_type}` : ``
            }${_stToken ? `&stToken=${_stToken}` : ``}${
              shared ? `&shared=${shared}` : ``
            }`
          )
        );
      }
    }

    //Redirection after breakin submission
    if (temp_data?.userProposal?.isBreakinCase === "Y") {
      dispatch(CancelAll(true));
      swal("Info", "Breakin policy already generated.", "info", {
        closeOnClickOutside: false,
      }).then(() =>
        token
          ? reloadPage(
              theme_conf?.broker_config?.broker_asset?.other_failure_url?.url ||
                RedirectFn(token)
            )
          : history.replace(
              `/${type}/lead-page?enquiry_id=${enquiry_id}${
                token ? `&xutm=${token}` : ``
              }${typeId ? `&typeid=${typeId}` : ``}${
                journey_type ? `&journey_type=${journey_type}` : ``
              }${_stToken ? `&stToken=${_stToken}` : ``}${
                shared ? `&shared=${shared}` : ``
              }`
            )
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data]);
};

export const useQuoteInitialiation = (
  quotesLoaded,
  setQuotesLoadingInitiated
) => {
  useEffect(() => {
    if (quotesLoaded > 0) {
      setQuotesLoadingInitiated(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [quotesLoaded]);
};

export const usePolicyTypePrefill = (temp_data, tab) => {
  useEffect(() => {
    if (
      tab === "tab1" &&
      temp_data?.quoteLog &&
      temp_data?.quoteLog?.premiumJson?.policyType === "Third Party"
    ) {
      document.getElementById("tab2") &&
        document.getElementById("tab2").click();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.quoteLog]);
};

export const useToaster_PreviousPolicyType = (
  temp_data,
  setCallToasterPreIc
) => {
  useEffect(() => {
    if (
      temp_data?.expiry &&
      temp_data?.expiry !== "New" &&
      !_.isEmpty(temp_data) &&
      temp_data?.isToastShown !== "Y" &&
      temp_data?.journeyCategory === "PCV" &&
      import.meta.env.VITE_BROKER === "ACE" &&
      !temp_data?.newCar &&
      !temp_data?.selectedQuote?.companyAlias
    ) {
      setCallToasterPreIc(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data]);
};

//prettier-ignore
export const useToaster_AddonPrefill = (dispatch, temp_data, setCallToasterPreIc, setCallToasterAddon, enquiry_id) => {
  useEffect(() => {
    if (
      !_.isEmpty(temp_data) &&
      (!temp_data?.infoToaster || temp_data?.infoToaster === "N") &&
      temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y"
    ) {
      dispatch(set_temp_data({ infoToaster: "Y" }));
      dispatch(
        UpdateQuotesData({ infoToaster: "Y", enquiryId: enquiry_id }, "Y")
      );
      setCallToasterAddon(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data]);
}

//prettier-ignore
export const useToaster_ExipryAssumption = (dispatch, temp_data, type, setCallToasterExpiry) => {
  useEffect(() => {
    if (
      temp_data?.isExpiryModified === "Y" &&
      type !== "cv" &&
      !temp_data?.newCar
    ) {
      dispatch(
        set_temp_data({
          isExpiryModified: "N",
        })
      );
      setCallToasterExpiry(true);
    }
    if (
      temp_data?.isExpiryModified === "Yes" &&
      type !== "cv" &&
      !temp_data?.newCar
    ) {
      dispatch(
        set_temp_data({
          isExpiryModified: "N",
        })
      );
      setCallToasterExpiry("assumption");
    }
    if (
      temp_data?.isExpiryModified === "registration" &&
      type !== "cv" &&
      !temp_data?.newCar
    ) {
      dispatch(
        set_temp_data({
          isExpiryModified: "N",
        })
      );
      setCallToasterExpiry("registration");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.isExpiryModified]);
}
//prettier-ignore
export const useFetch_Comprehensive = (FetchQuotes, quotesList, quoteComprehesive, buyNowSingleQuoteUpdate) => {
  useEffect(() => {
    if (
      quotesList?.comprehensive &&
      quotesList?.comprehensive?.length > 0 &&
      quoteComprehesive?.length === 0 &&
      !buyNowSingleQuoteUpdate
    ) {
      //prettier-ignore
      FetchQuotes(quotesList?.comprehensive, "comprehensive", quoteComprehesive)
    }

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [quotesList?.comprehensive, quoteComprehesive?.length]);
}

export const useFetch_ThirdParty = (
  FetchQuotes,
  quotesList,
  quotetThirdParty,
  buyNowSingleQuoteUpdate
) => {
  useEffect(() => {
    if (
      quotesList?.third_party &&
      quotesList?.third_party?.length > 0 &&
      quotetThirdParty?.length === 0 &&
      !buyNowSingleQuoteUpdate
    ) {
      //prettier-ignore
      FetchQuotes(quotesList?.third_party, "third_party", quotetThirdParty)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [quotesList?.third_party, quotetThirdParty?.length]);
};

export const useFetch_ShortTerm = (
  FetchQuotes,
  quotesList,
  quoteShortTerm,
  buyNowSingleQuoteUpdate
) => {
  useEffect(() => {
    if (
      quotesList?.short_term &&
      quotesList?.short_term?.length > 0 &&
      quoteShortTerm?.length === 0 &&
      !buyNowSingleQuoteUpdate
    ) {
      //prettier-ignore
      FetchQuotes(quotesList?.short_term, "shortTerm", quoteShortTerm)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [quotesList?.short_term, quoteShortTerm?.length]);
};

export const useQuoteLoadProgress = (
  dispatch,
  quotesList,
  quotesLoaded,
  loading,
  setQuotesLoadingCompleted,
  setProgressPercent,
  updateQuoteLoader
) => {
  useEffect(() => {
    let totalLength =
      (quotesList?.third_party ? quotesList?.third_party?.length : 0) +
      (quotesList?.comprehensive ? quotesList?.comprehensive?.length : 0) +
      (quotesList?.short_term ? quotesList?.short_term?.length : 0);

    if ((quotesLoaded && quotesLoaded < totalLength) || loading) {
      setQuotesLoadingCompleted(false);
      if (quotesLoaded >= totalLength) setProgressPercent(100);
      else if (quotesLoaded >= totalLength / 1.5) setProgressPercent(75);
      else if (quotesLoaded >= totalLength / 2) setProgressPercent(50);
      else if (quotesLoaded >= totalLength / 3) setProgressPercent(40);
      else if (quotesLoaded >= totalLength / 4) setProgressPercent(25);
      else setProgressPercent(15);
    } else if (!updateQuoteLoader) {
      setProgressPercent(100);
      setTimeout(() => {
        setQuotesLoadingCompleted(true);
      }, 1000);
      if (quotesLoaded >= totalLength) {
        dispatch(setQuotesLoaded(0));
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    quotesLoaded,
    quotesList?.third_party?.length,
    quotesList?.comprehensive?.length,
    quotesList?.short_term?.length,
    quotesList,
    loading,
    updateQuoteLoader,
  ]);
};

export const useSingleQuoteError = (
  dispatch,
  saveQuoteError,
  enquiry_id,
  history,
  _stToken
) => {
  useEffect(() => {
    if (saveQuoteError) {
      (saveQuoteError === "Payment Initiated" ||
        saveQuoteError === "Payment Link Already Generated..!") &&
        dispatch(DuplicateEnquiryId({ enquiryId: enquiry_id }));
      saveQuoteError === "Transaction Already Completed" &&
        history.replace(
          `/payment-success?enquiry_id=${enquiry_id}${
            _stToken ? `&stToken=${_stToken}` : ``
          }`
        );
    }
    return () => {
      dispatch(clearSaveQuoteError());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [saveQuoteError]);
};

export const useGrouping = (
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
) => {
  const { longTerm2, longTerm3 } = longtermParams || {};
  useEffect(() => {
    let selectedAddons = !_.isEmpty(addOnsAndOthers?.selectedAddons)
      ? addOnsAndOthers?.selectedAddons
      : [];
    let groupedQuotesByIC = GroupByIC(quoteComprehesive);
    let godigitQuotes = groupedQuotesByIC?.godigit || [];

    //filtering godigit quotes which have zero depreciation when zero depreciation is selected by the user
    let preGroupingFilter = godigitQuotes.filter((quote) => {
      return (
        quote?.addOnsData?.inBuilt?.zeroDepreciation ||
        quote?.addOnsData?.additional?.zeroDepreciation * 1 > 0
      );
    });

    //if zero depreciation is selected and filtered quotes has zero depreciation, otherwise we are not filtering anything.
    if (
      selectedAddons.includes("zeroDepreciation") &&
      preGroupingFilter?.length > 0
    ) {
      groupedQuotesByIC = { ...groupedQuotesByIC, godigit: preGroupingFilter };
    }
    //----------ZD No of claims----------
    //NON GDD DIGIT
    //get list of markers
    let markerList = NoOfClaims(groupedQuotesByIC, "godigit");
    //setting list in state
    setClaimList(markerList);
    //selected marker
    let marker = selectedAddons.includes("zeroDepreciation") ? zdlp : "";
    //Go digit claim selection: Default set to NULL
    //prettier-ignore
    groupedQuotesByIC = CreateMarker(marker, markerList, groupedQuotesByIC, "godigit")
    //GDD QUOTES
    let markerList_gdd = NoOfClaims(groupedQuotesByIC, "gdd_godigit");
    //setting list in state
    setClaimList_gdd(markerList_gdd);
    //selected marker
    let marker_gdd = selectedAddons.includes("zeroDepreciation")
      ? zdlp_gdd
      : "";
    //Go digit claim selection: Default set to NULL
    //prettier-ignore
    groupedQuotesByIC = CreateMarker(marker_gdd, markerList_gdd, groupedQuotesByIC, "gdd_godigit")
    //----x-----ZD No of claims-----x----

    //------- Tenure Filtering ----------
    groupedQuotesByIC = _filterTenure(groupedQuotesByIC, longtermParams);
    //---x--- Tenure Filtering ----------
    var quoteComprehesiveGroupedUnique = [];
    let newList = !_.isEmpty(groupedQuotesByIC) ? _.map(groupedQuotesByIC) : [];
    Grouping(
      newList,
      GetValidAdditionalKeys,
      selectedAddons,
      quoteComprehesiveGroupedUnique
    );

    //Apply relevance here based on addons / covers / accesories / discount
    //prettier-ignore
    quoteComprehesiveGroupedUnique = isRelevant ? relevance(quoteComprehesiveGroupedUnique, addOnsAndOthers, GetValidAdditionalKeys) : quoteComprehesiveGroupedUnique

    let uniquedGroup = quoteComprehesiveGroupedUnique.filter(Boolean);
    let uniquedGroup1 = _.uniqBy(uniquedGroup, "modifiedAlias");
    setQuoteComprehesiveGrouped(uniquedGroup1);
    setMultiUpdateQuotes && setMultiUpdateQuotes(groupedQuotesByIC);

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    addOnsAndOthers?.selectedAddons,
    sortBy,
    quoteComprehesive,
    zdlp,
    zdlp_gdd,
    isRelevant,
    addOnsAndOthers?.selectedCpa,
    addOnsAndOthers?.isTenure,
    tab,
    longTerm2,
    longTerm3,
  ]);
};

export const useGroupingShortTerm = (
  addOnsAndOthers,
  shortTerm3,
  ungroupedQuoteShortTerm3,
  shortTerm6,
  ungroupedQuoteShortTerm6,
  isRelevant,
  setGroupedQuoteShortTerm3,
  setGroupedQuoteShortTerm6
) => {
  useEffect(() => {
    if (
      (shortTerm3 && !_.isEmpty(ungroupedQuoteShortTerm3)) ||
      (shortTerm6 && !_.isEmpty(ungroupedQuoteShortTerm6))
    ) {
      let selectedAddons = !_.isEmpty(addOnsAndOthers?.selectedAddons)
        ? addOnsAndOthers?.selectedAddons
        : [];
      //prettier-ignore
      var groupedQuotesByIC = GroupByIC(shortTerm3 ? ungroupedQuoteShortTerm3 : ungroupedQuoteShortTerm6, true)
      var quoteComprehesiveGroupedUnique = [];
      let newList = !_.isEmpty(groupedQuotesByIC)
        ? _.map(groupedQuotesByIC)
        : [];
      //prettier-ignore
      Grouping(newList, GetValidAdditionalKeys, selectedAddons, quoteComprehesiveGroupedUnique)

      //Apply relevance here
      //prettier-ignore
      quoteComprehesiveGroupedUnique = isRelevant ? relevance(quoteComprehesiveGroupedUnique, addOnsAndOthers, GetValidAdditionalKeys) : quoteComprehesiveGroupedUnique
      let uniquedGroup = quoteComprehesiveGroupedUnique.filter(Boolean);
      let uniquedGroup1 = _.uniqBy(uniquedGroup, "company_alias");
      shortTerm3
        ? setGroupedQuoteShortTerm3(uniquedGroup1)
        : setGroupedQuoteShortTerm6(uniquedGroup1);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    addOnsAndOthers?.selectedAddons,
    ungroupedQuoteShortTerm3,
    ungroupedQuoteShortTerm6,
    isRelevant,
    addOnsAndOthers?.selectedCpa,
    addOnsAndOthers?.isTenure,
    shortTerm3,
    shortTerm6,
  ]);
};

export const useShareDrawer = (sendQuotes, addonDrawer, prevPopup2) => {
  useEffect(() => {
    if (sendQuotes || addonDrawer || prevPopup2) {
      document.body.style.position = "fixed";
      document.body.style.overflowY = "hidden";
      document.body.style.width = "100%";
    } else {
      document.body.style.position = "relative";
      document.body.style.height = "auto";
      document.body.style.overflowY = "auto";
    }
  }, [sendQuotes, addonDrawer, prevPopup2]);
};

export const useRenewalTPSelection = (temp_data) => {
  useEffect(() => {
    if (
      temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" &&
      temp_data?.corporateVehiclesQuoteRequest?.previousPolicyType ===
        "Third-party"
    ) {
      if (_.isEmpty(temp_data?.quoteLog?.premiumJson)) {
        document.getElementById("tab2") &&
          document.getElementById("tab2").click();
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.quoteLog?.premiumJson]);
};

export const useZeroDepError = (
  addOnsAndOthers,
  setErrorComprehensive,
  errorIcBased,
  errorCondition,
  shortTerm3,
  shortTerm6
) => {
  useEffect(() => {
    let errorIcBasedFiltered = errorIcBased;
    if (shortTerm3 || shortTerm6) {
      if (shortTerm3) {
        errorIcBasedFiltered = errorIcBasedFiltered.filter((id) =>
          ["short_term_3_breakin", "short_term_3"].includes(id.premiumTypeCode)
        );
      }
      if (shortTerm6) {
        errorIcBasedFiltered = errorIcBasedFiltered.filter((id) =>
          ["short_term_6_breakin", "short_term_6"].includes(id.premiumTypeCode)
        );
      }
    }
    if (addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation")) {
      setErrorComprehensive(
        errorIcBasedFiltered
          .filter((id) => id.type === errorCondition)
          .filter((id) => id.zeroDepError === true)
      );
    } else {
      setErrorComprehensive(
        errorIcBasedFiltered
          .filter((id) => id.type === errorCondition)
          .filter((id) => id.zeroDepError !== true)
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    errorIcBased,
    errorCondition,
    addOnsAndOthers?.selectedAddons,
    shortTerm3,
    shortTerm6,
  ]);
};

export const useMaxInbuiltAddonsCount = (
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
) => {
  useEffect(() => {
    let max = 0;
    (shortTerm3
      ? !_.isEmpty(quoteShortTerm3)
        ? quoteShortTerm3
        : []
      : shortTerm6
      ? !_.isEmpty(quoteShortTerm6)
        ? quoteShortTerm6
        : []
      : !_.isEmpty(quoteComprehesiveGrouped1)
      ? quoteComprehesiveGrouped1
      : quoteComprehesiveGrouped
    ).forEach((i) => {
      let inbuilt = i?.addOnsData?.inBuilt
        ? Object.keys(i?.addOnsData?.inBuilt)
        : [];
      let a1 = addOnsAndOthers?.selectedAddons
        ? addOnsAndOthers?.selectedAddons
        : [];

      let totalLength = _.union(inbuilt, a1);

      let totalLengthFiltered = _.filter(
        totalLength,
        (v) => v !== "nonzeroDepreciation"
      );

      if (max < totalLengthFiltered?.length) {
        max = totalLengthFiltered?.length;
      } else {
      }
    });
    setMaxAddonsMotor(max);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    quoteComprehesiveGrouped1,
    addOnsAndOthers?.selectedAddons,
    quoteComprehesiveGrouped,
    quoteShortTerm3,
    quoteShortTerm6,
    shortTerm3,
    shortTerm6,
    zdlp,
    zdlp_gdd,
  ]);
};

export const useKnowMoreSetter = (
  knowMoreObject,
  setKnowMoreQuote,
  tab,
  addOnsAndOthers,
  quoteComprehesiveGrouped1
) => {
  useEffect(() => {
    if (knowMoreObject?.quote) {
      if (knowMoreObject?.quote?.policyType === "Comprehensive") {
        let filteredKnowMoreQuote = quoteComprehesiveGrouped1.filter((id) =>
          id?.modifiedAlias
            ? id.modifiedAlias === knowMoreObject?.quote?.modifiedAlias
            : id.company_alias === knowMoreObject?.quote?.company_alias
        );

        setKnowMoreQuote(filteredKnowMoreQuote[0]);
      } else {
        setKnowMoreQuote(knowMoreObject?.quote);
      }
    }
  }, [
    tab,
    knowMoreObject,
    addOnsAndOthers?.selectedAddons,
    quoteComprehesiveGrouped1,
  ]);
};

export const useOnPopupCloseReload = (dispatch, temp_data, prevPopup2) => {
  useEffect(() => {
    if (!prevPopup2) {
      //	dispatch(clear());
      dispatch(CancelAll(false));
      dispatch(
        set_temp_data({
          reloaded: temp_data?.reloaded ? temp_data?.reloaded + 1 : 1,
        })
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [prevPopup2]);
};

export const useQuotePageTracking = (temp_data) => {
  const [trackCount, setTrackCount] = useState(false);
  useEffect(() => {
    if (
      !trackCount &&
      !_.isEmpty(temp_data) &&
      temp_data?.corporateVehiclesQuoteRequest?.businessType
    ) {
      setTrackCount(true);
      //Analytics | On Quote Page Landing.
      _quotePageTracking(temp_data);
    }
  }, [temp_data]);
};
