import React from "react";
import { Table } from "react-bootstrap";
import Badges from "../Badges";
import { currencyFormater } from "utils";
import { TypeReturn } from "modules/type";
import { BlockedSections } from "modules/quotesPage/addOnCard/cardConfig";

export const DiscountTable = ({ addOnsAndOthers, quote, type, temp_data }) => {
  return (
    <Table className="discountTable">
      <>
        {temp_data?.journeyCategory !== "GCV" && (
          <tr>
            <td className="discountValues">
              {addOnsAndOthers?.selectedDiscount?.includes(
                "Is the vehicle fitted with ARAI approved anti-theft device?"
              ) ? (
                !quote?.antitheftDiscount ? (
                  <Badges
                    title={"Not Available"}
                    name="antitheft_NA_value"
                  />
                ) : (
                  <Badges
                    title={
                      quote?.antitheftDiscount === "" ||
                      quote?.antitheftDiscount === 0
                        ? "Not Available"
                        : `
                          ₹
                          ${currencyFormater(quote?.antitheftDiscount)}
                        `
                    }
                    name="antitheft_value"
                  />
                )
              ) : (
                <Badges
                  title={"Not Selected"}
                  name="antitheft_NS_value"
                />
              )}
            </td>
          </tr>
        )}
        {TypeReturn(type) !== "cv" &&
          !BlockedSections(import.meta.env.VITE_BROKER).includes(
            "voluntary discount"
          ) && (
            <tr>
              <td className="discountValues">
                {addOnsAndOthers?.selectedDiscount?.includes(
                  "Voluntary Discounts"
                ) ? (
                  !quote?.voluntaryExcess ? (
                    <Badges
                      title={"Not Available"}
                      name="voluntary_NA_value"
                    />
                  ) : (
                    <Badges
                      title={
                        quote?.voluntaryExcess === 0
                          ? "Not Available"
                          : `₹ ${Math.round(quote?.voluntaryExcess)} `
                      }
                      name="voluntary_value"
                    />
                  )
                ) : (
                  <Badges
                    title={"Not Selected"}
                    name="voluntary_NS_value"
                  />
                )}
              </td>
            </tr>
          )}
        {TypeReturn(type) === "cv" && (
          <tr>
            <td className="discountValues">
              {addOnsAndOthers?.selectedDiscount?.includes(
                "Vehicle Limited to Own Premises"
              ) ? (
                quote?.limitedtoOwnPremisesOD ? (
                  <Badges
                    title={`₹ ${quote?.limitedtoOwnPremisesOD}`}
                    name="limited_to_own_premises_value"
                  />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="limited_to_own_premises_NA_value"
                  />
                )
              ) : (
                <Badges
                  title={"Not Selected"}
                  name="limited_to_own_premises_NS_value"
                />
              )}
            </td>
          </tr>
        )}

        <tr style={{ display: temp_data?.odOnly && "none" }}>
          <td className="discountValues">
            {addOnsAndOthers?.selectedDiscount?.includes("TPPD Cover") ? (
              quote?.tppdDiscount ? (
                <Badges
                  title={`₹ ${quote?.tppdDiscount}`}
                  name="tppd_value"
                />
              ) : (
                <Badges
                  title={"Not Available"}
                  name="tppd_NA_value"
                />
              )
            ) : (
              <Badges
                title={"Not Selected"}
                name="tppd_NS_value"
              />
            )}
          </td>
        </tr>
      </>
    </Table>
  );
};

export const DiscountTable1 = ({ addOnsAndOthers, quote, type, temp_data }) => {
  return (
    <Table className="discountTable">
      <>
        {temp_data?.journeyCategory !== "GCV" && (
          <tr>
            <td className="discountValues">
              {addOnsAndOthers?.selectedDiscount?.includes(
                "Is the vehicle fitted with ARAI approved anti-theft device?"
              ) ? (
                !quote?.antitheftDiscount ? (
                  <Badges
                    title={"Not Available"}
                    name="antitheft_NA_value"
                  />
                ) : (
                  <Badges
                    title={
                      quote?.antitheftDiscount === "" ||
                      quote?.antitheftDiscount === 0
                        ? "Not Available"
                        : `
                    ₹
                    ${currencyFormater(quote?.antitheftDiscount)}
                  `
                    }
                    name="antitheft_value"
                  />
                )
              ) : (
                <Badges
                  title={"Not Selected"}
                  name="antitheft_NS_value"
                />
              )}
            </td>
          </tr>
        )}

        {TypeReturn(type) !== "cv" &&
          !BlockedSections(import.meta.env.VITE_BROKER).includes(
            "voluntary discount"
          ) && (
            <tr>
              <td className="discountValues">
                {addOnsAndOthers?.selectedDiscount?.includes(
                  "Voluntary Discounts"
                ) ? (
                  !quote?.voluntaryExcess ? (
                    <Badges
                      title={"Not Available"}
                      name="voluntary_NA_value"
                    />
                  ) : (
                    <Badges
                      title={
                        quote?.voluntaryExcess == 0
                          ? "Not Available"
                          : ` ₹ ${Math.round(quote?.voluntaryExcess)} `
                      }
                      name="voluntary_value"
                    />
                  )
                ) : (
                  <Badges
                    title={"Not Selected"}
                    name="voluntary_NS_value"
                  />
                )}
              </td>
            </tr>
          )}
        {TypeReturn(type) === "cv" && (
          <tr>
            <td className="discountValues">
              {addOnsAndOthers?.selectedDiscount?.includes(
                "Vehicle Limited to Own Premises"
              ) ? (
                quote?.limitedtoOwnPremisesOD ? (
                  <Badges
                    title={`₹ ${quote?.limitedtoOwnPremisesOD}`}
                    name="limited_to_own_premises_value"
                  />
                ) : (
                  <Badges
                    title={"Not Available"}
                    name="limited_to_own_premises_NA_value"
                  />
                )
              ) : (
                <Badges
                  title={"Not Selected"}
                  name="limited_to_own_premises_NS_value"
                />
              )}
            </td>
          </tr>
        )}

        <tr style={{ display: temp_data?.odOnly && "none" }}>
          <td className="discountValues">
            {addOnsAndOthers?.selectedDiscount?.includes("TPPD Cover") ? (
              quote?.tppdDiscount ? (
                <Badges
                  title={`₹ ${quote?.tppdDiscount}`}
                  name="tppd_value"
                />
              ) : (
                <Badges
                  title={"Not Available"}
                  name="tppd_NA_value"
                />
              )
            ) : (
              <Badges
                title={"Not Selected"}
                name="tppd_NS_value"
              />
            )}
          </td>
        </tr>
      </>
    </Table>
  );
};
