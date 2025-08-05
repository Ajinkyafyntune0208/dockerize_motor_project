import React from "react";
import { Table } from "react-bootstrap";
import _ from "lodash";
import Badges from "../Badges";
import { currencyFormater } from "utils";
import { BlockedSections } from "modules/quotesPage/addOnCard/cardConfig";
import { TypeReturn } from "modules/type";

export const AdditionalCoverTable = ({
  addOnsAndOthers,
  quote,
  type,
  temp_data,
  shortTerm,
}) => {
  return (
    <Table className="additionalCoverTable">
      <>
        {!temp_data?.odOnly &&
          temp_data.journeyCategory === "GCV" &&
          !shortTerm && (
            <>
              <tr>
                <td className="additionalCoverValues">
                  {addOnsAndOthers?.selectedAdditions?.includes(
                    "LL paid driver/conductor/cleaner"
                  ) ? (
                    Number(quote?.defaultPaidDriver) === 0 ||
                    quote?.defaultPaidDriver === "N/A" ? (
                      <Badges
                        title={"Not Available"}
                        name="LL_paid_dr/cd/cl_NA_value"
                      />
                    ) : (
                      <span name="LL_paid_dr/cd/cl_value">
                        ₹ {currencyFormater(quote?.defaultPaidDriver)}
                      </span>
                    )
                  ) : (
                    <Badges
                      title={"Not Selected"}
                      name="LL_paid_dr/cd/cl_NS_value"
                    />
                  )}
                </td>
              </tr>
              <tr>
                <td className="additionalCoverValues">
                  {addOnsAndOthers?.selectedAdditions?.includes(
                    "PA paid driver/conductor/cleaner"
                  ) ? (
                    Number(quote?.motorAdditionalPaidDriver) === 0 ||
                    quote?.motorAdditionalPaidDriver === "N/A" ? (
                      <Badges
                        title={"Not Available"}
                        name="PA_paid_driver_NA_value"
                      />
                    ) : (
                      <span name="PA_paid_driver_value">
                        ₹ {currencyFormater(quote?.motorAdditionalPaidDriver)}
                      </span>
                    )
                  ) : (
                    <Badges
                      title={"Not Selected"}
                      name="PA_paid_driver_NS_value"
                    />
                  )}
                </td>
              </tr>
            </>
          )}

        {temp_data.journeyCategory !== "GCV" && !temp_data?.odOnly && (
          <>
            {BlockedSections(
              import.meta.env.VITE_BROKER,
              temp_data?.journeyCategory
            )?.includes("unnamed pa cover") ? (
              <noscript />
            ) : (
              <tr>
                <td className="additionalCoverValues">
                  {addOnsAndOthers?.selectedAdditions?.includes(
                    "Unnamed Passenger PA Cover"
                  ) ||
                  quote?.includedAdditional?.included?.includes(
                    "coverUnnamedPassengerValue"
                  ) ? (
                    Number(quote?.coverUnnamedPassengerValue) === 0 ||
                    quote?.coverUnnamedPassengerValue === "N/A" ||
                    quote?.coverUnnamedPassengerValue === "NA" ? (
                      <Badges
                        title={"Not Available"}
                        name="unnamed_passenger_NA_value"
                      />
                    ) : (
                      <span name="unnamed_passenger_value">
                        ₹ 
                        {currencyFormater(
                          quote?.companyAlias === "sbi" &&
                            addOnsAndOthers?.selectedCpa?.includes(
                              "Compulsory Personal Accident"
                            ) &&
                            !_.isEmpty(addOnsAndOthers?.isTenure)
                            ? quote?.coverUnnamedPassengerValue *
                                (type === "bike" ? 5 : 3)
                            : quote?.coverUnnamedPassengerValue
                        )}
                      </span>
                    )
                  ) : (
                    <Badges
                      title={"Not Selected"}
                      name="unnamed_passenger_NS_value"
                    />
                  )}
                </td>
              </tr>
            )}
            <tr
              style={{
                display:
                  (shortTerm ||
                    TypeReturn(type) === "bike" ||
                    temp_data?.journeyCategory === "GCV" ||
                    temp_data?.journeyCategory === "MISC") &&
                  "none",
              }}
            >
              <td className="additionalCoverValues">
                {addOnsAndOthers?.selectedAdditions?.includes(
                  "PA cover for additional paid driver"
                ) ? (
                  !quote?.motorAdditionalPaidDriver ||
                  Number(quote?.motorAdditionalPaidDriver) === 0 ||
                  quote?.motorAdditionalPaidDriver === "N/A" ? (
                    <Badges
                      title={"Not Available"}
                      name="additional_paid_driver_NA_value"
                    />
                  ) : (
                    <span name="additional_paid_driver_value">
                      ₹ 
                      {currencyFormater(
                        quote?.companyAlias === "sbi" &&
                          addOnsAndOthers?.selectedCpa?.includes(
                            "Compulsory Personal Accident"
                          ) &&
                          !_.isEmpty(addOnsAndOthers?.isTenure)
                          ? quote?.motorAdditionalPaidDriver *
                              (type === "bike" ? 5 : 3)
                          : quote?.motorAdditionalPaidDriver
                      )}
                    </span>
                  )
                ) : (
                  <Badges
                    title={"Not Selected"}
                    name="additional_paid_driver_NS_value"
                  />
                )}
              </td>
            </tr>
            <tr>
              <td className="additionalCoverValues">
                {addOnsAndOthers?.selectedAdditions?.includes(
                  "LL paid driver"
                ) ? (
                  Number(quote?.defaultPaidDriver) === 0 ||
                  quote?.defaultPaidDriver === "N/A" ? (
                    <Badges
                      title={"Not Available"}
                      name="LL_paid_driver_NA_value"
                    />
                  ) : (
                    <span name="LL_paid_driver_value">
                      ₹ {currencyFormater(quote?.defaultPaidDriver)}
                    </span>
                  )
                ) : (
                  <Badges
                    title={"Not Selected"}
                    name="LL_paid_driver_NS_value"
                  />
                )}
              </td>
            </tr>
          </>
        )}
        {import.meta.env.VITE_BROKER !== "OLA" && (
          <tr>
            <td className="additionalCoverValues">
              {addOnsAndOthers?.selectedAdditions?.includes(
                "Geographical Extension"
              ) ? (
                Number(quote?.geogExtensionODPremium) === 0 ||
                quote?.geogExtensionODPremium === "N/A" ? (
                  <Badges
                    title={"Not Available"}
                    name="geog_extension_NA_value"
                  />
                ) : (
                  <span name="geog_extension_value">
                    ₹ {currencyFormater(quote?.geogExtensionODPremium)}
                  </span>
                )
              ) : (
                <Badges title={"Not Selected"} name="geog_extension_NS_value" />
              )}
            </td>
          </tr>
        )}
        {temp_data.journeyCategory === "GCV" && (
          <tr>
            <td className="additionalCoverValues">
              {addOnsAndOthers?.selectedAdditions?.includes("NFPP Cover") ? (
                Number(quote?.nfpp) === 0 || quote?.nfpp === "N/A" ? (
                  <Badges title={"Not Available"} name="nfpp_NA_value" />
                ) : (
                  <span name="nfpp_value">
                    ₹ {currencyFormater(quote?.nfpp)}
                  </span>
                )
              ) : (
                <Badges title={"Not Selected"} name="nfpp_NS_value" />
              )}
            </td>
          </tr>
        )}
      </>
    </Table>
  );
};

