//eslint-disable-next-line
//test
import CryptoJS from "crypto-js";
import swal from "sweetalert";
import { TypeReturn } from "modules/type";
import _ from "lodash";
import { parse } from "qs";
import moment from "moment";
export * from "./validations";

//api encryption
export const DataEncrypt = (text) => {
  var key = CryptoJS.enc.Utf8.parse("01234567890123456789012345678901");
  var iv = CryptoJS.enc.Utf8.parse("1234567890123412");
  let encryptedTxt = CryptoJS.AES.encrypt(JSON.stringify(text), key, {
    iv: iv,
  });
  return encryptedTxt.toString();
};

//api decryption
export const DataDecrypt = (encryptedTxt) => {
  var DataEncrypt = encryptedTxt ? encryptedTxt.toString() : "";
  var key = CryptoJS.enc.Utf8.parse("01234567890123456789012345678901");
  var iv = CryptoJS.enc.Utf8.parse("1234567890123412");
  var decrypted = CryptoJS.AES.decrypt(DataEncrypt, key, {
    iv: iv,
  });
  var decrypted = CryptoJS.enc.Utf8.stringify(decrypted);
  return decrypted;
};

/** Encryption */
export const Encrypt = (str) => {
  return window.btoa(str);
};

/** Decryption */
export const Decrypt = (str) => {
  // Regular expression to validate Base64-encoded strings
  const isBase64 = (input) => {
    const base64Regex =
      /^(?:[A-Za-z0-9+/]{4})*?(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/;
    return base64Regex.test(input);
  };

  if (isBase64(str)) {
    try {
      return window.atob(str); // Decode the Base64 string
    } catch (error) {
      console.error("Decoding failed:", error);
      return null; // Return null if decoding fails
    }
  } else {
    console.warn("Invalid Base64 string");
    return null; // Return null if the input is not valid
  }
};

export const useQuery = (search) => {
  return new URLSearchParams(search);
};

export const getFirstError = (errors) => {
  const keys = Object.keys(errors);
  const error = keys && keys.length > 0 ? errors[keys[0]] : "";
  return error && error.length > 0 ? error[0] : "";
};

export const processData = (data) => {
  if (!data) return data;
  const dataStr = JSON.stringify(data);
  dataStr.replace(/true/g, 1);
  dataStr.replace(/false/g, 0);
  return JSON.parse(dataStr);
};

export const checkBool = (bool) => {
  return (
    typeof bool === "boolean" ||
    (typeof bool === "object" &&
      bool !== null &&
      typeof bool.valueOf() === "boolean")
  );
};

export const downloadFile = (
  url,
  options,
  isTarget,
  isForm,
  metaData,
  isPdf
) => {
  if (isForm) {
    const form = document.createElement("form");
    form.setAttribute("method", "post");
    form.setAttribute("action", url);
    if (!isPdf) {
      const IC_KYC_No = document.createElement("input");
      IC_KYC_No.value = metaData?.IC_KYC_No;
      IC_KYC_No.name = "IC_KYC_No";
      const Aggregator_KYC_Req_No = document.createElement("input");
      Aggregator_KYC_Req_No.value = metaData?.Aggregator_KYC_Req_No;
      Aggregator_KYC_Req_No.name = "Aggregator_KYC_Req_No";
      const Aggregator_Return_URL = document.createElement("input");
      Aggregator_Return_URL.value = metaData?.Aggregator_Return_URL;
      Aggregator_Return_URL.name = "Aggregator_Return_URL";
      form.append(IC_KYC_No);
      form.append(Aggregator_KYC_Req_No);
      form.append(Aggregator_Return_URL);
    } else {
      form.append("data", isPdf);
    }
    const submit = document.createElement("button");
    submit.setAttribute("type", "submit");
    submit.setAttribute("value", "Submit");
    form.append(submit);
    // &IC_KYC_No=${metaData?.IC_KYC_No}&Aggregator_KYC_Req_No=${metaData?.Aggregator_KYC_Req_No}
    // &Aggregator_Return_URL=${metaData?.Aggregator_Return_URL}`);

    document.body.appendChild(form);
    submit.click();
    document.body.removeChild(form);
  } else {
    const link = document.createElement("a");
    if (options) link.setAttribute("href", `options${encodeURIComponent(url)}`);
    if (isTarget) {
      link.setAttribute("target", `_blank`);
    }
    link.href = url;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
};

// all error
export const serializeError = (payload) => {
  let message = "";
  if (typeof payload === "string") message = payload;
  else if (typeof payload === "object") {
    for (const property in payload) {
      message = `${message}
${payload[property][0]}`;
    }
  }
  return message === "" ? "Network Error" : message;
};

// common action creatre(single get & post)
export const actionStructre = async (
  dispatch,
  onSuccess,
  onError,
  api,
  payload,
  specifyError,
  overrideMsg
) => {
  const {
    data,
    message,
    errors,
    success,
    errorSpecific,
    overrideMsg: msg,
    newEnquiryId,
  } = await api(payload);
  if (overrideMsg && msg) {
    dispatch(overrideMsg(msg));
  }
  if (data?.data || success) {
    dispatch(onSuccess(data?.data || message));
  } else {
    specifyError && errorSpecific && dispatch(specifyError(errorSpecific));
    dispatch(
      onError(
        (newEnquiryId && `newEnquiryId::${newEnquiryId}`) || errors || message
      )
    );
    console.error("Error", errors || message);
  }
};

// common action creatre(single get & post)
export const actionStructreBoth = async (
  dispatch,
  onSuccess,
  onError,
  api,
  payload,
  specifyError,
  errorData,
  raw,
  ckyc_error_data
) => {
  const { data, message, errors, success, errorSpecific, error, raw_error } =
    await api(payload);
  if (data.data && success) {
    dispatch(onSuccess(data.data || message));
  } else {
    raw && raw_error && dispatch(raw(raw_error));
    ckyc_error_data &&
      !_.isEmpty(data?.data) &&
      dispatch(ckyc_error_data(data.data));
    specifyError && errorSpecific && dispatch(specifyError(errorSpecific));
    errorData && dispatch(errorData(data));
    dispatch(onError(error || errors || message));
    console.error("Error", errors || message);
  }
};

export const numOnlyNoZero = (event) => {
  let key = event.keyCode || event.which;
  var startPos = event.currentTarget.selectionStart;

  if (startPos === 0 && (key === 48 || key === 96)) {
    event.preventDefault();
  } else if (
    event.shiftKey === false &&
    ((key >= 48 && key <= 57) ||
      (key >= 96 && key <= 105) ||
      key === 8 ||
      key === 9 ||
      key === 13 ||
      key === 16 ||
      // key === 17 ||
      key === 20 ||
      key === 35 ||
      key === 36 ||
      key === 37 ||
      key === 39 ||
      key === 46)
    // key === 144
  ) {
  } else {
    event.preventDefault();
  }
};

export const numOnly = (event) => {
  let key = event.keyCode || event.which;
  if (
    event.shiftKey === false &&
    ((key >= 48 && key <= 57) ||
      (key >= 96 && key <= 105) ||
      key === 8 ||
      key === 9 ||
      key === 13 ||
      key === 16 ||
      key === 17 ||
      key === 20 ||
      key === 35 ||
      key === 36 ||
      key === 37 ||
      key === 39 ||
      key === 86 ||
      key === 67 ||
      key === 46)
    // key === 144
  ) {
  } else {
    event.preventDefault();
  }
};

export const alpha = (e) => {
  let k = e.keyCode || e.which;
  return (
    (k > 64 && k < 91) ||
    (k > 96 && k < 123) ||
    k === 8 ||
    k === 32 ||
    (k >= 48 && k <= 57)
  );
};

export const noSpace = (event) => {
  let key = event.keyCode || event.which;
  if (key === 32) {
    event.preventDefault();
  }
};

export const toDate = (dateStr) => {
  const [day, month, year] = dateStr.split("-");
  return new Date(year, month - 1, day);
};

export const toDateOld = (dateStr) => {
  const [day, month, year] = dateStr.split("-");
  const originalDate = new Date(year, month - 1, day);
  originalDate.setMonth(originalDate.getMonth() - 25);
  return originalDate;
};

export const toDateDayOld = (dateStr) => {
  const [day, month, year] = dateStr.split("-");
  return new Date(year, month - 1, day - 1);
};

export const scrollToTargetAdjusted = (id, offsetVal) => {
  var element = document.getElementById(`${id}`);
  if (element) {
    const offset = offsetVal || 45;
    const bodyRect = document.body.getBoundingClientRect().top;
    const elementRect = element.getBoundingClientRect().top;
    const elementPosition = elementRect - bodyRect;
    const offsetPosition = elementPosition - offset;
    window.scrollTo({
      top: offsetPosition,
      behavior: "smooth",
    });
  }
};

export const scrollToTop = () => {
  window.scrollTo(0, 0);
};

export const reloadPage = (url, isTarget) => {
  const link = document.createElement("a");
  link.href = url;
  if (isTarget) {
    link.setAttribute("target", `_blank`);
  }
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

export const randomString = () =>
  Math.random().toString(36).substring(2, 15) +
  Math.random().toString(36).substring(2, 15);

export const currencyFormater = (amount, decimal) => {
  if (amount) {
    let formatedAmount = Number(amount)
      ?.toFixed(2)
      .replace(/\d(?=(\d{3})+\.)/g, "$&,")
      .slice(0, -3);
    if (decimal) {
      formatedAmount = Number(amount)
        ?.toFixed(2)
        .replace(/\d(?=(\d{3})+\.)/g, "$&,");
    }
    return formatedAmount;
  } else {
    return 0;
  }
};

export const camelToUnderscore = (key) => {
  var result = key.replace(/([A-Z])/g, " $1");
  return result.split(" ").join("_").toLowerCase();
};

export const RedirectFn = (token) => {
  switch (import.meta.env?.VITE_BROKER) {
    case "OLA":
      if (
        import.meta.env?.VITE_API_BASE_URL === "https://olaapi.fynity.in/api"
      ) {
        return "https://ola-dashboard.fynity.in/";
      } else {
        return "http://uatoladashboard.fynity.in/";
      }
    case "FYNTUNE":
      return "";
    case "ABIBL":
      if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apiabibl-preprod-carbike.fynity.in/api"
      ) {
        return "http://preprod-dasbhoard-abibl.fynity.in/";
      } else if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apiabibl-carbike.fynity.in/api"
      ) {
        return "http://uat-dasbhoard-abibl.fynity.in/";
      } else {
        return "http://abibl-prod-dashboard.fynity.in/";
      }
    case "GRAM":
      if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apigramcover-carbike.fynity.in/api"
      ) {
        return "http://uat-dasbhoard-gramcover.fynity.in/";
      } else {
        return "https://dashboard.gramcover.com/";
      }
    case "ACE":
      return "https://crm.aceinsurance.com:5555/";
    case "SRIYAH":
      return import.meta.env.VITE_PROD === "YES"
        ? "https://nammacover.com/"
        : "https://uat.nammacover.com/";
    case "RB":
      return window.location.hostname.includes("renewbuyinsurance")
        ? "https://www.renewbuyinsurance.com/"
        : "https://www.renewbuy.com/";
    case "SPA":
      return `https://uatdashboard.insuringall.com/`;
    case "BAJAJ":
      return import.meta.env.VITE_PROD === "YES"
        ? import.meta.env.VITE_BASENAME === "general-insurance"
          ? token
            ? "https://dashboard.bajajcapitalinsurance.com/customer/login"
            : window.location.origin
          : "https://partner.bajajcapitalinsurance.com"
        : import.meta.env.VITE_API_BASE_URL ===
          "https://stageapimotor.bajajcapitalinsurance.com/api"
        ? import.meta.env.VITE_BASENAME === "general-insurance"
          ? token
            ? "https://stagedashboard.bajajcapitalinsurance.com/customer/login"
            : window.location.origin
          : "https://partnerstage.bajajcapitalinsurance.com"
        : //UAT
        import.meta.env.VITE_BASENAME === "general-insurance"
        ? token
          ? "https://uatdashboard.bajajcapitalinsurance.com/customer/login"
          : "https://buypolicyuat.bajajcapitalinsurance.com/"
        : "https://partneruat.bajajcapitalinsurance.com";
    case "UIB":
      return ``;
    case "SRIDHAR":
      return `https://uatdashboard.sibinsure.com/`;
    case "POLICYERA":
      return import.meta.env.VITE_PROD === "YES"
        ? `https://dashboard.policyera.com/`
        : `https://uatdashboard.policyera.com/`;
    case "TATA":
      return import.meta.env.VITE_PROD === "YES"
        ? "https://lifekaplan.com/"
        : "https://uat.lifekaplan.com/";
    case "HEROCARE":
      return import.meta.env.VITE_PROD === "YES"
        ? !window.location.href.includes("preprod")
          ? `https://dashboard.heroinsurance.com/`
          : `https://preproddashboard.heroinsurance.com/`
        : `https://uatdashboard.heroinsurance.com/`;
    case "PAYTM":
      return import.meta.env.VITE_PROD === "YES"
        ? `https://posp.paytminsurance.co.in/posp/dashboard`
        : `https://posp-nonprod.paytminsurance.co.in/dashboard`;
    case "KMD":
      return "https://dashboard.kmdastur.com/misp/login";
    case "KAROINSURE":
      return import.meta.env.VITE_PROD === "YES"
        ? `https://www.karoinsure.in/dashboard/pos/login`
        : `https://uatkaroinsure.fynity.in/dashboard-revamp/login`;
    default:
      break;
  }
};

