import _ from "lodash";
import { TypeReturn } from "modules/type";
import { currencyFormater } from "utils";

export const getPAValue = (
  paCoverCondition,
  addOnsAndOthers,
  newGroupedQuotesCompare,
  type,
  index
) => {
  const selectedAdditions = addOnsAndOthers?.selectedAdditions;
  const motorAdditionalPaidDriver =
    newGroupedQuotesCompare[index]?.motorAdditionalPaidDriver;
  const companyAlias = newGroupedQuotesCompare[index]?.companyAlias;
  const isSBI = companyAlias === "sbi";
  const tenureNotEmpty = !_.isEmpty(addOnsAndOthers?.isTenure);
  const typeReturn = TypeReturn(type);

  if (paCoverCondition) {
    return "";
  } else if (
    selectedAdditions?.includes("PA cover for additional paid driver")
  ) {
    if (motorAdditionalPaidDriver && Number(motorAdditionalPaidDriver) !== 0) {
      const multiplier =
        isSBI &&
        addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        ) &&
        tenureNotEmpty
          ? typeReturn === "bike"
            ? 5
            : 3
          : 1;
      return `₹ ${currencyFormater(motorAdditionalPaidDriver * multiplier)}`;
    } else {
      return "Not Available";
    }
  } else {
    return "Not Selected";
  }
};

export const getUnnamedPassengerPAValue = (
  addOnsAndOthers,
  newGroupedQuotesCompare,
  type,
  unnamedPassengerCondition,
  index
) => {
  const selectedAdditions = addOnsAndOthers?.selectedAdditions;
  const coverUnnamedPassengerValue =
    newGroupedQuotesCompare[index]?.coverUnnamedPassengerValue;
  const companyAlias = newGroupedQuotesCompare[index]?.companyAlias;
  const isSBI = companyAlias === "sbi";
  const tenureNotEmpty = !_.isEmpty(addOnsAndOthers?.isTenure);
  const typeReturn = TypeReturn(type);

  if (unnamedPassengerCondition) {
    return "";
  } else if (
    selectedAdditions?.includes("Unnamed Passenger PA Cover") ||
    newGroupedQuotesCompare[index]?.includedAdditional?.included?.includes(
      "coverUnnamedPassengerValue"
    )
  ) {
    if (Number(coverUnnamedPassengerValue) !== 0) {
      const multiplier =
        isSBI &&
        addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        ) &&
        tenureNotEmpty
          ? typeReturn === "bike"
            ? 5
            : 3
          : 1;
      return `₹ ${currencyFormater(coverUnnamedPassengerValue * multiplier)}`;
    } else {
      return "Not Available";
    }
  } else {
    return "Not Selected";
  }
};

export const getLLPaidDriverValue = (
  temp_data,
  addOnsAndOthers,
  newGroupedQuotesCompare,
  index
) => {
  if (!temp_data?.odOnly) {
    if (addOnsAndOthers?.selectedAdditions?.includes("LL paid driver")) {
      const defaultPaidDriver =
        newGroupedQuotesCompare[index]?.defaultPaidDriver;
      if (Number(defaultPaidDriver) !== 0) {
        return `₹ ${currencyFormater(defaultPaidDriver)}`;
      } else {
        return "Not Available";
      }
    } else {
      return "Not Selected";
    }
  } else {
    return "";
  }
};

export const getGeographicalExtensionValue = (
  addOnsAndOthers,
  newGroupedQuotesCompare,
  index
) => {
  if (
    import.meta.env.VITE_BROKER !== "OLA" &&
    addOnsAndOthers?.selectedAdditions?.includes("Geographical Extension")
  ) {
    const geogExtensionODPremium =
      newGroupedQuotesCompare[index]?.geogExtensionODPremium;
    if (Number(geogExtensionODPremium) !== 0) {
      return `₹ ${currencyFormater(geogExtensionODPremium)}`;
    } else {
      return "Not Available";
    }
  } else {
    return "Not Selected";
  }
};

export const getNFPPValue = (
  temp_data,
  addOnsAndOthers,
  newGroupedQuotesCompare,
  index
) => {
  if (
    temp_data.journeyCategory === "GCV" &&
    addOnsAndOthers?.selectedAdditions?.includes("NFPP Cover")
  ) {
    const nfppPremium =
      newGroupedQuotesCompare[index]?.nfpp;
    if (Number(nfppPremium) !== 0) {
      return `₹ ${currencyFormater(nfppPremium)}`;
    } else {
      return "Not Available";
    }
  } else {
    return "Not Selected";
  }
};

export const getDriverValue = (
  LLAndPaPaidDriverCondition,
  addOnsAndOthers,
  newGroupedQuotesCompare,
  index,
  key
) => {
  const value = newGroupedQuotesCompare[index]?.[key];
  return LLAndPaPaidDriverCondition &&
    addOnsAndOthers?.selectedAdditions?.includes(
      key === "defaultPaidDriver"
        ? "LL paid driver/conductor/cleaner"
        : "PA paid driver/conductor/cleaner"
    )
    ? Number(value) !== 0
      ? `₹ ${currencyFormater(value)}`
      : "Not Available"
    : "Not Selected";
};

export const getGeographicalValue = (
  addOnsAndOthers,
  newGroupedQuotesCompare,
  index
) => {
  const geogExtensionODPremium =
    newGroupedQuotesCompare[index]?.geogExtensionODPremium;
  return import.meta.env.VITE_BROKER !== "OLA" &&
    addOnsAndOthers?.selectedAdditions?.includes("Geographical Extension")
    ? Number(geogExtensionODPremium) !== 0
      ? `₹ ${currencyFormater(geogExtensionODPremium)}`
      : "Not Available"
    : "Not Selected";
};
