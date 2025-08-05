import { PushEventToCt } from "../clevertap";
import { diffInYearsAndMonths } from "utils";

export const _ckycTracking = (temp_data) => {
  // if (window?.PushEventToCt) {
  let dataObj = {
    Vehicle_RegNo:
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo,
    Insurance_Company: temp_data?.quoteLog?.icAlias,
    Insurance_Product: temp_data?.subProduct?.productSubTypeName,
    Vehicle_Type: temp_data?.subProduct?.productSubTypeName,
    Vehicle_Make: temp_data?.quoteLog?.quoteDetails?.manfactureName,
    Vehicle_Model: temp_data?.quoteLog?.quoteDetails?.modelName,
    Vehicle_Variant: temp_data?.quoteLog?.quoteDetails?.versionName,
    Policy_Type: temp_data?.corporateVehiclesQuoteRequest?.businessType,
    Policy_Sub_Type: temp_data?.corporateVehiclesQuoteRequest?.policyType,
    URL: window.location.href,
    Stage: "KYC",
    SumInsured: temp_data?.selectedQuote?.idv,
    TraceID: temp_data?.journeyId,
    ...(temp_data?.userProposal?.policyStartDate && {
      Policy_Effective_Date: temp_data?.userProposal?.policyStartDate,
    }),
    Premium_Amount: temp_data?.quoteLog?.finalPremiumAmount,
    Add1: temp_data?.userProposal?.addressLine1,
    Add2: temp_data?.userProposal?.addressLine1,
    Add3: temp_data?.userProposal?.addressLine3,
    state_name: temp_data?.userProposal?.state,
    city_name: temp_data?.userProposal?.city,
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
    ["Name"]: `${temp_data?.firstName}${" "}${
      temp_data?.lastName ? `${temp_data?.lastName}` : ``
    }`,
    ["Ph Number"]: temp_data?.mobileNo,
    ["Email ID"]: temp_data?.emailId,
  };
  PushEventToCt("HI_Portal_KYC Completed_Motor", dataObj, temp_data);
  // }
};