export const getLogoCvType = (productSubTypeId) => {
  switch (productSubTypeId) {
    case 5:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/auto.png`;
    case 11:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/auto.png`;
    case 6:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/taxi-car1.png`;
    case 9:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/trck.png`;
    case 13:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/dumper2.png`;
    case 14:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/pickup.png`;
    case 15:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/tractor.png`;
    case 16:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/vehicle/tanker.png`;
    default:
      return `${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ""
      }/assets/images/auto-car.png`;
  }
};

export const UrlFn = (urlresp, token) => {
  const url = urlresp ? urlresp : "";
  switch (import.meta.env?.VITE_BROKER) {
    case "OLA":
      if (
        import.meta.env?.VITE_API_BASE_URL === `https://olaapi.fynity.in/api`
      ) {
        return `https://ola-dashboard.fynity.in/${url}`;
      } else {
        return `http://uatoladashboard.fynity.in/${url}`;
      }
    case "FYNTUNE":
      return "";
    case "ABIBL":
      if (
        import.meta.env?.VITE_API_BASE_URL ===
        `https://apiabibl-preprod-carbike.fynity.in/api`
      ) {
        return `http://preprod-dasbhoard-abibl.fynity.in/${url}`;
      } else if (
        import.meta.env?.VITE_API_BASE_URL ===
        `https://apiabibl-carbike.fynity.in/api`
      ) {
        return `http://uat-dasbhoard-abibl.fynity.in/${url}`;
      } else {
        return `http://abibl-prod-dashboard.fynity.in/${url}`;
      }
    case "GRAM":
      if (
        import.meta.env?.VITE_API_BASE_URL ===
        "https://apigramcover-carbike.fynity.in/api"
      ) {
        return `http://uat-dasbhoard-gramcover.fynity.in/${url}`;
      } else {
        return `https://dashboard.gramcover.com/${url}`;
      }
    case "ACE":
      return "https://crm.aceinsurance.com:5555/";
    case "SRIYAH":
      return import.meta.env.VITE_PROD === "YES"
        ? "https://nammacover.com/"
        : "https://uat.nammacover.com/";
    case "RB":
      return window.location.hostname.includes("renewbuyinsurance")
        ? "https://www.renewbuyinsurance.com/"
        : "https://www.renewbuy.com/";
    case "SPA":
      return `https://uatdashboard.insuringall.com/${url}`;
    case "BAJAJ":
      return import.meta.env.VITE_PROD === "YES"
        ? import.meta.env.VITE_BASENAME === "general-insurance"
          ? token
            ? "https://dashboard.bajajcapitalinsurance.com/customer/login"
            : window.location.origin
          : "https://partner.bajajcapitalinsurance.com"
        : import.meta.env.VITE_API_BASE_URL ===
          "https://stageapimotor.bajajcapitalinsurance.com/api"
        ? import.meta.env.VITE_BASENAME === "general-insurance"
          ? token
            ? "https://stagedashboard.bajajcapitalinsurance.com/customer/login"
            : window.location.origin
          : "https://partnerstage.bajajcapitalinsurance.com"
        : //UAT
        import.meta.env.VITE_BASENAME === "general-insurance"
        ? token
          ? "https://uatdashboard.bajajcapitalinsurance.com/customer/login"
          : "https://buypolicyuat.bajajcapitalinsurance.com/"
        : "https://partneruat.bajajcapitalinsurance.com";
    case "UIB":
      return ``;
    case "SRIDHAR":
      return `https://uatdashboard.sibinsure.com/${url}`;
    case "POLICYERA":
      return import.meta.env.VITE_PROD === "YES"
        ? `https://dashboard.policyera.com/${url}`
        : `https://uatdashboard.policyera.com/${url}`;
    case "TATA":
      return import.meta.env.VITE_PROD === "YES"
        ? "https://lifekaplan.com/"
        : "https://uat.lifekaplan.com/";
    case "HEROCARE":
      return import.meta.env.VITE_PROD === "YES"
        ? !window.location.href.includes("preprod")
          ? `https://dashboard.heroinsurance.com/`
          : `https://preproddashboard.heroinsurance.com/`
        : `https://uatdashboard.heroinsurance.com/${url}`;
    case "PAYTM":
      return import.meta.env.VITE_PROD === "YES"
        ? `https://posp.paytminsurance.co.in/posp/dashboard/${url}`
        : `https://posp-nonprod.paytminsurance.co.in/dashboard/${url}`;
    default:
      return `https://ola-dashboard.fynity.in/${url}`;
  }
};

