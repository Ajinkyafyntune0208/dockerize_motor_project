import React from "react";
import { Row } from "react-bootstrap";
import {
  DivSumIns,
  HeaderSumIns,
  HeaderTagLine,
  PSumIns,
  PTagLine,
  TagLineDiv,
} from "../info-style";
import { TypeReturn } from "modules/type";
import { currencyFormater } from "utils";

const PlanDetails = ({ selectedQuote, quoteLog, type, temp_data }) => {
  //Plan type evaluation
  const planType = `${
    selectedQuote?.policyType === "Comprehensive" &&
    TypeReturn(type) !== "cv" &&
    temp_data?.newCar
      ? "Bundled"
      : selectedQuote?.policyType || `N/A`
  }${
    selectedQuote?.policyType === "Short Term"
      ? ` (${
          selectedQuote?.premiumTypeCode === "short_term_3" ||
          selectedQuote?.premiumTypeCode === "short_term_3_breakin"
            ? "3 Months"
            : "6 Months"
        }) - Comprehensive`
      : selectedQuote?.policyType === "Comprehensive" &&
        temp_data?.newCar &&
        TypeReturn(type) !== "cv"
      ? ` - 1 yr. OD + ${TypeReturn(type) === "car" ? 3 : 5} yr. TP`
      : temp_data?.newCar && TypeReturn(type) !== "cv"
      ? ` - ${TypeReturn(type) === "car" ? 3 : 5} years`
      : !(selectedQuote?.tenure || selectedQuote?.tpTenure)
      ? ` - Annual`
      : selectedQuote?.tenure && selectedQuote?.tpTenure
      ? ` - ${selectedQuote?.tenure} yr. OD + ${selectedQuote?.tpTenure} yr. TP`
      : `${selectedQuote?.tpTenure} yr. TP`
  }`;

  return (
    <Row>
      <div className="mb-1 mt-1 mr-2">
        {selectedQuote?.companyAlias === "universal_sompo" ? (
          <img
            src={selectedQuote?.companyLogo}
            alt="logo"
            height={"55"}
            width={"auto"}
          />
        ) : (
          <img
            src={selectedQuote?.companyLogo}
            alt="logo"
            height={"60"}
            width={"auto"}
          />
        )}
      </div>
      <TagLineDiv>
        <HeaderTagLine>{selectedQuote?.companyName || `N/A`}</HeaderTagLine>
        <PTagLine>{selectedQuote?.productName || `N/A`}</PTagLine>
        {quoteLog?.premiumJson?.icAddress &&
        ["RB", "SRIYAH"].includes(import.meta.env.VITE_BROKER) ? (
          <PTagLine name="ic_address">
            {quoteLog?.premiumJson?.icAddress}
          </PTagLine>
        ) : (
          <noscript />
        )}
        <DivSumIns>
          <HeaderSumIns>Plan type & Policy type</HeaderSumIns>
          <PSumIns>
            <PTagLine className="font-weight-bold" name="plan_policy_type">
              {planType}
            </PTagLine>
          </PSumIns>
        </DivSumIns>
      </TagLineDiv>
      <DivSumIns>
        <HeaderSumIns>IDV Value</HeaderSumIns>
        <PSumIns name="idv_value">
          {selectedQuote?.idv*1
            ? `₹ ${currencyFormater(selectedQuote?.idv)}`
            : `N/A`}
        </PSumIns>
      </DivSumIns>
    </Row>
  );
};

export default PlanDetails;
