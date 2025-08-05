import { firstCard, secondCard, thirdCard } from "./index";

export const getUspArray = (newGroupedQuotesCompare) => {
  const uspList = [];

  uspList.push(firstCard(newGroupedQuotesCompare));
  uspList.push(secondCard(newGroupedQuotesCompare));
  uspList.push(thirdCard(newGroupedQuotesCompare));

  return uspList;
};
