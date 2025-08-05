import { currencyFormater } from "utils";
import { calculateAntiTheftDiscount, calculateNCBDiscount } from "./index";

export const getDiscountListGCV = (discountProps) => {
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
    ...(otherDiscounts * 1 && {
      "Other Discounts": `₹ ${currencyFormater(otherDiscounts) || 0}`,
    }),
  };
};
