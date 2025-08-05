export const firstCard = (newGroupedQuotesCompare) => {
  const uspDescriptions = [];

  for (let i = 0; i < 3; i++) {
    const uspDesc =
      newGroupedQuotesCompare[0]?.usp && newGroupedQuotesCompare[0]?.usp[i]
        ? newGroupedQuotesCompare[0]?.usp[i]?.usp_desc
        : "x";

    uspDescriptions.push(uspDesc);
  }

  return uspDescriptions;
};

export const secondCard = (newGroupedQuotesCompare) => {
  const uspDescriptions = [];

  for (let i = 0; i < 3; i++) {
    const uspDesc =
      newGroupedQuotesCompare[1]?.usp && newGroupedQuotesCompare[1]?.usp[i]
        ? newGroupedQuotesCompare[1]?.usp[i]?.usp_desc
        : "x";

    uspDescriptions.push(uspDesc);
  }

  return uspDescriptions;
};

export const thirdCard = (newGroupedQuotesCompare) => {
  const uspDescriptions = [];

  for (let i = 0; i < 3; i++) {
    const uspDesc =
      newGroupedQuotesCompare[2]?.usp && newGroupedQuotesCompare[2]?.usp[i]
        ? newGroupedQuotesCompare[2]?.usp[i]?.usp_desc
        : "x";

    uspDescriptions.push(uspDesc);
  }

  return uspDescriptions;
};
