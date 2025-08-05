import {
  ownershipRename,
  prevPolicyType,
  typeRename,
} from "analytics/typeCheck";
import { dateConvert } from "utils";
//init
const webEngage = window?.webengage;

export const _planTracking = (planlist, temp_data, type, applicableAddons) => {
  let planDetails = (planList) => {
    return planList
      .filter((item) => item?.idv > 0)
      .map((item, index) => {
        return {
          IDV: item?.idv,
          [`Insurer Name`]: item?.companyName,
          [`Insurer Logo`]: item?.companyLogo,
          Premium: item?.finalPayableAmount,
        };
      });
  };

  let { manfactureName, modelName, versionName, fuelType } =
    temp_data?.quoteLog?.quoteDetails || {};
  let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
  // prettier-ignore
  let { applicableNcb, previousPolicyType, vehicleRegistrationNo, vehicleOwnerType,
     vehicleRegisterDate } = temp_data?.corporateVehiclesQuoteRequest || {};

  let addons =
    applicableAddons && applicableAddons.length
      ? applicableAddons.map((item) => item).join(", ")
      : "";

  const filteredPlanlist = planlist.filter((item) => item?.idv > 0);

  if (webEngage && planlist) {
    webEngage.track("Motor Insurance Plans Found", {
      "Plan Details": planDetails(planlist),
      "Total Plans Found": filteredPlanlist.length,
      "Vehicle Details": vehicle_details,
      IDV: Number(temp_data?.vehicleIdv),
      "Motor Insurance Type": typeRename(type),
      "New NCB %": Number(applicableNcb),
      "Previous Policy Type": prevPolicyType(
        temp_data?.policyType || previousPolicyType
      ),
      "Trace ID": Number(temp_data?.traceId),
      "Enquiry URL": window.location.href,
      ...(vehicleRegistrationNo && {
        "Registration Number": vehicleRegistrationNo,
      }),
      Ownership: ownershipRename(vehicleOwnerType),
      "Previous Exp Date": dateConvert(temp_data?.expiry),
      "Registered On": dateConvert(vehicleRegisterDate),
      "Add Ons": addons,
    });
  }
};

export const _premiumTracking = (quote, temp_data, applicableAddons, type) => {
  let { manfactureName, modelName, versionName, fuelType } =
    temp_data?.quoteLog?.quoteDetails || {};
  let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
  //prettier-ignore
  let { previousPolicyType, vehicleRegisterDate } = temp_data?.corporateVehiclesQuoteRequest || {}
  //mapping addons
  let addons =
    applicableAddons && applicableAddons.length
      ? applicableAddons
          .map((item) => (item.name ? item?.name : item))
          .join(", ")
      : "";

  if (webEngage && quote) {
    webEngage.track("Motor Insurance Policy Details Viewed", {
      "IDV Value": Number(quote?.idv),
      "Insurer Name": quote?.companyName,
      "Insurer Logo": [quote?.companyLogo],
      Premium: Number(quote?.finalPayableAmount),
      "Vehicle Details": vehicle_details,
      "Motor Insurance Type": typeRename(type),
      "Previous Policy Type": prevPolicyType(
        temp_data?.policyType || previousPolicyType
      ),
      "Trace ID": Number(temp_data?.traceId),
      "Enquiry URL": window.location.href,
      "Previous Exp Date": dateConvert(temp_data?.expiry),
      "Registered Date": dateConvert(vehicleRegisterDate),
      "Add Ons": addons,
    });
  }
};

export const _buyNowTracking = (quote, temp_data, applicableAddons, type) => {
  let { manfactureName, modelName, versionName, fuelType } =
    temp_data?.quoteLog?.quoteDetails || {};
  let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
  //prettier-ignore
  let { previousPolicyType, vehicleRegisterDate } = temp_data?.corporateVehiclesQuoteRequest || {}
  //mapping addons
  let addons =
    applicableAddons && applicableAddons.length
      ? applicableAddons
          .map((item) => (item?.name ? item?.name : item))
          .join(", ")
      : "";
  if (webEngage && quote) {
    webEngage.track("Motor Insurance Policy Buy Initiated", {
      "IDV Value": Number(quote?.idv),
      "Insurer Name": quote?.companyName,
      "Insurer Image": [quote?.companyLogo],
      Premium: quote?.finalPayableAmount,
      "Vehicle Details": vehicle_details,
      "Motor Insurance Type": typeRename(type),
      "Previous Policy Type": prevPolicyType(
        temp_data?.policyType || previousPolicyType
      ),
      "Trace ID": Number(temp_data?.traceId),
      "Enquiry URL": window.location.href,
      "Previous Policy Expiry": dateConvert(temp_data?.expiry),
      "Registered Date": dateConvert(vehicleRegisterDate),
      "Add Ons": addons,
    });
  }
};

export const _saveQuoteTracking = (
  quote,
  temp_data,
  applicableAddons,
  type,
  finalPremium,
  tempData
) => {
  let { manfactureName, modelName, versionName, fuelType } =
    temp_data?.quoteLog?.quoteDetails || {};
  let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
  //prettier-ignore
  let { previousPolicyType, vehicleRegisterDate, applicableNcb } =
   temp_data?.corporateVehiclesQuoteRequest || {}
  //mapping addons
  let addons =
    applicableAddons && applicableAddons.length
      ? applicableAddons
          .map((item) => (item?.name ? item?.name : item))
          .join(", ")
      : "";
  if (webEngage && quote) {
    webEngage.track("Motor Insurance Policy Buy Proceeded", {
      "IDV Value": Number(quote?.idv),
      "Insurer Name": quote?.companyName,
      "Insurer Image": [quote?.companyLogo],
      Premium: quote?.finalPayableAmount,
      "Vehicle Details": vehicle_details,
      "Motor Insurance Type": typeRename(type),
      "Previous Policy Type": prevPolicyType(
        temp_data?.policyType || previousPolicyType
      ),
      "Trace ID": Number(temp_data?.traceId),
      "Enquiry URL": window.location.href,
      "Previous Policy Expiry": dateConvert(temp_data?.expiry),
      "Registered Date": dateConvert(vehicleRegisterDate),
      "Add Ons": addons,
      "New NCB %": Number(applicableNcb),
      "New Premium (incl. GST)": Number(
        temp_data?.quoteLog?.finalPremiumAmount || finalPremium
      ),
      "Old Premium (incl. GST)": Number(
        temp_data?.quoteLog?.odPremium || tempData?.finalPremium
      ),
    });
  }
};
