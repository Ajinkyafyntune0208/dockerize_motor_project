import _ from "lodash";

export const bike = _.compact([
  import.meta.env?.VITE_BROKER === "ACE" ||
  import.meta.env?.VITE_BROKER === "OLA"
    ? "godigit"
    : false,
  import.meta.env?.VITE_BROKER === "OLA" ? "acko" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "reliance" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "iffco_tokio" : false,
  import.meta.env?.VITE_BROKER === "BAJAJ" ? "godigit" : false,
]);
export const bikeTp = _.compact([
  import.meta.env?.VITE_BROKER === "ACE" ||
  import.meta.env?.VITE_BROKER === "OLA"
  ? "godigit"
    : false,
  import.meta.env?.VITE_BROKER === "OLA" ? "acko" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "reliance" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "iffco_tokio" : false,
  import.meta.env?.VITE_BROKER === "BAJAJ" ? "godigit" : false,
]);
export const car = _.compact([
  import.meta.env?.VITE_BROKER === "ACE" ||
  import.meta.env?.VITE_BROKER === "OLA"
    ? "godigit"
    : false,
  import.meta.env?.VITE_BROKER === "OLA" ? "acko" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "tata_aig" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "reliance" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "iffco_tokio" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "reliance" : false,
  import.meta.env?.VITE_BROKER === "BAJAJ" ? "icici_lombard" : false,
  import.meta.env?.VITE_BROKER === "BAJAJ" ? "reliance" : false,
  import.meta.env?.VITE_BROKER === "BAJAJ" ? "royal_sundaram" : false,
]);
export const carTp = _.compact([
  import.meta.env?.VITE_BROKER === "ACE" ||
  import.meta.env?.VITE_BROKER === "OLA"
    ? "godigit"
    : false,
  import.meta.env?.VITE_BROKER === "OLA" ? "acko" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "tata_aig" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "reliance" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "iffco_tokio" : false,
  import.meta.env?.VITE_BROKER === "BAJAJ" ? "icici_lombard" : false,
  import.meta.env?.VITE_BROKER === "BAJAJ" ? "reliance" : false,
  import.meta.env?.VITE_BROKER === "BAJAJ" ? "royal_sundaram" : false,

]);
export const cv12 = _.compact([
  import.meta.env?.VITE_BROKER === "ACE" ||
  import.meta.env?.VITE_BROKER === "OLA"
    ? "godigit"
    : false,
  import.meta.env?.VITE_BROKER === "OLA" ? "acko" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "tata_aig" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "reliance" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "iffco_tokio" : false,
]);
export const cvTp = _.compact([
  import.meta.env?.VITE_BROKER === "ACE" ||
  import.meta.env?.VITE_BROKER === "OLA"
    ? "godigit"
    : false,
  import.meta.env?.VITE_BROKER === "OLA" ? "acko" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "tata_aig" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "reliance" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "iffco_tokio" : false,
]);
export const cv6 = _.compact([
  import.meta.env?.VITE_BROKER === "ACE" ||
  import.meta.env?.VITE_BROKER === "OLA"
    ? "godigit"
    : false,
  import.meta.env?.VITE_BROKER === "OLA" ? "acko" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "iffco_tokio" : false,
  import.meta.env?.VITE_BROKER === "ACE" || import.meta.env?.VITE_BROKER === "HEROCARE" ? "tata_aig" : false,
]);
export const cv3 = _.compact([
  import.meta.env?.VITE_BROKER === "ACE" ||
  import.meta.env?.VITE_BROKER === "OLA"
    ? "godigit"
    : false,
  import.meta.env?.VITE_BROKER === "OLA" ? "acko" : false,
  import.meta.env?.VITE_BROKER === "ACE" ? "iffco_tokio" : false,
  import.meta.env?.VITE_BROKER === "ACE" || import.meta.env?.VITE_BROKER === "HEROCARE" ? "tata_aig" : false,
]);
