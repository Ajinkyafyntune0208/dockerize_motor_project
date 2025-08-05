import { currencyFormater } from "utils";
import { _addonValue } from "modules/quotesPage/quoteCard/card-logic";

export const GetAddonValueNoBadge = (
  addonName,
  addonDiscountPercentage,
  quote,
  addOnsAndOthers
) => {
  let inbuilt = quote?.addOnsData?.inBuilt
    ? Object.keys(quote?.addOnsData?.inBuilt)
    : [];
  let additional = quote?.addOnsData?.additional
    ? Object.keys(quote?.addOnsData?.additional)
    : [];
  let selectedAddons = addOnsAndOthers?.selectedAddons;
  if (inbuilt?.includes(addonName)) {
    return `${
      Number(quote?.addOnsData?.inBuilt[addonName]) !== 0
        ? `₹ ${currencyFormater(
            _addonValue(
              quote,
              addonName,
              addonDiscountPercentage,
              "inbuilt",
              "exclude-gst"
            )
          )}`
        : addonName === "roadSideAssistance" &&
          quote?.company_alias === "reliance"
        ? "-"
        : "Included"
    }`;
  } else if (
    additional?.includes(addonName) &&
    selectedAddons?.includes(addonName) &&
    Number(quote?.addOnsData?.additional[addonName]) !== 0 &&
    typeof quote?.addOnsData?.additional[addonName] === "number"
  ) {
    return `₹ ${currencyFormater(
      _addonValue(
        quote,
        addonName,
        addonDiscountPercentage,
        false,
        "exclude-gst"
      )
    )}`;
  } else if (
    additional?.includes(addonName) &&
    Number(quote?.addOnsData?.additional[addonName]) === 0
  ) {
    return quote?.applicableAddons?.includes(addonName)
      ? "Optional"
      : "Not Available";
  } else if (
    !additional?.includes(addonName) &&
    selectedAddons?.includes(addonName)
  ) {
    return quote?.applicableAddons?.includes(addonName)
      ? "Optional"
      : "Not Available";
  } else if (Number(quote?.addOnsData?.additional[addonName]) === 0) {
    return quote?.applicableAddons?.includes(addonName)
      ? "Optional"
      : "Not Available";
  } else if (
    additional?.includes(addonName) &&
    !selectedAddons?.includes(addonName)
  ) {
    return "Optional";
  } else {
    return quote?.applicableAddons?.includes(addonName)
      ? "Optional"
      : "Not Available";
  }
};

export const getBrokerWebsite = () => {
  switch (import.meta.env?.VITE_BROKER) {
    case "OLA":
      return "https://ola.fynity.in/";
    case "FYNTUNE":
      return "https://cardemo-re8h1ssyiyqenhj8gzym7hqmo7n67jdq.fynity.in/";
    default:
      break;
  }
};
