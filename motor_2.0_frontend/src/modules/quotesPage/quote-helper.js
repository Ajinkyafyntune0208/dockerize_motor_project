import { setQuotesList, clear } from "./quote.slice";
import { setTempData } from "./filterConatiner/quoteFilter.slice";
import { set_temp_data } from "modules/Home/home.slice";

export const NoOfDays = () => {
  return 1;
};

export const extPath = `${
  import.meta.env.VITE_BASENAME !== "NA"
    ? `/${import.meta.env.VITE_BASENAME}`
    : ""
}`;

export const handleViewExt = (view, setView) => {
  if (view === "grid") {
    setView("grid");
  } else {
    setView("list");
  }
  localStorage.setItem("view", view);
};

export const backExt = (
  dispatch,
  history,
  type,
  token,
  enquiry_id,
  typeId,
  journey_type,
  _stToken,
  shared
) => {
  history.push(
    `/${type}/vehicle-details?enquiry_id=${enquiry_id}${
      token ? `&xutm=${token}` : ``
    }${typeId ? `&typeid=${typeId}` : ``}${
      journey_type ? `&journey_type=${journey_type}` : ``
    }${_stToken ? `&stToken=${_stToken}` : ``}${
      shared ? `&shared=${shared}` : ``
    }`
  );
  dispatch(setQuotesList([]));
  dispatch(clear());
  dispatch(
    setTempData({
      policyType: false,
    })
  );
  dispatch(
    set_temp_data({
      newCar: false,
      breakIn: false,
      leadJourneyEnd: false,
    })
  );
};

export const getAddons = (quote, destructureType) => {
  let destructurePoint = destructureType ? `inBuilt` : `additional`;
  let addonArray = quote?.addOnsData?.[`${destructurePoint}`];
  let addons = addonArray ? Object.keys(addonArray) : [];

  return addons;
};

export const switchError = (code, error) => {
  switch (code) {
    case "businessType:rollover.third_party":
      return "The quotation for this Insurance Company is blocked.";
    case "businessType:rollover.comprehensive":
      return "The quotation for this Insurance Company is blocked.";
    default:
      return error;
  }
};

export const addonConfigbrokers =
  ["ACE", "HEROCARE"].includes(import.meta.env.VITE_BROKER) ||
  import.meta.env.VITE_BROKER === "KAROINSURE" || 
  import.meta.env.VITE_BROKER === "INSTANTBEEMA"
  ;
