import React, { useState, useEffect, useRef } from "react";
import { useDispatch, useSelector } from "react-redux";
import { Row, Col, Modal } from "react-bootstrap";
import "./otp.css";
import { Button, getBrokerLogoUrl } from "components";
import { useMediaPredicate } from "react-media-hook";
import { clear, VerifyOTP, VerifyCkycnum, ResentOtp } from "../proposal.slice";
import swal from "sweetalert";
import _ from "lodash";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { Enquiry } from "modules/Home/home.slice";
// prettier-ignore
import { CloseButton, Heading, ModalLeftContentDiv,
         ModalRightContentDiv, Paragraph,ResendBtn,
       } from "./otpStyle";
import { useLocation } from "react-router";
// import { getIcogoUrl } from "components/Details-funtion-folder/DetailsHolder";
const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const OTPPopup = (props) => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan320 = useMediaPredicate("(max-width: 320px)");
  const dispatch = useDispatch();
  const { onHide, otpSuccess, lead_otp, otpData, show } = props;
  const {
    verifyOtp,
    otpError,
    verifyCkycnum,
    temp_data: TempData,
    resentOtp,
  } = useSelector((state) => state.proposal);
  const {
    enquiry_id,
    loading: r_loading,
    share,
  } = useSelector((state) => state.home);

  const location = useLocation();
  const loc = location.pathname ? location.pathname.split("/") : "";
  const query = new URLSearchParams(location.search);
  const p_enquiry_id = query.get("enquiry_id");
  //temp card data
  const CardData = !_.isEmpty(TempData)
    ? TempData?.userProposal?.additonalData
      ? TempData?.userProposal?.additonalData
      : {}
    : {};

  const [loading, setLoading] = useState(false);
  const [resendSeconds, setResendSeconds] = useState(
    enquiry_id?.resendOtpTimeLimit
      ? enquiry_id?.resendOtpTimeLimit
      : share?.resendOtpTimeLimit
      ? share?.resendOtpTimeLimit
      : resentOtp?.resendOtpTimeLimit
  );

  useEffect(() => {
    if (
      // share?.resendOtpTimeLimit &&
      // !resendSeconds &&
      // resendSeconds * 1 !== 0 &&
      loc[2] === "proposal-page"
    ) {
      setResendSeconds(share?.resendOtpTimeLimit);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [share?.resendOtpTimeLimit, show]);

  // time formatter
  const minutes = Math.floor(resendSeconds / 60);
  const remainingSeconds = resendSeconds % 60;

  const otp1 = useRef();
  const otp2 = useRef();
  const otp3 = useRef();
  const otp4 = useRef();
  const otp5 = useRef();
  const otp6 = useRef();

  // function to handle number only input and focus on previous field on backspace
  const numOnly = (event) => {
    let key = event.keyCode || event.which;
    let numericKeys = (key >= 48 && key <= 57) || (key >= 96 && key <= 105);
    let allowedKeys = [8, 9, 13, 16, 17, 20, 35, 36, 37, 39].includes(key);
    if (event.shiftKey === false && (numericKeys || allowedKeys)) {
      if (numericKeys) {
        switch (event.target.name) {
          case "otp1":
            otp1.current.value = event.key;
            break;
          case "otp2":
            otp2.current.value = event.key;
            break;
          case "otp3":
            otp3.current.value = event.key;
            break;
          case "otp4":
            otp4.current.value = event.key;
            break;
          case "otp5":
            otp5.current.value = event.key;
            break;
          case "otp6":
            otp6.current.value = event.key;
            break;
          default:
            // Handle other cases
            break;
        }
      } else if (key === 8 && event.target.value === "") {
        // Handle backspace
        switch (event.target.name) {
          case "otp2":
            otp1.current.focus();
            break;
          case "otp3":
            otp2.current.focus();
            break;
          case "otp4":
            otp3.current.focus();
            break;
          case "otp5":
            otp4.current.focus();
            break;
          case "otp6":
            otp5.current.focus();
            break;
          default:
            otp1.current.focus();
            break;
        }
      }
    } else {
      event.preventDefault();
    }
  };

  const nextKey = (e) => {
    if (e.target.value) {
      if (e.target.name === "otp1") {
        otp2?.current?.focus && otp2.current.focus();
      }
      if (e.target.name === "otp2") {
        otp3?.current?.focus && otp3.current.focus();
      }
      if (e.target.name === "otp3") {
        otp4?.current?.focus && otp4.current.focus();
      }
      if (e.target.name === "otp4") {
        otp5?.current?.focus && otp5.current.focus();
      }
      if (e.target.name === "otp5") {
        otp6?.current?.focus && otp6.current.focus();
      }
    }
  };

  const {
    fullName,
    firstName,
    lastName,
    emailId,
    mobileNo,
    whatsappNo,
    ...other
  } = otpData || {};

  //verify OTP
  const otpEnter = () => {
    if (
      !props?.ckyc &&
      lead_otp &&
      otp1.current.value &&
      otp2.current.value &&
      otp3.current.value &&
      otp4.current.value
    ) {
      dispatch(
        Enquiry(
          {
            otp:
              otp1.current.value +
              otp2.current.value +
              otp3.current.value +
              otp4.current.value,
            firstName,
            lastName,
            fullName,
            emailId,
            mobileNo,
            whatsappNo,
            other,
          },
          true
        )
      );
    } else if (
      !props?.ckyc &&
      otp1.current.value &&
      otp2.current.value &&
      otp3.current.value &&
      otp4.current.value
    ) {
      setLoading(true);
      dispatch(
        VerifyOTP(
          {
            enquiryId: props?.enquiry_id,
            otp:
              otp1.current.value +
              otp2.current.value +
              otp3.current.value +
              otp4.current.value,
          },
          setLoading
        )
      );
    } else if (
      !!props?.ckyc &&
      props?.otp_id &&
      otp1.current.value &&
      otp2.current.value &&
      otp3.current.value &&
      otp4.current.value &&
      otp5.current.value &&
      otp6.current.value
    ) {
      setLoading(true);
      dispatch(
        VerifyCkycnum(
          {
            companyAlias: props?.companyAlias,
            mode: "otp",
            otpId: props?.otp_id,
            enquiryId: props?.enquiry_id,
            otp: Number(
              otp1.current.value +
                otp2.current.value +
                otp3.current.value +
                otp4.current.value +
                otp5.current.value +
                otp6.current.value
            ),
          },
          setLoading
        )
      );
    }
  };
  const pasteHandler = (e) => {
    e.preventDefault();
  };

  useEffect(() => {
    if (enquiry_id?.enquiryId) {
      otpSuccess();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id?.enquiryId]);

  //on Error
  useEffect(() => {
    if (otpError) {
      swal(
        "Error",
        props?.enquiry_id
          ? `${`Trace ID:- ${
              TempData?.traceId ? TempData?.traceId : props?.enquiry_id
            }.\n Error Message:- ${otpError}`}`
          : otpError,
        "error"
      );
    }

    if (verifyOtp) {
      if (!props?.ckyc) {
        otpSuccess();
        onHide();
      }
    } else if (props?.ckyc && props?.companyAlias === "tata_aig") {
      if (verifyCkycnum?.verification_status) {
        const data = verifyCkycnum;
        otpSuccess(data?.isBreakinCase === "Y" ? data : false);
        onHide();
      } else if (verifyCkycnum && !verifyCkycnum?.verification_status) {
        swal("Error", verifyCkycnum?.message || "verification failed", "error");
        onHide();
      }
    }

    return () => {
      dispatch(clear("verifyOtp"));
      dispatch(clear("verifyCkycnum"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [otpError, verifyOtp, verifyCkycnum]);

  useEffect(() => {
    let interval = null;
    if (resendSeconds > 0) {
      interval = setInterval(() => {
        setResendSeconds(resendSeconds - 1);
      }, 1000);
    } else {
      clearInterval(interval);
    }
    return () => clearInterval(interval);
  }, [resendSeconds]);

  const handleResend = () => {
    const data = {
      emailId: CardData?.owner?.email
        ? CardData?.owner?.email
        : otpData?.emailId,
      mobileNo: CardData?.owner?.mobileNumber
        ? CardData?.owner?.mobileNumber
        : otpData?.mobileNo,
      otpType: loc[2] === "proposal-page" ? "proposalOtp" : "leadOtp",
      ...(loc[2] === "proposal-page" && {
        notificationType: "email",
        enquiryId: p_enquiry_id,
        applicableNcb: TempData?.corporateVehiclesQuoteRequest?.applicableNcb,
        policyEndDate: TempData?.selectedQuote?.policyEndDate,
        policyStartDate: TempData?.selectedQuote?.policyStartDate,
        premiumAmount: TempData?.quoteLog?.finalPremiumAmount,
        productName: TempData?.selectedQuote?.productName,
        registrationNumber: CardData?.vehicle?.vehicaleRegistrationNumber,
        link: window.location.href.replace(/proposal-page/g, "proposal-page"),
      }),
      logo: otpData?.logo ? otpData?.logo : getBrokerLogoUrl(),
      ic_logo: TempData?.selectedQuote?.companyLogo,
      ic_name: TempData?.selectedQuote?.companyName,
      domain: `http://${window.location.hostname}`,
      firstName: otpData?.firstName
        ? otpData?.firstName
        : CardData?.owner?.firstName,
      lastName: otpData?.lastName
        ? otpData?.lastName
        : CardData?.owner?.lastName,
    };
    dispatch(ResentOtp(data));
    setResendSeconds(
      enquiry_id?.resendOtpTimeLimit
        ? enquiry_id?.resendOtpTimeLimit
        : share?.resendOtpTimeLimit
        ? share?.resendOtpTimeLimit
        : resentOtp?.resendOtpTimeLimit
    );
  };

  return (
    <Modal
      {...props}
      size="lg"
      aria-labelledby="contained-modal-title-vcenter"
      dialogClassName="my-modal"
      backdrop="static"
    >
      <Modal.Body
        style={{
          padding: "85px 15px 50px 15px",
          background: "white",
          borderRadius: "5px",
        }}
      >
        <CloseButton onClick={props.onHide}>×</CloseButton>
        <Row>
          <ModalLeftContentDiv md={12} lg={5} xl={5} sm={12}>
            <img
              src={`${
                import.meta.env.VITE_BASENAME !== "NA"
                  ? `/${import.meta.env.VITE_BASENAME}`
                  : ""
              }/assets/images/RFQ/otp.png`}
              alt="otp"
            />
          </ModalLeftContentDiv>
          <Col md={12} lg={7} xl={7} sm={12}>
            <div>
              <ModalRightContentDiv style={{ marginBottom: "25px" }}>
                <Heading>Please enter the OTP</Heading>
              </ModalRightContentDiv>
              <ModalRightContentDiv>
                <Paragraph>
                  {props?.ckyc ? (
                    "OTP has been sent to your registered mobile number."
                  ) : props?.lead_otp ? (
                    <>
                      OTP has been sent to <b>{`${props?.mobileNumber}`}</b>
                    </>
                  ) : import.meta.env.VITE_BROKER === "PAYTM" ? (
                    <>
                      OTP has been sent to <b>{`${props?.mobileNumber}`}</b>
                      &nbsp;&nbsp;
                    </>
                  ) : (
                    <>
                      OTP has been sent to <b>{`${props?.mobileNumber}`}</b> and{" "}
                      <b>{`${props?.email}`}</b>
                      &nbsp;&nbsp;
                    </>
                  )}
                </Paragraph>
              </ModalRightContentDiv>
              <ModalRightContentDiv
                style={{ marginLeft: lessthan320 && "15px" }}
                lessthan767={lessthan767}
                ckyc={props?.ckyc}
              >
                <input
                  name="otp1"
                  ref={otp1}
                  maxLength="1"
                  onKeyUp={nextKey}
                  type="tel"
                  onKeyDown={numOnly}
                  onPaste={pasteHandler}
                />
                <input
                  name="otp2"
                  ref={otp2}
                  maxLength="1"
                  onKeyUp={nextKey}
                  type="tel"
                  onKeyDown={numOnly}
                  onPaste={pasteHandler}
                />
                <input
                  name="otp3"
                  ref={otp3}
                  maxLength="1"
                  onKeyUp={nextKey}
                  type="tel"
                  onKeyDown={numOnly}
                  onPaste={pasteHandler}
                />
                <input
                  name="otp4"
                  ref={otp4}
                  maxLength="1"
                  onKeyUp={nextKey}
                  type="tel"
                  onKeyDown={numOnly}
                  onPaste={pasteHandler}
                />
                {props?.ckyc && (
                  <>
                    <input
                      name="otp5"
                      ref={otp5}
                      maxLength="1"
                      onKeyUp={nextKey}
                      type="tel"
                      onKeyDown={numOnly}
                      onPaste={pasteHandler}
                    />
                    <input
                      name="otp6"
                      ref={otp6}
                      maxLength="1"
                      onKeyUp={nextKey}
                      type="tel"
                      onKeyDown={numOnly}
                      onPaste={pasteHandler}
                    />
                  </>
                )}
              </ModalRightContentDiv>
              {!props?.ckyc ? (
                <ModalRightContentDiv>
                  {resendSeconds === 0 ? (
                    <ResendBtn onClick={handleResend}>Resend OTP</ResendBtn>
                  ) : (
                    <p>{`Resend OTP in ${
                      minutes > 0 ? `${minutes * 1} minutes and` : ""
                    } ${remainingSeconds * 1} seconds`}</p>
                  )}
                </ModalRightContentDiv>
              ) : (
                <p style={{ fontSize: "13px" }}>
                  In case of Aadhar based OTP process, the OTP shall be
                  delivered on the mobile number registered with AADHAR UIDAI
                  Authority.
                </p>
              )}

              <ModalRightContentDiv>
                {" "}
                <Button
                  onClick={otpEnter}
                  disabled={loading}
                  type="submit"
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
                  borderRadius="5px"
                  color="white"
                >
                  <text
                    style={{
                      fontSize: "15px",
                      padding: "-20px",
                      margin: "-20px -5px -20px -5px",
                      fontWeight: "400",
                    }}
                  >
                    {loading || r_loading ? "Please Wait..." : "Submit"}
                  </text>
                </Button>
              </ModalRightContentDiv>
            </div>
          </Col>
        </Row>
      </Modal.Body>
    </Modal>
  );
};

export default OTPPopup;
