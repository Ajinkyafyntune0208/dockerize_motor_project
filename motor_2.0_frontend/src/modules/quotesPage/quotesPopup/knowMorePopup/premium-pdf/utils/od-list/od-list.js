import { TypeReturn } from "modules/type";
import { currencyFormater } from "utils";

export const getOdList = (odListProps) => {
  const { quote, addOnsAndOthers, type, temp_data } = odListProps || {};
  
  return {
    "Basic Own Damage(OD)": `₹ ${currencyFormater(
      quote?.basicPremium +
        (quote?.companyAlias === "icici_lombard"
          ? (quote?.underwritingLoadingAmount * 1 || 0) +
            (quote?.totalLoadingAmount * 1 || 0)
          : 0)
    )}`,
    ...((addOnsAndOthers?.selectedAccesories?.includes("Electrical Accessories") 
     || quote?.includedAdditional?.included?.includes("motorElectricAccessoriesValue"))
   && {
      "Electrical Accessories":
        quote?.motorElectricAccessoriesValue * 1
          ? `₹ ${currencyFormater(quote?.motorElectricAccessoriesValue * 1)}`
          : quote?.includedAdditional?.included?.includes(
              "motorElectricAccessoriesValue"
            )
          ? "Included"
          : "N/A",
    }),

    ...((addOnsAndOthers?.selectedAccesories?.includes("Non-Electrical Accessories")
     || quote?.includedAdditional?.included.includes("motorNonElectricAccessoriesValue"))
    && {
      "Non-Electrical Accessories":
        quote?.motorNonElectricAccessoriesValue * 1
          ? `₹ ${currencyFormater(quote?.motorNonElectricAccessoriesValue * 1)}`
          : quote?.includedAdditional?.included.includes(
              "motorNonElectricAccessoriesValue"
            )
          ? "Included"
          : "N/A",
    }),
    ...(TypeReturn(type) !== "bike" &&
      temp_data?.tab !== "tab2" &&
      (quote?.motorLpgCngKitValue * 1 ||
        quote?.includedAdditional?.included.includes(
          "motorLpgCngKitValue"
        )) && {
        "LPG/CNG Kit": `${
          quote?.motorLpgCngKitValue * 1
            ? `₹ ${currencyFormater(quote?.motorLpgCngKitValue * 1)}`
            : "Included"
        }`,
      }
      
    ),

    ...((addOnsAndOthers?.selectedDiscount?.includes("Vehicle Limited to Own Premises") 
       || quote?.includedAdditional?.included.includes("Vehicle Limited to Own Premises"))
     && {
      "Vehicle limited to own premises":
        quote?.limitedtoOwnPremisesOD * 1
          ? `- ₹ ${currencyFormater(quote?.limitedtoOwnPremisesOD * 1)}`
          : "N/A",
    }),
    ...((addOnsAndOthers?.selectedAdditions?.includes("Geographical Extension") 
    || quote?.includedAdditional?.included.includes("Geographical Extension"))
    && {
      "Geographical Extension":
        quote?.geogExtensionODPremium * 1
          ? `₹ ${currencyFormater(quote?.geogExtensionODPremium)}`
          : "N/A",
    }),
  };
};
