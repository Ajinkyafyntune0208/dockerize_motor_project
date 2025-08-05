import { TypeReturn } from "modules/type";
import { GetAddonValueNoBadge } from "../helper";
import { currencyFormater } from "utils";
import _ from "lodash";

export const getVehicleInsurerType = (
  newGroupedQuotesCompare,
  temp_data,
  type
) => {
  return `${
    newGroupedQuotesCompare[0]?.policyType === "Comprehensive" &&
    temp_data?.newCar &&
    TypeReturn(type) !== "cv"
      ? "Bundled Policy"
      : newGroupedQuotesCompare[0]?.policyType
  } / ${
    TypeReturn(type) === "cv"
      ? newGroupedQuotesCompare[0]?.premiumTypeCode === "short_term_3" ||
        newGroupedQuotesCompare[0]?.premiumTypeCode === "short_term_3_breakin"
        ? "3 Months"
        : newGroupedQuotesCompare[0]?.premiumTypeCode === "short_term_6" ||
          newGroupedQuotesCompare[0]?.premiumTypeCode === "short_term_6_breakin"
        ? "6 Months"
        : newGroupedQuotesCompare[0]?.policyType === "Comprehensive"
        ? `(1yr OD + 1yr TP)`
        : "Annual"
      : newGroupedQuotesCompare[0]?.policyType === "Comprehensive"
      ? `(1yr OD + ${
          temp_data?.newCar ? (TypeReturn(type) === "car" ? "3" : "5") : "1"
        }yr TP)`
      : temp_data?.newCar
      ? `${TypeReturn(type) === "car" ? "3" : "5"} years`
      : "Annual"
  }`;
};

export const getVehicleModel = (newGroupedQuotesCompare) => {
  return `${newGroupedQuotesCompare[0]?.mmvDetail?.manfName?.replace(
    /&/,
    "and"
  )}-${newGroupedQuotesCompare[0]?.mmvDetail?.modelName?.replace(
    /&/,
    "and"
  )}-${newGroupedQuotesCompare[0]?.mmvDetail?.versionName
    ?.toString()
    ?.replace(/&/, "and")}-${
    newGroupedQuotesCompare[0]?.mmvDetail?.cubicCapacity
  }cc `;
};

// addonRowCreator.js
export const calculateCpaValue = (
  addOnsAndOthers,
  newGroupedQuotesCompare,
  index
) => {
  const cpaValue = addOnsAndOthers?.selectedCpa?.includes(
    "Compulsory Personal Accident"
  )
    ? `₹ ${currencyFormater(
        parseInt(
          !_.isEmpty(addOnsAndOthers?.isTenure)
            ? newGroupedQuotesCompare[index]?.multiYearCpa
            : newGroupedQuotesCompare[index]?.compulsoryPaOwnDriver
        )
      )}`
    : newGroupedQuotesCompare[index]?.compulsoryPaOwnDriver ||
      newGroupedQuotesCompare[index]?.multiYearCpa
    ? "Optional"
    : "Not Available";

  return cpaValue;
};

// addonRowCreator.js
export const calculateCpaValueForCv = (
  addOnsAndOthers,
  newGroupedQuotesCompare,
  index,
  temp_data
) => {
  const cpaValue = temp_data?.odOnly
    ? ""
    : addOnsAndOthers?.selectedCpa?.includes("Compulsory Personal Accident")
    ? `₹ ${currencyFormater(
        parseInt(newGroupedQuotesCompare[index]?.compulsoryPaOwnDriver)
      )}`
    : newGroupedQuotesCompare[index]?.compulsoryPaOwnDriver ||
      newGroupedQuotesCompare[index]?.multiYearCpa
    ? "Optional"
    : "Not Available";

  return cpaValue;
};

// addonRowCreator.js
export const createAddonRow = (
  addonNames,
  newGroupedQuotesCompare,
  index,
  addOnsAndOthers
) => {
  const addonRow = [];
  for (const addonName of addonNames) {
    addonRow.push(
      GetAddonValueNoBadge(
        addonName,
        newGroupedQuotesCompare[index]?.addonDiscountPercentage1,
        newGroupedQuotesCompare[index],
        addOnsAndOthers
      )
    );
  }
  return addonRow;
};
