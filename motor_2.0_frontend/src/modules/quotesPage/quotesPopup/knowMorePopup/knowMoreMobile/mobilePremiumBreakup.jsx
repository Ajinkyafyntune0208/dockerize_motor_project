import React, { useState, useEffect } from "react";
import { Badge } from "react-bootstrap";
import { useSelector } from "react-redux";
import { currencyFormater, scrollToTargetAdjusted, _haptics } from "utils";
import _ from "lodash";
import Style from "../style";
import Table from "./Tables";
import { handlePremPdfClick } from "../premium-pdf/pdf/premium-pdf-download";
import { handleEmailClick } from "../premium-pdf/pdf/premium-pdf-email";

export const MobilePremiumBreakup = ({
  quote,
  type,
  totalPremiumB,
  revisedNcb,
  totalPremiumC,
  addonDiscountPercentage,
  others,
  othersList,
  totalAddon,
  totalPremium,
  gst,
  finalPremium,
  handleClick,
  otherDiscounts,
  show,
  lessthan767,
  totalPremiumA,
  claimList,
  setZdlp,
  zdlp,
  claimList_gdd,
  setZdlp_gdd,
  zdlp_gdd,
  lessthan993,
  totalApplicableAddonsMotor,
  llpaidCon,
  tempData,
  selectedTab,
  setSendPdf,
  setSendQuotes,
  shortTerm,
  Theme,
  prefill,
  dispatch,
  extraLoading,
}) => {
  const { temp_data, gstStatus, theme_conf } = useSelector(
    (state) => state.home
  );
  const { addOnsAndOthers } = useSelector((state) => state.quotes);
  const { height } = useWindowDimensions();
  function getWindowDimensions() {
    const { innerWidth: width, innerHeight: height } = window;
    return {
      width,
      height,
    };
  }

  let userAgent = navigator.userAgent;
  const isBreakupShareable = theme_conf?.broker_config?.broker_asset?.communication_configuration?.premium_breakup;
  // eslint-disable-next-line no-unused-vars
  let isMobileIOS = false; //initiate as false
  // device detection
  if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream && lessthan767) {
    isMobileIOS = true;
  }
  function useWindowDimensions() {
    const [windowDimensions, setWindowDimensions] = useState(
      getWindowDimensions()
    );

    useEffect(() => {
      function handleResize() {
        setWindowDimensions(getWindowDimensions());
      }
      window.addEventListener("resize", handleResize);
      return () => window.removeEventListener("resize", handleResize);
    }, []);

    return windowDimensions;
  }

  useEffect(() => {
    scrollToTargetAdjusted("bodyCard", 45);
  }, [show]);

  const [Info, setInfo] = useState(lessthan767 ? false : true);
  const innerHeight = window.innerHeight;

  //LL-paid-driver/cleaner/conductor
  const llpdCon =
    quote?.llPaidDriverPremium * 1 ||
    quote?.llPaidConductorPremium * 1 ||
    quote?.llPaidCleanerPremium * 1;

  // prettier-ignore
  const emailPdfProp = {
      prefill, type, totalApplicableAddonsMotor, addonDiscountPercentage, quote, 
      addOnsAndOthers, others, othersList, temp_data, llpaidCon, revisedNcb, otherDiscounts, tempData,
      totalPremium, totalPremiumA, totalPremiumB, totalPremiumC, totalAddon, finalPremium, gst,
      selectedTab, shortTerm, Theme, setSendPdf, setSendQuotes, theme_conf
    }

  return (
    <Style.Container innerHeight={innerHeight}>
      <Style.Header>
        <Style.LogoContainer>
          {" "}
          <img
            src={quote?.companyLogo ? quote?.companyLogo : ""}
            alt=""
            className="PremIconMobile"
            id="premium_breakup_ic_img"
            style={{ height: "auto", width: "100%" }}
          />
        </Style.LogoContainer>
        <Style.PdfEmailContainer
          style={{ display: "flex", alignItems: "center" }}
        >
          <div className="mailAndPdfContainer">
            <div
              className="mailAndPdf"
              onClick={() =>
                handlePremPdfClick({
                  prefill,
                  type,
                  totalApplicableAddonsMotor,
                  addonDiscountPercentage,
                  quote,
                  addOnsAndOthers,
                  others,
                  othersList,
                  temp_data,
                  llpaidCon,
                  revisedNcb,
                  otherDiscounts,
                  tempData,
                  totalPremium,
                  totalPremiumA,
                  totalPremiumB,
                  totalPremiumC,
                  totalAddon,
                  finalPremium,
                  gst,
                  selectedTab,
                  shortTerm,
                  Theme,
                  dispatch,
                  extraLoading,
                  theme_conf,
                })
              }
            >
              {" "}
              <div className="logoWrapper">
                {" "}
                <i
                  className="fa fa-file-pdf-o"
                  style={{
                    fontSize: "16px",
                    color:
                      import.meta.env.VITE_BROKER === "ABIBL"
                        ? "#fff"
                        : "#333",
                  }}
                  aria-hidden="true"
                ></i>{" "}
              </div>
              <div className="emailText" style={{ fontSize: "14px" }}>
                {" "}
                PDF{" "}
              </div>
            </div>
            {import.meta.env.VITE_BROKER !== "PAYTM" &&
              (isBreakupShareable?.email ||
              isBreakupShareable?.whatsapp_api) && (
                <button
                  style={{
                    all: "unset",
                    display: "flex",
                    justifyContent: "center",
                    alignSelf: "center",
                  }}
                  onClick={() => handleEmailClick(emailPdfProp)}
                >
                  {" "}
                  <div className="logoWrapper">
                    {" "}
                    <i
                      className="fa fa-share-alt"
                      style={{
                        fontSize: "15px",
                        color:
                          import.meta.env.VITE_BROKER === "ABIBL"
                            ? "#fff"
                            : "#333",
                      }}
                      aria-hidden="true"
                    ></i>{" "}
                  </div>
                  <div className="emailText" style={{ fontSize: "14px" }}>
                    {" "}
                    SHARE{" "}
                  </div>
                </button>
              )}
          </div>
        </Style.PdfEmailContainer>
      </Style.Header>
      <Style.MBody height={height}>
        <Style.BodyDetails>
          {lessthan767 && (
            <p
              onClick={() => (Info ? setInfo(false) : setInfo(true))}
              style={
                Info
                  ? {
                      marginBottom: "10px",
                      fontSize: "12px",
                      fontWeight: 600,
                    }
                  : { fontSize: "12px", fontWeight: 600 }
              }
            >
              {"Vehicle Details"}
              {
                <i
                  style={{
                    fontSize: "18px",
                    position: "relative",
                    top: "1.5px",
                  }}
                  className={
                    Info ? "ml-1 fa fa-angle-up" : "ml-1 fa fa-angle-down"
                  }
                ></i>
              }
            </p>
          )}
          {Info && (
            <div className="vehicleDetails">
              <div className="idvData">
                IDV:{" "}
                {temp_data?.tab === "tab2" ? (
                  <Badge
                    variant="secondary"
                    style={{
                      cursor: "pointer",
                    }}
                  >
                    Not Applicable
                  </Badge>
                ) : (
                  ` ₹ ${currencyFormater(quote?.idv)}`
                )}
              </div>
              <div className="mmvData">
                {quote?.mmvDetail?.manfName}-{quote?.mmvDetail?.modelName}-
                {quote?.mmvDetail?.versionName}-
                {temp_data?.journeyCategory === "GCV"
                  ? quote?.mmvDetail?.grossVehicleWeight
                  : quote?.mmvDetail?.cubicCapacity}
                {temp_data?.journeyCategory === "GCV" ?  "GVW" : "cc"}
              </div>
              <div>
                {quote?.fuelType} | {quote?.vehicleRegistrationNo} -{" "}
                {temp_data?.rtoCity}
              </div>
              {temp_data?.corporateVehiclesQuoteRequest?.selectedGvw && (
                <div>
                  Gross Vehicle Weight (lbs) :{" "}
                  {temp_data?.corporateVehiclesQuoteRequest?.selectedGvw}
                </div>
              )}
            </div>
          )}
        </Style.BodyDetails>
        <Style.BodyPremiumBreakup
          id={"bodyCard"}
          style={Info ? { marginTop: "-10px" } : { marginTop: "-40px" }}
        >
          <Table.OwnDamage
            quote={quote}
            addOnsAndOthers={addOnsAndOthers}
            temp_data={temp_data}
          />
          <Table.Liability
            quote={quote}
            addOnsAndOthers={addOnsAndOthers}
            temp_data={temp_data}
            type={type}
            llpdCon={llpdCon}
            totalPremiumB={totalPremiumB}
          />
          <Table.OdDiscount
            revisedNcb={revisedNcb}
            addOnsAndOthers={addOnsAndOthers}
            quote={quote}
            otherDiscounts={otherDiscounts}
            totalPremiumC={totalPremiumC}
            temp_data={temp_data}
          />
          {/* <Table.MobileAddons
            quote={quote}
            claimList={claimList}
            claimList_gdd={claimList_gdd}
            setZdlp={setZdlp}
            setZdlp_gdd={setZdlp_gdd}
            zdlp={zdlp}
            zdlp_gdd={zdlp_gdd}
            type={type}
            addonDiscountPercentage={addonDiscountPercentage}
            addOnsAndOthers={addOnsAndOthers}
            lessthan993={lessthan993}
            othersList={othersList}
            totalAddon={totalAddon}
            others={others}
          /> */}
          <Table.FinalCalculation
            quote={quote}
            totalAddon={totalAddon}
            totalPremiumA={totalPremiumA}
            totalPremiumB={totalPremiumB}
            totalPremiumC={totalPremiumC}
            totalPremium={totalPremium}
            finalPremium={finalPremium}
            gst={gst}
            extraLoading={extraLoading}
          />
        </Style.BodyPremiumBreakup>
      </Style.MBody>

      <Style.BuyButtonMobile
        onClick={() => [_haptics([100, 0, 50]), handleClick()]}
      >
        {" "}
        BUY NOW{" "}
        <div className="amount">
          ₹ {currencyFormater(finalPremium)} {gstStatus ? "(incl. GST)" : ""}
        </div>
      </Style.BuyButtonMobile>
    </Style.Container>
  );
};

export default MobilePremiumBreakup;
