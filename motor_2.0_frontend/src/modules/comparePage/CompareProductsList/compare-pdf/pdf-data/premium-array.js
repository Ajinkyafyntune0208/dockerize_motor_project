import { currencyFormater } from "utils";

export const getTotalPremiumArray = (newGroupedQuotesCompare) => {
  var totalPremiumArray = [];
  totalPremiumArray.push(
    `₹ ${currencyFormater(parseInt(newGroupedQuotesCompare[0]?.gst1))}`
  );
  totalPremiumArray.push(
    `₹ ${currencyFormater(parseInt(newGroupedQuotesCompare[1]?.gst1))}`
  );

  if (Number(newGroupedQuotesCompare[2]?.idv) > 0) {
    totalPremiumArray.push(
      `₹ ${currencyFormater(parseInt(newGroupedQuotesCompare[2]?.gst1))}`
    );
  }
  return totalPremiumArray;
};
