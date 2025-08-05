import { TypeReturn } from "modules/type";
import { currencyFormater } from "utils";
import _ from "lodash";

export const getTpList = (tpListProps) => {
  const { quote, temp_data, addOnsAndOthers, type, llpaidCon } = tpListProps;
    const paCoverAmount = quote?.paPaidDriverSI
    ? `(${quote.paPaidDriverSI} SI)`
    : "";
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
    ...((addOnsAndOthers?.selectedAdditions?.includes(
      "Unnamed Passenger PA Cover"
    ) ||
      quote?.includedAdditional?.included?.includes(
        "coverUnnamedPassengerValue"
      )) &&
    !(
      quote?.includedAdditional?.included?.includes(
        "coverUnnamedPassengerValue"
      ) && !quote?.coverUnnamedPassengerValue * 1
    )
      ? {
          "PA For Unnamed Passenger": `${
            quote?.coverUnnamedPassengerValue * 1
              ? `₹ ${currencyFormater(
                  quote?.companyAlias === "sbi" &&
                    addOnsAndOthers?.selectedCpa?.includes(
                      "Compulsory Personal Accident"
                    ) &&
                    !_.isEmpty(addOnsAndOthers?.isTenure)
                    ? quote?.coverUnnamedPassengerValue *
                        (TypeReturn(type) === "bike" ? 5 : 3)
                    : quote?.coverUnnamedPassengerValue
                )}`
              : "N/A"
          }`,
        }
      : quote?.includedAdditional?.included?.includes(
          "coverUnnamedPassengerValue"
        )
      ? { "PA For Unnamed Passenger": "Included" }
      : {}),
    ...((addOnsAndOthers?.selectedAdditions?.includes(
      "PA cover for additional paid driver"
    ) ||
      addOnsAndOthers?.selectedAdditions?.includes(
        "PA paid driver/conductor/cleaner"
      )) &&
      TypeReturn(type) !== "bike" && {
        [`Additional PA Cover To Paid Driver ${paCoverAmount}`]:
          quote?.motorAdditionalPaidDriver * 1
            ? `₹ ${currencyFormater(
                quote?.companyAlias === "sbi" &&
                  addOnsAndOthers?.selectedCpa?.includes(
                    "Compulsory Personal Accident"
                  ) &&
                  !_.isEmpty(addOnsAndOthers?.isTenure)
                  ? quote?.motorAdditionalPaidDriver *
                      (TypeReturn(type) === "bike" ? 5 : 3)
                  : quote?.motorAdditionalPaidDriver
              )}`
            : "N/A",
      }),
    ...((addOnsAndOthers?.selectedAdditions?.includes("LL paid driver") ||
      addOnsAndOthers?.selectedAdditions?.includes(
        "LL paid driver/conductor/cleaner"
      )) &&
    !llpaidCon &&
    (quote?.defaultPaidDriver * 1 || quote?.llPaidDriverPremium * 1)
      ? temp_data?.journeyCategory === "GCV"
        ? {
            "Legal Liability To Paid Driver/Conductor/Cleaner":
              quote?.defaultPaidDriver * 1
                ? `₹ ${currencyFormater(quote?.defaultPaidDriver)}`
                : "N/A",
          }
        : {
            "Legal Liability To Paid Driver":
              quote?.defaultPaidDriver * 1
                ? `₹ ${currencyFormater(quote?.defaultPaidDriver)}`
                : "N/A",
          }
      : quote?.llPaidDriverPremium * 1 && {
          "Legal Liability To Paid Driver":
            quote?.llPaidDriverPremium * 1
              ? `₹ ${currencyFormater(quote?.llPaidDriverPremium)}`
              : "N/A",
        }),
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
    ...(addOnsAndOthers?.selectedAdditions?.includes("NFPP Cover") && {
      "NFPP Cover":
        quote?.nfpp * 1 ? `₹ ${currencyFormater(quote?.nfpp)}` : "N/A",
    }),
  };
};
