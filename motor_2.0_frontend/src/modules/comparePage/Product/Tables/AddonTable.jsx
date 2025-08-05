import React from "react";
import { Table } from "react-bootstrap";
import _ from "lodash";
import { currencyFormater } from "utils";
import OptionalTooltip from "../OptionalTooltip";
import { TypeReturn } from "modules/type";
import Badges from "../Badges";
import { NoAddonCotainer } from "../ProductStyle";

export const AddonTable = ({
  quote,
  temp_data,
  addOnsAndOthers,
  type,
  GetAddonValue,
}) => {
  return (
    <Table className="addonTable">
      <tr
        style={{
          display:
            (temp_data?.odOnly ||
              temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                "C") &&
            "none",
        }}
      >
        {addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        ) &&
        _.isEmpty(addOnsAndOthers?.isTenure) &&
        quote?.compulsoryPaOwnDriver * 1 ? (
          <td className="addonValues" name="cpa_value">
            {quote?.compulsoryPaOwnDriver * 1
              ? `₹ ${currencyFormater(parseInt(quote?.compulsoryPaOwnDriver))}`
              : "N/A"}
          </td>
        ) : quote?.compulsoryPaOwnDriver * 1 ||
          quote?.copiedSingleYearCpa * 1 ? (
          <td className="addonValues">
            <OptionalTooltip id={"A1"} name="cpa_tooltip_value" />
          </td>
        ) : (
          <td className="addonValues">
            <Badges title={"Not Available"} name="cpa_badge_NA_value" />
          </td>
        )}
      </tr>
      {TypeReturn(type) !== "cv" && temp_data?.newCar && (
        <tr
          style={{
            display:
              (temp_data?.odOnly ||
                temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                  "C") &&
              "none",
          }}
        >
          {!_.isEmpty(addOnsAndOthers?.isTenure) ? (
            !quote?.multiYearCpa * 1 ? (
              <td className="addonValues">
                <Badges
                  title={"Not Available"}
                  name="multiyear_cpa_badge_NA_value"
                />
              </td>
            ) : (
              <td className="addonValues" name="multiyear_cpa_value">
                ₹ {currencyFormater(parseInt(quote?.multiYearCpa))}
              </td>
            )
          ) : quote?.multiYearCpa * 1 || quote?.copiedMultiYearCpa * 1 ? (
            <td className="addonValues">
              <OptionalTooltip id={"A2"} name="multiyear_tooltip_value" />
            </td>
          ) : (
            <td className="addonValues">
              <Badges title={"Not Available"} name="multiyear_cpa_NA_value" />
            </td>
          )}
        </tr>
      )}
      <tr>
        {GetAddonValue("zeroDepreciation", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A3"} name="zero_dep_NS_value" />
          </td>
        ) : GetAddonValue(
            "zeroDepreciation",
            quote?.addonDiscountPercentage1
          ) === "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("zeroDepreciation") ? (
              <OptionalTooltip id={"A4"} name="zero_dep_NA_value" />
            ) : (
              <Badges title={"Not Available"} name="zero_dep_badge_NA_value" />
            )}
          </td>
        ) : (
          <td className="addonValues" name="zero_dep_value">
            {GetAddonValue("zeroDepreciation", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>

      <tr>
        {GetAddonValue(
          "roadSideAssistance",
          quote?.addonDiscountPercentage1
        ) === "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A5"} name="road_side_assist_NS_value" />
          </td>
        ) : GetAddonValue(
            "roadSideAssistance",
            quote?.addonDiscountPercentage1
          ) === "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("roadSideAssistance") ? (
              <OptionalTooltip id={"A6"} name="road_side_assist_NA_value" />
            ) : (
              <Badges
                title={"Not Available"}
                name="road_side_assist_badge_NA_value"
              />
            )}
          </td>
        ) : (
          <td className="addonValues" name="road_side_assist_value">
            {GetAddonValue(
              "roadSideAssistance",
              quote?.addonDiscountPercentage1
            )}
          </td>
        )}
      </tr>

      {TypeReturn(type) === "cv" && (
        <>
          <tr>
            {GetAddonValue("imt23", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A7"} name="imt_23_NS_value" />
              </td>
            ) : GetAddonValue("imt23", quote?.addonDiscountPercentage1) ===
              "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("imt23") ? (
                  <OptionalTooltip id={"A8"} name="imt_23_NA_value" />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="imt_23_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="imt_23_value">
                {GetAddonValue("imt23", quote?.addonDiscountPercentage1)}
              </td>
            )}
          </tr>
          {temp_data?.journeyCategory !== "GCV" && (
            <tr>
              {GetAddonValue("consumables", quote?.addonDiscountPercentage1) ===
              "N/S" ? (
                <td className="addonValues">
                  <OptionalTooltip id={"A9"} name="consumables_NS_value" />
                </td>
              ) : GetAddonValue(
                  "consumables",
                  quote?.addonDiscountPercentage1
                ) === "N/A" ? (
                <td className="addonValues">
                  {quote?.applicableAddons?.includes("consumables") ? (
                    <OptionalTooltip id={"A10"} name="consumables_NA_value" />
                  ) : (
                    <Badges
                      title={"Not Available"}
                      name="consumables_badge_NA_value"
                    />
                  )}
                </td>
              ) : (
                <td className="addonValues" name="consumables_value">
                  {GetAddonValue(
                    "consumables",
                    quote?.addonDiscountPercentage1
                  )}
                </td>
              )}
            </tr>
          )}
        </>
      )}

      {(TypeReturn(type) === "car" || TypeReturn(type) === "bike") && (
        <>
          <tr
            style={{
              display: TypeReturn(type) === "bike" && "none",
            }}
          >
            {GetAddonValue("keyReplace", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A11"} name="key_replace_NS_value" />
              </td>
            ) : GetAddonValue("keyReplace", quote?.addonDiscountPercentage1) ===
              "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("keyReplace") ? (
                  <OptionalTooltip id={"A12"} name="key_replace_NA_value" />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="key_replace_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="key_replace_value">
                {GetAddonValue("keyReplace", quote?.addonDiscountPercentage1)}
              </td>
            )}
          </tr>
          <tr>
            {GetAddonValue(
              "engineProtector",
              quote?.addonDiscountPercentage1
            ) === "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A13"} name="engine_protector_NS_value" />
              </td>
            ) : GetAddonValue(
                "engineProtector",
                quote?.addonDiscountPercentage1
              ) === "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("engineProtector") ? (
                  <OptionalTooltip
                    id={"A14"}
                    name="engine_protector_NA_value"
                  />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="engine_protector_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="engine_protector_value">
                {GetAddonValue(
                  "engineProtector",
                  quote?.addonDiscountPercentage1
                )}
              </td>
            )}
          </tr>
          <tr
            style={{
              display: TypeReturn(type) === "bike" && "none",
            }}
          >
            {GetAddonValue("ncbProtection", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A15"} name="ncb_protection_NS_value" />
              </td>
            ) : GetAddonValue(
                "ncbProtection",
                quote?.addonDiscountPercentage1
              ) === "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("ncbProtection") ? (
                  <OptionalTooltip id={"A16"} name="ncb_protection_NA_value" />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="ncb_protection_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="ncb_protection_value">
                {GetAddonValue(
                  "ncbProtection",
                  quote?.addonDiscountPercentage1
                )}
              </td>
            )}
          </tr>
          <tr>
            {GetAddonValue("consumables", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A17"} name="consumables_NS_value" />
              </td>
            ) : GetAddonValue(
                "consumables",
                quote?.addonDiscountPercentage1
              ) === "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("consumables") ? (
                  <OptionalTooltip id={"A18"} name="consumables_NA_value" />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="consumables_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="consumables_value">
                {GetAddonValue("consumables", quote?.addonDiscountPercentage1)}
              </td>
            )}
          </tr>
          <tr
            style={{
              display: TypeReturn(type) === "bike" && "none",
            }}
          >
            {GetAddonValue("tyreSecure", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A19"} name="tyre_secure_NS_value" />
              </td>
            ) : GetAddonValue("tyreSecure", quote?.addonDiscountPercentage1) ===
              "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("tyreSecure") ? (
                  <OptionalTooltip id={"A20"} name="tyre_secure_NA_value" />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="tyre_secure_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="tyre_secure_value">
                {GetAddonValue("tyreSecure", quote?.addonDiscountPercentage1)}
              </td>
            )}
          </tr>
          <tr>
            {GetAddonValue(
              "returnToInvoice",
              quote?.addonDiscountPercentage1
            ) === "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A21"} name="return_to_invoice_NS_value" />
              </td>
            ) : GetAddonValue(
                "returnToInvoice",
                quote?.addonDiscountPercentage1
              ) === "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("returnToInvoice") ? (
                  <OptionalTooltip
                    id={"A22"}
                    name="return_to_invoice_NA_value"
                  />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="return_to_invoice_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="return_to_invoice_value">
                {GetAddonValue(
                  "returnToInvoice",
                  quote?.addonDiscountPercentage1
                )}
              </td>
            )}
          </tr>
          <tr
            style={{
              display: TypeReturn(type) === "bike" && "none",
            }}
          >
            {GetAddonValue("lopb", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A23"} name="lopb_NS_value" />
              </td>
            ) : GetAddonValue("lopb", quote?.addonDiscountPercentage1) ===
              "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("lopb") ? (
                  <OptionalTooltip id={"A22"} name="lopb_NA_value" />
                ) : (
                  <Badges title={"Not Available"} name="lopb_badge_NA_value" />
                )}
              </td>
            ) : (
              <td className="addonValues" name="lopb_value">
                {GetAddonValue("lopb", quote?.addonDiscountPercentage1)}
              </td>
            )}
          </tr>
        </>
      )}

      <tr
        style={
          TypeReturn(type) === "cv"
            ? {
                display: "none",
              }
            : {}
        }
      >
        {GetAddonValue(
          "emergencyMedicalExpenses",
          quote?.addonDiscountPercentage1
        ) === "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip
              id={"A27"}
              name="emergency_medical_expenses_NS_value"
            />
          </td>
        ) : GetAddonValue(
            "emergencyMedicalExpenses",
            quote?.addonDiscountPercentage1
          ) === "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("emergencyMedicalExpenses") ? (
              <OptionalTooltip
                id={"A28"}
                name="emergency_medical_expenses_NA_value"
              />
            ) : (
              <Badges
                title={"Not Available"}
                name="emergency_medical_expenses_badge_NA_value"
              />
            )}
          </td>
        ) : (
          <td className="addonValues" name="emergency_medical_expenses_value">
            {GetAddonValue(
              "emergencyMedicalExpenses",
              quote?.addonDiscountPercentage1
            )}
          </td>
        )}
      </tr>
      <tr>
        {GetAddonValue("windShield", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A29"} name="wind_shield_NS_value" />
          </td>
        ) : GetAddonValue("windShield", quote?.addonDiscountPercentage1) ===
          "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("windShield") ? (
              <OptionalTooltip id={"A29"} name="wind_shield_NA_value" />
            ) : (
              <Badges
                title={"Not Available"}
                name="wind_shield_badge_NA_value"
              />
            )}
          </td>
        ) : (
          <td className="addonValues" name="wind_shield_value">
            {GetAddonValue("windShield", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>
      <tr>
        {GetAddonValue("emiProtection", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A30"} name="emiProtection_NS_value" />
          </td>
        ) : GetAddonValue("emiProtection", quote?.addonDiscountPercentage1) ===
          "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("emiProtection") ? (
              <OptionalTooltip id={"A30"} name="emi_Protection_NA_value" />
            ) : (
              <Badges title={"Not Available"} name="emi_Protection_NA_value" />
            )}
          </td>
        ) : (
          <td className="addonValues" name="emi_Protection_value">
            {GetAddonValue("emiProtection", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>
      <tr>
        {GetAddonValue("additionalTowing", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A30"} name="additional_towing_NS_value" />
          </td>
        ) : GetAddonValue(
            "additionalTowing",
            quote?.addonDiscountPercentage1
          ) === "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("additionalTowing") ? (
              <OptionalTooltip id={"A30"} name="additional_towing_NA_value" />
            ) : (
              <Badges
                title={"Not Available"}
                name="additional_towing_NA_value"
              />
            )}
          </td>
        ) : (
          <td className="addonValues" name="additional_towing_value">
            {GetAddonValue("additionalTowing", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>
      <tr>
        {GetAddonValue("batteryProtect", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A30"} name="batteryProtect_NS_value" />
          </td>
        ) : GetAddonValue("batteryProtect", quote?.addonDiscountPercentage1) ===
          "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("batteryProtect") ? (
              <OptionalTooltip id={"A30"} name="battery_Protect_NA_value" />
            ) : (
              <Badges title={"Not Available"} name="battery_Protect_NA_value" />
            )}
          </td>
        ) : (
          <td className="addonValues" name="battery_Protect_value">
            {GetAddonValue("batteryProtect", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>
    </Table>
  );
};

export const AddonTable1 = ({
  quote,
  temp_data,
  addOnsAndOthers,
  type,
  GetAddonValue,
}) => {
  return (
    <Table className="addonTable">
      <tr
        style={{
          display:
            (temp_data?.odOnly ||
              temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                "C") &&
            "none",
        }}
      >
        {addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        ) &&
        _.isEmpty(addOnsAndOthers?.isTenure) &&
        quote?.compulsoryPaOwnDriver * 1 ? (
          <td className="addonValues" name="cpa_value">
            ₹ {currencyFormater(parseInt(quote?.compulsoryPaOwnDriver))}
          </td>
        ) : quote?.compulsoryPaOwnDriver * 1 ||
          quote?.copiedSingleYearCpa * 1 ? (
          <td className="addonValues">
            <OptionalTooltip id={"A1"} name="cpa_tooltip_value" />
          </td>
        ) : (
          <td className="addonValues">
            <Badges title={"Not Available"} name="cpa_badge_NA_value" />
          </td>
        )}
      </tr>
      {TypeReturn(type) !== "cv" && temp_data?.newCar && (
        <tr
          style={{
            display:
              (temp_data?.odOnly ||
                temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                  "C") &&
              "none",
          }}
        >
          {!_.isEmpty(addOnsAndOthers?.isTenure) && quote?.multiYearCpa * 1 ? (
            <td className="addonValues" name="multiyear_cpa_value">
              ₹ {currencyFormater(parseInt(quote?.multiYearCpa))}
            </td>
          ) : quote?.multiYearCpa * 1 || quote?.copiedMultiYearCpa * 1 ? (
            <td className="addonValues">
              <OptionalTooltip id={"A1"} name="multiyear_cpa_tooltip_value" />
            </td>
          ) : (
            <td className="addonValues" name="multiyear_cpa_NA_value">
              Not Available
            </td>
          )}
        </tr>
      )}
      <tr>
        {GetAddonValue("zeroDepreciation", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A3"} name="zero_dep_NS_value" />
          </td>
        ) : GetAddonValue(
            "zeroDepreciation",
            quote?.addonDiscountPercentage1
          ) === "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("zeroDepreciation") ? (
              <OptionalTooltip id={"A4"} name="zero_dep_NA_value" />
            ) : (
              <Badges title={"Not Available"} name="zero_dep_badge_NA_value" />
            )}
          </td>
        ) : (
          <td className="addonValues" name="zero_dep_value">
            {GetAddonValue("zeroDepreciation", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>

      <tr>
        {GetAddonValue(
          "roadSideAssistance",
          quote?.addonDiscountPercentage1
        ) === "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A5"} name="road_side_assist_NS_value" />
          </td>
        ) : GetAddonValue(
            "roadSideAssistance",
            quote?.addonDiscountPercentage1
          ) === "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("roadSideAssistance") ? (
              <OptionalTooltip id={"A6"} name="road_side_assist_NA_value" />
            ) : (
              <Badges
                title={"Not Available"}
                name="road_side_assist_badge_NA_value"
              />
            )}
          </td>
        ) : (
          <td className="addonValues" name="road_side_assist_value">
            {GetAddonValue(
              "roadSideAssistance",
              quote?.addonDiscountPercentage1
            )}
          </td>
        )}
      </tr>

      {TypeReturn(type) === "cv" && (
        <>
          <tr>
            {GetAddonValue("imt23", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A7"} name="imt23_NS_value" />
              </td>
            ) : GetAddonValue("imt23", quote?.addonDiscountPercentage1) ===
              "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("imt23") ? (
                  <OptionalTooltip id={"A8"} name="imt23_NA_value" />
                ) : (
                  <Badges title={"Not Available"} name="imt23_badge_NA_value" />
                )}
              </td>
            ) : (
              <td className="addonValues" name="imt23_value">
                {GetAddonValue("imt23", quote?.addonDiscountPercentage1)}
              </td>
            )}
          </tr>
          {temp_data?.journeyCategory !== "GCV" && (
            <tr>
              {GetAddonValue("consumables", quote?.addonDiscountPercentage1) ===
              "N/S" ? (
                <td className="addonValues">
                  <OptionalTooltip id={"A9"} name="consumables_NS_value" />
                </td>
              ) : GetAddonValue(
                  "consumables",
                  quote?.addonDiscountPercentage1
                ) === "N/A" ? (
                <td className="addonValues">
                  {quote?.applicableAddons?.includes("consumables") ? (
                    <OptionalTooltip id={"A10"} name="consumables_NA_value" />
                  ) : (
                    <Badges
                      title={"Not Available"}
                      name="consumables_badge_NA_value"
                    />
                  )}
                </td>
              ) : (
                <td className="addonValues" name="consumables_value">
                  {GetAddonValue(
                    "consumables",
                    quote?.addonDiscountPercentage1
                  )}
                </td>
              )}
            </tr>
          )}
        </>
      )}

      {(TypeReturn(type) === "car" || TypeReturn(type) === "bike") && (
        <>
          <tr
            style={{
              display: TypeReturn(type) === "bike" && "none",
            }}
          >
            {GetAddonValue("keyReplace", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A11"} name="key_replace_NS_value" />
              </td>
            ) : GetAddonValue("keyReplace", quote?.addonDiscountPercentage1) ===
              "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("keyReplace") ? (
                  <OptionalTooltip id={"A12"} name="key_replace_NA_value" />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="key_replace_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <NoAddonCotainer
                amount
                className="keyAddon"
                name="key_replace_value"
              >
                {GetAddonValue("keyReplace", quote?.addonDiscountPercentage1)}
              </NoAddonCotainer>
            )}
          </tr>
          <tr>
            {GetAddonValue(
              "engineProtector",
              quote?.addonDiscountPercentage1
            ) === "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A13"} name="engine_protector_NS_value" />
              </td>
            ) : GetAddonValue(
                "engineProtector",
                quote?.addonDiscountPercentage1
              ) === "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("engineProtector") ? (
                  <OptionalTooltip
                    id={"A14"}
                    name="engine_protector_NA_value"
                  />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="engine_protector_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="engine_protector_value">
                {GetAddonValue(
                  "engineProtector",
                  quote?.addonDiscountPercentage1
                )}
              </td>
            )}
          </tr>
          <tr
            style={{
              display: TypeReturn(type) === "bike" && "none",
            }}
          >
            {GetAddonValue("ncbProtection", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A15"} name="ncb_protection_NS_value" />
              </td>
            ) : GetAddonValue(
                "ncbProtection",
                quote?.addonDiscountPercentage1
              ) === "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("ncbProtection") ? (
                  <OptionalTooltip id={"A16"} name="ncb_protection_NA_value" />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="ncb_protection_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="ncb_protection_value">
                {GetAddonValue(
                  "ncbProtection",
                  quote?.addonDiscountPercentage1
                )}
              </td>
            )}
          </tr>
          <tr>
            {GetAddonValue("consumables", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A17"} name="consumables_NS_value" />
              </td>
            ) : GetAddonValue(
                "consumables",
                quote?.addonDiscountPercentage1
              ) === "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("consumables") ? (
                  <OptionalTooltip id={"A18"} name="consumables_NA_value" />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="consumables_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="consumables_value">
                {GetAddonValue("consumables", quote?.addonDiscountPercentage1)}
              </td>
            )}
          </tr>
          <tr
            style={{
              display: TypeReturn(type) === "bike" && "none",
            }}
          >
            {GetAddonValue("tyreSecure", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A19"} name="tyre_secure_NS_value" />
              </td>
            ) : GetAddonValue("tyreSecure", quote?.addonDiscountPercentage1) ===
              "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("tyreSecure") ? (
                  <OptionalTooltip id={"A20"} name="tyre_secure_NA_value" />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="tyre_secure_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="tyre_secure_value">
                {GetAddonValue("tyreSecure", quote?.addonDiscountPercentage1)}
              </td>
            )}
          </tr>
          <tr>
            {GetAddonValue(
              "returnToInvoice",
              quote?.addonDiscountPercentage1
            ) === "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A21"} name="return_to_invoice_NS_value" />
              </td>
            ) : GetAddonValue(
                "returnToInvoice",
                quote?.addonDiscountPercentage1
              ) === "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("returnToInvoice") ? (
                  <OptionalTooltip
                    id={"A22"}
                    name="return_to_invoice_NA_value"
                  />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="return_to_invoice_badge_NA_value"
                  />
                )}
              </td>
            ) : (
              <td className="addonValues" name="return_to_invoice_value">
                {GetAddonValue(
                  "returnToInvoice",
                  quote?.addonDiscountPercentage1
                )}
              </td>
            )}
          </tr>
          <tr
            style={{
              display: TypeReturn(type) === "bike" && "none",
            }}
          >
            {GetAddonValue("lopb", quote?.addonDiscountPercentage1) ===
            "N/S" ? (
              <td className="addonValues">
                <OptionalTooltip id={"A23"} name="lopb_NS_value" />
              </td>
            ) : GetAddonValue("lopb", quote?.addonDiscountPercentage1) ===
              "N/A" ? (
              <td className="addonValues">
                {quote?.applicableAddons?.includes("lopb") ? (
                  <OptionalTooltip id={"A24"} name="lopb_NA_value" />
                ) : (
                  <Badges title={"Not Available"} name="lopb_badge_NA_value" />
                )}
              </td>
            ) : (
              <td className="addonValues" name="lopb_value">
                {GetAddonValue("lopb", quote?.addonDiscountPercentage1)}
              </td>
            )}
          </tr>
        </>
      )}

      <tr
        style={
          TypeReturn(type) === "cv"
            ? {
                display: "none",
              }
            : {}
        }
      >
        {GetAddonValue(
          "emergencyMedicalExpenses",
          quote?.addonDiscountPercentage1
        ) === "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip
              id={"A27"}
              name="emergency_medical_expenses_NS_value"
            />
          </td>
        ) : GetAddonValue(
            "emergencyMedicalExpenses",
            quote?.addonDiscountPercentage1
          ) === "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("emergencyMedicalExpenses") ? (
              <OptionalTooltip
                id={"A28"}
                name="emergency_medical_expenses_NA_value"
              />
            ) : (
              <Badges
                title={"Not Available"}
                name="emergency_medical_expenses_badge_NA_value"
              />
            )}
          </td>
        ) : (
          <td className="addonValues" name="emergency_medical_expenses_value">
            {GetAddonValue(
              "emergencyMedicalExpenses",
              quote?.addonDiscountPercentage1
            )}
          </td>
        )}
      </tr>
      <tr>
        {GetAddonValue("windShield", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A29"} name="wind_shield_NS_value" />
          </td>
        ) : GetAddonValue("windShield", quote?.addonDiscountPercentage1) ===
          "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("windShield") ? (
              <OptionalTooltip id={"A29"} name="wind_shield_NA_value" />
            ) : (
              <Badges
                title={"Not Available"}
                name="wind_shield_badge_NA_value"
              />
            )}
          </td>
        ) : (
          <td className="addonValues" name="wind_shield_value">
            {GetAddonValue("windShield", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>
      <tr>
        {GetAddonValue("emiProtection", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A30"} name="emiProtection_NS_value" />
          </td>
        ) : GetAddonValue("emiProtection", quote?.addonDiscountPercentage1) ===
          "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("emiProtection") ? (
              <OptionalTooltip id={"A30"} name="emi_Protection_NA_value" />
            ) : (
              <Badges title={"Not Available"} name="emi_Protection_NA_value" />
            )}
          </td>
        ) : (
          <td className="addonValues" name="emi_Protection_value">
            {GetAddonValue("emiProtection", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>
      <tr>
        {GetAddonValue("additionalTowing", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A30"} name="additional_towing_NS_value" />
          </td>
        ) : GetAddonValue(
            "additionalTowing",
            quote?.addonDiscountPercentage1
          ) === "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("additionalTowing") ? (
              <OptionalTooltip id={"A30"} name="additional_towing_NA_value" />
            ) : (
              <Badges
                title={"Not Available"}
                name="additional_towing_NA_value"
              />
            )}
          </td>
        ) : (
          <td className="addonValues" name="additional_towing_value">
            {GetAddonValue("additionalTowing", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>
      <tr>
        {GetAddonValue("batteryProtect", quote?.addonDiscountPercentage1) ===
        "N/S" ? (
          <td className="addonValues">
            <OptionalTooltip id={"A30"} name="batteryProtect_NS_value" />
          </td>
        ) : GetAddonValue("batteryProtect", quote?.addonDiscountPercentage1) ===
          "N/A" ? (
          <td className="addonValues">
            {quote?.applicableAddons?.includes("batteryProtect") ? (
              <OptionalTooltip id={"A30"} name="battery_Protect_NA_value" />
            ) : (
              <Badges title={"Not Available"} name="battery_Protect_NA_value" />
            )}
          </td>
        ) : (
          <td className="addonValues" name="battery_Protect_value">
            {GetAddonValue("batteryProtect", quote?.addonDiscountPercentage1)}
          </td>
        )}
      </tr>
    </Table>
  );
};
