import React from "react";
import ReactGA from "react-ga4";
import styled, { createGlobalStyle } from "styled-components";
import { ToastContainer } from "react-toastify";
import { Col } from "react-bootstrap";
import _ from "lodash";

//GA Event switch
export const GA_Event = (ga_event, type) => {
  switch (ga_event) {
    case "submit-click":
      ReactGA.event({
        category: `${type}_Insurance_form_button_click`,
        event: `${type}_Insurance_form_button_click`,
        action: "Click - Submit",
        action_type: "Click - Submit",
        business_lob: "Insurance",
        journey_status: "Get Quote Stage",
        input_details: "OTP generated",
      });
      break;
    case "submit-success":
      ReactGA.event({
        category: `${type}_Insurance_form_submit_success`,
        event: `${type}_Insurance_form_submit_success`,
        action: "Click - Submit",
        action_type: "Click - Submit",
        business_lob: "Insurance",
        journey_status: "Get Quote Stage",
        input_details: "OTP successfully submitted",
      });
      break;
    case "call-me":
      ReactGA.event({
        category: "request_call_back_form_click",
        event: "request_call_back_form_click",
        action: "Click - Call me",
        action_type: "Click - Call me",
        business_lob: "Insurance",
        journey_status: "Feedback Stage",
      });
      break;
    default:
      break;
  }
};

const notify = (toast) => {
  toast(
    "Congratulations! Your work email ID is eligible for exclusive discounts and corporate offers.",
    "Custom style",
    {
      toastId: "customId",
      className: "black-background",
      bodyClassName: "grow-font-size",
    }
  );
};

const WhatsAppInput = (lessthan767, sameNumber, setSameNumber) => (
  <Col sm="12" md={8} lg={8} xl={8} className="d-flex mx-auto">
    <SubmitDiv
      style={
        lessthan767
          ? { fontSize: "12px !important" }
          : { position: "relative", top: "-10px", left: "12px" }
      }
    >
      <label className="checkbox-container">
        <input
          className="bajajCheck"
          defaultChecked={false}
          name="accept"
          type="checkbox"
          value={sameNumber}
          checked={sameNumber}
          onChange={(e) => {
            setSameNumber(e.target.checked);
          }}
        />
        <span
          style={{ height: "18px!important", width: "18px!important" }}
          className="checkmarkwp checkmark"
        ></span>
      </label>
      <p
        className="whatsappNumber"
        style={
          lessthan767
            ? {
                fontSize: "12px !important",
                position: "relative",
                top: "-2px",
                zIndex: "-1",
              }
            : {}
        }
      >
        <span style={lessthan767 ? { fontSize: "12px !important" } : {}}>
          Whatsapp number same as mobile number
        </span>{" "}
      </p>
    </SubmitDiv>
  </Col>
);

const FinalSubmit = (consent, setConsent) => (
  <Col
    sm="12"
    md="12"
    lg="12"
    xl="12"
    className="d-flex justify-content-center"
  >
    <SubmitDiv>
      <label className="checkbox-container">
        <input
          className="bajajCheck"
          defaultChecked={false}
          name="accept"
          type="checkbox"
          value={consent}
          checked={consent}
          onChange={(e) => {
            setConsent(e.target.checked);
          }}
        />
        <span className="checkmark"></span>
      </label>
      <p className="privacyPolicy">
        <span>I Agree to be contacted via</span>{" "}
        <i
          className="fab fa-whatsapp text-success"
          style={{ fontSize: "14px" }}
        ></i>{" "}
        <span>Whatsapp.</span>
      </p>
    </SubmitDiv>
  </Col>
);

const brokerList =
  import.meta.env.VITE_BROKER === "RB" ||
  import.meta.env.VITE_BROKER === "FYNTUNE";

export const ProcessName = (FullName, setValue) => {
  if (FullName) {
    let FullnameCheck = FullName.split(" ");
    if (!_.isEmpty(FullnameCheck) && FullnameCheck?.length === 1) {
      let fname = FullnameCheck[0];
      setValue("firstName", fname);
    }
    if (!_.isEmpty(FullnameCheck) && FullnameCheck?.length > 1) {
      let fname = FullnameCheck.slice(0, -1).join(" ");
      let lname = FullnameCheck.slice(-1)[0];
      setValue("firstName", fname);
      setValue("lastName", lname);
    } else {
      setValue("lastName", "");
    }
  }
};

