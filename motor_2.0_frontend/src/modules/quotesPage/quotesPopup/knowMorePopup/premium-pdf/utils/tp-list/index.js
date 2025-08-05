import { TypeReturn } from "modules/type";
import { currencyFormater } from "utils";
import { getTpList } from "./tp-list";
import { getTpListGCV } from "./tp-list-gcv";
import { getTpListBike } from "./tp-list-bike";

export const tpObject = (tpObjectProps) => {
  const {
    quote,
    temp_data,
    addOnsAndOthers,
    type,
    llpaidCon,
    others,
    othersList,
    totalPremiumB,
  } = tpObjectProps;

  // get tp list common fields
  const tpListProps = { quote, temp_data, addOnsAndOthers, type, llpaidCon };
  let tpList = getTpList(tpListProps);

  // get tp list for gcv
  // prettier-ignore
  const tpListGcvProps= { quote, temp_data, addOnsAndOthers, type, llpaidCon, others, othersList }
  let tpListGCV = getTpListGCV(tpListGcvProps);

  // get tp list for bike
  const tpListBikeProps = { quote, temp_data, addOnsAndOthers, type };
  let tpListBike = getTpListBike(tpListBikeProps);

  return {
    title: "Liability",
    list:
      TypeReturn(type) === "bike"
        ? tpListBike
        : temp_data?.journeyCategory === "GCV" ||
          temp_data?.journeyCategory === "MISC"
        ? tpListGCV
        : tpList,
    total: {
      "Total Liability Premium (B)": `₹${currencyFormater(
        totalPremiumB - (quote?.tppdDiscount * 1 || 0)
      )}`,
    },
  };
};
