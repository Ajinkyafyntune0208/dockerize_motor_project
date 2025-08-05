import { useEffect } from "react";
import { relevance } from "modules/quotesPage/quote-logic";
import {
  GetValidAdditionalKeys,
  _filterTpTenure,
} from "modules/quotesPage/quote-logic";
import _ from "lodash";

export const useThirdPartySorting = ({
  quotetThirdParty,
  quotesLoadingComplted,
  quotesLoaded,
  isRelevant,
  addOnsAndOthers,
  temp_data,
  longtermParams,
  setQuoteTpGrouped1,
  sortBy
}) => {
  useEffect(() => {
    if (quotetThirdParty && quotesLoadingComplted && !quotesLoaded) {
      //Tp Sorting
      let relevantTp = isRelevant
        ? relevance(
            quotetThirdParty,
            addOnsAndOthers,
            GetValidAdditionalKeys,
            true,
            temp_data?.ownerTypeId === 2
          )
        : quotetThirdParty;
      //plan filtering using tenure
      relevantTp = _filterTpTenure(relevantTp, longtermParams);

      let restructTp = !_.isEmpty(relevantTp)
        ? relevantTp?.map((el) => ({
            ...el,
            finalPremWithCpa:
              (el?.finalPayableAmount * 1 ? el?.finalPayableAmount * 1 : 0) +
              (addOnsAndOthers?.selectedCpa?.includes(
                "Compulsory Personal Accident"
              )
                ? !_.isEmpty(addOnsAndOthers?.isTenure)
                  ? el?.multiYearCpa * 1
                    ? el?.multiYearCpa * 1
                    : 0
                  : el?.compulsoryPaOwnDriver * 1
                  ? el?.compulsoryPaOwnDriver * 1
                  : 0
                : 0),
          }))
        : [];

      // Sorting Logic
      if (Number(sortBy) === 3) {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(restructTp)) {
            let tpQuotes = _.orderBy(
              restructTp?.filter((el) => el?.isRenewal !== "Y"),
              ["finalPremWithCpa"],
              ["desc"]
            );
            let tpRenewalQuote = quotetThirdParty?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedTp = [...tpRenewalQuote, ...tpQuotes];
            setQuoteTpGrouped1(sortedTp);
          }
        } else {
          setQuoteTpGrouped1(
            _.orderBy(restructTp, ["finalPremWithCpa"], ["desc"])
          );
        }
      } else {
        if (temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y") {
          if (!_.isEmpty(restructTp)) {
            let tpQuotes = _.orderBy(
              restructTp?.filter((el) => el?.isRenewal !== "Y"),
              ["finalPremWithCpa"],
              ["asc"]
            );
            let tpRenewalQuote = restructTp?.filter(
              (el) => el?.isRenewal === "Y"
            );
            let sortedTp = [...tpRenewalQuote, ...tpQuotes];
            setQuoteTpGrouped1(sortedTp);
          }
        } else {
          setQuoteTpGrouped1(
            _.orderBy(restructTp, ["finalPremWithCpa"], ["asc"])
          );
        }
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    addOnsAndOthers?.selectedAddons,
    quotetThirdParty,
    quotesLoadingComplted,
    quotesLoaded,
    sortBy,
    isRelevant,
  ]);
};
