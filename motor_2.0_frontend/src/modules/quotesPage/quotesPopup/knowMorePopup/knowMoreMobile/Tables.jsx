import React from "react";
import { camelToUnderscore, currencyFormater } from "utils";
import Style from "../style";
import _ from "lodash";
import { Badge } from "react-bootstrap";
import { TypeReturn } from "modules/type";
import { getAddonName } from "modules/quotesPage/quoteUtil";
import { GetAddonValue } from "modules/helper";

export const FinalCalculation = ({
  quote,
  totalAddon,
  totalPremiumA,
  totalPremiumB,
  totalPremiumC,
  totalPremium,
  finalPremium,
  gst,
  extraLoading,
}) => {
  return (
    <Style.PremiumBreakupMobSection>
      <div className="premiumBreakupMobSection__header">
        <div> Total OD Payable (A + D - C) </div>
        <div className="premText">
          {" "}
          ₹{" "}
          {currencyFormater(
            quote?.totalOdPayable ||
              (totalPremiumA * 1 || 0) +
                (totalAddon * 1 || 0) -
                ((totalPremiumC * 1 || 0) - (quote?.tppdDiscount * 1 || 0)) +
                (extraLoading * 1 || 0)
          )}
        </div>
      </div>
      <div className="premiumBreakupMobSection__header">
        <div> Total TP Payable (B) </div>
        <div className="premText">
          {" "}
          ₹ {currencyFormater(totalPremiumB - (quote?.tppdDiscount * 1 || 0))}
        </div>
      </div>
      <div className="premiumBreakupMobSection__header">
        <div> Net Premium </div>
        <div className="premText"> ₹ {currencyFormater(totalPremium)}</div>
      </div>
      <div className="premiumBreakupMobSection__header">
        <div> GST </div>
        <div className="premText"> ₹ {currencyFormater(gst)}</div>
      </div>
      <div className="premiumBreakupMobSection__header">
        <div> Final Premium </div>
        <div className="premText"> ₹ {currencyFormater(finalPremium)}</div>
      </div>
    </Style.PremiumBreakupMobSection>
  );
};

