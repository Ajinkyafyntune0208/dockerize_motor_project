import { useEffect } from "react";

export const useInitialCkycValue = ({
  companyAlias,
  setckycValue,
  CardData,
  ckycValuePresent,
  fields,
}) => {
  useEffect(() => {
    companyAlias === "bajaj_allianz" ||
    companyAlias === "tata_aig" ||
    companyAlias === "united_india" ||
    companyAlias === "raheja" ||
    companyAlias === "oriental" ||
    companyAlias === "universal_sompo" ||
    companyAlias === "royal_sundaram" ||
    fields?.includes("ckycQuestion")
      ? setckycValue("NO")
      : ckycValuePresent
      ? setckycValue(ckycValuePresent)
      : setckycValue("YES");
  }, [CardData?.owner]);
};
