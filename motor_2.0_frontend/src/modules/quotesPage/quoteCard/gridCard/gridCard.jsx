/* eslint-disable react-hooks/rules-of-hooks */
import React, { useEffect, useState } from "react";
import styled, { createGlobalStyle, keyframes } from "styled-components";
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
import { getAddonName } from "../../quoteUtil";
import swal from "sweetalert";
import { CustomTooltip } from "components";
import { TypeReturn } from "modules/type";
import DeleteOutlineOutlinedIcon from "@mui/icons-material/DeleteOutlineOutlined";
import { GiMechanicGarage, GiMoneyStack } from "react-icons/gi";
import { BlockedSections } from "../../addOnCard/cardConfig";
import { ImCross, ImCheckmark } from "react-icons/im";
import {
  _buyNow,
  _polictTypeReselect,
  _addonValue,
  _addonCalc,
} from "../card-logic";
import {
  _premiumTracking,
  _saveQuoteTracking,
} from "analytics/quote-page/quote-tracking";
import { _discount } from "../../quote-logic";
import PayAsYouDrive from "../payd";
import { calculations } from "modules/quotesPage/calculations/ic-config/calculations-fallback";

export const GridCard = ({
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
    quotesLoaded,
  } = useSelector((state) => state.quotes);

  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");

  //Fetching adddons
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
  const shared = query.get("shared");
  const _stToken = fetchToken();
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
  //-----------Product selection through url when redirected from pdf----------------

  const address =
    !_.isEmpty(masterLogos) &&
    masterLogos.filter((ic) => ic.companyAlias === "royal_sundaram");

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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedProduct]);

  //-----------------sortByDefault----------------------
  useEffect(() => {
    setSort(tempData?.sortBy);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tempData?.sortBy]);

  //geetingAddonValue

  const GetAddonValue = (addonName, addonDiscountPercentage) => {
    let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
    let additional = Object.keys(quote?.addOnsData?.additional);
    let selectedAddons = addOnsAndOthers?.selectedAddons;
    if (inbuilt?.includes(addonName)) {
      return (
        <span style={{ ...(lessthan767 && { fontSize: "9px" }) }}>
          {Number(quote?.addOnsData?.inBuilt[addonName]) !== 0 ? (
            <span className="value">
              {`₹ ${currencyFormater(
                _addonValue(
                  quote,
                  addonName,
                  addonDiscountPercentage,
                  "inbuilt"
                )
              )}`}
            </span>
          ) : (
            <>
              {addonName === "roadSideAssistance" &&
              quote?.company_alias === "reliance" ? (
                <>-</>
              ) : (
                <>
                  {lessthan767 ? (
                    <>
                      {" "}
                      <i className="fa fa-check" style={{ color: "green" }}></i>
                    </>
                  ) : (
                    <>
                      <Badge variant="primary" style={{ position: "relative" }}>
                        <ImCheckmark />
                      </Badge>
                    </>
                  )}
                </>
              )}
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
      return (
        <span className="value">{`₹ ${currencyFormater(
          _addonValue(quote, addonName, addonDiscountPercentage, false)
        )}`}</span>
      );
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
            <span className="value">{`₹ ${currencyFormater(
              _addonValue(
                quote,
                addonName,
                addonDiscountPercentage,
                "inbuilt",
                "exclude-gst"
              )
            )}`}</span>
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
                  <Badge variant="primary" style={{ position: "relative" }}>
                    <ImCheckmark />
                  </Badge>
                </>
              )}
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
      return (
        <span className="value">
          ₹{" "}
          {currencyFormater(
            _addonValue(
              quote,
              addonName,
              addonDiscountPercentage,
              false,
              "exclude-gst"
            )
          )}
        </span>
      );
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [addOnsAndOthers?.selectedAddons, quote, temp_data?.tab]);

  //-----------------setting changed premium for premiuym recalculation-----------------

  useEffect(() => {
    if (tempData?.oldPremium && finalPremium) {
      setDifference(tempData?.oldPremium - finalPremium);
    } else {
      setDifference(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
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
        },
      ];
      dispatch(setFinalPremiumList(data));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
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
          companyAlias: quote?.companyAlias,
        },
      ];
      dispatch(setFinalPremiumList1(data));
    } else {
      dispatch(clearFinalPremiumList());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [finalPremium, gst, sendQuotes, tempData?.sendQuote]);

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
      temp_data?.prevIc !== "Not selected"
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
      ((quote?.policyType === "Third Party" &&
        import.meta.env?.VITE_BROKER === "GRAM") ||
        tempData?.policyType === "Third-party") &&
      // ||(temp_data?.tab === "tab1" && daysToExpiry > 90))
      !prevIcData &&
      !temp_data?.fastlaneNcbPopup &&
      !temp_data?.newCar &&
      daysToExpiry <= 90
    ) {
      setQuoteData({
        enquiryId: temp_data?.enquiry_id || enquiry_id,
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
      });
      setPrevPopupTp(true);
    } else if (
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
    } else if (
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
        totalPremiumA * 1 -
          totalPremiumC * 1 +
          quote?.finalTpPremium * 1 +
          uwLoading * 1 +
          (totalPremiumA * 1 -
            totalPremiumC * 1 +
            quote?.finalTpPremium * 1 +
            uwLoading * 1) *
            0.18
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
          (quote?.tppdPremiumAmount * 1 - quote?.tppdDiscount * 1) * 0.12
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

  const compareSelection =
    (!_.isEmpty(CompareData) &&
      !_.isEmpty(CompareData?.filter((x) => x.policyId === quote?.policyId))) ||
    "" ||
    "";
  const [totalApplicableAddonsMotor, setTotalApplicableAddonsMotor] = useState(
    []
  );

  const llpaidCon =
    quote?.llPaidDriverPremium * 1 ||
    quote?.llPaidConductorPremium * 1 ||
    quote?.llPaidCleanerPremium * 1;

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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [quote, addOnsAndOthers?.selectedAddons]);

  const addonTab =
    totalApplicableAddonsMotor &&
    !_.isEmpty(totalApplicableAddonsMotor) &&
    ((addOnsAndOthers?.selectedAccesories &&
      !_.isEmpty(addOnsAndOthers?.selectedAccesories)) ||
      (addOnsAndOthers?.selectedAdditions &&
        !_.isEmpty(addOnsAndOthers?.selectedAdditions)) ||
      (addOnsAndOthers?.selectedDiscount &&
        !_.isEmpty(addOnsAndOthers?.selectedDiscount)));

  const isAddonsAvailable =
    totalApplicableAddonsMotor &&
    !_.isEmpty(totalApplicableAddonsMotor.filter((item) => item !== "imt23"));

  const isAccessoriesAvailable =
    addOnsAndOthers?.selectedAccesories &&
    !_.isEmpty(addOnsAndOthers?.selectedAccesories);

  const isCoverAvailable =
    !_.isEmpty(addOnsAndOthers?.selectedAdditions) ||
    !_.isEmpty(totalApplicableAddonsMotor.filter((item) => item === "imt23"));

  const isDiscountAvailable =
    addOnsAndOthers?.selectedDiscount &&
    !_.isEmpty(addOnsAndOthers?.selectedDiscount);

  const isOthersAvailable =
    isCoverAvailable || isAccessoriesAvailable || isDiscountAvailable;

  const borderCondition =
    (totalApplicableAddonsMotor && !_.isEmpty(totalApplicableAddonsMotor)) ||
    (addOnsAndOthers?.selectedAccesories &&
      !_.isEmpty(addOnsAndOthers?.selectedAccesories)) ||
    (addOnsAndOthers?.selectedAdditions &&
      !_.isEmpty(addOnsAndOthers?.selectedAdditions)) ||
    (addOnsAndOthers?.selectedDiscount &&
      !_.isEmpty(addOnsAndOthers?.selectedDiscount));

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
    };
    setKnowMoreObject(data1);
  };

  //-----------------handle know more dynamically when grouping and quotes changed from premium breakup on ddon selection-----------------

  useEffect(() => {
    if (knowMore && knowMoreCompAlias === quote?.modifiedAlias) {
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
      import.meta.env.VITE_BROKER !== "RB" &&
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
    return import.meta.env.VITE_BROKER !== "RB" &&
      TypeReturn(type) === "car" &&
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
            lessthan767 ? "two" : "three"
          } quotes at once.`,
          "info"
        );
      }
    }
  };

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
                      `₹ ${currencyFormater(quote?.idv)}`
                    )}
                  </span>
                </div>
                <PolicyDetails
                  isMobileIOS={isMobileIOS}
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
                }}
              >
                {paydLoading &&
                quote?.companyAlias === paydLoading &&
                (quote?.payAsYouDrive || quote?.additionalTowingOptions) ? (
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
            <Col lg={4} md={4} sm={4} xs="4" className={"p-0"}>
              {/* <RibbonBadge>
                {!popupCard && quote?.isInspectionApplicable === "Y" ? (
                  <Badge variant="secondary">Inspection Required</Badge>
                ) : (
                  <noscript />
                )}
                {!popupCard &&
                quote?.isRenewal === "Y" &&
                temp_data?.expiry &&
                quote?.gdd !== "Y" ? (
                  <Badge variant="secondary">Renewal Quote</Badge>
                ) : (
                  <noscript />
                )}
                {quote?.gdd === "Y" && (
                  <Badge variant="secondary">Pay As You Drive</Badge>
                )}
              </RibbonBadge> */}
            </Col>
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
                  <CheckBoxContainer getSelected={compareSelection}>
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
                        (length >= (lessthan767 ? 2 : 3) &&
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
          {isPayD && quote?.policyType !== "Third Party" && type === "car" && (
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
                              <>
                                {/*<i
                                className="fa fa-close"
                                style={{ color: "red" }}
                            ></i>*/}
                                N/A
                              </>
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
                                    {!gstToggle
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
        lg={!popupCard ? 12 : multiPopupCard ? 12 : 12}
        md={6}
        sm={12}
        style={{
          marginTop: !popupCard ? "30px" : "20px",
          maxWidth: popupCard ? (lessthan767 ? "100%" : "45%") : "",
        }}
      >
        <QuoteCardMain
          onMouseEnter={() => setMouseHover(true)}
          onMouseLeave={() => setMouseHover(false)}
          isRenewal={quote?.isRenewal === "Y" && !popupCard}
          hover={!popupCard}
        >
          <Ribbons>
            {!popupCard && quote?.isInspectionApplicable === "Y" ? (
              <Ribbon>
                <div></div> Inspection Required
              </Ribbon>
            ) : (
              <noscript />
            )}
            {(!popupCard &&
              quote?.isRenewal === "Y" &&
              temp_data?.expiry &&
              quote?.gdd !== "Y") ||
            "" ? (
              <Ribbon>
                <div></div>Renewal Quote
              </Ribbon>
            ) : (
              <noscript />
            )}
            {quote?.ribbon ? (
              <Ribbon>
                <div></div>
                {quote?.ribbon}
              </Ribbon>
            ) : (
              <noscript />
            )}
            {quote?.gdd === "Y" && (
              <>
                <Ribbon
                  id={`gdd`}
                  data-tip={
                    "<h3 > DIGIT's Pay As You Drive Plan</h3> <div>Insurer offers an extra discount on your Own Damage (OD) premium if you drive less than 15,000 kms per year, in exchange for you uploading 7 Photos of your car before your current policy expires.</div>"
                  }
                  data-html={true}
                  data-for={`gddToolTip${index}`}
                  // htmlFor="gddToolTip"
                >
                  <div></div>
                  Pay As You Drive
                </Ribbon>
                <CustomTooltip
                  rider="true"
                  id={`gddToolTip${index}`}
                  place={"left"}
                  arrowPosition="top"
                  backColor="#fff"
                  arrowColor
                  Position={{ top: 40, left: 50 }}
                />
              </>
            )}
          </Ribbons>
          <CardOtherItemInner>
            {!popupCard ? (
              <>
                <Row style={{ alignItems: "center" }}>
                  <Col lg={7} md={7} sm={7} xs={7}>
                    <Row style={{ alignItems: "center" }}>
                      <Col lg={5} md={5} sm={5} xs={5}>
                        <LogoImg
                          src={
                            quote?.companyLogo ? quote?.companyLogo : demoLogo
                          }
                          alt="Plan Logo"
                        />
                      </Col>
                      <Col
                        lg={7}
                        md={7}
                        sm={7}
                        xs={7}
                        style={{
                          ...(quote?.dummyTile && { visibility: "hidden" }),
                        }}
                      >
                        <Row>
                          <Col lg={7} md={7} sm={7} xs={7}>
                            <ItemName base>Base Premium</ItemName>
                          </Col>
                          <Col lg={5} md={5} sm={5} xs={5}>
                            <ItemPrice base>
                              {" "}
                              <span className="value">{`₹
                           ${
                             !gstToggle
                               ? currencyFormater(basePremNoGst)
                               : currencyFormater(basePrem)
                           }`}</span>
                            </ItemPrice>
                          </Col>
                          {addOnsAndOthers?.selectedCpa?.includes(
                            "Compulsory Personal Accident"
                          ) &&
                            _.isEmpty(addOnsAndOthers?.isTenure) && (
                              <>
                                <Col lg={8} md={8} sm={8} xs={8}>
                                  <ItemName cpa>
                                    Compulsory Personal Accident
                                  </ItemName>
                                </Col>
                                <Col lg={4} md={4} sm={4} xs={4}>
                                  <ItemPrice cpa>
                                    {" "}
                                    {gstToggle == 0 ? (
                                      !quote?.compulsoryPaOwnDriver ||
                                      quote?.compulsoryPaOwnDriver == 0 ? (
                                        <Badge
                                          variant="danger"
                                          style={{ cursor: "pointer" }}
                                        >
                                          <ImCross />
                                        </Badge>
                                      ) : (
                                        <span className="value">
                                          {`₹ 
                                ${currencyFormater(
                                  parseInt(quote?.compulsoryPaOwnDriver)
                                )}`}
                                        </span>
                                      )
                                    ) : !quote?.compulsoryPaOwnDriver ||
                                      quote?.compulsoryPaOwnDriver == 0 ? (
                                      <Badge
                                        variant="danger"
                                        style={{ cursor: "pointer" }}
                                      >
                                        <ImCross />
                                      </Badge>
                                    ) : (
                                      <span className="value">{`₹ 
                              ${currencyFormater(
                                parseInt(quote?.compulsoryPaOwnDriver * 1.18)
                              )}`}</span>
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
                                <Col lg={8} md={8} sm={8} xs={8}>
                                  <ItemName cpa>
                                    CPA{" "}
                                    {TypeReturn(type) === "car"
                                      ? "3"
                                      : TypeReturn(type) === "bike" && "5"}{" "}
                                    years
                                  </ItemName>
                                </Col>
                                <Col lg={4} md={4} sm={4} xs={4}>
                                  <ItemPrice cpa>
                                    {quote?.multiYearCpa && "₹ "}
                                    {!quote?.multiYearCpa ? (
                                      <Badge
                                        variant="danger"
                                        style={{ cursor: "pointer" }}
                                      >
                                        <ImCross />
                                      </Badge>
                                    ) : gstToggle == 0 ? (
                                      <span className="value">
                                        {currencyFormater(
                                          parseInt(quote?.multiYearCpa)
                                        )}
                                      </span>
                                    ) : (
                                      <sapn className="value">
                                        {currencyFormater(
                                          parseInt(quote?.multiYearCpa * 1.18)
                                        )}
                                      </sapn>
                                    )}
                                  </ItemPrice>
                                </Col>
                              </>
                            )}
                          {ZD_Availablity() ? (
                            <>
                              <Col sm={5} md={5} lg={5} xl={5}>
                                <ZeroDev className="d-flex no-wrap">
                                  <CustomTooltip
                                    rider="true"
                                    id={
                                      quote?.gdd === "Y"
                                        ? `zdlp_m_gdd${index}`
                                        : `zdlp_m${index}`
                                    }
                                    place={"top"}
                                    customClassName="mt-3 "
                                    allowClick
                                    arrowColor
                                  >
                                    <p
                                      data-tip={`<div>With this upgrade, you will be eligible for the selected number of Zero Depreciation claims (instead of the usual one) in the policy period. All claims made in excess will be treated as non-zero dep claims.</div>`}
                                      data-html={true}
                                      data-for={
                                        quote?.gdd === "Y"
                                          ? `zdlp_m_gdd${index}`
                                          : `zdlp_m${index}`
                                      }
                                      className="text-left mx-2"
                                      onClick={() =>
                                        quote?.gdd === "Y"
                                          ? document.getElementById(
                                              `zdlp_gdd${index}`
                                            )
                                            ? document
                                                .getElementById(
                                                  `zdlp_gdd${index}`
                                                )
                                                .click()
                                            : {}
                                          : document.getElementById(
                                              `zdlp${index}`
                                            )
                                          ? document
                                              .getElementById(`zdlp${index}`)
                                              .click()
                                          : {}
                                      }
                                      style={{
                                        fontSize: [
                                          "BAJAJ",
                                          "ACE",

                                          "SRIDHAR",
                                          "HEROCARE",
                                        ].includes(
                                          import.meta.env.VITE_BROKER || ""
                                        )
                                          ? "11px"
                                          : "10px",
                                        cursor: "pointer",
                                        margin: "0.27rem 0",
                                      }}
                                    >
                                      Zero-dep claim
                                    </p>
                                  </CustomTooltip>
                                </ZeroDev>
                              </Col>
                              <Col
                                sm={7}
                                md={7}
                                lg={7}
                                xl={7}
                                className="d-flex w-100"
                              >
                                <div
                                  className="text-right w-100"
                                  style={{
                                    fontSize: "14px",
                                    marginTop: "1.2px",
                                  }}
                                >
                                  {quote?.gdd !== "Y" ? (
                                    !_.isEmpty(claimList) &&
                                    claimList.length > 1 ? (
                                      <>
                                        {
                                          <Badge
                                            variant={"light"}
                                            className="mx-1"
                                            style={
                                              claimList.sort().indexOf(zdlp) > 0
                                                ? {
                                                    color: "red",
                                                    position: "relative",
                                                    bottom: "1px",
                                                    cursor: "pointer",
                                                    fontSize: "10px",
                                                  }
                                                : {
                                                    visibility: "hidden",
                                                  }
                                            }
                                            onClick={() =>
                                              setZdlp(
                                                claimList[
                                                  claimList
                                                    .sort()
                                                    .indexOf(zdlp) - 1
                                                ]
                                              )
                                            }
                                          >
                                            <i className="fa fa-minus"></i>
                                          </Badge>
                                        }
                                        <Badge
                                          style={{
                                            fontSize: [
                                              "BAJAJ",
                                              "ACE",

                                              "SRIDHAR",
                                            ].includes(
                                              import.meta.env.VITE_BROKER || ""
                                            )
                                              ? "11px"
                                              : "10px",
                                          }}
                                        >
                                          {zdlp === "ONE"
                                            ? "ONE CLAIM"
                                            : zdlp === "TWO"
                                            ? "TWO CLAIM"
                                            : `${zdlp}`}
                                        </Badge>
                                        <Badge
                                          variant={"light"}
                                          className="mx-1 mb-1"
                                          style={
                                            claimList.sort().indexOf(zdlp) <
                                            claimList?.length * 1 - 1
                                              ? {
                                                  color: "green",
                                                  position: "relative",
                                                  bottom: "1px",
                                                  cursor: "pointer",
                                                  fontSize: "10px",
                                                }
                                              : {
                                                  visibility: "hidden",
                                                }
                                          }
                                          onClick={() =>
                                            setZdlp(
                                              claimList[
                                                claimList.sort().indexOf(zdlp) +
                                                  1
                                              ]
                                            )
                                          }
                                        >
                                          <i className="fa fa-plus"></i>
                                        </Badge>
                                      </>
                                    ) : (
                                      <noscript />
                                    )
                                  ) : !_.isEmpty(claimList_gdd) &&
                                    claimList_gdd.length > 1 ? (
                                    <>
                                      {
                                        <Badge
                                          variant={"light"}
                                          className="mx-1"
                                          style={
                                            claimList_gdd
                                              .sort()
                                              .indexOf(zdlp_gdd) > 0
                                              ? {
                                                  color: "red",
                                                  position: "relative",
                                                  bottom: "1px",
                                                  cursor: "pointer",
                                                }
                                              : {
                                                  visibility: "hidden",
                                                }
                                          }
                                          onClick={() =>
                                            setZdlp_gdd(
                                              claimList_gdd[
                                                claimList_gdd
                                                  .sort()
                                                  .indexOf(zdlp_gdd) - 1
                                              ]
                                            )
                                          }
                                        >
                                          <i className="fa fa-minus"></i>
                                        </Badge>
                                      }
                                      <Badge
                                        style={{
                                          fontSize: [
                                            "BAJAJ",
                                            "ACE",

                                            "SRIDHAR",
                                          ].includes(
                                            import.meta.env.VITE_BROKER || ""
                                          )
                                            ? "11px"
                                            : "12px",
                                        }}
                                      >
                                        {zdlp_gdd === "ONE"
                                          ? "ONE CLAIM"
                                          : zdlp_gdd === "TWO"
                                          ? "TWO CLAIM"
                                          : `${zdlp_gdd}`}
                                      </Badge>
                                      <Badge
                                        variant={"light"}
                                        className="mx-1 mb-1"
                                        style={
                                          claimList_gdd
                                            .sort()
                                            .indexOf(zdlp_gdd) <
                                          claimList_gdd?.length * 1 - 1
                                            ? {
                                                color: "green",
                                                position: "relative",
                                                bottom: "1px",
                                                cursor: "pointer",
                                              }
                                            : {
                                                visibility: "hidden",
                                              }
                                        }
                                        onClick={() =>
                                          setZdlp_gdd(
                                            claimList_gdd[
                                              claimList_gdd
                                                .sort()
                                                .indexOf(zdlp_gdd) + 1
                                            ]
                                          )
                                        }
                                      >
                                        <i className="fa fa-plus"></i>
                                      </Badge>
                                    </>
                                  ) : (
                                    <noscript />
                                  )}
                                </div>
                              </Col>
                            </>
                          ) : (
                            <noscript />
                          )}
                          {isPayD &&
                            quote?.policyType !== "Third Party" &&
                            type === "car" && (
                              <PayAsYouDrive
                                payD={
                                  quote?.payAsYouDrive ||
                                  quote?.additionalTowingOptions
                                }
                                isTowing={
                                  !_.isEmpty(quote?.additionalTowingOptions)
                                }
                                FetchQuotes={FetchQuotes}
                                quote={quote}
                                type={TypeReturn(type)}
                                enquiry_id={enquiry_id}
                                temp_data={temp_data}
                                addOnsAndOthers={addOnsAndOthers}
                                noPadding
                                lessthan767={lessthan767}
                                multiUpdateQuotes={
                                  multiUpdateQuotes?.godigit || []
                                }
                              />
                            )}
                        </Row>
                      </Col>
                    </Row>
                  </Col>
                  {!popupCard && (
                    <Col lg={5} md={5} sm={5} xs={5}>
                      <Row style={{ alignItems: "center" }}>
                        <Col
                          sm={6}
                          md={6}
                          lg={6}
                          xl={6}
                          className="coverIdv"
                          style={{
                            ...(quote?.dummyTile && { visibility: "hidden" }),
                          }}
                        >
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
                          <IdvText>IDV Value</IdvText>

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
                            <p
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
                                fontWeight: "600",
                                fontSize: "14px",
                                margin: "0",
                              }}
                            >{` ₹ ${currencyFormater(quote?.idv)}`}</p>
                          )}
                        </Col>
                        <Col
                          lg={6}
                          md={6}
                          sm={6}
                          xl={6}
                          style={{ display: "flex", flexDirection: "column" }}
                        >
                          {temp_data.tab !== "tab2" && !quote?.dummyTile && (
                            <Discount>
                              <del>
                                {`₹
                            ${currencyFormater(
                              totalPremiumC -
                                (quote?.tppdDiscount * 1 || 0) +
                                (!gstToggle
                                  ? totalPremium * 1
                                  : finalPremium * 1)
                            )}`}
                              </del>
                            </Discount>
                          )}
                          <CardBuyButton
                            translate="no"
                            themeDisable={
                              quote?.companyAlias === "hdfc_ergo" &&
                              temp_data?.carOwnership
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
                            id={`buy-${quote?.policyId}`}
                          >
                            {gstToggle ? (
                              <small className="withGstText">incl. GST</small>
                            ) : (
                              <noscript />
                            )}
                            <div
                              className="buyText"
                              style={{ display: !popupCard ? "none" : "" }}
                            >
                              {paydLoading &&
                              quote?.companyAlias === paydLoading &&
                              (quote?.payAsYouDrive ||
                                quote?.additionalTowingOptions) ? (
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
                                <> {popupCard ? "PROCEED" : "BUY NOW"}</>
                              )}
                            </div>
                            <div
                              style={{
                                fontWeight:
                                  import.meta.env.VITE_BROKER === "RB"
                                    ? "600"
                                    : "1000",
                                fontSize: "18px",
                              }}
                              className="buyPrice"
                            >
                              <span style={{ fontWeight: "1000" }}>
                                {" "}
                                {paydLoading &&
                                quote?.companyAlias === paydLoading &&
                                (quote?.payAsYouDrive ||
                                  quote?.additionalTowingOptions) ? (
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
                                      ? "Renew"
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
                    </Col>
                  )}
                </Row>
              </>
            ) : (
              <>
                <Row>
                  <Col
                    lg={2}
                    md={2}
                    sm={2}
                    xs="2"
                    style={{
                      ...(quote?.dummyTile && { visibility: "hidden" }),
                    }}
                  >
                    <LogoImg
                      src={quote?.companyLogo ? quote?.companyLogo : demoLogo}
                      alt="Plan Logo"
                    />
                    <div className="values" style={{ border: "none" }}>
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
                      <div className="coverIdv text-center"> IDV Value</div>
                      <div className="idvPrice text-center">
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
                            ₹ {currencyFormater(quote?.idv)}`
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
                      style={{
                        ...(!quote?.dummyTile
                          ? { width: "58%", padding: "3px 0px" }
                          : {}),
                      }}
                      id={`buy-${quote?.policyId}`}
                    >
                      {gstToggle ? (
                        <small className="withGstText">incl. GST</small>
                      ) : (
                        <noscript />
                      )}
                      {quote?.dummyTile
                        ? "Renew"
                        : popupCard
                        ? "PROCEED"
                        : "BUY NOW"}
                      <span translate="no" style={{ fontWeight: "1000" }}>
                        {" "}
                        {paydLoading &&
                        quote?.companyAlias === paydLoading &&
                        (quote?.payAsYouDrive ||
                          quote?.additionalTowingOptions) ? (
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
          </CardOtherItemInner>
          {isAddonsAvailable && temp_data?.tab !== "tab2" && (
            <div
              style={{
                padding: isAddonsAvailable && "0px 27px",
                ...(quote?.dummyTile && { visibility: "hidden" }),
              }}
            >
              <NoAddonCotainer>
                <Badge
                  className="tabBadge"
                  style={{ fontSize: "9px", marginTop: "-7px" }}
                >
                  Addons
                </Badge>
              </NoAddonCotainer>
            </div>
          )}
          <CardOtherItemNoBorder dummyTile={quote?.dummyTile}>
            {isAddonsAvailable && (
              <Row
                style={{
                  padding:
                    (addonTab || borderCondition) && temp_data?.tab !== "tab2"
                      ? "7px 27px"
                      : "0px 27px",
                  borderBottom:
                    (addonTab ||
                      !_.isEmpty(
                        totalApplicableAddonsMotor.filter(
                          (item) => item === "imt23"
                        )
                      )) &&
                    ".5px solid #e3e4e7",
                }}
              >
                {!popupCard && (
                  <>
                    {temp_data?.tab !== "tab2" && (
                      <>
                        {(quote?.company_alias === "reliance" &&
                        totalApplicableAddonsMotor?.includes(
                          "roadSideAssistance"
                        ) &&
                        TypeReturn(type) === "cv"
                          ? [
                              ..._.without(
                                totalApplicableAddonsMotor,
                                "roadSideAssistance"
                              )
                                .sort()
                                .reverse(),
                              "roadSideAssistance",
                            ]
                          : (totalApplicableAddonsMotor || []).sort().reverse()
                        ).map((item, index) => (
                          <AddonContainer
                            style={
                              (quote?.company_alias === "reliance" &&
                                item === "roadSideAssistance" &&
                                TypeReturn(type) === "cv") ||
                              item === "imt23"
                                ? {
                                    visibility: "hidden",
                                  }
                                : {}
                            }
                            hide={item === "imt23"}
                          >
                            <ItemName>
                              {" "}
                              {item === "emergencyMedicalExpenses" &&
                              (between9to12 || between13to14)
                                ? "Emergency M.E"
                                : getAddonName(item)}
                            </ItemName>
                            <ItemPrice
                              style={
                                (quote?.company_alias === "reliance" &&
                                  item === "roadSideAssistance" &&
                                  TypeReturn(type) === "cv") ||
                                item === "imt23"
                                  ? {
                                      visibility: "hidden",
                                    }
                                  : {}
                              }
                            >
                              {GetAddonValue(item, addonDiscountPercentage) ===
                              "N/S" ? (
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
                                    <ImCross />
                                  </Badge>
                                </NoAddonCotainer>
                              ) : (
                                <NoAddonCotainer>
                                  {!gstToggle
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
                          </AddonContainer>
                        ))}
                      </>
                    )}
                  </>
                )}
              </Row>
            )}
            {isOthersAvailable && (
              <div
                style={{
                  padding: "0px 13.5px",
                }}
              >
                <NoAddonCotainer>
                  <Badge
                    className="tabBadge"
                    style={{ fontSize: "9px", marginTop: "-7px" }}
                  >
                    {`${isAccessoriesAvailable ? "Accessories" : ""} ${
                      isCoverAvailable && isAccessoriesAvailable
                        ? "/ Covers"
                        : isCoverAvailable
                        ? "Covers"
                        : ""
                    } ${
                      isDiscountAvailable &&
                      (isCoverAvailable || isAccessoriesAvailable)
                        ? "/ Discounts"
                        : isDiscountAvailable
                        ? "Discounts"
                        : ""
                    }`}
                  </Badge>
                </NoAddonCotainer>
              </div>
            )}
            {isOthersAvailable && (
              <Row
                style={{
                  padding: borderCondition ? "7px 27px" : "0px 27px",
                }}
              >
                {/* accesories */}
                {addOnsAndOthers?.selectedAccesories?.includes(
                  "Electrical Accessories"
                ) &&
                  temp_data.tab !== "tab2" && (
                    <AddonContainer>
                      <ItemName>Electrical Accessories</ItemName>
                      <ItemPrice>
                        {!quote?.motorElectricAccessoriesValue &&
                        quote?.companyAlias !== "godigit" ? (
                          <Badge
                            variant="danger"
                            style={{ position: "relative" }}
                          >
                            <ImCross />
                          </Badge>
                        ) : Number(quote?.motorElectricAccessoriesValue) ===
                            0 && quote?.companyAlias !== "godigit" ? (
                          <Badge
                            variant="danger"
                            style={{ position: "relative" }}
                          >
                            <ImCross />
                          </Badge>
                        ) : quote?.companyAlias !== "godigit" ? (
                          <span className="value">
                            ₹{" "}
                            {currencyFormater(
                              Number(quote?.motorElectricAccessoriesValue)
                            )}
                          </span>
                        ) : (
                          <Badge
                            variant="primary"
                            style={{ position: "relative" }}
                          >
                            <ImCheckmark />
                          </Badge>
                        )}
                      </ItemPrice>
                    </AddonContainer>
                  )}
                {addOnsAndOthers?.selectedAccesories?.includes(
                  "Non-Electrical Accessories"
                ) &&
                  temp_data.tab !== "tab2" && (
                    <AddonContainer>
                      <ItemName>Non Electrical Accessories</ItemName>
                      <ItemPrice>
                        {!quote?.motorNonElectricAccessoriesValue &&
                        quote?.companyAlias !== "godigit" ? (
                          <Badge
                            variant="danger"
                            style={{ position: "relative" }}
                          >
                            <ImCross />
                          </Badge>
                        ) : Number(quote?.motorNonElectricAccessoriesValue) ===
                            0 && quote?.companyAlias !== "godigit" ? (
                          <Badge
                            variant="danger"
                            style={{ position: "relative" }}
                          >
                            <ImCross />
                          </Badge>
                        ) : quote?.companyAlias !== "godigit" ? (
                          <span className="value">
                            ₹{" "}
                            {currencyFormater(
                              Number(quote?.motorNonElectricAccessoriesValue)
                            )}
                          </span>
                        ) : (
                          <Badge
                            variant="primary"
                            style={{ position: "relative" }}
                          >
                            <ImCheckmark />
                          </Badge>
                        )}
                      </ItemPrice>
                    </AddonContainer>
                  )}
                {addOnsAndOthers?.selectedAccesories?.includes(
                  "External Bi-Fuel Kit CNG/LPG"
                ) &&
                  temp_data?.parent?.productSubTypeCode !== "MISC" &&
                  !(
                    temp_data?.fuel === "CNG" || temp_data?.fuel === "ELECTRIC"
                  ) &&
                  TypeReturn(type) !== "bike" &&
                  quote?.policyType !== "Third Party" && (
                    <AddonContainer>
                      <ItemName>Bi Fuel Kit</ItemName>
                      <ItemPrice>
                        {Number(quote?.motorLpgCngKitValue) === 0 &&
                        quote?.companyAlias !== "godigit" ? (
                          <Badge
                            variant="danger"
                            style={{ position: "relative" }}
                          >
                            <ImCross />
                          </Badge>
                        ) : quote?.company_alias === "godigit" ? (
                          <Badge
                            variant="primary"
                            style={{ position: "relative" }}
                          >
                            <ImCheckmark />
                          </Badge>
                        ) : (
                          <span className="value">
                            ₹{" "}
                            {currencyFormater(
                              Number(quote?.motorLpgCngKitValue)
                            )}
                          </span>
                        )}
                      </ItemPrice>
                    </AddonContainer>
                  )}
                {/* TP */}
                {addOnsAndOthers?.selectedAccesories?.includes(
                  "External Bi-Fuel Kit CNG/LPG"
                ) &&
                  temp_data?.parent?.productSubTypeCode !== "MISC" &&
                  !(
                    temp_data?.fuel === "CNG" || temp_data?.fuel === "ELECTRIC"
                  ) &&
                  quote?.policyType !== "Own Damage" &&
                  TypeReturn(type) !== "bike" && (
                    <AddonContainer>
                      <ItemName>Bi Fuel Kit TP</ItemName>
                      <ItemPrice>
                        {Number(quote?.cngLpgTp) === 0 &&
                        quote?.companyAlias !== "godigit" ? (
                          <Badge
                            variant="danger"
                            style={{ position: "relative" }}
                          >
                            <ImCross />
                          </Badge>
                        ) : quote?.company_alias === "godigit" ? (
                          <Badge
                            variant="primary"
                            style={{ position: "relative" }}
                          >
                            <ImCheckmark />
                          </Badge>
                        ) : (
                          <span className="value">
                            ₹ {currencyFormater(Number(quote?.cngLpgTp))}
                          </span>
                        )}
                      </ItemPrice>
                    </AddonContainer>
                  )}
                {/* additional cover  */}
                {addOnsAndOthers?.selectedAddons?.includes("imt23") && (
                  <AddonContainer>
                    <ItemName>IMT - 23</ItemName>
                    <ItemPrice>
                      {GetAddonValue("imt23", addonDiscountPercentage) ===
                      "N/S" ? (
                        <NoAddonCotainer>
                          <Badge
                            variant="secondary"
                            style={{ cursor: "pointer" }}
                          >
                            Not selected
                          </Badge>
                        </NoAddonCotainer>
                      ) : GetAddonValue("imt23", addonDiscountPercentage) ===
                        "N/A" ? (
                        <NoAddonCotainer>
                          <Badge variant="danger" style={{ cursor: "pointer" }}>
                            <ImCross />
                          </Badge>
                        </NoAddonCotainer>
                      ) : (
                        <NoAddonCotainer>
                          {!gstToggle
                            ? GetAddonValueNoGst(
                                "imt23",
                                addonDiscountPercentage
                              )
                            : GetAddonValue("imt23", addonDiscountPercentage)}
                        </NoAddonCotainer>
                      )}
                    </ItemPrice>
                  </AddonContainer>
                )}
                {TypeReturn(type) !== "bike" && (
                  <>
                    {(quote?.motorAdditionalPaidDriver * 1 ||
                      quote?.motorAdditionalPaidDriver * 1 === 0) &&
                    (addOnsAndOthers?.selectedAdditions?.includes(
                      "PA paid driver/conductor/cleaner"
                    ) ||
                      addOnsAndOthers?.selectedAdditions?.includes(
                        "PA cover for additional paid driver"
                      )) ? (
                      <AddonContainer>
                        <ItemName>
                          {temp_data?.journeyCategory === "GCV"
                            ? "PA Paid Driver/Conductor/Cleaner"
                            : "PA cover for additional paid driver"}
                        </ItemName>
                        <ItemPrice>
                          {quote?.motorAdditionalPaidDriver * 1 ? (
                            <span className="value">
                              {`₹ ${currencyFormater(
                                quote?.companyAlias === "sbi" &&
                                  addOnsAndOthers?.selectedCpa?.includes(
                                    "Compulsory Personal Accident"
                                  ) &&
                                  !_.isEmpty(addOnsAndOthers?.isTenure)
                                  ? quote?.motorAdditionalPaidDriver *
                                      (TypeReturn(type) === "bike" ? 5 : 3)
                                  : quote?.motorAdditionalPaidDriver
                              )}`}
                            </span>
                          ) : (
                            <Badge
                              variant="danger"
                              style={{ position: "relative" }}
                            >
                              <ImCross />
                            </Badge>
                          )}
                        </ItemPrice>
                      </AddonContainer>
                    ) : (
                      <noscript />
                    )}
                  </>
                )}
                {temp_data.journeyCategory !== "GCV" && !temp_data?.odOnly && (
                  <>
                    {addOnsAndOthers?.selectedAdditions?.includes(
                      "Unnamed Passenger PA Cover"
                    ) &&
                      !BlockedSections(
                        import.meta.env.VITE_BROKER,
                        temp_data?.journeyCategory
                      )?.includes("unnamed pa cover") && (
                        <AddonContainer>
                          <ItemName>Unnamed Passenger PA cover</ItemName>
                          <ItemPrice>
                            {Number(quote?.coverUnnamedPassengerValue) === 0 ||
                            quote?.coverUnnamedPassengerValue === "N/A" ||
                            quote?.coverUnnamedPassengerValue === "NA" ? (
                              <Badge
                                variant="danger"
                                style={{ position: "relative" }}
                              >
                                <ImCross />
                              </Badge>
                            ) : (
                              <span className="value">
                                ₹{" "}
                                {currencyFormater(
                                  quote?.companyAlias === "sbi" &&
                                    addOnsAndOthers?.selectedCpa?.includes(
                                      "Compulsory Personal Accident"
                                    ) &&
                                    !_.isEmpty(addOnsAndOthers?.isTenure)
                                    ? quote?.coverUnnamedPassengerValue *
                                        (type === "bike" ? 5 : 3)
                                    : quote?.coverUnnamedPassengerValue
                                )}
                              </span>
                            )}
                          </ItemPrice>
                        </AddonContainer>
                      )}
                  </>
                )}
                {(quote?.defaultPaidDriver * 1 ||
                  quote?.defaultPaidDriver * 1 === 0) &&
                (addOnsAndOthers?.selectedAdditions?.includes(
                  "LL paid driver"
                ) ||
                  addOnsAndOthers?.selectedAdditions?.includes(
                    "LL paid driver/conductor/cleaner"
                  )) ? (
                  !llpaidCon ? (
                    <AddonContainer>
                      <ItemName>
                        {temp_data?.journeyCategory === "GCV"
                          ? "LL Paid Driver/Conductor/Cleaner"
                          : "LL Paid Driver"}{" "}
                      </ItemName>
                      <ItemPrice>
                        {quote?.defaultPaidDriver * 1 ? (
                          <span className="value">
                            ₹ {currencyFormater(quote?.defaultPaidDriver)}
                          </span>
                        ) : (
                          <Badge
                            variant="danger"
                            style={{ position: "relative" }}
                          >
                            <ImCross />
                          </Badge>
                        )}
                      </ItemPrice>
                    </AddonContainer>
                  ) : (
                    <>
                      <AddonContainer>
                        <ItemName>Legal Liability To Paid Driver</ItemName>
                        <ItemPrice>
                          {quote?.llPaidDriverPremium * 1 ? (
                            <span className="value">
                              ₹ {currencyFormater(quote?.llPaidDriverPremium)}
                            </span>
                          ) : (
                            <Badge
                              variant="danger"
                              style={{ position: "relative" }}
                            >
                              <ImCross />
                            </Badge>
                          )}
                        </ItemPrice>
                      </AddonContainer>
                      {addOnsAndOthers?.selectedAdditions?.includes(
                        "LL paid driver/conductor/cleaner"
                      ) && quote?.llPaidConductorPremium * 1 ? (
                        <AddonContainer>
                          <ItemName>
                            {`Legal Liability To Paid Conductor ${
                              quote?.companyAlias === "icici_lombard" ||
                              quote?.companyAlias === "magma"
                                ? "/Cleaner"
                                : ""
                            }`}{" "}
                          </ItemName>
                          <ItemPrice>
                            {quote?.llPaidConductorPremium * 1 ? (
                              <span className="value">
                                {`₹ ${currencyFormater(
                                  quote?.llPaidConductorPremium
                                )}
                                `}
                              </span>
                            ) : (
                              <Badge
                                variant="danger"
                                style={{ position: "relative" }}
                              >
                                <ImCross />
                              </Badge>
                            )}
                          </ItemPrice>
                        </AddonContainer>
                      ) : (
                        <noscript />
                      )}
                      {!(
                        quote?.companyAlias === "icici_lombard" ||
                        quote?.companyAlias === "magma"
                      ) ? (
                        <AddonContainer>
                          <ItemName>Legal Liability To Paid Cleaner</ItemName>
                          <ItemPrice>
                            {quote?.llPaidCleanerPremium * 1 ? (
                              <span className="value">
                                ₹{" "}
                                {currencyFormater(quote?.llPaidCleanerPremium)}
                              </span>
                            ) : (
                              <Badge
                                variant="danger"
                                style={{ position: "relative" }}
                              >
                                <ImCross />
                              </Badge>
                            )}
                          </ItemPrice>
                        </AddonContainer>
                      ) : (
                        <noscript />
                      )}
                    </>
                  )
                ) : (
                  <noscript />
                )}
                {addOnsAndOthers?.selectedAdditions?.includes(
                  "Geographical Extension"
                ) &&
                  quote?.policyType !== "Third Party" && (
                    <AddonContainer>
                      <ItemName>Geographical Extension</ItemName>
                      <ItemPrice>
                        {quote?.geogExtensionODPremium * 1 ? (
                          <span className="value">
                            ₹ {currencyFormater(quote?.geogExtensionODPremium)}
                          </span>
                        ) : (
                          <Badge
                            variant="danger"
                            style={{ position: "relative" }}
                          >
                            <ImCross />
                          </Badge>
                        )}
                      </ItemPrice>
                    </AddonContainer>
                  )}
                {addOnsAndOthers?.selectedAdditions?.includes(
                  "Geographical Extension"
                ) &&
                  quote?.policyType !== "Own Damage" && (
                    <AddonContainer>
                      <ItemName>Geographical Extension TP</ItemName>
                      <ItemPrice>
                        {quote?.geogExtensionTPPremium * 1 ? (
                          <span className="value">
                            ₹ {currencyFormater(quote?.geogExtensionTPPremium)}
                          </span>
                        ) : (
                          <Badge
                            variant="danger"
                            style={{ position: "relative" }}
                          >
                            <ImCross />
                          </Badge>
                        )}
                      </ItemPrice>
                    </AddonContainer>
                  )}
                {/* discount  */}
                {!BlockedSections(
                  import.meta.env.VITE_BROKER,
                  temp_data?.journeyCategory
                )?.includes("unnamed pa cover") && (
                  <>
                    {temp_data.journeyCategory !== "GCV" &&
                      temp_data.tab !== "tab2" &&
                      addOnsAndOthers?.selectedDiscount?.includes(
                        "Is the vehicle fitted with ARAI approved anti-theft device?"
                      ) && (
                        <AddonContainer>
                          <ItemName>Vehicle is fitted with ARAI</ItemName>
                          <ItemPrice>
                            {!quote?.antitheftDiscount ? (
                              <Badge
                                variant="danger"
                                style={{ position: "relative" }}
                              >
                                <ImCross />
                              </Badge>
                            ) : quote?.antitheftDiscount === "" ||
                              quote?.antitheftDiscount === 0 ? (
                              <Badge
                                variant="danger"
                                style={{ position: "relative" }}
                              >
                                <ImCross />
                              </Badge>
                            ) : (
                              <span className="value">
                                ₹ {currencyFormater(quote?.antitheftDiscount)}
                              </span>
                            )}
                          </ItemPrice>
                        </AddonContainer>
                      )}

                    {TypeReturn(type) !== "cv" &&
                      !BlockedSections(import.meta.env.VITE_BROKER).includes(
                        "voluntary discount"
                      ) &&
                      temp_data.tab !== "tab2" &&
                      addOnsAndOthers?.selectedDiscount?.includes(
                        "Voluntary Discounts"
                      ) && (
                        <AddonContainer>
                          <ItemName>Voluntary Deductible</ItemName>
                          <ItemPrice>
                            {!quote?.voluntaryExcess ? (
                              <Badge
                                variant="danger"
                                style={{ position: "relative" }}
                              >
                                <ImCross />
                              </Badge>
                            ) : quote?.voluntaryExcess * 1 === 0 ? (
                              <Badge
                                variant="danger"
                                style={{ position: "relative" }}
                              >
                                <ImCross />
                              </Badge>
                            ) : (
                              <span className="value">
                                ₹ {Math.round(quote?.voluntaryExcess)}
                              </span>
                            )}
                          </ItemPrice>
                        </AddonContainer>
                      )}
                    {type === "cv" &&
                      addOnsAndOthers?.selectedDiscount?.includes(
                        "Vehicle Limited to Own Premises"
                      ) && (
                        <AddonContainer>
                          <ItemName>Vehicle Limited to Own Premises</ItemName>
                          <ItemPrice>
                            {quote?.limitedtoOwnPremisesOD ? (
                              <span className="value">
                                ₹ {quote?.limitedtoOwnPremisesOD}
                              </span>
                            ) : (
                              <Badge
                                variant="danger"
                                style={{ position: "relative" }}
                              >
                                <ImCross />
                              </Badge>
                            )}
                          </ItemPrice>
                        </AddonContainer>
                      )}
                    {addOnsAndOthers?.selectedDiscount?.includes(
                      "TPPD Cover"
                    ) && (
                      <AddonContainer>
                        <ItemName> TPPD Cover</ItemName>
                        <ItemPrice>
                          {quote?.tppdDiscount ? (
                            <span className="value">
                              ₹ {Math.round(quote?.tppdDiscount)}
                            </span>
                          ) : (
                            <Badge
                              variant="danger"
                              style={{ position: "relative" }}
                            >
                              <ImCross />
                            </Badge>
                          )}
                        </ItemPrice>
                      </AddonContainer>
                    )}
                  </>
                )}
              </Row>
            )}
            <Row>
              <KnowMoreButton>
                <Col lg={6} md={6} sm={6} xs="6">
                  <FeatureList>
                    {quote?.usp?.length > 0 &&
                      quote?.usp
                        ?.slice(0, 2)
                        ?.map((item, index) => (
                          <Feature>{item?.usp_desc}</Feature>
                        ))}
                  </FeatureList>
                </Col>
                <Col
                  lg={6}
                  md={6}
                  sm={6}
                  xs="6"
                  style={{
                    display: "flex",
                    justifyContent: "flex-end",
                    alignItems: "center",
                    gap: "20px",
                  }}
                >
                  <CashlessButon
                    onClick={() => {
                      //setKnowMore(true);
                      quote?.companyAlias === "hdfc_ergo" &&
                      temp_data?.carOwnership
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
                    <GiMechanicGarage
                      style={{
                        cursor: !quote?.garageCount && "not-allowed",
                        color: !quote?.garageCount && "#6b6e7166",
                        marginRight: "5px",
                      }}
                    />
                    <CardOtherItemBtn
                      style={{
                        cursor: !quote?.garageCount && "not-allowed",
                        color: !quote?.garageCount && "#6b6e7166",
                      }}
                    >
                      Cashless Garages{" "}
                      {quote?.garageCount?.length < 0
                        ? null
                        : quote?.garageCount?.length > 0
                        ? `(${quote?.garageCount?.length})`
                        : ""}
                    </CardOtherItemBtn>
                  </CashlessButon>
                  <CashlessButon
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
                    <GiMoneyStack style={{ marginRight: "5px" }} />
                    <CardOtherItemBtn>Premium Breakup</CardOtherItemBtn>
                  </CashlessButon>
                  {!popupCard &&
                    progressPercent === 100 &&
                    tempData.quoteComprehesiveGrouped.length > 1 && (
                      <>
                        <StyledDiv
                          tab={temp_data.tab}
                          disabled={temp_data.tab === "tab2"}
                          mouseHover={mouseHover}
                          onClick={handleCompare}
                          style={{
                            cursor:
                              temp_data.tab === "tab2"
                                ? "not-allowed"
                                : "pointer",
                            fontSize: "12px",
                            pointerEvents: allQuoteloading ? "none" : "",

                            ...((quote?.dummyTile || filterRenewal) && {
                              visibility: "hidden",
                            }),
                          }}
                        >
                          Compare
                        </StyledDiv>

                        <StyledDiv1 tab={temp_data.tab}>
                          <span
                            className="group-check float-right  "
                            style={{
                              width: "5%",
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
                                (length >= (lessthan767 ? 2 : 3) &&
                                  !watch(`checkmark[${index}]`)) ||
                                temp_data.tab === "tab2" ||
                                allQuoteloading
                                  ? true
                                  : false
                              }
                            />
                            <label
                              className="round-label"
                              onClick={handleCompare}
                            ></label>
                          </span>
                        </StyledDiv1>
                      </>
                    )}
                </Col>
              </KnowMoreButton>
            </Row>
          </CardOtherItemNoBorder>
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

export const GridSkeleton = ({
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
    <>
      <Col
        lg={!popupCard ? 12 : multiPopupCard ? 12 : 12}
        md={12}
        sm={12}
        style={{
          marginTop: !popupCard ? "30px" : "20px",
          maxWidth: popupCard ? (lessthan767 ? "100%" : "45%") : "",
          cursor: quotesLoaded || loading ? "progress" : "default",
        }}
      >
        <QuoteCardMain
          style={{
            ...(lessthan767 &&
              popupCard && {
                minHeight: "310px",
              }),
          }}
        >
          <CardOtherItemInner>
            <Row>
              <Col xlg={3} lg={3} style={{ textAlign: "left" }}>
                <Skeleton width={140} height={50}></Skeleton>
              </Col>
              <Col xlg={9} lg={9}>
                <Skeleton width={"100%"} height={50}></Skeleton>
              </Col>
            </Row>
            <CardOtherIdv></CardOtherIdv>
          </CardOtherItemInner>

          <div style={{ marginBottom: "10px", padding: "0 30px" }}>
            <Skeleton
              style={{ marginBottom: "10px" }}
              width={"100%"}
              height={20}
            ></Skeleton>
            <Skeleton width={"100%"} height={20}></Skeleton>
          </div>
        </QuoteCardMain>
      </Col>
      <GlobalStyle />
    </>
  );
};

const GlobalStyle = createGlobalStyle`

`;
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

const QuoteCardMain = styled.div`
  display: inline-block;
  position: relative;
  overflow: hidden;
  margin-right: 16px;
  /* padding: 10px 0 0; */
  border-radius: 8px;
  width: 100%;
  // overflow: hidden;
  min-height: "min-content";
  box-shadow: rgba(17, 17, 26, 0.05) 0px 1px 0px,
    rgba(17, 17, 26, 0.1) 0px 0px 8px;
  transition: box-shadow 0.3s ease-in-out;
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
    /* transform: scale(1.05); */
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
const CardOtherItemInner = styled.div`
  ${(props) =>
    props?.autoHeight
      ? `border-bottom: solid 1px transparent;`
      : `border-bottom: solid 1px #e3e4e8;`}
  ${(props) =>
    props?.autoHeight
      ? `padding: 10px 25px 5px 15px;`
      : `padding: 13px 30px 15px;`}
  /* height: ${(props) => (props?.autoHeight ? "" : `146px`)}; */
  @media only screen and (max-width: 1200px) and (min-width: 950px) {
    /* height: 230px; */
  }
  .coverIdv {
    padding: 0px 2.5px;
    font-size: 12.5px;
    text-align: center;
    margin-top: 3px;
    color: #4d5154;
    width: 100%;
    p {
      @media only screen and (max-width: 1150px) and (min-width: 993px) {
        font-size: 12px !important;
      }
    }
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
`;

const LogoImg = styled.img`
  /* max-height: 56px; */
  /* margin-bottom: 15px; */
  //margin-left: -10px;
  /* margin-top: -5px; */
  /* height: 56px; */
  /* min-height: 45px; */
  justify-content: flex-start;
  display: flex;
  width: 110px;
  height: auto;
  /* padding: 1px; */
  /* border: 0.5px solid #c0c0c0; */
  border-radius: 8px;
  object-fit: contain;

  @media only screen and (max-width: 1150px) {
    /* width: 45px; */
  }
  @media only screen and (max-width: 768px) {
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
  }
`;

const CardOtherName = styled.div`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-SemiBold"};
  font-size: 13px;
  line-height: 20px;
  margin-bottom: 4px;
  height: 20px;
  @media only screen and (max-width: 1200px) and (min-width: 950px) {
    height: 40px;
  }
`;
//

const CardOtherIdv = styled.div`
  float: left;
  position: relative;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  font-size: 15px;
  line-height: 20px;
  color: #000;
  margin-bottom: 11px;
  cursor: pointer;
  font-weight: 600;
  height: 20px;
  @media only screen and (max-width: 1200px) and (min-width: 950px) {
    height: 30px;
  }
`;

const CardBuyButton = styled.button`
  float: right !important;
  width: 130px;
  display: flex;
  height: ${({ popupCard }) => (popupCard ? "50px" : "")};
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
  float: none;
  font-weight: 1000;
  padding: 0px 0px;
  color: ${({ theme }) => theme.QuoteCard?.color || "#6c757d"};
  /* margin-bottom: 16px !important; */
  margin: 0 auto;
  transition: 0.2s ease-in-out;
  position: relative;
  /* right: 10px; */
  @media only screen and (max-width: 1350px) {
    font-size: 10px !important;
  }
  .withGstText {
    color: black;
    position: absolute;
    bottom: -28px;
    right: 0;
    left: 0;
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 2px;
    @media (max-width: 767px) {
      left: 0;
      font-size: 10px;
      letter-spacing: 1px;
      top: -28px;
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
          : "#bdd400 !important"
      } `};
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
        } `};
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
    /* position: relative; */
    bottom: 28px;
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
    width: 90% !important;
  }
`;

const CardOtherItemNoBorder = styled.div`
  padding: 0px 12px 0px 15px;
  border-bottom: none;
  ${({ dummyTile }) => (dummyTile ? "display: none;" : "")}
`;

const ItemName = styled.p`
  font-size: ${["BAJAJ", "ACE", "SRIDHAR"].includes(import.meta.env.VITE_BROKER)
    ? "11px"
    : "12px"};
  text-align: left;
  /* margin-left: 15px; */
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  white-space: nowrap;
  color: #6c757d !important;
  margin-bottom: ${({ base, cpa }) => (base ? "5px" : cpa ? "0px" : "0px")};
  font-weight: ${({ theme }) => theme.regularFont?.fontWeight || "600"};
  ${({ base, theme }) =>
    base && `color : ${theme.QuoteBorderAndFont?.linkColor}!important;`}
  @media only screen and (max-width: 1150px) and (min-width: 993px) {
    font-size: 8px !important;
  }
  @media only screen and (max-width: 1350px) and (min-width: 1151px) {
    /* font-size: 10px !important; */
  }
`;

const ItemPrice = styled.p`
  text-align: end;
  font-weight: 600;
  font-size: ${["BAJAJ", "ACE", "SRIDHAR"].includes(import.meta.env.VITE_BROKER)
    ? "11px"
    : "12px"};
  display: flex;
  justify-content: end;
  align-items: center;
  margin-left: 5px;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  /* height: 18px !important; */
  margin-bottom: ${({ base, cpa }) => (base ? "5px" : cpa ? "0px" : "0px")};
  ${({ base, theme }) =>
    base &&
    `color : ${theme.QuoteBorderAndFont?.linkColor};
  `}
  .value {
    background: ${({ noValue, base, cpa }) =>
      noValue || base || cpa ? "#fff" : "#84c150"};
    color: ${({ noValue, base, cpa }) => (base || cpa ? "" : "#fff")};
    padding: 0 7px;
    border-radius: 5px;
  }
  @media only screen and(max-width: 767px) {
    .value {
      font-size: 9.5px !important;
    }
  }
  @media only screen and (max-width: 1150px) and (min-width: 993px) {
    font-size: 8px !important;
  }
  @media only screen and (max-width: 1350px) and (min-width: 1151px) {
    font-size: 10px !important;
  }
  .badge-danger {
    background: #ff6a6a !important;
  }
  .badge-primary {
    background: #84c150 !important;
  }
`;

const CardOtherItemBtn = styled.span`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  border-radius: 7px;
  color: ${({ theme }) => theme.QuoteCard?.color || "#bdd400"};
  cursor: pointer;
  height: 40px;
  font-size: 12px;
  line-height: 20px;
  /* padding: 10px; */
  /* border-top: solid 1px #e3e4e8; */
  /* text-align: left; */
  font-weight: 600;
  @media only screen and (max-width: 1300px) and (min-width: 950px) {
    font-size: 9px;
  }
  &:hover {
    color: ${({ theme }) => theme.floatButton?.floatColor || "#bdd400"};
  }
`;

const StyledDiv = styled.div`
  /* position: absolute; */
  //top: -24px;
  top: 36px;
  text-align: right !important;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"basier_squaremedium"`};
  font-size: 10px;
  line-height: 12px;
  color: ${({ tab }) => (tab === "tab2" ? "#6b6e7166" : "#6b6e71")};
  text-align: center;
  width: 85px;
  //border: 1px solid #bdd400;
  border-bottom: none;
  //	z-index: 100;
  /* padding: 6px 4px 6px 0px; */
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
  left: -95px;
  top: -10px !important;
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

  .group-check input[type="checkbox"]:checked + label {
    border: none !important;
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
  /* position: relative; */
  display: flex;
  .tabBadge {
    background: white !important;
    color: ${({ theme }) => theme.Tab?.color || "#4ca729"} !important;
    border: ${({ theme }) => theme.CheckBox?.border || "1px solid #bdd400"};
    border-radius: 2px;
  }
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

const CompareBtn = styled.div`
  position: absolute;
  right: 10px;
  top: 0;
  font-size: 20px;
  cursor: ${({ tab, isDisable }) =>
    tab === "tab2" || isDisable ? "not-allowed" : "pointer"};
  pointer-events: ${({ isDisable }) => (isDisable ? "none" : "auto")};
  color: ${({ theme, isDisable }) =>
    isDisable
      ? "gray"
      : theme.QuoteBorderAndFont?.linkColor || "#bdd400"} !important;
`;

const FeatureList = styled.ul`
  display: flex;
  font-size: 10px;
  gap: 30px;
  padding: 2px 30px;
  margin-bottom: 0px !important;
`;

const Feature = styled.li`
  border-radius: 3px;
  color: #000;
  white-space: nowrap;
`;

const KnowMoreButton = styled.div`
  width: 100%;
  display: flex;
  justify-content: flex-end;
  gap: 30px;
  padding: 7px 30px;
  background: #eafaff;
  font-size: 12px;
  white-space: nowrap;
`;

const Ribbons = styled.div`
  display: flex;
  background: ${({ theme }) =>
    theme.QuoteCard?.ribbonBackground
      ? `${theme.QuoteCard?.ribbonBackground}`
      : "linear-gradient(90deg, rgba(2,0,36,1) 0%, rgba(189,212,0,1) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 100%)"};
`;

const Ribbon = styled.div`
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 0px;
  text-align: left;
  padding: 5px 30px;
  color: #000;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
  div {
    width: 6px;
    height: 6px;
    border-radius: 20px;
    background: #36b37e;
  }
`;

const Discount = styled.span`
  font-size: 14px;
  color: ${({ theme }) =>
    theme.QuoteCard?.color ? `${theme.QuoteCard?.color}` : "#bdd400 "};
`;

const AddonContainer = styled.div`
  display: flex;
  gap: 5px;
  border: 0.5px solid lightgray;
  display: ${({ hide }) => (hide ? "none" : "")};
  padding: 2px 6px;
  border-radius: 3px;
  margin: 0px 4px 4px 0px !important;
`;

const CashlessButon = styled.div`
  font-size: 13px;
  color: ${({ theme }) => theme.QuoteCard?.color || "#bdd400"};
`;

const ZeroDev = styled.span`
  margin-left: -5px;
  font-weight: 700;
`;

const IdvText = styled.p`
  font-size: 14px;
  font-weight: bold;
  margin-bottom: 5px;
`;
