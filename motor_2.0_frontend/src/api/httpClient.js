import axios from "axios";
import SecureLS from "secure-ls";
import { DataEncrypt, DataDecrypt, _generateKey } from "utils";
import swal from "sweetalert";
import { parse, stringify } from "qs";

const defaultOptions = {
  headers: {},
  queryParams: null,
};

export const restClient = axios.create();

restClient.interceptors.response.use(
  function (response) {
    return response;
  },
  function (error) {
    const err = error.response;
    const ls = new SecureLS();
    if (err.status === 401) {
      ls.remove("token");
      window.history.go("/");
    }
    return Promise.reject(error);
  }
);

const httpClient = async (
  url = "",
  options = defaultOptions,
  noBaseUrl,
  cancelToken,
  allowRaw,
  payloadKey,
  timeout
) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL;
  let fullPath = noBaseUrl ? `${url}` : `${baseUrl}${url}`;
  let encryptionHeader = false && !(options?.data instanceof FormData);

  if (options.queryParams) {
    const queryString = stringify(options.queryParams);
    fullPath = `${fullPath}?${queryString}`;
  }

  const lsToken = sessionStorage.getItem("lsToken");
  const stToken = sessionStorage.getItem("stToken");
  const urlStToken = parse(window.location.search, {
    ignoreQueryPrefix: true,
  })?.stToken;

  //Header used for API getProductSubType. - Ajinkya Thnkre
  restClient.defaults.headers.common[
    "lanninsport"
  ] = `NThmZjc0MGMzZGI1YjY3NDAyZjZlY2Y3OGQ4ODgyZjIjZmM4OTllZjc0NzBlZTY3MDUyZWQ5MmYwZjkwYTI2MTk=`;
  restClient.defaults.headers.common["validation"] = _generateKey(fullPath);
  restClient.defaults.headers.common["Accept"] = `application/json`;

  if (lsToken) {
    restClient.defaults.headers.common["lsToken"] = lsToken;
  }
  //Preference is given to the ST token in url.
  if (urlStToken || stToken) {
    restClient.defaults.headers.common["stToken"] = urlStToken || stToken;
  }
  if (encryptionHeader) {
    restClient.defaults.headers.common["x-encryption"] = `keep`;
  } else {
    restClient.defaults.headers.common["x-encryption"] = ``;
  }

  //Web-engage encryption API headers
  if (
    fullPath ===
      "https://prod-api.bajajcapital.com/bclcomapp/api/encryptData" &&
    import.meta.env.VITE_BROKER === "BAJAJ"
  ) {
    restClient.defaults.headers.common["key"] = "Bajajapi";
    restClient.defaults.headers.common["secret"] = "b@j@j@9#8#7#";
  }

  if (window.location.hostname === "localhost") {
    restClient.defaults.headers.common["exclude-cors"] = "EfEUJNck#9eM";
  }

  return await restClient({
    url: fullPath,
    method: options.method || "GET",
    data: encryptionHeader
      ? { payload: DataEncrypt(options.data) }
      : options.data,
    cancelToken: options.cancelToken,
    timeout: import.meta.env.VITE_PROD === "YES" ? timeout : false,
  })
    .then((resp) => {
      //check for LS & ST Tokens
      if (resp?.data?.responseToken) {
        if (
          stToken ||
          (!stToken && urlStToken) ||
          (stToken && urlStToken && stToken !== urlStToken)
        ) {
          sessionStorage.setItem("lsToken", resp?.data?.responseToken?.lsToken);
          sessionStorage.setItem("stToken", resp?.data?.responseToken?.stToken);
          //update url incase of forced reload
          var url = new URL(window.location.href);
          var searchParams = new URLSearchParams(url.search);
          searchParams.set("stToken", resp?.data?.responseToken?.stToken);
          url.search = searchParams.toString();
          //check if push state is supported
          if (
            window.history !== undefined &&
            window.history.pushState !== undefined
          ) {
            window.history.pushState({ path: url.href }, "", url.href);
          }
          swal("Please Note", "Updating session token", "info").then(() => {
            //Replace old ST Token with new.
            window.location.href = url.href;
          });
        } else {
          sessionStorage.setItem("lsToken", resp?.data?.responseToken?.lsToken);
          sessionStorage.setItem("stToken", resp?.data?.responseToken?.stToken);
        }
      }
      let responseStringified = encryptionHeader
        ? DataDecrypt(resp?.data?.data)
        : resp;
      let response = encryptionHeader
        ? responseStringified && {
            data: JSON.parse(responseStringified),
          }
        : responseStringified;

      return {
        data: response?.data || {},
        errors: response?.data.errors || response?.data.message,
        error: response?.data.error || response?.data.message,
        message: response?.data.message || response?.data?.msg,
        errorSpecific: response?.data?.errorSpecific,
        newEnquiryId: response?.data?.newEnquiryId,
        success:
          (resp?.status === 200 || resp?.status === 201) &&
          response?.data?.status,
        ...(allowRaw && { raw_response: response }),
        overrideMsg: response?.data.overrideMsg,
      };
    })
    .catch((errResp) => {
      let errRespStringified = encryptionHeader
        ? DataDecrypt(errResp?.data?.data)
        : errResp;
      let err =
        encryptionHeader && errRespStringified
          ? JSON.parse(errRespStringified)
          : errResp;
      return {
        data: err,
        errors: err?.response?.data?.errors,
        success: false,
        errorData: err?.response?.data,
        message:
          err?.response?.msg ||
          err?.response?.data?.message ||
          err?.response?.data?.msg ||
          err?.response?.data?.m,
        errorSpecific:
          err?.response?.errorSpecific || err?.response?.data?.errorSpecific,
        ...(allowRaw && { raw_error: err }),
      };
    });
};

export default httpClient;
