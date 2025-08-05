import _ from "lodash";
import { toDate } from "utils";
import moment from "moment";
import { differenceInDays, differenceInMonths } from "date-fns";

export const GetValidAdditionalKeys = (additional) => {
  var y = Object.entries(additional)
    .filter(([, v]) => Number(v) > 0)
    .map(([k]) => k);
  return y;
};

const CreateQuoteCategory = (quoteComprehesive) => {
  return quoteComprehesive?.map((el) =>
    el?.gdd === "Y"
      ? { ...el, modifiedAlias: `gdd_${el?.companyAlias}` }
      : { ...el, modifiedAlias: el?.companyAlias }
  );
};

export const GroupByIC = (quoteComprehesive, NoModification) => {
  return _.groupBy(
    NoModification ? quoteComprehesive : CreateQuoteCategory(quoteComprehesive),
    (i) => (NoModification ? i.companyAlias : i.modifiedAlias)
  );
};

export const NoOfClaims = (groupedQuotesByIC, IC_Name) => {
  return (
    (!_.isEmpty(groupedQuotesByIC?.[`${IC_Name}`]) &&
      groupedQuotesByIC?.[`${IC_Name}`].length > 1 &&
      _.compact(
        groupedQuotesByIC?.[`${IC_Name}`].map((el) => el?.claimsCovered)
      )) ||
    []
  );
};

//prettier-ignore
export const CreateMarker = (marker, markerList, groupedQuotesByIC, IC_Name) => {
    return marker && !_.isEmpty(markerList)
    ? //Filtered claim quote
      {
        ...groupedQuotesByIC,
        //If the selected marker is present , then the value of the godigit key is changed to the quote which has the marker key
        ...(!_.isEmpty(
          groupedQuotesByIC?.[`${IC_Name}`].filter(
            (el) => el?.claimsCovered === marker
          )
        ) && {
          godigit: groupedQuotesByIC?.[`${IC_Name}`].filter(
            (el) => el?.claimsCovered === marker
          ),
        }),
      }
    : //If no marker is selected then there is no need of filter.
      groupedQuotesByIC;
}

//2 + 2 | 3 + 3 | O(n * m)
const _longTermQuotes = (groupedQuotesByIC, targetTenure) => {
  targetTenure = Number(targetTenure); // Type casting to number
  const result = {};
  //Filtering tenured quotes
  if (targetTenure) {
    _.forEach(groupedQuotesByIC, (values, key) => {
      const matchingItems = _.filter(
        values,
        (item) =>
          item.hasOwnProperty("tpTenure") &&
          Number(item.tpTenure) === targetTenure
      );
      if (matchingItems.length > 0) {
        result[key] = matchingItems;
      }
    });
  } else {
    //Filtering non tenured quotes
    _.forEach(groupedQuotesByIC, (values, key) => {
      const matchingItems = _.filter(values, (item) => !item.tpTenure);
      if (matchingItems.length > 0) {
        result[key] = matchingItems;
      }
    });
  }

  return result;
};

export const _filterTenure = (groupedQuotesByIC, longtermParams) => {
  const { longTerm2, longTerm3 } = longtermParams || {};
  if (longTerm2 || longTerm3) {
    let tenure = longTerm2 ? 2 : 3;
    return _longTermQuotes(groupedQuotesByIC, tenure);
  } else {
    return groupedQuotesByIC;
  }
};

export const _filterTpTenure = (quoteList, longtermParams) => {
  const { longTerm2, longTerm3 } = longtermParams;
  if (longTerm2 || longTerm3) {
    let tenure = longTerm2 ? 2 : 3;
    return quoteList.filter((quote) => Number(quote?.tpTenure) === tenure);
  } else {
    return quoteList.filter((quote) => !quote?.tpTenure);
  }
};