export const simpleEncrypt = (salt, text) => {
  const textToChars = (text) => text.split("").map((c) => c.charCodeAt(0));
  const byteHex = (n) => ("0" + Number(n).toString(16)).substr(-2);
  const applySaltToChar = (code) =>
    textToChars(salt).reduce((a, b) => a ^ b, code);

  return text
    .split("")
    .map(textToChars)
    .map(applySaltToChar)
    .map(byteHex)
    .join("");
};

export const simpleDecrypt = (salt, encoded) => {
  const textToChars = (text) => text.split("").map((c) => c.charCodeAt(0));
  const applySaltToChar = (code) =>
    textToChars(salt).reduce((a, b) => a ^ b, code);
  return encoded
    .match(/.{1,2}/g)
    .map((hex) => parseInt(hex, 16))
    .map(applySaltToChar)
    .map((charCode) => String.fromCharCode(charCode))
    .join("");
};

export const AccessControl = (type, typeAccess, history) => {
  if (type) {
    const AccessControl = !_.isEmpty(typeAccess)
      ? _.compact(
          typeAccess.map((item) =>
            item?.product_sub_type_code
              ? item?.product_sub_type_code?.toLowerCase()
              : null
          )
        )
      : [];
    let typeRt = TypeReturn(type) === "cv" ? "pcv" : TypeReturn(type);
    let typeRt1 = TypeReturn(type) === "cv" ? "gcv" : TypeReturn(type);
    if (!(AccessControl.includes(typeRt) || AccessControl.includes(typeRt1))) {
      swal(
        "Error",
        "Access Control Error. Please contact the administrator for clearance.",
        "error",
        { closeOnClickOutside: false }
      ).then(() => history.replace("/"));
    }
  }
};

