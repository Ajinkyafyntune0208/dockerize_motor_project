/* eslint-disable jsx-a11y/anchor-is-valid */
import React, { useState, useEffect, useRef, useCallback } from "react";
import PropTypes from "prop-types";
import { Row, Col } from "react-bootstrap";
import InfoCardKnowMore from "./knowMoreInfo";
import { useForm } from "react-hook-form";
import { Loader } from "components";
import { useDispatch, useSelector } from "react-redux";
import { useLocation } from "react-router";
import { useHistory } from "react-router-dom";
import { useMediaPredicate } from "react-media-hook";
import { differenceInDays } from "date-fns";
import moment from "moment";
import { toDate, fetchToken } from "utils";
import Popup from "components/Popup/Popup";
import { setTempData } from "modules/quotesPage/filterConatiner/quoteFilter.slice";
import debounce from "lodash.debounce";
import {
  setSelectedQuote,
  SaveQuotesData,
  SaveAddonsData,
  clear,
  setQuotesList,
  saveSelectedQuoteResponse,
  GarageList,
  setGarage,
  CancelAll,
} from "modules/quotesPage/quote.slice";
import "./knowMorePopup.scss";
import _ from "lodash";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { getAddonName } from "modules/quotesPage/quoteUtil";
import { TypeReturn } from "modules/type";
import Style, { Body } from "../style";
import { GetAddonValue } from "modules/helper";
import PremiumBody from "./PremiumBody";
import CashlessBody from "./CashlessBody";
import { useOutsideClick } from "hoc";
import Addons from "./Addons";
import MobilePremiumBreakup from "../knowMoreMobile/mobilePremiumBreakup";
import { _buyNow } from "modules/quotesPage/quoteCard/card-logic";
import { _saveQuoteTracking } from "analytics/quote-page/quote-tracking";
import { _discount } from "modules/quotesPage/quote-logic";
import { handleEmailClick } from "../premium-pdf/pdf/premium-pdf-email";
import { handlePremPdfClick } from "../premium-pdf/pdf/premium-pdf-download";
import { BuyNowBtn } from "./_component/buy-now";

