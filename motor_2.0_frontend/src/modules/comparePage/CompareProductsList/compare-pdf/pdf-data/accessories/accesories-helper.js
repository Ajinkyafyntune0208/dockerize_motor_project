import { currencyFormater } from "utils";

export const accessory = (name, addOnsAndOthers, includeValue) => {
  const value = includeValue ? includeValue : name;
  const accessoryValue =
    value === "Electrical Accessories"
      ? addOnsAndOthers?.vehicleElectricAccessories
      : value === "Non Electrical Accessories"
      ? addOnsAndOthers?.vehicleNonElectricAccessories
      : value === "External Bi-Fuel Kit CNG/LPG"
      ? addOnsAndOthers?.externalBiFuelKit
      : value === "Trailer"
      ? addOnsAndOthers?.trailerCover
      : "";

  return `${name} ${
    addOnsAndOthers?.selectedAccesories?.includes(value)
      ? `(${accessoryValue})`
      : ""
  }`;
};

export const generateAccessoryValue = (
  accessoryName,
  selectedAccessories,
  newValue,
  companyAlias
) => {
  if (
    !selectedAccessories?.includes(accessoryName) ||
    (accessoryName === "External Bi-Fuel Kit CNG/LPG" &&
      !newValue &&
      newValue !== 0)
  ) {
    return "Not Selected";
  }

  if (Number(newValue) !== 0) {
    return `₹ ${currencyFormater(newValue)}`;
  }

  // if (companyAlias === "godigit") {
  //   return "Included";
  // }

  return "Not Available";
};