export const Disable_B2C = (
  temp_data,
  checkSellerType,
  token,
  journey_type,
  skip,
  theme_conf
) => {
  if (
    (import.meta.env?.VITE_BROKER === "ACE" ||
      // import.meta.env.VITE_BROKER === "KAROINSURE" ||
      (import.meta.env?.VITE_BROKER === "BAJAJ" &&
        import.meta.env.VITE_BASENAME !== "general-insurance")) &&
    !_.isEmpty(temp_data.corporateVehiclesQuoteRequest) &&
    !(checkSellerType?.includes("P") || checkSellerType?.includes("E")) &&
    !token &&
    import.meta.env.VITE_PROD === "YES"
  ) {
    if (import.meta.env?.VITE_BROKER === "BAJAJ") {
      swal("Access Control Error. User login required.", {
        closeOnClickOutside: false,
      }).then(() => reloadPage("https://partner.bajajcapitalinsurance.com/"));
    }
    if (import.meta.env?.VITE_BROKER === "ACE") {
     reloadPage("https://dashboard.aceinsurance.com/");
    }
    // if (import.meta.env?.VITE_BROKER === "KAROINSURE") {
    //   swal("Access Control Error. User login required.", {
    //     closeOnClickOutside: false,
    //   }).then(() => reloadPage("https://www.karoinsure.com/dashbaord/"));
    // }
  }
  if (
    import.meta.env?.VITE_BROKER === "OLA" &&
    !token &&
    !journey_type &&
    !_.isEmpty(temp_data) &&
    temp_data?.corporateVehiclesQuoteRequest?.journeyType !== "embeded-excel" &&
    temp_data?.corporateVehiclesQuoteRequest?.journeyType !==
      "embedded_scrub" &&
    !skip
  ) {
    swal("Access Control Error. User login required.").then(() =>
      reloadPage(
        theme_conf?.broker_config?.broker_asset?.other_failure_url?.url ||
          RedirectFn(token)
      )
    );
  }
};

