import { typeRename } from "analytics/typeCheck";
import {
  dateConvert,
  diffInYearsAndMonths,
  isB2B,
  getEpochFromDate,
} from "utils";
import { PushEventToCt, CtUserLogin } from "../clevertap";

//init
const we_track = window?.webengage;

export const _successTracking = (type, temp_data, enquiry_id) => {
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

    let { prevPolicyExpiryDate, InsuranceCompanyName } =
      temp_data?.userProposal?.additonalData?.prepolicy || {};

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
    we_track.track("Motor Insurance Payment Completed", {
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
      "Previous Insurance Company": InsuranceCompanyName,
      "Date of expiry":
        prevPolicyExpiryDate && dateConvert(prevPolicyExpiryDate),
    });
  }
};

export const _paymentSuccessTracking = (temp_data, policy) => {
  const EventName = (vehicle, profile) => {
    switch (vehicle) {
      case "car":
        return `Hi_Portal_4W${profile ? "" : "_Policy_Created"}`;
      case "bike":
        return `Hi_Portal_2W${profile ? "" : "_Policy_Created"}`;
      case "pcv":
        return `Hi_Portal_PCV${profile ? "" : "_Policy_Created"}`;
      case "gcv":
        return `Hi_Portal_GCV${profile ? "" : "_Policy_Created"}`;
      default:
        break;
    }
  };

  let EventNameValue =
    temp_data?.productSubTypeCode &&
    EventName(temp_data?.subProduct?.parent?.productSubTypeCode.toLowerCase());
  let policyNumber = policy?.policyNumber;
  //Appending profile attributes
  temp_data?.userProposal?.mobileNumber &&
    CtUserLogin(
      temp_data?.userProposal?.mobileNumber,
      true,
      { policyNo: policyNumber, eventName: EventNameValue },
      temp_data
    );

  let profileAttr =
    temp_data?.productSubTypeCode &&
    EventName(
      temp_data?.subProduct?.parent?.productSubTypeCode.toLowerCase(),
      true
    );

  //Appending multiple policy Number keys
  window.clevertap &&
    profileAttr &&
    window.clevertap.addMultiValueForKey(profileAttr, policyNumber);

  // if (window?.PushEventToCt) {
  let dataObj = {
    Vehicle_RegNo:
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo,
    Insurance_Company: temp_data?.quoteLog?.icAlias,
    Seller_Type:
      isB2B(temp_data) && isB2B(temp_data)?.[0] === "P"
        ? "POS"
        : isB2B(temp_data)?.[0] === "E"
        ? "Employee"
        : "B2C",
    Policy_Source: "HeroInsurance",
    ...(policy?.pdfUrl && { pdf_url: policy?.pdfUrl }),
    SumInsured: temp_data?.selectedQuote?.idv,
    Insurance_Product: temp_data?.subProduct?.productSubTypeName,
    Vehicle_Type: temp_data?.subProduct?.productSubTypeName,
    Vehicle_Make: temp_data?.quoteLog?.quoteDetails?.manfactureName,
    Vehicle_Model: temp_data?.quoteLog?.quoteDetails?.modelName,
    Vehicle_Variant: temp_data?.quoteLog?.quoteDetails?.versionName,
    Policy_Type: temp_data?.corporateVehiclesQuoteRequest?.businessType,
    Policy_Sub_Type: temp_data?.corporateVehiclesQuoteRequest?.policyType,
    Proposal_No: temp_data?.userProposal?.proposalNo,
    Proposal_Date: temp_data?.userProposal?.proposalDate,
    Proposal_Date_EPOCH: getEpochFromDate(
      temp_data?.userProposal?.proposalDate
    ),
    URL: window.location.href,
    Stage: "Issued",
    TraceID: temp_data?.journeyId,
    ...(temp_data?.userProposal?.policyStartDate && {
      Policy_Effective_Date: temp_data?.userProposal?.policyStartDate,
      Policy_Effective_Date_EPOCH: getEpochFromDate(
        temp_data?.userProposal?.policyStartDate
      ),
    }),
    Premium_Amount: temp_data?.quoteLog?.finalPremiumAmount,
    Add1: temp_data?.userProposal?.addressLine1,
    Add2: temp_data?.userProposal?.addressLine1,
    Add3: temp_data?.userProposal?.addressLine3,
    state_name: temp_data?.userProposal?.state,
    city_name: temp_data?.userProposal?.city,
    Policy_No: policyNumber,
    PIN: temp_data?.userProposal?.pincode,
    Customer_Type:
      temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "I"
        ? "Individual"
        : "Company",
    ...(temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C" && {
      Company_Name: temp_data?.userProposal?.firstName,
    }),
    ...(temp_data?.userProposal?.policyStartDate && {
      ["Policy tenure"]: diffInYearsAndMonths(
        temp_data?.userProposal?.policyStartDate,
        temp_data?.userProposal?.policyEndDate
      ),
    }),
    ["Policy_Expiry_Date_TP"]: temp_data?.userProposal?.tpEndDate,
    ["Policy_Expiry_Date_TP_EPOCH"]: getEpochFromDate(
      temp_data?.userProposal?.tpEndDate
    ),
    ["Policy_Expiry_Date_OD"]: temp_data?.userProposal?.policyEndDate,
    ["Policy_Expiry_Date_OD_EPOCH"]: getEpochFromDate(
      temp_data?.userProposal?.policyEndDate
    ),
    ["Name"]: `${temp_data?.firstName}${" "}${
      temp_data?.lastName ? `${temp_data?.lastName}` : ``
    }`,
    ["Ph Number"]: temp_data?.mobileNo,
    ["Email ID"]: temp_data?.emailId,
  };

  PushEventToCt(EventNameValue, dataObj, temp_data);
  // }
};
