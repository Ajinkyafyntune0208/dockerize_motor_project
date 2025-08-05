//version
export const getVersionLabel = (
  temp_data,
  type,
  fuelFype,
  kw,
  cubicCapacity,
  grosssVehicleWeight,
  versionName,
  vehicleBuiltUp
) => {

  const isNotGCV = temp_data?.parent?.productSubTypeCode !== "GCV";

  const commonPart = `${versionName}${type === "bike" ? ` (${fuelFype})` : ""}${
    vehicleBuiltUp ? `${" (" + vehicleBuiltUp}` + ")" : ""
  }`;
  const electricPart =
    fuelFype === "ELECTRIC" && type !== "bike" ? (kw ? ` - ${kw}kW` : "") : "";
  const capacityPart =
    cubicCapacity && type !== "bike" ? ` - ${cubicCapacity}CC` : "";
  const weightPart =
    grosssVehicleWeight && type !== "bike"
      ? ` - ${grosssVehicleWeight}GVW`
      : "";

  const result = isNotGCV
    ? `${commonPart}${electricPart || capacityPart}`
    : `${commonPart}${electricPart || weightPart}`;

  return result;
};
