import { useEffect } from "react";
import _ from "lodash";
import { _discount } from "modules/quotesPage/quote-logic";
import { calculations } from "modules/quotesPage/calculations/ic-config/calculations-fallback";

export const useShortTerm3Sorting = ({
  quoteShortTerm,
  quotesLoadingComplted,
  quotesLoaded,
  groupedQuoteShortTerm3,
  addOnsAndOthers,
  sortBy,
  isRelevant,
  longTerm2,
  longTerm3,
  temp_data,
  type,
  setQuoteShortTerm3,
}) => {
  useEffect(() => {
    if (quoteShortTerm && quotesLoadingComplted && !quotesLoaded) {
      //Short term 3 sorting
      let sortedShortTerm3 = calculations(
        groupedQuoteShortTerm3,
        quotesLoadingComplted,
        quotesLoaded,
        addOnsAndOthers,
        type,
        temp_data
      );

      // Sorting Logic
      if (Number(sortBy) === 5) {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(sortedShortTerm3)) {
            let sh3Quotes = _.orderBy(
              sortedShortTerm3?.filter((el) => el?.isRenewal !== "Y"),
              ["idv"],
              ["desc"]
            );
            let sh3RenewalQuote = sortedShortTerm3?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedsh3 = [...sh3RenewalQuote, ...sh3Quotes];
            setQuoteShortTerm3(sortedsh3);
          }
        } else {
          setQuoteShortTerm3(_.orderBy(sortedShortTerm3, ["idv"], ["desc"]));
        }
      } else if (Number(sortBy) === 4) {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(sortedShortTerm3)) {
            let sh3Quotes = _.orderBy(
              sortedShortTerm3?.filter((el) => el?.isRenewal !== "Y"),
              ["idv"],
              ["asc"]
            );
            let sh3RenewalQuote = sortedShortTerm3?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedsh3 = [...sh3RenewalQuote, ...sh3Quotes];
            setQuoteShortTerm3(sortedsh3);
          }
        } else {
          setQuoteShortTerm3(_.orderBy(sortedShortTerm3, ["idv"], ["asc"]));
        }
      } else if (Number(sortBy) === 3) {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(sortedShortTerm3)) {
            let sh3Quotes = _.orderBy(
              sortedShortTerm3?.filter((el) => el?.isRenewal !== "Y"),
              ["totalPayableAmountWithAddon"],
              ["desc"]
            );
            let sh3RenewalQuote = sortedShortTerm3?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedsh3 = [...sh3RenewalQuote, ...sh3Quotes];
            setQuoteShortTerm3(sortedsh3);
          }
        } else {
          setQuoteShortTerm3(
            _.orderBy(
              sortedShortTerm3,
              ["totalPayableAmountWithAddon"],
              ["desc"]
            )
          );
        }
      } else {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(sortedShortTerm3)) {
            let sh3Quotes = _.orderBy(
              sortedShortTerm3?.filter((el) => el?.isRenewal !== "Y"),
              ["totalPayableAmountWithAddon"],
              ["asc"]
            );
            let sh3RenewalQuote = sortedShortTerm3?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedsh3 = [...sh3RenewalQuote, ...sh3Quotes];
            setQuoteShortTerm3(sortedsh3);
          }
        } else {
          setQuoteShortTerm3(
            _.orderBy(
              sortedShortTerm3,
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
    groupedQuoteShortTerm3,
    longTerm2,
    longTerm3,
  ]);
};
