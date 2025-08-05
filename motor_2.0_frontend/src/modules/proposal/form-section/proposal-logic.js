import { toDate } from "utils";
import moment from "moment";
import { differenceInDays, differenceInMonths } from "date-fns";
import _ from "lodash";

//Logic for previous policy details.
//Conditions below determine when these details will be requested from the customer
export const PreviousPolicyCondition = (TempData) => {
  return (
    //when previous insurer is present and not new
    (TempData?.corporateVehiclesQuoteRequest?.previousInsurer &&
      TempData?.corporateVehiclesQuoteRequest?.previousInsurer !== "NEW" &&
      //incase of adrila hard coding
      TempData?.selectedQuote?.businessType !== "New Business" &&
      TempData?.corporateVehiclesQuoteRequest?.previousPolicyType !==
        "Not sure") ||
    //when third party is selected and business type is not new & previous policy type is not sure
    (TempData?.selectedQuote?.policyType === "Third Party" &&
      TempData?.selectedQuote?.businessType !== "New Business" &&
      TempData?.corporateVehiclesQuoteRequest?.previousPolicyType !==
        "Not sure") ||
    //breakin over 90 days
    ((!TempData?.corporateVehiclesQuoteRequest?.previousInsurer ||
      TempData?.corporateVehiclesQuoteRequest?.previousInsurerCode ===
        "Not selected") &&
      TempData?.corporateVehiclesQuoteRequest?.businessType === "breakin") ||
    //when previos policy type is TP and current policy type is comprehensive / not sure
    TempData?.corporateVehiclesQuoteRequest?.previousPolicyType ===
      "Third-party"
    //when previous policy expiry is not sure but previous policy type is Own Damage. (removed after #5115)
    // || (TempData?.corporateVehiclesQuoteRequest?.previousPolicyType ===
    //   "Not sure" &&
    //   TempData?.corporateVehiclesQuoteRequest?.policyType === "own_damage");
  );
};

//when previous policy expiry is not sure but previous policy type is Own Damage, only TP validations are required
export const ODPreviousPolicyExclusion = (TempData) => {
  return (
    TempData?.corporateVehiclesQuoteRequest?.previousPolicyType ===
      "Not sure" &&
    TempData?.corporateVehiclesQuoteRequest?.policyType === "own_damage"
  );
};

//Is NCB Applicable?
export const NcbApplicable = (TempData) =>
  TempData?.corporateVehiclesQuoteRequest?.previousPolicyType ===
    "Third-party" || TempData?.selectedQuote?.policyType === "Third Party";

//Policy Type calculation cases
export const diffCalc = (caseType, TempData) => {
  if (TempData?.regDate) {
    const currentDate = toDate(moment().format("DD-MM-YYYY"));
    const regDate = toDate(TempData?.regDate);
    const saodDate = toDate(moment().format("01-09-2018"));
    switch (caseType) {
      case "current_reg_days":
        return differenceInDays(currentDate, regDate);
      case "current_reg_months":
        return differenceInMonths(currentDate, regDate);
      case "reg_saod_days":
        return differenceInDays(regDate, saodDate);
      default:
        break;
    }
  } else {
    return false;
  }
};