import Table from "./Tables";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const KnowMorePopup = ({
  quote,
  show,
  onClose,
  selectedKnow,
  totalAddon,
  finalPremium,
  totalPremium,
  gst,
  totalPremiumA,
  totalPremiumB,
  applicableAddons,
  type,
  prevInsName,
  totalPremiumC,
  revisedNcb,
  popupCard,
  setPrevPopup,
  prevPopup,
  setSelectedId,
  setSelectedCompanyName,
  totalApplicableAddonsMotor,
  setSendQuotes,
  setSendPdf,
  sendQuotes,
  addonDiscountPercentage,
  uwLoading,
  setPrevPopupTp,
  setQuoteData,
  otherDiscounts,
  displayAddress,
  claimList,
  setZdlp,
  zdlp,
  claimList_gdd,
  setZdlp_gdd,
  zdlp_gdd,
  setApplicableAddonsLits,
  setSelectedIcId,
  setSelectedCompanyAlias,
  extraLoading,
  setSelectedGarage,
  setOpenGarageModal,
}) => {
  const lessthan1350 = useMediaPredicate("(max-width: 1350px)");
  const lessthan1120 = useMediaPredicate("(max-width: 1120px)");
  const lessthan993 = useMediaPredicate("(max-width: 993px)");
  const moreThan993 = useMediaPredicate("(min-width: 993px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan1300 = useMediaPredicate("(max-width: 1300px)");
  const dispatch = useDispatch();
  const history = useHistory();
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const {
    addOnsAndOthers,
    saveQuoteResponse,
    saveQuoteLoader,
    updateQuoteLoader,
    garage,
    loader,
    shortTerm,
    selectedTab,
  } = useSelector((state) => state.quotes);
  const enquiry_id = query.get("enquiry_id");
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const typeId = query.get("typeid");
  const journey_type = query.get("journey_type");
  const shared = query.get("shared");
  const _stToken = fetchToken();
  const { temp_data, prefill, gstStatus, isRedirectionDone, theme_conf } =
    useSelector((state) => state.home);
  const { tempData } = useSelector((state) => state.quoteFilter);
  const [key, setKey] = useState(selectedKnow);
  const { register, errors, watch, setValue } = useForm({});

  //getting screenwidth
  const [width, setWidth] = useState(window.innerWidth);
  const updateWidth = () => {
    setWidth(window.innerWidth);
  };
  useEffect(() => {
    window.addEventListener("resize", updateWidth);
    return () => window.removeEventListener("resize", updateWidth);
  });

  // ----------------others addon for privare car---------------------
  let others = quote?.addOnsData?.other
    ? Object.keys(
        quote?.company_alias === "royal_sundaram" &&
          !addOnsAndOthers?.selectedAddons.includes("returnToInvoice")
          ? _.omit(quote?.addOnsData?.other, [
              "fullInvoicePrice",
              "fullInvoicePriceInsuranceCost",
              "fullInvoicePriceRegCharges",
              "fullInvoicePriceRoadtax",
            ])
          : quote?.addOnsData?.other
      )
    : [];

  let othersList = quote?.addOnsData?.other;

  const isBreakupShareable =
    theme_conf?.broker_config?.broker_asset?.communication_configuration
      ?.premium_breakup;

  // ----------------expiry check---------------------
  const [daysToExpiry, setDaysToExpiry] = useState(false);

  useEffect(() => {
    let a = temp_data?.expiry;
    let b = moment().format("DD-MM-YYYY");
    let diffDays = a && b && differenceInDays(toDate(b), toDate(a));
    setDaysToExpiry(diffDays);

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.expiry]);

  // ----------------prev ic status ---------------------
  const [prevIcData, setPrevIcData] = useState(false);
  useEffect(() => {
    if (
      temp_data?.prevIc &&
      temp_data?.prevIc !== "Not selected" &&
      (temp_data?.corporateVehiclesQuoteRequest?.isPopupShown === "Y"  || temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") 

      // &&
      // theme_conf?.broker_config?.ncbconfig === "No"
    ) {
      setPrevIcData(true);
    } else {
      setPrevIcData(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.prevIc]);

  //-------------buy now conditions checking----------------
  const handleClick = async () => {
    dispatch(CancelAll(true));
    onClose(false);
    //Analytics | Buy now proceeded.
    _saveQuoteTracking(
      quote,
      temp_data,
      applicableAddons,
      type,
      finalPremium,
      tempData
    );
    //fixed the issue of premium breakup button not button working
    // if (
    //   ((quote?.policyType === "Third Party" &&
    //     import.meta.env?.VITE_BROKER === "GRAM") ||
    //     tempData?.policyType === "Third-party") &&
    //   !prevIcData &&
    //   !temp_data?.fastlaneNcbPopup &&
    //   !temp_data?.newCar &&
    //   daysToExpiry <= 90
    // ) {
    //   setQuoteData({
    //     enquiryId: enquiry_id || temp_data?.enquiry_id,
    //     ...(!_.isEmpty(temp_data?.agentDetails) &&
    //       !_.isEmpty(
    //         temp_data?.agentDetails?.filter(
    //           (x) => x?.sellerType === "P" || x?.sellerType === "E"
    //         )
    //       ) && {
    //         agentDetails: temp_data?.agentDetails?.filter(
    //           (x) => x?.sellerType === "P" || x?.sellerType === "E"
    //         )[0],
    //       }),
    //     icId: quote?.companyId,
    //     icAlias: quote?.companyName,
    //     productSubTypeId: quote?.productSubTypeId,
    //     masterPolicyId: quote?.masterPolicyId?.policyId,
    //     premiumJson: {
    //       ...quote,
    //       deductionOfNcb: revisedNcb,
    //       ...(temp_data?.odOnly && { IsOdBundledPolicy: "Y" }),
    //       ...(quote?.companyAlias === "royal_sundaram" &&
    //         quote?.isRenewal !== "Y" && {
    //           icAddress: displayAddress,
    //           addOnsData: {
    //             ...quote?.addOnsData,
    //             ...(!_.isEmpty(quote?.addOnsData?.additional) && {
    //               additional: Object.fromEntries(
    //                 Object.entries(quote?.addOnsData?.additional).map(
    //                   ([k, v]) => [
    //                     k,
    //                     _discount(
    //                       v,
    //                       addonDiscountPercentage,
    //                       quote?.companyAlias,
    //                       k
    //                     ),
    //                   ]
    //                 )
    //               ),
    //             }),
    //             ...(!_.isEmpty(quote?.addOnsData?.inBuilt) && {
    //               inBuilt: Object.fromEntries(
    //                 Object.entries(quote?.addOnsData?.inBuilt).map(([k, v]) => [
    //                   k,
    //                   _discount(
    //                     v,
    //                     addonDiscountPercentage,
    //                     quote?.companyAlias,
    //                     k
    //                   ),
    //                 ])
    //               ),
    //             }),
    //           },
    //         }),
    //       ...(quote?.companyAlias === "sbi" &&
    //         addOnsAndOthers?.selectedCpa?.includes(
    //           "Compulsory Personal Accident"
    //         ) &&
    //         !_.isEmpty(addOnsAndOthers?.isTenure) &&
    //         quote?.coverUnnamedPassengerValue * 1 && {
    //           coverUnnamedPassengerValue:
    //             quote?.coverUnnamedPassengerValue *
    //             (TypeReturn(type) === "bike" ? 5 : 3),
    //         }),
    //       ...(quote?.companyAlias === "sbi" &&
    //         addOnsAndOthers?.selectedCpa?.includes(
    //           "Compulsory Personal Accident"
    //         ) &&
    //         !_.isEmpty(addOnsAndOthers?.isTenure) &&
    //         quote?.motorAdditionalPaidDriver * 1 && {
    //           motorAdditionalPaidDriver:
    //             quote?.motorAdditionalPaidDriver *
    //             (TypeReturn(type) === "bike" ? 5 : 3),
    //         }),
    //     },
    //     exShowroomPriceIdv: quote?.idv,
    //     exShowroomPrice: quote?.showroomPrice,
    //     finalPremiumAmount: finalPremium,
    //     odPremium: quote?.finalOdPremium * 1,
    //     tpPremium: totalPremiumB,
    //     addonPremiumTotal: totalAddon,
    //     serviceTax: gst,
    //     revisedNcb: revisedNcb,
    //     applicableAddons:
    //       quote?.companyAlias === "royal_sundaram" && quote?.isRenewal !== "Y"
    //         ? !_.isEmpty(applicableAddons)
    //           ? applicableAddons?.map((el) => ({
    //               ...el,
    //               ...{
    //                 premium: _discount(
    //                   el?.premium,
    //                   addonDiscountPercentage,
    //                   quote?.companyAlias,
    //                   el?.name
    //                 ),
    //               },
    //             }))
    //           : []
    //         : applicableAddons,
    //     prevInsName: prevInsName[0]?.previousInsurer,
    //   });
    //   setPrevPopupTp(true);
    // } else
    if (
      !temp_data?.newCar &&
      !prevIcData &&
      !popupCard &&
      tempData?.policyType !== "Third-party" &&
      (quote?.policyType === "Comprehensive" ||
        quote?.policyType === "Short Term" ||
        quote?.policyType === "Own Damage") &&
      daysToExpiry < 90
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

          var data1 = {
            enquiryId: enquiry_id || temp_data?.enquiry_id,

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
        dispatch(
          SaveAddonsData({
            enquiryId: enquiry_id || temp_data?.enquiry_id,
            addonData: {
              addons: addonLists,
              compulsory_personal_accident:
                addOnsAndOthers?.selectedCpa?.includes(
                  "Compulsory Personal Accident"
                )
                  ? [
                      {
                        name: "Compulsory Personal Accident",
                        ...(!_.isEmpty(
                          _.compact(addOnsAndOthers?.isTenure)
                        ) && {
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
          })
        );
      }
      dispatch(
        SaveQuotesData({
          enquiryId: enquiry_id || temp_data?.enquiry_id,
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
                      Object.entries(quote?.addOnsData?.inBuilt).map(
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
          odPremium: quote?.finalOdPremium * 1,
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
        })
      );
    }
  };

  //-----proceeding to proposal after buy now api success---------

  useEffect(() => {
    if (saveQuoteResponse && !updateQuoteLoader) {
      history.push(
        `/${type}/proposal-page?enquiry_id=${enquiry_id}${
          token ? `&xutm=${token}` : ``
        }${typeId ? `&typeid=${typeId}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${_stToken ? `&stToken=${_stToken}` : ``}
        ${shared ? `&shared=${shared}` : ``}`
      );
      dispatch(saveSelectedQuoteResponse(false));
      dispatch(setQuotesList([]));
      dispatch(clear());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [saveQuoteResponse, updateQuoteLoader]);

  //---------------pincide api calling cashless garage with debounce------------------
  const pincode = watch("pincode");

  const cashelssCity = (pincode) => {
    if (pincode?.length === 6 && pincode * 1) {
      dispatch(
        GarageList({
          pincode: pincode,
          company_alias: quote?.company_alias,
          enquiryId: enquiry_id,
        })
      );
    } else {
      dispatch(
        GarageList({
          company_alias: quote?.company_alias,
          searchString: pincode,
          city_name: pincode,
          pincode: pincode,
          enquiryId: enquiry_id,
        })
      );
    }
  };

  const debouncedChangeHandler = useCallback(debounce(cashelssCity, 1500), []);
  useEffect(() => {
    if (pincode?.length >= 1) {
      debouncedChangeHandler(pincode);
    } else {
      dispatch(setGarage([]));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pincode]);

  //------------------clearing cashless garage data----------------------
  const clearAllGarage = () => {
    setValue("pincode", null);
    setValue("cityName", "");
    dispatch(setGarage([]));
  };

  //------------------handling pincode search----------------------

  const [showInput, setShowInput] = useState(false);

  const llpaidCon =
    quote?.llPaidDriverPremium * 1 ||
    quote?.llPaidConductorPremium * 1 ||
    quote?.llPaidCleanerPremium * 1;

  const dropRef = useRef(null);
  useOutsideClick(dropRef, () => setShowInput(false));

  // prettier-ignore
  const premiumPdfProps = {
    prefill, type, totalApplicableAddonsMotor, addonDiscountPercentage, quote, addOnsAndOthers, others,
    othersList, temp_data, llpaidCon, revisedNcb, otherDiscounts, tempData, totalPremium, totalPremiumA,
    totalPremiumB, totalPremiumC, totalAddon, finalPremium, gst, selectedTab, shortTerm, Theme,
    dispatch,  setSendPdf, setSendQuotes, enquiry_id, gstStatus, extraLoading, theme_conf
  }

  const content = (
    <>
      <Style.ContentWrap>
        <Row>
          {
            !lessthan993 && 
              <Col lg={3} md={12}>
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                borderBottom: "1px solid rgb(206, 206, 206)",
                height: "50px",
                position: "sticky",
                top: "0",
                zIndex: "9999",
                background: "white",
              }}
            >
              <div
                style={{ width: "30%", display: "flex", alignItems: "center" }}
              >
                <img
                  src={quote?.companyLogo ? quote?.companyLogo : ""}
                  alt=""
                  className="PremIcon"
                  id="premium_breakup_ic_img"
                  style={{ height: "auto", width: "100%" }}
                />
              </div>
              <div
                style={{ width: "65%", display: "flex", alignItems: "center" }}
              >
                <span style={{ fontWeight: "800" }}>{quote?.companyName}</span>{" "}
              </div>
            </div>
            <div
              style={{
                display: "flex",
                justifyContent: "center",
                flexDirection: "column",
                margin: "20px 0px",
              }}
            >
              <Col md={12} sm={12}>
                <Table.IcTable
                  quote={quote}
                  temp_data={temp_data}
                  type={type}
                  prefill={prefill}
                />
              </Col>
              <Col md={12} sm={12}>
                <Table.VehicleTable
                  quote={quote}
                  temp_data={temp_data}
                  tempData={tempData}
                />
              </Col>
              <Col md={12} sm={12}>
                <Table.NewNcbTable
                  quote={quote}
                  temp_data={temp_data}
                  tempData={tempData}
                />
              </Col>

              <BuyNowBtn
                handleClick={() =>
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
                finalPremium={finalPremium}
                gstStatus={gstStatus}
              />
            </div>
          </Col>
            
          }
          <Col lg={9} md={12}>
            <Style.DetailPopTabs>
              <ul
                className="nav nav-tabs"
                style={{
                  display: lessthan993 ? "none" : "flex",
                  position: "sticky",
                  top: 0,
                  background: "#fff",
                  zIndex: 1,
                  height: "50px",
                  paddingBottom: "50px",
                }}
              >
                <li
                  className={key === "premiumBreakupPop" ? "active" : ""}
                  style={{ cursor: "pointer", width: "200px", height: "100%" }}
                >
                  <a
                    data-toggle="tab"
                    aria-expanded="true"
                    onClick={() => setKey("premiumBreakupPop")}
                    style={{ height: "50px" , display: "flex", alignItems: "center", justifyContent: "center" }}
                  >
                    Premium Breakup
                  </a>
                </li>
                <li
                  className={key === "cashlessGaragesPop" ? "active" : ""}
                  style={{
                    borderRight: "1px solid black",
                    color: "blue",
                    cursor: "pointer",
                    visibility: !quote?.garageCount && "hidden",
                    width: "200px",
                    height: "100%",
                  }}
                >
                  <a
                    data-toggle="tab"
                    aria-expanded="false"
                    style={{ height: "50px" , display: "flex", alignItems: "center", justifyContent: "center"}}
                    onClick={() =>
                      (import.meta.env?.VITE_BROKER !== "ABIBL" ||
                        import.meta.env?.VITE_API_BASE_URL ===
                          "https://api-carbike.fynity.in/api") &&
                      setKey("cashlessGaragesPop")
                    }
                  >
                    Cashless Garages
                  </a>
                </li>
                <Style.PdfMail hide={key === "cashlessGaragesPop"}>
                  {!lessthan1120 ? (
                    // eslint-disable-next-line jsx-a11y/anchor-is-valid
                    <a
                      role="button"
                      onClick={() => handlePremPdfClick(premiumPdfProps)}
                      id="export_pdf_breakup"
                    >
                      <i
                        className="fa fa-file-pdf-o"
                        style={{ fontSize: "16px" }}
                        aria-hidden="true"
                      ></i>
                      &nbsp; PDF
                    </a>
                  ) : (
                    // eslint-disable-next-line jsx-a11y/anchor-is-valid
                    <a
                      role="button"
                      onClick={() => handlePremPdfClick(premiumPdfProps)}
                      id="export_pdf_breakup"
                    >
                      <i
                        className="fa fa-file-pdf-o"
                        style={{ fontSize: "20px" }}
                        aria-hidden="true"
                      ></i>
                    </a>
                  )}
                </Style.PdfMail>

                {import.meta.env.VITE_BROKER !== "PAYTM" &&
                  (isBreakupShareable?.email ||
                    isBreakupShareable?.whatsapp_api) && (
                    <Style.PdfMail hide={key === "cashlessGaragesPop"}>
                      {!lessthan1120 ? (
                        <button
                          style={{
                            all: "unset",
                            cursor: "pointer",
                          }}
                          onClick={() => handleEmailClick(premiumPdfProps)}
                          id="export_pdf_breakup"
                        >
                          <i
                            className="fa fa-share-alt"
                            style={{ fontSize: "16px" }}
                            aria-hidden="true"
                          ></i>
                          &nbsp; SHARE&nbsp;&nbsp;
                        </button>
                      ) : (
                        <button
                          style={{
                            all: "unset",
                            cursor: "pointer",
                          }}
                          onClick={() => handleEmailClick(premiumPdfProps)}
                          id="export_pdf_breakup"
                        >
                          <i
                            className="fa fa-share-alt"
                            style={{ fontSize: "15px" }}
                            aria-hidden="true"
                          ></i>
                        </button>
                      )}
                    </Style.PdfMail>
                  )}
                {/* <button
                  onClick={onClose} // replace with your actual close function
                  style={{
                    position: "absolute",
                    top: "9px",
                    right: "10px",
                    zIndex: 10,
                    background: "transparent",
                    border: "none",
                    fontSize: "18px",
                    cursor: "pointer",
                    color: "white",
                    border: "1px solid black",
                    backgroundColor: "black",
                    borderRadius: "50%",
                  }}
                  aria-label="Close"
                >
                  &times;
                </button> */}
              </ul>
              <Style.TabContet>
                <div
                  className={
                    key === "premiumBreakupPop"
                      ? "showDiv premBreakup"
                      : "hideDiv"
                  }
                >
                  <MobilePremiumBreakup
                    quote={quote}
                    type={TypeReturn(type)}
                    totalPremiumA={totalPremiumA}
                    totalPremiumB={totalPremiumB}
                    revisedNcb={revisedNcb}
                    totalPremiumC={totalPremiumC}
                    totalAddon={totalAddon}
                    GetAddonValue={GetAddonValue}
                    getAddonName={getAddonName}
                    totalApplicableAddonsMotor={totalApplicableAddonsMotor}
                    addonDiscountPercentage={addonDiscountPercentage}
                    others={others}
                    othersList={othersList}
                    totalPremium={totalPremium}
                    gst={gst}
                    finalPremium={finalPremium}
                    handleClick={handleClick}
                    otherDiscounts={otherDiscounts}
                    show={show}
                    lessthan767={lessthan767}
                    setZdlp={setZdlp}
                    zdlp={zdlp}
                    claimList={claimList}
                    setZdlp_gdd={setZdlp_gdd}
                    zdlp_gdd={zdlp_gdd}
                    claimList_gdd={claimList_gdd}
                    lessthan993={lessthan993}
                    llpaidCon={llpaidCon}
                    tempData={tempData}
                    selectedTab={selectedTab}
                    setSendPdf={setSendPdf}
                    setSendQuotes={setSendQuotes}
                    shortTerm={shortTerm}
                    Theme={Theme}
                    dispatch={dispatch}
                    prefill={prefill}
                    extraLoading={extraLoading}
                  />
                  <PremiumBody
                    lessthan993={lessthan993}
                    quote={quote}
                    temp_data={temp_data}
                    type={type}
                    prefill={prefill}
                    tempData={tempData}
                    addOnsAndOthers={addOnsAndOthers}
                    totalPremiumA={totalPremiumA}
                    totalPremiumB={totalPremiumB}
                    totalPremiumC={totalPremiumC}
                    llpaidCon={llpaidCon}
                    others={others}
                    otherDiscounts={otherDiscounts}
                    totalApplicableAddonsMotor={totalApplicableAddonsMotor}
                    addonDiscountPercentage={addonDiscountPercentage}
                    othersList={othersList}
                    revisedNcb={revisedNcb}
                    totalPremium={totalPremium}
                    totalAddon={totalAddon}
                    gst={gst}
                    uwLoading={uwLoading}
                    finalPremium={finalPremium}
                    extraLoading={extraLoading}
                  />
                </div>
                <div
                  className={
                    key === "premiumBreakupPop" ? "hideDiv" : "showDiv"
                  }
                >
                  <CashlessBody
                    lessthan993={lessthan993}
                    register={register}
                    temp_data={temp_data}
                    prefill={prefill}
                    errors={errors}
                    garage={garage}
                    clearAllGarage={clearAllGarage}
                    loader={loader}
                    setSendQuotes={setSendQuotes}
                    setSelectedGarage={setSelectedGarage}
                    setOpenGarageModal={setOpenGarageModal}
                    companyAlias={quote?.companyAlias}
                  />
                </div>
              </Style.TabContet>
            </Style.DetailPopTabs>
          </Col>
        </Row>
      </Style.ContentWrap>
      {saveQuoteLoader && <Loader />}
    </>
  );
  return (
    <Popup
      height={lessthan993 ? "100%" : width < 1300 ? "99vh" : "99vh"}
      width={width < 992 ? "100%" : lessthan1350 ? "100%" : "100vw"}
      show={show}
      onClose={onClose}
      content={content}
      position={"middle"}
      overflowX={true}
      outside={sendQuotes ? true : false}
    />
  );
};

// PropTypes
KnowMorePopup.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
  quote: PropTypes.object,
  selectedKnow: PropTypes.func,
  totalAddon: PropTypes.number,
  finalPremium: PropTypes.number,
  totalPremium: PropTypes.number,
  gst: PropTypes.number,
  totalPremiumA: PropTypes.number,
  totalPremiumB: PropTypes.number,
  applicableAddons: PropTypes.number,
  type: PropTypes.string,
  prevInsName: PropTypes.string,
  totalPremiumC: PropTypes.number,
  revisedNcb: PropTypes.number,
  popupCard: PropTypes.bool,
  setPrevPopup: PropTypes.func,
  prevPopup: PropTypes.bool,
  setSelectedId: PropTypes.func,
  setSelectedCompanyName: PropTypes.func,
  totalApplicableAddonsMotor: PropTypes.number,
  setSendQuotes: PropTypes.func,
  setSendPdf: PropTypes.func,
  sendQuotes: PropTypes.func,
  addonDiscountPercentage: PropTypes.number,
  uwLoading: PropTypes.number,
  setPrevPopupTp: PropTypes.func,
  setQuoteData: PropTypes.func,
  otherDiscounts: PropTypes.number,
  displayAddress: PropTypes.string,
  claimList: PropTypes.array,
  setZdlp: PropTypes.func,
  zdlp: PropTypes.string,
  claimList_gdd: PropTypes.string,
  setZdlp_gdd: PropTypes.func,
  zdlp_gdd: PropTypes.string,
  setApplicableAddonsLits: PropTypes.func,
  setSelectedIcId: PropTypes.func,
  setSelectedCompanyAlias: PropTypes.func,
};

export default KnowMorePopup;


// const PaymentTermOverlayClose = styled.div`
//   display: ${({ hiddenClose }) => (hiddenClose ? "none" : "flex")};
//   justify-content: flex-end;
//   position: absolute;
//   top: 10px;
//   right: 10px;
//   cursor: pointer;
//   z-index: 1111;
//   &:hover {
//     text-decoration: none;
//     color: rgb(230, 0, 0);
//   }
// `;