export const Liability = ({
  quote,
  addOnsAndOthers,
  temp_data,
  type,
  llpdCon,
  totalPremiumB,
}) => {
  return (
    <Style.PremiumBreakupMobSection>
      <div className="premiumBreakupMobSection__header">Liability</div>
      <div className="premiumBreakupMobSection__content">
        <div>Third Party Liability : </div>
        <div className="premText">
          {" "}
          ₹ {currencyFormater(quote?.tppdPremiumAmount)}
        </div>
      </div>
      <div className="premiumBreakupMobSection__content">
        <div>LPG/CNG Kit TP : </div>
        <div className="premText">
          {(quote?.cngLpgTp * 1 || quote?.includedAdditional?.included.includes("cngLpgTp")) ? "₹" : ""}{" "}
          {quote?.cngLpgTp * 1 || quote?.includedAdditional?.included.includes("cngLpgTp") ? currencyFormater(quote?.cngLpgTp) : "N/A"}
        </div>
      </div>
      {(addOnsAndOthers?.selectedDiscount?.includes("TPPD Cover") 
      || quote?.includedAdditional?.included.includes("TPPD Cover"))
      && (
        <div className="premiumBreakupMobSection__content">
          <div>TPPD Cover : </div>
          <div className="premText">
            {" "}
            - ₹ {currencyFormater(quote?.tppdDiscount)}
          </div>
        </div>
      )}
      {(addOnsAndOthers?.selectedAdditions?.includes(
        "Unnamed Passenger PA Cover"
      ) ||
        quote?.includedAdditional?.included?.includes(
          "coverUnnamedPassengerValue"
        )) &&
      !(
        quote?.includedAdditional?.included?.includes(
          "coverUnnamedPassengerValue"
        ) && !quote?.coverUnnamedPassengerValue * 1
      ) ? (
        <div className="premiumBreakupMobSection__content">
          <div>PA For Unnamed Passenger : </div>
          <div className="premText">
            {" "}
            {(quote?.coverUnnamedPassengerValue === "NA" || quote?.includedAdditional?.included.includes("coverUnnamedPassengerValue")) ||
            !(quote?.coverUnnamedPassengerValue * 1)
              ? "N/A"
              : `₹
                    ${currencyFormater(
                      quote?.companyAlias === "sbi" &&
                        addOnsAndOthers?.selectedCpa?.includes(
                          "Compulsory Personal Accident"
                        ) || quote?.includedAdditional?.included.includes("Compulsory Personal Accident") &&
                        !_.isEmpty(addOnsAndOthers?.isTenure)
                        ? quote?.coverUnnamedPassengerValue *
                            (type === "bike" ? 5 : 3)
                        : quote?.coverUnnamedPassengerValue
                    )}`}
          </div>
        </div>
      ) : quote?.includedAdditional?.included?.includes(
          "coverUnnamedPassengerValue"
        ) ? (
        <div className="premiumBreakupMobSection__content">
          <div>PA For Unnamed Passenger : </div>
          <div className="premText">
            <Badge
              variant="primary"
              style={{
                position: "relative",
                bottom: "2px",
              }}
            >
              Included
            </Badge>
          </div>
        </div>
      ) : (
        <noscript />
      )}

      {addOnsAndOthers?.selectedAdditions?.includes(
        "PA cover for additional paid driver"
      ) ||
      addOnsAndOthers?.selectedAdditions?.includes(
        "PA paid driver/conductor/cleaner"
      ) || 
      quote?.includedAdditional?.included.includes("PA cover for additional paid driver")
        || 
      quote?.includedAdditional?.included.includes("PA paid driver/conductor/cleaner")  
      ? (
        <div className="premiumBreakupMobSection__content">
          <div>Additional PA Cover To Paid Driver : </div>
          <div className="premText">
            {" "}
            {quote?.motorAdditionalPaidDriver * 1
              ? `₹
                    ${currencyFormater(
                      quote?.companyAlias === "sbi" &&
                        addOnsAndOthers?.selectedCpa?.includes(
                          "Compulsory Personal Accident"
                        ) &&
                        !_.isEmpty(addOnsAndOthers?.isTenure)
                        ? quote?.motorAdditionalPaidDriver *
                            (type === "bike" ? 5 : 3)
                        : quote?.motorAdditionalPaidDriver
                    )}`
              : "N/A"}
          </div>
        </div>
      ) : (
        <noscript />
      )}

      {(addOnsAndOthers?.selectedAdditions?.includes(
        "PA paid driver/conductor/cleaner"
      ) || quote?.includedAdditional?.included.includes("PA paid driver/conductor/cleaner")) && (
        <div className="premiumBreakupMobSection__content">
          <div>PA Cover To Paid Driver/Conductor/Cleaner: </div>
          <div className="premText">
            {" "}
            ₹ {currencyFormater(quote?.motorAdditionalPaidDriver)}
          </div>
        </div>
      )}
      {addOnsAndOthers?.selectedAdditions?.includes("LL paid driver") 
      || quote?.includedAdditional?.included.includes("LL paid driver")
      ? (
        <div className="premiumBreakupMobSection__content">
          <div>Legal Liability To Paid Driver : </div>
          <div className="premText">
            {" "}
            ₹ {currencyFormater(quote?.defaultPaidDriver)}
          </div>
        </div>
      ) : (
        <noscript />
      )}
      {quote?.limitedtoOwnPremisesTP ? (
        <div className="premiumBreakupMobSection__content">
          <div>Vehicle limited to own premises : </div>
          <div className="premText">
            {" "}
            ₹ {currencyFormater(quote?.limitedtoOwnPremisesTP)}
          </div>
        </div>
      ) : (
        <noscript />
      )}
      {(addOnsAndOthers?.selectedAdditions?.includes(
        "Geographical Extension"
      ) || quote?.includedAdditional?.included.includes("Geographical Extension")) && (
        <div className="premiumBreakupMobSection__content">
          <div>Geographical Extension : </div>
          <div className="premText">
            {" "}
            ₹ {currencyFormater(quote?.geogExtensionTPPremium)}
          </div>
        </div>
      )}
      {(addOnsAndOthers?.selectedAdditions?.includes("NFPP Cover") 
      || quote?.includedAdditional?.included.includes("NFPP Cover"))
      && (
        <div className="premiumBreakupMobSection__content">
          <div>NFPP Cover : </div>
          <div className="premText"> ₹ {currencyFormater(quote?.nfpp)}</div>
        </div>
      )}
      {(addOnsAndOthers?.selectedAdditions?.includes(
        "LL paid driver/conductor/cleaner"
      ) || quote?.includedAdditional?.included.includes("LL paid driver/conductor/cleaner")) &&
        (!llpdCon ? (
          <div className="premiumBreakupMobSection__content">
            <div>Legal Liability To Paid Driver/Conductor/Cleaner :</div>
            <div className="premText">
              {quote?.defaultPaidDriver * 1
                ? `₹ ${currencyFormater(quote?.defaultPaidDriver)}`
                : "N/A"}
            </div>
          </div>
        ) : (
          <>
            <div className="premiumBreakupMobSection__content">
              <div>{"Legal Liability To Paid Driver"} : </div>
              <div className="premText">
                {quote?.llPaidDriverPremium * 1
                  ? `₹ ${currencyFormater(quote?.llPaidDriverPremium)}`
                  : "N/A"}
              </div>
            </div>
            <div className="premiumBreakupMobSection__content">
              <div>
                {`Legal Liability To Paid Conductor ${
                  quote?.companyAlias === "icici_lombard" ||
                  quote?.companyAlias === "magma" ||
                  quote?.companyAlias === "cholla_mandalam" ||
                  quote?.companyAlias === "royal_sundaram" || 
                  quote?.companyAlias === "sbi"
                    ? "/Cleaner"
                    : ""
                }`}{" "}
                :{" "}
              </div>
              <div className="premText">
                {quote?.llPaidConductorPremium * 1
                  ? `₹ ${currencyFormater(quote?.llPaidConductorPremium)}`
                  : "N/A"}
              </div>
            </div>
            {quote?.companyAlias === "icici_lombard" ||
            quote?.companyAlias === "magma" ||
            quote?.companyAlias === "cholla_mandalam" ||
            quote?.companyAlias === "royal_sundaram" ? (
              <div className="premiumBreakupMobSection__content">
                <div>{"Legal Liability To Paid Cleaner"} : </div>
                <div className="premText">
                  {quote?.llPaidCleanerPremium * 1
                    ? `₹ ${currencyFormater(quote?.llPaidCleanerPremium)}`
                    : "N/A"}
                </div>
              </div>
            ) : (
              <noscript />
            )}
          </>
        ))}
      {(quote?.otherCovers?.legalLiabilityToEmployee !== undefined 
       || quote?.includedAdditional?.included.includes("legalLiabilityToEmployee"))
      &&
      temp_data?.ownerTypeId === 2 ? (
        <div className="premiumBreakupMobSection__content">
          <div>{"Legal Liability To Employee"} : </div>
          <div className="premText">
            {quote?.otherCovers?.legalLiabilityToEmployee * 1 === 0
              ? "Included"
              : `₹ ${currencyFormater(
                  quote?.otherCovers?.legalLiabilityToEmployee
                )}`}
          </div>
        </div>
      ) : (
        <noscript />
      )}
      {temp_data?.ownerTypeId === 1 && !temp_data?.odOnly && (
        <>
          <div className="premiumBreakupMobSection__content">
            <div style={{ display: "flex", justifyContent: "flex-start" }}>
              <text
                style={{ cursor: "pointer" }}
                onClick={() => {
                  document.getElementById(`Compulsory Personal Accident`) &&
                    document
                      .getElementById(`Compulsory Personal Accident`)
                      .click();
                }}
              >
                Compulsory PA Cover For Owner Driver{" "}
              </text>
              <Style.MFilterMenuBoxCheckConatiner>
                <div className="filterMenuBoxCheck">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    checked={addOnsAndOthers?.selectedCpa?.includes(
                      "Compulsory Personal Accident"
                    )}
                    onClick={() => {
                      document.getElementById(`Compulsory Personal Accident`) &&
                        document
                          .getElementById(`Compulsory Personal Accident`)
                          .click();
                    }}
                  />

                  <label
                    style={{ border: "none" }}
                    className="form-check-label"
                    htmlFor={"Compulsory Personal Accident"}
                  ></label>
                </div>
              </Style.MFilterMenuBoxCheckConatiner>{" "}
            </div>

            <div className="premText">
              {" "}
              {addOnsAndOthers?.selectedCpa?.includes(
                "Compulsory Personal Accident"
              ) ? (
                !_.isEmpty(addOnsAndOthers?.isTenure) ? (
                  !quote?.multiYearCpa * 1 ? (
                    "N/A"
                  ) : (
                    <>₹ {currencyFormater(quote?.multiYearCpa)}</>
                  )
                ) : !quote?.compulsoryPaOwnDriver * 1 ? (
                  "N/A"
                ) : (
                  <>₹ {currencyFormater(quote?.compulsoryPaOwnDriver)}</>
                )
              ) : (
                <>₹ 0</>
              )}
            </div>
          </div>
        </>
      )}
      <div className="premiumBreakupMobSection__header">
        <div> Total Liability Premium (B) </div>
        <div className="premText">
          {" "}
          ₹ {currencyFormater(totalPremiumB - (quote?.tppdDiscount * 1 || 0))}
        </div>
      </div>
    </Style.PremiumBreakupMobSection>
  );
};

