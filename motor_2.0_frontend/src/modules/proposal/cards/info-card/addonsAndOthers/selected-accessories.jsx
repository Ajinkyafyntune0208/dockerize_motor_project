import React from "react";
import { DivBenefits, DivTag, PBenefits, PTag, RowTag } from "../info-style";
import { Badge } from "react-bootstrap";
import _ from "lodash";

const SelectedAccessories = ({
  lessthan767,
  showAccesInfo,
  accesInfo,
  FilteredAccessories,
  temp_data,
  Theme,
}) => {
  return (
    <RowTag>
      <DivBenefits margin={lessthan767}>
        {lessthan767 ? (
          <PTag onClick={() => showAccesInfo((prev) => !prev)}>
            Selected Accessories
            <i
              style={{
                fontSize: "18px",
                position: "relative",
                top: "2.2px",
              }}
              className={
                accesInfo ? "ml-1 fa fa-angle-up" : "ml-1 fa fa-angle-down"
              }
            ></i>
          </PTag>
        ) : (
          <PTag>Selected Accessories</PTag>
        )}
        {accesInfo && (
          <DivTag>
            {_.compact([
              ...FilteredAccessories,
              temp_data?.selectedQuote?.addOnsData?.other &&
              !_.isEmpty(
                Object.keys(temp_data?.selectedQuote?.addOnsData?.other)
              ) &&
              Object.keys(temp_data?.selectedQuote?.addOnsData?.other).includes(
                "lLPaidDriver"
              )
                ? { name: "LL Paid Driver" }
                : "",
            ])?.map(({ name, sumInsured }, index) => (
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
                <span name="accessories">{name}</span>
                {sumInsured * 1 ? (
                  <>
                    <br />
                    <Badge
                      variant={
                        Theme?.sideCardProposal?.badge
                          ? Theme?.sideCardProposal?.badge
                          : `success`
                      }
                      name="accessories_sum_insured"
                    >{`₹ ${sumInsured}`}</Badge>
                  </>
                ) : (
                  <noscript />
                )}
              </PBenefits>
            ))}
          </DivTag>
        )}
      </DivBenefits>
    </RowTag>
  );
};

export default SelectedAccessories;
