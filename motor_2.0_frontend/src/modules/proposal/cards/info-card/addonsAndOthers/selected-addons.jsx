import React from "react";
import { DivBenefits, DivTag, PBenefits, PTag, RowTag } from "../info-style";
import { Badge } from "react-bootstrap";

const SelectedAddons = ({
  lessthan767,
  showAddonsInfo,
  addonsInfo,
  Additional,
  Theme,
  selectedQuote,
}) => {
  return (
    <RowTag>
      <DivBenefits margin={lessthan767}>
        {lessthan767 ? (
          <PTag onClick={() => showAddonsInfo((prev) => !prev)}>
            Selected Addons
            <i
              style={{
                fontSize: "18px",
                position: "relative",
                top: "2.2px",
              }}
              className={
                addonsInfo ? "ml-1 fa fa-angle-up" : "ml-1 fa fa-angle-down"
              }
            ></i>
          </PTag>
        ) : (
          <PTag>Selected Addons</PTag>
        )}
        {addonsInfo && (
          <DivTag>
            {Additional?.applicableAddons?.map(
              ({ name, premium, sumInsured }, index) => (
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
                  <span name="selected_addon">
                    {" "}
                    {name === "Zero Depreciation" &&
                    selectedQuote?.companyAlias === "godigit" &&
                    selectedQuote?.claimsCovered
                      ? `${name} (${selectedQuote?.claimsCovered})`
                      : name}
                  </span>
                  {premium * 1 || sumInsured * 1 ? (
                    <>
                      <br />
                      <Badge
                        variant={
                          Theme?.sideCardProposal?.badge
                            ? Theme?.sideCardProposal?.badge
                            : `success`
                        }
                        name="selected_addon_premium"
                      >{`₹ ${Math.round(premium || sumInsured)}`}</Badge>
                    </>
                  ) : (
                    <noscript />
                  )}
                </PBenefits>
              )
            )}
          </DivTag>
        )}
      </DivBenefits>
    </RowTag>
  );
};

export default SelectedAddons;
