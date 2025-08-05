import _, { parseInt } from "lodash";
export const getIDV = (idvType) => {
  switch (idvType) {
    case "avgIdv":
      return getAverageIdv();
    case "lowIdv":
      return getLowestIdv();
    case "highIdv":
      return getHighestIdv();
    default:
      return "0";
  }
};
export const getLowestIdv = (quote) => {
  let Min = _.minBy(quote, "minIdv");
  return parseInt(Min?.minIdv);
};

export const getHighestIdv = (quote) => {
  let Max = _.maxBy(quote, "maxIdv");
  return parseInt(Max?.maxIdv);
};

export const getAverageIdv = (quote) => {
  let filteredQuote = quote?.map((item) =>
    Number(item?.idv) ? Number(item?.idv) : 0
  );
  let newFilterQuote = filteredQuote.filter((cv) => cv != 0);

  let Avg = _.meanBy(newFilterQuote);

  return parseInt(Avg);
};
