/*eslint-disable*/
import React, { useEffect, useState } from "react";
import styled, { keyframes } from "styled-components";
import demoLogo from "../../../../assets/img/logo02.png";
import { useHistory } from "react-router-dom";
import { Row, Col, Badge, Spinner } from "react-bootstrap";
import { useDispatch, useSelector } from "react-redux";
import { useLocation } from "react-router";
import _ from "lodash";
import { setTempData } from "../../filterConatiner/quoteFilter.slice";
import {
  currencyFormater,
  Decrypt,
  toDate,
  reloadPage,
  fetchToken,
} from "utils";
import Skeleton from "react-loading-skeleton";
import { differenceInDays } from "date-fns";
import moment from "moment";
import {
  setSelectedQuote,
  SaveQuotesData,
  SaveAddonsData,
  clear,
  setQuotesList,
  setFinalPremiumList,
  setFinalPremiumList1,
  clearFinalPremiumList,
  saveSelectedQuoteResponse,
  CancelAll,
  setLoadingFromPDf,
  setzdAvailablity,
} from "../../quote.slice";
import { parseInt } from "lodash";
import { useMediaPredicate } from "react-media-hook";
import swal from "sweetalert";
import { CustomTooltip } from "components";
import { TypeReturn } from "modules/type";
import { ExpandMore, ExpandLess } from "@mui/icons-material";
import DeleteOutlineOutlinedIcon from "@mui/icons-material/DeleteOutlineOutlined";
import InfoOutlinedIcon from "@mui/icons-material/InfoOutlined";
import {
  _buyNow,
  _polictTypeReselect,
  _addonValue,
  _addonCalc,
} from "../card-logic";
import QuotesCardSkeleton from "../skeleton";
import { getHighestIdv, getLowestIdv } from "../../quotesPopup/idvPopup/helper";
import {
  _premiumTracking,
  _saveQuoteTracking,
} from "analytics/quote-page/quote-tracking";
import { _discount } from "../../quote-logic";
import { getAddonName } from "../../quoteUtil";
import { calculations } from "../../calculations/ic-config/calculations-fallback";
import PayAsYouDrive from "../payd";
import { _evaluateCommission } from "modules/quotesPage/calculations/commission/evaluate-comission";

