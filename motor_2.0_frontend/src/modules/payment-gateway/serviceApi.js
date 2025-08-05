import HttpClient from "../../api/httpClient";

// load
const loadOrder = (data) =>
  HttpClient("create-order", { method: "POST", data: data });
const saveOrder = (data) =>
  HttpClient("verify-payment", { method: "POST", data });

//payment
const payment = ({ data, typeRoute }) =>
  HttpClient(
    `/${typeRoute && typeRoute !== "cv" ? `${typeRoute}/` : ""}make-payment`,
    {
      method: "POST",
      data,
    },
    false,
    false,
    true
  );

//policy success details.
const policyGen = (data) =>
  HttpClient(`/getPolicyDetails`, { method: "POST", data });

const razorapi = (data, url) =>
  HttpClient(url, { method: "POST", data }, true, false, true);

export default { loadOrder, saveOrder, payment, policyGen, razorapi };
