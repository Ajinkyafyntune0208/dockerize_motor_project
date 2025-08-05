import { typeRename } from "analytics/typeCheck";
import { dateConvert } from "utils";

//init
const we_track = window?.webengage;

export const _vehicleTracking = (type, temp_data, data) => {
  if (we_track && temp_data) {
    let {
      manfactureName,
      modelName,
      versionName,
      fuelType,
      vehicleRegisterDate,
    } = temp_data?.quoteLog?.quoteDetails;
    let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
    let {
      companyName,
      companyLogo,
      idv,
      policyType,
      premiumTypeCode,
      finalPayableAmount,
    } = temp_data?.selectedQuote;

    let {
      vehicaleRegistrationNumber,
      isVehicleFinance,
      vehicleManfYear,
      isCarRegistrationAddressSame,
    } = data;
    //Compute plan details
    const getPlanDetails = () => {
      return `${
        policyType === "Short Term"
          ? ` (${
              premiumTypeCode === "short_term_3" ||
              premiumTypeCode === "short_term_3_breakin"
                ? "3 Months"
                : "6 Months"
            }) - Comprehensive`
          : policyType === "Comprehensive" && temp_data?.newCar && type !== "cv"
          ? ` - 1 yr. OD + ${type === "car" ? 3 : 5} yr. TP`
          : temp_data?.newCar && type !== "cv"
          ? ` - ${type === "car" ? 3 : 5} years`
          : ` - Annual`
      }`;
    };

    const applicableAddon = temp_data?.addons?.applicableAddons;
    let addonsData =
      applicableAddon && applicableAddon?.length
        ? applicableAddon?.map((item) => item?.name).join(", ")
        : "";

    we_track.track("Vehicle Details Submitted", {
      "Motor Insurance Type": typeRename(type),
      "Vehicle Details": vehicle_details,
      "Insurer Name": companyName,
      "Insurer Image": [companyLogo],
      "Trace ID": Number(temp_data?.traceId),
      "Enquiry URL": window.location.href,
      "Add Ons": addonsData,
      "IDV Value": idv,
      "Total Premium Payable": finalPayableAmount,
      "Policy Type": getPlanDetails(),
      "Plan Type": policyType,
      "Vehicle Registration No.": vehicaleRegistrationNumber,
      "Manufacture Month & Year": vehicleManfYear,
      "Is your Vehicle Financed?": isVehicleFinance,
      "Address same as communication address": isCarRegistrationAddressSame,
      "Registration Date": dateConvert(vehicleRegisterDate),
    });
  }
};
