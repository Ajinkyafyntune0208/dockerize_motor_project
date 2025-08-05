import HttpClient from "api/httpClient";

export const loginApi = (data) =>
  HttpClient("/userLogin", { method: "POST", data });

export const typeAccess = (data) =>
  HttpClient("/getProductSubType", { method: "POST", data });

export const postThemeConfig = (data) =>
  HttpClient("/themeConfig", { method: "POST", data });

// logout
const remove_Token = (data) => HttpClient("/logout", { method: "POST", data });

export default {
  typeAccess,
  loginApi,
  postThemeConfig,
  remove_Token,
};
