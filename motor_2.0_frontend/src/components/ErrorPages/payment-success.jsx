import React, { useEffect, useRef, useState } from "react";
import { Row, Badge } from "react-bootstrap";
import {
  Button,
  Loader,
  FloatButton,
  ContentFn,
  UrlFn,
  getBrokerLogoUrl,
  Bajaj_rURL,
  Toaster,
} from "components";
import { Button as Btn } from "react-bootstrap";
import { useHistory, useLocation } from "react-router";
import { useDispatch, useSelector } from "react-redux";
import { useForm } from "react-hook-form";
import {
  PolicyGen,
  clear,
} from "modules/payment-gateway/payment-gateway.slice";
import swal from "sweetalert";
import {
  downloadFile,
  reloadPage,
  scrollToTop,
  _isUserCustomer,
  isB2B,
  isTokenExpired,
} from "utils";
import { Prefill } from "modules/proposal/proposal.slice";
import { generatePaymentStatusHTML } from "modules/payment-pdf/html";
import _ from "lodash";
import {
  ShareQuote,
  TokenStatus,
  TriggerWhatsapp,
  getNdslUrl,
} from "modules/Home/home.slice";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import "./btn.css";
import styled, { createGlobalStyle } from "styled-components";
import { useMediaPredicate } from "react-media-hook";
import Carousel from "components/slider/Carousel";
import {
  _successTracking,
  _paymentSuccessTracking,
} from "analytics/payment-success/payment-success-tracking";
import {
  SubmitData,
  clear as clearRehit,
} from "modules/GeneratePdf/generate.slice";
import { brokerEmailFunction } from "components";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const PaymentSuccess = () => {
  const dispatch = useDispatch();
  const { policy, error, loading, policyLoading } = useSelector(
    (state) => state.payment
  );
  const { tokenStatus } = useSelector((state) => state.home);
  const { submit, error: errorRehit } = useSelector((state) => state.generate);

  const history = useHistory();
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const icrm = !localStorage.SSO_user;
  const { temp_data } = useSelector((state) => state.proposal);
  const { theme_conf, ndslUrl } = useSelector((state) => state.home);
  const lessthan767 = useMediaPredicate("(max-width: 767px)");

  // store trace id to localStorage to prevent multiple trigger of events
  const storedId = localStorage.getItem("enquiry_id");

  const CardData = !_.isEmpty(temp_data)
    ? temp_data?.userProposal?.additonalData
      ? temp_data?.userProposal?.additonalData
      : {}
    : {};

  // Rehit Button to download pdf using another toaster using below API
  const rehitButton = () => {
    dispatch(
      SubmitData({
        enquiryId: enquiry_id,
        url: `${import.meta.env.VITE_API_BASE_URL}/generatePdf`,
      })
    );
  };

  // for 10 mins - every 10 secs, will invoke this function.
  const [updatePoll, setUpdatePoll] = useState(0);
  // useEffect(() => {
  //   if (!policy?.pdfUrl) {
  //     setTimeout(
  //       () => {
  //         rehitButton();
  //         setUpdatePoll((prev) => prev + 1);
  //       },
  //       updatePoll < 60 ? 10000 : 300000
  //     );
  //   }
  // }, [updatePoll]);

  const [limit, setLimit] = useState(false);
  useEffect(() => {
    if (submit?.pdf_link && !limit) {
      setLimit(true);
      downloadFile(submit?.pdf_link, false, true);
    }
  }, [submit?.pdf_link]);

  const handleReset = () => {
    dispatch(clearRehit());
  };

  useEffect(() => {
    if (!_.isEmpty(submit)) {
      if (submit?.pdf_link) {
        swal({
          title: "Info",
          text: `Your Policy Number is ${submit?.policy_number}`,
          icon: "success",
          timer: 2000,
        });
      } else {
        swal("Error", "Pdf not found", "error");
      }
    }
    if (errorRehit) {
      swal({
        title: "Info",
        content: {
          element: "div",
          attributes: {
            innerHTML: `
              <div style="text-align: left">
                <p><strong>Dear Customer,</strong></p>
                <p>
                  Unable to retrieve the policy PDF from Insurance Company, please retry after 30 minutes. If in case PDF not available, send us an email at 
                  <a href="mailto:${
                    theme_conf?.broker_config?.brokerSupportEmail ||
                    brokerEmailFunction()
                  }" style="color: #0000FF; text-decoration: underline;">${
              theme_conf?.broker_config?.brokerSupportEmail ||
              brokerEmailFunction()
            }</a> along with Enquiry ID.
                </p>
                <p><em>Thank you.</em></p>
              </div>
            `,
          },
        },
        icon: "info",
      }).then((result) => {
        if (result) {
          handleReset();
        }
      });
    }
  }, [submit, errorRehit]);

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

  //PDF Toaster
  const [pdfToaster, setPdfToaster] = useState(false);
  const [rehitpdfToaster, setRehitPdfToaster] = useState(false);
  const formRef = useRef(null);
  const proceed = () => {
    formRef.current.submit();
  };

  const Inputs = !_.isEmpty(policy?.redirection_data?.post_data_proceed) ? (
    Object.keys(policy?.redirection_data?.post_data_proceed).map((k, i) => {
      return (
        <input
          type="hidden"
          name={`${k}`}
          value={policy?.redirection_data?.post_data_proceed[`${k}`]}
        />
      );
    })
  ) : (
    <noscript />
  );

  const FORM = (
    <form
      ref={formRef}
      id="proceed-for-check"
      action={policy?.redirection_data?.proceed}
      method="POST"
    >
      {Inputs}
    </form>
  );
  //calling the api token status api to check if token is expired or not
  useEffect(() => {
    dispatch(TokenStatus({ userProductJourneyId: enquiry_id }));
    scrollToTop();
  }, []);

  useEffect(() => {
    if (policy?.pdfUrl) {
      setPdfToaster(true);
    }
  }, [policy.pdfUrl]);

  //if token is expired redirecting the user to the provided url in the redirection data
  useEffect(() => {
    if (!_.isEmpty(tokenStatus) && tokenStatus?.status === false) {
      //if tokenStatus is false it means token is expired
      reloadPage(tokenStatus?.redirection_data?.url);
    }
  }, [tokenStatus?.status]);
  //Session validation
  // useEffect(() => {
  //   if (!_.isEmpty(temp_data) && !_.isEmpty(policy)) {
  //     // if (isB2B(temp_data)) {
  //       let sessionStart = isB2B(temp_data, true)?.createdAt || temp_data?.journeyStage?.createdAt;
  //       let expiryStatus = isTokenExpired(sessionStart);
  //       console.log(sessionStart,"sessionStart");
  //       console.log(expiryStatus,"expiryStatus");
  //       if (expiryStatus) {
  //         reloadPage(
  //           policy?.redirection_data?.P ||
  //             policy?.redirection_data?.E ||
  //             policy?.redirection_data?.U ||
  //             theme_conf?.broker_config?.broker_asset
  //               ?.success_payment_url_redirection?.url
  //         );
  //       }
  //     // }
  //   }
  // }, [policy, temp_data]);

  //on success script
  useEffect(() => {
    if (
      !_.isEmpty(temp_data)
      //&&
      // (!_.isEmpty(policy) || !policyLoading)
    ) {
      let type = temp_data?.productSubTypeCode?.toLowerCase();
      //Analytics | Payment success tracking
      _paymentSuccessTracking(temp_data, policy);
      if (!storedId || (storedId && String(storedId) !== String(enquiry_id))) {
        _successTracking(type, temp_data, enquiry_id);
        // if there is no trace id in localStorage setting the value into there
        localStorage.setItem(`enquiry_id`, enquiry_id || temp_data?.traceId);
      }
      import.meta.env.VITE_BROKER === "TATA" &&
        dispatch(
          getNdslUrl({
            policy_no: policy?.policyNumber,
            email: temp_data?.emailId,
            contact: temp_data?.mobileNo,
            first_name: temp_data?.firstName,
            middle_name: "",
            last_name: temp_data?.lastName,
            section: "motor",
            company: temp_data?.selectedQuote?.companyName,
            companyAlias: temp_data?.selectedQuote?.companyAlias,
          })
        );
      //set rehit toaster if policy pdf is not present
      !policy?.pdfUrl && setRehitPdfToaster(true);
      let respData = {
        //user details
        userFname: temp_data?.firstName,
        userLname: temp_data?.lastName,
        userMobile: temp_data?.userMobile,
        emailId: temp_data?.emailId,
        //agent details
        agentDetails:
          !_.isEmpty(temp_data?.agentDetails) &&
          temp_data?.agentDetails &&
          temp_data?.agentDetails?.filter((el) => el?.sellerType !== "U"),
        //policy details
        policyStartDate: temp_data?.userProposal?.policyStartDate,
        policyEndDate: temp_data?.userProposal?.policyEndDate,
        policyNumber: policy?.policyNumber,
        proposalNumber: policy?.proposalNumber,
        //pdf url
        ...(policy?.pdfUrl && { pdfUrl: policy?.pdfUrl }),
        //IC details
        ic: temp_data?.selectedQuote?.companyName,
        icLogo: temp_data?.selectedQuote?.companyLogo,
        //product details
        productName: temp_data?.selectedQuote?.productName,
        transactionId: enquiry_id,
        ...(policy?.final_payable_amount && {
          final_payable_amount: policy?.final_payable_amount,
        }),
      };
      import.meta.env.VITE_BROKER === "GRAM" &&
        window?.Android &&
        window.Android.SendToPaymentSuccessPage(JSON.stringify(respData));
    }
  }, [policy]);

  //load prefill data
  useEffect(() => {
    if (enquiry_id) {
      dispatch(Prefill({ enquiryId: enquiry_id }, true));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id]);

  //email - trigger
  useEffect(() => {
    if (
      enquiry_id &&
      CardData?.owner?.email &&
      temp_data?.selectedQuote?.productName &&
      policy?.policyNumber
    )
      dispatch(
        ShareQuote({
          enquiryId: enquiry_id,
          notificationType: "all",
          domain: `http://${window.location.hostname}`,
          type: "paymentSuccess",
          emailId: CardData?.owner?.email,
          mobileNo: CardData?.owner?.mobileNumber,
          email: CardData?.owner?.email,
          to: `91${CardData?.owner?.mobileNumber}`,
          firstName: CardData?.owner?.firstName,
          lastName: CardData?.owner?.lastName,
          action: window.location.href,
          link: window.location.href,
          productName: temp_data?.selectedQuote?.productName,
          policyNumber: policy?.policyNumber,
          logo: getBrokerLogoUrl(),
        })
      );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id, temp_data, policy]);

  //mobile - trigger
  useEffect(() => {
    if (
      enquiry_id &&
      CardData?.owner?.mobileNumber &&
      temp_data?.selectedQuote?.productName &&
      policy?.policyNumber
    )
      dispatch(
        ShareQuote({
          enquiryId: enquiry_id,
          notificationType: "sms",
          domain: `http://${window.location.hostname}`,
          type: "policyGeneratedSms",
          emailId: CardData?.owner?.email,
          mobileNo: CardData?.owner?.mobileNumber,
          firstName: CardData?.owner?.firstName,
          lastName: CardData?.owner?.lastName,
          action: window.location.href,
          link: window.location.href,
          productName: temp_data?.selectedQuote?.productName,
          policyNumber: policy?.policyNumber,
          logo: getBrokerLogoUrl(),
        })
      );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id, temp_data, policy]);

  //whatsapp - trigger
  useEffect(() => {
    if (enquiry_id && CardData?.owner?.mobileNumber)
      import.meta.env.VITE_BROKER === "OLA" &&
        dispatch(
          TriggerWhatsapp({
            enquiryId: enquiry_id,
            domain: `http://${window.location.hostname}`,
            type: "paymentSuccess",
            notificationType: "whatsapp",
            firstName: CardData?.owner?.firstName,
            lastName: CardData?.owner?.lastName,
            mobileNo: `91${CardData?.owner?.mobileNumber}`,
            to: `91${CardData?.owner?.mobileNumber}`,
            url: window.location.href,
            action: window.location.href,
            link: window.location.href,
            logo: getBrokerLogoUrl(),
          })
        );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [policy]);

  //Url
  // useEffect(() => {
  //   if (enquiry_id)
  //     dispatch(
  //       Url({
  //         proposalUrl: window.location.href,
  //         quoteUrl: window.location.href
  //           ? window.location.href?.replace(/payment-success/g, "quotes")
  //           : "",
  //         userProductJourneyId: enquiry_id,
  //       })
  //     );
  //   // eslint-disable-next-line react-hooks/exhaustive-deps
  // }, [enquiry_id]);

  useEffect(() => {
    if (enquiry_id && !_.isEmpty(temp_data)) {
      dispatch(PolicyGen({ userProductJourneyId: enquiry_id }));
    } else {
      !enquiry_id &&
        swal("Error", "No enquiry id found", "error").then(() =>
          reloadPage(
            theme_conf?.broker_config?.broker_asset?.other_failure_url?.url ||
              UrlFn()
          )
        );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id, temp_data]);

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

  //onload previous data clear
  useEffect(() => {
    dispatch(clear("pdf"));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  //policy pdf download
  useEffect(() => {
    if (policy?.pdfUrl && !limit) {
      setLimit(true);
      downloadFile(`${policy?.pdfUrl}`, false, true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [policy?.pdfUrl]);

  const handleRedirection = () => {
    const { VITE_BROKER, VITE_API_BASE_URL, VITE_BASENAME } = import.meta.env;

    const { corporateVehiclesQuoteRequest, agentDetails, productSubTypeId } =
      temp_data || {};

    const { journeyType } = corporateVehiclesQuoteRequest || {};
    // Determine if the app is the Ola Driver App
    const isOlaDriverApp =
      VITE_BROKER === "OLA" && journeyType === "driver-app";
    // Check if the user is an agent based on agentDetails content
    const isAgent =
      agentDetails &&
      !_.isEmpty(agentDetails) &&
      (!_.isEmpty(agentDetails.find((o) => o.sellerType === "E")) ||
        !_.isEmpty(agentDetails.find((o) => o.sellerType === "P")));
    // Determine if the user is a customer
    const isCustomer = _isUserCustomer(
      enquiry_id,
      theme_conf?.broker_config?.pc_redirection
    );
    // Check for specific brokers
    const isBajaj = VITE_BROKER === "BAJAJ";
    const isRB = VITE_BROKER === "RB";
    const isUIB = VITE_BROKER === "UIB";

    // Handle redirection for Ola Driver App
    if (isOlaDriverApp) {
      reloadPage(
        VITE_API_BASE_URL === "https://api-ola-uat.fynity.in/api"
          ? "https://auth-repose-azure.stg.corp.olacabs.com/olamoney/kyc-web/wallet/driver/crosssell-dashboard"
          : "https://supply-api.olacabs.com/crosssell-dashboard"
      );
    } else if (window?.JSBridge) {
      // If JSBridge exists, call popWindow to handle redirection
      window.JSBridge.call(
        "paytmOpenDeeplink",
        {
          deeplink:
            "paytmmp://sflanding?url=https://storefront.paytm.com/v2/h/insurance-home&title=Insurance&backbtn=true&vertical=Insurance",
          popWindow: true,
        },
        function (result) {
          console.log(result);
        }
      );
    } else if (!isAgent || isCustomer || isRB || isUIB) {
      // Handle redirection based on various conditions
      if (isBajaj) {
        // Redirect for Bajaj
        reloadPage(Bajaj_rURL(true));
      } else if (policy?.redirection_data?.U) {
        // Redirect based on policy data if available
        reloadPage(policy.redirection_data.U);
      } else {
        // Default redirection logic based on productSubTypeId
        reloadPage(
          `${window.location.origin}${
            VITE_BASENAME !== "NA" ? `/${VITE_BASENAME}` : ""
          }/${
            Number(productSubTypeId) === 1
              ? "car"
              : Number(productSubTypeId) === 2
              ? "bike"
              : "cv"
          }/lead-page`
        );
      }
    } else {
      // Fallback redirection logic
      reloadPage(
        policy?.redirection_data?.P ||
          policy?.redirection_data?.E ||
          theme_conf?.broker_config?.broker_asset
            ?.success_payment_url_redirection?.url ||
          UrlFn()
      );
    }

    // Additional redirection handling for Android devices
    if (window?.Android) {
      window.Android.SendToHomePage("Redirecting to homepage");
    }
  };

  return (
    <>
      <>
        {!_.isEmpty(temp_data) && !loading && !_.isEmpty(policy) ? (
          policy?.status === "FAILURE" && !policy?.pdfUrl ? (
            <>
              <Row className="text-center w-100 mx-auto">
                <div
                  className="mt-4 d-flex flex-column justify-content-center w-100"
                  style={{ paddingTop: "80px" }}
                >
                  <p
                    className="text-center w-100 policy_text"
                    style={{ fontSize: "1.1rem", color: "#006600" }}
                  >
                    It seems your CKYC verification is not complete.
                    {!policy?.redirection_data?.kyc_url ? (
                      "No url provided by insurance company for CKYC verification.Please contact partner."
                    ) : (
                      <>
                        <a
                          target="_blank"
                          href={policy?.redirection_data?.kyc_url}
                          id="policyPdfHref"
                          style={{
                            textDecoration: "underline",
                            cursor: "pointer",
                          }}
                        >
                          Click Here
                        </a>{" "}
                        to complete.
                      </>
                    )}
                  </p>
                  {temp_data?.selectedQuote?.companyAlias !== "acko" && (
                    <>
                      <p
                        className="text-center w-100 policy_text"
                        style={{ fontSize: "1.1rem", color: "#006600" }}
                      >
                        OR
                      </p>
                      {FORM}
                      <p
                        className="text-center w-100 policy_text"
                        style={{ fontSize: "1.1rem", color: "#006600" }}
                      >
                        <span
                          onClick={proceed}
                          style={{
                            textDecoration: "underline",
                            cursor: "pointer",
                          }}
                        >
                          Proceed
                        </span>{" "}
                        if already verified.
                      </p>
                    </>
                  )}
                </div>
              </Row>
            </>
          ) : (
            <>
              {policy?.created_at ? (
                <BadgeContainer lessthan767={lessthan767} variant="success">
                  {policy?.created_at}
                </BadgeContainer>
              ) : temp_data?.userProposal?.proposalDate ? (
                <BadgeContainer lessthan767={lessthan767} variant="success">
                  {`Proposal Creation : ${temp_data?.userProposal?.proposalDate}`}
                </BadgeContainer>
              ) : (
                <noscript />
              )}
              <Row className="text-center w-100 mx-auto">
                <Top className="mx-auto" style={{ width: "50%" }}>
                  <div className="mt-4 d-flex justify-content-center w-100">
                    <img
                      src={`${
                        import.meta.env.VITE_BASENAME !== "NA"
                          ? `/${import.meta.env.VITE_BASENAME}`
                          : ""
                      }/assets/images/like.${
                        import.meta.env.VITE_BROKER === "RB" ? "png" : "svg"
                      }`}
                      alt="errImg"
                      height={
                        import.meta.env.VITE_BROKER === "RB" ? "80" : "100"
                      }
                      width={
                        import.meta.env.VITE_BROKER === "RB" ? "80" : "100"
                      }
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
                    {!!policy?.custom_message ? (
                      <p
                        className="text-center w-100 policy_text"
                        style={{ fontSize: "1.1rem", color: "#006600" }}
                      >
                        {policy?.custom_message}
                      </p>
                    ) : (
                      <p
                        className="text-center w-100 policy_text"
                        style={{ fontSize: "1.1rem", color: "#006600" }}
                      >
                        {`Your${
                          temp_data?.selectedQuote?.companyName
                            ? ` ${temp_data?.selectedQuote?.companyName}`
                            : ""
                        } policy${
                          policy?.policyNumber || policy?.proposalNumber
                            ? ` with ${
                                policy?.policyNumber ? "policy" : "proposal"
                              } number "${
                                policy?.policyNumber || policy?.proposalNumber
                              }"`
                            : ""
                        } has been issued & a soft copy will be shared to your registered email address shortly.`}
                      </p>
                    )}

                    {((import.meta.env.VITE_BROKER === "RB" && icrm) ||
                      import.meta.env.VITE_BROKER !== "RB") && (
                      <>
                        <p
                          className="text-center w-100 mt-2 content_text email_contact_text"
                          style={{ fontSize: "1.1rem" }}
                        >
                          {ContentFn()}
                        </p>
                        {policy?.redirection_data?.kyc_url ? (
                          temp_data?.selectedQuote?.companyAlias !== "sbi" ? (
                            <>
                              <p
                                className="text-center w-100 mt-2 content_text email_contact_text"
                                style={{ fontSize: "1.1rem" }}
                              >
                                Customer is not CKYC verified, kindly proceed
                                with Manual CKYC otherwise Policy will get
                                cancelled after couple of days of issuance and
                                claim will not be settled.
                              </p>
                              <p
                                className="text-center w-100 mt-2 content_text email_contact_text"
                                style={{ fontSize: "1.1rem" }}
                              >
                                <a
                                  target="_blank"
                                  href={policy?.redirection_data?.kyc_url || ""}
                                  style={{
                                    textDecoration: "underline",
                                    cursor: "pointer",
                                  }}
                                >
                                  Click Here to complete CKYC
                                </a>
                              </p>
                            </>
                          ) : (
                            <noscript />
                          )
                        ) : (
                          <p
                            className="text-center w-100 mt-2 content_text email_contact_text"
                            style={{ fontSize: "1.1rem" }}
                          >
                            Please note that CKYC is mandatory from 01/01/2023
                            as per guidelines. Policy purchased without CKYC
                            will be cancelled.
                          </p>
                        )}
                        {ndslUrl?.url && false && (
                          <>
                            <p
                              className="text-center w-100 mt-2 content_text email_contact_text"
                              style={{ fontSize: "1.1rem" }}
                            >
                              You can now manage all your insurance policies
                              under a single log-in account known as Electronic
                              Insurance Account (eIA). Opening an eIA is free.
                            </p>

                            <p
                              className="text-center w-100 mt-2 content_text email_contact_text"
                              style={{ fontSize: "1.1rem" }}
                            >
                              {`To open an eIA please click on the below link: ${ndslUrl?.url}`}
                            </p>
                          </>
                        )}
                        <Thank
                          className="text-center w-100 mt-2 thank_text"
                          style={{ fontSize: "1.1rem" }}
                        >
                          Thank you for contacting us.
                        </Thank>
                      </>
                    )}
                  </div>
                  {policy?.pdfUrl &&
                  temp_data?.corporateVehiclesQuoteRequest?.journeyType !==
                    "driver-app" ? (
                    <div
                      className="mb-4 d-flex justify-content-center w-100"
                      style={{ marginTop: "-10px" }}
                    >
                      <p>
                        If download doesn't start automatically then{" "}
                        <span className="link">
                          <Btn
                            // style={{ marginTop: "-16px", marginLeft: "-9px" }}
                            style={{
                              all: "unset",
                              cursor: "pointer",
                            }}
                            variant="link"
                            onClick={() => {
                              downloadFile(policy?.pdfUrl, false, true);
                            }}
                          >
                            click here.
                          </Btn>
                        </span>
                      </p>
                    </div>
                  ) : (
                    <div
                      className="mb-4 d-flex justify-content-center w-100"
                      style={{ marginTop: "-10px" }}
                    >
                      <StyleP
                        lessthan767={lessthan767}
                        // style={{ color: "black", fontSize: lessthan767 && "0.7rem" }}
                        // variant="link"
                        // onClick={() => {
                        //   downloadFile(policy?.pdfUrl, false, true);
                        // }}
                      >
                        Thank You for the transaction. Your policy will be
                        generated within 48 - 72 hours.
                      </StyleP>
                    </div>
                  )}
                  {import.meta.env.VITE_BROKER === "RB" && !icrm && (
                    <>
                      {policy?.redirection_data?.kyc_url ? (
                        temp_data?.selectedQuote?.companyAlias !== "sbi" ? (
                          <div
                            style={{ marginTop: "-20px", marginBottom: "30px" }}
                          >
                            <p
                              className="text-center w-100 content_text email_contact_text"
                              style={{ fontSize: "1.1rem" }}
                            >
                              Customer is not CKYC verified, kindly proceed with
                              Manual CKYC otherwise Policy will get cancelled
                              after couple of days of issuance and claim will
                              not be settled.
                            </p>
                            <p
                              className="text-center w-100 mt-2 content_text email_contact_text"
                              style={{ fontSize: "1.1rem" }}
                            >
                              <a
                                target="_blank"
                                href={policy?.redirection_data?.kyc_url || ""}
                                style={{
                                  textDecoration: "underline",
                                  cursor: "pointer",
                                }}
                              >
                                Click Here to complete CKYC
                              </a>
                            </p>
                          </div>
                        ) : (
                          <noscript />
                        )
                      ) : (
                        <p
                          className="text-center w-100 mt-2 content_text email_contact_text"
                          style={{ fontSize: "1.1rem" }}
                        >
                          Please note that CKYC is mandatory from 01/01/2023 as
                          per guidelines. Policy purchased without CKYC will be
                          cancelled
                        </p>
                      )}
                      <Heading>
                        {lessthan767
                          ? "You can earn more by selling"
                          : "Recommended business to earn more"}
                      </Heading>
                      {!lessthan767 ? (
                        <CardContainer>
                          <Card
                            card1
                            onClick={() =>
                              reloadPage(
                                "https://health.renewbuy.com/input/basic-details"
                              )
                            }
                          >
                            <CardIcon
                              src={`${
                                import.meta.env.VITE_BASENAME !== "NA"
                                  ? `/${import.meta.env.VITE_BASENAME}`
                                  : ""
                              }/assets/images/healthIns.png`}
                            />
                            <p>Health Insurance</p>
                            <small>You can earn</small>
                            <br />
                            <h5>₹ 1200*</h5>
                          </Card>
                          <Card card2>
                            <CardIcon
                              src={`${
                                import.meta.env.VITE_BASENAME !== "NA"
                                  ? `/${import.meta.env.VITE_BASENAME}`
                                  : ""
                              }/assets/images/life.png`}
                            />
                            <p>Life Insurance</p>
                            <small>You can earn</small>
                            <br />
                            <h5>₹ 4000*</h5>
                          </Card>
                          <p
                            style={{
                              background: "white",
                              color: "#9C9C9C",
                              borderRadius: "50%",
                              padding: "3px 5px",
                            }}
                          >
                            Or
                          </p>
                          <Card
                            onClick={() =>
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
                              )
                            }
                          >
                            <CardIcon
                              motor
                              src={`${
                                import.meta.env.VITE_BASENAME !== "NA"
                                  ? `/${import.meta.env.VITE_BASENAME}`
                                  : ""
                              }/assets/images/motor.png`}
                            />
                            <p>Motor Policy</p>
                            <small>Issue another</small>
                          </Card>
                        </CardContainer>
                      ) : (
                        <Carousel />
                      )}
                    </>
                  )}
                  {import.meta.env.VITE_BROKER === "RB" && !icrm && (
                    <>
                      {lessthan767 ? (
                        <p
                          style={{
                            fontSize: "9px",
                            color: "	#A8A8A8",
                            marginTop: "30px",
                            marginBottom: "-2px",
                          }}
                        >
                          T&C*
                        </p>
                      ) : (
                        <noscript />
                      )}
                      <p style={{ fontSize: "9px", color: "	#A8A8A8" }}>
                        {`${
                          lessthan767 ? "" : "T&C* "
                        }This amount is indicative, based on 15% Payout on a net
                premium of Rs. 8000 for health and 40% payout on Life Term plan
                of net premium Rs.10000.`}
                      </p>
                    </>
                  )}
                  {import.meta.env.VITE_BROKER === "RB" && !icrm && (
                    <>
                      <p
                        className="text-center w-100 mt-2 content_text email_contact_text"
                        style={{ fontSize: "1.1rem" }}
                      >
                        {theme_conf?.broker_config ? (
                          <>
                            In case of any further requirements, please contact
                            us at
                            <b> {theme_conf?.broker_config?.email}</b> or call
                            us at our number
                            <b> {theme_conf?.broker_config?.phone} </b>
                          </>
                        ) : (
                          ContentFn()
                        )}
                      </p>
                      <Thank
                        className="text-center w-100 mt-2 thank_text"
                        style={{ fontSize: "1.1rem" }}
                      >
                        Thank you for contacting us.
                      </Thank>
                    </>
                  )}
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
                          onClick={() => handleRedirection()}
                        >
                          Go To Homepage
                        </Button>
                        {theme_conf?.broker_config?.feedbackModule && (
                          <Button
                            onClick={() =>
                              history.push(`/feedback?enquiry_id=${enquiry_id}`)
                            }
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
                            shadow={"none"}
                            style={{ marginLeft: "20px" }}
                          >
                            Give a feedback
                          </Button>
                        )}
                      </div>
                    )}
                </Top>
                <GlobalStyle />
              </Row>
              <FloatButton />
            </>
          )
        ) : (
          <div style={{ height: "100vh" }}>
            <Loader scaleHeight />
          </div>
        )}
      </>
      <Toaster
        Theme={{}}
        callToaster={pdfToaster}
        setCall={setPdfToaster}
        content={`Please click the download button in case the download has not initiated automatically.`}
        buttonText={"Download"}
        setEdit={() => downloadFile(policy?.pdfUrl, false, true)}
        autoClose={30000}
      />
      <Toaster
        Theme={{}}
        callToaster={rehitpdfToaster}
        setCall={setRehitPdfToaster}
        content={`Generating the policy PDF may take some time.`}
        buttonText={"Check status"}
        setEdit={() => rehitButton()}
        autoClose={30000}
      />
    </>
  );
};

const GlobalStyle = createGlobalStyle`
.link {
  color: ${({ theme }) => theme?.Payment?.color} !important;
  &:hover {
    text-decoration: underline;
  }
}

.btn-link{
  color: ${({ theme }) =>
    import.meta.env.VITE_BROKER === "UIB" && theme?.Payment?.color}!important;

}
.email_contact_text{
  color : ${({ theme }) =>
    import.meta.env.VITE_BROKER === "UIB" &&
    theme.floatButton?.floatColor &&
    theme.floatButton?.floatColor}!important;
}
`;

const BadgeContainer = styled(Badge)`
  float: right;
  position: relative;
  top: 10px;
  right: ${({ lessthan767 }) => (lessthan767 ? "39px" : "60px")};
  font-size: ${({ lessthan767 }) => (lessthan767 ? "10px" : "")};
  background-color: ${({ theme }) => theme?.proposalProceedBtn?.hex1}};
`;

const StyleP = styled.p`
  color: ${({ theme }) =>
    import.meta.env.VITE_BROKER === "UIB" && theme?.floatButton?.floatColor
      ? theme.floatButton?.floatColor
      : "black"}!important;
  font-size: ${({ lessthan767 }) => lessthan767 && "0.7rem"};
`;

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
    .policy_text {
      font-size: 0.9rem !important;
    }
    .content_text {
      font-size: 0.8rem !important;
    }
    .thank_text {
      font-size: 0.8rem !important;
    }
    .linkLine1 {
      font-size: 0.8rem !important;
    }
  }
`;

const Thank = styled.p`
  color: ${({ theme }) => theme.Payment?.color || "rgb(189,212,0)"};
`;

const Heading = styled.h4`
  margin: -15px 0px 30px 0px;
  color: gray;
`;

const CardContainer = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 25px auto;
  height: 200px;
  width: 600px;
  border-radius: 15px;
  background: #292949;
  cursor: pointer;
`;

const Card = styled.div`
  height: 200px;
  width: 190px;
  color: #fff;
  padding: 15px;
  transition: 1s;
  &:hover {
    color: #fff;
    background-image: linear-gradient(
      to right,
      #ffb76b 0%,
      #ffa73d 30%,
      #ff7c00 60%,
      #ff7f04 100%
    );
    border-radius: 15px;
    transform: scale(1.1);
  }
`;

const CardIcon = styled.img`
  height: 36px;
  padding: ${(props) => props.motor && "4px 0px"};
  width: ${(props) => (props.motor ? "60px" : "36px")};
  margin: 15px auto;
`;

export default PaymentSuccess;
