import { useEffect } from "react";
import _ from "lodash";
import { calculations } from "../../../calculations/ic-config/calculations-fallback";

export const useComprehensiveSorting = ({
  quoteComprehesiveGrouped,
  quotesLoadingComplted,
  quotesLoaded,
  addOnsAndOthers,
  type,
  temp_data,
  sortBy,
  setQuoteComprehesiveGrouped1,
  zdlp,
  zdlp_gdd,
}) => {
  useEffect(() => {
    if (quoteComprehesiveGrouped && quotesLoadingComplted && !quotesLoaded) {
      let sortedAndGrouped = calculations(
        quoteComprehesiveGrouped,
        quotesLoadingComplted,
        quotesLoaded,
        addOnsAndOthers,
        type,
        temp_data
      );
      //Sorting logic
      if (Number(sortBy) === 3) {
        // Check if the value of sortBy is 3
        // Sort by total payable amount with addon in descending order
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          // Check if the corporate vehicle quote request is a renewal
          if (!_.isEmpty(sortedAndGrouped)) {
            // Check if the sortedAndGrouped array is not empty
            let compQuotes = _.orderBy(
              // Create a new array compQuotes by filtering out renewal quotes from sortedAndGrouped and ordering by totalPayableAmountWithAddon in descending order
              sortedAndGrouped?.filter((el) => el?.isRenewal !== "Y"), // Filter out non-renewal quotes
              ["totalPayableAmountWithAddon"], // Sort by totalPayableAmountWithAddon
              ["desc"] // Sort in descending order
            );
            let compRenewalQuote = sortedAndGrouped?.filter(
              // Create a new array compRenewalQuote by filtering out only renewal quotes from sortedAndGrouped
              (el) => el?.isRenewal === "Y" // Filter for renewal quotes
            );
            let sortedcomp = [...compRenewalQuote, ...compQuotes]; // Combine compRenewalQuote and compQuotes into a new array sortedcomp
            setQuoteComprehesiveGrouped1(sortedcomp); // Update the state with the sorted array
          }
        } else {
          // If the corporate vehicle quote request is not a renewal
          setQuoteComprehesiveGrouped1(
            // Update the state by ordering the sortedAndGrouped array by totalPayableAmountWithAddon in descending order
            _.orderBy(
              sortedAndGrouped,
              ["totalPayableAmountWithAddon"],
              ["desc"]
            )
          );
        }
      } else if (Number(sortBy) === 4) {
        // Check if the value of sortBy is 4
        // Sort by idv in ascending order
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          // Check if the corporate vehicle quote request is a renewal
          if (!_.isEmpty(sortedAndGrouped)) {
            // Check if the sortedAndGrouped array is not empty
            let compQuotes = _.orderBy(
              // Create a new array compQuotes by filtering out renewal quotes from sortedAndGrouped and ordering by idv in ascending order
              sortedAndGrouped?.filter((el) => el?.isRenewal !== "Y"), // Filter out non-renewal quotes
              ["idv"], // Sort by idv
              ["asc"] // Sort in ascending order
            );
            let compRenewalQuote = sortedAndGrouped?.filter(
              // Create a new array compRenewalQuote by filtering out only renewal quotes from sortedAndGrouped
              (el) => el?.isRenewal === "Y" // Filter for renewal quotes
            );
            let sortedcomp = [...compRenewalQuote, ...compQuotes]; // Combine compRenewalQuote and compQuotes into a new array sortedcomp
            setQuoteComprehesiveGrouped1(sortedcomp); // Update the state with the sorted array
          }
        } else {
          // If the corporate vehicle quote request is not a renewal
          setQuoteComprehesiveGrouped1(
            // Update the state by ordering the sortedAndGrouped array by idv in ascending order
            _.orderBy(sortedAndGrouped, ["idv"], ["asc"])
          );
        }
      } else if (Number(sortBy) === 5) {
        // Check if the value of sortBy is 5
        // Sort by idv in descending order
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          // Check if the corporate vehicle quote request is a renewal
          if (!_.isEmpty(sortedAndGrouped)) {
            // Check if the sortedAndGrouped array is not empty
            let compQuotes = _.orderBy(
              // Create a new array compQuotes by filtering out renewal quotes from sortedAndGrouped and ordering by idv in descending order
              sortedAndGrouped?.filter((el) => el?.isRenewal !== "Y"), // Filter out non-renewal quotes
              ["idv"], // Sort by idv
              ["desc"] // Sort in descending order
            );
            let compRenewalQuote = sortedAndGrouped?.filter(
              // Create a new array compRenewalQuote by filtering out only renewal quotes from sortedAndGrouped
              (el) => el?.isRenewal === "Y" // Filter for renewal quotes
            );
            let sortedcomp = [...compRenewalQuote, ...compQuotes]; // Combine compRenewalQuote and compQuotes into a new array sortedcomp
            setQuoteComprehesiveGrouped1(sortedcomp); // Update the state with the sorted array
          }
        } else {
          // If the corporate vehicle quote request is not a renewal
          setQuoteComprehesiveGrouped1(
            // Update the state by ordering the sortedAndGrouped array by idv in descending order
            _.orderBy(sortedAndGrouped, ["idv"], ["desc"])
          );
        }
      } else {
        // If sortBy is not 3, 4, or 5
        // Sort by total payable amount with addon in ascending order
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          // Check if the corporate vehicle quote request is a renewal
          if (!_.isEmpty(sortedAndGrouped)) {
            // Check if the sortedAndGrouped array is not empty
            let compQuotes = _.orderBy(
              // Create a new array compQuotes by filtering out renewal quotes from sortedAndGrouped and ordering by totalPayableAmountWithAddon in ascending order
              sortedAndGrouped?.filter((el) => el?.isRenewal !== "Y"), // Filter out non-renewal quotes
              ["totalPayableAmountWithAddon"], // Sort by totalPayableAmountWithAddon
              ["asc"] // Sort in ascending order
            );
            let compRenewalQuote = sortedAndGrouped?.filter(
              // Create a new array compRenewalQuote by filtering out only renewal quotes from sortedAndGrouped
              (el) => el?.isRenewal === "Y" // Filter for renewal quotes
            );
            let sortedcomp = [...compRenewalQuote, ...compQuotes]; // Combine compRenewalQuote and compQuotes into a new array sortedcomp
            setQuoteComprehesiveGrouped1(sortedcomp); // Update the state with the sorted array
          }
        } else {
          // If the corporate vehicle quote request is not a renewal
          setQuoteComprehesiveGrouped1(
            // Update the state by ordering the sortedAndGrouped array by totalPayableAmountWithAddon in ascending order
            _.orderBy(
              sortedAndGrouped,
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
    quoteComprehesiveGrouped,
    quotesLoadingComplted,
    quotesLoaded,
    sortBy,
    zdlp,
    zdlp_gdd,
  ]);
};