//journey stage exclusion from url dispatch
const excludeStage = (temp_data) => {
  return ![
    "Payment Initiated",
    "pg_redirection",
    "Policy Issued",
    "Policy Issued, but pdf not generated",
    "Policy Issued And PDF Generated",
    "payment success",
    "payment failed",
    "Inspection Accept",
  ].includes(
    ["payment success", "payment failed"].includes(
      temp_data?.journeyStage?.stage?.toLowerCase()
    )
      ? temp_data?.journeyStage?.stage?.toLowerCase()
      : temp_data?.journeyStage?.stage
  );
};

//Handle journey stages
//prettier-ignore
export const journeyProcess = (dispatch, Url, API ,enquiry_id, temp_data, stage, additionalCon, addParam, type) => {
  if (
    enquiry_id &&
    temp_data?.journeyStage?.stage &&
    temp_data?.userProposal?.isBreakinCase !== "Y" &&
    (_.isEmpty(additionalCon) ? true : additionalCon?.condition)
  ) {
    excludeStage(temp_data)
    &&
      dispatch(
        Url({
          proposalUrl: `${window.location.href}${addParam ? addParam : ""}`,
          quoteUrl: `${window.location.href}${addParam ? addParam : ""}`,
          stage: stage ,
          userProductJourneyId: enquiry_id,
          ...(type !== "cv" && { section: type }),
        })
      );
  }
  //create duplicate enquiry id
  if (
    temp_data?.journeyStage?.stage === "Payment Initiated" ||
    temp_data?.journeyStage?.stage?.toLowerCase() === "payment failed"
  ) {
    dispatch(API({ enquiryId: enquiry_id }));
  }
}