export const MobileAddons = ({
  quote,
  claimList,
  claimList_gdd,
  setZdlp,
  setZdlp_gdd,
  zdlp,
  zdlp_gdd,
  type,
  addonDiscountPercentage,
  addOnsAndOthers,
  lessthan993,
  othersList,
  totalAddon,
  others,
}) => {
  return (
    <Style.PremiumBreakupMobSection>
      <div className="premiumBreakupMobSection__header">
        {quote?.applicableAddons.includes("imt23")
          ? "Addons & Covers"
          : "Addons"}
      </div>
      <>
        {quote?.company_alias === "godigit" &&
          ((quote?.gdd !== "Y" &&
            !_.isEmpty(claimList) &&
            claimList.length > 1) ||
            (quote?.gdd === "Y" &&
              !_.isEmpty(claimList_gdd) &&
              claimList_gdd.length > 1)) && (
            <div
              style={{
                background: "#E0E0E0",
                borderRadius: "5px",
                color: "black",
                fontWeight: "600",
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
                margin: "5px 0px",
                padding: "2px 0px 3px 7px",
              }}
            >
              <div style={{ fontSize: "11px" }}>Zero-dep claim</div>
              <div className="text-right" style={{ fontSize: "14px" }}>
                {quote?.gdd !== "Y" ? (
                  !_.isEmpty(claimList) && claimList.length > 1 ? (
                    <>
                      {
                        <Badge
                          variant={"light"}
                          className="mx-1"
                          style={
                            claimList.sort().indexOf(zdlp) > 0
                              ? {
                                  color: "red",
                                  position: "relative",
                                  bottom: "1px",
                                }
                              : {
                                  visibility: "hidden",
                                }
                          }
                          onClick={() =>
                            setZdlp(
                              claimList[claimList.sort().indexOf(zdlp) - 1]
                            )
                          }
                        >
                          <i className="fa fa-minus"></i>
                        </Badge>
                      }
                      <Badge
                        style={{
                          fontSize: ["BAJAJ", "ACE", "SRIDHAR"].includes(
                            import.meta.env.VITE_BROKER || ""
                          )
                            ? "11px"
                            : "12px",
                        }}
                      >
                        {zdlp === "ONE"
                          ? "ONE CLAIM"
                          : zdlp === "TWO"
                          ? "TWO CLAIM"
                          : `${zdlp}`}
                      </Badge>
                      <Badge
                        variant={"light"}
                        className="mx-1 mb-1"
                        style={
                          claimList.sort().indexOf(zdlp) <
                          claimList?.length * 1 - 1
                            ? {
                                color: "green",
                                position: "relative",
                                bottom: "1px",
                              }
                            : {
                                visibility: "hidden",
                              }
                        }
                        onClick={() =>
                          setZdlp(claimList[claimList.sort().indexOf(zdlp) + 1])
                        }
                      >
                        <i className="fa fa-plus"></i>
                      </Badge>
                    </>
                  ) : (
                    <noscript />
                  )
                ) : !_.isEmpty(claimList_gdd) && claimList_gdd.length > 1 ? (
                  <>
                    <Badge
                      variant={"light"}
                      className="mx-1"
                      style={
                        claimList_gdd.sort().indexOf(zdlp_gdd) > 0
                          ? {
                              color: "red",
                              position: "relative",
                              bottom: "1px",
                            }
                          : {
                              visibility: "hidden",
                            }
                      }
                      onClick={() =>
                        setZdlp_gdd(
                          claimList_gdd[
                            claimList_gdd.sort().indexOf(zdlp_gdd) - 1
                          ]
                        )
                      }
                    >
                      <i className="fa fa-minus"></i>
                    </Badge>
                    <Badge
                      style={{
                        fontSize: ["BAJAJ", "ACE", "SRIDHAR"].includes(
                          import.meta.env.VITE_BROKER || ""
                        )
                          ? "11px"
                          : "12px",
                      }}
                    >
                      {zdlp_gdd === "ONE"
                        ? "ONE CLAIM"
                        : zdlp_gdd === "TWO"
                        ? "TWO CLAIM"
                        : `${zdlp_gdd}`}
                    </Badge>
                    <Badge
                      variant={"light"}
                      className="mx-1 mb-1"
                      style={
                        claimList_gdd.sort().indexOf(zdlp_gdd) <
                        claimList_gdd?.length * 1 - 1
                          ? {
                              color: "green",
                              position: "relative",
                              bottom: "1px",
                            }
                          : {
                              visibility: "hidden",
                            }
                      }
                      onClick={() =>
                        setZdlp_gdd(
                          claimList_gdd[
                            claimList_gdd.sort().indexOf(zdlp_gdd) + 1
                          ]
                        )
                      }
                    >
                      <i className="fa fa-plus"></i>
                    </Badge>
                  </>
                ) : (
                  <noscript />
                )}
              </div>
            </div>
          )}
      </>
      <>
        {quote?.applicableAddons?.length > 0 &&
          _.uniq(quote?.applicableAddons)?.map((item, index) => (
            <div
              style={{
                display:
                  quote?.company_alias === "reliance" &&
                  item === "roadSideAssistance" &&
                  TypeReturn(type) === "cv" &&
                  "none",
              }}
              className="premiumBreakupMobSection__content"
            >
              <>
                <div
                  style={{
                    display: "flex",
                    justifyContent: "flex-start",
                  }}
                >
                  <text
                    style={{ cursor: "pointer" }}
                    onClick={() => {
                      document.getElementById(
                        `${getAddonName(item, addonDiscountPercentage)}`
                      ) &&
                        document
                          .getElementById(
                            `${getAddonName(item, addonDiscountPercentage)}`
                          )
                          .click();
                    }}
                  >
                    {getAddonName(item, addonDiscountPercentage)}{" "}
                  </text>
                  <Style.MFilterMenuBoxCheckConatiner>
                    <div className="filterMenuBoxCheck">
                      <input
                        type="checkbox"
                        className="form-check-input"
                        checked={addOnsAndOthers?.selectedAddons?.includes(
                          item
                        )}
                        onClick={() => {
                          document.getElementById(
                            `${getAddonName(item, addonDiscountPercentage)}`
                          ) &&
                            document
                              .getElementById(
                                `${getAddonName(item, addonDiscountPercentage)}`
                              )
                              .click();
                        }}
                      />

                      <label
                        style={{ border: "none" }}
                        className="form-check-label"
                        htmlFor={`${getAddonName(
                          item,
                          addonDiscountPercentage
                        )}`}
                      ></label>

                      <span style={{ marginLeft: "3px" }}></span>
                    </div>
                  </Style.MFilterMenuBoxCheckConatiner>
                </div>

                {GetAddonValue(
                  item,
                  addonDiscountPercentage,
                  quote,
                  addOnsAndOthers,
                  lessthan993
                ) !== "N/A" ? (
                  <div className="premText">
                    {" "}
                    {GetAddonValue(
                      item,
                      addonDiscountPercentage,
                      quote,
                      addOnsAndOthers,
                      lessthan993
                    ) === "N/S" ? (
                      <>N/S</>
                    ) : GetAddonValue(
                        item,
                        addonDiscountPercentage,
                        quote,
                        addOnsAndOthers,
                        lessthan993
                      ) === "N/A" ? (
                      <Badge
                        variant="danger"
                        style={{
                          cursor: "pointer",
                        }}
                      >
                        Not Available
                      </Badge>
                    ) : (
                      <>
                        {GetAddonValue(
                          item,
                          addonDiscountPercentage,
                          quote,
                          addOnsAndOthers,
                          lessthan993
                        )}
                      </>
                    )}
                  </div>
                ) : (
                  <>N/A</>
                )}
              </>
            </div>
          ))}
        {others.map((item, index) => (
          <div className="premiumBreakupMobSection__content">
            <div>
              {camelToUnderscore(item) &&
                camelToUnderscore(item)
                  .replace(/_/g, " ")
                  .split(" ")
                  .map(_.capitalize)
                  .join(" ")}
            </div>
            <div className="premText">
              {" "}
              {Number(othersList[item]) === 0 ? (
                <i className="fa fa-check" style={{ color: "green" }}></i>
              ) : (
                `₹ ${currencyFormater(othersList[item])}`
              )}{" "}
            </div>
          </div>
        ))}
      </>

      <div className="premiumBreakupMobSection__header">
        <div> Total Addon Premium (D) </div>
        <div className="premText"> ₹ {currencyFormater(totalAddon)}</div>
      </div>
      {quote?.showLoadingAmount && Number(quote?.totalLoadingAmount) > 0 && (
        <div className="premiumBreakupMobSection__header">
          <div> Total Loading Amount </div>
          <div className="premText"> ₹ {currencyFormater(totalAddon)}</div>
        </div>
      )}
    </Style.PremiumBreakupMobSection>
  );
};

