import { BlockedSections } from "modules/quotesPage/addOnCard/cardConfig";
import { TypeReturn } from "modules/type";
import {
  getDriverValue,
  getGeographicalExtensionValue,
  getGeographicalValue,
  getLLPaidDriverValue,
  getPAValue,
  getUnnamedPassengerPAValue,
  getNFPPValue
} from "./additional-helper";

export const getAdditionalArray = (additionalArrayProps) => {
  const { temp_data, shortTerm, type, addOnsAndOthers, newGroupedQuotesCompare } = additionalArrayProps;

  // if the condition do not match only then show PA Cover For Additional Paid Driver
  const paCoverCondition =
    temp_data?.odOnly ||
    shortTerm ||
    TypeReturn(type) === "bike" ||
    temp_data.journeyCategory === "GCV" ||
    temp_data?.journeyCategory === "MISC";

  // if the condition do not match only then show Um named Passenger PA Cover
  const unnamedPassengerCondition =
    temp_data?.odOnly ||
    temp_data.journeyCategory === "GCV" ||
    BlockedSections(
      import.meta.env.VITE_BROKER,
      temp_data?.journeyCategory
    )?.includes("unnamed pa cover");

  let additionalArray = [];
  additionalArray.push([
    paCoverCondition
      ? ""
      : `PA Cover For Additional Paid Driver  ${
          addOnsAndOthers?.selectedAdditions?.includes(
            "PA cover for additional paid driver"
          )
            ? ` (${
                addOnsAndOthers?.additionalPaidDriver 
              })`
            : ""
        } `,
    unnamedPassengerCondition
      ? ""
      : `Umnamed Passenger PA Cover  ${
          addOnsAndOthers?.selectedAdditions?.includes(
            "Unnamed Passenger PA Cover"
          ) ||
          newGroupedQuotesCompare[0]?.includedAdditional?.included?.includes(
            "coverUnnamedPassengerValue"
          )
            ? `(${
                addOnsAndOthers?.unNamedCoverValue 
              })`
            : ""
        }   `,
    !temp_data?.odOnly
      ? `LL Paid Driver  ${
          addOnsAndOthers?.selectedAdditions?.includes("LL paid driver")
            ? ""
            : ""
        } `
      : "",
    import.meta.env.VITE_BROKER !== "OLA" && `Geographical Extension`,
    temp_data.journeyCategory === "GCV" && "NFPP Cover"
  ]);
  additionalArray.push([
    getPAValue(paCoverCondition, addOnsAndOthers, newGroupedQuotesCompare, type, 0),
    getUnnamedPassengerPAValue(addOnsAndOthers, newGroupedQuotesCompare, type, unnamedPassengerCondition, 0),
    getLLPaidDriverValue(temp_data, addOnsAndOthers, newGroupedQuotesCompare, 0),
    getGeographicalExtensionValue(addOnsAndOthers, newGroupedQuotesCompare, 0),
    getNFPPValue(temp_data, addOnsAndOthers, newGroupedQuotesCompare, 0)
  ]);
  additionalArray.push([
    getPAValue(paCoverCondition, addOnsAndOthers, newGroupedQuotesCompare, type, 1),
    getUnnamedPassengerPAValue(addOnsAndOthers, newGroupedQuotesCompare, type, unnamedPassengerCondition, 1),
    getLLPaidDriverValue(temp_data, addOnsAndOthers, newGroupedQuotesCompare, 1),
    getGeographicalExtensionValue(addOnsAndOthers, newGroupedQuotesCompare, 1),
    getNFPPValue(temp_data, addOnsAndOthers, newGroupedQuotesCompare, 1)
  ]);
  if (Number(newGroupedQuotesCompare[2]?.idv) > 0) {
    additionalArray.push([
      getPAValue(paCoverCondition, addOnsAndOthers, newGroupedQuotesCompare, type, 2),
      getUnnamedPassengerPAValue(addOnsAndOthers, newGroupedQuotesCompare, type, unnamedPassengerCondition, 2),
      getLLPaidDriverValue(temp_data, addOnsAndOthers, newGroupedQuotesCompare, 2),
      getGeographicalExtensionValue(addOnsAndOthers, newGroupedQuotesCompare, 2),
      getNFPPValue(temp_data, addOnsAndOthers, newGroupedQuotesCompare, 2)
    ]);
  }
  return additionalArray;
};

export const getAdditionalArrayGcv = (additionalArrayGcvProps) => {
  const { shortTerm, temp_data, addOnsAndOthers, newGroupedQuotesCompare } = additionalArrayGcvProps;

  const LLAndPaPaidDriverCondition =
    !shortTerm && !temp_data?.odOnly && temp_data.journeyCategory === "GCV";

  let additionalArrayGcv = [];
  additionalArrayGcv.push([
    LLAndPaPaidDriverCondition && `LL paid driver/conductor/cleaner`,
    LLAndPaPaidDriverCondition && `PA paid driver/conductor/cleaner`,
    import.meta.env.VITE_BROKER !== "OLA" && `Geographical Extension`,
    temp_data.journeyCategory === "GCV" && "NFPP Cover"
  ]);

  additionalArrayGcv.push([
    getDriverValue(LLAndPaPaidDriverCondition, addOnsAndOthers, newGroupedQuotesCompare, 0, "defaultPaidDriver"),
    getDriverValue(LLAndPaPaidDriverCondition, addOnsAndOthers, newGroupedQuotesCompare, 0, "motorAdditionalPaidDriver"),
    getGeographicalValue(addOnsAndOthers, newGroupedQuotesCompare, 0),
    getNFPPValue(temp_data, addOnsAndOthers, newGroupedQuotesCompare, 0)
  ]);
  additionalArrayGcv.push([
    getDriverValue(LLAndPaPaidDriverCondition, addOnsAndOthers, newGroupedQuotesCompare, 1, "defaultPaidDriver"),
    getDriverValue(LLAndPaPaidDriverCondition, addOnsAndOthers, newGroupedQuotesCompare, 1, "motorAdditionalPaidDriver"),
    getGeographicalValue(addOnsAndOthers, newGroupedQuotesCompare, 1),
    getNFPPValue(temp_data, addOnsAndOthers, newGroupedQuotesCompare, 1)
  ]);
  if (Number(newGroupedQuotesCompare[2]?.idv) > 0) {
    additionalArrayGcv.push([
      getDriverValue(LLAndPaPaidDriverCondition, addOnsAndOthers, newGroupedQuotesCompare, 2, "defaultPaidDriver"),
      getDriverValue(LLAndPaPaidDriverCondition, addOnsAndOthers, newGroupedQuotesCompare, 2, "motorAdditionalPaidDriver"),
      getGeographicalValue(addOnsAndOthers, newGroupedQuotesCompare, 2),
      getNFPPValue(temp_data, addOnsAndOthers, newGroupedQuotesCompare, 2)
    ]);
  }
  return additionalArrayGcv;
};