export const QuoteCard = ({
  quote,
  register,
  index,
  compare,
  progressPercent,
  lessthan767,
  length,
  watch,
  onCompare,
  type,
  typeId,
  setPrevPopup,
  prevPopup,
  setSelectedId,
  popupCard,
  multiPopupCard,
  setSelectedCompanyName,
  setSelectedCompanyAlias,
  gstToggle,
  maxAddonsMotor,
  setSelectedIcId,
  setKnowMoreObject,
  setKnowMore,
  knowMore,
  setSelectedKnowMore,
  quoteComprehesiveGrouped,
  knowMoreCompAlias,
  allQuoteloading,
  sendQuotes,
  setApplicableAddonsLits,
  setPrevPopupTp,
  setQuoteData,
  isMobileIOS,
  journey_type,
  zdlp,
  setZdlp,
  mobileComp,
  setMobileComp,
  claimList,
  zdlp_gdd,
  setZdlp_gdd,
  claimList_gdd,
  diffDays,
  date,
  NoOfDays,
  CompareData,
  loadingNTooltip,
  renewalFilter,
  FetchQuotes,
  multiUpdateQuotes,
}) => {
  const dispatch = useDispatch();
  const history = useHistory();
  const { temp_data, isRedirectionDone, theme_conf } = useSelector(
    (state) => state.home
  );
  const { prevInsList, tempData } = useSelector((state) => state.quoteFilter);
  const {
    addOnsAndOthers,
    saveQuoteResponse,
    updateQuoteLoader,
    loadingFromPdf,
    zdAvailablity,
    masterLogos,
    paydLoading,
  } = useSelector((state) => state.quotes);
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const shared = query.get("shared");
  //Fetching addons
  const addonStructure = addOnsAndOthers?.dbStructure?.addonData?.addons
    ? addOnsAndOthers?.dbStructure?.addonData?.addons
    : [];

  let fetchedCalculations = calculations(
    [quote],
    true,
    false,
    addOnsAndOthers,
    type,
    temp_data
  );
  let calculatedQuote = !_.isEmpty(fetchedCalculations)
    ? fetchedCalculations[0]
    : {};

  let selectedProductEncrypted = query.get("productId")
    ? query.get("productId")
    : null;
  let selectedProduct = selectedProductEncrypted
    ? selectedProductEncrypted * 1
      ? selectedProductEncrypted
      : Decrypt(selectedProductEncrypted)
    : null;
  let selectedTypeEncrypted = lessthan767
    ? ""
    : query.get("selectedType")
    ? query.get("selectedType")
    : null;
  let selectedType = selectedTypeEncrypted
    ? Decrypt(selectedTypeEncrypted)
    : null;
  let selectedTermEncrypted = lessthan767
    ? ""
    : query.get("selectedTerm")
    ? query.get("selectedTerm")
    : null;
  let selectedTerm = selectedTermEncrypted
    ? Decrypt(selectedTermEncrypted)
    : null;

  const token = query.get("xutm") || localStorage?.SSO_user_motor;

  const [mouseHover, setMouseHover] = useState(false);
  const [mouseHoverBenefits, setMouseHoverBenefits] = useState(false);

  const [difference, setDifference] = useState(false);
  const [sort, setSort] = useState(0);

  const between9to12 = useMediaPredicate(
    "(min-width:993px) and (max-width:1250px)"
  );
  const between13to14 = useMediaPredicate(
    "(min-width:1350px) and (max-width:1450px)"
  );

  const _stToken = fetchToken();
  //-----------Product selection through url when redirected from pdf----------------

  const address =
    !_.isEmpty(masterLogos) &&
    masterLogos.filter((ic) => ic.companyAlias === "royal_sundaram");

  const displayAddress = !_.isEmpty(address)
    ? address[0]?.Address
      ? address[0]?.Address
      : false
    : false;

  const filterRenewal =
    renewalFilter &&
    quote?.isRenewal === "Y" &&
    temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
    import.meta.env.VITE_BROKER === "BAJAJ";

  useEffect(() => {
    !loadingFromPdf &&
      _polictTypeReselect(
        selectedProduct,
        selectedTerm,
        lessthan767,
        enquiry_id,
        selectedType,
        token,
        typeId,
        date,
        diffDays,
        NoOfDays,
        journey_type,
        _stToken,
        shared
      );
  }, [selectedProduct]);

  //-----------------sortByDefault----------------------
  useEffect(() => {
    setSort(tempData?.sortBy);
  }, [tempData?.sortBy]);

  //getingAddonValue
  const GetAddonValue = (addonName, addonDiscountPercentage) => {
    let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
    let additional = Object.keys(quote?.addOnsData?.additional);
    let selectedAddons = addOnsAndOthers?.selectedAddons;

    if (inbuilt?.includes(addonName)) {
      return (
        <span style={{ ...(lessthan767 && { fontSize: "9px" }) }}>
          {Number(quote?.addOnsData?.inBuilt[addonName]) !== 0 ? (
            `₹ ${currencyFormater(
              _addonValue(quote, addonName, addonDiscountPercentage, "inbuilt")
            )}`
          ) : (
            <>
              {
                <>
                  {lessthan767 ? (
                    <>
                      {" "}
                      <i className="fa fa-check" style={{ color: "green" }}></i>
                    </>
                  ) : (
                    <>
                      <Badge
                        variant="primary"
                        style={{ position: "relative", bottom: "2px" }}
                      >
                        Included
                      </Badge>
                    </>
                  )}
                </>
              }
            </>
          )}
        </span>
      );
    } else if (
      additional?.includes(addonName) &&
      selectedAddons?.includes(addonName) &&
      Number(quote?.addOnsData?.additional[addonName]) !== 0 &&
      typeof quote?.addOnsData?.additional[addonName] === "number"
    ) {
      return `₹ ${currencyFormater(
        _addonValue(quote, addonName, addonDiscountPercentage, false)
      )}`;
    } else if (
      additional?.includes(addonName) &&
      Number(quote?.addOnsData?.additional[addonName]) === 0
    ) {
      return "N/A";
    } else if (
      !additional?.includes(addonName) &&
      selectedAddons?.includes(addonName)
    ) {
      return "N/A";
    } else if (
      !(additional?.includes(addonName) || inbuilt?.includes(addonName))
    ) {
      return "N/A";
    } else if (Number(quote?.addOnsData?.additional[addonName]) === 0) {
      return "N/A";
    } else if (
      additional?.includes(addonName) &&
      !selectedAddons?.includes(addonName)
    ) {
      return "N/S";
    } else {
      return "N/A";
    }
  };

  //-----------------getting addon value without  gst---------------------

  const GetAddonValueNoGst = (addonName, addonDiscountPercentage) => {
    let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
    let additional = Object.keys(quote?.addOnsData?.additional);
    let selectedAddons = addOnsAndOthers?.selectedAddons;

    if (inbuilt?.includes(addonName)) {
      return (
        <span style={{ ...(lessthan767 && { fontSize: "9px" }) }}>
          {Number(quote?.addOnsData?.inBuilt[addonName]) !== 0 ? (
            `₹ ${currencyFormater(
              _addonValue(
                quote,
                addonName,
                addonDiscountPercentage,
                "inbuilt",
                "exclude-gst"
              )
            )}`
          ) : (
            <>
              {addonName === "roadSideAssistance" &&
              quote?.company_alias === "reliance" ? (
                <>-</>
              ) : lessthan767 ? (
                <>
                  {" "}
                  <i className="fa fa-check" style={{ color: "green" }}></i>
                </>
              ) : (
                <>
                  <Badge
                    variant="primary"
                    style={{ position: "relative", bottom: "2px" }}
                  >
                    Included
                  </Badge>
                </>
              )}
            </>
          )}
        </span>
      );

      //	return `Inbuilt ₹ ${quote?.addOnsData?.inBuilt[addonName]}`;
    } else if (
      additional?.includes(addonName) &&
      selectedAddons?.includes(addonName) &&
      Number(quote?.addOnsData?.additional[addonName]) !== 0 &&
      typeof quote?.addOnsData?.additional[addonName] === "number"
    ) {
      return `₹ ${currencyFormater(
        _addonValue(
          quote,
          addonName,
          addonDiscountPercentage,
          false,
          "exclude-gst"
        )
      )}`;
    } else if (
      additional?.includes(addonName) &&
      //	selectedAddons.includes(addonName) &&
      Number(quote?.addOnsData?.additional[addonName]) === 0
    ) {
      return "N/A";
    } else if (
      !additional?.includes(addonName) &&
      selectedAddons?.includes(addonName)
    ) {
      return "N/A";
    } else if (
      !(additional?.includes(addonName) || inbuilt?.includes(addonName))
    ) {
      return "N/A";
    } else if (Number(quote?.addOnsData?.additional[addonName]) === 0) {
      return "N/A";
    } else if (
      additional?.includes(addonName) &&
      !selectedAddons?.includes(addonName)
    ) {
      return "N/S";
    } else {
      return "N/A";
    }
  };

  //Calculation
  //prettier-ignore
  const { totalAddon1: totalAddon, totalOthersAddon, addonDiscount, 
          addonDiscountPercentage1: addonDiscountPercentage,
          totalPremiumB1: totalPremiumB, revisedNcb1: revisedNcb,
          otherDiscounts, totalPremiumA, totalPremiumc1: totalPremiumC,
          totalPremium1: totalPremium, gst1: gst, finalPremium1: finalPremium,
          uwLoading, totalLoading: extraLoading
         } = calculatedQuote || {};

  const [applicableAddons, setApplicableAddons] = useState(null);
  useEffect(() => {
    if (temp_data?.tab !== "tab2") {
      let additional = Object.keys(quote?.addOnsData?.additional);
      let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
      let selectedAddons = addOnsAndOthers?.selectedAddons || [];
      let additionalList = quote?.addOnsData?.additional;
      let inbuiltList = quote?.addOnsData?.inBuilt;
      var addonsSelectedList = [];
      if (!_.isEmpty(selectedAddons) || !_.isEmpty(inbuilt)) {
        selectedAddons.forEach((el) => {
          if (additional?.includes(el) && Number(additionalList[el])) {
            var newList = {
              name: getAddonName(el),
              premium: Number(additionalList[el]),
              ...(el === "zeroDepreciation" &&
                quote?.companyAlias === "godigit" && {
                  claimCovered: addonStructure.filter(
                    (x) => x?.name === "Zero Depreciation"
                  )?.[0]?.claimCovered,
                }),
            };
            addonsSelectedList.push(newList);
          }
        });

        inbuilt.forEach((el) => {
          var newList = {
            name: getAddonName(el),
            premium: Number(inbuiltList[el]),
            ...(el === "zeroDepreciation" &&
              quote?.companyAlias === "godigit" && {
                claimCovered: addonStructure.filter(
                  (x) => x?.name === "Zero Depreciation"
                )?.[0]?.claimCovered,
              }),
          };
          addonsSelectedList.push(newList);
        });

        setApplicableAddons(addonsSelectedList);
      } else {
        setApplicableAddons([]);
      }
    }
  }, [addOnsAndOthers?.selectedAddons, quote, temp_data?.tab]);

  //-----------------setting changed premium for premiuym recalculation-----------------

  useEffect(() => {
    if (tempData?.oldPremium && finalPremium) {
      setDifference(tempData?.oldPremium - finalPremium);
    } else {
      setDifference(false);
    }
  }, [finalPremium]);

  //------------------finalPremiumSave---------------------

  useEffect(() => {
    if (finalPremium && gst && onCompare) {
      var data = [
        {
          finalPremium: finalPremium,
          gst: gst,
          policyId: quote?.policyId,
          totalPremiumB: totalPremiumB,
          applicableAddons: applicableAddons,
          addonPremiumTotal: totalAddon,
          companyAlias: quote?.companyAlias,
        },
      ];
      dispatch(setFinalPremiumList(data));
    }
  }, [finalPremium, gst, onCompare]);

  useEffect(() => {
    if (finalPremium && gst && (sendQuotes || tempData?.sendQuote)) {
      var data = [
        {
          finalPremium: finalPremium,
          finalPremiumNoGst: totalPremium,
          gst: gst,
          policyId: quote?.policyId,
          name: quote?.companyName,
          idv: quote?.idv,
          logo: quote?.companyLogo,
          productName: quote?.productName,
          policyType: quote?.policyType,
          applicableAddons: applicableAddons,
          companyAlias: quote?.companyAlias,
        },
      ];
      dispatch(setFinalPremiumList1(data));
    } else {
      dispatch(clearFinalPremiumList());
    }
  }, [finalPremium, gst, sendQuotes, tempData?.sendQuote]);

  //For commission evaluation -This will be moved to calculations and calculations fallback later
  const KeyMapping = {
    odPremium: totalPremiumA,
    tpPremium: totalPremiumB,
    netPremium: totalPremium,
    netpremium: totalPremium,
    addonPremium: totalAddon,
    addonPd: (addOnsAndOthers?.selectedAddons || []).map((i) =>
      getAddonName(i)
    ),
    totalPremium: finalPremium,
  };
  //------------expiry logic-----------------------

  const [daysToExpiry, setDaysToExpiry] = useState(false);

  useEffect(() => {
    let a = temp_data?.expiry;
    let b = moment().format("DD-MM-YYYY");
    let diffDays = a && b && differenceInDays(toDate(b), toDate(a));
    setDaysToExpiry(diffDays);
  }, [temp_data?.expiry]);

  //-----------------previous ic condition for popup-----------------
  let prevInsName = prevInsList.filter((i) => i.tataAig === temp_data?.prevIc);

  const [prevIcData, setPrevIcData] = useState(false);

  useEffect(() => {
    if (
      temp_data?.prevIc &&
      temp_data?.prevIc !== "others" &&
      temp_data?.prevIc !== "Not selected" && 
      (temp_data?.corporateVehiclesQuoteRequest?.isPopupShown === "Y" || temp_data?.isPopupShown === "Y" || temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") 
      // &&
      // theme_conf?.broker_config?.ncbconfig === "No" 
    ) {
      setPrevIcData(true);
    } else {
      setPrevIcData(false);
    }
  }, [temp_data?.prevIc]);

  //---------------handling buy now button click-----------------

  const handleClick = async () => {
    dispatch(setLoadingFromPDf(true));
    dispatch(CancelAll(true));
    //Analytics | Buy now proceeded.
    _saveQuoteTracking(
      quote,
      temp_data,
      applicableAddons,
      type,
      finalPremium,
      tempData
    );
    if (
      !temp_data?.newCar &&
      !prevIcData &&
      !popupCard &&
      tempData?.policyType !== "Third-party" &&
      (quote?.policyType === "Comprehensive" ||
        quote?.policyType === "Short Term" ||
        quote?.policyType === "Own Damage") &&
      daysToExpiry <= 90
    ) {
      setPrevPopup(true);
      setSelectedId(quote?.policyId);
      setSelectedCompanyName(quote?.companyName);
      setSelectedCompanyAlias(quote?.company_alias);
      setApplicableAddonsLits(
        !_.isEmpty(applicableAddons) && applicableAddons.map((x) => x.name)
      );
      setSelectedIcId(quote?.companyId);
      dispatch(
        setTempData({
          oldPremium: finalPremium,
        })
      );
    }else if (
      !prevPopup &&
      (temp_data?.newCar ||
        prevIcData ||
        !temp_data?.fastlaneNcbPopup ||
        quote?.policyType === "Third Party" ||
        tempData?.policyType === "Third-party" ||
        tempData?.policyType === "Not sure" ||
        daysToExpiry > 90)
    ) {

      dispatch(setSelectedQuote(quote));

      if (
        temp_data?.tab === "tab2" ||
        tempData?.policyType === "Third-party" ||
        daysToExpiry > 90
      ) {

        if (temp_data?.tab === "tab2") {
          var newSelectedAccesories = [];
          if (
            addOnsAndOthers?.selectedAccesories?.includes(
              "External Bi-Fuel Kit CNG/LPG"
            )
          ) {
            var newD = {
              name: "External Bi-Fuel Kit CNG/LPG",
              sumInsured: Number(addOnsAndOthers?.externalBiFuelKit),
            };
            newSelectedAccesories.push(newD);
          }
          var discount = [];

          if (addOnsAndOthers?.selectedDiscount?.includes("TPPD Cover")) {
            discount.push({ name: "TPPD Cover" });
          }
          if (
            addOnsAndOthers?.selectedDiscount?.includes(
              "Vehicle Limited to Own Premises"
            )
          ) {
            discount.push({ name: "Vehicle Limited to Own Premises" });
          }
          var data1 = {
            enquiryId: temp_data?.enquiry_id || enquiry_id,

            addonData: {
              addons: null,
              accessories: newSelectedAccesories,
              discounts: discount,
            },
          };

          dispatch(SaveAddonsData(data1));
        }
      } else {
        let addonLists = [];
        let addonListRedux = addOnsAndOthers?.selectedAddons || [];
        addonListRedux.forEach((el) => {
          let data;
          if (el === "additionalTowing") {
            data = {
              name: getAddonName(el),
              sumInsured: !_.isEmpty(
                addOnsAndOthers?.dbStructure?.addonData?.addons
              )
                ? addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
                    (x) => x?.name === "Additional Towing"
                  )?.[0]?.sumInsured
                : "10000",
            };
          } else if (el === "zeroDepreciation") {
            data = {
              name: getAddonName(el),
              claimCovered: !_.isEmpty(
                addOnsAndOthers?.dbStructure?.addonData?.addons
              )
                ? addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
                    (x) => x?.name === "Zero Depreciation"
                  )?.[0]?.claimCovered
                : "ONE",
            };
          } else {
            data = {
              name: getAddonName(el),
            };
          }

          addonLists.push(data);
        });

        var data2 = {
          enquiryId: temp_data?.enquiry_id || enquiry_id,

          addonData: {
            addons: addonLists,
            compulsory_personal_accident:
              addOnsAndOthers?.selectedCpa?.includes(
                "Compulsory Personal Accident"
              )
                ? [
                    {
                      name: "Compulsory Personal Accident",
                      ...(!_.isEmpty(_.compact(addOnsAndOthers?.isTenure)) && {
                        tenure: TypeReturn(type) === "car" ? 3 : 5,
                      }),
                    },
                  ]
                : [
                    {
                      reason:
                        "I have another motor policy with PA owner driver cover in my name",
                    },
                  ],
          },
        };
        dispatch(SaveAddonsData(data2));
      }
      var QuoteData = {
        enquiryId: temp_data?.enquiry_id || enquiry_id,
        traceId: temp_data?.traceId,
        icId: quote?.companyId,
        icAlias: quote?.companyName,
        productSubTypeId: quote?.productSubTypeId,
        masterPolicyId: quote?.masterPolicyId?.policyId,
        premiumJson: {
          ...quote,
          deductionOfNcb: revisedNcb,
          ...(temp_data?.odOnly && { IsOdBundledPolicy: "Y" }),
          ...(quote?.companyAlias === "royal_sundaram" &&
            quote?.isRenewal !== "Y" && {
              icAddress: displayAddress,
              addOnsData: {
                ...quote?.addOnsData,
                ...(!_.isEmpty(quote?.addOnsData?.additional) && {
                  additional: Object.fromEntries(
                    Object.entries(quote?.addOnsData?.additional).map(
                      ([k, v]) => [
                        k,
                        _discount(
                          v,
                          addonDiscountPercentage,
                          quote?.companyAlias,
                          k
                        ),
                      ]
                    )
                  ),
                }),
                ...(!_.isEmpty(quote?.addOnsData?.inBuilt) && {
                  inBuilt: Object.fromEntries(
                    Object.entries(quote?.addOnsData?.inBuilt).map(([k, v]) => [
                      k,
                      _discount(
                        v,
                        addonDiscountPercentage,
                        quote?.companyAlias,
                        k
                      ),
                    ])
                  ),
                }),
              },
            }),
          ...(quote?.companyAlias === "sbi" &&
            addOnsAndOthers?.selectedCpa?.includes(
              "Compulsory Personal Accident"
            ) &&
            !_.isEmpty(addOnsAndOthers?.isTenure) &&
            quote?.coverUnnamedPassengerValue * 1 && {
              coverUnnamedPassengerValue:
                quote?.coverUnnamedPassengerValue *
                (TypeReturn(type) === "bike" ? 5 : 3),
            }),
          ...(quote?.companyAlias === "sbi" &&
            addOnsAndOthers?.selectedCpa?.includes(
              "Compulsory Personal Accident"
            ) &&
            !_.isEmpty(addOnsAndOthers?.isTenure) &&
            quote?.motorAdditionalPaidDriver * 1 && {
              motorAdditionalPaidDriver:
                quote?.motorAdditionalPaidDriver *
                (TypeReturn(type) === "bike" ? 5 : 3),
            }),
        },
        exShowroomPriceIdv: quote?.idv,
        exShowroomPrice: quote?.showroomPrice,
        finalPremiumAmount: finalPremium,
        odPremium: totalPremiumA,
        tpPremium: totalPremiumB,
        addonPremiumTotal: totalAddon,
        serviceTax: gst,
        revisedNcb: revisedNcb,
        applicableAddons:
          quote?.companyAlias === "royal_sundaram" && quote?.isRenewal !== "Y"
            ? !_.isEmpty(applicableAddons)
              ? applicableAddons?.map((el) => ({
                  ...el,
                  ...{
                    premium: _discount(
                      el?.premium,
                      addonDiscountPercentage,
                      quote?.companyAlias,
                      el?.name
                    ),
                  },
                }))
              : []
            : applicableAddons,
        prevInsName: prevInsName[0]?.previousInsurer,
      };
      dispatch(SaveQuotesData(QuoteData));
    }
  };
  //---------------redirect to proposal after buy now succeed-----------------
  useEffect(() => {
    if (saveQuoteResponse && !updateQuoteLoader) {
      dispatch(CancelAll(false));

      history.push(
        `/${type}/proposal-page?enquiry_id=${enquiry_id}${
          token ? `&xutm=${token}` : ``
        }${typeId ? `&typeid=${typeId}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${_stToken ? `&stToken=${_stToken}` : ``}${
          shared ? `&shared=${shared}` : ``
        }`
      );
      dispatch(saveSelectedQuoteResponse(false));
      dispatch(setQuotesList([]));
      dispatch(clear());
    }
  }, [saveQuoteResponse, updateQuoteLoader]);

  //--------------for displaying base premium quote card-----------------

  const [basePrem, setBasePrem] = useState(quote?.finalPayableAmount);
  const [basePremNoGst, setBasePremNoGst] = useState(quote?.finalPayableAmount);

  useEffect(() => {
    if (temp_data?.journeyCategory !== "GCV") {
      setBasePrem(
        Math.round(
          totalPremiumA * 1 -
            totalPremiumC * 1 +
            quote?.finalTpPremium * 1 +
            uwLoading * 1 +
            (totalPremiumA * 1 -
              totalPremiumC * 1 +
              quote?.finalTpPremium * 1 +
              uwLoading * 1) *
              0.18
        )
      );
      setBasePremNoGst(
        totalPremiumA * 1 -
          totalPremiumC * 1 +
          quote?.finalTpPremium * 1 +
          uwLoading * 1
      );
    } else {
      setBasePrem(
        totalPremiumA * 1 -
          totalPremiumC * 1 +
          quote?.finalTpPremium * 1 +
          (totalPremiumA * 1 -
            totalPremiumC * 1 +
            quote?.finalTpPremium * 1 -
            quote?.tppdPremiumAmount * 1 +
            quote?.tppdDiscount * 1 || 0) *
            0.18 +
          (quote?.tppdPremiumAmount * 1 - (quote?.tppdDiscount * 1 || 0)) * 0.12
      );
      setBasePremNoGst(
        totalPremiumA * 1 - totalPremiumC * 1 + quote?.finalTpPremium * 1
      );
    }
  }, [
    quote?.finalOdPremium,
    totalPremiumC,
    quote?.finalTpPremium,
    quote?.tppdPremiumAmount,
    sort,
    quote,
    addOnsAndOthers?.selectedAddons,
    quote?.tppdDiscount,
    uwLoading,
    totalPremiumA,
  ]);

  //--------------------display logic of addon card car-----------------

  const GetValidAdditionalKeys = (additional) => {
    var y = Object.entries(additional)
      .filter(([, v]) => Number(v) > 0)
      .map(([k]) => k);
    return y;
  };

  const compareSelection =
    !_.isEmpty(CompareData) &&
    !_.isEmpty(CompareData?.filter((x) => x.policyId === quote?.policyId));

  const [totalApplicableAddonsMotor, setTotalApplicableAddonsMotor] = useState(
    []
  );

  //-----------------setting dummy psave while quote loading-----------------
  const [numberOfInbuilt, setNumberOfInbuilt] = useState(0);
  const [dummySpace, setDummySpace] = useState(0);
  const [dummySpace1, setDummySpace1] = useState(0);
  useEffect(() => {
    if (maxAddonsMotor && quote) {
      setDummySpace1(maxAddonsMotor + numberOfInbuilt - 1);
    } else {
      setDummySpace1(0);
    }
  }, [quote, maxAddonsMotor, numberOfInbuilt]);
  useEffect(() => {
    if (maxAddonsMotor && quote) {
      setDummySpace(maxAddonsMotor - numberOfInbuilt);
    } else {
      setDummySpace(0);
    }
  }, [quote, maxAddonsMotor, numberOfInbuilt]);

  //-------------setting aplicable addons for quote card-----------------

  useEffect(() => {
    if (quote) {
      let x1 = quote?.addOnsData?.inBuilt
        ? Object.keys(quote?.addOnsData?.inBuilt)
        : [];

      //	let additional1 = quote?.addOnsData?.additional;
      //	var y1 = GetValidAdditionalKeys(additional1);
      var a1 = addOnsAndOthers?.selectedAddons;
      let z1 = [...x1];
      let applicableAddonMotor = [];
      if (a1 && x1) {
        applicableAddonMotor = _.union(a1, x1);
      }

      setNumberOfInbuilt(
        applicableAddonMotor ? applicableAddonMotor?.length : 0
      );
      setTotalApplicableAddonsMotor(applicableAddonMotor);
    }
  }, [quote, addOnsAndOthers?.selectedAddons]);
  //-----------------handle know more click-----------------

  const handleKnowMoreClick = async (data) => {
    //Analytics | Premium tracking
    _premiumTracking(quote, temp_data, applicableAddons, TypeReturn(type));
    setSelectedKnowMore(data);
    setKnowMore(true);
    var data1 = {
      quote: quote,
      totalAddon: totalAddon,
      totalPremium: totalPremium,
      gst: gst,
      finalPremium: finalPremium,
      totalPremiumA: totalPremiumA,
      totalPremiumB: totalPremiumB,
      totalPremiumC: totalPremiumC,
      applicableAddons: applicableAddons,
      type: type,
      prevInsName: prevInsName,
      revisedNcb: revisedNcb,
      popupCard: popupCard,
      setPrevPopup: setPrevPopup,
      prevPopup: prevPopup,
      addonDiscount: addonDiscount,
      addonDiscountPercentage: addonDiscountPercentage,
      totalOthersAddon: totalOthersAddon,
      totalApplicableAddonsMotor: totalApplicableAddonsMotor,
      uwLoading: uwLoading,
      otherDiscounts: otherDiscounts,
      icAddress: displayAddress,
      extraLoading: extraLoading,
    };
    setKnowMoreObject(data1);
  };

  //-----------------handle know more dynamically when grouping and quotes changed from premium breakup on ddon selection-----------------

  useEffect(() => {
    if (
      knowMore &&
      (quote?.modifiedAlias
        ? knowMoreCompAlias === quote?.modifiedAlias
        : knowMoreCompAlias === quote?.companyAlias)
    ) {
      var data = {
        quote: quote,
        selectedKnowMore: data,
        totalAddon: totalAddon,
        totalPremium: totalPremium,
        gst: gst,
        finalPremium: finalPremium,
        totalPremiumA: totalPremiumA,
        totalPremiumB: totalPremiumB,
        totalPremiumC: totalPremiumC,
        applicableAddons: applicableAddons,
        type: type,
        prevInsName: prevInsName,
        revisedNcb: revisedNcb,
        popupCard: popupCard,
        setPrevPopup: setPrevPopup,
        prevPopup: prevPopup,
        addonDiscount: addonDiscount,
        addonDiscountPercentage: addonDiscountPercentage,
        totalOthersAddon: totalOthersAddon,
        totalApplicableAddonsMotor: totalApplicableAddonsMotor,
        uwLoading: uwLoading,
        otherDiscounts: otherDiscounts,
        icAddress: displayAddress,
        extraLoading: extraLoading,
      };
      setKnowMoreObject(data);
    }
  }, [
    addOnsAndOthers,
    knowMore,
    quoteComprehesiveGrouped,
    knowMoreCompAlias,
    totalPremium,
    finalPremium,
    totalApplicableAddonsMotor,
    totalOthersAddon,
    totalAddon,
    totalPremiumB,
    totalPremiumC,
    applicableAddons,
    revisedNcb,
    gst,
    totalPremiumA,
    displayAddress,
  ]);
  let lessthan600 = useMediaPredicate("(max-width: 600px)");
  let lessthan400 = useMediaPredicate("(max-width: 400px)");

  useEffect(() => {
    if (
      // import.meta.env.VITE_BROKER !== "RB" &&
      TypeReturn(type) === "car" &&
      ((!_.isEmpty(claimList) && quote?.gdd !== "Y") ||
        (!_.isEmpty(claimList_gdd) && quote?.gdd === "Y"))
    ) {
      let check =
        quote?.companyAlias === "godigit" &&
        applicableAddons &&
        !_.isEmpty(
          applicableAddons.filter((item) => item?.name === "Zero Depreciation")
        );
      dispatch(setzdAvailablity([...zdAvailablity, check]));
    }
  }, [applicableAddons]);

  const ZD_Availablity = () => {
    // return import.meta.env.VITE_BROKER !== "RB" &&
    return TypeReturn(type) === "car" &&
      applicableAddons &&
      !_.isEmpty(
        applicableAddons.filter((item) => item?.name === "Zero Depreciation")
      ) &&
      quote?.companyAlias === "godigit" &&
      ((!_.isEmpty(claimList) && quote?.gdd !== "Y") ||
        (!_.isEmpty(claimList_gdd) && quote?.gdd === "Y"))
      ? true
      : false;
  };

  const mobRibbon = {
    fontSize: "8px",
    width: isMobileIOS ? "130px" : "115px",
    top: "-16px",
    right: "35px",
    textAlign: "center",
  };

  const handleCompare = () => {
    const checkbox = document.getElementById(`reviewAgree${quote?.policyId}`);
    if (!checkbox.disabled) {
      checkbox.click();
    } else {
      if (temp_data.tab === "tab2") {
        swal("Info", `You can only compare comprehensive quotes`, "info");
      } else {
        swal(
          "Info",
          `You can only compare up to ${
            lessthan767 ? "three" : "three"
          } quotes at once.`,
          "info"
        );
      }
    }
  };

  const isPayD =
    !_.isEmpty(quote?.payAsYouDrive) ||
    (!_.isEmpty(quote?.additionalTowingOptions) &&
      (addOnsAndOthers?.selectedAddons || []).includes("additionalTowing")) ||
    (quote?.companyAlias === "godigit" &&
      //check if zd is selected and zd has premium
      (((addOnsAndOthers?.selectedAddons || []).includes("zeroDepreciation") &&
        !_.isEmpty(quote?.addOnsData?.additional) &&
        quote?.addOnsData?.additional?.zeroDepreciation * 1) ||
        //check if the quote has inbuilt zd
        (!_.isEmpty(quote?.addOnsData?.inBuilt) &&
          quote?.addOnsData?.inBuilt?.zeroDepreciation)));

  const { standardBrokerage, customBrokerage } =
    _evaluateCommission(
      quote?.commission,
      totalPremiumA,
      totalPremiumC,
      totalPremiumB,
      totalPremium,
      totalAddon,
      finalPremium,
      KeyMapping
    ) || {};

  const renderPayout =
    quote?.commission?.rewardType === "POINTS"
      ? `${customBrokerage || standardBrokerage} Points`
      : `₹ ${customBrokerage || standardBrokerage} Payout`;

  return lessthan767 && !popupCard ? (
    <>
      <MobileQuoteCard getSelected={compareSelection}>
        <CompareCheckMobile>
          {!popupCard && quote?.isInspectionApplicable === "Y" ? (
            <FoldedRibbon
              style={{
                ...mobRibbon,
              }}
            >
              Inspection Required
            </FoldedRibbon>
          ) : (
            <noscript />
          )}
          {!popupCard &&
          quote?.isRenewal === "Y" &&
          temp_data?.expiry &&
          quote?.gdd !== "Y" ? (
            <FoldedRibbon
              style={{
                ...mobRibbon,
              }}
            >
              Renewal Quote
            </FoldedRibbon>
          ) : (
            <noscript />
          )}
          {quote?.gdd === "Y" && (
            <FoldedRibbon
              id={`robbon${index}`}
              style={{
                ...mobRibbon,
              }}
            >
              Pay As You Drive
            </FoldedRibbon>
          )}
          {quote?.ribbon && (
            <FoldedRibbon
              id={`ribbon${index}`}
              style={{
                ...mobRibbon,
              }}
            >
              {quote?.ribbon}
            </FoldedRibbon>
          )}
          {(customBrokerage || standardBrokerage) && (
            <FoldedRibbon
              id={`ribbon${index}`}
              style={{
                ...mobRibbon,
              }}
            >
              {renderPayout}
              {renderPayout}
            </FoldedRibbon>
          )}
          <StyledDiv
            disabled={temp_data.tab === "tab2" || quote?.dummyTile}
            mouseHover={mouseHover}
            onClick={handleCompare}
            style={{
              cursor:
                temp_data.tab === "tab2" || quote?.dummyTile
                  ? "not-allowed"
                  : "pointer",
              fontSize: "12px",
              pointerEvents: allQuoteloading ? "none" : "",
              display: "none",
            }}
          >
            Compare
          </StyledDiv>

          <StyledDiv1
            mobileComp={
              mobileComp &&
              (quote?.companyAlias === "godigit" ||
                (!popupCard && quote?.isInspectionApplicable === "Y") ||
                (!popupCard &&
                  quote?.isRenewal === "Y" &&
                  temp_data?.expiry &&
                  differenceInDays(
                    toDate(moment().format("DD-MM-YYYY")),
                    toDate(temp_data?.expiry)
                  ) <= 90))
            }
          >
            {mobileComp ? (
              <span
                className="group-check float-right"
                style={{
                  width: "5%",
                  display: "none",
                }}
              ></span>
            ) : (
              <noscript />
            )}
          </StyledDiv1>
        </CompareCheckMobile>

        <MobileQuoteCardTop>
          <Row>
            <Col lg={4} md={4} sm={4} xs="4">
              <LogoImg
                style={{ height: lessthan600 && "auto" }}
                src={quote?.companyLogo ? quote?.companyLogo : demoLogo}
                alt="Plan Logo"
              />
            </Col>
            <Col lg={4} md={4} sm={4} xs="4" style={{ padding: "0px" }}>
              <MobileIdvContainer dummyTile={quote?.dummyTile}>
                <div className="idvMobContainer">
                  <span
                    className="idvTextMob"
                    style={{ fontSize: !isMobileIOS ? "13px" : "11px" }}
                  >
                    {" "}
                    IDV {false ? "Value" : ""} :{" "}
                  </span>
                  <span
                    className="idvValMob"
                    style={{ fontSize: !isMobileIOS ? "13px" : "11px" }}
                  >
                    {temp_data?.tab === "tab2" ? (
                      <Badge
                        variant="secondary"
                        style={{
                          cursor: "pointer",
                          marginBottom: "5px",
                        }}
                      >
                        Not Applicable
                      </Badge>
                    ) : (
                      <span
                        style={{
                          color:
                            tempData?.idvChoosed === quote?.idv
                              ? ""
                              : tempData?.idvChoosed > quote?.idv
                              ? "red"
                              : tempData?.idvChoosed < quote?.idv
                              ? "green"
                              : "",
                        }}
                      >
                        ₹ {currencyFormater(quote?.idv)}
                      </span>
                    )}
                  </span>
                </div>
                {(tempData?.idvChoosed === quote?.idv ||
                  tempData?.idvChoosed < quote?.idv ||
                  tempData?.idvChoosed > quote?.idv) && (
                  <CustomTooltip
                    small
                    id={`idvTooltip${index}`}
                    place={"bottom"}
                    arrowPosition="top"
                    allowClick
                  />
                )}
                <PolicyDetails
                  isMobileIOS={isMobileIOS}
                  id={quote?.companyAlias}
                  onClick={() => {
                    quote?.companyAlias === "hdfc_ergo" &&
                    temp_data?.carOwnership
                      ? swal({
                          title: "Please Note",
                          text: 'Transfer of ownership is not allowed for this quote. Please select ownership change as "NO" to buy this quote',
                          icon: "info",
                        })
                      : quote?.noCalculation === "Y"
                      ? swal(
                          "Please Note",
                          "Premium Breakup is not available for this quote",
                          "info"
                        )
                      : handleKnowMoreClick("premiumBreakupPop");
                  }}
                >
                  Premium Breakup &gt;
                </PolicyDetails>
              </MobileIdvContainer>
            </Col>
            <Col lg={4} md={4} sm={4} xs="4">
              <CardBuyButton
                themeDisable={
                  quote?.companyAlias === "hdfc_ergo" && temp_data?.carOwnership
                }
                onClick={() =>
                  _buyNow(
                    theme_conf?.broker_config,
                    temp_data,
                    quote,
                    addOnsAndOthers,
                    isRedirectionDone,
                    token,
                    handleClick,
                    applicableAddons,
                    type
                  )
                }
                style={{
                  height: lessthan600 && "0",
                  lineHeight: lessthan600 && "0",
                  padding: lessthan600 && "17px 0px",
                  display: lessthan600 && "flex",
                  alignItems: lessthan600 && "center",
                  justifyContent: lessthan600 && "center",
                }}
              >
                {paydLoading &&
                quote?.companyAlias === paydLoading &&
                (quote?.payAsYouDrive ||
                  quote?.additionalTowingOptions ||
                  quote?.claimsCovered) ? (
                  <div style={{ textAlign: lessthan600 ? "center" : "" }}>
                    <Spinner size="sm" animation="grow" variant="light" />
                    <Spinner size="sm" animation="grow" variant="light" />
                    <Spinner size="sm" animation="grow" variant="light" />
                  </div>
                ) : (
                  <>
                    {gstToggle ? (
                      <small className="withGstText">incl. GST</small>
                    ) : (
                      <noscript />
                    )}
                    {quote?.dummyTile
                      ? "Renew"
                      : `₹ ${
                          !gstToggle && !popupCard
                            ? currencyFormater(totalPremium)
                            : currencyFormater(finalPremium) || ""
                        }`}
                  </>
                )}
              </CardBuyButton>
            </Col>
          </Row>
          <Row>
            <Col lg={4} md={4} sm={4} xs="4">
              {!_.isEmpty(quote?.usp) && (
                <UspContainer
                  onClick={() => setMouseHoverBenefits(!mouseHoverBenefits)}
                >
                  Features
                </UspContainer>
              )}
            </Col>
            <Col lg={4} md={4} sm={4} xs="4" className={"p-0"}></Col>
            <Col lg={4} md={4} sm={4} xs="4">
              {!mobileComp ? (
                <CashlessGarageMobContainer>
                  <CashlessGarageMob
                    style={{
                      cursor: !quote?.garageCount && "not-allowed",
                      color: !quote?.garageCount && "#6b6e7166",
                    }}
                    onClick={() => {
                      quote?.companyAlias === "hdfc_ergo" &&
                      temp_data?.carOwnership
                        ? swal({
                            title: "Please Note",
                            text: 'Transfer of ownership is not allowed for this quote. Please select ownership change as "NO" to buy this quote',
                            icon: "info",
                          })
                        : quote?.garageCount &&
                          handleKnowMoreClick("cashlessGaragePop");
                    }}
                  >
                    Cashless Garage
                  </CashlessGarageMob>
                </CashlessGarageMobContainer>
              ) : (
                <CashlessGarageMobContainer>
                  <CheckBoxContainer
                    getSelected={compareSelection}
                    btnDisable={length >= 3}
                  >
                    <input
                      type="checkbox"
                      className="round-check"
                      id={`reviewAgree${quote?.policyId}`}
                      name={`checkmark[${index}]`}
                      ref={register}
                      value={quote?.policyId}
                      defaultValue={quote?.policyId}
                      onClick={() =>
                        CompareData?.length === 1 &&
                        compareSelection &&
                        setMobileComp(false)
                      }
                      disabled={
                        (length >= (lessthan767 ? 3 : 3) &&
                          !watch(`checkmark[${index}]`)) ||
                        temp_data.tab === "tab2" ||
                        quote?.dummyTile ||
                        allQuoteloading
                      }
                    />
                    <label
                      style={{
                        display: temp_data?.tab === "tab2" && "none",
                        fontSize: "9px",
                      }}
                      className="round-label"
                      htmlFor={`reviewAgree${quote?.policyId}`}
                    >
                      {compareSelection ? (
                        <text
                          style={{
                            display: "flex",
                            justifyContent: "center",
                            alignItems: "center",
                            gap: "5px",
                          }}
                        >
                          <DeleteOutlineOutlinedIcon
                            style={{ fontSize: "13px" }}
                          />
                          Remove
                        </text>
                      ) : (
                        "+ Compare"
                      )}
                    </label>
                  </CheckBoxContainer>
                </CashlessGarageMobContainer>
              )}
            </Col>
          </Row>
        </MobileQuoteCardTop>

        <HowerTabsMobile>
          <ContentTabBenefitsMobile
            className={
              mouseHoverBenefits && quote?.usp?.length > 0
                ? "showBenefits"
                : "hideBenefits"
            }
            style={{ cursor: "default" }}
          >
            <ul>
              {quote?.usp?.length > 0 &&
                quote?.usp?.map((item, index) => <li>{item?.usp_desc}</li>)}
            </ul>
          </ContentTabBenefitsMobile>
        </HowerTabsMobile>

        <AddonAndCpaMobile dummyTile={quote?.dummyTile}>
          {isPayD && quote?.policyType !== "Third Party" && type === "car" ? (
            <div
              style={{
                borderRadius: "5px",
                color: "black",
                fontWeight: "600",
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
                margin: "5px -7px",
                width: "100%",
              }}
            >
              <PayAsYouDrive
                payD={quote?.payAsYouDrive || quote?.additionalTowingOptions}
                isTowing={!_.isEmpty(quote?.additionalTowingOptions)}
                FetchQuotes={FetchQuotes}
                quote={quote}
                type={TypeReturn(type)}
                enquiry_id={enquiry_id}
                temp_data={temp_data}
                addOnsAndOthers={addOnsAndOthers}
                noPadding
                lessthan767={lessthan767}
                multiUpdateQuotes={multiUpdateQuotes?.godigit || []}
              />
            </div>
          ) : (
            <noscript />
          )}

          <Row style={{ ...(quote?.dummyTile && { visibility: "hidden" }) }}>
            <Col lg={6} md={6} sm={6} xs="6">
              <AddonContainerMobile>
                <div className="addonNameMobile">Base Premium</div>
                <div className="addonValueMobile">
                  {" "}
                  ₹{" "}
                  {!gstToggle
                    ? currencyFormater(basePremNoGst)
                    : currencyFormater(basePrem)}
                </div>
              </AddonContainerMobile>
            </Col>
            {(addOnsAndOthers?.selectedCpa?.includes(
              "Compulsory Personal Accident"
            ) ||
              totalApplicableAddonsMotor.length > 0) && (
              <>
                <>
                  {addOnsAndOthers?.selectedCpa?.includes(
                    "Compulsory Personal Accident"
                  ) && (
                    <Col lg={6} md={6} sm={6} xs="6">
                      <AddonContainerMobile>
                        <div className="addonNameMobile">Compulsory PA</div>
                        <div className="addonValueMobile">
                          {_.isEmpty(addOnsAndOthers?.isTenure) ? (
                            !quote?.compulsoryPaOwnDriver * 1 ? (
                              <>N/A</>
                            ) : gstToggle == 0 ? (
                              `₹ ${currencyFormater(
                                parseInt(quote?.compulsoryPaOwnDriver)
                              )}`
                            ) : (
                              `₹ ${currencyFormater(
                                parseInt(quote?.compulsoryPaOwnDriver * 1.18)
                              )}`
                            )
                          ) : !(quote?.multiYearCpa * 1) ? (
                            <>
                              {/*<i
                              className="fa fa-close"
                              style={{ color: "red" }}
                          ></i>*/}
                              N/A
                            </>
                          ) : gstToggle == 0 ? (
                            `₹ ${currencyFormater(
                              parseInt(quote?.multiYearCpa)
                            )}`
                          ) : (
                            `₹ ${currencyFormater(
                              parseInt(quote?.multiYearCpa * 1.18)
                            )}`
                          )}
                        </div>
                      </AddonContainerMobile>
                    </Col>
                  )}
                </>
                <>
                  {temp_data?.tab !== "tab2" &&
                    totalApplicableAddonsMotor
                      .sort()
                      .reverse()
                      .map((item, index) => (
                        <>
                          <Col
                            lg={6}
                            md={6}
                            sm={6}
                            xs="6"
                            style={{
                              display:
                                quote?.company_alias === "reliance" &&
                                item === "roadSideAssistance" &&
                                TypeReturn(type) === "cv" &&
                                "none",
                            }}
                          >
                            <AddonContainerMobile>
                              <div className="addonNameMobile">
                                {getAddonName(item)}
                              </div>
                              <div className="addonValueMobile">
                                {GetAddonValue(
                                  item,
                                  addonDiscountPercentage
                                ) === "N/A" ? (
                                  <>
                                    {/* <i
                                      className="fa fa-close"
                                      style={{ color: "red" }}
                                    ></i> */}
                                    N/A
                                  </>
                                ) : (
                                  <>
                                    {quote?.company_alias === "godigit" &&
                                    item === "zeroDepreciation" &&
                                    paydLoading &&
                                    quote?.companyAlias === paydLoading &&
                                    (quote?.payAsYouDrive ||
                                      quote?.additionalTowingOptions ||
                                      quote?.claimsCovered)
                                      ? "Fetching..."
                                      : !gstToggle
                                      ? GetAddonValueNoGst(
                                          item,
                                          addonDiscountPercentage
                                        )
                                      : GetAddonValue(
                                          item,
                                          addonDiscountPercentage
                                        )}
                                  </>
                                )}
                              </div>
                            </AddonContainerMobile>
                          </Col>
                        </>
                      ))}
                </>
              </>
            )}
          </Row>
        </AddonAndCpaMobile>
      </MobileQuoteCard>
    </>
  ) : (
    // Mobile Card End
    <>
      <Col
        lg={!popupCard ? 4 : multiPopupCard ? 4 : 6}
        md={6}
        sm={12}
        style={{
          marginTop: !popupCard ? "30px" : "20px",
          maxWidth: popupCard ? (lessthan767 ? "100%" : "50%") : "",
        }}
      >
        <QuoteCardMain
          onMouseEnter={() => setMouseHover(true)}
          onMouseLeave={() => setMouseHover(false)}
          isRenewal={quote?.isRenewal === "Y" && !popupCard}
          hover={!popupCard}
        >
          {!popupCard && quote?.isInspectionApplicable === "Y" ? (
            <FoldedRibbon>Inspection Required</FoldedRibbon>
          ) : (
            <noscript />
          )}
          {(!popupCard &&
            quote?.isRenewal === "Y" &&
            temp_data?.expiry &&
            quote?.gdd !== "Y") ||
          "" ? (
            <FoldedRibbon>Renewal Quote</FoldedRibbon>
          ) : (
            <noscript />
          )}
          {quote?.ribbon ? (
            <FoldedRibbon>{quote?.ribbon}</FoldedRibbon>
          ) : (
            <noscript />
          )}
          {quote?.gdd === "Y" && (
            <>
              <FoldedRibbon
                style={{
                  fontSize: "9.5px",
                  fontWeight: "700",
                  cursor: "pointer",
                }}
                id={`gdd`}
                data-tip={
                  "<h3 > DIGIT's Pay As You Drive Plan</h3> <div>Insurer offers an extra discount on your Own Damage (OD) premium if you drive less than 15,000 kms per year, in exchange for you uploading 7 Photos of your car before your current policy expires.</div>"
                }
                data-html={true}
                data-for={`gddToolTip${index}`}
                // htmlFor="gddToolTip"
              >
                Pay As You Drive
              </FoldedRibbon>
              <CustomTooltip
                rider="true"
                id={`gddToolTip${index}`}
                place={"left"}
                arrowPosition="top"
                arrowColor
                Position={{ top: 40, left: 50 }}
              />
            </>
          )}
          {customBrokerage || standardBrokerage ? (
            <FoldedRibbon>{renderPayout}</FoldedRibbon>
          ) : (
            <noscript />
          )}
          {!popupCard &&
            progressPercent === 100 &&
            tempData.quoteComprehesiveGrouped.length > 1 && (
              <>
                <StyledDiv
                  tab={temp_data.tab}
                  disabled={temp_data.tab === "tab2" || quote?.dummyTile}
                  mouseHover={mouseHover}
                  onClick={handleCompare}
                  style={{
                    cursor:
                      temp_data.tab === "tab2" || quote?.dummyTile
                        ? "not-allowed"
                        : "pointer",
                    fontSize: "12px",
                    pointerEvents:
                      allQuoteloading || filterRenewal ? "none" : "",
                    ...((quote?.dummyTile || filterRenewal) && {
                      visibility: "hidden",
                    }),
                  }}
                >
                  Compare
                </StyledDiv>

                <StyledDiv1 tab={temp_data.tab}>
                  {true && (
                    <span
                      className="group-check float-right  "
                      style={{
                        width: "5%",
                        ...((quote?.dummyTile || filterRenewal) && {
                          visibility: "hidden",
                        }),
                      }}
                    >
                      <input
                        type="checkbox"
                        className="round-check"
                        id={`reviewAgree${quote?.policyId}`}
                        name={`checkmark[${index}]`}
                        ref={register}
                        value={quote?.policyId}
                        defaultValue={quote?.policyId}
                        disabled={
                          //disabled during loading
                          (length >= (lessthan767 ? 3 : 3) &&
                            !watch(`checkmark[${index}]`)) ||
                          temp_data.tab === "tab2" ||
                          quote?.dummyTile ||
                          allQuoteloading ||
                          filterRenewal
                            ? true
                            : false
                        }
                      />
                      <label
                        className="round-label"
                        onClick={handleCompare}
                      ></label>
                    </span>
                  )}
                </StyledDiv1>
              </>
            )}
          <CardOtherItemInner>
            {!popupCard ? (
              <>
                <Row>
                  <Col lg={6} md={6} sm={6} xs="6">
                    <LogoImg
                      src={quote?.companyLogo ? quote?.companyLogo : demoLogo}
                      alt="Plan Logo"
                    />
                  </Col>
                </Row>

                <Row>
                  <Col
                    lg={5}
                    md={5}
                    sm={5}
                    xs="5"
                    style={{
                      ...(quote?.dummyTile && { visibility: "hidden" }),
                    }}
                  >
                    <div className="coverIdv"> IDV Value</div>{" "}
                    {(tempData?.idvChoosed === quote?.idv ||
                      tempData?.idvChoosed < quote?.idv ||
                      tempData?.idvChoosed > quote?.idv) && (
                      <CustomTooltip
                        small
                        id={`idvTooltip${index}`}
                        place={"bottom"}
                        arrowPosition="top"
                        Position={{ top: 10, left: 0 }}
                      />
                    )}
                    <div className="idvPrice">
                      {temp_data?.tab === "tab2" ? (
                        <Badge
                          variant="secondary"
                          style={{
                            cursor: "pointer",
                            marginBottom: "5px",
                          }}
                        >
                          Not Applicable
                        </Badge>
                      ) : (
                        <span
                          name="idv_value"
                          data-tip={`<div>${
                            tempData?.idvChoosed === quote?.idv
                              ? "Offered IDV matches your preferred IDV"
                              : tempData?.idvChoosed > quote?.idv
                              ? "Offered IDV is less than your preferred IDV"
                              : tempData?.idvChoosed < quote?.idv
                              ? "Offered IDV is more than your preferred IDV"
                              : ""
                          } </div>`}
                          data-html={true}
                          data-for={`idvTooltip${index}`}
                          style={{
                            color:
                              tempData?.idvChoosed === quote?.idv
                                ? ""
                                : tempData?.idvChoosed > quote?.idv
                                ? "red"
                                : tempData?.idvChoosed < quote?.idv
                                ? "green"
                                : "",
                          }}
                        >
                          ₹ {currencyFormater(quote?.idv)}
                        </span>
                      )}
                    </div>
                  </Col>
                  <Col lg={7} md={7} sm={7} xs="7">
                    <CardBuyButton
                      themeDisable={
                        quote?.companyAlias === "hdfc_ergo" &&
                        temp_data?.carOwnership
                      }
                      title={quote?.commission || ""}
                      onClick={() =>
                        _buyNow(
                          theme_conf?.broker_config,
                          temp_data,
                          quote,
                          addOnsAndOthers,
                          isRedirectionDone,
                          token,
                          handleClick,
                          applicableAddons,
                          type
                        )
                      }
                      id={`buy-${quote?.policyId}`}
                      disabled={
                        paydLoading &&
                        quote?.companyAlias === paydLoading &&
                        (quote?.payAsYouDrive ||
                          quote?.additionalTowingOptions ||
                          quote?.claimsCovered)
                      }
                    >
                      {gstToggle ? (
                        <small className="withGstText">incl. GST</small>
                      ) : (
                        <noscript />
                      )}
                      <div
                        className="buyText"
                        style={
                          paydLoading &&
                          quote?.companyAlias === paydLoading &&
                          (quote?.payAsYouDrive ||
                            quote?.additionalTowingOptions ||
                            quote?.claimsCovered)
                            ? { marginTop: "12px" }
                            : {}
                        }
                      >
                        {paydLoading &&
                        quote?.companyAlias === paydLoading &&
                        (quote?.payAsYouDrive ||
                          quote?.additionalTowingOptions ||
                          quote?.claimsCovered) ? (
                          <div>
                            <Spinner
                              size="sm"
                              animation="grow"
                              variant="light"
                            />
                            <Spinner
                              size="sm"
                              animation="grow"
                              variant="light"
                            />
                            <Spinner
                              size="sm"
                              animation="grow"
                              variant="light"
                            />
                          </div>
                        ) : (
                          <>
                            {" "}
                            {quote?.dummyTile
                              ? "Renew"
                              : popupCard
                              ? "PROCEED"
                              : "BUY NOW"}
                          </>
                        )}
                      </div>
                      <div
                        style={{
                          fontWeight:
                            import.meta.env.VITE_BROKER === "RB"
                              ? "600"
                              : "1000",
                          fontSize: "18px",
                          ...(paydLoading &&
                            quote?.companyAlias === paydLoading &&
                            (quote?.payAsYouDrive ||
                              quote?.additionalTowingOptions ||
                              quote?.claimsCovered) && {
                              display: "none",
                            }),
                        }}
                        className="buyPrice"
                      >
                        <span
                          translate="no"
                          style={{ fontWeight: "1000" }}
                          name="buy_now"
                        >
                          {" "}
                          {paydLoading &&
                          quote?.companyAlias === paydLoading &&
                          (quote?.payAsYouDrive ||
                            quote?.additionalTowingOptions ||
                            quote?.claimsCovered) ? (
                            <div>
                              <Spinner
                                size="sm"
                                animation="grow"
                                variant="light"
                              />
                              <Spinner
                                size="sm"
                                animation="grow"
                                variant="light"
                              />
                              <Spinner
                                size="sm"
                                animation="grow"
                                variant="light"
                              />
                            </div>
                          ) : (
                            <>
                              {quote?.dummyTile
                                ? "Quote"
                                : `₹ ${
                                    !gstToggle
                                      ? currencyFormater(totalPremium * 1)
                                      : currencyFormater(finalPremium * 1)
                                  }`}
                            </>
                          )}
                        </span>
                      </div>
                    </CardBuyButton>
                  </Col>
                </Row>
              </>
            ) : (
              <>
                <Row>
                  <Col lg={6} md={6} sm={6} xs="6">
                    <LogoImg
                      src={quote?.companyLogo ? quote?.companyLogo : demoLogo}
                      alt="Plan Logo"
                    />
                  </Col>

                  <Col lg={6} md={6} sm={6} xs="6">
                    <div
                      className="values"
                      style={{
                        border: "none",
                      }}
                    >
                      <div className="coverIdv text-center"> IDV Value</div>
                      <div
                        className="idvPrice text-center"
                        style={{
                          cursor: "pointer",
                        }}
                      >
                        {temp_data?.tab === "tab2" ? (
                          <Badge
                            variant="secondary"
                            style={{
                              cursor: "pointer",
                              marginLeft: "10px",
                              marginBottom: "5px",
                            }}
                          >
                            Not Applicable
                          </Badge>
                        ) : (
                          <span
                            name="idv_value"
                            data-tip={`<div>${
                              tempData?.idvChoosed === quote?.idv
                                ? "Offered IDV matches your preferred IDV"
                                : tempData?.idvChoosed > quote?.idv
                                ? "Offered IDV is less than your preferred IDV"
                                : "Offered IDV is more than your preferred IDV"
                            } </div>`}
                            data-html={true}
                            data-for={`idvTooltip${index}`}
                            style={{
                              color:
                                tempData?.idvChoosed === quote?.idv
                                  ? ""
                                  : tempData?.idvChoosed > quote?.idv
                                  ? "red"
                                  : "green",
                              cursor: "pointer !important",
                            }}
                          >
                            ₹ {currencyFormater(quote?.idv)}
                          </span>
                        )}
                      </div>
                    </div>
                  </Col>
                </Row>

                <Row>
                  <Col lg={12} md={12} sm={12} xs="12">
                    <CardBuyButton
                      onClick={
                        quote?.dummyTile
                          ? reloadPage(quote?.redirection_url)
                          : handleClick
                      }
                      style={{ width: "58%", padding: "3px 0px" }}
                      id={`buy-${quote?.policyId}`}
                    >
                      {gstToggle ? (
                        <small className="withGstText">incl. GST</small>
                      ) : (
                        <noscript />
                      )}
                      {popupCard ? "PROCEED" : "BUY NOW"}
                      <span translate="no" style={{ fontWeight: "1000" }}>
                        {" "}
                        {paydLoading &&
                        quote?.companyAlias === paydLoading &&
                        (quote?.payAsYouDrive ||
                          quote?.additionalTowingOptions ||
                          quote?.claimsCovered) ? (
                          <div>
                            <Spinner
                              size="sm"
                              animation="grow"
                              variant="light"
                            />
                            <Spinner
                              size="sm"
                              animation="grow"
                              variant="light"
                            />
                            <Spinner
                              size="sm"
                              animation="grow"
                              variant="light"
                            />
                          </div>
                        ) : (
                          <>
                            ₹{" "}
                            {!gstToggle && !popupCard
                              ? currencyFormater(totalPremium)
                              : currencyFormater(finalPremium)}
                          </>
                        )}
                      </span>
                    </CardBuyButton>
                  </Col>
                </Row>
              </>
            )}

            {quote?.usp && quote?.usp?.length > 0 && (
              <HowerTabs>
                <Badge
                  variant="secondary"
                  style={{
                    zIndex: 997,
                  }}
                  onMouseEnter={() => setMouseHoverBenefits(true)}
                  onMouseLeave={() => setMouseHoverBenefits(false)}
                >
                  Features{" "}
                  {mouseHoverBenefits ? (
                    <ExpandLess className="arrowIcon" />
                  ) : (
                    <ExpandMore className="arrowIcon" />
                  )}
                </Badge>
                <ContentTabBenefits
                  className={
                    mouseHoverBenefits && quote?.usp?.length > 0
                      ? "showBenefits"
                      : "hideBenefits"
                  }
                  style={{ cursor: "default" }}
                >
                  <ul>
                    {quote?.usp?.length > 0 &&
                      quote?.usp?.map((item, index) => (
                        <li key={index}>{item?.usp_desc}</li>
                      ))}
                  </ul>
                  {dummySpace1 && true && temp_data.tab !== "tab2" ? (
                    <>
                      {[...Array(dummySpace1)].map((elementInArray, index) => (
                        <>
                          <li style={{ listStyle: "none" }}>&nbsp;</li>
                        </>
                      ))}
                    </>
                  ) : (
                    <></>
                  )}
                </ContentTabBenefits>
              </HowerTabs>
            )}
          </CardOtherItemInner>
          <CardOtherItemNoBorder dummyTile={quote?.dummyTile}>
            <Row>
              {!popupCard && (
                <>
                  <>
                    {isPayD &&
                      quote?.policyType !== "Third Party" &&
                      type === "car" && (
                        <PayAsYouDrive
                          payD={
                            quote?.payAsYouDrive ||
                            quote?.additionalTowingOptions
                          }
                          isTowing={!_.isEmpty(quote?.additionalTowingOptions)}
                          FetchQuotes={FetchQuotes}
                          quote={quote}
                          type={TypeReturn(type)}
                          enquiry_id={enquiry_id}
                          temp_data={temp_data}
                          addOnsAndOthers={addOnsAndOthers}
                          lessthan767={lessthan767}
                          multiUpdateQuotes={multiUpdateQuotes?.godigit || []}
                        />
                      )}
                    {
                      <>
                        <Col lg={7} md={7} sm={7} xs={7}>
                          <ItemName>Base Premium</ItemName>
                        </Col>
                        <Col lg={5} md={5} sm={5} xs={5}>
                          <ItemPrice name="base_premium">
                            {quote?.company_alias === "bajaj_allianz" &&
                            quote?.isRenewal === "Y" ? (
                              <>
                                <NoAddonCotainer>
                                  <Badge
                                    variant="secondary"
                                    style={{ cursor: "pointer" }}
                                  >
                                    Not Applicable
                                  </Badge>
                                </NoAddonCotainer>
                              </>
                            ) : (
                              <>
                                {" "}
                                ₹{" "}
                                {!gstToggle
                                  ? currencyFormater(basePremNoGst)
                                  : currencyFormater(basePrem)}
                              </>
                            )}
                          </ItemPrice>
                        </Col>
                      </>
                    }
                    {addOnsAndOthers?.selectedCpa?.includes(
                      "Compulsory Personal Accident"
                    ) &&
                      _.isEmpty(addOnsAndOthers?.isTenure) && (
                        <>
                          <Col lg={8} md={8} sm={8} xs={8}>
                            <ItemName>Compulsory Personal Accident</ItemName>
                          </Col>
                          <Col lg={4} md={4} sm={4} xs={4}>
                            <ItemPrice>
                              {" "}
                              {gstToggle == 0 ? (
                                !quote?.compulsoryPaOwnDriver ||
                                quote?.compulsoryPaOwnDriver == 0 ? (
                                  <NoAddonCotainer>
                                    <Badge
                                      variant="danger"
                                      style={{ cursor: "pointer" }}
                                    >
                                      Not Available
                                    </Badge>
                                  </NoAddonCotainer>
                                ) : (
                                  "₹ " +
                                  currencyFormater(
                                    parseInt(quote?.compulsoryPaOwnDriver)
                                  )
                                )
                              ) : !quote?.compulsoryPaOwnDriver ||
                                quote?.compulsoryPaOwnDriver == 0 ? (
                                <NoAddonCotainer>
                                  <Badge
                                    variant="danger"
                                    style={{ cursor: "pointer" }}
                                  >
                                    Not Available
                                  </Badge>
                                </NoAddonCotainer>
                              ) : (
                                "₹ " +
                                currencyFormater(
                                  parseInt(quote?.compulsoryPaOwnDriver * 1.18)
                                )
                              )}
                            </ItemPrice>
                          </Col>
                        </>
                      )}
                    {addOnsAndOthers?.selectedCpa?.includes(
                      "Compulsory Personal Accident"
                    ) &&
                      !_.isEmpty(addOnsAndOthers?.isTenure) && (
                        <>
                          <Col lg={7} md={7} sm={7} xs={7}>
                            <ItemName>
                              CPA{" "}
                              {TypeReturn(type) === "car"
                                ? "3"
                                : TypeReturn(type) === "bike" && "5"}{" "}
                              years
                            </ItemName>
                          </Col>
                          <Col lg={5} md={5} sm={5} xs={5}>
                            <ItemPrice>
                              {quote?.multiYearCpa ? "₹ " : ""}
                              {!quote?.multiYearCpa ? (
                                <NoAddonCotainer>
                                  <Badge
                                    variant="danger"
                                    style={{ cursor: "pointer" }}
                                  >
                                    Not Available
                                  </Badge>
                                </NoAddonCotainer>
                              ) : gstToggle == 0 ? (
                                currencyFormater(parseInt(quote?.multiYearCpa))
                              ) : (
                                currencyFormater(
                                  parseInt(quote?.multiYearCpa * 1.18)
                                )
                              )}
                            </ItemPrice>
                          </Col>
                        </>
                      )}
                    {temp_data?.tab !== "tab2" && (
                      <>
                        {(totalApplicableAddonsMotor || [])
                          .sort()
                          .reverse()
                          .map((item, index) => (
                            <>
                              {/* {GetAddonValue(item) !== "N/A" && ( */}
                              <>
                                <Col lg={6} md={7} sm={7} xs={7}>
                                  <ItemName>
                                    {" "}
                                    {item === "emergencyMedicalExpenses" &&
                                    (between9to12 || between13to14)
                                      ? "Emergency M.E"
                                      : getAddonName(item)}
                                  </ItemName>
                                </Col>
                                <Col lg={6} md={5} sm={5} xs={5}>
                                  <ItemPrice>
                                    {GetAddonValue(
                                      item,
                                      addonDiscountPercentage
                                    ) === "N/S" ? (
                                      <NoAddonCotainer>
                                        <Badge
                                          variant="secondary"
                                          style={{ cursor: "pointer" }}
                                        >
                                          Not selected
                                        </Badge>
                                      </NoAddonCotainer>
                                    ) : GetAddonValue(
                                        item,
                                        addonDiscountPercentage
                                      ) === "N/A" ? (
                                      <NoAddonCotainer>
                                        <Badge
                                          variant="danger"
                                          style={{ cursor: "pointer" }}
                                        >
                                          Not Available
                                        </Badge>
                                      </NoAddonCotainer>
                                    ) : (
                                      <NoAddonCotainer>
                                        {quote?.company_alias === "godigit" &&
                                        item === "zeroDepreciation" &&
                                        paydLoading &&
                                        quote?.companyAlias === paydLoading &&
                                        (quote?.payAsYouDrive ||
                                          quote?.additionalTowingOptions ||
                                          quote?.claimsCovered)
                                          ? "Fetching..."
                                          : !gstToggle
                                          ? GetAddonValueNoGst(
                                              item,
                                              addonDiscountPercentage
                                            )
                                          : GetAddonValue(
                                              item,
                                              addonDiscountPercentage
                                            )}
                                      </NoAddonCotainer>
                                    )}
                                  </ItemPrice>
                                </Col>
                              </>
                              {/* )} */}
                            </>
                          ))}
                        {dummySpace ? (
                          <>
                            {[...Array(dummySpace)].map(
                              (elementInArray, index) => (
                                <>
                                  {" "}
                                  <Col lg={8} md={8} sm={8} xs={8}>
                                    <ItemName> &nbsp;</ItemName>
                                  </Col>
                                  <Col lg={4} md={4} sm={4} xs={4}>
                                    <ItemPrice> &nbsp;</ItemPrice>
                                  </Col>
                                </>
                              )
                            )}
                          </>
                        ) : (
                          <></>
                        )}
                      </>
                    )}
                  </>
                </>
              )}
              {!isPayD &&
                quote?.policyType !== "Third Party" &&
                type === "car" && (
                  <PayAsYouDrive
                    payD={
                      quote?.payAsYouDrive || quote?.additionalTowingOptions
                    }
                    isTowing={!_.isEmpty(quote?.additionalTowingOptions)}
                    FetchQuotes={FetchQuotes}
                    quote={quote}
                    type={TypeReturn(type)}
                    enquiry_id={enquiry_id}
                    temp_data={temp_data}
                    addOnsAndOthers={addOnsAndOthers}
                    noDisplay
                    lessthan767={lessthan767}
                    multiUpdateQuotes={multiUpdateQuotes?.godigit || []}
                  />
                )}
            </Row>
          </CardOtherItemNoBorder>
          {!popupCard && (
            <Row
              mb-10
              style={{
                marginBottom: "10px",
              }}
            >
              <Col
                lg={6}
                md={6}
                onClick={() => {
                  //setKnowMore(true);
                  quote?.companyAlias === "hdfc_ergo" && temp_data?.carOwnership
                    ? swal({
                        title: "Please Note",
                        text: 'Transfer of ownership is not allowed for this quote. Please select ownership change as "NO" to buy this quote',
                        icon: "info",
                      })
                    : quote?.garageCount &&
                      handleKnowMoreClick("cashlessGaragesPop");
                }}
                style={{
                  cursor: !quote?.garageCount && "not-allowed",
                  pointerEvents:
                    import.meta.env?.VITE_BROKER === "ABIBL" &&
                    import.meta.env?.VITE_API_BASE_URL !==
                      "https://api-carbike.fynity.in/api"
                      ? "none"
                      : "",
                }}
              >
                <CardOtherItemBtn
                  title={
                    quote?.garageCount * 1 < 0
                      ? false
                      : quote?.garageCount * 1 > 0
                      ? `${quote?.garageCount} garages are available for this quote.`
                      : false
                  }
                  style={{
                    cursor: !quote?.garageCount && "not-allowed",
                    color:
                      (!quote?.garageCount || loadingNTooltip) && "#6b6e7166",
                  }}
                >
                  Cashless Garages{" "}
                </CardOtherItemBtn>
              </Col>
              <Col
                data-tip={"<div >Please wait till all the quotes load </div>"}
                data-html={true}
                data-for={`premiumBk-tooltip_${quote?.policyId}`}
                lg={6}
                md={6}
                onClick={() => {
                  quote?.companyAlias === "hdfc_ergo" && temp_data?.carOwnership
                    ? swal({
                        title: "Please Note",
                        text: 'Transfer of ownership is not allowed for this quote. Please select ownership change as "NO" to buy this quote',
                        icon: "info",
                      })
                    : quote?.noCalculation === "Y" || quote?.dummyTile
                    ? swal(
                        "Please Note",
                        "Premium Breakup is not available for this quote",
                        "info"
                      )
                    : handleKnowMoreClick("premiumBreakupPop");
                }}
              >
                <CardOtherItemBtn
                  loadingNTooltip={loadingNTooltip}
                  id={quote?.companyAlias}
                >
                  Premium Breakup
                </CardOtherItemBtn>
              </Col>
            </Row>
          )}
          {popupCard && (
            <>
              {multiPopupCard && (
                <ProductName>{quote?.productName}</ProductName>
              )}

              <div className="saved_money">
                <div
                  className="row text-center"
                  style={{
                    fontSize: "0.85rem",
                    fontFamily: ({ theme }) =>
                      theme?.QuoteBorderAndFont?.fontFamily
                        ? theme?.QuoteBorderAndFont?.fontFamily
                        : "basier_squareregular",
                  }}
                >
                  <div className="col-6">
                    <p className="p-0 m-0 text-muted">Old Premium</p>
                  </div>
                  <div className="col-6">
                    <p className="p-0 m-0">
                      {currencyFormater(parseInt(tempData?.oldPremium))}
                    </p>
                  </div>
                  <div className="col-6">
                    <p className="p-0 m-0">New Premium</p>
                  </div>
                  <div className="col-6">
                    <p className="p-0 m-0 text-success">
                      {currencyFormater(parseInt(finalPremium))}
                    </p>
                  </div>
                </div>

                <div className="saved_money_div">
                  <img
                    src={`${
                      import.meta.env.VITE_BASENAME !== "NA"
                        ? `/${import.meta.env.VITE_BASENAME}`
                        : ""
                    }/assets/images/money.svg`}
                    className="saved_money_image"
                    alt="saved_money_image"
                  />
                  <span className="saved_money_text mx-auto">
                    {difference > 0 ? (
                      <>You have saved Rs {parseInt(difference)}</>
                    ) : (
                      <>
                        Premium changed by Rs{" "}
                        {Number(parseInt(difference)) * -1}
                      </>
                    )}
                  </span>
                </div>
              </div>
            </>
          )}
        </QuoteCardMain>
      </Col>
    </>
  );
};

export const QuoteSkelton = ({
  popupCard,
  type,
  maxAddonsMotor,
  quotesLoaded,
  loading,
  multiPopupCard,
}) => {
  let lessthan767 = useMediaPredicate("(max-width: 767px)");

  return lessthan767 && !popupCard ? (
    <>
      <MobileQuoteCard style={{ padding: "15px 20px" }}>
        <Row>
          <Col lg={4} md={4} sm={4} xs="4">
            <Skeleton width={80} height={20}></Skeleton>
          </Col>
          <Col lg={4} md={4} sm={4} xs="4">
            <Skeleton width={80} height={20}></Skeleton>
          </Col>
          <Col lg={4} md={4} sm={4} xs="4">
            <Skeleton width={80} height={20}></Skeleton>
          </Col>
        </Row>
        <Row>
          <Col lg={4} md={4} sm={4} xs="4">
            <Skeleton width={80} height={20}></Skeleton>
          </Col>
          <Col lg={4} md={4} sm={4} xs="4"></Col>
          <Col lg={4} md={4} sm={4} xs="4">
            <Skeleton width={80} height={20}></Skeleton>
          </Col>
        </Row>
      </MobileQuoteCard>
    </>
  ) : (
    <QuotesCardSkeleton
      popupCard={popupCard}
      multiPopupCard={multiPopupCard}
      lessthan767={lessthan767}
      quotesLoaded={quotesLoaded}
      loading={loading}
      type={type}
      maxAddonsMotor={maxAddonsMotor}
    />
  );
};

const FoldedRibbon = styled.div`
  --f: 5px; /* control the folded part*/
  --r: 5px; /* control the ribbon shape */
  --t: 5px; /* the top offset */

  position: absolute;
  overflow: visible;
  font-size: 11.5px;
  font-weight: 600;
  color: #fff;
  inset: var(--t) calc(-1 * var(--f)) auto auto;
  padding: 0 10px var(--f) calc(10px + var(--r));
  clip-path: polygon(
    0 0,
    100% 0,
    100% calc(100% - var(--f)),
    calc(100% - var(--f)) 100%,
    calc(100% - var(--f)) calc(100% - var(--f)),
    0 calc(100% - var(--f)),
    var(--r) calc(50% - var(--f) / 2)
  );
  background: ${({ theme }) => theme.Tab?.color || "#4ca729"};
  box-shadow: 0 calc(-1 * var(--f)) 0 inset #0005;
  /* z-index: 999 !important; */
`;

export const QuoteCardMain = styled.div`
  display: inline-block;
  position: relative;
  // width: 303px;
  margin-right: 16px;
  padding: 10px 0 0;
  border-radius: 8px;
  width: 100%;
  // overflow: hidden;
  min-height: "min-content";
  box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1),
    0 10px 10px -5px rgba(0, 0, 0, 0.04);
  border: ${({ isRenewal, theme }) =>
    isRenewal
      ? theme?.Registration?.proceedBtn?.background
        ? `1px solid ${theme?.Registration?.proceedBtn?.background}`
        : `1px solid #4ca729`
      : `1px solid #d0d0d0d0`};
  background-color: #ffffff;
  text-align: center;
  @media screen and (max-width: 1290px) {
    // width: 95%;
  }
  &:hover {
    transform: scale(1.05);
    transition: transform 0.7s;
    box-shadow: ${({ theme }) =>
      theme?.QuoteCard?.boxShadow ||
      "0 8px 25px 1px #b3ffb3, 0 10px 10px 1px #b3ffb3"};
  }
  .saved_money {
    width: 100%;
    display: flex;
    justify-content: center;
    flex-direction: column;
    padding: 25px;
    @media (max-width: 991px) {
      padding-left: 14px;
      padding-right: 14px;
    }
  }
  .saved_money_div {
    border-radius: 20px 5px 5px 20px;
    display: flex;
    align-items: center;
    margin-top: 12px;
    background: ${({ theme }) =>
      theme.QuoteBorderAndFont?.moneyBackground || "rgba(96, 241, 96, 0.25)"};
    width: 95%;
    margin-left: 18px;
  }
  .saved_money_image {
    width: 35px;
    height: 35px;
    margin-left: -18px;
  }
  .saved_money_text {
    font-size: 0.8rem;
    font-family: ${({ theme }) =>
      theme?.regularFont?.fontFamily
        ? theme?.regularFont?.fontFamily
        : `"ui-serif"`};
    padding-right: 20px;
  }
`;
export const CardOtherItemInner = styled.div`
  ${(props) =>
    props?.autoHeight
      ? `border-bottom: solid 1px transparent;`
      : `border-bottom: solid 1px #e3e4e8;`}
  padding: 15px 20px 0;
  ${(props) => (props?.autoHeight ? `` : ``)}
  height: ${(props) => (props?.autoHeight ? "" : `146px`)};
  @media only screen and (max-width: 1200px) and (min-width: 950px) {
    height: 230px;
  }
  .coverIdv {
    padding: 0px 2.5px;
    font-size: 12.5px;
    text-align: left;
    margin-top: 3px;
    color: #4d5154;
  }
  .idvPrice {
    padding: 0px 2.5px;
    text-align: left;
    font-size: 14px !important;
    white-space: pre;
    @media only screen and (max-width: 1350px) and (min-width: 993px) {
      font-size: 12.5px !important;
    }
  }
  .idvTooltip {
    position: absolute;
    right: 12px;
    top: 18px;
    @media (max-width: 1350px) {
      right: -4px;
    }
  }
`;

const LogoImg = styled.img`
  max-height: 56px;
  margin-bottom: 15px;
  //margin-left: -10px;
  margin-top: -5px;
  height: 56px;
  min-height: 45px;
  justify-content: flex-start;
  display: flex;
  width: 100%;
  padding: 1px;
  border: 0.5px solid #c0c0c0;
  border-radius: 8px;
  object-fit: contain;

  @media only screen and (max-width: 993px) {
    max-width: 120px;
  }
  @media only screen and (max-width: 768px) {
    // border: none;
  }
`;

const CardBuyButton = styled.button`
  float: left;
  // width: 220px;
  display: flex;
  height: 47px;
  margin-top: 6px;

  background-color: ${({ theme, themeDisable }) =>
    `${
      themeDisable
        ? "#787878"
        : theme.QuoteCard?.color3
        ? theme.QuoteCard?.color3
        : "#bdd400 !important"
    } `};

  border: ${({ theme, themeDisable }) =>
    themeDisable
      ? "1px solid #787878"
      : theme.QuoteCard?.border || "1px solid #bdd400"};
  color: #fff !important;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-SemiBold"};
  font-size: 12px;
  line-height: 40px;
  border-radius: 50px;
  margin-left: 0;
  outline: none;
  display: grid;
  width: 90%;
  float: none;
  font-weight: 1000;
  padding: 0px 0px;
  color: ${({ theme }) => theme.QuoteCard?.color || "#6c757d"};
  margin-bottom: 16px !important;
  margin: 0 auto;
  justify-contnet: space-between;
  transition: 0.2s ease-in-out;
  position: relative;
  bottom: 3px;
  @media only screen and (max-width: 1350px) {
    font-size: 10px !important;
  }
  .withGstText {
    color: black;
    position: absolute;
    top: -30px;
    right: 0;
    left: 0;
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 1px;
    @media (max-width: 767px) {
      left: 0;
      font-size: 10px;
      letter-spacing: 1px;
      top: -27px;
    }
    @media (max-width: 600px) {
      top: 40px;
      left: 5px;
      letter-spacing: 0px;
      font-size: 8.5px;
    }
  }
  & span {
    font-size: 15px;
    display: contents;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"Inter-Light"`};
    @media only screen and (max-width: 1200px) {
      font-size: 13px !important;
    }
  }
  &:hover {
    ${(props) =>
      props?.themeDisable ? "" : `background-color: #fff !important`};
    color: ${({ theme, themeDisable }) =>
      `${
        themeDisable
          ? "#787878"
          : theme.QuoteCard?.color3
          ? theme.QuoteCard?.color3
          : "#bdd400"
      } !important`};
    .buyPrice {
      color: ${({ theme, themeDisable }) =>
        `${
          themeDisable
            ? "white"
            : theme.QuoteCard?.color3
            ? theme.QuoteCard?.color3
            : "#bdd400 !important"
        } `};
    }
    .buyText {
      color: ${({ theme, themeDisable }) =>
        `${
          themeDisable
            ? "white"
            : theme.QuoteCard?.color3
            ? theme.QuoteCard?.color3
            : "#bdd400 !important"
        } !important`};
    }
    ${(props) =>
      props?.themeDisable
        ? ""
        : `border: ${({ theme }) =>
            theme.QuoteCard?.border || "1px solid #bdd400 !important"};`};
    &:before {
      transform: translateX(300px) skewX(-15deg);
      opacity: 0.6;
      transition: 0.7s;
    }
    &:after {
      transform: translateX(300px) skewX(-15deg);
      opacity: 1;
      transition: 0.7s;
    }
  }
  .buyPrice {
    position: relative;
    bottom: 32px;
    text-align: left;
    white-space: pre;
    display: flex;
    justify-content: center;
    align-self: center;
    color: "white";
  }
  .buyText {
    position: relative;
    bottom: 6px;
    color: white;
    text-align: left;
    white-space: pre;
    display: flex;
    justify-content: center;
    align-self: center;
    font-size: 10px;
  }
  @media only screen and (max-width: 767px) {
    font-size: 13px !important;
    margin-top: 5px;
    padding: 3px;
  }
`;

export const CardOtherItemNoBorder = styled.div`
  padding: 4px 12px 0px 15px;
  border-bottom: none;
  margin-top: 10px;
  ${({ dummyTile }) => (dummyTile ? "visibility: hidden;" : "")}
`;

const ItemName = styled.p`
  font-size: ${["BAJAJ", "ACE", "SRIDHAR"].includes(import.meta.env.VITE_BROKER)
    ? "11px"
    : "12px"};
  text-align: left;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  white-space: nowrap;
  color: #6c757d !important;
  font-weight: ${({ theme }) => theme.regularFont?.fontWeight || "600"};

  @media only screen and (max-width: 1150px) and (min-width: 993px) {
    font-size: 8px !important;
  }
  @media only screen and (max-width: 1350px) and (min-width: 1151px) {
    font-size: 10px !important;
  }
`;

const ItemPrice = styled.p`
  text-align: end;
  font-weight: 600;
  font-size: ${["BAJAJ", "ACE", "SRIDHAR"].includes(import.meta.env.VITE_BROKER)
    ? "11px"
    : "12px"};
  margin-right: 5px;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  height: 18px !important;
  @media only screen and (max-width: 1150px) and (min-width: 993px) {
    font-size: 8px !important;
  }
  @media only screen and (max-width: 1350px) and (min-width: 1151px) {
    font-size: 10px !important;
  }
`;

export const CardOtherItemBtn = styled.span`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  color: ${({ theme, loadingNTooltip }) =>
    loadingNTooltip ? "#6b6e7166" : theme.QuoteCard?.color || "#bdd400"};
  cursor: pointer;
  height: 40px;
  font-size: 11px;
  line-height: 20px;
  padding: 10px 0px 10px 0;
  /* border-top: solid 1px #e3e4e8; */
  /* text-align: left; */
  font-weight: 600;
  @media only screen and (max-width: 1300px) and (min-width: 950px) {
    font-size: 9px;
  }
  &:hover {
    color: ${({ theme, loadingNTooltip }) =>
      loadingNTooltip
        ? "#6b6e7166"
        : theme.floatButton?.floatColor || "#bdd400"};
  }
`;

const StyledDiv = styled.div`
  position: absolute;
  //top: -24px;
  top: 36px;
  background: #ffffff;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"basier_squaremedium"`};
  font-size: 10px;
  line-height: 12px;
  color: ${({ tab }) => (tab === "tab2" ? "#6b6e7166" : "#6b6e71")};
  text-align: center;
  width: 120px;
  //border: 1px solid #bdd400;
  border-bottom: none;
  //	z-index: 100;
  padding: 6px 4px 6px 0px;
  right: 0px;
  //	border-radius: 60px 0px 0px 0px;
  // 	0 10px 10px -5px rgba(0, 0, 0, 0.04);
  clip-path: inset(-11px -11px 0px -21px);

  @media only screen and (max-width: 992px) {
    width: 160px;
  }
  @media only screen and (max-width: 767px) {
    width: 115px;
    top: -6px;
    right: 0px;
    width: unset;
  }
`;

const StyledDiv1 = styled.div`
  position: relative;
  left: -102px;
  bottom: -15px;
  /* z-index: 101; */
  .round-label:before {
    cursor: ${({ tab }) => (tab === "tab2" ? "not-allowed" : "pointer")};
    @media (max-width: 767px) {
      border-radius: 50%;
      left: 3px;
      background: ${({ mobileComp }) => mobileComp && "#ffffff"};
      border: ${({ theme }) =>
        theme?.QuoteCard?.borderCheckBox || "1px solid  rgb(129 129 129)"};
    }
  }

  .group-check input[type="checkbox"]:checked + label:before {
    transform: scale(1);
    background-color: ${({ theme }) => theme.CheckBox?.color || "#bdd400"};
    border: ${({ theme }) => theme.CheckBox?.border || "1px solid #bdd400"};
    box-shadow: ${({ theme }) =>
      theme.QuoteBorderAndFont?.shadowCheck || "none"};
    filter: ${({ theme }) =>
      theme.QuoteBorderAndFont?.filterPropertyCheckBox || "none"};
    @media (max-width: 767px) {
      border-radius: 50%;
      left: 3px;
      border-color: ${({ mobileComp }) => mobileComp && "#ffffff"};
    }
  }
  @media (max-width: 993px) {
    left: -121px;
    bottom: -18px;
  }

  @media (max-width: 767px) {
    left: -62px;
    bottom: 21px;
  }
`;

const NoAddonCotainer = styled.div`
  position: relative;
  //	bottom: 5px;
`;
const RibbonBadge = styled.div`
  display: flex;
  align-items: center;
  .badge-secondary {
    background: ${({ theme }) => theme.Tab?.color || "#4ca729"} !important;
    font-size: 7px !important;
    padding: 2px 6px !important;
    line-height: 1.5 !important;
  }
`;

const HowerTabs = styled.div`
  font-family: ${({ theme }) => theme?.fontFamily && theme?.fontFamily};
  z-index: 997;
  display: flex;
  position: relative;
  bottom: 8px;
  justify-content: center;
  align-items: center;
  .badge-secondary {
    background: white !important;
    // cursor: pointer !important;
    color: ${({ theme }) =>
      theme.QuoteBorderAndFont?.linkColor || "#bdd400"} !important;
  }
  flex-direction: column;
  .hideBenefits {
    display: none !important;
  }
  .showBenefits {
    display: block !important;
  }
  .arrowIcon {
    color: ${({ theme }) =>
      theme.QuoteBorderAndFont?.linkColor || "#bdd400"} !important;
    font-size: 12px;
    transition: all 0.3s ease-in-out;
  }
`;
const moveDown = keyframes`
  0% {
	 transform: translateY(-10px);
    opacity: 0;
  }

  100% {
	 transform: translateY(6px);
	 opacity: 1;
  }
`;

const ContentTabBenefits = styled.div`
  display: flex;
  // min-height: 400px;
  background: white;
  z-index: 1000;
  width: 113%;
  font-size: 12px;
  text-align: left;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  transform: translateY(6px);
  animation: ${moveDown} 0.9s;
  padding: 0px 20px 0px 0px;
  @media only screen and (max-width: 993px) {
    width: 105%;
  }
`;

const ProductName = styled.div`
  display: flex;
  justify-content: center;
  align-items: cenetr;
  font-size: 16px;
  font-weight: bold;
  min-height: 48px !important;
`;
const MobileQuoteCard = styled.div`
  width: 100%;
  margin-top: 10px;
  box-shadow: rgb(149 157 165 / 70%) 0px 8px 10px;
  padding: 10px 0px;
  border: ${({ getSelected, isRenewal, theme }) =>
    isRenewal || getSelected
      ? theme?.Registration?.proceedBtn?.background
        ? `1px solid ${theme?.Registration?.proceedBtn?.background}`
        : `1px solid #bdd400 `
      : `1px solid #d0d0d0d0`};
`;
const MobileQuoteCardTop = styled.div`
  padding: 0px 10px;
`;

const MobileIdvContainer = styled.div`
  ${({ dummyTile }) => (dummyTile ? "visibility: hidden;" : "")}
  .idvTextMob {
    font-size: 10.5px;
  }
  .idvValMob {
    font-size: 11px;
    font-weight: 600;
    @media only screen and (max-width: 400px) {
      font-size: 9px;
    }
    @media only screen and (max-width: 330px) {
      font-size: 8px;
    }
  }
  .coverages {
    font-size: 10px;
    font-weight: 600;
    color: ${({ theme }) => theme.QuotePopups?.color2 || "#060"};
  }
  .idvMobContainer {
    white-space: nowrap;
  }
  .idvTooltip {
    position: absolute;
    top: 3px;
    right: 10px;
    font-size: 20px;
  }
`;

const PolicyDetails = styled.div`
  color: ${({ theme }) => theme.QuoteCard?.color || "#bdd400"};
  padding: 5px 0px 10px 0px;
  font-size: ${({ isMobileIOS }) => (isMobileIOS ? "11px" : "12px")};
  font-weight: 600;
  white-space: nowrap;
  &:hover {
    color: ${({ theme }) => theme.floatButton?.floatColor || "#bdd400"};
  }
`;

const CashlessGarageMob = styled.button`
  border-radius: 30px;
  background-color: #f1f4f7;
  font-size: 9px;
  padding: 5px;
  border: 1px solid #f1f4f7;
  position: relative;
  bottom: 6px;
`;
const CheckBoxContainer = styled.div`
  width: 78px;
  text-align: center;
  border-radius: 30px;
  font-size: 9px;
  padding: 5px 5px 0px 5px;
  border: 1px solid #f1f4f7;
  position: relative;
  background: #f1f4f7;
  bottom: 6px;
  color: ${({ getSelected, theme }) =>
    theme.QuoteCard?.color ? `${theme.QuoteCard?.color}` : "#bdd400 "};
  border: ${({ theme }) =>
    theme?.Registration?.proceedBtn?.background
      ? `1px solid ${theme?.Registration?.proceedBtn?.background}`
      : `1px solid #bdd400 `};
  border: ${({ btnDisable, getSelected }) =>
    !getSelected && btnDisable && "none"};
  border: ${({ btnDisable, getSelected }) =>
    !getSelected && btnDisable && "none"};

  .round-label:before {
    @media (max-width: 767px) {
      visibility: hidden;
    }
  }
`;

const CashlessGarageMobContainer = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
`;

const UspContainer = styled.div`
  display: flex;
  align-items: center;
  font-size: 10px;
  margin-left: 15px;
  font-weight: 600;
  color: ${({ theme }) =>
    theme.QuoteBorderAndFont?.linkColor || "#bdd400"} !important;
  text-decoration: underline;
`;
const AddonAndCpaMobile = styled.div`
  width: 100%;
  border-top: 1px solid #d0d0d0;
  padding: 2px 20px;
  font-size: 9px;
  ${({ dummyTile }) => (dummyTile ? "display: none;" : "")}
`;
const AddonContainerMobile = styled.div`
  margin: 1px 0px;
  display: flex;
  .addonNameMobile {
    width: 70%;
    font-weight: 600;
  }
  .addonValueMobile {
    width: 30%;
    font-size: 9.5px !important;
    font-weight: 600;
    text-align: end;
    white-space: nowrap;
  }
`;
const CompareCheckMobile = styled.div`
  position: absolute;
  right: -40px;
  z-index: 400;
`;

const HowerTabsMobile = styled.div`
  z-index: 997;
  display: flex;
  position: relative;
  bottom: 8px;
  justify-content: center;
  align-items: center;
  flex-direction: column;
  .hideBenefits {
    display: none !important;
  }
  .showBenefits {
    display: block !important;
  }
`;

const ContentTabBenefitsMobile = styled.div`
  display: flex;
  min-height: 66px;
  background: white;
  z-index: 1000;
  width: 100%;
  font-size: 9px;
  text-align: left;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  transform: translateY(6px);
  animation: ${moveDown} 0.9s;
  padding: 0px 20px 0px 0px;
`;
