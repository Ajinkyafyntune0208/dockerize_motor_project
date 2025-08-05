import React from "react";
import { Row } from "react-bootstrap";
import {
  DivPremium,
  DivTotal,
  HeaderPremium,
  LiTag,
  RowTagPlan,
  SpanTagRight,
  StrongTag,
  UlTag,
} from "../info-style";
import { currencyFormater } from "utils";

const PBSection = ({
  lessthan767,
  showBreakup,
  breakup,
  quoteLog,
  temp_data,
}) => {
  return (
    <>
      {!(
        temp_data?.quoteLog?.premiumJson?.isRenewal === "Y" &&
        temp_data?.quoteLog?.premiumJson?.companyAlias === "bajaj_allianz"
      ) ? (
        <Row>
          <DivPremium>
            {lessthan767 ? (
              <HeaderPremium
                onClick={() => showBreakup((prev) => !prev)}
                className={!breakup ? "mb-3" : ""}
              >
                Premium Break-up
                <i
                  style={{
                    fontSize: "18px",
                    position: "relative",
                    top: "2.2px",
                  }}
                  className={
                    breakup ? "ml-1 fa fa-angle-up" : "ml-1 fa fa-angle-down"
                  }
                ></i>
              </HeaderPremium>
            ) : (
              <HeaderPremium>Premium Break-up</HeaderPremium>
            )}
          </DivPremium>
        </Row>
      ) : (
        <noscript />
      )}
      {breakup &&
        !(
          temp_data?.quoteLog?.premiumJson?.isRenewal === "Y" &&
          temp_data?.quoteLog?.premiumJson?.companyAlias === "bajaj_allianz"
        ) && (
          <RowTagPlan
            xs={1}
            sm={1}
            md={1}
            lg={1}
            xl={1}
          >
            <UlTag>
              {quoteLog?.odPremium === 0 &&
              temp_data?.quoteLog?.premiumJson?.isRenewal === "Y" &&
              temp_data?.quoteLog?.premiumJson?.companyAlias ===
                "bajaj_allianz" ? (
                <noscript />
              ) : (
                <LiTag>
                  Own Damage Premium
                  <SpanTagRight name="own_damage_premium">{`₹ ${
                    currencyFormater(quoteLog?.odPremium) || `0`
                  }`}</SpanTagRight>
                </LiTag>
              )}
              {quoteLog?.tpPremium === 0 &&
              temp_data?.quoteLog?.premiumJson?.isRenewal === "Y" &&
              temp_data?.quoteLog?.premiumJson?.companyAlias ===
                "bajaj_allianz" ? (
                <noscript />
              ) : (
                <LiTag>
                  Third Party Premium
                  <SpanTagRight name="third_party_premium">{`₹ ${
                    currencyFormater(
                      quoteLog?.tpPremium -
                        (quoteLog?.premiumJson?.tppdDiscount * 1 || 0)
                    ) || `0`
                  }`}</SpanTagRight>
                </LiTag>
              )}
              {quoteLog?.addonPremium === 0 &&
              temp_data?.quoteLog?.premiumJson?.isRenewal === "Y" &&
              temp_data?.quoteLog?.premiumJson?.companyAlias ===
                "bajaj_allianz" ? (
                <noscript />
              ) : (
                <LiTag>
                  Addon Premium
                  <SpanTagRight name="addon_premium">{`₹ ${
                    currencyFormater(quoteLog?.addonPremium) || `0`
                  }`}</SpanTagRight>
                </LiTag>
              )}
              <LiTag>
                Total Discount{" "}
                {temp_data?.selectedQuote?.policyType !== "Third Party"
                  ? `(NCB ${
                      temp_data?.corporateVehiclesQuoteRequest?.applicableNcb ||
                      quoteLog?.quoteDetails?.applicableNcb ||
                      `0`
                    }% Incl.)`
                  : ``}
                <SpanTagRight name="total_discount">
                  {" "}
                  {`- ₹ ${currencyFormater(
                    temp_data?.selectedQuote?.finalTotalDiscount * 1
                      ? temp_data?.selectedQuote?.finalTotalDiscount * 1
                      : 0
                  )} `}
                </SpanTagRight>
              </LiTag>
              <LiTag>
                {"GST"}
                <SpanTagRight name="gst">{`₹ ${
                  currencyFormater(quoteLog?.serviceTax) || `0`
                }`}</SpanTagRight>
              </LiTag>
            </UlTag>
          </RowTagPlan>
        )}
      <Row
        xs={1}
        sm={1}
        md={1}
        lg={1}
        xl={1}
      >
        <DivTotal>
          <div>
            <small>Total Premium Payable</small>
          </div>
          <div>
            <StrongTag name="total_premium_payable">{`₹ ${
              currencyFormater(quoteLog?.finalPremiumAmount) || `0`
            }`}</StrongTag>
          </div>
        </DivTotal>
      </Row>
    </>
  );
};

export default PBSection;
