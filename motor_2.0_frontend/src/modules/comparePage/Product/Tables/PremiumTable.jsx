import React from "react";
import { Table } from "react-bootstrap";
import _ from "lodash";
import { currencyFormater } from "utils";

export const PremiumTable = ({ quote }) => {
  return (
    <Table className="premiumTable">
      <tr>
        <td
          className="premiumValues"
          name="total_premiumA1_value"
        >
          ₹ {currencyFormater(quote?.totalPremiumA1)}
        </td>
      </tr>

      <tr>
        <td
          className="premiumValues"
          name="total_premiumB1_value"
        >
          ₹{" "}
          {currencyFormater(
            quote?.totalPremiumB1 - (quote?.tppdDiscount * 1 || 0)
          )}
        </td>
      </tr>
      <tr>
        <td
          className="premiumValues"
          name="total_addon1_value"
        >
          ₹ {currencyFormater(quote?.totalAddon1)}
        </td>
      </tr>
      <tr>
        <td
          className="premiumValues"
          name="total_premiumC1_value"
        >
          ₹ {currencyFormater(quote?.totalPremiumc1)}
        </td>
      </tr>
      <tr>
        <td
          className="premiumValues"
          name="gst1_value"
        >
          ₹ {currencyFormater(quote?.gst1)}
        </td>
      </tr>
      <tr>
        <td
          className="premiumValues"
          name="final_premium1_value"
        >
          <strong> ₹ {currencyFormater(quote?.finalPremium1)}</strong>
        </td>
      </tr>
    </Table>
  );
};

export const PremiumTable1 = ({ quote }) => {
  return (
    <Table className="premiumTable">
      <tr>
        <td
          className="premiumValues"
          name="total_premiumA1_value"
        >
          ₹ {currencyFormater(quote?.totalPremiumA1)}
        </td>
      </tr>

      <tr>
        <td
          className="premiumValues"
          name="total_premiumB1_value"
        >
          ₹{" "}
          {currencyFormater(
            quote?.totalPremiumB1 - (quote?.tppdDiscount * 1 || 0)
          )}
        </td>
      </tr>
      <tr>
        <td
          className="premiumValues"
          name="total_addon1_value"
        >
          ₹ {currencyFormater(quote?.totalAddon1)}
        </td>
      </tr>
      <tr>
        <td
          className="premiumValues"
          name="total_premiumC1_value"
        >
          ₹ {currencyFormater(quote?.totalPremiumc1)}
        </td>
      </tr>
      <tr>
        <td
          className="premiumValues"
          name="gst1_value"
        >
          ₹ {currencyFormater(quote?.gst1)}
        </td>
      </tr>
      <tr>
        <td
          className="premiumValues totalPremiumDiv"
          name="final_premium1_value"
        >
          <strong> ₹ {currencyFormater(quote?.finalPremium1)}</strong>
        </td>
      </tr>
    </Table>
  );
};