//TP details Inclusion (Renewal window)
export const TPDetailsInclusion = (TempData, type) =>
  TempData?.selectedQuote?.policyType === "Own Damage" ||
  TempData?.corporateVehiclesQuoteRequest?.policyType === "own_damage" ||
  //If diff between reg date & current date is 34 or above 34 but less than 36
  //For car
  (((diffCalc("current_reg_months", TempData) >= 34 &&
    diffCalc("current_reg_days", TempData) > 270 &&
    //If diff between reg date & current date = 36, we check if the diff of days between reg date & current date <= 1095
    (diffCalc("current_reg_months", TempData) < 36 ||
      (diffCalc("current_reg_months", TempData) === 36 &&
        diffCalc("current_reg_days", TempData) <= 1095)) &&
    type === "car") || //If diff between reg date & current date is 58 or above 58 but less than 60
    //For bike
    (diffCalc("reg_saod_days", TempData) >= 0 &&
      diffCalc("current_reg_months", TempData) >= 58 &&
      diffCalc("current_reg_days", TempData) > 270 &&
      //If diff between reg date & current date = 60, we check if the diff of days between reg date & current date <= 1095
      (diffCalc("current_reg_months", TempData) < 60 ||
        (diffCalc("current_reg_months", TempData) === 60 &&
          diffCalc("current_reg_days", TempData) <= 1095)) &&
      //Only appliable for car
      type === "bike")) &&
    new Date().getFullYear() -
      Number(
        TempData?.corporateVehiclesQuoteRequest?.vehicleRegisterDate?.slice(
          TempData?.corporateVehiclesQuoteRequest?.vehicleRegisterDate?.length -
            4
        )
      ) >=
      1 && //Checking if prev policy type is TP or Compreensive
    ((TempData?.corporateVehiclesQuoteRequest?.previousPolicyType ===
      "Third-party" &&
      TempData?.selectedQuote?.isRenewal !== "Y") ||
      ["Comprehensive", "Own-damage"]?.includes(
        TempData?.corporateVehiclesQuoteRequest?.previousPolicyType
      )) &&
    //previousPolicyTypeIdentifier indicates false OD/Multi-Yr TP/Bundle in prev type policy. Hence it should not be Y
    TempData?.corporateVehiclesQuoteRequest?.previousPolicyTypeIdentifier !==
      "Y");

/*-----ckyc-logic-----*/
//Post submit ckyc
export const PostSubmit = (TempData) =>
  _.compact([
    "godigit",
    "tata_aig",
    "bajaj_allianz",
    "kotak",
    "raheja",
    "new_india",
    "shriram",
    "oriental",
  ]).includes(TempData?.selectedQuote?.companyAlias);
//  ||
// (import.meta.env.VITE_PROD !== "YES" &&
//   TempData?.selectedQuote?.companyAlias === "sbi");

//get ckyc type
//This is for Pre-submit
export const getCkycType = (fields, TempData, data) => {
  return fields?.includes("ckyc") ||
    TempData?.selectedQuote?.companyAlias === "godigit"
    ? data?.isckycPresent === "YES"
      ? "ckyc_number"
      : data?.identity === "aadharNumber"
      ? "aadhar_card"
      : data?.identity === "panNumber"
      ? "pan_card"
      : data?.identity === "form60"
      ? "form60"
      : data?.identity === "drivingLicense"
      ? "driving_license"
      : data?.identity === "voterId"
      ? "voter_id"
      : data?.identity === "passportNumber"
      ? "passport"
      : data?.identity === "cinNumber"
      ? "cinNumber"
      : data?.identity === "gstNumber"
      ? "gstNumber"
      : data?.identity === "passportFileNumber"
      ? "passportFileNumber"
      : data?.identity === "udyam"
      ? "udyam"
      : data?.identity === "udyog"
      ? "udyog"
      : null
    : null;
};
//prettier-ignore
export const getCkycMode = (companyAlias, data, poa_file, poi_file, photo) => {
    return companyAlias === "united_india" || companyAlias === "oriental"
    ? "ckyc"
    : data?.isckycPresent === "YES"
    ? companyAlias === "united_india" || companyAlias === "oriental"
      ? "api"
      : "ckyc_number"
    : poa_file || poi_file || photo
    ? "documents"
    : data?.identity === "aadharNumber"
    ? companyAlias === "icici_lombard"
      ? "aadhar_with_dob"
      : "aadhar"
    : data?.identity === "panNumber"
    ? "pan_number_with_dob"
    : data?.identity === "passportNumber"
    ? "passport"
    : data?.identity === "voterId"
    ? "voter_id"
    : data?.identity === "drivingLicense"
    ? "driving_license"
    : data?.identity === "cinNumber"
    ? "cinNumber"
    : data?.identity === "gstNumber"
    ? "gstNumber"
    : data?.identity === "passportFileNumber"
    ? "passportFileNumber"
    : data?.identity === "udyam"
    ? "udyam"
    : data?.identity === "udyog"
    ? "udyog" 
    : null
}
//prettier-ignore
export const getCkycDocumentMode = (companyAlias, data, poa_file, poi_file, photo) => {
    return companyAlias === "united_india" || companyAlias === "oriental"
    ? "ckyc"
    : data?.isckycPresent === "YES"
    ? "ckyc_number"
    : poa_file || poi_file || photo
    ? "documents"
    : data?.identity === "aadharNumber"
    ? companyAlias === "icici_lombard"
      ? "aadhar_with_dob"
      : "aadhar"
    : data?.identity === "drivingLicense"
    ? "driving_license" : "pan_number_with_dob"
}

