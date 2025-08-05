import { TypeReturn } from "modules/type";
import { getDiscountList } from "./discount-list";
import { getDiscountListGCV } from "./discount-list-gcv";
import { getDiscountListBike } from "./discount-list-bike";
import { currencyFormater } from "utils";

export const calculateNCBDiscount = (revisedNcb) => {
  return currencyFormater(revisedNcb) !== 0
    ? `₹ ${currencyFormater(revisedNcb)}`
    : "N/A";
};

export const calculateAntiTheftDiscount = (quote) => {
  return quote?.antitheftDiscount * 1
    ? `₹ ${currencyFormater(quote?.antitheftDiscount)}`
    : "N/A";
};

export const discountObject = (discountObjectProps) => {
  const {
    revisedNcb,
    addOnsAndOthers,
    temp_data,
    quote,
    otherDiscounts,
    type,
    totalPremiumC,
  } = discountObjectProps;

  // get discount list
  // prettier-ignore
  const discountProps = { revisedNcb, addOnsAndOthers, temp_data, quote, otherDiscounts }
  let discountList = getDiscountList(discountProps);

  // get discount list for GCV
  let discountListGcv = getDiscountListGCV(discountProps);

  // get discount list for Bike
  let discountListBike = getDiscountListBike(discountProps);

  return {
    title: "Own Damage Discounts",
    list:
      TypeReturn(type) === "bike"
        ? discountListBike
        : temp_data?.journeyCategory === "GCV" ||
          temp_data?.journeyCategory === "MISC"
        ? discountListGcv
        : discountList,
    total: {
      "Total Discount (C)": `₹ ${currencyFormater(
        totalPremiumC - (quote?.tppdDiscount * 1 || 0)
      )}`,
    },
  };
};