//prettier-ignore
export const journeyProcessProposal = ( dispatch, Url, enquiry_id, temp_data, stage ) => {
  if (
    enquiry_id &&
    temp_data?.journeyStage?.stage &&
    temp_data?.userProposal?.isBreakinCase !== "Y"
  ) {
    excludeStage(temp_data) &&
      dispatch(
        Url({
          proposalUrl: window.location.href,
          quoteUrl: window.location.href
            ? window.location.href?.replace(/proposal-page/g, "quotes")
            : "",
          stage: stage,
          userProductJourneyId: enquiry_id,
        })
      );
  }
};

//prettier-ignore
export const journeyProcessQuotes = (dispatch, Url, API ,enquiry_id, temp_data, stage) => {
  if (
    enquiry_id &&
    temp_data?.journeyStage?.stage &&
    temp_data?.userProposal?.isBreakinCase !== "Y"
  ) {
    excludeStage(temp_data)
    &&
      dispatch(
        Url({
          proposalUrl: `${window.location.href}`,
          quoteUrl: `${window.location.href}`,
          stage: stage ,
          userProductJourneyId: enquiry_id,
          lsq_stage: "Quote Seen",      
        })
      );
  }
  //create duplicate enquiry id
  if (
    temp_data?.journeyStage?.stage === "Payment Initiated" ||
    temp_data?.journeyStage?.stage?.toLowerCase() === "payment failed"
  ) {
    dispatch(API({ enquiryId: enquiry_id }));
  }
}

//Payment incomplete action
//prettier-ignore
export const PaymentIncomplete = ( type, token, enquiryId, typeId, journey_type, loc, _stToken, shared) => {
  swal(
    "Please Note",
    "Payment status is Incomplete. Proposal update required.",
    "info"
  ).then(() => {
    reloadPage(
      `${window.location.protocol}//${window.location.host}${
        import.meta.env.VITE_BASENAME !== "NA"
          ? `/${import.meta.env.VITE_BASENAME}`
          : ``
      }/${type}/${loc}?enquiry_id=${enquiryId}${
        token ? `&xutm=${token}` : ""
      }${typeId ? `&typeid=${typeId}` : ``}${
        journey_type ? `&journey_type=${journey_type}` : ``
      }${_stToken ? `&stToken=${_stToken}` : ``}${shared ? `&shared=${shared}` : ``}`
    );
  });
};

//Post payment Actions
export const PostTransaction = (
  temp_data,
  dispatch,
  CancelAll,
  enquiry_id,
  noFailure,
  _stToken
) => {
  const PaymentSuccessfulStages = [
    "Policy Issued And PDF Generated",
    "Policy Issued",
    "Policy Issued, but pdf not generated",
    "payment success",
  ];

  if (
    PaymentSuccessfulStages.includes(
      temp_data?.journeyStage?.stage?.toLowerCase() === "payment success"
        ? "payment success"
        : temp_data?.journeyStage?.stage
    )
  ) {
    dispatch && CancelAll && dispatch(CancelAll(true));
    swal("Info", "This Proposal has already been submitted", "info").then(() =>
      temp_data?.journeyStage?.stage?.toLowerCase() !== "payment failed"
        ? reloadPage(
            `${window.location.protocol}//${window.location.host}${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ``
            }/payment-success${enquiry_id ? `?enquiry_id=${enquiry_id}` : ``}${
              _stToken ? `&stToken=${_stToken}` : ``
            }`
          )
        : !noFailure
        ? reloadPage(
            `${window.location.protocol}//${window.location.host}${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ``
            }/payment-failure${enquiry_id ? `?enquiry_id=${enquiry_id}` : ``}${
              _stToken ? `&stToken=${_stToken}` : ``
            }`
          )
        : {}
    );
  }
};

export const fetchToken = () => {
  let _storedSt = sessionStorage.getItem("stToken");
  let _urlSt = parse(window.location.search, {
    ignoreQueryPrefix: true,
  })?.stToken;
  return _urlSt || _storedSt;
};

export const _preventDuplicateTab = (enquiry_id) => {
  const channel = new BroadcastChannel("tab");
  channel.postMessage(enquiry_id);
  // note that listener is added after posting the message

  channel.addEventListener("message", (msg) => {
    if (msg.data === enquiry_id) {
      // message received from 2nd tab
      swal(
        "Please note",
        "Multiple tabs with this session has been detected. This instance of the session will be cleared",
        "info"
      ).then(() => {
        sessionStorage.clear();
        window.location.reload();
      });
    }
  });
};

