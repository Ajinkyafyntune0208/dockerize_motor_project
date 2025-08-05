import { typeRename } from "analytics/typeCheck";
import { dateConvert } from "utils";
//init
const we_track = window?.webengage;

export const _paymentTracking = (
  type,
  temp_data,
  enquiry_id,
  data,
  vehicle
) => {
  if (we_track && temp_data) {
    let { manfactureName, modelName, versionName, fuelType } =
      temp_data?.quoteLog?.quoteDetails || {};
    let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
    let {
      companyName,
      companyLogo,
      idv,
      policyType,
      premiumTypeCode,
      finalPayableAmount,
    } = temp_data?.selectedQuote;

    const { vehicleRegisterDate } = temp_data?.corporateVehiclesQuoteRequest || {};

    let {
      vehicaleRegistrationNumber,
      isVehicleFinance,
      vehicleManfYear,
      isCarRegistrationAddressSame,
    } = vehicle || {};
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
    we_track.track("Motor Insurance Payment Initiated", {
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
      "Previous Insurance Company": data?.InsuranceCompanyName,
      "Date of expiry": dateConvert(data?.prevPolicyExpiryDate),
      "Proposal URL": window.location.href,
      "Proposal Number": Number(temp_data?.userProposal?.proposalNo),
      "Registration Date": dateConvert(vehicleRegisterDate),
    });
  }
};

export const _shareTracking = (type, temp_data, enquiry_id, data, vehicle) => {
  if (we_track && temp_data) {
    let { manfactureName, modelName, versionName, fuelType } =
      temp_data?.quoteLog?.quoteDetails || {};
    let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
    let {
      companyName,
      companyLogo,
      idv,
      policyType,
      premiumTypeCode,
      finalPayableAmount,
    } = temp_data?.selectedQuote;

    const { vehicleRegisterDate } = temp_data?.corporateVehiclesQuoteRequest || {};

    let {
      vehicaleRegistrationNumber,
      isVehicleFinance,
      vehicleManfYear,
      isCarRegistrationAddressSame,
    } = vehicle || {};
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

    we_track.track("Motor Insurance Proposal Sending", {
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
      "Previous Insurance Company": data?.InsuranceCompanyName,
      "Date of expiry": data?.prevPolicyExpiryDate,
      "Proposal URL": window.location.href,
      "Proposal Number": Number(temp_data?.userProposal?.proposalNo),
      "Registration Date": dateConvert(vehicleRegisterDate),
    });
  }
};

export const _deliveryTracking = (type, temp_data, channel) => {
  if (we_track && temp_data) {
    let { manfactureName, modelName, versionName, fuelType } =
      temp_data?.quoteLog?.quoteDetails || {};
    let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
    let {
      companyName,
      companyLogo,
      idv,
      policyType,
      premiumTypeCode,
      finalPayableAmount,
    } = temp_data?.selectedQuote || {};

    let {
      vehicaleRegistrationNumber,
      isVehicleFinance,
      vehicleManfYear,
      isCarRegistrationAddressSame,
    } = temp_data?.userProposal?.additonalData?.vehicle || {}; 

    let { prevPolicyExpiryDate, previousInsuranceCompany } =
      temp_data?.userProposal?.additonalData?.prepolicy || {};

    const { vehicleRegisterDate } = temp_data?.corporateVehiclesQuoteRequest || {};

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

    we_track.track("Motor Insurance Proposal Sent ", {
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
      ...(previousInsuranceCompany && {
        "Previous Insurance Company": previousInsuranceCompany,
      }),
      "Date of expiry": dateConvert(prevPolicyExpiryDate),
      "Proposal URL": window.location.href,
      "Registration Date": dateConvert(vehicleRegisterDate),
      "Sent From": channel,
      "Proposal Number": Number(temp_data?.userProposal?.proposalNo),
    });
  }
};

export const _downloadTracking = (type, temp_data, enquiry_id) => {
  if (we_track && temp_data) {
    let { manfactureName, modelName, versionName, fuelType } =
      temp_data?.quoteLog?.quoteDetails || {};
    let vehicle_details = `${manfactureName} ${modelName} ${versionName} (${fuelType})`;
    let {
      companyName,
      companyLogo,
      idv,
      policyType,
      premiumTypeCode,
      finalPayableAmount,
    } = temp_data?.selectedQuote || {};

    let {
      vehicaleRegistrationNumber,
      isVehicleFinance,
      vehicleManfYear,
      isCarRegistrationAddressSame,
    } = temp_data?.userProposal?.additonalData?.vehicle || {};

    let { prevPolicyExpiryDate, previousInsuranceCompany } =
      temp_data?.userProposal?.additonalData?.prepolicy || {};

    const applicableAddon = temp_data?.addons?.applicableAddons;
    let addonsData =
      applicableAddon && applicableAddon?.length
        ? applicableAddon?.map((item) => item?.name).join(", ")
        : "";

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
    we_track.track("Motor Insurance Proposal Downloaded", {
      "Motor Insurance Type": typeRename(type),
      "Vehicle Details": vehicle_details,
      "Insurer Name": companyName,
      "Insurer Image": [companyLogo],
      "Trace ID": Number(temp_data?.traceId),
      "Proposal URL": window.location.href,
      "Add Ons": addonsData,
      "IDV Value": idv,
      "Total Premium Payable": finalPayableAmount,
      "Policy Type": getPlanDetails(),
      "Plan Type": policyType,
      "Vehicle Registration No.": vehicaleRegistrationNumber,
      "Manufacture Month & Year": vehicleManfYear,
      "Is your Vehicle Financed?": isVehicleFinance,
      "Address same as communication address": isCarRegistrationAddressSame,
      "Previous Insurance Company": previousInsuranceCompany,
      "Date of expiry": dateConvert(prevPolicyExpiryDate),
      "Proposal PDF URL": "",
      "Proposal Number": Number(temp_data?.userProposal?.proposalNo),
    });
  }
};