//Applicable addons logic
export const ConsolidateAddons = (el_modified) => {
  let applicable_addons = [];
  el_modified.forEach((item) => {
    applicable_addons = [...applicable_addons, ...item?.applicableAddons];
  });
  //appending applicable addons to all packages of the IC
  return el_modified.map((item) => ({
    ...item,
    applicableAddons: applicable_addons,
  }));
};

//prettier-ignore
export const Grouping = (newList, GetValidAdditionalKeys, selectedAddons, quoteComprehesiveGroupedUnique) => {
  newList.forEach((el_modified) => {
    let BestMatch = [];
    let match = {};
    //appending applicable addons to all packages of the IC
    let el = ConsolidateAddons(el_modified);

    //Grouping Logic
    el.forEach((i) => {
      if (_.isEmpty(match)) {
        match = i;
      } else {
        //get addon keys of last best
        let x1 =
          match?.addOnsData?.inBuilt &&
          Object.keys(match?.addOnsData?.inBuilt);
        let additional1 = match?.addOnsData?.additional;
        var y1 = GetValidAdditionalKeys(additional1);
        let z1 = [...x1, ...y1];
        let commonLast = selectedAddons
          ? selectedAddons.filter((m) => !_.isEmpty(z1) && z1?.includes(m))
          : 0;
        // get addon keys for current
        let x = i?.addOnsData?.inBuilt && Object.keys(i?.addOnsData?.inBuilt);
        let additional = i?.addOnsData?.additional;
        var y = GetValidAdditionalKeys(additional);
        let z = [...x, ...y];
        let commonCurrent = selectedAddons
          ? selectedAddons.filter((m) => !_.isEmpty(z) && z?.includes(m))
          : 0;
        //Swap if dummy tile is stored in match and a quote is fetched.
        if(match?.dummyTile && !i?.dummyTile){
          match = i
        }
        //Dummy Tiles are excluded from best match in all steps
        // if current the elemenet has more addons common with selectedAddons than last then swap it with match.
        if ((commonCurrent?.length > commonLast?.length) && !i?.dummyTile) {
          match = i;
        }
        if (commonCurrent?.length === commonLast?.length) {
          //find premium of match & current elem when the addons matched are equal in number
          let matchAddonPremium = 0;
          let currentAddonPremium = 0;

          Object.entries(
            match?.addOnsData?.additional ? match?.addOnsData?.additional : {}
          ).forEach(([key, value]) => {
            matchAddonPremium =
              Number(matchAddonPremium) +
              (selectedAddons?.includes(key) && Number(value)
                ? Number(value)
                : 0);
          });

          //calculation matched - other addons
          Object.entries(
            match?.addOnsData?.other ? match?.addOnsData?.other : {}
          ).forEach(([key, value]) => {
            matchAddonPremium =
              Number(matchAddonPremium) + (Number(value) ? Number(value) : 0);
          });

          Object.entries(
            i?.addOnsData?.additional ? i?.addOnsData?.additional : {}
          ).forEach(([key, value]) => {
            currentAddonPremium =
              Number(currentAddonPremium) +
              (selectedAddons?.includes(key) && Number(value)
                ? Number(value)
                : 0);
          });

          //calculation matched - other addons
          Object.entries(
            i?.addOnsData?.other ? i?.addOnsData?.other : {}
          ).forEach(([key, value]) => {
            currentAddonPremium =
              Number(currentAddonPremium) +
              (Number(value) ? Number(value) : 0);
          });

          //calculation matched - other addons

          Object.entries(
            match?.addOnsData?.inBuilt ? match?.addOnsData?.inBuilt : {}
          ).forEach(([key, value]) => {
            matchAddonPremium =
              Number(matchAddonPremium) + (Number(value) ? Number(value) : 0);
          });

          Object.entries(
            i?.addOnsData?.inBuilt ? i?.addOnsData?.inBuilt : {}
          ).forEach(([key, value]) => {
            currentAddonPremium =
              Number(currentAddonPremium) +
              (Number(value) ? Number(value) : 0);
          });
          if ((currentAddonPremium < matchAddonPremium) && !i?.dummyTile) {
            match = i;
          }
        }
      }
    });
    !_.isEmpty(match) && BestMatch.push(match);
    quoteComprehesiveGroupedUnique.push(BestMatch[0]);
  });
}

