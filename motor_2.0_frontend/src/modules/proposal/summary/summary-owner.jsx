import React from "react";
import _ from "lodash";
import styled from "styled-components";
import { Row, Col } from "react-bootstrap";
import { useSelector } from "react-redux";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { Identities, identitiesCompany } from "../cards/data";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;
const SummaryOwner = ({ summary, lessthan768, fields, popup }) => {
  const { temp_data } = useSelector((state) => state.proposal);
  const { orgFields } = useSelector((state) => state.proposal);
  const ckycTypes =
    Number(temp_data?.ownerTypeId) === 1 ? Identities() : identitiesCompany();
  const InfoFn = (header, value, fullSpanInDesktop) => {
    return (
      <Col
        sm={6}
        xs={6}
        md={fullSpanInDesktop ? 12 : 6}
        lg={fullSpanInDesktop ? 12 : 4}
        xl={fullSpanInDesktop ? 12 : 4}
        className="py-2 px-2 text-nowrap"
      >
        <DivHeader lessthan768={lessthan768}>{header}</DivHeader>
        <DivValue lessthan768={lessthan768}>
          {(value || Number(value) === 0 ? value : "-").toString()}
        </DivValue>
      </Col>
    );
  };
  const organizationValue = orgFields?.find(
    (item) => item?.code == summary?.organizationType
  );
  const ckycName = (key) => {
    return ckycTypes?.find((each) => each?.id === key)?.name;
  };

  const firstCapital = (str) => {
    return str.charAt(0).toUpperCase() + str.slice(1);
  };

  return (
    <div
      className="d-flex flex-wrap"
      style={{ marginTop: popup ? "4px" : "-50px" }}
    >
      <Row
        xs={1}
        sm={1}
        md={2}
        lg={3}
        xl={3}
        style={{ width: "100%" }}
        className="d-flex p-1"
      >
        {!_.isEmpty(summary) ? (
          <>
            {Number(temp_data?.ownerTypeId) === 1 ? (
              <>
                {summary?.fullName && InfoFn("FULL NAME", summary?.fullName)}
                {/* {summary?.firstName && InfoFn("FIRST NAME", summary?.firstName)}
                {summary?.lastName && InfoFn("LAST NAME", summary?.lastName)} */}
              </>
            ) : (
              <>
                {summary?.firstName &&
                  InfoFn("COMPANY NAME", summary?.firstName)}
                {summary?.lastName &&
                  InfoFn("REPRESENTATIVE NAME", summary?.lastName)}
              </>
            )}
            {fields.includes("fatherName") &&
              summary?.fatherName &&
              InfoFn("FATHER'S NAME", summary?.fatherName)}
            {fields.includes("gender") &&
              (summary?.genderName || summary?.gender) &&
              InfoFn("GENDER", summary?.genderName || summary?.gender)}
            {temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
              "I" &&
              fields.includes("dob") &&
              summary?.dob &&
              InfoFn("DATE OF BIRTH", summary?.dob)}
            {summary?.mobileNumber &&
              InfoFn("MOBILE NUMBER", summary?.mobileNumber)}
            {(import.meta.env.VITE_BROKER !== "OLA" ||
              fields.includes("email")) &&
              summary?.email &&
              InfoFn("EMAIL ID", summary?.email)}
            {temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
              "C" &&
              (summary?.doi || summary?.dob) &&
              fields.includes("ckyc") &&
              InfoFn("DATE OF INCORPORATION", summary?.doi || summary?.dob)}
            {fields.includes("panNumber") &&
              summary?.panNumber &&
              InfoFn("PAN NO.", summary?.panNumber)}
            {fields.includes("gstNumber") &&
              summary?.gstNumber &&
              InfoFn("GST NUMBER", summary?.gstNumber)}
            {fields.includes("occupation") &&
              (summary?.occupationName || summary?.occupation) &&
              InfoFn(
                "OCCUPATION TYPE",
                summary?.occupationName || summary?.occupation
              )}
            {fields.includes("maritalStatus") &&
              summary?.maritalStatus &&
              InfoFn("MARITAL STATUS", summary?.maritalStatus)}
            {summary?.isPanPresent === "NO" &&
              summary?.formType &&
              temp_data?.selectedQuote?.companyAlias !== "bajaj_allianz" &&
              InfoFn(
                "FORM TYPE",
                summary?.formType === "form60" ? "Form 60" : "Form 49A"
              )}
            {temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
              temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                "C" &&
              summary?.isCinPresent &&
              InfoFn("Do You Have CIN", summary?.isCinPresent)}
            {summary?.identity &&
              summary[`${summary?.identity}`] &&
              InfoFn("CKYC TYPE", ckycName(summary?.identity))}
            {summary?.fileUploaded &&
              InfoFn("FILE UPLOADED", summary?.fileUploaded)}
            {summary?.identity &&
              summary?.identity !== "panNumber" &&
              summary?.identity !== "gstNumber" &&
              summary[`${summary?.identity}`] &&
              InfoFn(
                ckycName(summary?.identity),
                summary[`${summary?.identity}`]
              )}
            {(summary?.poi_identity || summary?.poiIdentity) &&
              InfoFn(
                "Proof of Identity",
                ckycName(summary?.poi_identity || summary?.poiIdentity)
              )}
            {summary[`poi_${summary?.poi_identity}`] &&
              InfoFn(
                ckycName(summary?.poi_identity),
                summary[`poi_${summary?.poi_identity}`]
              )}
            {summary?.poiIdentity &&
              summary[`poi${firstCapital(summary?.poiIdentity)}`] &&
              InfoFn(
                ckycName(summary?.poiIdentity),
                summary[`poi${firstCapital(summary?.poiIdentity)}`]
              )}
            {(summary?.poa_identity || summary?.poaIdentity) &&
              InfoFn(
                "Proof of Address",
                ckycName(summary?.poa_identity) || summary?.poaIdentity
              )}
            {summary[`poa_${summary?.poa_identity}`] &&
              InfoFn(
                ckycName(summary?.poa_identity),
                summary[`poa_${summary?.poa_identity}`]
              )}
            {summary?.poaIdentity &&
              summary[`poa${firstCapital(summary?.poaIdentity)}`] &&
              InfoFn(
                ckycName(summary?.poaIdentity),
                summary[`poa${firstCapital(summary?.poaIdentity)}`]
              )}
            {/* {
              summary?.identity &&  summary[`${summary?.identity}`] &&
              InfoFn(ckycName(summary?.identity), summary[`${summary?.identity}`])
            } */}
            {summary?.ckycNumber && InfoFn("CKYC NUMBER", summary?.ckycNumber)}
            {summary?.industryType &&
              InfoFn("ORGANIZATION TYPE", organizationValue?.value)}
            {summary?.industryType &&
              InfoFn("INDUSTRY TYPE", summary?.industryType[0].value)}
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
                Communication Address
              </p>
            </Col>
            {summary?.address && InfoFn("ADDRESS", summary?.address)}
            {/* {summary?.addressLine1 &&
              InfoFn("ADDRESS LINE 1", summary?.addressLine1)}
            {summary?.addressLine2 &&
              InfoFn("ADDRESS LINE 2", summary?.addressLine2)}
            {summary?.addressLine3 &&
              InfoFn("ADDRESS LINE 3", summary?.addressLine3)} */}
            {summary?.pincode && InfoFn("PINCODE", summary?.pincode)}
            {summary?.state && InfoFn("STATE", summary?.state)}
            {summary?.city && InfoFn("CITY", summary?.city)}
            {summary?.ifsc &&
              ["universal_sompo", "sbi"].includes(
                temp_data?.selectedQuote?.companyAlias
              ) && (
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
                    Bank Details
                  </p>
                </Col>
              )}
            {summary?.ifsc && InfoFn("IFSC", summary?.ifsc)}
            {summary?.bankName && InfoFn("BANK NAME", summary?.bankName)}
            {temp_data?.selectedQuote?.companyAlias === "sbi" &&
              summary?.branchName &&
              InfoFn("BRANCH NAME", summary?.branchName)}
            {summary?.accountNumber &&
              InfoFn("ACCOUNT NUMBER", summary?.accountNumber)}
            {temp_data?.selectedQuote?.companyAlias === "universal_sompo" &&
              summary?.pepStatus &&
              InfoFn("POLITICALLY EXPOSED PERSON", summary?.pepStatus)}
            {temp_data?.selectedQuote?.companyAlias === "universal_sompo" &&
              summary?.gogreenStatus &&
              InfoFn("GO GREEN STATUS", summary?.gogreenStatus)}
          </>
        ) : (
          <p style={{ color: "red" }}>Form data not found</p>
        )}
      </Row>
    </div>
  );
};

const DivHeader = styled.div`
  font-size: ${({ lessthan768 }) => (lessthan768 ? "11px" : "12px")};
  font-weight: 600;
`;

const DivValue = styled.div`
  font-size: ${({ lessthan768 }) => (lessthan768 ? "11px" : "12px")};
  white-space: pre-wrap;
  word-wrap: break-word;
`;

export default SummaryOwner;
