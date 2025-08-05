import { ownershipRename, typeRename } from "analytics/typeCheck";
import { dateConvert } from "utils";

//init
const webEngage = window?.webengage;

export const _planTracking = (planlist, temp_data, type, applicableAddons) => {
  let planDetails = (planList) => {
    return planList.map((item, index) => {
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
  //prettier-ignore
  let { previousPolicyType, vehicleOwnerType, previousPolicyExpiryDate,
          vehicleRegisterDate 
        } = temp_data?.corporateVehiclesQuoteRequest || {};
  let addons =
    applicableAddons && applicableAddons.length
      ? applicableAddons.map((item) => item).join(", ")
      : [];
  if (webEngage && planlist && planlist.length) {
    webEngage.track("Motor Insurance Plans Compared", {
      "Plan Details": planDetails(planlist),
      "Insurer Names": planDetails(planlist)
        .map((i) => i?.[`Insurer Name`])
        .join(","),
      "Vehicle Details": vehicle_details,
      "Motor Insurance Type": typeRename(type),
      "Previous Policy Type": previousPolicyType,
      "Trace ID": temp_data?.traceId,
      "Enquiry URL": window.location.href,
      Ownership: ownershipRename(vehicleOwnerType),
      "Previous Exp Date": dateConvert(previousPolicyExpiryDate),
      "Registered On": dateConvert(vehicleRegisterDate),
      "Quote Comparison URL": window.location.href,
      "Add Ons": addons,
    });
  }
};

export const _comparePDFTracking = (
  planlist,
  temp_data,
  type,
  applicableAddons
) => {
  let planDetails = (planList) => {
    return planList.map((item, index) => {
      return {
        IDV: item?.idv,
        [`Insurer Name`]: item?.companyName,
        [`Insurer Logo`]: item?.logo,
        Premium: item?.finalPremium1,
      };
    });
  };
  let { manfactureName, modelName, versionName, fuelType } =
    temp_data?.quoteLog?.quoteDetails || {};
  let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
  //prettier-ignore
  let { previousPolicyType, vehicleOwnerType, previousPolicyExpiryDate,
          vehicleRegisterDate 
        } = temp_data?.corporateVehiclesQuoteRequest || {};

  let addons =
    applicableAddons && applicableAddons.length
      ? applicableAddons.map((item) => item).join(", ")
      : [];

  if (webEngage && planlist && planlist.length) {
    webEngage.track("Motor Insurance Plans Comparison Downloaded", {
      "Plan Details": planDetails(planlist),
      "Vehicle Details": vehicle_details,
      "Motor Insurance Type": typeRename(type),
      "Previous Policy Type": previousPolicyType,
      "Trace ID": temp_data?.traceId,
      "Enquiry URL": window.location.href,
      Ownership: ownershipRename(vehicleOwnerType),
      "Previous Exp Date": dateConvert(previousPolicyExpiryDate),
      "Registered On": dateConvert(vehicleRegisterDate),
      "Quote Comparison URL": window.location.href,
      "Add Ons": addons,
    });
  }
};
