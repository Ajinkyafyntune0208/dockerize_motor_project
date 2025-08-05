import { GetAddonValueNoBadge } from "modules/comparePage/CompareProductsList/helper";
import { getAddonName } from "modules/quotesPage/quoteUtil";
import { TypeReturn } from "modules/type";
import { camelToUnderscore, currencyFormater } from "utils";
import _ from "lodash";

export const generateAddonList = (addonListProps) => {
  // prettier-ignore
  const { type, totalApplicableAddonsMotor, addonDiscountPercentage, quote, 
    addOnsAndOthers, others, othersList, temp_data } = addonListProps;
  let addonList = {};
  const getAddonValue = (addonKey) => {
    const addonValue = GetAddonValueNoBadge(
      addonKey,
      addonDiscountPercentage,
      quote,
      addOnsAndOthers
    );
    if (addonValue === "N/S") {
      return "Not selected";
    } else if (addonValue === "N/A") {
      return "Not Available";
    } else {
      return addonValue;
    }
  };

  if (["car", "bike", "cv"].includes(TypeReturn(type))) {
    totalApplicableAddonsMotor.forEach((item) => {
      const addonValue = GetAddonValueNoBadge(
        item,
        addonDiscountPercentage,
        quote,
        addOnsAndOthers
      );
      if (addonValue !== "N/A" && addonValue !== "Not Available") {
        let keyName = getAddonName(item);

        if (
          keyName === "Zero Depreciation" &&
          quote?.companyAlias === "godigit" && type !== "bike"
        ) {
          keyName =`Zero Depreciation (${quote?.claimsCovered})`;
        }
        addonList[keyName] = addonValue === "N/S" ? "Not selected" : addonValue;
      }
    });

    _.without(others, "lLPaidDriver").forEach((item) => {
      addonList[
        `${
          camelToUnderscore(item)
            ?.replace(/_/g, " ")
            .split(" ")
            .map(_.capitalize)
            .join(" ") || ""
        }`
      ] =
        Number(currencyFormater(othersList[item])) === 0
          ? "Included"
          : `₹ ${currencyFormater(othersList[item])}`;
    });
  } else {
    if (temp_data?.journeyCategory === "GCV") {
      addonList = {
        "Zero Depreciation": getAddonValue("zeroDepreciation"),
        "IMT - 23": getAddonValue("imt23"),
      };
    } else {
      addonList = {
        "Zero Depreciation": getAddonValue("zeroDepreciation"),
        ...(quote?.company_alias === "reliance" &&
          TypeReturn(type) === "cv" && {
            "Road Side Assistance": getAddonValue("roadSideAssistance"),
          }),
      };
    }
  }
  return addonList;
};
