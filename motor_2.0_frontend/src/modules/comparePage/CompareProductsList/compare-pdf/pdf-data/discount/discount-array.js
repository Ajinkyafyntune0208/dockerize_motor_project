import { TypeReturn } from "modules/type";
import { currencyFormater } from "utils";
import _ from "lodash";

export const getDiscountArray = (discountProps) => {
  // prettier-ignore
  const { temp_data, addOnsAndOthers, type, newGroupedQuotesCompare } = discountProps;

  let discountArray = [];
  discountArray.push(
    // eslint-disable-next-line no-sparse-arrays
    _.compact([
      temp_data.journeyCategory !== "GCV"
        ? `Vehicle is fitted with ARAI  ${
            addOnsAndOthers?.selectedDiscount?.includes(
              "Is the vehicle fitted with ARAI approved anti-theft device?"
            )
              ? ""
              : ""
          } `
        : "",
      TypeReturn(type) !== "cv"
        ? `Voluntary Deductible ${
            addOnsAndOthers?.selectedDiscount?.includes("Voluntary Discounts")
              ? ""
              : ""
          } `
        : "",
      !temp_data?.odOnly &&
        temp_data.journeyCategory === "GCV" &&
        `Vehicle Limited to Own Premises`,
      !temp_data?.odOnly && `TPPD Cover`,
      ,
    ])
  );
  discountArray.push(
    _.compact([
      temp_data.journeyCategory !== "GCV"
        ? addOnsAndOthers?.selectedDiscount?.includes(
            "Is the vehicle fitted with ARAI approved anti-theft device?"
          )
          ? Number(
              newGroupedQuotesCompare[0]?.antitheftDiscount
                ? newGroupedQuotesCompare[0]?.antitheftDiscount
                : 0
            ) !== 0
            ? `₹ ${currencyFormater(
                newGroupedQuotesCompare[0]?.antitheftDiscount
              )}`
            : "Not Available"
          : "Not Selected"
        : "",
      TypeReturn(type) !== "cv"
        ? addOnsAndOthers?.selectedDiscount?.includes("Voluntary Discounts")
          ? addOnsAndOthers?.volDiscountValue !== 0
            ? `₹ ${currencyFormater(
                newGroupedQuotesCompare[0]?.voluntaryExcess
              )}` === "₹ 0"
              ? "Not Available"
              : `₹ ${currencyFormater(
                  newGroupedQuotesCompare[0]?.voluntaryExcess
                )}`
            : "Not Available"
          : "Not Selected"
        : "",
      !temp_data?.odOnly && temp_data.journeyCategory === "GCV"
        ? addOnsAndOthers?.selectedDiscount?.includes(
            "Vehicle Limited to Own Premises"
          )
          ? Number(newGroupedQuotesCompare[0]?.coverUnnamedPassengerValue) !== 0
            ? `₹ ${currencyFormater(
                newGroupedQuotesCompare[0]?.coverUnnamedPassengerValue
              )}` === "₹ 0"
              ? "Not Available"
              : `₹ ${currencyFormater(
                  newGroupedQuotesCompare[0]?.coverUnnamedPassengerValue
                )}`
            : "Not Available"
          : temp_data?.odOnly
          ? ""
          : "Not Selected"
        : "",
      addOnsAndOthers?.selectedDiscount?.includes("TPPD Cover")
        ? Number(newGroupedQuotesCompare[0]?.tppdDiscount || 0) !== 0
          ? `₹ ${currencyFormater(
              newGroupedQuotesCompare[0]?.tppdDiscount
            )}` === "₹ 0"
            ? "Not Available"
            : `₹ ${currencyFormater(newGroupedQuotesCompare[0]?.tppdDiscount)}`
          : "Not Available"
        : temp_data?.odOnly
        ? ""
        : "Not Selected",
    ])
  );
  if (Number(newGroupedQuotesCompare[1]?.idv) > 0) {
    discountArray.push(
      _.compact([
        temp_data.journeyCategory !== "GCV"
          ? addOnsAndOthers?.selectedDiscount?.includes(
              "Is the vehicle fitted with ARAI approved anti-theft device?"
            )
            ? Number(
                newGroupedQuotesCompare[1]?.antitheftDiscount
                  ? newGroupedQuotesCompare[1]?.antitheftDiscount
                  : 0
              ) !== 0
              ? `₹ ${currencyFormater(
                  newGroupedQuotesCompare[1]?.antitheftDiscount
                )}`
              : "Not Available"
            : "Not Selected"
          : "",
        TypeReturn(type) !== "cv"
          ? addOnsAndOthers?.selectedDiscount?.includes("Voluntary Discounts")
            ? addOnsAndOthers?.volDiscountValue !== 0
              ? `₹ ${currencyFormater(
                  newGroupedQuotesCompare[1]?.voluntaryExcess
                )}` === "₹ 0"
                ? "Not Available"
                : `₹ ${currencyFormater(
                    newGroupedQuotesCompare[1]?.voluntaryExcess
                  )}`
              : "Not Available"
            : "Not Selected"
          : "",
        !temp_data?.odOnly && temp_data.journeyCategory === "GCV"
          ? addOnsAndOthers?.selectedDiscount?.includes(
              "Vehicle Limited to Own Premises"
            )
            ? Number(newGroupedQuotesCompare[1]?.coverUnnamedPassengerValue) !==
              0
              ? `₹ ${currencyFormater(
                  newGroupedQuotesCompare[1]?.coverUnnamedPassengerValue
                )}` === "₹ 0"
                ? "Not Available"
                : `₹ ${currencyFormater(
                    newGroupedQuotesCompare[1]?.coverUnnamedPassengerValue
                  )}`
              : "Not Available"
            : temp_data?.odOnly
            ? ""
            : "Not Selected"
          : "",
        addOnsAndOthers?.selectedDiscount?.includes("TPPD Cover")
          ? Number(newGroupedQuotesCompare[1]?.tppdDiscount || 0) !== 0
            ? `₹ ${currencyFormater(
                newGroupedQuotesCompare[1]?.tppdDiscount
              )}` === "₹ 0"
              ? "Not Available"
              : `₹ ${currencyFormater(
                  newGroupedQuotesCompare[1]?.tppdDiscount
                )}`
            : "Not Available"
          : temp_data?.odOnly
          ? ""
          : "Not Selected",
      ])
    );
  }
  if (Number(newGroupedQuotesCompare[2]?.idv) > 0) {
    discountArray.push(
      _.compact([
        temp_data.journeyCategory !== "GCV"
          ? addOnsAndOthers?.selectedDiscount?.includes(
              "Is the vehicle fitted with ARAI approved anti-theft device?"
            )
            ? Number(
                newGroupedQuotesCompare[2]?.antitheftDiscount
                  ? newGroupedQuotesCompare[2]?.antitheftDiscount
                  : 0
              ) !== 0
              ? `₹ ${currencyFormater(
                  newGroupedQuotesCompare[2]?.antitheftDiscount
                    ? newGroupedQuotesCompare[2]?.antitheftDiscount
                    : 0
                )}`
              : "Not Available"
            : "Not Selected"
          : "",
        TypeReturn(type) !== "cv"
          ? addOnsAndOthers?.selectedDiscount?.includes("Voluntary Discounts")
            ? addOnsAndOthers?.volDiscountValue !== 0
              ? `₹ ${currencyFormater(
                  newGroupedQuotesCompare[2]?.voluntaryExcess
                )}` === "₹ 0"
                ? "Not Available"
                : `₹ ${currencyFormater(
                    newGroupedQuotesCompare[2]?.voluntaryExcess
                  )}`
              : "Not Available"
            : "Not Selected"
          : "",
        temp_data.journeyCategory === "GCV"
          ? addOnsAndOthers?.selectedDiscount?.includes(
              "Vehicle Limited to Own Premises"
            )
            ? Number(newGroupedQuotesCompare[2]?.coverUnnamedPassengerValue) !==
              0
              ? `₹ ${currencyFormater(
                  newGroupedQuotesCompare[2]?.coverUnnamedPassengerValue
                )}` === "₹ 0"
                ? "Not Available"
                : `₹ ${currencyFormater(
                    newGroupedQuotesCompare[2]?.coverUnnamedPassengerValue
                  )}`
              : "Not Available"
            : temp_data?.odOnly
            ? ""
            : "Not Selected"
          : "",
        addOnsAndOthers?.selectedDiscount?.includes("TPPD Cover")
          ? Number(newGroupedQuotesCompare[2]?.tppdDiscount || 0) !== 0
            ? `₹ ${currencyFormater(
                newGroupedQuotesCompare[2]?.tppdDiscount
              )}` === "₹ 0"
              ? "Not Available"
              : `₹ ${currencyFormater(
                  newGroupedQuotesCompare[2]?.tppdDiscount
                )}`
            : "Not Available"
          : temp_data?.odOnly
          ? ""
          : "Not Selected",
      ])
    );
  }
  return discountArray;
};
