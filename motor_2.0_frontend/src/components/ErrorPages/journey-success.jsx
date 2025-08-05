import React, { useEffect } from "react";
import { Row, Col } from "react-bootstrap";
import { Button, Loader, UrlFn, Bajaj_rURL } from "components";
import { useLocation } from "react-router";
import { useDispatch, useSelector } from "react-redux";
import { reloadPage, scrollToTop } from "utils";
import styled from "styled-components";
import { Prefill } from "modules/proposal/proposal.slice";
import { Prefill as PrefillHome } from "modules/Home/home.slice";
import _ from "lodash";
import { useMediaPredicate } from "react-media-hook";
import { ShareQuote } from "../../modules/Home/home.slice";

const JourneySuccess = (props) => {
  const dispatch = useDispatch();
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const FinalAmtEncrypted = query.get("xmc");
  const xmc = FinalAmtEncrypted ? window.atob(FinalAmtEncrypted) : "";
  const inspectionNo = query.get("inspection_no");
  const IC = query.get("IC");
  const token = query.get("xutm");
  const { temp_data } = useSelector((state) => state.proposal);
  const { theme_conf } = useSelector((state) => state.home);
  const lessthan767 = useMediaPredicate("(max-width: 767px)");

  //IOS check.
  let isMobileIOS = false; //initiate as false
  // device detection
  if (
    /iPad|iPhone|iPod/.test(navigator.userAgent) &&
    !window.MSStream &&
    lessthan767
  ) {
    isMobileIOS = true;
  }

  var standalone = window.navigator.standalone,
    userAgent = window.navigator.userAgent.toLowerCase(),
    safari = /safari/.test(userAgent),
    ios = /iphone|ipod|ipad/.test(userAgent);

  useEffect(() => {
    if (enquiry_id && inspectionNo) {
      dispatch(Prefill({ enquiryId: enquiry_id }));
      dispatch(PrefillHome({ enquiryId: enquiry_id }));
      window.Android &&
        window.Android.SendToPaymentInspectionPage(inspectionNo);
    }
  }, [enquiry_id, inspectionNo]);

  useEffect(() => {
    scrollToTop();
  }, []);

  useEffect(() => {
    temp_data?.userProposal?.email &&
      temp_data?.userProposal?.mobileNumber &&
      dispatch(
        ShareQuote({
          enquiryId: enquiry_id,
          notificationType: "all",
          domain: `http://${window.location.hostname}`,
          type: "inspectionIntimation",
          emailId: temp_data?.userProposal?.email,
          firstName: temp_data?.userProposal?.firstName,
          lastName: temp_data?.userProposal?.lastName,
          productName: temp_data?.selectedQuote?.productName,
          // logo: props?.getLogoUrl(),
          mobileNo: temp_data?.userProposal?.mobileNumber,
          to: `91${temp_data?.userProposal?.mobileNumber}`,
          
          ic_logo: temp_data?.selectedQuote?.companyLogo,
        })
      );
  }, [temp_data?.userProposal?.email, temp_data?.userProposal?.mobileNumber]);

  const handleRedirect = () => {
    const { VITE_BROKER, VITE_API_BASE_URL, VITE_BASENAME } =
      import.meta.env;

    const {
      corporateVehiclesQuoteRequest,
      journeyType,
      agentDetails,
      productSubTypeId,
    } = temp_data || {};
    // Determine if the current app is for OLA drivers
    const isOlaDriver =
      VITE_BROKER === "OLA" &&
      corporateVehiclesQuoteRequest?.journeyType === "driver-app";
    // Check if there is a Point of Sale (POS) agent
    const isPOS = agentDetails?.find((o) => o.sellerType === "P");
    // Check if there is an employee agent
    const isEmployee = agentDetails?.find((o) => o.sellerType === "E");
    // Check if the product subtype is for a car
    const isProductSubTypeCar = Number(productSubTypeId) === 1;
    // Check if the product subtype is for a bike
    const isProductSubTypeBike = Number(productSubTypeId) === 2;
    // Handle redirection for OLA drivers based on the environment
    if (isOlaDriver) {
      const redirectUrl =
        VITE_API_BASE_URL === "https://api-ola-uat.fynity.in/api"
          ? "https://auth-repose-azure.stg.corp.olacabs.com/olamoney/kyc-web/wallet/driver/crosssell-dashboard"
          : "https://supply-api.olacabs.com/crosssell-dashboard";
      reloadPage(redirectUrl);
    } else if (window?.JSBridge) {
      // If JSBridge exists, use it to handle redirection
      window.JSBridge.call("popWindow");
    } else if (
      // Check for non-empty agent details and specific broker conditions
      (!_.isEmpty(agentDetails) && (isPOS || isEmployee)) ||
      VITE_BROKER === "RB" ||
      VITE_BROKER === "UIB"
    ) {
      if (VITE_BROKER === "BAJAJ") {
        // Redirect for BAJAJ broker
        reloadPage(Bajaj_rURL(true));
      } else {
        // Construct the base path for redirection
        const basePath =
          VITE_BASENAME !== "NA" ? `/${VITE_BASENAME}` : "";
        // Determine the product path based on the product subtype
        const productPath = isProductSubTypeCar
          ? "car"
          : isProductSubTypeBike
          ? "bike"
          : "cv";
        // Execute the redirection
        reloadPage(
          `${window.location.origin}${basePath}/${productPath}/lead-page`
        );
      }
    } else {
      // Fallback redirection logic
      if (VITE_BROKER === "BAJAJ") {
        reloadPage(Bajaj_rURL());
      } else if (isEmployee) {
        // Redirect based on employee seller type
        reloadPage(
          theme_conf?.broker_config?.broker_asset
            ?.success_payment_url_redirection?.url || UrlFn(true, "E")
        );
      } else {
        // Default redirection
        reloadPage(
          theme_conf?.broker_config?.broker_asset
            ?.success_payment_url_redirection?.url || UrlFn(true)
        );
      }
    }
    // Additional redirection handling for Android devices
    if (window.Android) {
      window.Android.SendToHomePage("Redirecting to homepage");
    }
  };

  return !_.isEmpty(temp_data) ? (
    <Row className="text-center w-100 mx-auto">
      <Top className="mx-auto" style={{ width: "50%" }}>
        <div className="mt-4 d-flex justify-content-center w-100">
          <img
            src={`${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ""
            }/assets/images/like.svg`}
            alt="errImg"
            height="100"
            width="100"
            className="success_image"
          />
        </div>
        <div className="mt-4 d-flex flex-column justify-content-center w-100">
          <h4
            className="text-center w-100 text-success font-weight-bold cong_text"
            style={{ fontSize: "2.3rem" }}
          >
            Congratulations!
          </h4>
        </div>
        <div className="mt-4 d-flex flex-column justify-content-center w-100">
          <p
            className="text-center w-100 proposal_text"
            style={{ fontSize: "1.2rem", color: "#006600" }}
          >
            {`Proposal has been submitted successfully. Premium has been evaluated based on the NCB declaration made. ${
              xmc ? `Final Payable premium is Rs. ${Number(xmc).toFixed(2)}` : ``
            }.`}
          </p>
          {!temp_data?.icBreakinUrl ? (
            <p
              className="text-center w-100 refId_text"
              style={{ fontSize: "1.2rem" }}
            >
              {`Your Inspection request with ${
                IC || "the insurance company"
              } is raised with ID/Reference ID ${inspectionNo}, You will receive an email / Whatsapp / SMS with the above reference id, please continue for the payment from the link provided in 
              the mail after the inspection approval..`}
            </p>
          ) : (
            <>
              <p>
                {`Your inspection request with ${
                  IC || "the insurance company"
                } has been successfully created with Reference ID ${inspectionNo}. You will receive an email, WhatsApp message, or SMS with this reference ID.
`}
              </p>
              <p>
                {"Please "}
                <a href={temp_data?.icBreakinUrl}>
                  <u>click here</u>
                </a>
                {" to initiate the Inspection process"}
              </p>
            </>
          )}
        </div>
        {((import.meta.env.VITE_BROKER === "OLA" &&
          temp_data?.corporateVehiclesQuoteRequest?.journeyType ===
            "driver-app") ||
          import.meta.env.VITE_BROKER !== "OLA") &&
          (!(
            (isMobileIOS && !standalone && !safari) ||
            userAgent.includes("wv")
          ) ||
            import.meta.env.VITE_BROKER === "PAYTM") && (
            <div className="mt-2 d-flex justify-content-center w-100">
              <Button
                buttonStyle="outline-solid"
                hex1="#006400"
                hex2="#228B22"
                borderRadius="25px"
                type="submit"
                shadow={"none"}
                onClick={() => handleRedirect()}
              >
                Go To Homepage
              </Button>
            </div>
          )}
      </Top>
    </Row>
  ) : (
    <Loader />
  );
};

const Top = styled.div`
  font-family: ${({ theme }) => theme.Payment?.fontFamily || ""};
  font-weight: ${({ theme }) => theme.Payment?.fontWeight || ""};
  @media (max-width: 767px) {
    width: 100% !important;
    padding: 0 30px;
    .success_image {
      height: 50px;
      width: 50px;
    }
    .cong_text {
      font-size: 1.3rem !important;
    }
    .proposal_text {
      font-size: 0.8rem !important;
    }
    .refId_text {
      font-size: 0.8rem !important;
    }
  }
`;

export default JourneySuccess;
