import React from "react";
import { Row, Col, Form, Button as Btn } from "react-bootstrap";
import { Textbox, Button, Error } from "components";
import swal from "sweetalert";
import EastIcon from "@mui/icons-material/East";
import { numOnly, _haptics } from "utils";
import {
  Extn,
  allowSkip,
  allowWithoutOTP,
} from "modules/Home/steps/lead-page/helper";
//prettier-ignore
import { isFullNameValid, isEmailValid, isMobileNoValid, isWhatsappNoValid } from './validation';
import CorporateDiscount from "./corporate-discount";
import { _leadTrack } from "analytics/input-pages/lead-tracking";
import { TypeReturn } from "modules/type";

export const LeadForm = ({ btnDisable, setbtnDisable, token, ...rest }) => {
  // prettier-ignore
  const { 
    register, handleSubmit, watch, errors, 
    sameNumber,setSameNumber, lessthan767,
    setSkip, theme_conf, onSubmit, Theme,
    consent, setConsent, selected, trigger, type
} = rest;

  // prettier-ignore
  const { brokerList, WhatsAppInput, FinalSubmit } = Extn;
  const firstName = watch("firstName");
  const lastName = watch("lastName");
  const fullName = watch("fullName");
  const emailId = watch("emailId");
  const mobileNo = watch("mobileNo");
  const whatsappNo = watch("whatsappNo");

  const formData = {
    firstName,
    lastName,
    fullName,
    emailId,
    mobileNo,
    ...(brokerList && { whatsappNo: sameNumber ? mobileNo : whatsappNo }),
  };

  const _renderForm = () => {
    return (
      <Form
        onSubmit={(e) => {
          e.preventDefault();
        }}
      >
        <Row className={`w-100 d-flex no-wrap mt-5 mx-auto`}>
          <>
            <Col sm="12" md={8} lg={8} xl={8} className="mx-auto">
              <div className="w-100">
                <Textbox
                  lg
                  type="text"
                  id="fullname"
                  fieldName="Full name"
                  name="fullName"
                  placeholder=" "
                  fontWeight={"1000"}
                  onInput={(e) =>
                    (e.target.value =
                      e.target.value.length <= 1
                        ? ("" + e.target.value).toUpperCase()
                        : e.target.value)
                  }
                  register={register}
                  error={
                    errors.fullName?.message ||
                    errors.firstName?.message ||
                    errors.lastName?.message
                  }
                />
                {(errors.fullName || errors.firstName || errors.lastName) && (
                  <Error
                    style={{
                      marginTop: "-20px",
                      ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
                        textAlign: "left",
                        marginLeft: "18px",
                      }),
                    }}
                  >
                    {errors.fullName?.message ||
                      errors.firstName?.message ||
                      errors.lastName?.message}
                  </Error>
                )}
              </div>
              <input type="hidden" ref={register} name="firstName" />
              <input type="hidden" ref={register} name="lastName" />
            </Col>
            <Col sm="12" md={8} lg={8} xl={8} className="mx-auto">
              <div className="w-100">
                <Textbox
                  lg
                  type="text"
                  id="emailId"
                  fieldName="Email"
                  fontWeight={"1000"}
                  name="emailId"
                  placeholder=" "
                  register={register}
                  error={errors?.emailId}
                />
                {!!errors?.emailId && (
                  <Error
                    style={{
                      marginTop: "-20px",
                      ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
                        textAlign: "left",
                        marginLeft: "18px",
                      }),
                    }}
                  >
                    {errors?.emailId?.message}
                  </Error>
                )}
              </div>
            </Col>
          </>
          <Col sm="12" md={8} lg={8} xl={8} className="mx-auto">
            <div className="w-100">
              <Textbox
                lg
                type="tel"
                id="mobileNo"
                fieldName="Mobile No."
                fontWeight={"1000"}
                name="mobileNo"
                placeholder=" "
                register={register}
                error={errors?.mobileNo}
                maxLength="10"
                onKeyDown={numOnly}
              />
              {!!errors?.mobileNo && (
                <Error
                  style={{
                    marginTop: "-20px",
                    ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
                      textAlign: "left",
                      marginLeft: "18px",
                    }),
                  }}
                >
                  {errors?.mobileNo?.message}
                </Error>
              )}
            </div>
          </Col>
          {brokerList && !sameNumber && (
            <Col sm="12" md={8} lg={8} xl={8} className="mx-auto">
              <div className="w-100">
                <Textbox
                  lg
                  type="tel"
                  id="whatsappNo"
                  fieldName="Whatsapp No."
                  fontWeight={"1000"}
                  name="whatsappNo"
                  placeholder=" "
                  register={register}
                  error={errors?.whatsappNo}
                  maxLength="10"
                  onKeyDown={numOnly}
                  readOnly={sameNumber}
                />
                {!!errors?.whatsappNo && (
                  <Error
                    style={{
                      marginTop: "-20px",
                    }}
                  >
                    {errors?.whatsappNo?.message}
                  </Error>
                )}
              </div>
            </Col>
          )}
          {brokerList && WhatsAppInput(lessthan767, sameNumber, setSameNumber)}
          {["ACE", "ABIBL"].includes(import.meta.env.VITE_BROKER) &&
            FinalSubmit(consent, setConsent)}
          <Col
            sm="12"
            md="12"
            lg="12"
            xl="12"
            className="d-flex justify-content-center mt-1"
            style={
              firstName && lastName && mobileNo && emailId
                ? {
                    transition:
                      "top 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)",
                  }
                : {}
            }
          >
            <Button
              className="proceed-button"
              buttonStyle="outline-solid"
              style={{ width: "160px" }}
              disabled={
                btnDisable ||
                (!fullName && !emailId && !mobileNo && !whatsappNo)
              }
              onClick={() => {
                if (
                  isFullNameValid(theme_conf, fullName, firstName, lastName) &&
                  isEmailValid(theme_conf, emailId) &&
                  isMobileNoValid(theme_conf, selected, mobileNo) &&
                  isWhatsappNoValid(whatsappNo)
                ) {
                  _haptics([100, 0, 50]);
                  onSubmit(formData);
                  _leadTrack({
                    ...formData,
                    type: TypeReturn(type),
                  });
                  setbtnDisable(true);
                  allowWithoutOTP(token, theme_conf) && setSkip(true);
                } else {
                  if (!fullName && !emailId && !mobileNo && !whatsappNo) {
                    swal("Please enter your details");
                  } else {
                    trigger();
                    handleSubmit(onSubmit);
                  }
                }
              }}
              hex1={
                fullName || emailId || mobileNo || whatsappNo
                  ? import.meta.env.VITE_BROKER === "RB"
                    ? Theme?.leadPageBtn?.background1
                    : Theme?.leadPageBtn?.background || "#bdd400"
                  : "#e7e7e7"
              }
              hex2={
                fullName || emailId || mobileNo || whatsappNo
                  ? import.meta.env.VITE_BROKER === "RB"
                    ? Theme?.leadPageBtn?.background2
                    : Theme?.leadPageBtn?.background || "#bdd400"
                  : "#e7e7e7"
              }
              shadow={
                firstName || lastName || emailId || mobileNo || whatsappNo
                  ? false
                  : "none"
              }
              borderRadius={
                Theme?.leadPageBtn?.borderRadius
                  ? Theme?.leadPageBtn?.borderRadius
                  : "20px"
              }
              type="submit"
            >
              <text
                style={{
                  color:
                    fullName || emailId || mobileNo || whatsappNo
                      ? Theme?.leadPageBtn?.textColor
                        ? Theme?.leadPageBtn?.textColor
                        : import.meta.env.VITE_BROKER === "RB"
                        ? "white"
                        : "black"
                      : " black",
                }}
              >
                <span>Proceed</span>{" "}
                <span className="eastIcon">
                  <EastIcon sx={{ fontSize: "18px" }} />
                </span>
              </text>
            </Button>
          </Col>
          {allowSkip(token, theme_conf) && (
            <Col
              sm="12"
              md="12"
              lg="12"
              xl="12"
              className="d-flex justify-content-center mt-2 noOutLine"
            >
              <Btn
                className={`lead_link ${
                  Theme?.leadPageBtn?.link ? Theme?.leadPageBtn?.link : ""
                }`}
                variant={"link"}
                type="button"
                disabled={btnDisable}
                style={{ visibility: selected ? "hidden" : "" }}
                onClick={() => {
                  _haptics([100, 0, 50]);
                  onSubmit({
                    firstName: null,
                    lastName: null,
                    emailId: null,
                    mobileNo: null,
                    isSkipped: true,
                  });
                  setbtnDisable(true);
                  setSkip(true);
                }}
              >
                Skip for now
              </Btn>
            </Col>
          )}
          {["UIB", "FYNTUNE"].includes(import.meta.env.VITE_BROKER) && <CorporateDiscount />}
        </Row>
      </Form>
    );
  };

  return _renderForm();
};