export const selectedId = (
  caseType,
  TempData,
  Identities,
  identitiesCompany,
  data,
  companyAlias
) => {
  return data?.[`${caseType}`] && TempData
    ? Number(TempData?.ownerTypeId) === 1
      ? Identities(companyAlias, true).find(
          (each) => each.id === data?.[`${caseType}`]
        )
      : identitiesCompany(companyAlias, true)?.find(
          (each) => each.id === data?.[`${caseType}`]
        )
    : "";
};
/*--x--ckyc-logic--x--*/

/*--------------- Handle Addon declaration -------------*/
//Addon availability check
export const ZD_Validation = (TempData) => {
  //getting list of addons applied to quote
  let applicableAddons = !_.isEmpty(TempData?.addons?.applicableAddons)
    ? TempData?.addons?.applicableAddons?.map((x) => x?.name)
    : [];

  return applicableAddons?.includes("Zero Depreciation") ? true : false;
};
//Road Side Assistance
export const RSA_Validation = (TempData) => {
  //getting list of addons applied to quote
  let applicableAddons = !_.isEmpty(TempData?.addons?.applicableAddons)
    ? TempData?.addons?.applicableAddons?.map((x) => x?.name)
    : [];

  return applicableAddons?.includes("Road Side Assistance") ||
    applicableAddons?.includes("Road Side Assistance 2")
    ? true
    : false;
};
//Key Replacement
export const KR_Validation = (TempData) => {
  //getting list of addons applied to quote
  let applicableAddons = !_.isEmpty(TempData?.addons?.applicableAddons)
    ? TempData?.addons?.applicableAddons?.map((x) => x?.name)
    : [];

  return applicableAddons?.includes("Key Replacement") ? true : false;
};
//NCB Protection
export const NCB_Validation = (TempData) => {
  //getting list of addons applied to quote
  let applicableAddons = !_.isEmpty(TempData?.addons?.applicableAddons)
    ? TempData?.addons?.applicableAddons?.map((x) => x?.name)
    : [];

  return applicableAddons?.includes("NCB Protection") ? true : false;
};
//Tyre Secure
export const TS_Validation = (TempData) => {
  //getting list of addons applied to quote
  let applicableAddons = !_.isEmpty(TempData?.addons?.applicableAddons)
    ? TempData?.addons?.applicableAddons?.map((x) => x?.name)
    : [];

  return applicableAddons?.includes("Tyre Secure") ? true : false;
};
export const ES_Validation = (TempData) => {
  //getting list of addons applied to quote
  let applicableAddons = !_.isEmpty(TempData?.addons?.applicableAddons)
    ? TempData?.addons?.applicableAddons?.map((x) => x?.name)
    : [];

  return applicableAddons?.includes("Engine Protector") ? true : false;
};
export const CO_Validation = (TempData) => {
  //getting list of addons applied to quote
  let applicableAddons = !_.isEmpty(TempData?.addons?.applicableAddons)
    ? TempData?.addons?.applicableAddons?.map((x) => x?.name)
    : [];

  return applicableAddons?.includes("Consumable") ? true : false;
};
//Loss of Personal Belongings
export const LOPB_Validation = (TempData) => {
  //getting list of addons applied to quote
  let applicableAddons = !_.isEmpty(TempData?.addons?.applicableAddons)
    ? TempData?.addons?.applicableAddons?.map((x) => x?.name)
    : [];

  return applicableAddons?.includes("Loss of Personal Belongings")
    ? true
    : false;
};
//Return To Invoice
export const RTI_Validation = (TempData) => {
  //getting list of addons applied to quote
  let applicableAddons = !_.isEmpty(TempData?.addons?.applicableAddons)
    ? TempData?.addons?.applicableAddons?.map((x) => x?.name)
    : [];

  return applicableAddons?.includes("Return To Invoice") ? true : false;
};
//accessories
//Electrical
export const ElectricalKit = (TempData) => {
  let accessories = !_.isEmpty(TempData?.addons?.accessories)
    ? TempData?.addons?.accessories?.map((x) => x?.name)
    : [];

  return accessories?.includes("Electrical Accessories") &&
    TempData?.selectedQuote?.motorElectricAccessoriesValue * 1
    ? true
    : false;
};
//Non-Electrical
export const NonElectricalKit = (TempData) => {
  let accessories = !_.isEmpty(TempData?.addons?.accessories)
    ? TempData?.addons?.accessories?.map((x) => x?.name)
    : [];

  return accessories?.includes("Non-Electrical Accessories") &&
    TempData?.selectedQuote?.motorNonElectricAccessoriesValue * 1
    ? true
    : false;
};
//LPG/CNG
export const Kit = (TempData) => {
  let accessories = !_.isEmpty(TempData?.addons?.accessories)
    ? TempData?.addons?.accessories?.map((x) => x?.name)
    : [];

  return accessories?.includes("External Bi-Fuel Kit CNG/LPG") &&
    (TempData?.selectedQuote?.motorLpgCngKitValue * 1 ||
      TempData?.selectedQuote?.cngLpgTp * 1)
    ? // TempData?.corporateVehiclesQuoteRequest?.fuelType === "CNG" ||
      // TempData?.corporateVehiclesQuoteRequest?.fuelType === "LPG"
      true
    : false;
};