const _checkCPA = (quote, cpa, tenure) => {
  const multiYear = tenure && !_.isEmpty(tenure);
  return cpa && !_.isEmpty(cpa)
    ? multiYear
      ? quote?.multiYearCpa * 1 || 0
      : quote?.compulsoryPaOwnDriver * 1 || 0
    : true;
};

const _checkAddons = (quote, selectedAddons, GetValidAdditionalKeys) => {
  let x = quote?.addOnsData?.inBuilt && Object.keys(quote?.addOnsData?.inBuilt);
  let additional = quote?.addOnsData?.additional;
  var y = GetValidAdditionalKeys(additional);
  let z = [...x, ...y];
  let matchedAddons = selectedAddons
    ? selectedAddons.filter((m) => !_.isEmpty(z) && z?.includes(m))
    : 0;
  return _.isEqual(_.sortBy(matchedAddons), _.sortBy(selectedAddons));
};

const _checkAccesories = (quote, accesories = []) => {
  let accesoriesArray = accesories.map((accesory) => {
    switch (accesory) {
      case "Electrical Accessories":
        return quote?.motorElectricAccessoriesValue * 1;
      case "Non-Electrical Accessories":
        return quote?.motorNonElectricAccessoriesValue * 1;
      case "External Bi-Fuel Kit CNG/LPG":
        return quote?.motorLpgCngKitValue * 1 || quote?.cngLpgTp * 1;
      default:
        return false;
    }
  });
  return (
    _.compact(accesoriesArray).length === accesories.length ||
    _.isEmpty(accesories) ||
    !accesories
  );
};

const _checkCovers = (quote, covers = []) => {
  let coversArray = covers.map((cover) => {
    switch (cover) {
      case "PA cover for additional paid driver":
        return quote?.motorAdditionalPaidDriver * 1;
      case "Unnamed Passenger PA Cover":
        return (
          quote?.coverUnnamedPassengerValue * 1 ||
          quote?.includedAdditional?.included?.includes(
            "coverUnnamedPassengerValue"
          )
        );
      case "LL paid driver":
        return (
          quote?.defaultPaidDriver * 1 ||
          quote?.llPaidDriverPremium * 1 ||
          quote?.llPaidConductorPremium * 1 ||
          quote?.llPaidCleanerPremium * 1
        );
      case "Geographical Extension":
        return (
          quote?.geogExtensionTPPremium * 1 || quote?.geogExtensionODPremium * 1
        );
      case "NFPP Cover":
        return (
          quote?.nfpp
        );
      default:
        break;
    }
  });
  return (
    _.compact(coversArray).length === covers.length ||
    _.isEmpty(covers) ||
    !covers
  );
};

const _checkDiscounts = (quote, discounts = []) => {
  let discountsArray = discounts.map((discount) => {
    switch (discount) {
      case "Is the vehicle fitted with ARAI approved anti-theft device?":
        return quote?.antitheftDiscount * 1 ? true : false;
      case "Voluntary Discounts":
        return quote?.voluntaryExcess * 1 ? true : false;
      case "TPPD Cover":
        return quote?.tppdDiscount * 1 ? true : false;
      default:
        return false;
    }
  });
  return (
    _.compact(discountsArray).length === discounts.length ||
    _.isEmpty(discounts) ||
    !discounts
  );
};

const _correction = (exclude, excludeFrom) =>
  _.difference(excludeFrom, exclude);

const _tpCorrection = (selectedBenefit, type) => {
  //exclusions
  const excludeAccesories = [
    "Electrical Accessories",
    "Non-Electrical Accessories",
  ];
  const excludeDiscounts = [
    "Is the vehicle fitted with ARAI approved anti-theft device?",
    "Voluntary Discounts",
  ];
  //Apply corrections on Accesories/Discounts
  return _correction(
    type === "Discount" ? excludeDiscounts : excludeAccesories,
    selectedBenefit
  );
};

