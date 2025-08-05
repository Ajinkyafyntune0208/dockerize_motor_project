import { subMonths } from "date-fns";
import { TypeReturn } from "modules/type";
import moment from "moment";
import _ from "lodash";
import { currencyFormater } from "utils";

export const _getPolicyTypeData = (quote, temp_data, type) => {
  return `Policy Type : ${
    quote?.policyType === "Short Term"
      ? `${
          quote.premiumTypeCode === "short_term_3" ||
          quote.premiumTypeCode === "short_term_3_breakin"
            ? "3 Months"
            : "6 Months"
        }(Comprehensive)`
      : quote?.policyType === "Comprehensive" &&
        temp_data?.newCar &&
        TypeReturn(type) !== "cv"
      ? `Bundled(1 yr OD + ${TypeReturn(type) === "car" ? 3 : 5} yr TP)`
      : quote?.policyType
  }`;
};

export const _getVehicleDetails = (quote, temp_data) => {
  return `${quote?.mmvDetail?.manfName}-${quote?.mmvDetail?.modelName}-${
    quote?.mmvDetail?.versionName
  }-	${
    quote?.mmvDetail?.fuelType === "ELECTRIC" ||
    quote?.mmvDetail?.fuelType === "Electric"
      ? `${quote?.mmvDetail?.kw || " "}kW`
      : temp_data?.journeyCategory === "GCV"
      ? `${quote?.mmvDetail?.grossVehicleWeight || " "} ${"GVW"}`
      : `${quote?.mmvDetail?.cubicCapacity || " "}CC`
  } `;
};

export const _getPreviousPolicyExpiry = (temp_data, tempData) => {
  return `Previous Policy Expiry : ${
    temp_data?.newCar
      ? "N/A"
      : tempData?.policyType === "Not sure"
      ? "Not available"
      : temp_data?.breakIn
      ? temp_data?.expiry === "New" ||
        moment(subMonths(new Date(Date.now()), 9)).format("DD-MM-YYYY") ===
          temp_data?.expiry
        ? ""
        : temp_data?.expiry
      : temp_data?.expiry
  }`;
};

export const _getBusinessType = (quote) => {
  return `Business Type : ${
    quote?.isRenewal === "Y"
      ? "Renewal"
      : quote?.businessType &&
        quote?.businessType.split(" ").map(_.capitalize).join(" ")
  }`;
};

export const _loadingAmount = (quote) => {
  return quote?.showLoadingAmount &&
    (Number(quote?.totalLoadingAmount) > 0 ||
      Number(quote?.underwritingLoadingAmount))
    ? `₹ ${currencyFormater(
        Number(quote?.totalLoadingAmount) ||
          Number(quote?.underwritingLoadingAmount)
      )}`
    : "0";
};

export const _totalAmount = (totalAmountProps) => {
  // prettier-ignore
  const { totalPremiumA, totalAddon, totalPremiumC, quote, extraLoading, totalPremiumB,
     totalPremium, gst, finalPremium } = totalAmountProps;
  return {
    "Total OD Payable (A + D - C)": `₹ ${currencyFormater(
      quote?.totalOdPayable ||
        (totalPremiumA * 1 || 0) +
          (totalAddon * 1 || 0) -
          ((totalPremiumC * 1 || 0) - (quote?.tppdDiscount * 1 || 0)) +
          (extraLoading * 1 || 0)
    )}`,
    "Total TP Payable (B)": `₹ ${currencyFormater(
      totalPremiumB - (quote?.tppdDiscount * 1 || 0)
    )}`,
    "Net Premium": `₹ ${currencyFormater(totalPremium)}`,
    GST: `₹ ${currencyFormater(gst)}`,
    "Gross Premium (incl. GST)": `₹ ${currencyFormater(finalPremium)}`,
  };
};
