import React from "react";
import { Table } from "react-bootstrap";
import Badges from "../Badges";
import { currencyFormater } from "utils";
import { TypeReturn } from "modules/type";

export const AccessoriesTable = ({ addOnsAndOthers, quote, type }) => {
  return (
    <Table className="accessoriesTable">
      <tr>
        <td className="accessoriesValues">
          {" "}
          {addOnsAndOthers?.selectedAccesories?.includes(
            "Electrical Accessories"
          ) ? (
            !quote?.motorElectricAccessoriesValue &&
            quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="electric_accessory" />
            ) : Number(quote?.motorElectricAccessoriesValue) === 0 &&
              quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="electric_accessory" />
            ) : quote?.companyAlias !== "godigit" ? (
              `₹ ${currencyFormater(
                Number(quote?.motorElectricAccessoriesValue)
              )}`
            ) : (
              <Badges
                title={TypeReturn(type) === "bike" ? "Not Available" : "Included"}
                // title={"Not Available"}
                name="electric_accessory"
              />
            )
          ) : (
            <Badges title={"Not Selected"} name="electric_accessory" />
          )}
        </td>
      </tr>
      <tr>
        <td className="accessoriesValues">
          {addOnsAndOthers?.selectedAccesories?.includes(
            "Non-Electrical Accessories"
          ) ? (
            !quote?.motorNonElectricAccessoriesValue &&
            quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="non_electric_accessory" />
            ) : Number(quote?.motorNonElectricAccessoriesValue) === 0 &&
              quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="non_electric_accessory" />
            ) : quote?.companyAlias !== "godigit" ? (
              `₹ ${currencyFormater(
                Number(quote?.motorNonElectricAccessoriesValue)
              )}`
            ) : (
              <Badges
                title={TypeReturn(type) === "bike" ? "Not Available" : "Included"}
                name="non_electric_accessory"
              />
            )
          ) : (
            <Badges title={"Not Selected"} name="non_electric_accessory" />
          )}
        </td>
      </tr>
      <tr style={{ display: TypeReturn(type) === "bike" && "none" }}>
        <td className="accessoriesValues">
          {addOnsAndOthers?.selectedAccesories?.includes(
            "External Bi-Fuel Kit CNG/LPG"
          ) || Number(quote?.motorLpgCngKitValue) ? (
            Number(quote?.motorLpgCngKitValue) === 0 &&
            quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="bi_fuel_kit_accessory" />
            ) : quote?.company_alias === "godigit" && TypeReturn(type) !== "bike" ? (
              "Included"
            ) : (
              `₹ ${currencyFormater(Number(quote?.motorLpgCngKitValue))}`
            )
          ) : (
            <Badges title={"Not Selected"} name="bi_fuel_kit_accessory" />
          )}
        </td>
      </tr>
    </Table>
  );
};

export const AccessoriesTable1 = ({ addOnsAndOthers, quote, type }) => {
  return (
    <Table className="accessoriesTable">
      <tr>
        {" "}
        <td className="accessoriesValues">
          {addOnsAndOthers?.selectedAccesories?.includes(
            "Electrical Accessories"
          ) ? (
            !quote?.motorElectricAccessoriesValue &&
            quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="electric_accessory" />
            ) : Number(quote?.motorElectricAccessoriesValue) === 0 &&
              quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="electric_accessory" />
            ) : quote?.companyAlias !== "godigit" ? (
              `₹ ${currencyFormater(
                Number(quote?.motorElectricAccessoriesValue)
              )}`
            ) : (
              <Badges title={"Included"} name="electric_accessory" />
            )
          ) : (
            <Badges title={"Not Selected"} name="electric_accessory" />
          )}
        </td>
      </tr>
      <tr>
        <td className="accessoriesValues">
          {addOnsAndOthers?.selectedAccesories?.includes(
            "Non-Electrical Accessories"
          ) ? (
            !quote?.motorNonElectricAccessoriesValue &&
            quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="non_electric_accessory" />
            ) : Number(quote?.motorNonElectricAccessoriesValue) === 0 &&
              quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="non_electric_accessory" />
            ) : quote?.companyAlias !== "godigit" ? (
              `₹ ${currencyFormater(
                Number(quote?.motorNonElectricAccessoriesValue)
              )}`
            ) : (
              <Badges title={"Included"} name="non_electric_accessory" />
            )
          ) : (
            <Badges title={"Not Selected"} name="non_electric_accessory" />
          )}
        </td>
      </tr>
      <tr style={{ display: TypeReturn(type) === "bike" && "none" }}>
        <td className="accessoriesValues">
          {addOnsAndOthers?.selectedAccesories?.includes(
            "External Bi-Fuel Kit CNG/LPG"
          ) ? (
            Number(quote?.motorLpgCngKitValue) === 0 &&
            quote?.companyAlias !== "godigit" ? (
              <Badges title={"Not Available"} name="bi_fuel_kit_accessory" />
            ) : quote?.company_alias === "godigit" ? (
              "Included"
            ) : (
              `₹ ${currencyFormater(Number(quote?.motorLpgCngKitValue))}`
            )
          ) : (
            <Badges title={"Not Selected"} name="bi_fuel_kit_accessory" />
          )}
        </td>
      </tr>
    </Table>
  );
};
