/* eslint-disable react-hooks/exhaustive-deps */
import React, { useEffect, useState } from "react";
import CompareProductsList from "./CompareProductsList/CompareProductsList";
import Features from "./Features/Features";
import { subMonths } from "date-fns";
import { useHistory } from "react-router-dom";
import _ from "lodash";
import { BackButton, FloatButton, Loader } from "components";
import { useLocation } from "react-router";
import { useDispatch, useSelector } from "react-redux";
import {
  compareQuotes,
  getCompareData,
  setCompareData,
  setShowPop,
  UpdateQuotesData,
  MasterLogoList,
} from "../quotesPage/quote.slice";
import { PrevInsList as getPrevInsList } from "../quotesPage/filterConatiner/quoteFilter.slice";
import PrevInsurerPopup2 from "modules/quotesPage/quotesPopup/prevInsurerPopup/prevInsurerPopup2";
import moment from "moment";
import { useMediaPredicate } from "react-media-hook";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { downloadFile, fetchToken, _generateKey } from "utils";
import Popup from "components/Popup/Popup";
import { PdfDiv, TopDiv } from "./ComparePageStyle";
import Content from "./Content/Content";
import ContentModal from "./Content/ContentModal";
import { _comparePDFTracking } from "analytics/compare-page/compare-tracking";
import { TypeReturn } from "modules/type";
import swal from "sweetalert";

/*---------------date config----------------*/
const notSureDate = subMonths(new Date(Date.now()), 9);
const formatedDate = moment(notSureDate).format("DD-MM-YYYY");
/*-----x---------date config-----x----------*/