export const _translate = () => {
  //Initialize translate
  const _translateInit = () => {
    new window.google.translate.TranslateElement(
      {
        pageLanguage: "en",
        autoDisplay: false,
        includedLanguages: "en,hi",
      },
      "google_translate_element"
    );
  };

  var addScript = document.createElement("script");
  addScript.setAttribute("async", true);
  addScript.setAttribute(
    "src",
    "https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"
  );
  document.body.appendChild(addScript);
  window.googleTranslateElementInit = _translateInit;
};

//Dynamic meta data
export const meta = (typeRoute, theme_conf) => ({
  title:
    import.meta.env.VITE_BROKER === "HEROCARE"
      ? "Hero Insurance"
      : import.meta.env.VITE_BROKER === "BAJAJ"
      ? typeRoute() === "bike"
        ? "Bike Insurance | Compare and Buy/Renew 2 wheeler Insurance"
        : typeRoute() === "car"
        ? "Car Insurance | Compare and Buy/Renew 4 wheeler Insurance"
        : "Motor Insurance | Check Your Insurance Payment Status | Policy Document"
      : import.meta.env.VITE_TITLE,
  description:
    import.meta.env.VITE_BROKER === "BAJAJ"
      ? typeRoute() === "bike"
        ? "Looking for the best bike insurance? Compare and buy/renew 2 wheeler insurance for your motorcycle. Get affordable coverage for your ride today."
        : typeRoute() === "car"
        ? "Compare and Buy/Renew 4 Wheeler Insurance for Old and New Cars. Get the best coverage for your vehicle today"
        : "Quickly check the status of your recent insurance payments and view your policy document. Get up-to-date information on Insurance coverages and more."
      : import.meta.env.VITE_META,
  meta: {
    charset: "utf-8",
    property: {
      "og:url": `${window.location.hostname}${
        import.meta.env.VITE_BASENAME === "NA"
          ? ""
          : `/${import.meta.env.VITE_BASENAME}`
      }`,
    },
  },
});

export const _setAgentSession = (agent, enquiry_id, pc_redirection) => {
  if (
    !_.isEmpty(agent) &&
    enquiry_id &&
    !localStorage.getItem("enquiry_id") &&
    pc_redirection
  ) {
    localStorage.setItem(
      `${enquiry_id}_${import.meta.env.VITE_BROKER}_${
        import.meta.env.VITE_PROD === "YES" ? 1 : 0
      }`,
      agent[0]?.sellerType
    );
  }
};

export const _isUserCustomer = (enquiry_id, pc_redirection) => {
  if (enquiry_id && pc_redirection) {
    return !localStorage?.[
      `${enquiry_id}_${import.meta.env.VITE_BROKER}_${
        import.meta.env.VITE_PROD === "YES" ? 1 : 0
      }`
    ];
  }
};

export const _generateKey = (fullPath) => {
  function randomSplit(inputString, marker = "~:~") {
    const parts = inputString.match(/.{1,5}/g) || [];
    return parts.join(marker);
  }
  function randomEncrypt(inputString, markerOpen = "{", markerClose = "}") {
    return inputString
      .split("~:~")
      .map((part) => {
        return Math.random() < 0.5
          ? `${markerOpen}${DataEncrypt(part)}${markerClose}`
          : part;
      })
      .join("~:~");
  }

  const preSharedKey = "ijHjx4/alAwjLu1ftuwLF3g0w4pNORaol9GQ4Y0qYVM=";
  const splitString = randomSplit(preSharedKey);
  const encryptedString = randomEncrypt(splitString);

  const validity = moment().add(3, "minutes").format("DD/MM/YYYY HH:mm:ss");
  const userAgent = window.navigator.userAgent;
  const encodedFullPath = encodeURIComponent(fullPath);

  const key = `${encryptedString}|${encodedFullPath}|${validity}|${userAgent}`;
  return DataEncrypt(key);
};

export const getPageName = (url) => {
  if (url.includes("lead-page")) {
    return "lead-page";
  } else if (url.includes("registration")) {
    return "registration-page";
  } else if (url.includes("vehicle-type")) {
    return "vehicle-category";
  } else if (url.includes("vehicle-details")) {
    return "vehicle-details";
  } else if (url.includes("quotes")) {
    return "quote-page";
  } else if (url.includes("compare-quote")) {
    return "compare-page";
  } else if (url.includes("proposal")) {
    return "proposal-page";
  } else if (url.includes("payment-gateway")) {
    return "payment-gateway";
  } else if (url.includes("payment-success")) {
    return "payment-success";
  } else if (url.includes("payment-failure")) {
    return "payment-failure";
  } else if (url.includes("successful")) {
    return "breakin-generation";
  } else {
    return "motor-insurance";
  }
};

