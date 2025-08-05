import HttpClient from "api/httpClient";

const addOnList = (data) =>
  HttpClient("/getAddonList", { method: "POST", data });

const voluntaryList = (data) =>
  HttpClient("/getVoluntaryDiscounts", { method: "POST", data });

const masterLogoList = (data) =>
  HttpClient(`/masterCompanyLogos`, {
    method: "POST",
    data,
  });

const getQuotes = (data) =>
  HttpClient("/getProductDetails", { method: "POST", data });

export const updateQuote = (data) =>
  HttpClient("/updateQuoteRequestData ", { method: "POST", data });

export const getPremByIC = (ic, data, typeUrl, ourRequest) =>
  HttpClient(
    typeUrl === "car" || typeUrl === "bike"
      ? `/${typeUrl}/premiumCalculation/${ic}`
      : `/premiumCalculation/${ic}`,
    {
      method: "POST",
      data,
      cancelToken: ourRequest ? ourRequest.token : false,
    },
    false,
    false,
    false,
    false,
    190000
  );

export const saveSelectedQuote = (data) =>
  HttpClient(`/saveQuoteData`, { method: "POST", data });

export const saveSelectedAddons = (data) =>
  HttpClient(`/saveAddonData`, { method: "POST", data });

export const garage = (data) =>
  HttpClient(`/cashlessGarage`, { method: "POST", data });

export const premPdf = (data) =>
  HttpClient(`/premiumBreakupPdf}`, { method: "POST", data });

export const emailPdf = (data) =>
  HttpClient(`/premiumBreakupMail?data=${data?.data}`, {
    method: "POST",
    data,
  });

export const emailComparePdf = (data) =>
  HttpClient(`/comapareEmail?data=${data?.data}`, {
    method: "POST",
    data,
  });

export const getShorlenUrl = (data) =>
  HttpClient("/shorten_url", {
    method: "POST",
    data,
  });

export const whatsappNotification = (data) =>
  HttpClient(`/whatsappNotification`, {
    method: "POST",
    data,
  });

export const postCompareData = (data) =>
  HttpClient(`/comparesms`, {
    method: "POST",
    data: {
      data: data,
    },
  });

export const fetchCompareData = (data) =>
  HttpClient(`/comparesms`, {
    method: "POST",
    data: {
      data,
    },
  });

export const downloadPremiumBreakup = (data) =>
  HttpClient(
    `/premiumBreakupPdf`,
    {
      method: "POST",
      data,
    },
    false,
    false,
    false,
    true
  );

export const addonConfig = (data) =>
  HttpClient(`/getDefaultCovers`, { method: "POST", data });

export default {
  addOnList,
  getQuotes,
  voluntaryList,
  masterLogoList,
  garage,
  premPdf,
  emailPdf,
  whatsappNotification,
  downloadPremiumBreakup,
  emailComparePdf,
  addonConfig,
  postCompareData,
  fetchCompareData,
  getShorlenUrl,
};
