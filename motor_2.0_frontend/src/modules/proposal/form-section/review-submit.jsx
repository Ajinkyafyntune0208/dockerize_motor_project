import React, { useEffect, useState } from "react";
import { Row, Col, Spinner } from "react-bootstrap";
import { shortHandAddonAndAll } from "../proposal-constants";
import { addonarr } from "./proposal-logic";
import { SubmitDiv } from "../style";
import _ from "lodash";
import { BrokerName, Button } from "components";
import ProposalFinalModal from "../modals/proposalFinalModal";

const FinalSubmit = (props) => {
  //prettier-ignore
  const {
    TempData, zd_rti_condition, setZd_rti_condition, Theme,
    submitProcess, theme_conf, ZD_preview_conditions, type,
    onFinalSubmit, lessthan768, breakinCase, TypeReturn, companyAlias
  } = props;

  //setn proposal state
  const [proposalModal, setProposalModal] = useState(false);

  // effect to close proposal modal
  useEffect(() => {
    if (!submitProcess) {
      setProposalModal(false);
    }
  }, [submitProcess, proposalModal]);

  useEffect(() => {
    if (import.meta.env.VITE_BROKER === "KMD") {
      const declarationElement = document.getElementById("declaration");

      if (
        declarationElement &&
        theme_conf?.broker_config?.p_declaration &&
        theme_conf?.broker_config?.p_declaration.includes("#link#")
      ) {
        const content = declarationElement.innerHTML;

        // Replace the text
        const updatedContent = content.replace(
          "#link#",
          `<a href={${window.location.origin}/privacy}>Privacy Policy</a>`
        );

        // Update the innerHTML with the new content
        declarationElement.innerHTML = updatedContent;
      }
    }
  }, [theme_conf?.broker_config?.p_declaration]);

  //to store checkbox value
  const [terms_condition, setTerms_condition] = useState(true);

  //addon block condition
  const disableProceed = !(
    _.isEmpty(zd_rti_condition) ||
    !Object.values(zd_rti_condition)?.includes(false) ||
    TempData?.selectedQuote?.isBreakinApplicable
  );

  //button condition check
  const buttonDisable =
    ((breakinCase && TempData?.userProposal?.isInspectionDone === "Y") ||
      (TypeReturn(type) === "bike" &&
        companyAlias !== "godigit" &&
        companyAlias !== "icici_lombard" &&
        companyAlias !== "united_india") ||
      !breakinCase) &&
    (_.isEmpty(zd_rti_condition) ||
      !Object.values(zd_rti_condition)?.includes(false) ||
      //added after hdfc bike issue
      TypeReturn(type) === "bike" ||
      TempData?.selectedQuote?.isBreakinApplicable);
  const btnColour =
    terms_condition && buttonDisable
      ? Theme?.proposalProceedBtn?.hex2
        ? Theme?.proposalProceedBtn?.hex2
        : "#4ca729"
      : "#787878";

  const btnCon =
    (terms_condition && buttonDisable ? false : true) || submitProcess
      ? true
      : false;

  const ncbCheck =
    TempData?.corporateVehiclesQuoteRequest?.previousPolicyType &&
    TempData?.selectedQuote?.policyType !== "Third Party" &&
    TempData?.corporateVehiclesQuoteRequest?.previousNcb &&
    TempData?.corporateVehiclesQuoteRequest?.isClaim !== "Y"
      ? TempData?.corporateVehiclesQuoteRequest?.previousNcb * 1
        ? `I confirm that NCB percentage declared is correct and no claims were made in the previous policy.`
        : ""
      : "";

  const hideConsent =
    import.meta.env.VITE_BROKER === "BAJAJ" &&
    import.meta.env.VITE_BASENAME !== "general-insurance";

  return (
    <Row style={{ padding: "10.5px" }}>
      <Col
        xl="12"
        lg="12"
        md="12"
        sm="12"
        style={{ ...(hideConsent && { display: "none" }) }}
      >
        <SubmitDiv>
          <label className="checkbox-container">
            <input
              className="bajajCheck"
              id="checkboxId"
              defaultChecked={false}
              name="accept"
              type="checkbox"
              disabled={submitProcess}
              value={terms_condition}
              checked={terms_condition ? true : false}
              onChange={(e) => {
                setTerms_condition(e.target.checked);
              }}
            />
            <span className="checkmark"></span>
          </label>
          <p
            className="privacyPolicy"
            id={"declaration"}
            style={
              theme_conf?.broker_config?.p_declaration &&
              theme_conf?.broker_config?.p_declaration.includes("${link}")
                ? { cursor: "pointer" }
                : {}
            }
            onClick={() =>
              theme_conf?.broker_config?.p_declaration &&
              theme_conf?.broker_config?.p_declaration.includes("${link}")
                ? document.getElementById("checkboxId").click()
                : {}
            }
          >
            {`${
              theme_conf?.broker_config?.p_declaration ||
              `I confirm all the details shared are correct and accurate as per my knowledge.
			        I agree with all the T&C and my vehicle has a valid PUC certificate.
			        I also declare that the information provided above is true and accept that if it is found to be false, it may impact claims.
			        I agree any changes to the details post payment might require additional payment.
             ${BrokerName()} (including its representatives) shall not be held liable for any changes due to incorrect information.`
            }
              ${
                TempData?.gcvCarrierType === "PRIVATE"
                  ? `Valid documents supporting Private Carrier Cover required during claims.
                     Otherwise, it may lead to claim rejection by insurer.`
                  : ""
              }
              ${
                TempData?.corporateVehiclesQuoteRequest?.previousNcb
                  ? ncbCheck
                  : ""
              }
              `}
          </p>
        </SubmitDiv>
      </Col>
      {ZD_preview_conditions &&
        addonarr(TempData)?.map((item) => (
          <Col xl="12" lg="12" md="12" sm="12">
            <SubmitDiv>
              <label className="checkbox-container">
                <input
                  className="bajajCheck"
                  defaultChecked={false}
                  id={`${item}-declaration`}
                  name={item}
                  disabled={submitProcess}
                  type="checkbox"
                  value={zd_rti_condition[item] ? true : false}
                  checked={zd_rti_condition[item] ? true : false}
                  onChange={(e) => {
                    setZd_rti_condition({
                      ...zd_rti_condition,
                      [item]: e.target.checked,
                    });
                  }}
                />
                <span className="checkmark"></span>
              </label>
              <p className="privacyPolicy">{`I confirm that ${shortHandAddonAndAll(
                item
              )} 
                was available in my previous policy.`}</p>
            </SubmitDiv>
          </Col>
        ))}
      <Col
        sm="12"
        md="12"
        lg="12"
        xl="12"
        className="d-flex justify-content-center"
      >
        {disableProceed ? (
          <SubmitDiv className="ElemFade">
            <p
              style={{
                fontSize: "15px",
                color: "#D80000 ",
                display: "flex",
                justifyContent: "start",
                marginTop: "10px",
                textAlign: "left",
                border: "1px solid #D80000",
                padding: "5px",
                borderRadius: "4px",
              }}
            >
              <i
                style={{
                  position: "relative",
                  top: "3.74px",
                  marginRight: "3px",
                }}
                className="fa fa-exclamation-circle"
                aria-hidden="true"
              />
              {
                " An unselected addon isn't included in your current policy, so we can't proceed."
              }
            </p>
          </SubmitDiv>
        ) : (
          <Button
            type="submit"
            buttonStyle="outline-solid"
            id="proposal-submit"
            hex1={btnColour}
            hex2={btnColour}
            borderRadius="5px"
            color="white"
            disabled={btnCon}
            onClick={() => [onFinalSubmit(), setProposalModal(true)]}
            shadow={"none"}
          >
            <text
              style={{
                fontSize: lessthan768 ? "12px" : "15px",
                padding: "-20px",
                margin: "-20px -5px -20px -5px",
                fontWeight: "400",
              }}
            >
              {submitProcess ? (
                "Processing "
              ) : !breakinCase ? (
                "Review & Submit"
              ) : (breakinCase &&
                  TempData?.userProposal?.isInspectionDone === "Y") ||
                //allowing for all the IC's bike except godigit, icici_lombard and united india
                (TypeReturn(type) === "bike" &&
                  breakinCase &&
                  companyAlias !== "godigit" &&
                  companyAlias !== "icici_lombard" &&
                  companyAlias !== "united_india") ? (
                "Proceed"
              ) : (
                <text>
                  <i className="fa fa-info-circle" /> Inspection Pending
                </text>
              )}
              {submitProcess ? (
                <Spinner animation="grow" variant="light" size="sm" />
              ) : (
                <noscript />
              )}
            </text>
          </Button>
        )}
      </Col>
      {<ProposalFinalModal show={submitProcess && proposalModal} />}
    </Row>
  );
};

export default FinalSubmit;
