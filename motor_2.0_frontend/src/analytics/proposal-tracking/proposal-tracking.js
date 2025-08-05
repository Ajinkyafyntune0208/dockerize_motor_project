import { PushEventToCt, CtUserLogin } from "../clevertap";
import { diffInYearsAndMonths } from "utils";

export const _proposalPageTracking = (temp_data) => {
  //CT onUserLogin
  temp_data?.mobileNo &&
    CtUserLogin(temp_data?.mobileNo, false, false, temp_data);
  // if (window?.PushEventToCt) {
  let dataObj = {
    Vehicle_RegNo:
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo,
    SumInsured: temp_data?.selectedQuote?.idv,
    Insurance_Company: temp_data?.quoteLog?.icAlias,
    Insurance_Product: temp_data?.subProduct?.productSubTypeName,
    Vehicle_Type: temp_data?.subProduct?.productSubTypeName,
    Vehicle_Make: temp_data?.quoteLog?.quoteDetails?.manfactureName,
    Vehicle_Model: temp_data?.quoteLog?.quoteDetails?.modelName,
    Vehicle_Variant: temp_data?.quoteLog?.quoteDetails?.versionName,
    Policy_Type: temp_data?.corporateVehiclesQuoteRequest?.businessType,
    Policy_Sub_Type: temp_data?.corporateVehiclesQuoteRequest?.policyType,
    URL: window.location.href,
    Stage: "Proposal",
    TraceID: temp_data?.journeyId,
    ...(temp_data?.userProposal?.policyStartDate && {
      Policy_Effective_Date: temp_data?.userProposal?.policyStartDate,
    }),
    SumInsured: temp_data?.selectedQuote?.idv,
    ...(temp_data?.userProposal?.policyStartDate && {
      ["Policy tenure"]: diffInYearsAndMonths(
        temp_data?.userProposal?.policyStartDate,
        temp_data?.userProposal?.policyEndDate
      ),
    }),
    Premium_Amount: temp_data?.quoteLog?.finalPremiumAmount,
    ["Name"]: `${temp_data?.firstName}${" "}${
      temp_data?.lastName ? `${temp_data?.lastName}` : ``
    }`,
    ["Ph Number"]: temp_data?.mobileNo,
    ["Email ID"]: temp_data?.emailId,
  };
  PushEventToCt("HI_Portal_Proposal_Page_Landed_Motor", dataObj, temp_data);
  // }
};
