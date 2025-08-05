import { TypeReturn } from "modules/type";
import { accessory, generateAccessoryValue } from "./accesories-helper";

export const getAccessoriesArray = (accessoriesProps) => {
  const { temp_data, addOnsAndOthers, type, newGroupedQuotesCompare } =
    accessoriesProps;
  let accessoriesArray = [];
  if (temp_data.journeyCategory === "GCV") {
    accessoriesArray.push([
      accessory("Electrical Accessories", addOnsAndOthers),
      accessory("Non-Electrical Accessories", addOnsAndOthers),
      accessory("Bi Fuel Kit", addOnsAndOthers, "External Bi-Fuel Kit CNG/LPG"),
      accessory("Trailer", addOnsAndOthers),
    ]);
  } else {
    accessoriesArray.push([
      accessory("Electrical Accessories", addOnsAndOthers),
      accessory("Non-Electrical Accessories", addOnsAndOthers),
      TypeReturn(type) === "bike"
        ? ""
        : accessory(
            "Bi Fuel Kit",
            addOnsAndOthers,
            "External Bi-Fuel Kit CNG/LPG"
          ),
    ]);
  }
  if (temp_data.journeyCategory === "GCV") {
    accessoriesArray.push([
      generateAccessoryValue(
        "Electrical Accessories",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[0]?.motorElectricAccessoriesValue,
        newGroupedQuotesCompare[0]?.companyAlias
      ),
      generateAccessoryValue(
        "Non-Electrical Accessories",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[0]?.motorNonElectricAccessoriesValue,
        newGroupedQuotesCompare[0]?.companyAlias
      ),
      generateAccessoryValue(
        "External Bi-Fuel Kit CNG/LPG",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[0]?.motorLpgCngKitValue,
        newGroupedQuotesCompare[0]?.companyAlias
      ),
      generateAccessoryValue(
        "Trailer",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[0]?.trailerValue,
        newGroupedQuotesCompare[0]?.companyAlias
      ),
    ]);
  } else {
    accessoriesArray.push([
      generateAccessoryValue(
        "Electrical Accessories",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[0]?.motorElectricAccessoriesValue,
        newGroupedQuotesCompare[0]?.companyAlias
      ),
      generateAccessoryValue(
        "Non-Electrical Accessories",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[0]?.motorNonElectricAccessoriesValue,
        newGroupedQuotesCompare[0]?.companyAlias
      ),
      TypeReturn(type) === "bike"
        ? ""
        : generateAccessoryValue(
            "External Bi-Fuel Kit CNG/LPG",
            addOnsAndOthers?.selectedAccesories,
            newGroupedQuotesCompare[0]?.motorLpgCngKitValue,
            newGroupedQuotesCompare[0]?.companyAlias
          ),
    ]);
  }
  if (temp_data.journeyCategory === "GCV") {
    accessoriesArray.push([
      generateAccessoryValue(
        "Electrical Accessories",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[1]?.motorElectricAccessoriesValue,
        newGroupedQuotesCompare[1]?.companyAlias
      ),
      generateAccessoryValue(
        "Non-Electrical Accessories",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[1]?.motorNonElectricAccessoriesValue,
        newGroupedQuotesCompare[1]?.companyAlias
      ),
      generateAccessoryValue(
        "External Bi-Fuel Kit CNG/LPG",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[1]?.motorLpgCngKitValue,
        newGroupedQuotesCompare[1]?.companyAlias
      ),
      generateAccessoryValue(
        "Trailer",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[1]?.trailerValue,
        newGroupedQuotesCompare[1]?.companyAlias
      ),
    ]);
  } else {
    accessoriesArray.push([
      generateAccessoryValue(
        "Electrical Accessories",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[1]?.motorElectricAccessoriesValue,
        newGroupedQuotesCompare[1]?.companyAlias
      ),
      generateAccessoryValue(
        "Non-Electrical Accessories",
        addOnsAndOthers?.selectedAccesories,
        newGroupedQuotesCompare[1]?.motorNonElectricAccessoriesValue,
        newGroupedQuotesCompare[1]?.companyAlias
      ),
      TypeReturn(type) === "bike"
        ? ""
        : generateAccessoryValue(
            "External Bi-Fuel Kit CNG/LPG",
            addOnsAndOthers?.selectedAccesories,
            newGroupedQuotesCompare[1]?.motorLpgCngKitValue,
            newGroupedQuotesCompare[1]?.companyAlias
          ),
    ]);
  }
  if (Number(newGroupedQuotesCompare[2]?.idv) > 0) {
    if (temp_data.journeyCategory === "GCV") {
      accessoriesArray.push([
        generateAccessoryValue(
          "Electrical Accessories",
          addOnsAndOthers?.selectedAccesories,
          newGroupedQuotesCompare[2]?.motorElectricAccessoriesValue,
          newGroupedQuotesCompare[2]?.companyAlias
        ),
        generateAccessoryValue(
          "Non-Electrical Accessories",
          addOnsAndOthers?.selectedAccesories,
          newGroupedQuotesCompare[2]?.motorNonElectricAccessoriesValue,
          newGroupedQuotesCompare[2]?.companyAlias
        ),
        generateAccessoryValue(
          "External Bi-Fuel Kit CNG/LPG",
          addOnsAndOthers?.selectedAccesories,
          newGroupedQuotesCompare[2]?.motorLpgCngKitValue,
          newGroupedQuotesCompare[2]?.companyAlias
        ),
        generateAccessoryValue(
          "Trailer",
          addOnsAndOthers?.selectedAccesories,
          newGroupedQuotesCompare[2]?.trailerValue,
          newGroupedQuotesCompare[2]?.companyAlias
        ),
      ]);
    } else {
      accessoriesArray.push([
        generateAccessoryValue(
          "Electrical Accessories",
          addOnsAndOthers?.selectedAccesories,
          newGroupedQuotesCompare[2]?.motorElectricAccessoriesValue,
          newGroupedQuotesCompare[2]?.companyAlias
        ),
        generateAccessoryValue(
          "Non-Electrical Accessories",
          addOnsAndOthers?.selectedAccesories,
          newGroupedQuotesCompare[2]?.motorNonElectricAccessoriesValue,
          newGroupedQuotesCompare[2]?.companyAlias
        ),
        TypeReturn(type) === "bike"
          ? ""
          : generateAccessoryValue(
              "External Bi-Fuel Kit CNG/LPG",
              addOnsAndOthers?.selectedAccesories,
              newGroupedQuotesCompare[2]?.motorLpgCngKitValue,
              newGroupedQuotesCompare[2]?.companyAlias
            ),
      ]);
    }
  }
  return accessoriesArray;
};
