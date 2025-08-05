import { currencyFormater } from "utils";
import { calculateAntiTheftDiscount, calculateNCBDiscount } from "./index";

export const getDiscountListBike = (discountProps) => {
  const { revisedNcb, addOnsAndOthers, temp_data, quote, otherDiscounts } =
    discountProps;
  return {
    "Deduction of NCB": calculateNCBDiscount(revisedNcb),
    ...(addOnsAndOthers?.selectedDiscount?.includes(
      "Is the vehicle fitted with ARAI approved anti-theft device?"
    ) &&
      temp_data?.tab !== "tab2" && {
        "Anti-Theft": calculateAntiTheftDiscount(quote),
      }),
    ...(temp_data?.journeyCategory !== "GCV" &&
      addOnsAndOthers?.selectedDiscount?.includes("Voluntary Discounts") && {
        "Voluntary Deductible":
          quote?.voluntaryExcess * 1
            ? `₹ ${currencyFormater(quote?.voluntaryExcess)}`
            : "N/A",
      }),
    ...(addOnsAndOthers?.selectedDiscount?.includes(
      "Automobile Association of India Discount"
    ) && {
      "Automobile Association of India":
        quote?.aaiDiscount * 1
          ? `₹ ${currencyFormater(quote?.aaiDiscount)}`
          : "N/A",
    }),
    ...(otherDiscounts * 1 && {
      "Other Discounts": `₹ ${currencyFormater(otherDiscounts) || 0}`,
    }),
  };
};
