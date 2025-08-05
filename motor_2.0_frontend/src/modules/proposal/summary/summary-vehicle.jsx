import React from "react";
import _ from "lodash";
import styled from "styled-components";
import { Row, Col } from "react-bootstrap";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const SummaryVehicle = ({ summary, temp, lessthan768, fields }) => {
  const lessthan569 = useMediaPredicate("(max-width: 569px)");
  const InfoFn = (header, value) => {
    return (
      <Col sm={6} xs={6} md={6} lg={4} xl={4} className="py-2 px-2 text-nowrap">
        <DivHeader lessthan768={lessthan768}>{header}</DivHeader>
        <DivValue lessthan768={lessthan768}>
          {(value || Number(value) === 0 ? value : "-").toString().trim()}
        </DivValue>
      </Col>
    );
  };

  const question = (ques, ans) => {
    return (
      <Col sm={12} xs={12} md={12} lg={12} xl={12} className="py-0 px-2 w-100">
        <Col sm={12} xs={12} md={12} lg={12} xl={12} className="py-0 px-0">
          <div
            style={{
              fontSize: lessthan768 ? "11px" : "12px",
              fontWeight: "600",
              ...(lessthan569 && { marginLeft: "-10px" }),
            }}
          >
            {ques}
          </div>
        </Col>
        <Col sm={12} xs={12} md={12} lg={12} xl={12} className="py-0 px-0">
          <div
            style={{
              fontSize: lessthan768 ? "11px" : "12px",
              fontWeight: "600",
              ...(lessthan569 && { marginLeft: "-10px" }),
            }}
          >
            {ans ? "Yes" : "No"}
          </div>
        </Col>
      </Col>
    );
  };

  return (
    <div className="d-flex flex-wrap" style={{ marginTop: "-50px" }}>
      <Row
        xs={1}
        sm={1}
        md={2}
        lg={3}
        xl={3}
        style={{ width: "100%" }}
        className="d-flex p-0"
      >
        {!_.isEmpty(summary) ? (
          <>
            {summary?.vehicaleRegistrationNumber &&
              InfoFn(
                "VEHICLE REGISTRATION NO.",
                summary?.vehicaleRegistrationNumber.toUpperCase()
              )}
            {summary?.engineNumber &&
              InfoFn(
                temp?.corporateVehiclesQuoteRequest?.fuelType === "ELECTRIC"
                  ? "Motor/Battery Number"
                  : "ENGINE NUMBER",
                summary?.engineNumber
              )}
            {summary?.chassisNumber &&
              InfoFn("CHASSIS NUMBER", summary?.chassisNumber)}
            {summary?.registrationDate &&
              InfoFn("REGISTRATION DATE", summary?.registrationDate)}
            {summary?.vehicleManfYear &&
              InfoFn("MANUFACTURE MONTH & YEAR", summary?.vehicleManfYear)}
            {summary?.hazardousType &&
              InfoFn("Hazardous Type", summary?.hazardousType)}
            {fields.includes("vehicleColor") &&
              summary?.vehicleColor &&
              InfoFn("VEHICLE COLOR", summary?.vehicleColor)}
            {fields.includes("inspectionType") &&
              summary?.inspectionType &&
              InfoFn("INSPECTION TYPE", summary?.inspectionType)}
            {fields.includes("inspectionType") &&
              summary?.inspectionAddress &&
              InfoFn("INSPECTION ADDRESS", summary?.inspectionAddress)}
            {fields.includes("pucNo") &&
              summary?.pucNo &&
              InfoFn("PUC NUMBER", summary?.pucNo)}
            {fields.includes("pucExpiry") &&
              summary?.pucExpiry &&
              InfoFn("PUC EXPIRY DATE", summary?.pucExpiry)}
            {/* {question("Did car's ownership change in the last 12 months?", summary?.carOwnership)} */}
            {summary?.carOwnership ? (
              question(
                "Is the existing policy not in your name?",
                summary?.policyOwner
              )
            ) : (
              <noscript />
            )}
            {summary?.isVehicleFinance && (
              <>
                <Col
                  xs={12}
                  sm={12}
                  md={12}
                  lg={12}
                  xl={12}
                  className="mt-1 px-2"
                  style={{ marginBottom: "-10px" }}
                >
                  <p
                    style={{
                      color: Theme?.proposalHeader?.color
                        ? Theme?.proposalHeader?.color
                        : "#1a5105",
                      fontSize: "16px",
                      fontWeight: "600",
                    }}
                  >
                    Financer Details
                  </p>
                </Col>
                {(summary?.financer_name || summary?.nameOfFinancer) &&
                  InfoFn(
                    "FINANCER NAME",
                    summary?.fullNameFinance || summary?.financer_name || summary?.nameOfFinancer
                  )}
                {(summary?.agreement_type ||
                  summary?.agreementType ||
                  summary?.financerAgreementType) &&
                  InfoFn(
                    "AGREEMENT TYPE",
                    summary?.agreement_type ||
                      summary?.agreementType ||
                      summary?.financerAgreementType
                  )}
                {fields.includes("hypothecationCity") &&
                  summary?.hypothecationCity &&
                  InfoFn("FINANCER (CITY/BRANCH)", summary?.hypothecationCity)}
              </>
            )}
            {!summary?.isCarRegistrationAddressSame && (
              <>
                <Col
                  xs={12}
                  sm={12}
                  md={12}
                  lg={12}
                  xl={12}
                  className="mt-1 px-2"
                  style={{ marginBottom: "-10px" }}
                >
                  <p
                    style={{
                      color: Theme?.proposalHeader?.color
                        ? Theme?.proposalHeader?.color
                        : "#1a5105",
                      fontSize: "16px",
                      fontWeight: "600",
                    }}
                  >
                    Vehicle Registration Address
                  </p>
                </Col>
                {summary?.carRegistrationAddress1 &&
                  InfoFn("ADDRESS LINE 1", summary?.carRegistrationAddress1)}
                {summary?.carRegistrationAddress2 &&
                  InfoFn("ADDRESS LINE 2", summary?.carRegistrationAddress2)}
                {summary?.carRegistrationAddress3 &&
                  InfoFn("ADDRESS LINE 3", summary?.carRegistrationAddress3)}
                {summary?.carRegistrationPincode &&
                  InfoFn("PINCODE", summary?.carRegistrationPincode)}
                {summary?.carRegistrationState &&
                  InfoFn("STATE", summary?.carRegistrationState)}
                {summary?.carRegistrationCity &&
                  InfoFn("CITY", summary?.carRegistrationCity)}
              </>
            )}
          </>
        ) : (
          <p style={{ color: "red" }}>Form data not found</p>
        )}
      </Row>
    </div>
  );
};

// puc: true

const DivHeader = styled.div`
  font-size: ${({ lessthan768 }) => (lessthan768 ? "11px" : "12px")};
  font-weight: 600;
  white-space: pre-line;
`;

const DivValue = styled.div`
  font-size: ${({ lessthan768 }) => (lessthan768 ? "11px" : "12px")};
  white-space: pre-wrap;
  word-wrap: break-word;
`;

export default SummaryVehicle;
