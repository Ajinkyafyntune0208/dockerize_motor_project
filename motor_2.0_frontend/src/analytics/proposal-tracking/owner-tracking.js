import { typeRename } from "analytics/typeCheck";
import { dateConvert } from "utils";

//init
const we_track = window?.webengage;

export const _ownerTracking = (type, temp_data, enquiry_id, data) => {
  if (we_track && temp_data) {
    let { manfactureName, modelName, versionName, fuelType } =
      temp_data?.quoteLog?.quoteDetails;
    let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
    let {
      companyName,
      companyLogo,
      idv,
      policyType,
      premiumTypeCode,
      finalPayableAmount,
    } = temp_data?.selectedQuote;

    const { firstName, lastName, dob, genderName, occupationName, pincode, state, city, maritalStatus } = data || {}

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
    we_track.track("Owner Details Submitted", {
      "Owner First Name": firstName,
      "Owner Last Name": lastName,
      "Owner Birth Date": dateConvert(dob),
      "Owner Gender": genderName,
      "Owner Occupation Type": occupationName,
      "Owner Marital Status": maritalStatus,
      "Owner Pincode": pincode *1,
      "Owner State": state,
      "Owner City": city,
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
    });
  }
};
