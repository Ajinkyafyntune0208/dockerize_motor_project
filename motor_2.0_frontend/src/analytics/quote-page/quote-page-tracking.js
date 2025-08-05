import { PushEventToCt, CtUserLogin } from "../clevertap";

export const _quotePageTracking = (temp_data) => {
  // if(window?.PushEventToCt){
  let dataObj = {
    Product_Type: temp_data?.subProduct?.parent?.productSubTypeCode,
    Registration_Number:
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo,
    Vehicle_Type: temp_data?.subProduct?.productSubTypeName,
    Vehicle_Make: temp_data?.quoteLog?.quoteDetails?.manfactureName,
    Vehicle_Model: temp_data?.quoteLog?.quoteDetails?.modelName,
    Vehicle_Variant: temp_data?.quoteLog?.quoteDetails?.versionName,
    Policy_Type: temp_data?.corporateVehiclesQuoteRequest?.businessType,
    Policy_Sub_Type: temp_data?.corporateVehiclesQuoteRequest?.policyType,
    URL: window.location.href,
    Stage: "Quote",
    TraceID: temp_data?.journeyId,
    ["Name"]: `${temp_data?.firstName}${" "}${
      temp_data?.lastName ? `${temp_data?.lastName}` : ``
    }`,
    ["Ph Number"]: temp_data?.mobileNo,
    ["Email ID"]: temp_data?.emailId,
  };
  temp_data?.mobileNo &&
    CtUserLogin(temp_data?.mobileNo, false, false, temp_data);
  PushEventToCt("HI_Portal_Quotation_Page_Landed_Motor", dataObj, temp_data);
  // }
};
