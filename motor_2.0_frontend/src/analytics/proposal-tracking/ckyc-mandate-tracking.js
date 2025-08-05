import { typeRename } from "analytics/typeCheck";

//init
const we_track = window?.webengage;

export const _ckycMandateTracking = (type, temp_data) => {
  if (we_track && temp_data) {
    let { manfactureName, modelName, versionName, fuelType } =
      temp_data?.quoteLog?.quoteDetails;
    let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
    let { companyName, companyLogo, idv } = temp_data?.selectedQuote;
    let { applicableNcb } = temp_data?.corporateVehiclesQuoteRequest;

    const applicableAddon = temp_data?.addons?.applicableAddons;
    let addonsData =
      applicableAddon && applicableAddon?.length
        ? applicableAddon?.map((item) => item?.name).join(", ")
        : "";
    we_track.track("Acknowledged KYC Verification", {
      "Motor Insurance Type": typeRename(type),
      "Vehicle Details": vehicle_details,
      "Insurer Name": companyName,
      "Insurer Image": [companyLogo],
      "Trace ID": Number(temp_data?.traceId),
      "Enquiry URL": window.location.href,
      "Add Ons": addonsData,
      "IDV Value": idv,
      "NCB %": Number(applicableNcb),
      "New Premium (incl. GST)": Number(
        temp_data?.quoteLog?.finalPremiumAmount
      ),
      "Old Premium (incl. GST)": Number(temp_data?.quoteLog?.odPremium),
    });
  }
};
