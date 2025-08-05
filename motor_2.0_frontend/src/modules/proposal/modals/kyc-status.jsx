import React from "react";
import { Modal } from "react-bootstrap";
import { Button } from "components";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import _ from "lodash";
import { reloadPage } from "utils";
import styled from "styled-components";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

export const PreSubmitKyc = (props) => {
  const {
    submitData,
    companyAlias,
    loading,
    disclaimer,
    pre_payment_ckyc,
    verifyCkyc,
    lessthan768,
  } = props;

  let displayText =
    submitData?.kyc_message && submitData?.kyc_message.split("<break>");

  return (
    <>
      <Modal.Header closeButton size="lg">
        <Modal.Title id="contained-modal-title-vcenter">
          Please Note
        </Modal.Title>
      </Modal.Header>
      <Modal.Body>
        {loading ? (
          <p style={{ textAlign: "center" }}>Please Wait...</p>
        ) : (
          <>
            <p>It seems your CKYC verification is not complete.</p>
            {submitData?.kyc_message && (
              <PTag>
                {displayText?.length > 1
                  ? displayText.map((item) => (
                      <>
                        <>{item}</>
                        <br />
                      </>
                    ))
                  : submitData?.kyc_message}
              </PTag>
            )}
            <p style={{ textAlign: "center" }}>
              {!!submitData?.is_kyc_url_present ? (
                <>
                  <p className="mt-2" style={{ textAlign: "left" }}>
                    Note:- {disclaimer(true)}
                  </p>
                </>
              ) : (
                "No url provided by insurance company for CKYC verification. Please contact partner."
              )}
            </p>
          </>
        )}
      </Modal.Body>
      {!!submitData?.is_kyc_url_present ||
      (pre_payment_ckyc && props?.companyAlias === "godigit") ? (
        <Modal.Footer>
          {!!submitData?.is_kyc_url_present ? (
            <Button
              type="button"
              buttonStyle="outline-solid"
              onClick={() => {
                reloadPage(
                  submitData?.kyc_url || "",
                  ["godigit", "raheja", "kotak"].includes(
                    companyAlias
                  )
                );
              }}
              hex1={
                Theme?.paymentConfirmation?.Button?.hex1
                  ? Theme?.paymentConfirmation?.Button?.hex1
                  : "#4ca729"
              }
              hex2={
                Theme?.paymentConfirmation?.Button?.hex2
                  ? Theme?.paymentConfirmation?.Button?.hex2
                  : "#4ca729"
              }
              borderRadius="5px"
              color={
                Theme?.PaymentConfirmation?.buttonTextColor
                  ? Theme?.PaymentConfirmation?.buttonTextColor
                  : "white"
              }
              style={{ ...(lessthan768 && { width: "100%" }) }}
              shadow={"none"}
            >
              <text
                style={{
                  fontSize: "15px",
                  padding: "-20px",
                  margin: "-20px -5px -20px -5px",
                  fontWeight: "400",
                }}
              >
                {"Redirect for CKYC"}
              </text>
            </Button>
          ) : (
            <noscript />
          )}
          {pre_payment_ckyc && props?.companyAlias === "godigit" && (
            <Button
              type="button"
              buttonStyle="outline-solid"
              onClick={verifyCkyc}
              hex1={
                Theme?.paymentConfirmation?.Button?.hex1
                  ? Theme?.paymentConfirmation?.Button?.hex1
                  : "#4ca729"
              }
              hex2={
                Theme?.paymentConfirmation?.Button?.hex2
                  ? Theme?.paymentConfirmation?.Button?.hex2
                  : "#4ca729"
              }
              borderRadius="5px"
              color={
                Theme?.PaymentConfirmation?.buttonTextColor
                  ? Theme?.PaymentConfirmation?.buttonTextColor
                  : "white"
              }
              style={{ ...(lessthan768 && { width: "100%" }) }}
              shadow={"none"}
            >
              <text
                style={{
                  fontSize: "15px",
                  padding: "-20px",
                  margin: "-20px -5px -20px -5px",
                  fontWeight: "400",
                }}
              >
                {"Proceed to Payment"}
                <i className="fa fa-arrow-circle-right ml-2"></i>
              </text>
            </Button>
          )}
        </Modal.Footer>
      ) : (
        <noscript />
      )}
    </>
  );
};

const PTag = styled.p`
  word-wrap: break-word;
`;
