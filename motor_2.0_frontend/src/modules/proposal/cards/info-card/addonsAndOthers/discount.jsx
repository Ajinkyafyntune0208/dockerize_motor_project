import React from "react";
import { DivBenefits, DivTag, PBenefits, PTag, RowTag } from "../info-style";
import { Badge } from "react-bootstrap";
import _ from "lodash";

const Discount = ({
  lessthan767,
  showDiscountInfo,
  discountInfo,
  FilteredDiscounts,
  Theme,
  quoteLog,
}) => {
  return (
    <RowTag>
      <DivBenefits margin={lessthan767}>
        {lessthan767 ? (
          <PTag onClick={() => showDiscountInfo((prev) => !prev)}>
            Discounts
            <i
              style={{
                fontSize: "18px",
                position: "relative",
                top: "2.2px",
              }}
              className={
                discountInfo ? "ml-1 fa fa-angle-up" : "ml-1 fa fa-angle-down"
              }
            ></i>
          </PTag>
        ) : (
          <PTag>Discounts</PTag>
        )}
        {discountInfo ? (
          <DivTag>
            {FilteredDiscounts?.map(({ name, sumInsured }, index) => (
              <PBenefits key={index}>
                <span className="mr-1">
                  <i
                    className={`fa fa-star ${
                      Theme?.sideCardProposal?.icon
                        ? Theme?.sideCardProposal?.icon
                        : "text-success"
                    }`}
                  />
                </span>
                {name === "voluntary_insurer_discounts"
                  ? "Voluntary Deductibles"
                  : name
                      .replace(/_/g, " ")
                      .split(" ")
                      .map(_.capitalize)
                      .join(" ")}
                {sumInsured * 1 ? (
                  <>
                    <br />
                    <Badge
                      variant={
                        Theme?.sideCardProposal?.badge
                          ? Theme?.sideCardProposal?.badge
                          : `success`
                      }
                      name="voluntary_insurer_discount"
                    >{`₹ ${sumInsured}`}</Badge>
                  </>
                ) : (
                  <noscript />
                )}
              </PBenefits>
            ))}
          </DivTag>
        ) : (
          <noscript />
        )}
        {quoteLog?.premiumJson?.tppdDiscount * 1 ? (
          <DivTag>
            <PBenefits>
              <span className="mr-1">
                <i
                  className={`fa fa-star ${
                    Theme?.sideCardProposal?.icon
                      ? Theme?.sideCardProposal?.icon
                      : "text-success"
                  }`}
                />
              </span>
              {"TPPD Discount"}
              <br />
              <Badge
                variant={
                  Theme?.sideCardProposal?.badge
                    ? Theme?.sideCardProposal?.badge
                    : `success`
                }
                name="tppd_discount"
              >{`₹ ${quoteLog?.premiumJson?.tppdDiscount}`}</Badge>
            </PBenefits>
          </DivTag>
        ) : (
          <noscript />
        )}
      </DivBenefits>
    </RowTag>
  );
};

export default Discount;
