import React from "react";
import { Row, Col } from "react-bootstrap";
import _ from "lodash";
import styled from "styled-components";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const excludes = [
  "nomineeRelationship",
  "previousInsuranceCompany",
  "tpInsuranceCompany",
  "cPAInsComp",
  "cPAPolicyNo",
  "cPASumInsured",
  "cPAPolicyToDt",
  "cPAPolicyFmDt",
  "reason",
  "prevPolicyExpiryDate",
  "tpInsuranceCompanyName",
  "tpInsuranceNumber",
  "tpStartDate",
  "tpEndDate",
  "isClaim",
  "cpa"
];

const tpExcludes = [
  "tpInsuranceCompany",
  "previousInsuranceCompany",
  "InsuranceCompanyName",
  "previousPolicyExpiryDate",
  "previousPolicyNumber",
  "prevPolicyExpiryDate",
];

const SummaryProposal = ({
  data: dataObj,
  type,
  sort,
  lessthan768,
  PolicyValidationExculsion,
  isOrganizationSummary,
}) => {
  //case insensitive sort
  let data = sort
    ? !_.isEmpty(dataObj)
      ? Object.keys(dataObj)
          .sort(function (a, b) {
            return a.toLowerCase().localeCompare(b.toLowerCase());
          })
          .reduce((r, k) => ((r[k] = dataObj[k]), r), {})
      : {}
    : dataObj;
  let keys = !_.isEmpty(data) ? isOrganizationSummary ? Object.keys(data).map(i => i === "firstName" ? "companyName" : i) : Object.keys(data) : [];
  let values = !_.isEmpty(data) ? Object.values(data) : [];

  const newKeys = !_.isEmpty(keys)
    ? _.compact([
        ...[
          keys.includes("tpInsuranceCompany") && "tpInsuranceCompany",
          keys.includes("tpInsuranceCompanyName") && "tpInsuranceCompanyName",
          keys.includes("tpInsuranceNumber") && "tpInsuranceNumber",
          keys.includes("tpStartDate") && "tpStartDate",
          keys.includes("tpEndDate") && "tpEndDate",
        ],
      ])
    : [];

  return (
    <div className="d-flex flex-wrap">
      <Row
        xs={1}
        sm={1}
        md={2}
        lg={3}
        xl={3}
        style={
          type === "header"
            ? { width: "100%", margin: "auto" }
            : { width: "100%", marginTop: "-60px" }
        }
        className="d-flex py-2 px-0"
      >
        {!_.isEmpty(keys) ? (
          keys.map((item, index) => (
            <>
              {(!excludes.includes(item) && values[index]) ||
              (item === "reason" &&
                values[index] === "I do not have a valid driving license.") ? (
                <Col
                  sm={6}
                  xs={6}
                  md={6}
                  lg={4}
                  xl={4}
                  className="py-2 px-2 text-nowrap"
                >
                  <DivHeader lessthan768={lessthan768}>
                    {keys[index]
                      .replace(/([A-Z])/g, " $1")
                      .split(" ")
                      .join("_")
                      .replace(/_/g, " ")
                      .toUpperCase()}
                  </DivHeader>
                  <DivValue lessthan768={lessthan768}>
                    {!!values[index] && values[index].toString()}
                  </DivValue>
                </Col>
              ) : (
                <noscript />
              )}
            </>
          ))
        ) : (
          <p style={{ color: "red" }}>Form data not found</p>
        )}

        {!PolicyValidationExculsion && <Col
          xs={12}
          sm={12}
          md={12}
          lg={12}
          xl={12}
          className="mt-1 px-2"
          style={{ marginBottom: "-10px" }}
        >
          {!_.isEmpty(newKeys) && (
            <p
              style={{
                color: Theme?.proposalHeader?.color
                  ? Theme?.proposalHeader?.color
                  : "#1a5105",
                fontSize: "16px",
                fontWeight: "600",
              }}
            >
              TP Policy Details
            </p>
          )}
        </Col>}

        {!_.isEmpty(newKeys) ? (
          newKeys.map((item, index) => (
            <>
              {!tpExcludes.includes(item) && data?.[`${item}`] ? (
                <Col
                  sm={6}
                  xs={6}
                  md={6}
                  lg={4}
                  xl={4}
                  className="py-2 px-2 text-nowrap"
                >
                  <DivHeader lessthan768={lessthan768}>
                    {newKeys[index]
                      .replace(/([A-Z])/g, " $1")
                      .split(" ")
                      .join("_")
                      .replace(/_/g, " ")
                      .toUpperCase()}
                  </DivHeader>
                  <DivValue lessthan768={lessthan768}>
                    {!!data?.[`${item}`] && data?.[`${item}`].toString()}
                  </DivValue>
                </Col>
              ) : (
                <noscript />
              )}
            </>
          ))
        ) : (
          <noscript />
        )}
      </Row>
    </div>
  );
};

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

export default SummaryProposal;
