import React from "react";
import { Table } from "react-bootstrap";
import Badges from "../Badges";
import { currencyFormater } from "utils";

export const OtherCoversTable = ({ quote, temp_data }) => {
  return (
    <Table className="discountTable">
      <tr>
        <td className="discountValues">
          {temp_data?.ownerTypeId === 2 ? (
            quote?.otherCovers?.legalLiabilityToEmployee === undefined ? (
              <Badges
                title={"Not Available"}
                name="other_cover_NA_value"
              />
            ) : (
              <Badges
                title={
                  quote?.otherCovers?.legalLiabilityToEmployee === 0
                    ? "Included"
                    : `
                          ₹
                          ${currencyFormater(
                            quote?.otherCovers?.legalLiabilityToEmployee
                          )}
                        `
                }
                name="other_cover_value"
              />
            )
          ) : (
            <Badges
              title={"Not Selected"}
              name="other_cover_NS_value"
            />
          )}
        </td>
      </tr>
    </Table>
  );
};

export const OtherCoversTable1 = ({ quote, temp_data }) => {
  return (
    <Table className="discountTable">
      <tr>
        <td className="discountValues">
          {temp_data?.ownerTypeId === 2 ? (
            quote?.otherCovers?.legalLiabilityToEmployee === undefined ? (
              <Badges
                title={"Not Available"}
                name="other_cover_NA_value"
              />
            ) : (
              <Badges
                title={
                  quote?.otherCovers?.legalLiabilityToEmployee === 0
                    ? "Included"
                    : `
                          ₹
                          ${currencyFormater(
                            quote?.otherCovers?.legalLiabilityToEmployee
                          )}
                        `
                }
                name="other_cover_value"
              />
            )
          ) : (
            <Badges
              title={"Not Selected"}
              name="other_cover_NS_value"
            />
          )}
        </td>
      </tr>
    </Table>
  );
};
