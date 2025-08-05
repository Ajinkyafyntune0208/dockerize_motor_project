import { TypeReturn } from "modules/type";
import { currencyFormater } from "utils";
import { getOdListBike } from "./od-list-bike";

export const odObject = (odObjectProps) => {
  const { quote, addOnsAndOthers, type, odLists, totalPremiumA } =
    odObjectProps;

  // od list bike
  let odListsBike = getOdListBike(quote, addOnsAndOthers);

  return {
    title: "Own Damage",
    list: TypeReturn(type) === "bike" ? odListsBike : odLists,
    total: {
      "Total OD Premium (A)": `₹ ${currencyFormater(
        totalPremiumA +
          (quote?.underwritingLoadingAmount * 1 || 0) +
          (quote?.totalLoadingAmount * 1 || 0)
      )}`,
    },
  };
};
