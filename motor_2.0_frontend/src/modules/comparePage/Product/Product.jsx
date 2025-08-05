import React, { useEffect, useState } from "react";
import { useHistory } from "react-router-dom";
import _ from "lodash";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useDispatch, useSelector } from "react-redux";
import { useLocation } from "react-router";
import { Loader } from "components";
import "./product.scss";
import { set_temp_data } from "modules/Home/home.slice";
import { currencyFormater, fetchToken } from "utils";
import {
  setSelectedQuote,
  SaveQuotesData,
  clear,
  setQuotesList,
  saveSelectedQuoteResponse,
  SaveAddonsData,
  compareQuotes,
  setShowPop,
} from "../../quotesPage/quote.slice";
import { differenceInDays } from "date-fns";
import moment from "moment";
import { toDate } from "utils";
import { useMediaPredicate } from "react-media-hook";
import CloseSharpIcon from "@material-ui/icons/CloseSharp";
import { setTempData } from "../../quotesPage/filterConatiner/quoteFilter.slice";
import { TypeReturn } from "modules/type";
import {
  AddPlanIcon,
  CloseContainer,
  DataCard,
  FoldedRibbon,
  RecPlanBuyBtn,
  TableWrapper,
  TopDiv,
  TopInfo,
  TopLi,
} from "./ProductStyle";
import Badges from "./Badges";
import { BlockedSections } from "modules/quotesPage/addOnCard/cardConfig";
import { UspTable, UspTable1 } from "./Tables/UspTable";
import { PremiumTable, PremiumTable1 } from "./Tables/PremiumTable";
import { AddonTable, AddonTable1 } from "./Tables/AddonTable";
import { AccessoriesTable, AccessoriesTable1 } from "./Tables/AccessoriesTable";
import {
  AdditionalCoverTable,
  AdditionalCoverTable1,
} from "./Tables/AdditionalCoverTable";
import { DiscountTable, DiscountTable1 } from "./Tables/DiscountTable";
import { ImCross } from "react-icons/im";
import { _buyNow } from "modules/quotesPage/quoteCard/card-logic";
import { OtherCoversTable, OtherCoversTable1 } from "./Tables/OtherCoversTable";
import { _discount } from "modules/quotesPage/quote-logic";
import { getAddonName } from "modules/quotesPage/quoteUtil";

