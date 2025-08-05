import { TypeReturn } from "modules/type";
import { camelToUnderscore, currencyFormater } from "utils";
import _ from "lodash";

export const getTpListGCV = (tpListGcvProps) => {
  // prettier-ignore
  const {quote, temp_data, addOnsAndOthers, type, llpaidCon, others, othersList } = tpListGcvProps
  return {
    "Third Party Liability":
      quote?.tppdPremiumAmount * 1
        ? `₹ ${currencyFormater(quote?.tppdPremiumAmount)} `
        : "N/A",

    ...(quote?.otherCovers?.legalLiabilityToEmployee !== undefined &&
      temp_data?.ownerTypeId === 2 && {
        "Legal Liability To Employee":
          quote?.otherCovers?.legalLiabilityToEmployee * 1 === 0
            ? "Included"
            : `₹ ${currencyFormater(
                quote?.otherCovers?.legalLiabilityToEmployee
              )}`,
      }),

    ...(addOnsAndOthers?.selectedDiscount?.includes("TPPD Cover") && {
      "TPPD Discounts":
        quote?.tppdDiscount * 1
          ? `- ₹ ${currencyFormater(quote?.tppdDiscount)}`
          : "N/A",
    }),

    // Additional PA Cover
    ...((addOnsAndOthers?.selectedAdditions?.includes(
      "PA paid driver/conductor/cleaner"
    ) ||
      addOnsAndOthers?.selectedAdditions?.includes(
        "PA cover for additional paid driver"
      )) && {
      [(() => {
        const isGCVorMISC =
          temp_data?.journeyCategory === "GCV" ||
          temp_data?.journeyCategory === "MISC";
        const paCoverLabel = isGCVorMISC
          ? "Additional PA Cover To Paid Driver/Conductor/Cleaner"
          : "Additional PA Cover To Paid Driver";
        const paCoverAmount = quote?.paPaidDriverSI
          ? `${quote.paPaidDriverSI} SI`
          : "";
        return `${paCoverLabel}${
          paCoverAmount ? " (" + paCoverAmount + ")" : ""
        }`;
      })()]: quote?.motorAdditionalPaidDriver
        ? `₹ ${currencyFormater(quote?.motorAdditionalPaidDriver)}`
        : "N/A",
    }),

    ...((addOnsAndOthers?.selectedAdditions?.includes("LL paid driver") ||
      addOnsAndOthers?.selectedAdditions?.includes(
        "LL paid driver/conductor/cleaner"
      )) &&
    !llpaidCon
      ? quote?.defaultPaidDriver * 1
        ? {
            "Legal Liability To Paid Driver/Conductor/Cleaner": `₹ ${currencyFormater(
              quote?.defaultPaidDriver
            )}`,
          }
        : {
            "Legal Liability To Paid Driver/Conductor/Cleaner": "N/A",
          }
      : {
          ...((addOnsAndOthers?.selectedAdditions?.includes("LL paid driver") ||
            addOnsAndOthers?.selectedAdditions?.includes(
              "LL paid driver/conductor/cleaner"
            )) && {
            "Legal Liability To Paid Driver":
              quote?.llPaidDriverPremium * 1
                ? `₹ ${currencyFormater(quote?.llPaidDriverPremium)}`
                : "N/A",
          }),

          ...(addOnsAndOthers?.selectedAdditions?.includes(
            "LL paid driver/conductor/cleaner"
          ) && {
            [`Legal Liability To Paid Conductor ${
              [
                "icici_lombard",
                "magma",
                "cholla_mandalam",
                "royal_sundaram",
                "sbi",
              ].includes(quote?.companyAlias)
                ? "/Cleaner"
                : ""
            }`]:
              quote?.llPaidConductorPremium * 1
                ? `₹ ${currencyFormater(quote?.llPaidConductorPremium)}`
                : "N/A",
          }),

          ...(![
            "icici_lombard",
            "magma",
            "cholla_mandalam",
            "royal_sundaram",
            "sbi",
          ].includes(quote?.companyAlias) &&
            quote?.llPaidCleanerPremium * 1 && {
              "Legal Liability To Paid Cleaner":
                quote?.llPaidCleanerPremium * 1
                  ? `₹ ${currencyFormater(quote?.llPaidCleanerPremium)}`
                  : "N/A",
            }),
        }),

    ...(!addOnsAndOthers?.selectedAdditions?.includes("LL paid driver") &&
      !_.isEmpty(others) &&
      others?.includes("lLPaidDriver") &&
      others.map(
        (item) =>
          item === "lLPaidDriver" && {
            [camelToUnderscore(item)
              ?.replace(/_/g, " ")
              .split(" ")
              .map(_.capitalize)
              .join(" ")]:
              Number(othersList[item]) === 0
                ? "Included"
                : `₹ ${currencyFormater(quote?.defaultPaidDriver)}`,
          }
      )[0]),

    ...((quote?.cngLpgTp * 1 || quote?.cngLpgTp * 1 === 0) &&
      TypeReturn(type) !== "bike" && {
        "LPG/CNG Kit TP": `₹ ${currencyFormater(quote?.cngLpgTp) || "N/A"}`,
      }),

    ...(addOnsAndOthers?.selectedCpa?.includes(
      "Compulsory Personal Accident"
    ) && {
      [`Compulsory PA Cover For Owner Driver ${
        !_.isEmpty(addOnsAndOthers?.isTenure) && TypeReturn(type) === "car"
          ? "(3 Years)"
          : !_.isEmpty(addOnsAndOthers?.isTenure) && TypeReturn(type) === "bike"
          ? "(5 Years)"
          : ""
      }`]: `${
        addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        ) && !_.isEmpty(addOnsAndOthers?.isTenure)
          ? quote?.multiYearCpa * 1
            ? "₹ " + currencyFormater(quote?.multiYearCpa)
            : "N/A"
          : quote?.compulsoryPaOwnDriver * 1
          ? "₹ " + currencyFormater(quote?.compulsoryPaOwnDriver)
          : "N/A"
      }`,
    }),

    ...(addOnsAndOthers?.selectedDiscount?.includes(
      "Vehicle Limited to Own Premises"
    ) && {
      "Vehicle limited to own premises":
        quote?.limitedtoOwnPremisesOD * 1
          ? `- ₹ ${currencyFormater(quote?.limitedtoOwnPremisesOD * 1)}`
          : "N/A",
    }),

    ...(addOnsAndOthers?.selectedAdditions?.includes(
      "Geographical Extension"
    ) && {
      "Geographical Extension":
        quote?.geogExtensionTPPremium * 1
          ? `₹ ${currencyFormater(quote?.geogExtensionTPPremium)}`
          : "N/A",
    }),
  };
};