//relevance (exact match)
export const relevance = (
  quoteComprehesiveGroupedUnique,
  addOnsAndOthers,
  GetValidAdditionalKeys,
  ThirdParty,
  isCompany
) => {
  let FilteredQuotes = [];
  //selected
  let selectedCpa = addOnsAndOthers?.selectedCpa;
  let selectedTenure = addOnsAndOthers?.isTenure;
  let selectedAddons = addOnsAndOthers?.selectedAddons;
  let selectedAccesories = addOnsAndOthers?.selectedAccesories;
  let selectedAdditions = addOnsAndOthers?.selectedAdditions;
  let selectedDiscount = addOnsAndOthers?.selectedDiscount;
  //Removing additional selections in case of third party
  if (ThirdParty) {
    selectedAccesories = _tpCorrection(selectedAccesories, "Accesories");
    selectedDiscount = _tpCorrection(selectedDiscount, "Discount");
  }

  //Looping through each unique quote.
  quoteComprehesiveGroupedUnique.forEach((quote) => {
    //Check for cpa.
    const cpa = _checkCPA(quote, selectedCpa, selectedTenure);
    //Check for addons.
    const addons = _checkAddons(quote, selectedAddons, GetValidAdditionalKeys);
    //Check for accesories.
    const accesories = _checkAccesories(quote, selectedAccesories);
    //Check for covers
    const covers = _checkCovers(quote, selectedAdditions);
    //Check for discounts.
    const discounts = _checkDiscounts(quote, selectedDiscount);

    //All checks ~ CPA, Addon, Accesory, cover, discount;
    if (
      (cpa || isCompany) &&
      (addons || ThirdParty) &&
      accesories &&
      covers &&
      discounts
    ) {
      FilteredQuotes.push(quote);
    }
  });
  //Check for discounts
  return FilteredQuotes;
};
export const renewalOnly = (quoteArray) => {
  return quoteArray.filter((i) => i?.isRenewal === "Y");
};

//prettier-ignore
export const FetchCompare = (tab, shortTerm3, quoteShortTerm3, shortTerm6, quoteShortTerm6, quoteComprehesiveGrouped1, quoteTpGrouped1) => {
  return tab === "tab1"
  ? shortTerm3
    ? !_.isEmpty(quoteShortTerm3) && quoteShortTerm3[0]
      ? quoteShortTerm3
      : []
    : shortTerm6
    ? !_.isEmpty(quoteShortTerm6) && quoteShortTerm6[0]
      ? quoteShortTerm6
      : []
    : !_.isEmpty(quoteComprehesiveGrouped1) && quoteComprehesiveGrouped1[0]
    ? quoteComprehesiveGrouped1
    : []
  : !_.isEmpty(quoteTpGrouped1) && quoteTpGrouped1[0]
  ? quoteTpGrouped1
  : []
}

export const _fetchTerm = (quoteShortTerm, term) => {
  return quoteShortTerm.filter(
    (quote) =>
      quote.premiumTypeCode === `short_term_${term}` ||
      quote.premiumTypeCode === `short_term_${term}_breakin`
  );
};

/**
 * Applies a discount to a value.
 *
 * @param {number} value - The original value to apply the discount to.
 * @param {number} discount - The discount percentage.
 * @returns {number} - The value after applying the discount.
 */

const applyDiscount = (value, discount) => {
  return discount ? +(+value - +value * (discount / 100)) : +value;
};

/**
 * Calculates the discounted value for an insurance addon based on the company and addon type.
 * For Royal Sundaram company, specific rules apply to roadside assistance, loss of personal belongings,
 * and key replacement addons. For other companies, the original value is returned without any discount.
 *
 * @param {number} value - The original value of the addon.
 * @param {number} discount - The discount percentage to be applied.
 * @param {string} companyAlias - The alias of the insurance company.
 * @param {string} addonName - The name of the addon.
 * @returns {number} - The discounted value, adjusted according to the rules for minimum values where applicable.
 */
