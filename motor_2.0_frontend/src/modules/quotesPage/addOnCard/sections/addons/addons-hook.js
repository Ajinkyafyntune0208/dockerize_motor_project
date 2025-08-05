/* eslint-disable react-hooks/exhaustive-deps */
import { useEffect } from "react";
import {
  SaveAddonsData,
  SetaddonsAndOthers,
} from "modules/quotesPage/quote.slice";
import { isEmpty } from "lodash";

export const useSaveAddonData = (addonDataProps) => {
  // prettier-ignore
  const {
      upd, rsa2, rsa, zeroDep, imt23, keyReplace, engineProtector, ncbProtection, consumables, tyreSecure,
      returnToInvoice, lopb, emergencyMedicalExpenses, windshield, emiprotection, batteryprotect, userData, enquiry_id, dispatch,
      additionalTowing, addOnsAndOthers, gstToggle
    } = addonDataProps;
  useEffect(() => {
    if (!upd && !isEmpty(userData?.temp_data)) {
      var addons = [];
      var addons2 = [];
      var toggleGst = [];

      if (rsa2) {
        addons.push("roadSideAssistance2");
        addons2.push({ name: "Road Side Assistance 2" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (rsa) {
        addons.push("roadSideAssistance");
        addons2.push({ name: "Road Side Assistance" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (zeroDep) {
        let claimCovered =
          !isEmpty(addOnsAndOthers?.dbStructure?.addonData?.addons) &&
          addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
            (x) => x?.name === "Zero Depreciation"
          )?.[0]?.claimCovered;
        addons.push("zeroDepreciation");
        addons2.push({
          name: "Zero Depreciation",
          ...(claimCovered && { claimCovered: claimCovered }),
        });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (imt23) {
        addons.push("imt23");
        addons2.push({ name: "IMT - 23" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      //motor addons
      if (keyReplace) {
        addons.push("keyReplace");
        addons2.push({ name: "Key Replacement" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (engineProtector) {
        addons.push("engineProtector");
        addons2.push({ name: "Engine Protector" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (ncbProtection) {
        addons.push("ncbProtection");
        addons2.push({ name: "NCB Protection" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (consumables) {
        addons.push("consumables");
        addons2.push({ name: "Consumable" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (tyreSecure) {
        addons.push("tyreSecure");
        addons2.push({ name: "Tyre Secure" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (returnToInvoice) {
        addons.push("returnToInvoice");
        addons2.push({ name: "Return To Invoice" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (lopb) {
        addons.push("lopb");
        addons2.push({ name: "Loss of Personal Belongings" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (emergencyMedicalExpenses) {
        addons.push("emergencyMedicalExpenses");
        addons2.push({ name: "Emergency Medical Expenses" });
      }
      if (windshield) {
        addons.push("windShield");
        addons2.push({ name: "Wind Shield" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (emiprotection) {
        addons.push("emiProtection");
        addons2.push({ name: "EMI Protection" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (batteryprotect) {
        addons.push("batteryProtect");
        addons2.push({ name: "Battery Protect" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (additionalTowing) {
        let sumInsured =
          !isEmpty(addOnsAndOthers?.dbStructure?.addonData?.addons) &&
          addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
            (x) => x?.name === "Additional Towing"
          )?.[0]?.sumInsured;
        addons.push("additionalTowing");
        addons2.push({
          name: "Additional Towing",
          ...(sumInsured && { sumInsured: sumInsured }),
        });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      //gst toggle
      if (gstToggle) {
        toggleGst.push({ gstToggle: "Y" });
      } else {
        toggleGst.push({ gstToggle: "N" });
      }

      var data = {
        selectedAddons: addons.filter(Boolean),
      };
      var data1 = {
        enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
        addonData: { addons: addons2.filter(Boolean) },
        frontend_tags: toggleGst.filter(Boolean),
      };

      dispatch(
        SetaddonsAndOthers({
          ...data,
          dbStructure: { addonData: { addons: addons2.filter(Boolean) } },
        })
      );

      dispatch(SaveAddonsData(data1));
    }
  }, [
    rsa,
    rsa2,
    zeroDep,
    upd,
    imt23,
    keyReplace,
    engineProtector,
    ncbProtection,
    consumables,
    tyreSecure,
    returnToInvoice,
    lopb,
    emergencyMedicalExpenses,
    windshield,
    emiprotection,
    batteryprotect,
    additionalTowing,
    gstToggle,
  ]);
};