//Listing addon/Kit Requirements
const ZD_req = (TempData) =>
  [
    "tata_aig",
    "royal_sundaram",
    "liberty_videocon",
    "future_generali",
    "hdfc_ergo",
    "sbi",
    "bajaj_allianz",
    "shriram",
    "kotak",
    "icici_lombard",
    "magma"
  ].includes(TempData?.selectedQuote?.companyAlias);
const RSA_req = (TempData) =>
  ["bajaj_allianz", "future_generali", "magma"].includes(
    TempData?.selectedQuote?.companyAlias
  );
const CO_req = (TempData) =>
  ["tata_aig", "future_generali", "magma"].includes(
    TempData?.selectedQuote?.companyAlias
  );
const KR_req = (TempData) =>
  ["bajaj_allianz", "future_generali", "magma"].includes(
    TempData?.selectedQuote?.companyAlias
  );
const ES_req = (TempData) =>
  ["tata_aig", "liberty_videocon", "future_generali", "bajaj_allianz", "magma"].includes(
    TempData?.selectedQuote?.companyAlias
  );
const NCB_req = (TempData) =>
  ["future_generali", "magma"].includes(TempData?.selectedQuote?.companyAlias);
const TS_req = (TempData) =>
  ["tata_aig", "future_generali", "magma"].includes(
    TempData?.selectedQuote?.companyAlias
  );
const RTI_req = (TempData) =>
  ["future_generali", "hdfc_ergo", "sbi", "tata_aig", "magma"].includes(
    TempData?.selectedQuote?.companyAlias
  );
const LOPB_req = (TempData) =>
  ["future_generali", "bajaj_allianz", "magma"].includes(
    TempData?.selectedQuote?.companyAlias
  );
//Accessories
const EA_req = (TempData) =>
  ["future_generali"].includes(TempData?.selectedQuote?.companyAlias);
const NEA_req = (TempData) =>
  ["future_generali"].includes(TempData?.selectedQuote?.companyAlias);
const kit_req = (TempData) =>
  ["tata_aig", "future_generali", "kotak", "sbi"].includes(
    TempData?.selectedQuote?.companyAlias
  );

const addonFunction = (suppfn, excludeCon, AllowIn, TempData) => {
  return (ZD_Validation(TempData) || excludeCon) && AllowIn && suppfn;
};

//Validate addon availability and selection
export const conTS = (TempData) =>
  addonFunction(TS_Validation(TempData), true, TS_req(TempData), TempData);
