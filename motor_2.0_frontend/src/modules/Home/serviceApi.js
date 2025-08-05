import HttpClient from "api/httpClient";
import _ from "lodash";

//lead-page
const enquiry = (data) =>
  HttpClient("/createEnquiryId", { method: "POST", data });

//journey-type
const type = (data) => HttpClient("/getOwnerTypes", { method: "POST", data });

//vehicle type
const vehicleType = (data) =>
  HttpClient("/getVehicleCategory", { method: "POST", data });

//variant type
const variantType = (data) =>
  HttpClient("/getModelVersion", { method: "POST", data });

//brand type
const brandType = (data) =>
  HttpClient("/getManufacturer", { method: "POST", data });

//model type
const modelType = (data) => HttpClient("/getModel", { method: "POST", data });

//RTO
const rto = (data) => HttpClient("/getRto", { method: "POST", data });

//prefill
const prefill = (data) =>
  HttpClient("/getUserRequestedData", { method: "POST", data });

//save
const save = (data) =>
  HttpClient("/saveQuoteRequestData", { method: "POST", data });

//callUs
const callUs = (data) => HttpClient("/callUs", { method: "POST", data });

//shareQuote
const shareQuote = (data) => HttpClient("/sendEmail", { method: "POST", data });

//Token Validation
const tokenVal = (data) =>
  HttpClient("/tokenValidate", { method: "POST", data }, false, false, true);

//produvt category
const subType = (data) =>
  HttpClient("/getProductSubType", { method: "POST", data });

//Theme category
const themeService = (Broker, payload) =>
  HttpClient(
    `${Broker ? Broker : ""}/themeConfig`,
    {
      method: "POST",
      data: { ...payload, test: true },
    },
    Broker ? true : false
  );

//Theme category
const themeServicePost = (data, Broker) =>
  HttpClient(
    `${Broker ? Broker : ""}/themeConfig?test=true`,
    {
      method: "POST",
      data,
    },
    Broker ? true : false
  );

//getFuel
const getFuel = (data) => HttpClient("/getFuelType", { method: "POST", data });

//fastLane
const getFastLane = (data) => {
  const queryParams = ["FYNTUNE", "OLA", "ABIBL", "ACE"].includes(
    import.meta.env.VITE_BROKER
  )
    ? { is_renewal: "Y" }
    : {};
  return HttpClient(`/getVehicleDetails`, {
    method: "POST",
    data: { ...data, ...queryParams },
  });
};

//fastLane
const getFastLaneRenewal = (data) => {
  return HttpClient(`/getVehicleDetails`, {
    method: "POST",
    data: {
      ...data,
      is_renewal: "Y",
      ...(data?.policyNumber && { isPolicyNumber: "Y" }),
    },
  });
};
//vahaan config
const getVahaanConfig = (data) =>
  HttpClient(`/132f0a931bccc0458941eec8e128b8d3`, {
    method: "POST",
    data: { ...data },
  });

//mobileNo validate
const mobileValidator = (data) => {
  return HttpClient("/agentMobileValidator", {
    method: "POST",
    data,
  });
};

//email validate
const emailValidator = (data) => {
  return HttpClient("/agentEmailValidator", {
    method: "POST",
    data,
  });
};
//token validation for payment success redirection
const tokenValidate = (data) =>
  HttpClient(`/getIcons`, {
    method: "POST",
    data,
  });

//whatsapp service
const whatsappTrigger = (data) =>
  HttpClient("/whatsappNotification", { method: "POST", data });

//link-click & delivery
const linkTrigger = (data) =>
  HttpClient("/linkDelivery", { method: "POST", data });

const validationService = (data) =>
  HttpClient("/addProposalValidation", { method: "POST", data });

const getValidationService = (data) =>
  HttpClient("/getProposalValidation", { method: "GET" });

const getIcList = (data) => HttpClient("/getIcList", { method: "GET" });

const getFrontendUrl = (data) =>
  HttpClient(`/frontendUrl`, { method: "POST", data });

const ndsl = (data) =>
  HttpClient("/TmibaslGetNsdlLink", { method: "POST", data });

// post faq
const postFaq = (data) => HttpClient("/postFaq", { method: "POST", data });

// get faq
const getFaq = (data) => HttpClient("/getFaq", { method: "GET" });

// post communication preference
const postCommunicationPreference = (data) =>
  HttpClient("/fetchOrSetCommunicationPreference", { method: "POST", data });

const postFeedback = (data) =>
  HttpClient("/feedback", { method: "POST", data });

const encryptUser = (data) =>
  HttpClient(
    `https://prod-api.bajajcapital.com/bclcomapp/api/encryptData`,
    { method: "POST", data },
    true
  );

//redirection resume journey
const resumeJourney = (data) =>
  HttpClient("/redirect", { method: "POST", data });

export default {
  enquiry,
  type,
  vehicleType,
  brandType,
  modelType,
  rto,
  variantType,
  prefill,
  save,
  callUs,
  tokenVal,
  shareQuote,
  subType,
  themeService,
  themeServicePost,
  getFuel,
  getFastLane,
  getFastLaneRenewal,
  getVahaanConfig,
  mobileValidator,
  emailValidator,
  whatsappTrigger,
  linkTrigger,
  validationService,
  getValidationService,
  getIcList,
  getFrontendUrl,
  ndsl,
  postFaq,
  getFaq,
  postCommunicationPreference,
  postFeedback,
  encryptUser,
  tokenValidate,
  resumeJourney,
};