export const _discount = (value, discount, companyAlias, addonName) => {
  let returnValue = value;
  // Convert addon name to lowercase and remove spaces for comparison
  const addonNameLower = addonName.toLowerCase().replace(/ /g, "");
  // Check if the insurance company is Royal Sundaram
  const isRoyalSundaram = companyAlias === "royal_sundaram";

  // Determine the type of addon based on its name
  const isRoadsideAssistance =
    addonNameLower === "roadsideassistance" ||
    addonNameLower === "roadsideassistance2" ||
    addonNameLower === "roadsideassistance(₹49)";
  const isLossOfPersonalBelongings = [
    "lossofpersonalbelongings",
    "lopb",
  ].includes(addonNameLower);

  // Apply discount logic based on the company and addon type
  if (isRoyalSundaram) {
    if (isRoadsideAssistance) {
      // For roadside assistance, return the original value without any discount
      returnValue = +value;
    } else if (isLossOfPersonalBelongings) {
      // Apply discount for loss of personal belongings and ensure a minimum value of 100

      const addonValue = applyDiscount(value, discount);
      returnValue = addonValue < 100 ? 100 : addonValue;
    } else {
      // For other addons, simply apply the discount

      returnValue = applyDiscount(value, discount);
    }
  } else {
    // For companies other than Royal Sundaram, return the original value without any discount
    returnValue = +value;
  }
  return returnValue;
};

/*----- Quote Calculation ------*/
//Addons Premium Calculation
//Additional - Selected
//prettier-ignore
export const _calculateAddons = (quote, selectedAddons, additional, additionalList, addonDiscountPercentage) => {
  let allAddons = [
    "zeroDepreciation",
    "roadSideAssistance",
    "roadSideAssistance2",
    "imt23",
    "keyReplace",
    "engineProtector",
    "ncbProtection",
    "consumables",
    "tyreSecure",
    "returnToInvoice",
    "lopb",
    "emergencyMedicalExpenses",
    "windShield",
    "emiProtection",
    "additionalTowing",
    "batteryProtect",
  ];
  let totalAdditional = 0;
  let undiscounted = 0;
  (selectedAddons ? selectedAddons : allAddons).forEach((el) => {
    if (
      !_.isEmpty(additional) &&
      additional?.includes(el) &&
      typeof additionalList[el] === "number"
    ) {
      undiscounted = undiscounted + additionalList[el];
  
      totalAdditional =
        totalAdditional +
        _discount(additionalList[el], addonDiscountPercentage, quote?.companyAlias, el);
    }
  });
  return totalAdditional;
}
/*--x-- Quote Calculation ---x--*/

//Policy Type calculation cases
export const diffCalc = (caseType, temp_data) => {
  if (temp_data?.regDate) {
    const currentDate = toDate(moment().format("DD-MM-YYYY"));
    const regDate = toDate(temp_data?.regDate);
    const saodDate = toDate(moment().format("01-09-2018"));
    switch (caseType) {
      case "current_reg_days":
        return differenceInDays(currentDate, regDate);
      case "current_reg_months":
        return differenceInMonths(currentDate, regDate);
      case "reg_saod_days":
        return differenceInDays(regDate, saodDate);
      default:
        break;
    }
  } else {
    return false;
  }
};

