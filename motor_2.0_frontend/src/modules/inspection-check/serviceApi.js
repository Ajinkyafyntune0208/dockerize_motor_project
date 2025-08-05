import HttpClient from "api/httpClient";

//vehicle type
const vehicleType = (data) =>
	HttpClient("/getVehicleCategory", { method: "POST", data });

//vehicle type
const submit = (data) =>
	HttpClient("/inspectionConfirm", { method: "POST", data });

//previous insurer
const prevIc = (data) =>
	HttpClient(`/getBreakinCompany`, { method: "POST", data });

export default { vehicleType, submit, prevIc };
