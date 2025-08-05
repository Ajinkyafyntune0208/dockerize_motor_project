import { currencyFormater } from "utils";

export const getOdListBike = (quote, addOnsAndOthers) => {
  return {
    "Basic Own Damage(OD)": `₹ ${currencyFormater(
      quote?.basicPremium +
        (quote?.companyAlias === "icici_lombard"
          ? (quote?.underwritingLoadingAmount * 1 || 0) +
            (quote?.totalLoadingAmount * 1 || 0)
          : 0)
    )}`,
    ...(addOnsAndOthers?.selectedAccesories?.includes(
      "Electrical Accessories"
    ) && {
      "Electrical Accessories":
        quote?.motorElectricAccessoriesValue * 1
          ? `₹ ${currencyFormater(quote?.motorElectricAccessoriesValue * 1)}`
          : quote?.includedAdditional?.included?.includes(
              "motorElectricAccessoriesValue"
            )
          ? "Included"
          : "N/A",
    }),

    ...(addOnsAndOthers?.selectedAccesories?.includes(
      "Non-Electrical Accessories"
    ) && {
      "Non-Electrical Accessories":
        quote?.motorNonElectricAccessoriesValue * 1
          ? `₹ ${currencyFormater(quote?.motorNonElectricAccessoriesValue * 1)}`
          : quote?.includedAdditional?.included.includes(
              "motorNonElectricAccessoriesValue"
            )
          ? "Included"
          : "N/A",
    }),

    ...(addOnsAndOthers?.selectedDiscount?.includes(
      "Vehicle Limited to Own Premises"
    ) && {
      "Vehicle limited to own premises":
        quote?.limitedtoOwnPremisesOD * 1
          ? `- ₹ ${currencyFormater(quote?.limitedtoOwnPremisesOD * 1)}`
          : "N/A",
    }),

    ...(addOnsAndOthers?.selectedAdditions?.includes(
      "Geographical Extension"
    ) && {
      "Geographical Extension":
        quote?.geogExtensionODPremium * 1
          ? `₹ ${currencyFormater(quote?.geogExtensionODPremium)}`
          : "N/A",
    }),
    ...(quote?.showLoadingAmount &&
      (Number(quote?.totalLoadingAmount) > 0 ||
        Number(quote?.underwritingLoadingAmount)) && {
        "Total Loading Amount": `₹ ${currencyFormater(
          Number(quote?.totalLoadingAmount) ||
            Number(quote?.underwritingLoadingAmount)
        )}`,
      }),
  };
};
