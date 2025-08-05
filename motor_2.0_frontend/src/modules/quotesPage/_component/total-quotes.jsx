import React from "react";
import { FilterTopBoxTitle } from "../quotesStyle";
import CustomTooltip from "components/tooltip/CustomTooltip";
import tooltip from "../../../assets/img/tooltip.svg";
import { renewalOnly } from "../quote-logic";

export const QuotesLength = ({
  compare,
  tab,
  shortTerm3,
  quoteShortTerm3,
  shortTerm6,
  quoteShortTerm6,
  quoteComprehesiveGrouped,
  quotetThirdParty,
  isRelevant,
  renewalFilter,
  temp_data,
  quotesLoaded,
  quotesList,
  finalErrorTp,
  filterErrorComp
}) => {

  const isRenewalEnabled =
    temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" &&
    !temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
    import.meta.env.VITE_BROKER === "BAJAJ" &&
    !(
      import.meta.env.VITE_BROKER === "BAJAJ" &&
      import.meta.env.VITE_BASENAME === "general-insurance"
    ) &&
    renewalFilter;
    
  const multiFilter = isRelevant && isRenewalEnabled;
  const isFilterEnabled = isRelevant || isRenewalEnabled;

  const getCount = () => {
    let quoteArray =
      tab === "tab1"
        ? shortTerm3
          ? quoteShortTerm3
          : shortTerm6
          ? quoteShortTerm6
          : quoteComprehesiveGrouped
        : quotetThirdParty;
    return (
      //Renewal Filter
      (isRenewalEnabled ? renewalOnly(quoteArray) : quoteArray)?.length
    );
  };

  const total =
    (quotesList?.third_party ? quotesList?.third_party?.length : 0) +
    (quotesList?.comprehensive ? quotesList?.comprehensive?.length : 0) +
    (quotesList?.short_term ? quotesList?.short_term?.length : 0);

  const isLoading = quotesLoaded < total;
  const isLoadingInititated = total > 0 && quotesLoaded === total;
  const displayCount = ((!isLoadingInititated && !isLoading && !getCount()) || (getCount() < 1 ));

  return (
    <FilterTopBoxTitle
      compare={compare}
      align={"center"}
      exp={true}
      show={displayCount}
    >
      <span className="quoteLen">{getCount()}</span>{" "}
      <span className="foundMessageQuote ml-1">Quotes Found</span>{" "}
      {isFilterEnabled && (
        <span className="mx-1">
          <CustomTooltip
            id="filterInfo__Tooltipvol_m"
            place={"right"}
            customClassName="mt-3"
            allowClick
          >
            <img
              style={{ marginBottom: "2.5px" }}
              data-tip={`<h3>Quotes Filtered</h3> <div>Filtered using ${
                multiFilter
                  ? "Best Match & Renewal"
                  : isRelevant
                  ? "Best Match"
                  : "Renewal"
              } criteria</div>`}
              data-html={true}
              data-for="filterInfo__Tooltipvol_m"
              src={tooltip}
              alt="tooltip"
            />
          </CustomTooltip>
        </span>
      )}
    </FilterTopBoxTitle>
  );
};
