import React from "react";
import { TypeReturn } from "modules/type";
import Style from "../style";
import _ from "lodash";
import Checkbox from "./Checkbox";

const Addons = ({
  temp_data,
  quote,
  type,
  addOnsAndOthers,
  claimList_gdd,
  claimList,
  zdlp,
  zdlp_gdd,
  setZdlp,
  setZdlp_gdd,
}) => {
  return (
    <Style.AddonInfo>
      <div className="addonHead">
        {" "}
        {temp_data?.tab === "tab2"
          ? temp_data?.ownerTypeId === 2
            ? ""
            : "CPA"
          : temp_data?.tab !== "tab2" &&
            quote?.applicableAddons?.length &&
            (quote?.compulsoryPaOwnDriver > 0 || quote?.multiYearCpa > 0) &&
            temp_data?.ownerTypeId !== 2
          ? quote?.applicableAddons.includes("imt23")
            ? "CPA, Addons & Covers"
            : "CPA & Addons"
          : quote?.applicableAddons?.length > 0
          ? quote?.applicableAddons.includes("imt23")
            ? "Addons & Covers"
            : "Addons"
          : (quote?.compulsoryPaOwnDriver > 0 || quote?.multiYearCpa > 0) &&
            temp_data?.ownerTypeId !== 2 &&
            "CPA"}
      </div>
      {temp_data?.ownerTypeId === 1 && !temp_data?.odOnly && (
        <>
          {
            <Checkbox
              id={"Compulsory Personal Accident"}
              value={"Compulsory Personal Accident"}
              defaultChecked={
                addOnsAndOthers?.selectedCpa?.includes(
                  "Compulsory Personal Accident"
                ) && _.isEmpty(addOnsAndOthers?.isTenure)
              }
              checked={
                addOnsAndOthers?.selectedCpa?.includes(
                  "Compulsory Personal Accident"
                ) && _.isEmpty(addOnsAndOthers?.isTenure)
              }
              data_tip={
                "<h3 >Compulsory Personal Accident</h3> <div>Compulsory Personal Accident cover protects you against partial, total disability, or death caused due to an accident. As per the IRDAI notice. Personal Accident (PA) Cover is mandatory if the car is owned by an individual.</div>"
              }
              data_for={"cpa1__Tooltipvol"}
              lable={"Compulsory Personal Accident"}
            />
          }
          {TypeReturn(type) !== "cv" && temp_data?.newCar && (
            <Checkbox
              id={"Compulsory Personal Accident 1"}
              value={"Compulsory Personal Accident 1"}
              defaultChecked={!_.isEmpty(addOnsAndOthers?.isTenure)}
              checked={!_.isEmpty(addOnsAndOthers?.isTenure)}
              data_tip={
                "<h3 >Compulsory Personal Accident</h3> <div>Compulsory Personal Accident cover protects you against partial, total disability, or death caused due to an accident. As per the IRDAI notice. Personal Accident (PA) Cover is mandatory if the car is owned by an individual.</div>"
              }
              data_for={"cpa1__Tooltipvol"}
              lable={`Compulsory Personal Accident ${
                TypeReturn(type) === "car" ? "(3 Years)" : "(5 Years)"
              }`}
            />
          )}
        </>
      )}
      {temp_data?.tab !== "tab2" && (
        <>
          {quote?.applicableAddons?.includes("zeroDepreciation") && (
            <Checkbox
              id={"Zero Depreciation"}
              defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                "zeroDepreciation"
              )}
              checked={addOnsAndOthers?.selectedAddons?.includes(
                "zeroDepreciation"
              )}
              value={addOnsAndOthers?.selectedAddons?.includes(
                "zeroDepreciation"
              )}
              data_tip={
                "<h3 >Zero Depreciation</h3> <div>Also called Nil Depreciation cover or Bumper-to-Bumper cover. An add-on which gives you complete cover on any body parts of the car excluding tyres and batteries. Insurer will pay entire cost of body parts, ignoring the year-on-year depreciation in value of these parts.</div>"
              }
              data_for={"zero__Tooltipvol"}
              lable={"Zero Depreciation"}
            />
          )}

          {/* {quote?.companyAlias === "godigit" &&
            addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation") &&
            (["ONE", "TWO", "UNLIMITED"])
              ?.sort()
              ?.map((each) => (
                <Checkbox
                  style={{ marginLeft: "20px" }}
                  id={
                    quote?.gdd !== "Y"
                      ? `Zerodep_${each}`
                      : `Zerodep_gdd_${each}`
                  }
                  defaultChecked={
                    quote?.gdd !== "Y" ? zdlp === each : zdlp_gdd === each
                  }
                  checked={
                    quote?.gdd !== "Y" ? zdlp === each : zdlp_gdd === each
                  }
                  value={quote?.gdd !== "Y" ? zdlp === each : zdlp_gdd === each}
                  data_for={
                    quote?.gdd !== "Y"
                      ? `Zerodep_${each}`
                      : `Zerodep_gdd_${each}`
                  }
                  onClick={() =>
                    quote?.gdd !== "Y" ? setZdlp(each) : setZdlp_gdd(each)
                  }
                  lable={
                    each === "ONE"
                      ? "One"
                      : each === "TWO"
                      ? "Two"
                      : each === "UNLIMITED"
                      ? "Unlimited"
                      : each
                  }
                />
              ))} */}

          {quote?.applicableAddons?.includes("roadSideAssistance") && (
            <Checkbox
              id={"Road Side Assistance"}
              defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                "roadSideAssistance"
              )}
              checked={addOnsAndOthers?.selectedAddons?.includes(
                "roadSideAssistance"
              )}
              value={addOnsAndOthers?.selectedAddons?.includes(
                "roadSideAssistance"
              )}
              data_tip={
                "<h3 >Road Side Assistance</h3> <div>Roadside Assistance Coverage means a professional technician comes to your rescue when your car breaks down in the middle of the journey leaving you stranded.</div>"
              }
              data_for={"rsa__Tooltipvol"}
              lable={"Road Side Assistance"}
            />
          )}
          {TypeReturn(type) === "cv" &&
            quote?.applicableAddons?.includes("imt23") && (
              <Checkbox
                id={"IMT - 23"}
                defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                  "imt23"
                )}
                checked={addOnsAndOthers?.selectedAddons?.includes("imt23")}
                value={addOnsAndOthers?.selectedAddons?.includes("imt23")}
                data_tip={
                  "<h3 >IMT - 23</h3> <div>COVER FOR LAMPS TYRES / TUBES MUDGUARDS BONNET /SIDE PARTS BUMPERS HEADLIGHTS AND PAINTWORK OF DAMAGED PORTION ONLY .</div>"
                }
                data_for={"imtTooltipvol"}
                lable={"IMT - 23"}
              />
            )}

          {temp_data?.journeyCategory !== "GCV" &&
            quote?.applicableAddons?.includes("consumables") && (
              <Checkbox
                id={"Consumable"}
                defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                  "consumables"
                )}
                checked={addOnsAndOthers?.selectedAddons?.includes(
                  "consumables"
                )}
                value={addOnsAndOthers?.selectedAddons?.includes("consumables")}
                data_tip={
                  "<h3 >Consumable</h3> <div>Consumable items of a car include nut and bolt, screw, washer, grease, lubricant, clips, A/C gas, bearings, distilled water, engine oil, oil filter, fuel filter, break oil and related parts</div>"
                }
                data_for={"consumableTooltipvol"}
                lable={"Consumable"}
              />
            )}
          {(TypeReturn(type) === "car" || TypeReturn(type) === "bike") && (
            <>
              {quote?.applicableAddons?.includes("keyReplace") && (
                <Checkbox
                  id={"Key Replacement"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "keyReplace"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "keyReplace"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "keyReplace"
                  )}
                  data_tip={
                    "<h3 >Key Replacement</h3> <div>An add-on which covers cost of car keys and lock replacement or locksmith charges incase of your car keys is stolen.</div>"
                  }
                  data_for={"keyTooltipvol"}
                  lable={"Key Replacement"}
                />
              )}

              {quote?.applicableAddons?.includes("engineProtector") && (
                <Checkbox
                  id={"Engine Protector"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "engineProtector"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "engineProtector"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "engineProtector"
                  )}
                  data_tip={
                    "<h3 >Engine Protector</h3> <div>Engine protection cover in car insurance provides coverage towards damages or losses to the insured vehicle’s engine. The add-on compensates you for the replacement or repair of your car’s engine or parts.</div>"
                  }
                  data_for={"engTooltipvol"}
                  lable={"Engine Protector"}
                />
              )}
              {quote?.applicableAddons?.includes("ncbProtection") && (
                <Checkbox
                  id={"NCB Protection"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "ncbProtection"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "ncbProtection"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "ncbProtection"
                  )}
                  data_tip={
                    "<h3 >NCB Protection</h3> <div>The NCB Protector protects Your Earned No claim Bonus, in the event of an Own Damage claim made for Partial Loss including claims for Windshield glass, Total Loss, and Theft of vehicle/ accessories. The No Claim Bonus will not get impacted for the first 2 claims preferred during the course of this policy per year.</div>"
                  }
                  data_for={"ncbProtTooltipvol"}
                  lable={"NCB Protection"}
                />
              )}

              {quote?.applicableAddons?.includes("tyreSecure") && (
                <Checkbox
                  id={"Tyre Secure"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "tyreSecure"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "tyreSecure"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "tyreSecure"
                  )}
                  data_tip={
                    "<h3 >Tyre Secure</h3> <div>The cost of damage to the insured vehicle due to an accident, riot, vandalism, and natural and man-made calamities. The repair cost of the tyre and tube without being damaged due to a claimable event is not covered by any basic insurance policy.</div>"
                  }
                  data_for={"tyreTooltipvol"}
                  lable={"Tyre Secure"}
                />
              )}
              {quote?.applicableAddons?.includes("returnToInvoice") && (
                <Checkbox
                  id={"Return To Invoice"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "returnToInvoice"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "returnToInvoice"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "returnToInvoice"
                  )}
                  data_tip={
                    "<h3 >Return To Invoice</h3> <div>Return to Invoice is an add-on option which covers the gap between the insured declared value and the invoice value of your car along with the registration and other applicable taxes.</div>"
                  }
                  data_for={"roiTooltipvol"}
                  lable={"Return To Invoice"}
                />
              )}
              {quote?.applicableAddons?.includes("lopb") && (
                <Checkbox
                  id={"Loss of Personal Belongings"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "lopb"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes("lopb")}
                  value={addOnsAndOthers?.selectedAddons?.includes("lopb")}
                  data_tip={
                    "<h3 >Loss of Personal Belongings</h3> <div>With this cover in place, your insurer will cover losses arising due to damage or theft of your personal Belongings from the insured car as per the terms and conditions of the policy.</div>"
                  }
                  data_for={"lopb__Tooltipvol"}
                  lable={"Loss of Personal Belongings"}
                />
              )}
              {quote?.applicableAddons?.includes(
                "emergencyMedicalExpenses"
              ) && (
                <Checkbox
                  id={"Emergency Medical Expenses"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "emergencyMedicalExpenses"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "emergencyMedicalExpenses"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "emergencyMedicalExpenses"
                  )}
                  data_tip={"<h3 >Emergency Medical Expenses</h3> <div></div>"}
                  data_for={"emeTooltipvol"}
                  lable={"Emergency Medical Expenses"}
                />
              )}
              {quote?.applicableAddons?.includes("windShield") && (
                <Checkbox
                  id={"Wind Shield"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "windShield"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "windShield"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "windShield"
                  )}
                  data_tip={"<h3 >Wind Shield</h3> <div></div>"}
                  data_for={"emeTooltipvol"}
                  lable={"Wind Shield"}
                />
              )}
              {quote?.applicableAddons?.includes("windShield") && (
                <Checkbox
                  id={"Wind Shield"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "windShield"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "windShield"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "windShield"
                  )}
                  data_tip={"<h3 >Wind Shield</h3> <div></div>"}
                  data_for={"emeTooltipvol"}
                  lable={"Wind Shield"}
                />
              )}
              {quote?.applicableAddons?.includes("emiProtection") && (
                <Checkbox
                  id={"EMI Protection"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "emiProtection"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "emiProtection"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "emiProtection"
                  )}
                  data_tip={"<h3 >EMI Protection</h3> <div></div>"}
                  data_for={"emiprotectionTooltipvol"}
                  lable={"EMI Protection"}
                />
              )}
              {quote?.applicableAddons?.includes("batteryProtect") && (
                <Checkbox
                  id={"Battery Protect"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "batteryProtect"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "batteryProtect"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "batteryProtect"
                  )}
                  data_tip={"<h3 >Battery Protect</h3> <div></div>"}
                  data_for={"batteryprotectTooltipvol"}
                  lable={"Battery Protect"}
                />
              )}
              {quote?.applicableAddons?.includes("additionalTowing") && (
                <Checkbox
                  id={"Additional Towing"}
                  defaultChecked={addOnsAndOthers?.selectedAddons?.includes(
                    "additionalTowing"
                  )}
                  checked={addOnsAndOthers?.selectedAddons?.includes(
                    "additionalTowing"
                  )}
                  value={addOnsAndOthers?.selectedAddons?.includes(
                    "additionalTowing"
                  )}
                  data_tip={"<h3 >Additional Towing</h3> <div></div>"}
                  data_for={"additionaltowingTooltipvol"}
                  lable={"Additional Towing"}
                />
              )}
            </>
          )}
        </>
      )}
    </Style.AddonInfo>
  );
};

export default Addons;
