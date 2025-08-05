import { isEmpty } from "lodash";

export const NomineeMandatory = (companyAlias) => {
  return ["royal_sundaram", "edelweiss", "kotak", "universal_sompo","shriram"].includes(companyAlias);
};

export const shortHandAddonAndAll = (addon) => {
  switch (addon) {
    case "roadSideAssistance":
      return "Road Side Assistance";
    case "roadSideAssistance2":
      return "Road Side Assistance (₹ 49)";
    case "zeroDepreciation":
      return "Zero Depreciation";
    case "imt23":
      return "IMT - 23";
    case "keyReplace":
      return "Key Replacement";
    case "engineProtector":
      return "Engine Protector";
    case "ncbProtection":
      return "NCB Protection";
    case "consumables":
      return "Consumable";
    case "tyreSecure":
      return "Tyre Secure";
    case "returnToInvoice":
      return "Return To Invoice";
    case "lopb":
      return "Loss of Personal Belongings";
    case "emergencyMedicalExpenses":
      return "Emergency Medical Expenses";
    case "windShield":
      return "Wind Shield";
    case "emiProtection":
      return "EMI Protection";
    case "additionalTowing":
      return "Additional Towing";
    case "batteryProtect":
      return "Battery Protect";  
    case "electricleKit":
      return "Electrical Accessories";
    case "nonElectricleKit":
      return "Non-Electrical Accessories";
    case "externalBiKit":
      return "External Bi-Fuel Kit CNG/LPG";
    default:
      return "";
  }
};

//ncb
export const getNewNcb = (ncb) => {
  switch (ncb * 1) {
    case 0:
      return 20;
    case 20:
      return 25;
    case 25:
      return 35;
    case 35:
      return 45;
    case 45:
      return 50;
    case 50:
      return 50;
    default:
      return 20;
  }
};

//ICS with redirection for CKYC
export const redirection_ckyc = [
  "godigit",
  "reliance",
  "hdfc_ergo",
  "cholla_mandalam",
  "royal_sundaram",
  "universal_sompo",
  "liberty_videocon",
  "future_generali",
  "edelweiss",
];
//ICS with ovd for CKYC
export const ovd_ckyc = ["icici_lombard", "bajaj_allianz", "iffco_tokio"];

//AML Brokers
export const amlBrokers = (IC) => {
  if (IC === "shriram") {
    return ["OLA", "SPA", "HEROCARE", "PAYTM", "KMD", "BAJAJ", "KAROINSURE","VCARE","WOMINGO","INSTANTBEEMA"];
  }
  if (IC === "royal_sundaram") {
    return ["HEROCARE"];
  } else {
    return [];
  }
};

/**
 * Disables the Customer Identification Number (CIN) based on the IC and organization type.
 * @param {string} IC - The IC value to check against.
 * @param {string} organizationType - The organization type to check against.
 * @returns {boolean} Returns true if CIN should be disabled, false otherwise.
 */
export const _disableCIN = (IC, organizationType, isPOA) => {
  const excludedOrganizations = ["2", "9", "11", "18", "26", "C1", "68", "88"];
  const excludedPOAOrganisations = ["29", "30"];

  if (IC === "sbi" && organizationType) {
    let exclusionCheck = [];
    if (!isPOA) {
      exclusionCheck = excludedOrganizations.filter((item) => {
        return item.toString() === organizationType.toString();
      });
    } else {
      exclusionCheck = excludedPOAOrganisations.filter((item) => {
        return item.toString() !== organizationType.toString();
      });
    }

    return !isEmpty(exclusionCheck);
  } else {
    return false;
  }
};

export const panMandatoryIC = [
  "edelweiss",
  "reliance",
  "royal_sundaram",
  "united_india",
  "oriental",
  "magma",
  "tata_aig",
  "shriram",
  "universal_sompo",
];
