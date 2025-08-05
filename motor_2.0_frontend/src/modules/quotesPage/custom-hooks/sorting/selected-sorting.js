import { useEffect, useState } from "react";
import { useDispatch } from "react-redux";
import { UpdateQuotesData } from "modules/quotesPage/quote.slice";

export const useSelectedSorting = ({
  temp_data,
  quoteComprehesive,
  quotetThirdParty,
  quoteShortTerm,
  quotesLoaded,
  watch,
  enquiry_id,
}) => {
  //sort type state
  const [sortBy, setSortBy] = useState(
    temp_data?.corporateVehiclesQuoteRequest?.sortBy
      ? temp_data?.corporateVehiclesQuoteRequest?.sortBy
      : "2"
  );

  const dispatch = useDispatch();
  const sortByData = watch("sort-by");
  useEffect(() => {
    setSortBy(
      temp_data?.corporateVehiclesQuoteRequest?.sortBy || sortByData?.id
    );
  }, [sortByData]);

  const sortingData = {
    enquiryId: temp_data?.enquiry_id || enquiry_id,
    sortBy: sortBy,
  };

  useEffect(() => {
    if (
      ((quoteComprehesive && quoteComprehesive.length >= 1) ||
        (quotetThirdParty && quotetThirdParty.length >= 1) ||
        (quoteShortTerm && quoteShortTerm.length >= 1)) &&
      !quotesLoaded &&
      sortBy
    ) {
      dispatch(UpdateQuotesData(sortingData, "Y"));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sortBy]);

  return {
    sortBy,
    setSortBy,
  };
};