export const ComparePage = (props) => {
  const ls = new SecureLS();
  const ThemeLS = ls.get("themeData");
  const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;
  const { temp_data } = useSelector((state) => state.home);
  const { tempData, prevInsList } = useSelector((state) => state.quoteFilter);
  const { showPop, masterLogos } = useSelector((state) => state.quotes);
  const _stToken = fetchToken();
  const {
    compareQuotesList,
    addOnsAndOthers,
    quoteComprehesive: ComprehensiveQuotes,
    comparePdfData,
    shortTermType,
    error,
    loading,
    quoteFill,
  } = useSelector((state) => state.quotes);

  //check for prefilled data
  //toggling b/w comprehensive and short term
  const quoteCompFill = !_.isEmpty(
    shortTermType ? shortTermType : ComprehensiveQuotes
  )
    ? shortTermType
      ? shortTermType
      : ComprehensiveQuotes
    : quoteFill;
  const quoteComprehesive = !_.isEmpty(quoteCompFill)
    ? quoteCompFill
    : compareQuotesList;

  const dispatch = useDispatch();
  const location = useLocation();
  const history = useHistory();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const { type } = props?.match?.params;
  const typeId = query.get("typeid");
  const shared = query.get("shared");
  const [prevPopup2, setPrevPopup2] = useState(false);
  const lessThan768 = useMediaPredicate("(max-width: 768px)");
  const lessThan380 = useMediaPredicate("(max-width: 380px)");
  const [selectedId, setSelectedId] = useState(false);
  const [selectedCompanyName, setSelectedCompanyName] = useState(false);
  const [selectedCompanyAlias, setSelectedCompanyAlias] = useState(false);
  const [selectedIcId, setSelectedIcId] = useState(false);
  const [applicableAddonsLits, setApplicableAddonsLits] = useState(false);

  //scrolEvent
  const [scrollPosition, setScrollPosition] = useState(0);
  const handleScroll = () => {
    const position = window.pageYOffset;
    setScrollPosition(position);
  };

  useEffect(() => {
    window.addEventListener("scroll", handleScroll, { passive: true });
    return () => {
      window.removeEventListener("scroll", handleScroll);
    };
  }, []);

  //---------------------------getting prev ic api------------------------------------
  const [prevList, setPrevList] = useState(true);
  useEffect(() => {
    if (
      prevInsList?.length === 0 &&
      prevList &&
      location.pathname === `/${type}/compare-quote`
    ) {
      dispatch(getPrevInsList({ enquiryId: enquiry_id }));
      setPrevList(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [prevInsList]);

  //---------------------getLogoList-----------------------------

  const [masterLogo, setMasterLogo] = useState(false);
  useEffect(() => {
    if (
      !masterLogo &&
      masterLogos?.length === 0 &&
      location.pathname === `/${type}/compare-quote`
    ) {
      setMasterLogo(true);
      dispatch(MasterLogoList({ enquiryId: enquiry_id }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [masterLogos]);

  //updating prev insurer
  useEffect(() => {
    if (prevPopup2) {
      var data = {
        enquiryId: temp_data?.enquiry_id || enquiry_id,
        vehicleIdv: tempData.idvChoosed,
        idvChangedType: tempData?.idvType,
        vehicleElectricAccessories: Number(
          addOnsAndOthers?.vehicleElectricAccessories
        ),
        vehicleNonElectricAccessories: Number(
          addOnsAndOthers?.vehicleNonElectricAccessories
        ),
        externalBiFuelKit: Number(addOnsAndOthers?.externalBiFuelKit),
        OwnerDriverPaCover: addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        )
          ? "Y"
          : "N",
        antiTheft: addOnsAndOthers?.selectedDiscount?.includes(
          "Is the car fitted with ARAI approved anti-theft device?"
        )
          ? "Y"
          : "N",
        UnnamedPassengerPaCover: addOnsAndOthers?.selectedAdditions?.includes(
          "Unnamed Passenger PA Cover"
        )
          ? addOnsAndOthers?.unNamedCoverValue === "₹ 2 lac"
            ? 200000
            : 100000
          : null,

        voluntarydeductableAmount:
          addOnsAndOthers?.volDiscountValue !== "None" &&
          addOnsAndOthers?.selectedDiscount?.includes("Voluntary Discounts")
            ? addOnsAndOthers?.volDiscountValue
            : 0,
        isClaim: temp_data?.noClaimMade ? "N" : "Y",
        previousNcb: temp_data?.carOwnership
          ? temp_data?.ncb
          : temp_data?.ncb
          ? temp_data?.ncb?.slice(0, -1)
          : 0,
        applicableNcb: temp_data?.carOwnership
          ? 0
          : temp_data?.newNcb
          ? temp_data?.newNcb?.slice(0, -1)
          : 0,

        previousInsurer:
          temp_data?.prevIcFullName?.length !== "NEW"
            ? temp_data?.prevIcFullName === "New"
              ? "NEW"
              : temp_data?.prevIcFullName
            : "NEW",
        previousInsurerCode:
          temp_data?.prevIc !== "New"
            ? temp_data?.prevIc === "New"
              ? "NEW"
              : temp_data?.prevIc
            : "NEW",

        manufactureYear: temp_data?.manfDate,
        policyExpiryDate:
          temp_data?.expiry === "Not Sure" || temp_data?.expiry === "New"
            ? formatedDate
            : temp_data?.expiry,
        vehicleRegisterDate: temp_data?.regDate,
        previousPolicyType: !temp_data?.newCar
          ? tempData?.policyType === "New"
            ? "Not sure"
            : tempData?.policyType
          : "NEW",
        ownershipChanged: temp_data?.carOwnership ? "Y" : "N",
        isIdvChanged:
          tempData.idvChoosed && tempData.idvChoosed !== 0 ? "Y" : "N",
        businessType: temp_data?.newCar
          ? "newbusiness"
          : temp_data?.breakIn
          ? "breakin"
          : "rollover",

        policyType: temp_data?.odOnly ? "own_damage" : "comprehensive",
        vehicleOwnerType: temp_data?.ownerTypeId === 1 ? "I" : "C",
        version: temp_data?.versionId,
        versionName: temp_data?.versionName,
        fuelType: temp_data?.fuel,
      };

      dispatch(UpdateQuotesData(data));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    addOnsAndOthers?.selectedAccesories,
    addOnsAndOthers?.vehicleElectricAccessories,
    addOnsAndOthers?.vehicleNonElectricAccessories,
    addOnsAndOthers?.externalBiFuelKit,
    addOnsAndOthers?.selectedAdditions,
    addOnsAndOthers?.unNamedCoverValue,
    addOnsAndOthers?.additionalPaidDriver,
    addOnsAndOthers?.selectedDiscount,
    addOnsAndOthers?.volDiscountValue,
    tempData?.idvChoosed,
    tempData?.idvType,
    temp_data?.ncb,
    temp_data?.expiry,
    temp_data?.prevIc,
    temp_data?.prevIcFullName,
    temp_data?.manfDate,
    temp_data?.regDate,
    temp_data?.expiry,
    tempData?.policyType,
    temp_data?.noClaimMade,
    temp_data?.newCar,
    temp_data?.breakIn,
    temp_data?.carOwnership,
    temp_data?.ownerTypeId,
    temp_data?.fuel,
    temp_data?.versionId,
    temp_data?.versionName,
  ]);

  const back = () => {
    dispatch(
      setCompareData({
        ...[],
        enquiry_id: enquiry_id,
        userProductJourneyId: enquiry_id,
      })
    );
    history.push(
      `/${type}/quotes?enquiry_id=${enquiry_id}${
        token ? `&xutm=${token}` : ``
      }${typeId ? `&typeid=${typeId}` : ``}${
        _stToken ? `&stToken=${_stToken}` : ``
      }${shared ? `&shared=${shared}` : ``}`
    );
  };

  // Back to quotes page if there is no quotes to compare
  useEffect(() => {
    if (
      import.meta.env.VITE_BROKER === "TATA" &&
      compareQuotesList.length === 0 &&
      quoteComprehesive?.length < 1
    ) {
      history.push(
        `/${type}/quotes?enquiry_id=${enquiry_id}${
          token ? `&xutm=${token}` : ``
        }${typeId ? `&typeid=${typeId}` : ``}${
          _stToken ? `&stToken=${_stToken}` : ``
        }${shared ? `&shared=${shared}` : ``}`
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [compareQuotesList, quoteComprehesive]);

  // PDF Downloader funtion
  const handlePdfDownload = async () => {
    if (comparePdfData) {
      // Analytics | Compare PDF data tracking
      _comparePDFTracking(
        comparePdfData?.insurance_details,
        temp_data,
        TypeReturn(type),
        comparePdfData?.selectedAddons
      );

      const utf8Str = JSON.stringify(comparePdfData);
      const url = `${import.meta.env?.VITE_API_BASE_URL}/policyComparePdf`;

      try {
        const response = await fetch(url, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            validation: _generateKey(url),
          },
          body: JSON.stringify({ data: utf8Str }),
        });

        if (!response.ok) throw new Error("Failed to download PDF");

        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);

        const a = document.createElement("a");
        a.href = downloadUrl;
        a.download = "compare.pdf";
        document.body.appendChild(a);
        a.click();
        a.remove();

        window.URL.revokeObjectURL(downloadUrl);
      } catch (error) {
        console.error("Error downloading PDF:", error);
      }
    }
  };

  useEffect(() => {
    dispatch(
      getCompareData({
        enquiry_id: enquiry_id,
        userProductJourneyId: enquiry_id,
        type: "fetch",
      })
    );
  }, [dispatch, enquiry_id]);

  const closePopup = () => dispatch(setShowPop(false));

  const [validQuote, setValidQuote] = useState(
    compareQuotesList?.filter((x) => x.idv)
  );

  useEffect(() => {
    let quotes = compareQuotesList?.filter((x) => x.idv);
    setValidQuote(quotes);
    if (quotes.length === 3) {
      closePopup();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [compareQuotesList]);

  const compareFn = (singleQuote) => {
    if (validQuote?.length < 3) {
      let validCompareQuotes = compareQuotesList?.filter((x) => x.idv);
      dispatch(compareQuotes([...validCompareQuotes, singleQuote]));
    }
  };

  const compareFn2 = (singleQuote) => {
    if (validQuote?.length < 3) {
      let validCompareQuotes = compareQuotesList?.filter((x) => x.idv);
      dispatch(compareQuotes([...validCompareQuotes, singleQuote]));
    }
  };

  const removeFn = (singleQuote) => {
    let allQuotes = compareQuotesList?.filter(
      (x) => x.policyId !== singleQuote.policyId
    );
    dispatch(compareQuotes(allQuotes));
  };

  const content = (
    <Content
      lessThan768={lessThan768}
      compareQuotesList={compareQuotesList}
      shortTermType={shortTermType}
      tempData={tempData}
      closePopup={closePopup}
      validQuote={validQuote}
      removeFn={removeFn}
      compareFn={compareFn}
    />
  );

  const innerHeight = window.innerHeight;

  const contentModal = (
    <ContentModal
      innerHeight={innerHeight}
      lessThan768={lessThan768}
      compareQuotesList={compareQuotesList}
      tempData={tempData}
      closePopup={closePopup}
      validQuote={validQuote}
      compareFn2={compareFn2}
      removeFn={removeFn}
    />
  );

  const [zdlp, setZdlp] = useState("ONE");
  const [claimList, setClaimList] = useState([]);

  const [zdlp_gdd, setZdlp_gdd] = useState("ONE");
  const [claimList_gdd, setClaimList_gdd] = useState([]);

  return (
    <>
      {!loading ? (
        <>
          {showPop &&
            (!lessThan768 ? (
              <Popup
                content={content}
                show={showPop}
                closePop={closePopup}
                reduxClose
                width="900px"
                mobileHeight="90% !important"
                height={lessThan768 ? "90% !important" : "auto"}
                outside={true}
              />
            ) : (
              <Popup
                content={contentModal}
                show={showPop}
                onClose={closePopup}
                reduxClose
                width="100%"
                mobileHeight="100% !important"
                height={lessThan768 ? "100% !important" : "auto"}
              />
            ))}
          <TopDiv>
            <div className="compareConatiner" id={"topdf"}>
              <div className="backBtn" style={{ paddingBottom: "30px" }}>
                <BackButton
                  type="button"
                  onClick={back}
                  style={{
                    marginTop: "-20px",
                    left: "35px",
                    top: Theme?.BackButton?.backButtonTop
                      ? Theme?.BackButton?.backButtonTop
                      : "110px",
                  }}
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className=""
                    viewBox="0 0 24 24"
                  >
                    <path d="M11.67 3.87L9.9 2.1 0 12l9.9 9.9 1.77-1.77L3.54 12z" />
                    <path d="M0 0h24v24H0z" fill="none" />
                  </svg>
                  <text style={{ color: "black" }}>Back</text>
                </BackButton>
              </div>
              <div>
                <div className="compare-page compare-page-container">
                  <div className="compare-products-wrap">
                    <Features
                      compareQuotes={compareQuotesList}
                      ButtonPanel={() => {}}
                      type={type}
                      quote={compareQuotesList[0]}
                      scrollPosition={scrollPosition}
                      zdlp={zdlp}
                      setZdlp={setZdlp}
                      claimList={claimList}
                      setClaimList={setClaimList}
                      zdlp_gdd={zdlp_gdd}
                      setZdlp_gdd={setZdlp_gdd}
                      claimList_gdd={claimList_gdd}
                      setClaimList_gdd={setClaimList_gdd}
                      applicableAddonsLits={applicableAddonsLits}
                    />
                    <CompareProductsList
                      quoteComprehesive={quoteComprehesive}
                      compareQuotes={compareQuotesList}
                      type={type}
                      setPrevPopup={setPrevPopup2}
                      prevPopup={prevPopup2}
                      setSelectedId={setSelectedId}
                      setSelectedCompanyName={setSelectedCompanyName}
                      setSelectedIcId={setSelectedIcId}
                      setSelectedCompanyAlias={setSelectedCompanyAlias}
                      setApplicableAddonsLits={setApplicableAddonsLits}
                      scrollPosition={scrollPosition}
                      zdlp={zdlp}
                      setZdlp={setZdlp}
                      claimList={claimList}
                      setClaimList={setClaimList}
                      zdlp_gdd={zdlp_gdd}
                      setZdlp_gdd={setZdlp_gdd}
                      claimList_gdd={claimList_gdd}
                      setClaimList_gdd={setClaimList_gdd}
                    />
                  </div>
                </div>
              </div>
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
                />
              )}
            </div>
            <FloatButton />
            {lessThan768 && (
              <PdfDiv
                onClick={
                  validQuote?.length > 1
                    ? handlePdfDownload
                    : console.log("Run", validQuote)
                }
                style={{
                  position: "fixed",
                  right: "1%",
                  top: "67%",
                  zIndex: "1",
                  borderRadius: "50%",
                  padding: lessThan380 ? "10px 16px" : "12px 18px",
                  fontSize: "21px",
                }}
              >
                <i className="fa fa-download pdf_icon"></i>
              </PdfDiv>
            )}
          </TopDiv>
        </>
      ) : (
        <Loader />
      )}
    </>
  );
};
