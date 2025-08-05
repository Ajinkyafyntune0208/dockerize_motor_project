import HttpClient from "api/httpClient";

//proposal-save
const save = (data) => HttpClient("/save", { method: "POST", data });

//proposal-submit
const submit = ({ typeRoute, data }) =>
  HttpClient(
    `/${typeRoute && typeRoute !== "cv" ? `${typeRoute}/` : ""}submit`,
    { method: "POST", data }
  );

//get gender
const gender = (data) =>
  HttpClient(`/getGender`, {
    method: "POST",
    data,
  });

//get inspection Type
const inspectionType = (data) =>
  HttpClient(`/getInspectionType`, {
    method: "POST",
    data,
  });
//get nominee relation
const relations = (data) =>
  HttpClient(`/getNomineeRelationship`, {
    method: "POST",
    data,
  });

//get financer
//get financer
const financer = (data) =>
  HttpClient(`/getFinancerList`, { method: "POST", data });

//get IFSC
const bankIfsc = (data) =>
  HttpClient(`/bankVerification?ifsc`, {
    method: "POST",
    data,
  });

//get agreement
const agreement = (data) =>
  HttpClient(`/getFinancerAgreementType`, {
    method: "POST",
    data,
  });

//get pincode
const pincode = (data) => HttpClient(`/getPincode`, { method: "POST", data });

//prefill
const prefill = (data) =>
  HttpClient("/getUserRequestedData", { method: "POST", data });

//occupation
const occupation = (data) =>
  HttpClient(`/getOccupation`, { method: "POST", data });

//previous insurer
const prevIc = (data) =>
  HttpClient(`/getPreviousInsurerList`, { method: "POST", data });

const saveLeadData = (data) =>
  HttpClient("/updateUserJourney", { method: "POST", data });

//check Addon Data
const checkAddon = (data) =>
  HttpClient("/cvApplicableAddons", { method: "POST", data });

//brochure/wordings
//brochure/wordings
const wording = (data) =>
  HttpClient(`/getWordingsPdf`, { method: "POST", data });

//url
const url = (data) => HttpClient(`/updateJourneyUrl`, { method: "POST", data });

//save addon
export const saveAddons = (data) =>
  HttpClient(`/saveAddonData`, { method: "POST", data });

//vehicle category
export const category = (data) =>
  HttpClient(`/getVehicleCategories`, { method: "GET", data });

//vehicle usage
export const usage = (id) =>
  HttpClient(`/getVehicleUsageTypes`, {
    method: "POST",
    data: { vehicleCategoryId: id },
  });

//get OTP
export const otp = (data) =>
  HttpClient(`/ComparePolicySmsOtp`, { method: "POST", data });

//verify OTP
export const verifyOtp = (data) =>
  HttpClient(`/verifysmsotp`, {
    method: "POST",
    data,
  });

export const verifyCkycnum = (data) =>
  HttpClient(`/ckyc-verifications`, {
    method: "POST",
    data,
  });

export const godigitKyc = (data) =>
  HttpClient(`/GodigitKycStatus`, {
    method: "POST",
    data,
  });

export const RSKyc = (data) =>
  HttpClient(`/royalSundaramKycStatus`, {
    method: "POST",
    data,
  });

//duplicate enquiry id
const duplicateEnquiry = (data) =>
  HttpClient("/createDuplicateJourney", { method: "POST", data });

//Adrila Journey Redirection
const adrila = (data) =>
  HttpClient(`/getVehicleDetails`, { method: "POST", data });

//get all IC
const getIc = (baseUrl) =>
  HttpClient(
    baseUrl ? `${baseUrl}/getIcList` : "/getIcList",
    { method: "GET" },
    baseUrl ? true : false
  );

//get all fields
//get all fields
const fields = (data, baseUrl) =>
  HttpClient(
    `${baseUrl ? baseUrl : ""}/getProposalFields`,
    { method: "POST", data },
    baseUrl ? true : false
  );
const setFields = (data, baseUrl) =>
  HttpClient(
    `${baseUrl ? baseUrl : ""}/addProposalfield`,
    { method: "POST", data },
    baseUrl ? true : false
  );

//get all fields
//get all fields
const GetOrgfields = (data) =>
  HttpClient(`/getOrganizationTypes`, {
    method: "POST",
    data: { ...data, companyAlias: data?.company_alias },
  });

//get industry types fields (New integration for nic)
const GetIndustryfields = (data) =>
  HttpClient(`/getIndustryTypes`, {
    method: "POST",
    data: { ...data, companyAlias: data?.company_alias },
  });

const finsall = (data) =>
  HttpClient("/finsall/saveOrUpdateBankSelector", { method: "POST", data });

const sbiColors = (data) =>
  HttpClient(`/getColor`, {
    method: "POST",
    data: { companyAlias: data },
  });

const accessToken = (data) =>
  HttpClient("/accessToken", { method: "POST", data });

// resent otp
const resentOtp = (data) => HttpClient("/sendOtp", { method: "POST", data });

// resent otp
const proposalPdf = (data) =>
  HttpClient("/proposalPagePdf", { method: "POST", data });

// financer branch master
const branchMaster = (data) =>
  HttpClient("/getFinancerBranch", { method: "POST", data });

export default {
  save,
  gender,
  relations,
  financer,
  bankIfsc,
  agreement,
  pincode,
  prefill,
  occupation,
  submit,
  prevIc,
  saveLeadData,
  checkAddon,
  wording,
  url,
  saveAddons,
  category,
  usage,
  otp,
  verifyOtp,
  duplicateEnquiry,
  adrila,
  getIc,
  fields,
  setFields,
  finsall,
  sbiColors,
  verifyCkycnum,
  godigitKyc,
  RSKyc,
  accessToken,
  GetOrgfields,
  GetIndustryfields,
  resentOtp,
  proposalPdf,
  branchMaster,
  inspectionType,
};
