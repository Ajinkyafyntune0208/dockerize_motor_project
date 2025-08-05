import HttpClient from "api/httpClient";

const ncbList = (data) => HttpClient("/getNcb", { method: "POST", data });

const prevInsList = (data) => HttpClient("/previousInsurer", { method: "POST", data });

export const saveQuoteRequestData = (data) =>
  HttpClient("/saveQuoteRequestData", { method: "POST", data });

const saveLeadData = (data) =>
  HttpClient("/updateUserJourney", { method: "POST", data });

export default {
  ncbList,
  prevInsList,
  saveQuoteRequestData,
  saveLeadData,
};