export const isB2B = (data, returnType) => {
  let agent =
    !_.isEmpty(
      data?.agentDetails?.filter(
        (item) =>
          ["E", "P"].includes(item?.sellerType) ||
          ["E", "P"].includes(item?.seller_type)
      )
    ) &&
    data?.agentDetails?.filter(
      (item) =>
        ["E", "P"].includes(item?.sellerType) ||
        ["E", "P"].includes(item?.seller_type)
    );
  return returnType && !_.isEmpty(agent) ? agent[0] : !_.isEmpty(agent);
};

export const _haptics = (feedbackArray) => {
  return navigator && navigator?.vibrate && navigator.vibrate(feedbackArray);
};

export const dateConvert = (dateString) => {
  return dateString === "New"
    ? "New"
    : moment.utc(dateString, "DD-MM-YYYY").toDate();
};

export const createHash = (obj) => {
  const entries = Object.entries(obj);
  const sortedEntries = JSON.stringify(
    entries.sort((a, b) => a[0].localeCompare(b[0]))
  );
  const hash = CryptoJS.MD5(sortedEntries).toString();
  return hash;
};

export function diffInYearsAndMonths(date1, date2) {
  const d1 = moment(date1, "DD-MM-YYYY");
  const d2 = moment(date2, "DD-MM-YYYY");

  // Calculate the difference in years and months
  let years = d2.diff(d1, "years");
  let months = d2.diff(d1, "months") % 12;

  // If months exceed or equal 12, convert them into an extra year
  if (months >= 11) {
    years += 1;
    months = 0;
  }

  return `${years} years and ${months} months`;
}

export function initializeCleverTap() {
  // Set up the clevertap object globally on the window
  window.clevertap = {
    event: [],
    profile: [],
    account: [],
    onUserLogin: [],
    region: "in1",
    notifications: [],
    privacy: [],
  };

  window.clevertap.account.push({
    id:
      import.meta.env.VITE_PROD === "YES"
        ? "W86-97R-4W7Z"
        : "TEST-RK8-865-6R7Z",
  });
  window.clevertap.privacy.push({ optOut: false });
  window.clevertap.privacy.push({ useIP: false });

  // Create the script element for CleverTap
  const wzrk = document.createElement("script");
  wzrk.type = "text/javascript";
  wzrk.async = true;
  wzrk.src =
    (document.location.protocol === "https:"
      ? "https://d2r1yp2w7bby2u.cloudfront.net"
      : "http://static.clevertap.com") + "/js/clevertap.min.js";

  // Wait for the script to load before pushing notifications
  wzrk.onload = () => {
    if (shouldShowNotification()) {
      window.clevertap.notifications.push({
        titleText: "Would you like to receive Push Notifications?",
        bodyText:
          "We promise to only send you relevant content and give you updates on your transactions",
        okButtonText: "Sign me up!",
        rejectButtonText: "No thanks",
        okButtonColor: "#F28046",
        askAgainTimeInSeconds: 5,
        serviceWorkerPath:
          "https://misp.heroinsurance.com/CP/App_Themes/clevertap_sw.js",
        okCallback: function () {
          saveNotificationResponse("accepted");
        },
        rejectCallback: function () {
          saveNotificationResponse("rejected");
        },
      });
    }
  };

  // Append the CleverTap script to the document
  const s = document.getElementsByTagName("script")[0];
  s.parentNode.insertBefore(wzrk, s);

  // Utility functions for handling notification response
  function shouldShowNotification() {
    return sessionStorage.getItem("notificationResponse") === null;
  }

  function saveNotificationResponse(response) {
    sessionStorage.setItem("notificationResponse", response);
  }
}

export const allowedClevertapRoutes = () => {
  let isRoutematch = [
    `/vehicle-details`,
    `/vehicle-type`,
    `/proposal-page`,
    `/quotes`,
    `/compare-quote`,
    `/registration`,
    `/payment-gateway`,
    "/payment-success",
    "/payment-failure",
  ].map((i) => (window.location.href.includes(i) ? true : false));
  return isRoutematch.includes(true);
};

export const isTokenExpired = (time) => {
  if (!time) return true;
  const tokenTime = moment(time);
  const currentTime = moment();
  return currentTime.diff(tokenTime, "minutes") >= 30;
};

export const getEpochFromDate = (dateStr) => {
  if (!dateStr) return null;

  const ddmmyyyyRegex = /^\d{2}-\d{2}-\d{4}$/;
  const date = ddmmyyyyRegex.test(dateStr)
    ? moment(dateStr, "DD-MM-YYYY", true)
    : moment(dateStr);

  return date.isValid() ? `$D_${date.valueOf()}` : null;
};

export const vahaanServicesName = [
  "ongrid",
  "adrila",
  "adrilla",
  "fastlane",
  "zoop",
  "adrilanew",
  "surepass",
  "signzy",
];