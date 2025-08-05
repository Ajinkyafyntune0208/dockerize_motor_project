/* eslint-disable jsx-a11y/anchor-is-valid */
import React, { useEffect } from "react";
import { Row, Col } from "react-bootstrap";
import {
  Button,
  Loader,
  ContentFn,
  UrlFn,
  Bajaj_rURL,
  getBrokerLogoUrl,
} from "components";
import { useHistory, useLocation } from "react-router";
import { useDispatch, useSelector } from "react-redux";
import { reloadPage, scrollToTop, _isUserCustomer } from "utils";
import _ from "lodash";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import styled from "styled-components";
import { Prefill, clear } from "modules/proposal/proposal.slice";
import swal from "sweetalert";
import { useMediaPredicate } from "react-media-hook";
import { ShareQuote } from "modules/Home/home.slice";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const JourneyFailure = () => {
  const dispatch = useDispatch();
  const { temp_data, error } = useSelector((state) => state.proposal);
  const { theme_conf } = useSelector((state) => state.home);
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const errMsg = query.get("msg");
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

  const CardData = !_.isEmpty(temp_data)
    ? temp_data?.userProposal?.additonalData
      ? temp_data?.userProposal?.additonalData
      : {}
    : {};

  useEffect(() => {
    scrollToTop();
  }, []);

  window.Android && window.Android.SendToPaymentFailPage("Payment failed");

  useEffect(() => {
    if (error) {
      swal(
        "Error",
        `${`Trace ID:- ${
          temp_data?.traceId ? temp_data?.traceId : enquiry_id
        }.\n Error Message:- ${error}`}`,
        "error"
      );
    }
    return () => {
      dispatch(clear());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [error]);

  //load prefill data
  useEffect(() => {
    if (enquiry_id) dispatch(Prefill({ enquiryId: enquiry_id }, true));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id]);

  //email - trigger
  useEffect(() => {
    if (
      enquiry_id &&
      CardData?.owner?.email &&
      temp_data?.selectedQuote?.productName
    )
      dispatch(
        ShareQuote({
          enquiryId: enquiry_id,
          notificationType: "all",
          domain: `http://${window.location.hostname}`,
          type: "paymentFailure",
          emailId: CardData?.owner?.email,
          email: CardData?.owner?.email,
          mobileNo: temp_data?.mobileNo,
          firstName: CardData?.owner?.firstName,
          lastName: CardData?.owner?.lastName,
          action: window.location.href,
          link: window.location.href,
          productName: temp_data?.selectedQuote?.productName,
          reInitiate: temp_data?.journeyStage?.proposalUrl,
          logo: getBrokerLogoUrl(),
        })
      );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id, temp_data]);

  const shouldRedirect = () => {
    // Check if the current broker is OLA
    const isOlaBroker = import.meta.env.VITE_BROKER === "OLA";
    // Check if the journey type is for a driver app
    const isDriverAppJourney =
      temp_data?.corporateVehiclesQuoteRequest?.journeyType === "driver-app";
    // Check if the environment is the UAT (User Acceptance Testing) environment
    const isUatEnv =
      import.meta.env.VITE_API_BASE_URL ===
      "https://api-ola-uat.fynity.in/api";
    // Check if the environment is the production environment
    const isProductionEnv =
      import.meta.env.VITE_API_BASE_URL === "https://supply-api.olacabs.com";
    // Check if agent details are empty
    const isAgentDetailsEmpty = _.isEmpty(temp_data?.agentDetails);
    // Check if there is a POS (Point of Sale) seller in agent details
    const hasPOSSeller = temp_data?.agentDetails?.find(
      (o) => o?.sellerType === "P"
    );
    // Check if there is an employee seller in agent details
    const hasEmployeeSeller = temp_data?.agentDetails?.find(
      (o) => o?.sellerType === "E"
    );
    // Check if the current broker is BAJAJ
    const isBajajBroker = import.meta.env.VITE_BROKER === "BAJAJ";
    // Check if the current broker is RB
    const isRBBroker = import.meta.env.VITE_BROKER === "RB";
    // Check if the current broker is UIB
    const isUIBBroker = import.meta.env.VITE_BROKER === "UIB";
    // Determine if the user is a customer based on certain conditions
    const isUserCustomer = _isUserCustomer(
      enquiry_id,
      theme_conf?.broker_config?.pc_redirection
    );
    // Redirect logic for Ola driver app journey
    if (isOlaBroker && isDriverAppJourney) {
      reloadPage(
        isUatEnv
          ? "https://auth-repose-azure.stg.corp.olacabs.com/olamoney/kyc-web/wallet/driver/crosssell-dashboard"
          : "https://supply-api.olacabs.com/crosssell-dashboard"
      );
    } else if (window?.JSBridge) {
      // If JSBridge exists, call popWindow to handle redirection
      window.JSBridge.call("popWindow");
    } else if (
      // Complex condition to determine if redirection should occur based on agent details or user type
      !(!isAgentDetailsEmpty && (hasPOSSeller || hasEmployeeSeller)) ||
      isUserCustomer ||
      isAgentDetailsEmpty ||
      isRBBroker ||
      isUIBBroker
    ) {
      // Redirection logic for Bajaj broker or default redirection
      if (isBajajBroker) {
        reloadPage(Bajaj_rURL(true));
      } else {
        // Default redirection based on product subtype ID
        reloadPage(
          `${window.location.origin}${
            import.meta.env.VITE_BASENAME !== "NA"
              ? `/${import.meta.env.VITE_BASENAME}`
              : ``
          }/${
            Number(temp_data?.productSubTypeId) === 1
              ? "car"
              : Number(temp_data?.productSubTypeId) === 2
              ? "bike"
              : "cv"
          }/lead-page`
        );
      }
    } else {
      // Fallback redirection logic for Bajaj broker or based on seller type
      if (isBajajBroker) {
        reloadPage(Bajaj_rURL());
      } else if (hasEmployeeSeller) {
        reloadPage(theme_conf?.broker_config?.broker_asset?.success_payment_url_redirection?.url || UrlFn(true, "E"));
      } else {
        reloadPage(theme_conf?.broker_config?.broker_asset?.success_payment_url_redirection?.url ||  UrlFn(true));
      }
    }
    // Additional redirection handling for Android devices
    if (window.Android) {
      window.Android.SendToHomePage("Redirecting to homepage");
    }
  };

  return !_.isEmpty(temp_data) ? (
    <Row className="text-center w-100 mx-auto" style={{}}>
      <Top className="mx-auto" style={{ width: "50%" }}>
        <div className="mt-4 d-flex justify-content-center w-100">
          <img
            src={`${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ""
            }/assets/images/remove.png`}
            alt="errImg"
            height="100"
            width="100"
            className="failure_image"
          />
        </div>
        <div className="mt-4 d-flex flex-column justify-content-center w-100">
          <h4
            className="text-center w-100 text-danger font-weight-bold oops_text"
            style={{ fontSize: "2.3rem" }}
          >
            Oops!
          </h4>
          <h4 className="text-center w-100 text-danger font-weight-bold transaction_text">
            {errMsg ? errMsg : "Your transaction was unsuccessful!"}
          </h4>
        </div>
        <div className="mt-4 d-flex flex-column justify-content-center w-100">
          <p
            className="text-center w-100 error_text"
            style={{ fontSize: "1.1rem", color: "red" }}
          >
            Process could not be completed, please ensure the information you
            provided is Correct.
          </p>
          <p
            className="text-center w-100 content_text"
            style={{ fontSize: "1.1rem" }}
          >
            {theme_conf?.broker_config ? (
              <>
                In case of any further requirements, please contact us at
                <b> {theme_conf?.broker_config?.email}</b> or call us at our
                number
                <b> {theme_conf?.broker_config?.phone} </b>
              </>
            ) : (
              ContentFn()
            )}
          </p>
        </div>
        {temp_data?.journeyStage?.proposalUrl &&
          !theme_conf?.broker_config?.hide_retry && (
            <div className="mt-2 d-flex justify-content-center w-100">
              <Button
                buttonStyle="outline-solid"
                hex1={
                  Theme?.proposalProceedBtn?.hex1
                    ? Theme?.proposalProceedBtn?.hex1
                    : "#4ca729"
                }
                hex2={
                  Theme?.proposalProceedBtn?.hex2
                    ? Theme?.proposalProceedBtn?.hex2
                    : "#4ca729"
                }
                borderRadius={
                  Theme?.QuoteBorderAndFont?.borderRadius
                    ? Theme?.QuoteBorderAndFont?.borderRadius
                    : "25px"
                }
                type="submit"
                shadow={"none"}
                onClick={() =>
                  reloadPage(
                    temp_data?.journeyStage?.proposalUrl?.includes("dropout")
                      ? `${temp_data?.journeyStage?.proposalUrl?.replace(
                          /quotes/gi,
                          "proposal-page"
                        )}`
                      : `${temp_data?.journeyStage?.proposalUrl?.replace(
                          /quotes/gi,
                          "proposal-page"
                        )}&dropout=true`
                  )
                }
              >
                Retry Payment
              </Button>
            </div>
          )}
        {((import.meta.env.VITE_BROKER === "OLA" &&
          temp_data?.corporateVehiclesQuoteRequest?.journeyType ===
            "driver-app") ||
          import.meta.env.VITE_BROKER !== "OLA") &&
          ((!(
            (isMobileIOS && !standalone && !safari) ||
            userAgent.includes("wv")
          )) || import.meta.env.VITE_BROKER === "PAYTM" ) &&
          !theme_conf?.broker_config?.block_home_redirection && (
            <div style={{ margin: "20px" }}>
              <a
                style={{
                  textDecoration: "underline",
                  color: Theme?.proposalProceedBtn?.hex2
                    ? Theme?.proposalProceedBtn?.hex2
                    : "#4ca729",
                  cursor: "pointer",
                }}
                onClick={() => shouldRedirect()}
              >
                Go to Homepage
              </a>
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
    .failure_image {
      height: 50px;
      width: 50px;
    }
    .oops_text {
      font-size: 1.3rem !important;
    }
    .transaction_text {
      font-size: 1rem !important;
    }
    .error_text {
      font-size: 0.9rem !important;
    }
    .content_text {
      font-size: 0.8rem !important;
    }
  }
`;

export default JourneyFailure;
