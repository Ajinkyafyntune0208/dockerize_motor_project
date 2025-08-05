import React from "react";
import { DivBenefits, DivTag, PBenefits, PTag, RowTag } from "../info-style";
import { Badge } from "react-bootstrap";

const OtherCovers = ({ lessthan767, addonsInfo, Theme, temp_data }) => {
  return (
    <RowTag>
      <DivBenefits margin={lessthan767}>
        {lessthan767 ? (
          <PTag>
            Other Covers
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
          <PTag>Other Covers</PTag>
        )}
        {temp_data?.selectedQuote?.otherCovers?.legalLiabilityToEmployee !==
          undefined &&
          temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
            "C" && (
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
                Legal Liability To Employee
                {temp_data?.selectedQuote?.otherCovers
                  ?.legalLiabilityToEmployee * 1 ? (
                  <>
                    <br />
                    <Badge
                      variant={
                        Theme?.sideCardProposal?.badge
                          ? Theme?.sideCardProposal?.badge
                          : `success`
                      }
                      name="legal_employee_liability"
                    >{`₹ ${Math.round(
                      temp_data?.selectedQuote?.otherCovers
                        ?.legalLiabilityToEmployee
                    )}`}</Badge>
                  </>
                ) : (
                  <noscript />
                )}
              </PBenefits>
            </DivTag>
          )}
      </DivBenefits>
    </RowTag>
  );
};

export default OtherCovers;
