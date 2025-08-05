import React, { useState, useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import Product from "../Product/Product";
import "./compare-products-list.scss";
import _ from "lodash";
import { useLocation } from "react-router";
import {
  AddonConfig,
  addonConfig as clearAddonConfig,
  setValidQuotes,
} from "modules/quotesPage/quote.slice";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";
import { TopDiv } from "./CompareProductStyle";
import PropTypes from "prop-types";
import { Prefill, clear_temp_data } from "modules/Home/home.slice";
//prettier-ignore
import { _discount } from "modules/quotesPage/quote-logic";
import { usePdfCreate } from "./compare-pdf/pdf-hook";
import { useGrouping } from "modules/quotesPage/custom-hooks/quote-page-hooks/quote-page-hook";
import { calculations } from "modules/quotesPage/calculations/ic-config/calculations-fallback";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const CompareProductsList = (props) => {
  // prettier-ignore
  const {
    compareQuotes, type, setPrevPopup, prevPopup, setSelectedId, setSelectedCompanyName, quoteComprehesive,
    setSelectedIcId, setSelectedCompanyAlias, setApplicableAddonsLits, scrollPosition, zdlp, setClaimList, zdlp_gdd, setClaimList_gdd,
  } =props

  const lessThan767 = useMediaPredicate("(max-width: 767px)");
  const dispatch = useDispatch();
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const token = query.get("xutm");
  const { addOnsAndOthers, shortTerm, selectedTab, addonConfig } = useSelector(
    (state) => state.quotes
  );
  const { temp_data, theme_conf, gstStatus } = useSelector(
    (state) => state.home
  );

  /// grouping addon based for private car
  const [quoteComprehesiveGrouped, setQuoteComprehesiveGrouped] =
    useState(quoteComprehesive);
  const [quoteComprehesiveGrouped1, setQuoteComprehesiveGrouped1] = useState(
    []
  );

  const GetValidAdditionalKeys = (additional) => {
    var y = Object.entries(additional)
      .filter(([, v]) => Number(v) > 0)
      .map(([k]) => k);
    return y;
  };

  /* Grouping Logic
  List of operations: 
  All these are done for a normal quote and PAYD quote seperately
  a) Group By IC
  b) Fetch list of markers ( Zerodep claims )
  c) Get selected marker.
  */

  //Grouped params
  useGrouping(
    addOnsAndOthers,
    quoteComprehesive,
    setClaimList,
    setClaimList_gdd,
    zdlp,
    zdlp_gdd,
    setQuoteComprehesiveGrouped
  );

  useEffect(() => {
    if (quoteComprehesiveGrouped) {
      let sortedAndGrouped = calculations(
        quoteComprehesiveGrouped,
        true,
        false,
        addOnsAndOthers,
        type,
        temp_data
      );
      let sortedGroupedcomp1 = _.sortBy(sortedAndGrouped, [
        "totalPayableAmountWithAddon",
      ]);
      setQuoteComprehesiveGrouped1(sortedGroupedcomp1);
    }
  }, [
    addOnsAndOthers?.selectedAddons,
    quoteComprehesiveGrouped,
    addOnsAndOthers?.selectedCpa,
  ]);

  const [icLists, setICLists] = useState([]);
  useEffect(() => {
    if (compareQuotes) {
      setICLists(
        _.compact(
          compareQuotes?.map((x) =>
            x?.modifiedAlias ? x.modifiedAlias : x?.company_alias
          )
        )
      );
    }
  }, [compareQuotes]);

  //---------------------Prefill Api-----------------------
  //clear temp data
  // useEffect(() => {
  //   dispatch(clear_temp_data({}));
  // }, [])

  //Addon-config
  useEffect(() => {
    if (
      enquiry_id &&
      (["ACE", "RB"]?.includes(import.meta.env.VITE_BROKER) ||
        import.meta.env.VITE_BROKER === "KAROINSURE" || 
        import.meta.env.VITE_BROKER === "INSTANTBEEMA"
        )
    ) {
      dispatch(AddonConfig({ enquiryId: enquiry_id }));
    }
    //eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id]);

  useEffect(() => {
    if (
      enquiry_id &&
      (import.meta.env.VITE_BROKER === "ACE" ||
        import.meta.env.VITE_BROKER === "KAROINSURE" || 
        import.meta.env.VITE_BROKER === "INSTANTBEEMA") &&
      addonConfig
    ) {
      dispatch(Prefill({ enquiryId: enquiry_id }));
    }
    //clear config data
    return () => {
      dispatch(clearAddonConfig(null));
    };
    //eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id, addonConfig]);

  //without preselected addons
  useEffect(() => {
    if (
      enquiry_id &&
      import.meta.env.VITE_BROKER !== "ACE" &&
      import.meta.env.VITE_BROKER !== "KAROINSURE" && 
      import.meta.env.VITE_BROKER !== "INSTANTBEEMA"
    ) {
      dispatch(Prefill({ enquiryId: enquiry_id }));
    }
    //eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id]);

  const [newGroupedQuotesCompare, setNewGroupedQuotesCompare] =
    useState(compareQuotes);

  useEffect(() => {
    if (quoteComprehesiveGrouped1) {
      let FilteredGroupedByIc = quoteComprehesiveGrouped1?.filter(
        ({ modifiedAlias, company_alias }) =>
          modifiedAlias
            ? icLists?.includes(modifiedAlias)
            : icLists?.includes(company_alias)
      );
      let filteredExistingQuote = compareQuotes?.filter(
        ({ minIdv }) => minIdv === 1
      );
      setNewGroupedQuotesCompare(
        _.concat(FilteredGroupedByIc, filteredExistingQuote)
      );
    }
  }, [quoteComprehesiveGrouped1, compareQuotes, icLists]);

  const [validQuote, setValidQuote] = useState(false);
  useEffect(() => {
    if (newGroupedQuotesCompare) {
      let validQuote = !_.isEmpty(newGroupedQuotesCompare)
        ? _.compact(
            newGroupedQuotesCompare?.map(({ companyName }) =>
              companyName ? companyName : null
            )
          )
        : [];
      setValidQuote(validQuote);
      dispatch(setValidQuotes(validQuote));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [newGroupedQuotesCompare]);

  //pdf json creations
  // prettier-ignore
  const pdfProps = { newGroupedQuotesCompare, temp_data, addOnsAndOthers, type, shortTerm, enquiry_id,
    selectedTab, theme_conf, Theme, gstStatus, dispatch, xutm: token }
  usePdfCreate(pdfProps);

  return (
    <TopDiv>
      <div className="compare-products-list">
        <ul className="cd-products-columns">
          {newGroupedQuotesCompare?.map((item, index) => (
            <Product
              quote={item}
              index={index}
              length={newGroupedQuotesCompare?.length}
              type={type}
              setPrevPopup={setPrevPopup}
              prevPopup={prevPopup}
              setSelectedId={setSelectedId}
              setSelectedCompanyName={setSelectedCompanyName}
              validQuote={validQuote}
              popupCard={false}
              setSelectedIcId={setSelectedIcId}
              setSelectedCompanyAlias={setSelectedCompanyAlias}
              setApplicableAddonsLits={setApplicableAddonsLits}
              scrollPosition={scrollPosition}
              quoteComprehesive={quoteComprehesive}
            />
          ))}
        </ul>
      </div>
    </TopDiv>
  );
};

export default CompareProductsList;

// PropTypes
CompareProductsList.propTypes = {
  compareQuotes: PropTypes.array,
  type: PropTypes.string,
  setPrevPopup: PropTypes.func,
  prevPopup: PropTypes.bool,
  setSelectedId: PropTypes.func,
  setSelectedCompanyName: PropTypes.func,
  quoteComprehesive: PropTypes.array,
  setSelectedIcId: PropTypes.func,
  setSelectedCompanyAlias: PropTypes.func,
  setApplicableAddonsLits: PropTypes.func,
  scrollPosition: PropTypes.number,
  zdlp: PropTypes.string,
  setClaimList: PropTypes.func,
  zdlp_gdd: PropTypes.string,
  setClaimList_gdd: PropTypes.func,
};

// DefaultTypes
CompareProductsList.defaultProps = {
  compareQuotes: [],
  type: "",
  setPrevPopup: () => {},
  prevPopup: false,
  setSelectedId: () => {},
  setSelectedCompanyName: () => {},
  quoteComprehesive: [],
  setSelectedIcId: () => {},
  setSelectedCompanyAlias: () => {},
  setApplicableAddonsLits: () => {},
  scrollPosition: 0,
  zdlp: "",
  setClaimList: () => {},
  zdlp_gdd: "",
  setClaimList_gdd: () => {},
};