export const AdditionalCoverTable1 = ({
  addOnsAndOthers,
  quote,
  type,
  temp_data,
  shortTerm,
}) => {
  return (
    <Table className="additionalCoverTable">
      {!temp_data?.odOnly &&
        temp_data.journeyCategory === "GCV" &&
        !shortTerm && (
          <>
            <tr>
              <td className="additionalCoverValues">
                {addOnsAndOthers?.selectedAdditions?.includes(
                  "LL paid driver/conductor/cleaner"
                ) ? (
                  Number(quote?.defaultPaidDriver) === 0 ||
                  quote?.defaultPaidDriver === "N/A" ? (
                    <Badges
                      title={"Not Available"}
                      name="LL_paid_dr/cd/cl_NA_value"
                    />
                  ) : (
                    <span name="LL_paid_dr/cd/cl_value">
                      ₹ {currencyFormater(quote?.defaultPaidDriver)}
                    </span>
                  )
                ) : (
                  <Badges
                    title={"Not Selected"}
                    name="LL_paid_dr/cd/cl_NS_value"
                  />
                )}
              </td>
            </tr>
            <tr>
              <td className="additionalCoverValues">
                {addOnsAndOthers?.selectedAdditions?.includes(
                  "PA paid driver/conductor/cleaner"
                ) ? (
                  Number(quote?.motorAdditionalPaidDriver) === 0 ||
                  quote?.motorAdditionalPaidDriver === "N/A" ? (
                    <Badges
                      title={"Not Available"}
                      name="PA_paid_driver_NA_value"
                    />
                  ) : (
                    <span name="PA_paid_driver_value">
                      ₹ {currencyFormater(quote?.motorAdditionalPaidDriver)}
                    </span>
                  )
                ) : (
                  <Badges
                    title={"Not Selected"}
                    name="PA_paid_driver_NS_value"
                  />
                )}
              </td>
            </tr>
          </>
        )}

      {temp_data.journeyCategory !== "GCV" && !temp_data?.odOnly && (
        <>
          {BlockedSections(
            import.meta.env.VITE_BROKER,
            temp_data?.journeyCategory
          ).includes("unnamed pa cover") ? (
            <noscript />
          ) : (
            <tr>
              <td className="additionalCoverValues">
                {addOnsAndOthers?.selectedAdditions?.includes(
                  "Unnamed Passenger PA Cover"
                ) ||
                quote?.includedAdditional?.included?.includes(
                  "coverUnnamedPassengerValue"
                ) ? (
                  Number(quote?.coverUnnamedPassengerValue) === 0 ||
                  quote?.coverUnnamedPassengerValue === "N/A" ||
                  quote?.coverUnnamedPassengerValue === "NA" ? (
                    <Badges
                      title={"Not Available"}
                      name="unnamed_passenger_NA_value"
                    />
                  ) : (
                    <span name="unnamed_passenger_value">
                      ₹ 
                      {currencyFormater(
                        quote?.companyAlias === "sbi" &&
                          addOnsAndOthers?.selectedCpa?.includes(
                            "Compulsory Personal Accident"
                          ) &&
                          !_.isEmpty(addOnsAndOthers?.isTenure)
                          ? quote?.coverUnnamedPassengerValue *
                              (type === "bike" ? 5 : 3)
                          : quote?.coverUnnamedPassengerValue
                      )}
                    </span>
                  )
                ) : (
                  <Badges
                    title={"Not Selected"}
                    name="unnamed_passenger_NS_value"
                  />
                )}
              </td>
            </tr>
          )}
          <tr
            style={{
              display:
                (shortTerm ||
                  TypeReturn(type) === "bike" ||
                  temp_data?.journeyCategory === "GCV" ||
                  temp_data?.journeyCategory === "MISC") &&
                "none",
            }}
          >
            <td className="additionalCoverValues">
              {addOnsAndOthers?.selectedAdditions?.includes(
                "PA cover for additional paid driver"
              ) ? (
                !quote?.motorAdditionalPaidDriver ||
                Number(quote?.motorAdditionalPaidDriver) === 0 ||
                quote?.motorAdditionalPaidDriver === "N/A" ? (
                  <Badges
                    title={"Not Available"}
                    name="additional_paid_driver_NA_value"
                  />
                ) : (
                  <span name="additional_paid_driver_value">
                    ₹ 
                    {currencyFormater(
                      quote?.companyAlias === "sbi" &&
                        addOnsAndOthers?.selectedCpa?.includes(
                          "Compulsory Personal Accident"
                        ) &&
                        !_.isEmpty(addOnsAndOthers?.isTenure)
                        ? quote?.motorAdditionalPaidDriver *
                            (type === "bike" ? 5 : 3)
                        : quote?.motorAdditionalPaidDriver
                    )}
                  </span>
                )
              ) : (
                <Badges
                  title={"Not Selected"}
                  name="additional_paid_driver_NS_value"
                />
              )}
            </td>
          </tr>
          <tr>
            <td className="additionalCoverValues">
              {addOnsAndOthers?.selectedAdditions?.includes(
                "LL paid driver"
              ) ? (
                Number(quote?.defaultPaidDriver) === 0 ||
                quote?.defaultPaidDriver === "N/A" ? (
                  <Badges
                    title={"Not Available"}
                    name="LL_paid_driver_NA_value"
                  />
                ) : (
                  <span name="LL_paid_driver_value">
                    ₹ {currencyFormater(quote?.defaultPaidDriver)}
                  </span>
                )
              ) : (
                <Badges title={"Not Selected"} name="LL_paid_driver_NS_value" />
              )}
            </td>
          </tr>
        </>
      )}
      {import.meta.env.VITE_BROKER !== "OLA" && (
        <tr>
          <td className="additionalCoverValues">
            {addOnsAndOthers?.selectedAdditions?.includes(
              "Geographical Extension"
            ) ? (
              Number(quote?.geogExtensionODPremium) === 0 ||
              quote?.geogExtensionODPremium === "N/A" ? (
                <Badges
                  title={"Not Available"}
                  name="geog_extension_NA_value"
                />
              ) : (
                <span name="geog_extension_value">
                  ₹ {currencyFormater(quote?.geogExtensionODPremium)}
                </span>
              )
            ) : (
              <Badges title={"Not Selected"} name="geog_extension_NS_value" />
            )}
          </td>
        </tr>
      )}
      {temp_data.journeyCategory === "GCV" && (
        <tr>
          <td className="additionalCoverValues">
            {addOnsAndOthers?.selectedAdditions?.includes("NFPP Cover") ? (
              Number(quote?.nfpp) === 0 || quote?.nfpp === "N/A" ? (
                <Badges title={"Not Available"} name="nfpp_NA_value" />
              ) : (
                <span name="nfpp_value">
                  ₹ {currencyFormater(quote?.nfpp)}
                </span>
              )
            ) : (
              <Badges title={"Not Selected"} name="nfpp_NS_value" />
            )}
          </td>
        </tr>
      )}
    </Table>
  );
};
