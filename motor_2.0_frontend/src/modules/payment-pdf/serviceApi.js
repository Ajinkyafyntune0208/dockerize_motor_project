import HttpClient from "api/httpClient";

//Generate payment Pdf
const submit = (data) => {
  return HttpClient(`${data.url}`, { method: "POST", data }, true);
};
export default { submit };
