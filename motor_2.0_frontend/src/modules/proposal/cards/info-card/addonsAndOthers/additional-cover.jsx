import React from "react";
import { DivBenefits, DivTag, PBenefits, PTag, RowTag } from "../info-style";
import { Badge } from "react-bootstrap";
import { currencyFormater } from "utils";

const AdditionalCover = ({
  lessthan767,
  showCoversInfo,
  coversInfo,
  FilteredAdditionalCovers,
  Theme,
  temp_data,
  FilteredCPA,
}) => {
  return (
    <RowTag>
      <DivBenefits margin={lessthan767}>
        {lessthan767 ? (
          <PTag onClick={() => showCoversInfo((prev) => !prev)}>
            Additional Covers
            <i
              style={{
                fontSize: "18px",
                position: "relative",
                top: "2.2px",
              }}
              className={
                coversInfo ? "ml-1 fa fa-angle-up" : "ml-1 fa fa-angle-down"
              }
            ></i>
          </PTag>
        ) : (
          <PTag>Additional Covers</PTag>
        )}
        {coversInfo && (
          <DivTag>
            {FilteredAdditionalCovers?.map(
              (
                {
                  name,
                  sumInsured,
                  lLNumberCleaner,
                  lLNumberConductor,
                  lLNumberDriver,
                  premium,
                },
                index
              ) => (
                <>
                  <PBenefits key={index}>
                    {name !== "Geographical Extension" && (
                      <span className="mr-1">
                        <i
                          className={`fa fa-star ${
                            Theme?.sideCardProposal?.icon
                              ? Theme?.sideCardProposal?.icon
                              : "text-success"
                          }`}
                        />
                      </span>
                    )}
                    <span name="additional_cover">
                      {name !== "Geographical Extension" ? name : ""}
                    </span>
                    {((sumInsured * 1 || premium * 1) &&
                      name !== "LL paid driver" &&
                      name !== "Geographical Extension") ||
                    name === "LL paid driver/conductor/cleaner" ? (
                      <>
                        {name === "LL paid driver/conductor/cleaner" ? (
                          <>
                            <br />
                            {lLNumberCleaner ? (
                              <Badge
                                variant={
                                  Theme?.sideCardProposal?.badge
                                    ? Theme?.sideCardProposal?.badge
                                    : `success`
                                }
                                name="no_of_cleaners"
                              >{`No. of Cleaners: ${
                                temp_data?.selectedQuote?.companyAlias ===
                                "godigit"
                                  ? 1
                                  : Number(lLNumberCleaner) < 3
                                  ? lLNumberCleaner
                                  : 2
                              }`}</Badge>
                            ) : (
                              <noscript />
                            )}
                            {lLNumberConductor ? (
                              <Badge
                                variant={
                                  Theme?.sideCardProposal?.badge
                                    ? Theme?.sideCardProposal?.badge
                                    : `success`
                                }
                                className={lLNumberCleaner ? "mx-1" : "mr-1"}
                                name="no_of_conductors"
                              >{`No. of Conductors: ${
                                temp_data?.selectedQuote?.companyAlias ===
                                "godigit"
                                  ? 1
                                  : Number(lLNumberConductor) < 3
                                  ? lLNumberConductor
                                  : 2
                              }`}</Badge>
                            ) : (
                              <noscript />
                            )}
                            {lLNumberDriver ? (
                              <Badge
                                variant={
                                  Theme?.sideCardProposal?.badge
                                    ? Theme?.sideCardProposal?.badge
                                    : `success`
                                }
                                className={
                                  lLNumberConductor && lLNumberCleaner
                                    ? ""
                                    : lLNumberConductor || lLNumberCleaner
                                    ? "mx-1"
                                    : "mr-1"
                                }
                                name="no_of_drivers"
                              >{`No. of Drivers: ${
                                temp_data?.selectedQuote?.companyAlias ===
                                "godigit"
                                  ? 1
                                  : Number(lLNumberDriver) < 3
                                  ? lLNumberDriver
                                  : 2
                              }`}</Badge>
                            ) : (
                              <noscript />
                            )}
                          </>
                        ) : (
                          <>
                            <br />
                            <Badge
                              variant={
                                Theme?.sideCardProposal?.badge
                                  ? Theme?.sideCardProposal?.badge
                                  : `success`
                              }
                              name="sum_insured_premium"
                            >{`₹ ${sumInsured || premium}`}</Badge>
                          </>
                        )}
                      </>
                    ) : name === "Geographical Extension" ? (
                      <>
                        {" "}
                        <span className="mr-1">
                          <i
                            className={`fa fa-star ${
                              Theme?.sideCardProposal?.icon
                                ? Theme?.sideCardProposal?.icon
                                : "text-success"
                            }`}
                          />
                        </span>
                        <text>Geographical Extension</text>
                        <br />
                        {temp_data?.selectedQuote?.geogExtensionODPremium ? (
                          <Badge
                            variant={
                              Theme?.sideCardProposal?.badge
                                ? Theme?.sideCardProposal?.badge
                                : `success`
                            }
                            name="geog_extension_od_premium"
                          >
                            {` ₹ ${currencyFormater(
                              temp_data?.selectedQuote?.geogExtensionODPremium
                            )} (OD)`}
                          </Badge>
                        ) : (
                          <noscript />
                        )}
                        {temp_data?.selectedQuote?.geogExtensionTPPremium ? (
                          <Badge
                            style={{ marginLeft: "5px" }}
                            variant={
                              Theme?.sideCardProposal?.badge
                                ? Theme?.sideCardProposal?.badge
                                : `success`
                            }
                            name="geog_extension_tp_premium"
                          >
                            {` ₹ ${currencyFormater(
                              temp_data?.selectedQuote?.geogExtensionTPPremium
                            )} (TP)`}
                          </Badge>
                        ) : (
                          <noscript />
                        )}
                      </>
                    ) : (
                      <noscript />
                    )}
                  </PBenefits>
                </>
              )
            )}
            {FilteredCPA?.map(({ name, sumInsured }, index) => (
              <PBenefits key={index}>
                {name ? (
                  <span className="mr-1">
                    <i
                      className={`fa fa-star ${
                        Theme?.sideCardProposal?.icon
                          ? Theme?.sideCardProposal?.icon
                          : "text-success"
                      }`}
                    />
                  </span>
                ) : (
                  <noscript />
                )}
                <span name="cpa">{name}</span>
                {sumInsured * 1 ? (
                  <>
                    <br />
                    <Badge
                      variant={
                        Theme?.sideCardProposal?.badge
                          ? Theme?.sideCardProposal?.badge
                          : `success`
                      }
                      name="sum_insured"
                    >{`₹ ${sumInsured}`}</Badge>
                  </>
                ) : (
                  <noscript />
                )}
              </PBenefits>
            ))}
          </DivTag>
        )}
      </DivBenefits>
    </RowTag>
  );
};

export default AdditionalCover;