export const conES = (TempData) =>
  addonFunction(ES_Validation(TempData), true, ES_req(TempData), TempData);
export const conCO = (TempData) =>
  addonFunction(CO_Validation(TempData), true, CO_req(TempData), TempData);
export const conRTI = (TempData) =>
  addonFunction(RTI_Validation(TempData), true, RTI_req(TempData), TempData);
export const conZD = (TempData) =>
  addonFunction(ZD_Validation(TempData), false, ZD_req(TempData), TempData);
export const conRS = (TempData) =>
  addonFunction(RSA_Validation(TempData), true, RSA_req(TempData), TempData);
export const conKR = (TempData) =>
  addonFunction(KR_Validation(TempData), true, KR_req(TempData), TempData);
export const conNCB = (TempData) =>
  addonFunction(NCB_Validation(TempData), true, NCB_req(TempData), TempData);
export const conLOPB = (TempData) =>
  addonFunction(LOPB_Validation(TempData), true, LOPB_req(TempData), TempData);
export const conEAKit = (TempData) =>
  addonFunction(ElectricalKit(TempData), true, EA_req(TempData), TempData);
export const conNEAKit = (TempData) =>
  addonFunction(NonElectricalKit(TempData), true, NEA_req(TempData), TempData);
export const conKit = (TempData) =>
  addonFunction(Kit(TempData), true, kit_req(TempData), TempData);

//Auto selection of addon declaration
export const AddonDeclaration = (companyAlias, TempData) =>
  TempData?.corporateVehiclesQuoteRequest?.businessType !== "newbusiness" &&
  TempData?.policyType !== "Not sure" &&
  companyAlias === "hdfc_ergo"
    ? false
    : true;

//Return array of declared addons
export const addonarr = (TempData) =>
  _.compact([
    conTS(TempData) && "tyreSecure",
    conES(TempData) && "engineProtector",
    conCO(TempData) && "consumables",
    conRTI(TempData) && "returnToInvoice",
    conZD(TempData) && "zeroDepreciation",
    conRS(TempData) && "roadSideAssistance",
    conKR(TempData) && "keyReplace",
    conNCB(TempData) && "ncbProtection",
    conLOPB(TempData) && "lopb",
    conEAKit(TempData) && "electricleKit",
    conNEAKit(TempData) && "nonElectricleKit",
    conKit(TempData) && "externalBiKit",
  ]);

//Hyperverge logic

//united india || Oriental ckyc
export const HyperVergeFn = (
  accessToken,
  companyAlias,
  enquiry_id,
  handler,
  temp_data
) => {
  let customer =
    temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "I"
      ? "individual"
      : "corporate";
  const workflowId =
    companyAlias === "united_india"
      ? ["HEROCARE", "INSTANTBEEMA","VCARE","WOMINGO"].includes(import.meta.env.VITE_BROKER) 
        ? "KYC_Flow"
        : "kyc_flow"
      : "portal_kyc_flow";
  if (companyAlias === "united_india" || companyAlias === "oriental") {
    const transactionId = `${enquiry_id}`;
    const hyperKycConfig = new window.HyperKycConfig(
      accessToken,
      workflowId,
      transactionId
    );
    hyperKycConfig.setInputs({ customer });
    window.HyperKYCModule.launch(hyperKycConfig, handler);
  }
};

//Ongrid execution conditions
export const ongridConditions = (vahaanConfig, type) => {
  return vahaanConfig?.data?.vahan?.proposal?.[type] ?? false;
};

export const ongridReadOnly = (type, temp_data) => {
  const brokers = ["RB", "BAJAJ", "ABIBL", "ACE", "POLICYERA", "SPA", "PAYTM", "KAROINSURE","VCARE","WOMINGO"];

  const broker = import.meta.env.VITE_BROKER;
  const isBike = type === "bike";

  return (
    temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
    (brokers.includes(broker) || (broker === "GRAM" && isBike))
  );
};

//vehicle-card | product type
export const productType = (productType) => {
  switch (productType) {
    case "cv":
      return "commercial vehicle";
    case "car":
      return "car";
    case "bike":
      return "two-wheeler";
    default:
      return productType;
  }
};
