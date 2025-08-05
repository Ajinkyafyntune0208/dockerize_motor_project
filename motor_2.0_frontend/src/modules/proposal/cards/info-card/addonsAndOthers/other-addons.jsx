import React from "react";
import { DivBenefits, DivTag, PBenefits, PTag, RowTag } from "../info-style";
import { Badge } from "react-bootstrap";
import _ from "lodash";

const OtherAddons = ({
  lessthan767,
  showOAddonsInfo,
  oAddonsInfo,
  addonsInfo,
  others,
  Theme,
  temp_data,
}) => {
  return (
    <RowTag>
      <DivBenefits margin={lessthan767}>
        {lessthan767 ? (
          <PTag onClick={() => showOAddonsInfo((prev) => !prev)}>
            Other Addons
            <i
              style={{
                fontSize: "18px",
                position: "relative",
                top: "2.2px",
              }}
              className={
                oAddonsInfo ? "ml-1 fa fa-angle-up" : "ml-1 fa fa-angle-down"
              }
            ></i>
          </PTag>
        ) : (
          <PTag>Other Addons</PTag>
        )}
        {(addonsInfo && (
          <DivTag>
            {others?.map((name, index) =>
              name === "lLPaidDriver" ? (
                <noscript />
              ) : (
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
                  {name
                    .replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`)
                    .replace(/_/g, " ")
                    .split(" ")
                    .map(_.capitalize)
                    .join(" ")}
                  {temp_data?.selectedQuote?.addOnsData?.other[name] * 1 ? (
                    <>
                      <br />
                      <Badge
                        variant={
                          Theme?.sideCardProposal?.badge
                            ? Theme?.sideCardProposal?.badge
                            : `success`
                        }
                        name="other_addons"
                      >{`₹ ${Math.round(
                        temp_data?.selectedQuote?.addOnsData?.other[name] * 1
                      )}`}</Badge>
                    </>
                  ) : (
                    <noscript />
                  )}
                </PBenefits>
              )
            )}
          </DivTag>
        )) || <noscript />}
      </DivBenefits>
    </RowTag>
  );
};

export default OtherAddons;
