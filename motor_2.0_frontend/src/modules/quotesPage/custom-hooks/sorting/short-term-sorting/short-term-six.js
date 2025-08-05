import { useEffect } from "react";
import _ from "lodash";
import { _discount } from "modules/quotesPage/quote-logic";
import { calculations } from "modules/quotesPage/calculations/ic-config/calculations-fallback";

export const useShortTerm6Sorting = ({
  quoteShortTerm,
  quotesLoadingComplted,
  quotesLoaded,
  groupedQuoteShortTerm6,
  addOnsAndOthers,
  sortBy,
  isRelevant,
  longTerm2,
  longTerm3,
  temp_data,
  type,
  setQuoteShortTerm6,
}) => {
  useEffect(() => {
    if (quoteShortTerm && quotesLoadingComplted && !quotesLoaded) {
      //Short Term 6 Sorting
      let sortedShortTerm6 = calculations(
        groupedQuoteShortTerm6,
        quotesLoadingComplted,
        quotesLoaded,
        addOnsAndOthers,
        type,
        temp_data
      );
      // Sorting Logic
      if (Number(sortBy) === 5) {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(sortedShortTerm6)) {
            let sh6Quotes = _.orderBy(
              sortedShortTerm6?.filter((el) => el?.isRenewal !== "Y"),
              ["idv"],
              ["desc"]
            );
            let sh6RenewalQuote = sortedShortTerm6?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedsh6 = [...sh6RenewalQuote, ...sh6Quotes];
            setQuoteShortTerm6(sortedsh6);
          }
        } else {
          setQuoteShortTerm6(_.orderBy(sortedShortTerm6, ["idv"], ["desc"]));
        }
      } else if (Number(sortBy) === 4) {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(sortedShortTerm6)) {
            let sh6Quotes = _.orderBy(
              sortedShortTerm6?.filter((el) => el?.isRenewal !== "Y"),
              ["idv"],
              ["asc"]
            );
            let sh6RenewalQuote = sortedShortTerm6?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedsh6 = [...sh6RenewalQuote, ...sh6Quotes];
            setQuoteShortTerm6(sortedsh6);
          }
        } else {
          setQuoteShortTerm6(_.orderBy(sortedShortTerm6, ["idv"], ["asc"]));
        }
      } else if (Number(sortBy) === 3) {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(sortedShortTerm6)) {
            let sh6Quotes = _.orderBy(
              sortedShortTerm6?.filter((el) => el?.isRenewal !== "Y"),
              ["totalPayableAmountWithAddon"],
              ["desc"]
            );
            let sh6RenewalQuote = sortedShortTerm6?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedsh6 = [...sh6RenewalQuote, ...sh6Quotes];
            setQuoteShortTerm6(sortedsh6);
          }
        } else {
          setQuoteShortTerm6(
            _.orderBy(
              sortedShortTerm6,
              ["totalPayableAmountWithAddon"],
              ["desc"]
            )
          );
        }
      } else {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(sortedShortTerm6)) {
            let sh6Quotes = _.orderBy(
              sortedShortTerm6?.filter((el) => el?.isRenewal !== "Y"),
              ["totalPayableAmountWithAddon"],
              ["asc"]
            );
            let sh6RenewalQuote = sortedShortTerm6?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedsh6 = [...sh6RenewalQuote, ...sh6Quotes];
            setQuoteShortTerm6(sortedsh6);
          }
        } else {
          setQuoteShortTerm6(
            _.orderBy(
              sortedShortTerm6,
              ["totalPayableAmountWithAddon"],
              ["asc"]
            )
          );
        }
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    addOnsAndOthers?.selectedAddons,
    quotesLoadingComplted,
    quoteShortTerm,
    quotesLoaded,
    sortBy,
    isRelevant,
    groupedQuoteShortTerm6,
    longTerm2,
    longTerm3,
  ]);
};