export const OdDiscount = ({
  revisedNcb,
  addOnsAndOthers,
  quote,
  otherDiscounts,
  totalPremiumC,
  temp_data,
}) => {
  return (
    <Style.PremiumBreakupMobSection>
      <div className="premiumBreakupMobSection__header">
        Own Damage Discounts
      </div>
      <div className="premiumBreakupMobSection__content">
        <div>Deduction of NCB : </div>
        <div className="premText"> ₹ {currencyFormater(revisedNcb)}</div>
      </div>

      {addOnsAndOthers?.selectedDiscount?.includes(
        "Is the vehicle fitted with ARAI approved anti-theft device?"
      ) &&
        temp_data?.tab !== "tab2" &&
        temp_data?.journeyCategory !== "GCV" && (
          <div className="premiumBreakupMobSection__content">
            <div>Anti-Theft : </div>
            <div className="premText">
              {" "}
              ₹ {currencyFormater(quote?.antitheftDiscount)}
            </div>
          </div>
        )}

      {(addOnsAndOthers?.selectedDiscount?.includes("Voluntary Discounts") 
      || quote?.includedAdditional?.included.includes("Voluntary Discounts"))
      && (
        <div className="premiumBreakupMobSection__content">
          <div>Voluntary Deductible : </div>
          <div className="premText">
            {quote?.voluntaryExcess * 1
              ? `₹ ${currencyFormater(quote?.voluntaryExcess)}`
              : "N/A"}
          </div>
        </div>
      )}

      {addOnsAndOthers?.selectedDiscount?.includes(
        "Automobile Association of India Discount"
      ) && (
        <div className="premiumBreakupMobSection__content">
          <div>Automobile Association of India : </div>
          <div className="premText">
            {quote?.aaiDiscount * 1
              ? `₹ ${currencyFormater(quote?.aaiDiscount)}`
              : "N/A"}
          </div>
        </div>
      )}

      {otherDiscounts * 1 ? (
        <div className="premiumBreakupMobSection__content">
          <div>Other Discounts: </div>
          <div className="premText"> ₹ {currencyFormater(otherDiscounts)}</div>
        </div>
      ) : (
        <noscript />
      )}

      <div className="premiumBreakupMobSection__header">
        <div> Total Discount (C) </div>
        <div className="premText">
          {" "}
          ₹ {currencyFormater(totalPremiumC - (quote?.tppdDiscount * 1 || 0))}
        </div>
      </div>
    </Style.PremiumBreakupMobSection>
  );
};

