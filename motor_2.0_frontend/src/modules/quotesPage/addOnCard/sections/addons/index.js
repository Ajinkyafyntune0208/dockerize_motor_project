// prettier-ignore
export const isAccessoriesEmpty = (accesories, ElectricAmount, NonElectricAmount, ExternalAmount) => {
    return (
      (accesories?.includes("Electrical Accessories") &&
        (!ElectricAmount || ElectricAmount * 1 === 0)) ||
      (accesories?.includes("Non-Electrical Accessories") &&
        (!NonElectricAmount || NonElectricAmount * 1 === 0)) ||
      (accesories?.includes("External Bi-Fuel Kit CNG/LPG") &&
        (!ExternalAmount || ExternalAmount * 1 === 0))
    );
  };

export const getAccessoriesData = (accessoriesProps) => {
  // prettier-ignore
  const { selectedAccesories, ElectricAmount, NonElectricAmount, ExternalAmount, TrailerAmount } = accessoriesProps;

  return {
    selectedAccesories: selectedAccesories,
    vehicleElectricAccessories: selectedAccesories?.includes(
      "Electrical Accessories"
    )
      ? Number(ElectricAmount)
      : 0,
    vehicleNonElectricAccessories: selectedAccesories?.includes(
      "Non-Electrical Accessories"
    )
      ? Number(NonElectricAmount)
      : 0,
    externalBiFuelKit: selectedAccesories?.includes(
      "External Bi-Fuel Kit CNG/LPG"
    )
      ? Number(ExternalAmount)
      : 0,
    trailerCover: selectedAccesories?.includes("Trailer")
      ? Number(TrailerAmount)
      : 0,
  };
};

export const getNewSelectedAccessories = (newAccessoriesProps) => {
  // prettier-ignore
  const {
      selectedAccesories, temp_data, ExternalAmount, NonElectricAmount, ElectricAmount, TrailerAmount,
    } = newAccessoriesProps;

  let newSelectedAccesories = [];

  if (
    selectedAccesories?.includes("External Bi-Fuel Kit CNG/LPG") &&
    !["CNG", "LPG"].includes(temp_data?.fuel)
  ) {
    const newD = {
      name: "External Bi-Fuel Kit CNG/LPG",
      sumInsured: Number(ExternalAmount),
    };
    newSelectedAccesories.push(newD);
  }
  if (selectedAccesories?.includes("Non-Electrical Accessories")) {
    const newD = {
      name: "Non-Electrical Accessories",
      sumInsured: Number(NonElectricAmount),
    };
    newSelectedAccesories.push(newD);
  }
  if (selectedAccesories?.includes("Electrical Accessories")) {
    const newD = {
      name: "Electrical Accessories",
      sumInsured: Number(ElectricAmount),
    };
    newSelectedAccesories.push(newD);
  }
  if (selectedAccesories?.includes("Trailer")) {
    const newD = {
      name: "Trailer",
      sumInsured: Number(TrailerAmount),
    };
    newSelectedAccesories.push(newD);
  }
  return newSelectedAccesories;
};