//styles
const StyledH4 = styled.h4`
  font-size: ${import.meta.env.VITE_BROKER === "BAJAJ" ||
  import.meta.env.VITE_BROKER === "SPA" ||
  import.meta.env.VITE_BROKER === "KMD"
    ? "34px"
    : "36px"};
  color: ${({ theme }) => theme.regularFont?.fontColor || "rgb(74, 74, 74)"};
  ${import.meta.env.VITE_BROKER === "TATA" &&
  ` background: linear-gradient(to right, #00bcd4 0%, #ae15d4 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;`}

  font-family: ${({ theme }) =>
    theme.regularFont?.headerFontFamily || "sans-serif"};
  white-space: pre-wrap;
  max-width: 760px;
  @media (max-width: 767px) {
    font-size: 22px;
  }
  @media (max-width: 375px) {
    font-size: 20.5px;
  }
  @media (max-width: 360px) {
    font-size: 20px;
  }
`;

const GlobalStyle = createGlobalStyle`
  ${({ theme }) =>
    theme?.fontFamily &&
    ` .gRDOdS,.gRDOdS ~ label,.gRDOdS:not(:placeholder-shown) ~ label,.cdsZcX , .cdsZcX:not(:placeholder-shown) ~ label,.lgaHSq, .lgaHSq:not(:placeholder-shown) ~ label, .cdsZcX ~ label{
  font-family: ${theme?.fontFamily} !important;
}`}

body {
  // background: #EAEAEA !important;
}

.proceed-button:hover .eastIcon{
  padding-left:8px;
  transition:0.2s ease-in-out;
}
.lead_link, .lead_link:hover {
  color: ${({ theme }) => theme.links?.color || ""};
}
`;

const SubmitDiv = styled.div`
  .checkbox-container {
    display: block;
    position: relative;
    padding-left: 35px;
    cursor: pointer;
    font-size: 22px;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
  }
  .checkbox-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
  }
  .checkbox-container input:checked ~ .checkmark,
  .plan-card .checkbox-container input:checked ~ .checkmark {
    background-color: ${({ theme }) =>
      theme?.proposalProceedBtn?.hex1
        ? theme?.proposalProceedBtn?.hex1
        : "#268f05"};
  }
  .checkbox-container .checkmark {
    position: absolute;
    top: 2.1px !important;
    left: 0 !important;
    height: 20px;
    width: 20px;
    background-color: #eee;
    border: 1px solid #ddd;
    border-radius: 0;
  }
  .checkbox-container .checkmarkwp {
    position: absolute;
    top: 2.1px !important;
    left: 0 !important;
    height: 18px !important;
    width: 18px !important;
    background-color: #eee;
    border: 1px solid #ddd;
    border-radius: 0;
  }
  .checkbox-container input:checked ~ .checkmark:after {
    display: block;
  }
  .checkbox-container .checkmark:after {
    content: url(${import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ""}/assets/images/checkbox-select.png);
    left: 1px;
    top: -10px;
    width: 17px;
    height: 16px;
    position: absolute;
  }
  .privacyPolicy {
    padding-left: 40px;
    font-size: 13px;
    color: #545151;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `sans-serif`};
    text-align: justify;
    text-justify: inter-word;
  }
  .whatsappNumber {
    padding-left: 30px;
    font-size: 10px !important;
    color: #545151;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `sans-serif`};
    text-align: justify;
    text-justify: inter-word;
  }

  @media screen and (max-width: 993px) {
    .checkbox-container .checkmark:after {
      content: url(/assets/images/checkbox-select.png);
      left: 1px;
      width: 17px;
      height: 16px;
      position: absolute;
      color: #0000;
    }
  }
`;

const Ribbon = styled.button`
  cursor: default !important;
  background-color: ${({ theme }) =>
    theme?.leadPageBtn?.background3 || "#f2f7cc"};
  color: ${({ theme }) =>
    import.meta.env.VITE_BROKER === "UIB"
      ? theme?.leadPageBtn?.textColor
      : theme?.Registration?.otherBtn?.hex1 || "#006400"};
  @media (max-width: 767px) {
    font-size: 10.5px;
    padding: 10px 35px;
  }
`;

const Proceed = styled.div``;

const StyledContainer = styled(ToastContainer)`
  .Toastify__toast-container {
  }
  .Toastify__toast {
    width: 100%;
    background-color: ${({ theme }) =>
      theme?.leadPageBtn?.background3 || "#f2f7cc"};
    color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "UIB"
        ? theme?.leadPageBtn?.textColor
        : theme?.Registration?.otherBtn?.hex1 || "#006400"};
  }
  .Toastify__toast-body {
  }
  .Toastify__progress-bar {
  }
`;

// prettier-ignore
export const Extn = {
  GA_Event, notify, StyledH4, GlobalStyle, SubmitDiv,
  Ribbon, Proceed, StyledContainer, WhatsAppInput, FinalSubmit,
  brokerList
};

export const _GA_Event = (event, type) => {
  import.meta.env.VITE_BROKER === "BAJAJ" &&
    import.meta.env.VITE_BASENAME !== "NA" &&
    Extn.GA_Event("submit-click", type);
};

export const allowSkip = (token, theme_conf) =>
  !(
    (import.meta.env.VITE_BROKER === "BAJAJ" ||
      import.meta.env.VITE_BROKER === "GRAM" ||
      import.meta.env.VITE_BROKER === "HEROCARE") &&
    !token
  ) &&
  (!theme_conf?.broker_config?.lead_otp ||
    (import.meta.env.VITE_BROKER === "HEROCARE" && token) ||
    (import.meta.env.VITE_BROKER === "BAJAJ" &&
      import.meta.env.VITE_BASENAME === "NA")) &&
  import.meta.env.VITE_BROKER !== "TATA";

export const allowWithoutOTP = (token, theme_conf) => {
  return (
    !theme_conf?.broker_config?.lead_otp ||
    (import.meta.env.VITE_BROKER === "BAJAJ" &&
      import.meta.env.VITE_BASENAME === "NA") ||
    (import.meta.env.VITE_BROKER === "HEROCARE" && token)
  );
};
