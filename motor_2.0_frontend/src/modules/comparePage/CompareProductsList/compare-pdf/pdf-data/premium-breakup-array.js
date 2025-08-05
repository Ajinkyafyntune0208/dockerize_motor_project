import { currencyFormater } from "utils";

export const getPremiumBreakupArray = (premiumBreakupProps) => {
  const { temp_data, newGroupedQuotesCompare } = premiumBreakupProps;
  let premiumBreakupArray = [];
  premiumBreakupArray.push([
    "Own Damage Premium",
    "Third Party Premium",
    "Add On Premium",
    `Total Discount (NCB ${temp_data?.newNcb} Incl.)`,
    `GST`,
    "Gross Premium (incl. GST)",
  ]);
  premiumBreakupArray.push([
    `₹ ${currencyFormater(newGroupedQuotesCompare[0]?.totalPremiumA1 * 1)}`,
    `₹ ${currencyFormater(
      newGroupedQuotesCompare[0]?.totalPremiumB1 -
        (newGroupedQuotesCompare[0]?.tppdDiscount * 1 || 0)
    )}`,
    `₹ ${currencyFormater(newGroupedQuotesCompare[0]?.totalAddon1)}`,
    `₹ ${currencyFormater(newGroupedQuotesCompare[0]?.totalPremiumc1)}`,
    `₹ ${currencyFormater(newGroupedQuotesCompare[0]?.gst1)}`,
    `₹ ${currencyFormater(newGroupedQuotesCompare[0]?.finalPremium1)}`,
  ]);
  premiumBreakupArray.push([
    `₹ ${currencyFormater(newGroupedQuotesCompare[1]?.totalPremiumA1 * 1)}`,
    `₹ ${currencyFormater(
      newGroupedQuotesCompare[1]?.totalPremiumB1 -
        (newGroupedQuotesCompare[1]?.tppdDiscount * 1 || 0)
    )}`,
    `₹ ${currencyFormater(newGroupedQuotesCompare[1]?.totalAddon1)}`,
    `₹ ${currencyFormater(newGroupedQuotesCompare[1]?.totalPremiumc1)}`,
    `₹ ${currencyFormater(newGroupedQuotesCompare[1]?.gst1)}`,
    `₹ ${currencyFormater(newGroupedQuotesCompare[1]?.finalPremium1)}`,
  ]);
  if (Number(newGroupedQuotesCompare[2]?.idv) > 0) {
    premiumBreakupArray.push([
      `₹ ${currencyFormater(newGroupedQuotesCompare[2]?.totalPremiumA1 * 1)}`,
      `₹ ${currencyFormater(
        newGroupedQuotesCompare[2]?.totalPremiumB1 -
          (newGroupedQuotesCompare[2]?.tppdDiscount * 1 || 0)
      )}`,
      `₹ ${currencyFormater(newGroupedQuotesCompare[2]?.totalAddon1)}`,
      `₹ ${currencyFormater(newGroupedQuotesCompare[2]?.totalPremiumc1)}`,
      `₹ ${currencyFormater(newGroupedQuotesCompare[2]?.gst1)}`,
      `₹ ${currencyFormater(newGroupedQuotesCompare[2]?.finalPremium1)}`,
    ]);
  }
  return premiumBreakupArray;
};