export const OwnDamage = ({ quote, addOnsAndOthers, temp_data }) => {
  return (
    <Style.PremiumBreakupMobSection>
      <div className="premiumBreakupMobSection__header">Own Damage</div>
      <div className="premiumBreakupMobSection__content">
        <div>Basic Own Damage : </div>
        <div className="premText">
          {" "}
          ₹{" "}
          {currencyFormater(
            quote?.basicPremium +
              (quote?.companyAlias === "icici_lombard"
                ? (quote?.underwritingLoadingAmount * 1 || 0) +
                  (quote?.totalLoadingAmount * 1 || 0)
                : 0)
          )}
        </div>
      </div>
      {(addOnsAndOthers?.selectedAccesories?.includes(
        "Electrical Accessories"
      ) || quote?.includedAdditional?.included.includes("Electrical Accessories"))
      && (
        <div className="premiumBreakupMobSection__content">
          <div>Electrical Accessories : </div>
          <div className="premText">
            {quote?.motorElectricAccessoriesValue * 1 ? (
              `₹ 
                      ${currencyFormater(
                        quote?.motorElectricAccessoriesValue * 1
                      )}`
            ) : quote?.includedAdditional?.included?.includes(
                "motorElectricAccessoriesValue"
              ) ? (
              <Badge
                variant="primary"
                style={{
                  position: "relative",
                  bottom: "2px",
                }}
              >
                Included
              </Badge>
            ) : (
              "N/A"
            )}
          </div>
        </div>
      )}
      {(addOnsAndOthers?.selectedAccesories?.includes(
        "Non-Electrical Accessories"
      ) || quote?.includedAdditional?.included.includes("Non-Electrical Accessories"))
      && (
        <div className="premiumBreakupMobSection__content">
          <div>Non-Electrical Accessories : </div>
          <div className="premText">
            {quote?.motorNonElectricAccessoriesValue * 1 ? (
              `₹ 
                      ${currencyFormater(
                        quote?.motorNonElectricAccessoriesValue * 1
                      )}`
            ) : quote?.includedAdditional?.included.includes(
                "motorNonElectricAccessoriesValue"
              ) ? (
              <Badge
                variant="primary"
                style={{
                  position: "relative",
                  bottom: "2px",
                }}
              >
                Included
              </Badge>
            ) : (
              "N/A"
            )}
          </div>
        </div>
      )}
      {((quote?.motorLpgCngKitValue * 1 ||
        quote?.motorLpgCngKitValue * 1 === 0) 
        || quote?.includedAdditional?.included.includes("motorLpgCngKitValue"))
        && (
        <div className="premiumBreakupMobSection__content">
          <div>LPG/CNG Kit : </div>
          <div className="premText">
            {quote?.motorLpgCngKitValue * 1 ? (
              `₹ 
                      ${currencyFormater(quote?.motorLpgCngKitValue)}`
            ) : temp_data?.fuel === "CNG" ||
              quote?.includedAdditional?.included.includes(
                "motorLpgCngKitValue"
              ) ? (
              <Badge
                variant="primary"
                style={{
                  position: "relative",
                  bottom: "2px",
                }}
              >
                Included
              </Badge>
            ) : (
              "N/A"
            )}
          </div>
        </div>
      )}
      {addOnsAndOthers?.selectedAccesories?.includes("Trailer") ? (
        <div className="premiumBreakupMobSection__content">
          <div>Trailer : </div>
          <div className="premText">
            {" "}
            ₹ {currencyFormater(quote?.trailerValue)}
          </div>
        </div>
      ) : (
        <noscript />
      )}
      {quote?.limitedtoOwnPremisesOD ? (
        <div className="premiumBreakupMobSection__content">
          <div>Vehicle limited to own premises : </div>
          <div className="premText">
            {" "}
            ₹ {currencyFormater(quote?.limitedtoOwnPremisesOD)}
          </div>
        </div>
      ) : (
        <noscript />
      )}
      {(addOnsAndOthers?.selectedAdditions?.includes(
        "Geographical Extension"
      ) 
      || quote?.includedAdditional?.included.includes("Geographical Extension"))
      && (
        <div className="premiumBreakupMobSection__content">
          <div>Geographical Extension : </div>
          <div className="premText">
            {" "}
            ₹ {currencyFormater(quote?.geogExtensionODPremium)}
          </div>
        </div>
      )}
      <div className="premiumBreakupMobSection__header">
        <>
          <div> Total OD Premium (A) </div>
          <div className="premText">
            {" "}
            ₹{" "}
            {currencyFormater(
              quote?.finalOdPremium * 1 +
                (quote?.underwritingLoadingAmount * 1 || 0) +
                (quote?.totalLoadingAmount * 1 || 0)
            )}
          </div>
        </>
      </div>
    </Style.PremiumBreakupMobSection>
  );
};

export default {
  FinalCalculation,
  Liability,
  MobileAddons,
  OdDiscount,
  OwnDamage,
};