function Product({
  quote,
  length,
  type,
  setPrevPopup,
  prevPopup,
  setSelectedId,
  setSelectedCompanyName,
  validQuote,
  popupCard,
  setSelectedIcId,
  setSelectedCompanyAlias,
  setApplicableAddonsLits,
  scrollPosition,
  index,
}) {
  const ls = new SecureLS();
  const ThemeLS = ls.get("themeData");
  const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;
  const dispatch = useDispatch();
  const history = useHistory();
  const location = useLocation();
  const _stToken = fetchToken();
  const query = new URLSearchParams(location.search);
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const { temp_data, isRedirectionDone, theme_conf } = useSelector(
    (state) => state.home
  );
  const { shortTerm } = useSelector((state) => state.quotes);
  const typeId = query.get("typeid");
  const enquiry_id = query.get("enquiry_id");
  const shared = query.get("shared");
  const { prevInsList, tempData } = useSelector((state) => state.quoteFilter);
  const {
    addOnsAndOthers,
    saveQuoteResponse,
    saveQuoteLoader,
    compareQuotesList,
    updateQuoteLoader,
    quoteComprehesive,
  } = useSelector((state) => state.quotes);

  const GetAddonValue = (addonName, addonDiscountPercentage) => {
    let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
    let additional = Object.keys(quote?.addOnsData?.additional);
    let selectedAddons = addOnsAndOthers?.selectedAddons;
    if (inbuilt?.includes(addonName)) {
      return (
        <span className="addonValueText">
          {Number(quote?.addOnsData?.inBuilt[addonName]) !== 0 ? (
            `₹ ${currencyFormater(
              parseInt(
                _discount(
                  quote?.addOnsData?.inBuilt[addonName],
                  addonDiscountPercentage,
                  quote?.companyAlias,
                  addonName
                )
              )
            )}`
          ) : (
            <>
              {addonName === "roadSideAssistance" &&
              quote?.company_alias === "reliance" ? (
                <>-</>
              ) : (
                <Badges title={"Included"} />
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
      return `₹ ${currencyFormater(
        parseInt(
          _discount(
            quote?.addOnsData?.additional[addonName],
            addonDiscountPercentage,
            quote?.companyAlias,
            addonName
          )
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

  const [daysToExpiry, setDaysToExpiry] = useState(false);

  useEffect(() => {
    let a = temp_data?.expiry;
    let b = moment().format("DD-MM-YYYY");
    let diffDays = a && b && differenceInDays(toDate(b), toDate(a));
    setDaysToExpiry(diffDays);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.expiry]);

  let prevInsName = prevInsList.filter((i) => i.tataAig === temp_data?.prevIc);

  const [prevIcData, setPrevIcData] = useState(false);

  //---------------------applicable addons-------------------------
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
              ...(el === "zeroDepreciation" 
                && quote?.companyAlias === "godigit" && {claimCovered : addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
                (x) => x?.name === "Zero Depreciation"
              )?.[0]?.claimCovered}),
            };
            addonsSelectedList.push(newList);
          }
        });

        inbuilt.forEach((el) => {
          var newList = {
            name: getAddonName(el),
            premium: Number(inbuiltList[el]),
            ...(el === "zeroDepreciation" 
              && quote?.companyAlias === "godigit" && {claimCovered : addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
              (x) => x?.name === "Zero Depreciation"
            )?.[0]?.claimCovered}),
          };
          addonsSelectedList.push(newList);
        });

        setApplicableAddons(addonsSelectedList);
      } else {
        setApplicableAddons([]);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [addOnsAndOthers?.selectedAddons, quote]);
  //----------------addonTotalCaluclatuion-----------------------

  useEffect(() => {
    if (temp_data?.prevIc && temp_data?.prevIc !== "Not selected") {
      setPrevIcData(true);
    } else {
      setPrevIcData(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.prevIc]);
  const handleClick = async () => {
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
      // if (!temp_data?.newCar) {
      setPrevPopup(true);
      setSelectedId(quote?.policyId);
      setSelectedCompanyName(quote?.companyName);
      setSelectedCompanyAlias(quote?.company_alias);
      setApplicableAddonsLits(
        !_.isEmpty(quote?.applicableAddons1) &&
          quote?.applicableAddons1.map((x) => x.name)
      );
      setSelectedIcId(quote?.companyId);
      dispatch(
        setTempData({
          oldPremium: quote?.finalPremium1,
        })
      );
    }
    //		setOnSubmit(true);
    else if (
      !prevPopup &&
      (temp_data?.newCar ||
        prevIcData ||
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
        if (!temp_data?.newCar) {
          dispatch(
            set_temp_data({
              //	ncb: "0%",
              //	newNcb: "0%",
              //	tab: "tab2",
              prevIc: "others",
              prevIcFullName: "others",
            })
          );
        }

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
        var data1 = {
          enquiryId: temp_data?.enquiry_id || enquiry_id,

          addonData: {
            addons: null,
            accessories: newSelectedAccesories,
            discounts: null,
          },
        };

        dispatch(SaveAddonsData(data1));
      } else {
        let addonLists = [];
        let addonListRedux = addOnsAndOthers?.selectedAddons || [];

        addonListRedux.forEach((el) => {
          let data;
          if(el === "additionalTowing"){
            data = {
              name: getAddonName(el),
              sumInsured: !_.isEmpty(
                addOnsAndOthers?.dbStructure?.addonData?.addons
              )
                ? addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
                    (x) => x?.name === "Additional Towing"
                  )?.[0]?.sumInsured
                : "10000",
            }
          } 
          else if(el === "zeroDepreciation") {
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
          } 
          else {
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
                ? [{ name: "Compulsory Personal Accident" }]
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
          deductionOfNcb: quote?.revisedNcb1,
          ...(temp_data?.odOnly && { IsOdBundledPolicy: "Y" }),
          ...(quote?.companyAlias === "royal_sundaram" &&
            quote?.isRenewal !== "Y" && {
              addOnsData: {
                ...quote?.addOnsData,
                ...(!_.isEmpty(quote?.addOnsData?.additional) && {
                  additional: Object.fromEntries(
                    Object.entries(quote?.addOnsData?.additional).map(
                      ([k, v]) => [
                        k,
                        _discount(
                          v,
                          quote?.addonDiscountPercentage1,
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
                        quote?.addonDiscountPercentage1,
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
        finalPremiumAmount: quote?.finalPremium1,
        odPremium: quote?.finalOdPremium * 1,
        tpPremium: quote?.totalPremiumB1,
        addonPremiumTotal: quote?.totalAddon1,
        revisedNcb: quote?.revisedNcb1,
        serviceTax: quote?.gst1,
        applicableAddons:
          quote?.companyAlias === "royal_sundaram" && quote?.isRenewal !== "Y"
            ? !_.isEmpty(applicableAddons)
              ? applicableAddons?.map((el) => ({
                  ...el,
                  ...{
                    premium: _discount(
                      el?.premium,
                      quote?.addonDiscountPercentage1,
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

  useEffect(() => {
    if (saveQuoteResponse && !updateQuoteLoader) {
      history.push(
        `/${type}/proposal-page?enquiry_id=${enquiry_id}${
          token ? `&xutm=${token}` : ``
        }${typeId ? `&typeid=${typeId}` : ``}${
          _stToken ? `&stToken=${_stToken}` : ``
        }${shared ? `&shared=${shared}` : ``}`
      );
      dispatch(saveSelectedQuoteResponse(false));
      dispatch(setQuotesList([]));
      dispatch(clear());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [saveQuoteResponse, updateQuoteLoader]);

  const lessThan768 = useMediaPredicate("(max-width: 768px)");

  const YOffSet = window.pageYOffset;
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    if (YOffSet > 50) {
      setVisible(true);
    } else {
      setVisible(false);
    }
  }, [YOffSet]);

  const openPop = () => {
    dispatch(setShowPop(true));
  };

  const removeFn = (singleQuote) => {
    let allQuotes = compareQuotesList?.filter(
      (x) => String(x.policyId) !== String(singleQuote.policyId)
    );
    dispatch(compareQuotes(allQuotes));
  };

  return (
    <>
      <TopDiv>
        <TopLi
          className={`compare-page-product compare-page-container ${
            length < 3 ? `compare-page-product--${length}` : ""
          } `}
        >
          {lessThan768 && visible && (
            <div
              className="myDiv"
              style={{
                position: "fixed",
                height: lessThan768 ? "165px": "190px",
                width: "100%",
                background: "#fff",
                zIndex: "999",
                top: "0",
                right: "0",
                left: "0",
              }}
            ></div>
          )}
          {quote?.idv ? (
            <TopInfo
              isRenewal={quote?.isRenewal === "Y" && !popupCard}
              className={`top-info ${
                lessThan768 && visible && "mobile-top-info"
              }`}
              fixed={
                lessThan768
                  ? false
                  : scrollPosition >
                    (Theme?.QuoteBorderAndFont?.scrollHeight
                      ? Theme?.QuoteBorderAndFont?.scrollHeight
                      : 68)
                  ? true
                  : false
              }
            >
              {!popupCard && quote?.isRenewal === "Y" ? (
                <FoldedRibbon>Renewal Quote</FoldedRibbon>
              ) : (
                <noscript />
              )}
              {quote?.ribbon ? (
                <FoldedRibbon>{quote?.ribbon}</FoldedRibbon>
              ) : (
                <noscript />
              )}
              {!popupCard && quote?.gdd === "Y" ? (
                <FoldedRibbon handleSize={quote?.gdd === "Y"}>
                  Pay as you drive
                </FoldedRibbon>
              ) : (
                <noscript />
              )}
              {quote?.idv &&
                validQuote.length > 1 &&
                !_.isEmpty(quoteComprehesive) && (
                  <CloseContainer
                    leftIcon={
                      (!popupCard && quote?.isRenewal === "Y") ||
                      quote?.ribbon ||
                      (!popupCard && quote?.gdd === "Y")
                    }
                  >
                    <CloseSharpIcon
                      onClick={() => removeFn(quote)}
                      style={{
                        position: "absolute",
                        top: lessThan768 ? "-8px" : "3px",
                        right: lessThan768 ? "-10px" : "3px",
                        fontSize: lessThan768 ? "18px" : "24px",
                        background: "#fff",
                        borderRadius: "50%",

                        padding: "3px",
                        boxShadow: "rgba(99, 99, 99, 0.2) 0px 2px 8px 0px",
                        cursor: `${validQuote?.length > 1 ? "pointer" : ""}`,
                        color: `${validQuote?.length > 1 ? "black" : "grey"}`,
                      }}
                    />
                  </CloseContainer>
                )}
              <div className="compare-page-product__logo-wrap">
                <img src={quote?.companyLogo} alt="plan" />
              </div>
              <p>
                <br />
                <strong>
                  <span style={{ fontSize: "14px" }}> IDV ₹</span>{" "}
                  {currencyFormater(quote?.idv)}
                </strong>
              </p>
              <div className="planAmt"></div>
              <div className="buy_now_div" translate="no">
                <RecPlanBuyBtn
                  className="recPlanBuyBtn"
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
                      handleClick
                    )
                  }
                >
                  <small className="withGstText">incl. GST</small>
                  {!lessThan768 && (
                    <span style={{ fontSize: "10px" }}>BUY NOW</span>
                  )}{" "}
                  ₹ {currencyFormater(quote?.finalPremium1)}
                </RecPlanBuyBtn>
              </div>
            </TopInfo>
          ) : (
            <TopInfo
              className={`top-info ${
                lessThan768 && visible && "mobile-top-info"
              }`}
              fixed={
                lessThan768
                  ? false
                  : scrollPosition >
                    (Theme?.QuoteBorderAndFont?.scrollHeight
                      ? Theme?.QuoteBorderAndFont?.scrollHeight
                      : 68)
                  ? true
                  : false
              }
            >
              <div
                style={{
                  height: "185px",
                  display: "flex",
                  flexDirection: "column",
                  justifyContent: "center",
                  alignItems: "center",
                }}
              >
                {!_.isEmpty(quoteComprehesive) ? (
                  <>
                    <AddPlanIcon
                      onClick={openPop}
                      className="fa fa-plus"
                      style={{
                        cursor: "pointer",
                        fontSize: lessThan768 ? "15px" : "25px",
                        width: lessThan768 ? "30px" : "50px",
                        height: lessThan768 ? "30px" : "50px",
                        borderRadius: "15%",
                        display: "flex",
                        justifyContent: "center",
                        alignItems: "center",
                      }}
                    ></AddPlanIcon>
                    <p
                      style={{
                        padding: "10px",
                        fontWeight: "bold",
                        fontSize: lessThan768 && "12px",
                      }}
                    >
                      Add Plans
                    </p>
                  </>
                ) : (
                  <>
                    <ImCross
                      style={{
                        cursor: "pointer",
                        fontSize: lessThan768 ? "15px" : "25px",
                        width: lessThan768 ? "30px" : "50px",
                        height: lessThan768 ? "30px" : "50px",
                        borderRadius: "15%",
                        display: "flex",
                        justifyContent: "center",
                        alignItems: "center",
                        color: "red",
                        padding: "5px",
                        marginTop: "30px",
                      }}
                    ></ImCross>
                    <p
                      style={{
                        padding: "10px",
                        fontWeight: "bold",
                        fontSize: lessThan768 && "12px",
                        marginTop: "1rem",
                      }}
                    >
                      NOT ALLOWED
                    </p>
                  </>
                )}
              </div>
            </TopInfo>
          )}
          <DataCard
            className={`${lessThan768 && visible && "mobile-data-card"}`}
            fixed={
              lessThan768
                ? false
                : scrollPosition >
                  (Theme?.QuoteBorderAndFont?.scrollHeight
                    ? Theme?.QuoteBorderAndFont?.scrollHeight
                    : 68)
                ? true
                : false
            }
          >
            {!quote?.companyName ? (
              <noscript />
            ) : quote?.idv ? (
              <TableWrapper>
                <UspTable quote={quote} />
                <PremiumTable quote={quote} />
                <AddonTable
                  quote={quote}
                  temp_data={temp_data}
                  addOnsAndOthers={addOnsAndOthers}
                  type={type}
                  GetAddonValue={GetAddonValue}
                />
                <AccessoriesTable
                  addOnsAndOthers={addOnsAndOthers}
                  quote={quote}
                  type={type}
                />
                <AdditionalCoverTable
                  addOnsAndOthers={addOnsAndOthers}
                  quote={quote}
                  type={type}
                  temp_data={temp_data}
                  shortTerm={shortTerm}
                />
                {temp_data?.ownerTypeId === 2 && (
                  <OtherCoversTable quote={quote} temp_data={temp_data} />
                )}
                {BlockedSections(
                  import.meta.env.VITE_BROKER,
                  temp_data?.journeyCategory
                )?.includes("unnamed pa cover") ? (
                  <noscript />
                ) : (
                  <DiscountTable
                    addOnsAndOthers={addOnsAndOthers}
                    quote={quote}
                    type={type}
                    temp_data={temp_data}
                  />
                )}
              </TableWrapper>
            ) : (
              <TableWrapper style={{ paddingTop: "202px" }}>
                <UspTable1 quote={quote} />
                <PremiumTable1 quote={quote} />
                <AddonTable1
                  quote={quote}
                  temp_data={temp_data}
                  addOnsAndOthers={addOnsAndOthers}
                  type={type}
                  GetAddonValue={GetAddonValue}
                />
                <AccessoriesTable1
                  addOnsAndOthers={addOnsAndOthers}
                  quote={quote}
                  type={type}
                />
                <AdditionalCoverTable1
                  addOnsAndOthers={addOnsAndOthers}
                  quote={quote}
                  type={type}
                  temp_data={temp_data}
                  shortTerm={shortTerm}
                />
                {temp_data?.ownerTypeId === 2 && (
                  <OtherCoversTable1 quote={quote} temp_data={temp_data} />
                )}
                {BlockedSections(
                  import.meta.env.VITE_BROKER,
                  temp_data?.journeyCategory
                )?.includes("unnamed pa cover") ? (
                  <noscript />
                ) : (
                  <DiscountTable1
                    addOnsAndOthers={addOnsAndOthers}
                    quote={quote}
                    type={type}
                    temp_data={temp_data}
                  />
                )}
              </TableWrapper>
            )}
          </DataCard>
        </TopLi>
      </TopDiv>
      {saveQuoteLoader && <Loader />}
    </>
  );
}

export default Product;
