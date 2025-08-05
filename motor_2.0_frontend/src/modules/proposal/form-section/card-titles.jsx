import React from "react";
import { Button as Btn } from "react-bootstrap";
import swal from "sweetalert";
import { Label } from "../style";
import { getLogoCvType, _haptics } from "utils";

//Card Summary Title Function
export function Titles(
  titleName,
  stateName,
  TempData,
  rsKycStatus,
  setDropout,
  breakinCase,
  GenerateDulicateEnquiry,
  icr,
  TypeReturn,
  type,
  submitProcess
) {
  return (
    <div style={{ display: "flex", width: "100%" }}>
      <Label style={{ width: "95%" }}>{titleName}</Label>
      {(!breakinCase ||
        (TypeReturn(type) === "bike" &&
          breakinCase &&
          TempData?.selectedQuote?.companyAlias !== "godigit" &&
          TempData?.selectedQuote?.companyAlias !== "icici_lombard" &&
          TempData?.selectedQuote?.companyAlias !== "united_india")) &&
        !icr &&
        !rsKycStatus?.kyc_status &&
        !submitProcess && (
          <div
            style={{
              display: "flex",
              justifyContent: "flex-end",
              marginTop: "-2px",
            }}
          >
            <Btn
              size="sm"
              style={{ background: "transparent", border: "none" }}
              variant="light"
              id={`${titleName}_edit`}
              onClick={() => {
                if (
                  ["Payment Initiated", "payment failed"].includes(
                    ["payment failed"].includes(
                      TempData?.journeyStage?.stage.toLowerCase()
                    )
                      ? TempData?.journeyStage?.stage.toLowerCase()
                      : TempData?.journeyStage?.stage
                  )
                ) {
                  swal({
                    title: "Confirm Action",
                    text: `Payment status is Incomplete. To edit the Proposal an update is required.`,
                    icon: "info",
                    buttons: {
                      cancel: "Cancel",
                      catch: {
                        text: "Confirm",
                        value: "confirm",
                      },
                    },
                    dangerMode: true,
                  }).then((caseValue) => {
                    switch (caseValue) {
                      case "confirm":
                        return GenerateDulicateEnquiry();
                      // break;
                      default:
                    }
                  });
                } else {
                  _haptics([100, 0, 50]);
                  stateName("form");
                  setDropout(false);
                }
              }}
            >
              <i
                className="fa fa-pencil-square-o"
                aria-hidden="true"
                style={{
                  color: "#fff",
                  fontWeight: "bold",
                  fontSize: "19px",
                }}
              />
            </Btn>
          </div>
        )}
    </div>
  );
}

export const TitleState = (
  formState,
  titleCase,
  titleSubcase,
  type,
  TempData,
  lessthan768
) => {
  if (titleCase === "state") {
    return formState === "hidden" ? `` : formState === "summary" ? 1 : 2;
  } else if (titleCase === "radius") {
    return formState === "hidden" ? "5px 5px 5px 5px" : "5px 5px 0px 0px";
  } else if (titleCase === "animate") {
    return formState === "hidden"
      ? {
          maxHeight: "0",
          transition: "max-height 0.4s ease-in-out",
        }
      : {
          maxHeight: "100%",
          transition: "max-height 0.4s ease-in-out",
        };
  } else if (titleCase === "image") {
    switch (titleSubcase) {
      case "image":
        return type === "bike"
          ? `${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ""
            }/assets/images/vehicle/bike3.png`
          : type === "car"
          ? `${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ""
            }/assets/images/taxi-car1.png`
          : getLogoCvType(Number(TempData?.productSubTypeId));
      case "image-style":
        return lessthan768
          ? {
              position: "relative",
              top: "1px",
              left: "18px",
            }
          : {
              position: "relative",
              top: "-4px",
              left: "18px",
            };
      default:
        break;
    }
  }
};