//previous policy identifier code logic
//Policy type code identifier (3+3/5+5 are excluded)
export const previousPolicyTypeIdentifierCode = (tempData, temp_data, type) => {
  if (tempData?.policyType === "Third-party") {
    if (type === "cv") {
      return "01";
    } else {
      //This flag denotes single year TP
      if (
        temp_data?.previousPolicyTypeIdentifier === "Y" &&
        !temp_data?.newCar
      ) {
        return "01";
      }
      //multi year TP
      else {
        return type === "car" ? "03" : "05";
      }
    }
  } else if (tempData?.policyType === "Comprehensive") {
    if (type === "cv") {
      return "11";
    } else {
      if (temp_data?.newCar) {
        return type === "car" ? "13" : "15";
      }
      //1+1
      else if (
        temp_data?.previousPolicyTypeIdentifier === "Y" &&
        temp_data?.regDate &&
        // 1+1
        Number(temp_data?.regDate?.slice(temp_data?.regDate?.length - 4)) <
          new Date().getFullYear() - 1 &&
        //static OD
        ((diffCalc("reg_saod_days", temp_data) >= 0 &&
          diffCalc("current_reg_days", temp_data) > 270 &&
          (diffCalc("current_reg_months", temp_data) < 60 ||
            (diffCalc("current_reg_months", temp_data) === 60 &&
              diffCalc("current_reg_days", temp_data) <= 1095)) &&
          type === "bike") ||
          // Renewal margin
          ((diffCalc("current_reg_months", temp_data) < 36 ||
            (diffCalc("current_reg_months", temp_data) === 36 &&
              diffCalc("current_reg_days", temp_data) <= 1095)) &&
            type === "car") ||
          (diffCalc("reg_saod_days", temp_data) >= 0 &&
            diffCalc("current_reg_months", temp_data) >= 34 &&
            diffCalc("current_reg_days", temp_data) > 270 &&
            (diffCalc("current_reg_months", temp_data) < 36 ||
              (diffCalc("current_reg_months", temp_data) === 36 &&
                diffCalc("current_reg_days", temp_data) <= 1095)) &&
            type === "car") ||
          (diffCalc("reg_saod_days", temp_data) >= 0 &&
            diffCalc("current_reg_months", temp_data) >= 58 &&
            diffCalc("current_reg_days", temp_data) > 270 &&
            (diffCalc("current_reg_months", temp_data) < 60 ||
              (diffCalc("current_reg_months", temp_data) === 60 &&
                diffCalc("current_reg_days", temp_data) <= 1095)) &&
            type === "bike"))
      ) {
        return "11";
      } else if (
        !(
          (diffCalc("reg_saod_days", temp_data) >= 0 &&
            diffCalc("current_reg_days", temp_data) > 270 &&
            (diffCalc("current_reg_months", temp_data) < 60 ||
              (diffCalc("current_reg_months", temp_data) === 60 &&
                diffCalc("current_reg_days", temp_data) <= 1095)) &&
            type === "bike") ||
          ((diffCalc("current_reg_months", temp_data) < 36 ||
            (diffCalc("current_reg_months", temp_data) === 36 &&
              diffCalc("current_reg_days", temp_data) <= 1095)) &&
            type === "car") ||
          (diffCalc("reg_saod_days", temp_data) >= 0 &&
            diffCalc("current_reg_months", temp_data) >= 34 &&
            diffCalc("current_reg_days", temp_data) > 270 &&
            (diffCalc("current_reg_months", temp_data) < 36 ||
              (diffCalc("current_reg_months", temp_data) === 36 &&
                diffCalc("current_reg_days", temp_data) <= 1095)) &&
            type === "car") ||
          (diffCalc("reg_saod_days", temp_data) >= 0 &&
            diffCalc("current_reg_months", temp_data) >= 58 &&
            diffCalc("current_reg_days", temp_data) > 270 &&
            (diffCalc("current_reg_months", temp_data) < 60 ||
              (diffCalc("current_reg_months", temp_data) === 60 &&
                diffCalc("current_reg_days", temp_data) <= 1095)) &&
            type === "bike")
        ) &&
        !temp_data?.newCar
      ) {
        return type === "car" ? "11" : "11";
      } else {
        return type === "car" ? "13" : "15";
      }
    }
  } else if (tempData?.policyType === "Own-damage") {
    return "10";
  } else if (tempData?.policyType === "Not sure") {
    return "00";
  }
  //If nothing matches or if CV journey then return null
  else {
    return null;
  }
};
