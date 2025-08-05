import React from "react";
// prettier-ignore
import { Col, Form, Row, Button as Btn, Spinner } from "react-bootstrap";
import Textbox from "components/inputs/TextInput/textInput";
import MailOutlineOutlinedIcon from "@material-ui/icons/MailOutlineOutlined";
import MessageIcon from "@material-ui/icons/Message";
import WhatsAppIcon from "@material-ui/icons/WhatsApp";
import { MultiEmail } from "components/Popup/email/MultiEmail";
import { QrCode, QrCodeText } from "../style";
// import themeConfig from "modules/theme-config/theme-config";
import { useSelector } from "react-redux";
import { EvaluateChannels } from "components/dynamicShare/dynamicShare-logic";

const ShareForm = ({
  lessthan576,
  sendPdf,
  shareQuotesFromToaster,
  loc,
  register,
  errors,
  watch,
  MobileNo,
  onSubmit,
  setValue,
  setDisEmail,
  userDataHome,
  temp_data,
  disEmail,
  handleSubmit,
  customLoad,
  setShowModal,
  shareProposalPayment,
}) => {
  const { theme_conf } = useSelector((state) => state.home);
  const handleSubConditions = window.location.href.includes("proposal-page")
    ? shareProposalPayment
      ? "proposal_payment"
      : "proposal"
    : sendPdf
    ? "premium_breakup"
    : "quote";

  const showWhatsapp =
    EvaluateChannels(theme_conf, "whatsapp_api", handleSubConditions) ||
    EvaluateChannels(theme_conf, "whatsapp_redirection", handleSubConditions);

  const showWhatsappRedirection = EvaluateChannels(
    theme_conf,
    "whatsapp_redirection",
    handleSubConditions
  );

  const showEmail = EvaluateChannels(theme_conf, "email", handleSubConditions);
  const showSms = EvaluateChannels(theme_conf, "sms", handleSubConditions);

  const showAll =
    [showEmail, showSms, showWhatsapp].filter(Boolean).length >= 2 &&
    EvaluateChannels(theme_conf, "all_btn", handleSubConditions);

  const disableAll =
    !MobileNo ||
    MobileNo?.length < 10 ||
    errors?.mobileNo ||
    (!(MobileNo && [showSms, showWhatsapp]) &&
      !(MobileNo && disEmail && [showEmail])) ||
    showWhatsappRedirection;

  const reSizeEmailInput = showEmail && showAll; // whatsapp || sms

  return (
    <Form
      style={{
        transform: loc[2] === "quotes" ? "translateY(20px)" : "",
        height: loc[2] === "quotes" ? "200px" : "",
      }}
    >
      <Row style={{ width: "100%" }}>
        {
          <>
            {(showSms || showWhatsapp) && (
              <Col sm="10" md="10" lg="10" xl="10" xs={lessthan576 && 8}>
                <div className="w-100">
                  <Textbox
                    nonCircular
                    lg
                    type="tel"
                    id="mobileNo"
                    fieldName="Mobile No."
                    name="mobileNo"
                    placeholder=" "
                    register={register}
                    error={
                      !!errors?.mobileNo &&
                      watch("mobileNo") &&
                      watch("mobileNo")?.length === 10 &&
                      errors?.mobileNo?.message
                    }
                    maxLength="10"
                    fontWeight="bold"
                    onInput={(e) =>
                      (e.target.value = e.target.value.replace(/[^0-9-/]/g, ""))
                    }
                    isEmail
                  />
                </div>
              </Col>
            )}
            {lessthan576 ? (
              <>
                {showWhatsapp ? (
                  <Col xs={2} style={{ paddingLeft: "0px" }}>
                    <Btn
                      type="button"
                      variant="success"
                      disabled={
                        !MobileNo || MobileNo?.length < 10 || errors?.mobileNo
                      }
                      onClick={() => onSubmit(MobileNo, 2)}
                      style={{
                        position: "relative",
                        right: "0px",
                        height: "50px",
                        width: "50px",
                        cursor:
                          (!MobileNo ||
                            MobileNo?.length < 10 ||
                            errors?.mobileNo) &&
                          "not-allowed",
                      }}
                    >
                      <WhatsAppIcon style={{ color: "#000" }} />
                    </Btn>
                  </Col>
                ) : (
                  <noscript />
                )}
                {shareQuotesFromToaster !== true && showSms ? (
                  <Col xs={2}>
                    <Btn
                      type="submit"
                      variant="warning"
                      disabled={
                        !MobileNo || MobileNo?.length < 10 || errors?.mobileNo
                      }
                      onClick={() => onSubmit(MobileNo, 1)}
                      style={{
                        position: "relative",
                        right: showWhatsapp ? "0px" : "15px",
                        height: "50px",
                        width: "50px",
                        cursor:
                          (!MobileNo ||
                            MobileNo?.length < 10 ||
                            errors?.mobileNo) &&
                          "not-allowed",
                      }}
                    >
                      <MessageIcon style={{ color: "#000" }} />
                    </Btn>
                  </Col>
                ) : (
                  <noscript />
                )}
              </>
            ) : (
              <>
                {showWhatsapp ? (
                  <Col sm="1" md="1" lg="1" xl="1" xs={2}>
                    <Btn
                      type="button"
                      variant="success"
                      disabled={
                        !MobileNo || MobileNo?.length < 10 || errors?.mobileNo
                      }
                      onClick={() => onSubmit(MobileNo, 2)}
                      style={{
                        position: "relative",
                        right: "15px",
                        height: "50px",
                        width: "50px",
                        cursor:
                          (!MobileNo ||
                            MobileNo?.length < 10 ||
                            errors?.mobileNo) &&
                          "not-allowed",
                      }}
                    >
                      <WhatsAppIcon style={{ color: "#000" }} />
                    </Btn>
                  </Col>
                ) : (
                  <noscript />
                )}
                {shareQuotesFromToaster !== true && showSms ? (
                  <Col sm="1" md="1" lg="1" xl="1" xs={2}>
                    <Btn
                      type="submit"
                      variant="warning"
                      disabled={
                        !MobileNo || MobileNo?.length < 10 || errors?.mobileNo
                      }
                      onClick={() => onSubmit(MobileNo, 1)}
                      style={{
                        position: "relative",
                        right: showWhatsapp ? "0px" : "15px",
                        height: "50px",
                        width: "50px",
                        cursor:
                          (!MobileNo ||
                            MobileNo?.length < 10 ||
                            errors?.mobileNo) &&
                          "not-allowed",
                      }}
                    >
                      <MessageIcon style={{ color: "#000" }} />
                    </Btn>
                  </Col>
                ) : (
                  <noscript />
                )}
              </>
            )}
          </>
        }
      </Row>
      <Row style={{ width: "100%" }}>
        <Col
          sm="10"
          md="10"
          lg="10"
          xl="10"
          xs={
            (lessthan576 &&
              handleSubConditions &&
              (loc[2] === "quotes" || loc[2] === "proposal-page")) ||
            reSizeEmailInput ||
            (import.meta.env.VITE_BROKER === "HEROCARE" &&
              loc[2] !== "proposal-page")
              ? 8
              : 10
          }
        >
          {shareQuotesFromToaster !== true && showEmail && (
            <div className="w-100">
              <MultiEmail
                register={register}
                setValue={setValue}
                setDisEmail={setDisEmail}
                prefill={
                  userDataHome?.emailId ||
                  userDataHome?.userProposal?.additonalData?.owner?.email ||
                  temp_data?.userProposal?.additonalData?.owner?.email
                }
              />
            </div>
          )}
        </Col>
        <Col
          sm="1"
          md="1"
          lg="1"
          xl="1"
          xs={lessthan576 ? 2 : 2}
          style={{ paddingLeft: lessthan576 ? "0" : "" }}
        >
          {shareQuotesFromToaster !== true && showEmail && (
            <Btn
              type="submit"
              variant="primary"
              disabled={disEmail}
              onClick={handleSubmit(onSubmit)}
              style={{
                height: "50px",
                width: "50px",
                position: "relative",
                right: lessthan576 ? "0" : "15px",
                cursor: disEmail && "not-allowed",
              }}
            >
              {!customLoad ? (
                <MailOutlineOutlinedIcon style={{ color: "#000" }} />
              ) : (
                <Spinner animation="border" variant="light" size="sm" />
              )}
            </Btn>
          )}
        </Col>
        {showAll ? (
          <Col
            sm="1"
            md="1"
            lg="1"
            xl="1"
            xs={lessthan576 ? 2 : 2}
            style={{ paddingLeft: lessthan576 ? "0" : "" }}
          >
            <Btn
              type="button"
              variant="secondary"
              disabled={disableAll}
              onClick={() => {
                onSubmit(MobileNo, 3, true);
              }}
              style={{
                height: "50px",
                width: "50px",
                left: lessthan576 ? "15px" : "0",
                position: "relative",
                cursor: disableAll && "not-allowed",
                color: "#000",
                fontWeight: "bold",
              }}
            >
              {!customLoad && "ALL"}
            </Btn>
          </Col>
        ) : (
          <noscript />
        )}
      </Row>
      <QrCodeText>
        Share url via{" "}
        <QrCode onClick={() => setShowModal(true)}>QR CODE</QrCode>
      </QrCodeText>
    </Form>
  );
};

export default ShareForm;
