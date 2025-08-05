import React from "react";
import { Row } from "react-bootstrap";
import {
  DivPremium,
  HeaderPremium,
  LiTag,
  RowTagPlan,
  SpanTagRight,
  UlTag,
} from "../info-style";
import { TypeReturn } from "modules/type";
import { CustomTooltip } from "components";

const VehicleDetail = ({
  lessthan767,
  showVehicleInfo,
  vehicleInfo,
  type,
  temp_data,
  VehicleDetails,
}) => {
  const manfLength =
    VehicleDetails?.manfName && VehicleDetails?.manfName.length > 21;
  const modelNameLength =
    VehicleDetails?.modelName && VehicleDetails?.modelName.length > 36;
  const variantLength =
    VehicleDetails?.versionName && VehicleDetails?.versionName.length > 30;

  const version =
    `${VehicleDetails?.versionName} ${TypeReturn(type) !== "bike" ? "-" : ""} ${
      TypeReturn(type) !== "bike"
        ? temp_data?.parent?.productSubTypeCode === "GCV"
          ? VehicleDetails?.grossVehicleWeight ||
            temp_data?.corporateVehiclesQuoteRequest?.defaultGvw ||
            "N/A"
          : VehicleDetails?.cubicCapacity || "N/A"
        : ""
    } ${
      TypeReturn(type) !== "bike"
        ? temp_data?.parent?.productSubTypeCode === "GCV"
          ? "gvw"
          : "CC"
        : ""
    }` || `N/A`;

  const isGwvSelected =
    temp_data?.corporateVehiclesQuoteRequest?.selectedGvw &&
    temp_data?.corporateVehiclesQuoteRequest?.defaultGvw &&
    temp_data?.corporateVehiclesQuoteRequest?.selectedGvw * 1 !==
      temp_data?.corporateVehiclesQuoteRequest?.defaultGvw * 1;

  return (
    <>
      <Row>
        <DivPremium>
          {lessthan767 ? (
            <HeaderPremium
              onClick={() => showVehicleInfo((prev) => !prev)}
              className={!vehicleInfo ? "mb-2" : ""}
            >
              {vehicleInfo ? "Vehicle Details" : "Vehicle Details"}
              <i
                style={{
                  fontSize: "18px",
                  position: "relative",
                  top: "2.2px",
                }}
                className={
                  vehicleInfo ? "ml-1 fa fa-angle-up" : "ml-1 fa fa-angle-down"
                }
              ></i>
            </HeaderPremium>
          ) : (
            <HeaderPremium>Vehicle Details</HeaderPremium>
          )}
        </DivPremium>
      </Row>
      {vehicleInfo && (
        <RowTagPlan
          xs={1}
          sm={1}
          md={1}
          lg={1}
          xl={1}
        >
          <CustomTooltip
            rider={true}
            id={
              (manfLength || modelNameLength || variantLength) && "mmvTooltip"
            }
            place={"right"}
            customClassName="mt-3"
            mmvText
          >
            <label
              style={{ marginBottom: "-4px" }}
              data-tip={
                !lessthan767 &&
                `<div>
                    Manfacture: ${VehicleDetails?.manfName} <br />
                    Model: ${VehicleDetails?.modelName} <br />
                    ${
                      VehicleDetails?.versionName
                        ? `Variant: ${version} <br />`
                        : ""
                    }
                    ${
                      isGwvSelected
                        ? `Selected GVW: ${
                            temp_data?.corporateVehiclesQuoteRequest
                              ?.selectedGvw || "N/A"
                          } <br />`
                        : ""
                    } <br />
                    ${
                      VehicleDetails?.fuelType
                        ? `Fuel Type: ${VehicleDetails?.fuelType} <br />`
                        : ""
                    }
                  </div>`
              }
              data-html={true}
              data-for={!lessthan767 && "mmvTooltip"}
            >
              <UlTag>
                <LiTag>
                  Manufacturer Name
                  <SpanTagRight
                    length={manfLength}
                    name="manf_name"
                  >
                    {`${VehicleDetails?.manfName}` || `N/A`}
                  </SpanTagRight>
                </LiTag>
                <LiTag>
                  Model Name
                  <SpanTagRight
                    length={modelNameLength}
                    name="model_name"
                  >
                    {`${VehicleDetails?.modelName}` || `N/A`}
                  </SpanTagRight>
                </LiTag>
                {VehicleDetails?.versionName ? (
                  <LiTag>
                    Variant
                    <SpanTagRight
                      length={variantLength}
                      name="model_version"
                    >
                      {version}
                    </SpanTagRight>
                  </LiTag>
                ) : (
                  <noscript />
                )}
                {isGwvSelected ? (
                  <LiTag>
                    Selected GVW
                    <SpanTagRight name="selected_gvw">
                      {`${temp_data?.corporateVehiclesQuoteRequest?.selectedGvw} lbs` ||
                        `N/A`}
                    </SpanTagRight>
                  </LiTag>
                ) : (
                  <noscript />
                )}
                {VehicleDetails?.fuelType ? (
                  <LiTag>
                    Fuel Type
                    <SpanTagRight name="fuel_type">
                      {`${VehicleDetails?.fuelType}` || `N/A`}
                    </SpanTagRight>
                  </LiTag>
                ) : (
                  <noscript />
                )}
              </UlTag>
            </label>
          </CustomTooltip>
        </RowTagPlan>
      )}
    </>
  );
};

export default VehicleDetail;
