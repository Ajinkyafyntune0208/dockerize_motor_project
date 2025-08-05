import { subMonths } from "date-fns";
import { TypeReturn } from "modules/type";
import moment from "moment";
import React from "react";
import { Badge, Table } from "react-bootstrap";
import _ from "lodash";
import { camelToUnderscore, currencyFormater } from "utils";
import { DetailRow } from "../style";
import { GetAddonValue } from "modules/helper";
import { getAddonName } from "modules/quotesPage/quoteUtil";
import  "./knowmoreStyle.css";
import { FuelType } from "modules/Home/steps/car-details/helper";

export const IcTable = ({ quote, temp_data, type, prefill }) => {
  return (
    <Table bordered>
      <tbody>
        <tr>
          <td name="product" class="heading">
            {quote?.productName}
          </td>
        </tr>
        <tr>
          <td name="policy_type">
            <span>Policy Type : </span>
            <span class="spann">
              {quote?.policyType === "Short Term"
                ? `${
                    quote.premiumTypeCode === "short_term_3" ||
                    quote.premiumTypeCode === "short_term_3_breakin"
                      ? "3 Months"
                      : "6 Months"
                  }(Comprehensive)`
                : quote?.policyType === "Comprehensive" &&
                  temp_data?.newCar &&
                  TypeReturn(type) !== "cv"
                ? `Bundled(1 yr OD + ${
                    TypeReturn(type) === "car" ? 3 : 5
                  } yr TP)`
                : quote?.policyType}
            </span>
          </td>
        </tr>

        <tr>
          <td name="model">
            <span>Model:</span>
            <span class="spann">
              {`${quote?.mmvDetail?.manfName || ""} ${
                quote?.mmvDetail?.modelName || ""
              }-	${quote?.mmvDetail?.versionName || ""} ${
                quote?.mmvDetail?.fuelType === "ELECTRIC" ||
                quote?.mmvDetail?.fuelType === "Electric"
                  ? `${quote?.mmvDetail?.kw || " "}${
                      TypeReturn(type) === "bike" ? "" : "kW"
                    }`
                  : temp_data?.journeyCategory === "GCV"
                  ? `${quote?.mmvDetail?.grossVehicleWeight || " "} ${"GVW"}`
                  : `${quote?.mmvDetail?.cubicCapacity || " "} ${
                      TypeReturn(type) === "bike" ? "CC" : "CC"
                    }`
              }`}
            </span>
          </td>
        </tr>
        <tr>
          <td name="fuel_type">
            <span>Fuel Type : </span>
            <span class="spann">
              {quote?.fuelType ? quote?.fuelType.toUpperCase() : "N/A"}
            </span>
          </td>
        </tr>
        <tr>
          {temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
          temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo !==
            "NEW" ? (
            <td name="reg_no">
              <span>Reg No. : </span>
              <span class="spann">
                {
                  temp_data?.corporateVehiclesQuoteRequest
                    ?.vehicleRegistrationNo
                }{" "}
                -{" "}
                {temp_data?.rtoCity ||
                  prefill?.corporateVehiclesQuoteRequest?.rtoCity}
              </span>
            </td>
          ) : (
            <td name="rto">
              <span>RTO :</span>
              <span class="spann">
                {quote?.vehicleRegistrationNo} -{" "}
                {temp_data?.rtoCity ||
                  prefill?.corporateVehiclesQuoteRequest?.rtoCity}
              </span>
            </td>
          )}
        </tr>
      </tbody>
    </Table>
  );
};

export const VehicleTable = ({ quote, temp_data, tempData }) => {
  return (
    <Table bordered>
      <tbody>
        {quote?.mmvDetail?.seatingCapacity * 1 && (
          <tr>
            <td name="seating_capacity">
              <span>Vehicle Seating Capacity :</span>
              <span class="spann">{` ${quote?.mmvDetail?.seatingCapacity * 1}`}</span>
            </td>
          </tr>
        )}
        <tr>
          <td name="idv">
            <span>IDV :</span>
            <span class="spann">
              {temp_data?.tab === "tab2" ? (
                <Badge
                  variant="secondary"
                  style={{
                    cursor: "pointer",
                  }}
                >
                  Not Applicable
                </Badge>
              ) : (
                ` ₹ ${currencyFormater(quote?.idv)}`
              )}
            </span>
          </td>
        </tr>
        <tr>
          <td name="reg_date">
            <span>Reg Date :</span>
            <span class="spann">{quote?.vehicleRegisterDate}</span>
          </td>
        </tr>
        <tr>
          <td name="previous_policy_expiry">
            <span>Previous Policy Expiry :</span>
            <span class="spann">
              {temp_data?.newCar
                ? "N/A"
                : tempData?.policyType === "Not sure"
                ? "Not available"
                : temp_data?.breakIn
                ? temp_data?.expiry === "New" ||
                  moment(subMonths(new Date(Date.now()), 9)).format(
                    "DD-MM-YYYY"
                  ) === temp_data?.expiry
                  ? ""
                  : temp_data?.expiry
                : temp_data?.expiry}
            </span>
          </td>
        </tr>
        {!temp_data?.breakIn && quote?.policyStartDate && (
          <tr>
            <td name="policy_start_date">
              <span>Policy Start Date :</span>
              <span class="spann">{quote?.policyStartDate}</span>
            </td>
          </tr>
        )}
        <tr>
          <td name="business_type">
            <span>Business Type : </span>
            <span class="spann">
              {quote?.isRenewal === "Y"
                ? "Renewal"
                : quote?.businessType &&
                  quote?.businessType.split(" ").map(_.capitalize).join(" ")}
            </span>
          </td>
        </tr>
      </tbody>
    </Table>
  );
};

export const PlanDetailsTable = ({
  quote,
  addOnsAndOthers,
  type,
  temp_data,
  totalPremiumA,
}) => {
  return (
    <Table bordered style={{ borderRadius: "5px" }}>
      <thead>
        <tr>
          <th
            style={{
              textAlign: "center",
              color:
                import.meta.env.VITE_BROKER === "RB" && "rgb(25, 102, 255)",
            }}
          >
            Own Damage
          </th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <DetailRow>
              <span> Basic Own Damage(OD)</span>
              <span className="amount" name="basic_own_damage">
                {quote?.basicPremium * 1 ? "₹" : ""}{" "}
                {quote?.basicPremium * 1
                  ? currencyFormater(
                      quote?.basicPremium +
                        (quote?.companyAlias === "icici_lombard"
                          ? (quote?.underwritingLoadingAmount * 1 || 0) +
                            (quote?.totalLoadingAmount * 1 || 0)
                          : 0)
                    )
                  : "N/A"}
              </span>
            </DetailRow>
          </td>
        </tr>
        <>
          {addOnsAndOthers?.selectedAccesories?.includes(
            "Electrical Accessories"
          ) ||
          quote?.includedAdditional?.included?.includes(
            "motorElectricAccessoriesValue"
          ) ? (
            <tr>
              <td>
                {" "}
                <DetailRow>
                  <span> Electrical Accessories</span>
                  <span className="amount" name="electrical_accessories">
                    {quote?.motorElectricAccessoriesValue * 1 ? (
                      `₹
                                  ${currencyFormater(
                                    quote?.motorElectricAccessoriesValue
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
                  </span>
                </DetailRow>
              </td>
            </tr>
          ) : (
            <noscript />
          )}
          {addOnsAndOthers?.selectedAccesories?.includes(
            "Non-Electrical Accessories"
          ) ||
          quote?.includedAdditional?.included.includes(
            "motorNonElectricAccessoriesValue"
          ) ? (
            <tr>
              <td>
                {" "}
                <DetailRow>
                  <span>Non-Electrical Accessories</span>
                  <span className="amount" name="non_electrical_accessories">
                    {quote?.motorNonElectricAccessoriesValue * 1 ? (
                      ` ₹
                                  ${currencyFormater(
                                    quote?.motorNonElectricAccessoriesValue
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
                  </span>
                </DetailRow>
              </td>
            </tr>
          ) : (
            <noscript />
          )}
          {temp_data?.tab !== "tab2" &&
          (quote?.motorLpgCngKitValue * 1 ||
            quote?.motorLpgCngKitValue * 1 === 0 ||
            quote?.includedAdditional?.included.includes(
              "motorLpgCngKitValue"
            )) ? (
            <tr>
              <td>
                {" "}
                <DetailRow>
                  <span> LPG/CNG Kit</span>
                  <span className="amount" name="lpg_cng_kit">
                    {quote?.motorLpgCngKitValue * 1 ? (
                      `₹
                                  ${currencyFormater(
                                    quote?.motorLpgCngKitValue
                                  )}`
                    ) : temp_data?.fuel === "CNG" ||
                      temp_data?.fuel === "LPG" ||
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
                  </span>
                </DetailRow>
              </td>
            </tr>
          ) : (
            <noscript />
          )}
          {addOnsAndOthers?.selectedDiscount?.includes(
            "Vehicle Limited to Own Premises"
          ) ||
          quote?.includedAdditional?.included.includes(
            "Vehicle Limited to Own Premises"
          ) ? (
            <tr>
              <td>
                {" "}
                <DetailRow>
                  <span> Vehicle limited to own premises</span>
                  <span name="vehicle_limited_to_own_premises">
                    {quote?.limitedtoOwnPremisesOD * 1 ? "- ₹" : ""}{" "}
                    {quote?.limitedtoOwnPremisesOD * 1
                      ? currencyFormater(quote?.limitedtoOwnPremisesOD)
                      : "N/A"}
                  </span>
                </DetailRow>
              </td>
            </tr>
          ) : (
            <noscript />
          )}
          {addOnsAndOthers?.selectedAdditions?.includes(
            "Geographical Extension"
          ) ||
          quote?.includedAdditional?.included.includes(
            "Geographical Extension"
          ) ? (
            <tr>
              <td>
                {" "}
                <DetailRow>
                  <span> Geographical Extension</span>
                  <span name="geographical_extension">
                    {quote?.geogExtensionODPremium * 1 ? "₹" : ""}{" "}
                    {quote?.geogExtensionODPremium * 1
                      ? currencyFormater(quote?.geogExtensionODPremium)
                      : "N/A"}
                  </span>
                </DetailRow>
              </td>
            </tr>
          ) : (
            <noscript />
          )}
        </>
        {addOnsAndOthers?.selectedAccesories?.includes("Trailer") ||
        quote?.includedAdditional?.included.includes("Trailer") ? (
          <tr>
            <td>
              {temp_data?.journeyCategory === "GCV" ? (
                <DetailRow>
                  <span> Trailer</span>
                  <span className="amount" name="trailer">
                    {quote?.trailerValue * 1 ? "₹" : ""}{" "}
                    {quote?.trailerValue * 1
                      ? currencyFormater(quote?.trailerValue)
                      : "N/A"}
                  </span>
                </DetailRow>
              ) : (
                <DetailRow>&nbsp;</DetailRow>
              )}{" "}
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        {quote?.showLoadingAmount &&
        (Number(quote?.totalLoadingAmount) > 0 ||
          Number(quote?.underwritingLoadingAmount)) ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span>Total Loading Amount</span>
                <span name="total_loading_amount">
                  ₹{" "}
                  {currencyFormater(
                    Number(quote?.totalLoadingAmount) ||
                      Number(quote?.underwritingLoadingAmount)
                  )}
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span className="boldText"> {"Total OD Premium (A)"}</span>
              <span name="total_od_premium_a">
                ₹{" "}
                {currencyFormater(
                  totalPremiumA +
                    (quote?.underwritingLoadingAmount * 1 || 0) +
                    (quote?.totalLoadingAmount * 1 || 0)
                )}
              </span>
            </DetailRow>
          </td>
        </tr>
      </tbody>
    </Table>
  );
};

export const LiabilityTable = ({
  quote,
  addOnsAndOthers,
  temp_data,
  type,
  llpaidCon,
  others,
  othersList,
  totalPremiumB,
}) => {
  const isGCVorMISC =
    temp_data?.journeyCategory === "GCV" ||
    temp_data?.journeyCategory === "MISC";

  const paCoverLabel = isGCVorMISC
    ? "Additional PA Cover To Paid Driver/Conductor/Cleaner"
    : "Additional PA Cover To Paid Driver";

  const paCoverAmount = quote?.paPaidDriverSI
    ? `(${quote.paPaidDriverSI} SI)`
    : "";
  return (
    <Table bordered>
      <thead>
        <tr>
          <th
            style={{
              textAlign: "center",
              color:
                import.meta.env.VITE_BROKER === "RB" && "rgb(25, 102, 255)",
            }}
          >
            Liability
          </th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span> Third Party Liability</span>
              <span className="amount" name="thrid_party_liability">
                {quote?.tppdPremiumAmount * 1 ? "₹" : ""}{" "}
                {quote?.tppdPremiumAmount * 1
                  ? currencyFormater(quote?.tppdPremiumAmount)
                  : "N/A"}
              </span>
            </DetailRow>
          </td>
        </tr>
        {addOnsAndOthers?.selectedDiscount?.includes("TPPD Cover") ||
        quote?.includedAdditional?.included?.includes("tppdDiscount") ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span>TPPD Discounts</span>
                <span className="amount" name="tppd_discounts">
                  {quote?.tppdDiscount * 1 ? "- ₹" : ""}{" "}
                  {quote?.tppdDiscount * 1 ? (
                    currencyFormater(quote?.tppdDiscount)
                  ) : quote?.includedAdditional?.included?.includes(
                      "tppdDiscount"
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
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        {temp_data?.journeyCategory !== "GCV" && (
          <>
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
              <tr>
                <td>
                  {" "}
                  <DetailRow>
                    <span> PA For Unnamed Passenger</span>
                    <span className="amount" name="pa_for_unnamed_passenger">
                      {quote?.coverUnnamedPassengerValue === "NA" ||
                      !(quote?.coverUnnamedPassengerValue * 1)
                        ? "N/A"
                        : `	₹ ${currencyFormater(
                            quote?.companyAlias === "sbi" &&
                              addOnsAndOthers?.selectedCpa?.includes(
                                "Compulsory Personal Accident"
                              ) &&
                              !_.isEmpty(addOnsAndOthers?.isTenure)
                              ? quote?.coverUnnamedPassengerValue *
                                  (TypeReturn(type) === "bike" ? 5 : 3)
                              : quote?.coverUnnamedPassengerValue
                          )}`}
                    </span>
                  </DetailRow>
                </td>
              </tr>
            ) : quote?.includedAdditional?.included?.includes(
                "coverUnnamedPassengerValue"
              ) ? (
              <tr>
                <td>
                  <DetailRow>
                    <span> PA For Unnamed Passenger</span>
                    <span className="amount">
                      <Badge
                        variant="primary"
                        style={{
                          position: "relative",
                          bottom: "2px",
                        }}
                      >
                        Included
                      </Badge>
                    </span>
                  </DetailRow>
                </td>
              </tr>
            ) : (
              <noscript />
            )}
          </>
        )}
        {
          <>
            {addOnsAndOthers?.selectedAdditions?.includes(
              "PA paid driver/conductor/cleaner"
            ) ||
            addOnsAndOthers?.selectedAdditions?.includes(
              "PA cover for additional paid driver"
            ) ||
            quote?.includedAdditional?.included.includes(
              "PA paid driver/conductor/cleaner"
            ) ||
            quote?.includedAdditional?.included.includes(
              "PA cover for additional paid driver"
            ) ? (
              <tr>
                <td>
                  {" "}
                  <DetailRow noWrap={true}>
                    <span style={{ fontSize: "11px" }}>
                      {" "}
                      {`${paCoverLabel} ${paCoverAmount}`}
                    </span>
                    <span className="amount">
                      {quote?.motorAdditionalPaidDriver * 1 ? "₹" : ""}{" "}
                      {quote?.motorAdditionalPaidDriver * 1
                        ? currencyFormater(
                            quote?.companyAlias === "sbi" &&
                              addOnsAndOthers?.selectedCpa?.includes(
                                "Compulsory Personal Accident"
                              ) &&
                              !_.isEmpty(addOnsAndOthers?.isTenure)
                              ? quote?.motorAdditionalPaidDriver *
                                  (TypeReturn(type) === "bike" ? 5 : 3)
                              : quote?.motorAdditionalPaidDriver
                          )
                        : "N/A"}
                    </span>
                  </DetailRow>
                </td>
              </tr>
            ) : (
              <noscript />
            )}
          </>
        }
        {addOnsAndOthers?.selectedAdditions?.includes("LL paid driver") ||
        addOnsAndOthers?.selectedAdditions?.includes(
          "LL paid driver/conductor/cleaner"
        ) ||
        quote?.includedAdditional?.included?.includes("defaultPaidDriver") ? (
          !llpaidCon ? (
            <tr>
              <td>
                {" "}
                <DetailRow>
                  <span>
                    {" "}
                    {temp_data?.journeyCategory === "GCV"
                      ? "Legal Liability To Paid Driver/Conductor/Cleaner"
                      : "Legal Liability To Paid Driver"}{" "}
                  </span>
                  <span
                    className="amount"
                    name="legal_liability_to_paid_driver"
                  >
                    {quote?.defaultPaidDriver * 1
                      ? `₹ ${currencyFormater(quote?.defaultPaidDriver)}`
                      : "N/A"}
                  </span>
                </DetailRow>
              </td>
            </tr>
          ) : (
            <>
              {!(
                quote?.addOnsData?.other?.lLPaidDriver * 1 === 0 &&
                quote?.companyAlias === "cholla_mandalam"
              ) && (
                <tr>
                  <td>
                    {" "}
                    <DetailRow>
                      <span> {"Legal Liability To Paid Driver"} </span>
                      <span
                        className="amount"
                        name="legal_liability_to_paid_driver"
                      >
                        {quote?.llPaidDriverPremium * 1
                          ? `₹ ${currencyFormater(quote?.llPaidDriverPremium)}`
                          : "N/A"}
                      </span>
                    </DetailRow>
                  </td>
                </tr>
              )}
              {addOnsAndOthers?.selectedAdditions?.includes(
                "LL paid driver/conductor/cleaner"
              ) ? (
                <tr>
                  <td>
                    {" "}
                    <DetailRow>
                      <span>
                        {" "}
                        {`Legal Liability To Paid Conductor ${
                          quote?.companyAlias === "icici_lombard" ||
                          quote?.companyAlias === "magma" ||
                          quote?.companyAlias === "cholla_mandalam" ||
                          quote?.companyAlias === "royal_sundaram" ||
                          quote?.companyAlias === "sbi"
                            ? "/Cleaner"
                            : ""
                        }`}{" "}
                      </span>
                      <span className="amount">
                        {quote?.llPaidConductorPremium * 1
                          ? `₹ ${currencyFormater(
                              quote?.llPaidConductorPremium
                            )}`
                          : "N/A"}
                      </span>
                    </DetailRow>
                  </td>
                </tr>
              ) : (
                <noscript />
              )}
              {!(
                quote?.companyAlias === "icici_lombard" ||
                quote?.companyAlias === "magma" ||
                quote?.companyAlias === "cholla_mandalam" ||
                quote?.companyAlias === "royal_sundaram"
              ) ? (
                <tr>
                  <td>
                    {" "}
                    <DetailRow>
                      <span> {"Legal Liability To Paid Cleaner"} </span>
                      <span
                        className="amount"
                        name="legal_liability_to_paid_cleaner"
                      >
                        {quote?.llPaidCleanerPremium * 1
                          ? `₹ ${currencyFormater(quote?.llPaidCleanerPremium)}`
                          : "N/A"}
                      </span>
                    </DetailRow>
                  </td>
                </tr>
              ) : (
                <noscript />
              )}
            </>
          )
        ) : (
          <noscript />
        )}
        {!addOnsAndOthers?.selectedAdditions?.includes("LL paid driver") &&
          !_.isEmpty(others) &&
          others?.includes("lLPaidDriver") &&
          others.map(
            (item) =>
              item === "lLPaidDriver" && (
                <tr>
                  <td>
                    {" "}
                    <DetailRow>
                      <span>
                        {camelToUnderscore(item) &&
                          camelToUnderscore(item)
                            .replace(/_/g, " ")
                            .split(" ")
                            .map(_.capitalize)
                            .join(" ")}
                      </span>
                      <span>
                        {" "}
                        {Number(othersList[item]) === 0 ? (
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
                          `₹ ${currencyFormater(quote?.defaultPaidDriver)}`
                        )}{" "}
                      </span>
                    </DetailRow>
                  </td>
                </tr>
              )
          )}
        {
          <>
            {quote?.cngLpgTp * 1 ||
            quote?.cngLpgTp * 1 === 0 ||
            quote?.includedAdditional?.included?.includes("cngLpgTp") ? (
              <tr>
                <td>
                  {" "}
                  <DetailRow>
                    <span>LPG/CNG Kit TP</span>
                    <span className="amount" name="lpg_cng">
                      {quote?.cngLpgTp * 1 ? "₹" : ""}{" "}
                      {quote?.cngLpgTp * 1
                        ? currencyFormater(quote?.cngLpgTp)
                        : "N/A"}
                    </span>
                  </DetailRow>
                </td>
              </tr>
            ) : (
              <noscript />
            )}
          </>
        }
        {addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        ) && _.isEmpty(addOnsAndOthers?.isTenure) ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span> Compulsory PA Cover For Owner Driver </span>
                <span className="amount" name="compulsory_pa_own_driver">
                  {addOnsAndOthers?.selectedCpa?.includes(
                    "Compulsory Personal Accident"
                  ) && quote?.compulsoryPaOwnDriver * 1 === 0
                    ? "N/A"
                    : "₹ " + currencyFormater(quote?.compulsoryPaOwnDriver)}
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        {(quote?.otherCovers?.legalLiabilityToEmployee !== undefined ||
          quote?.includedAdditional?.included.includes(
            "legalLiabilityToEmployee"
          )) &&
        temp_data?.ownerTypeId === 2 ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span> Legal Liability To Employee </span>
                <span>
                  {quote?.otherCovers?.legalLiabilityToEmployee * 1 === 0 ? (
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
                    "₹" +
                    currencyFormater(
                      quote?.otherCovers?.legalLiabilityToEmployee
                    )
                  )}
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        {addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        ) && !_.isEmpty(addOnsAndOthers?.isTenure) ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span>
                  {" "}
                  Compulsory PA Cover For Owner Driver{" "}
                  {TypeReturn(type) === "car"
                    ? "(3 Years)"
                    : TypeReturn(type) === "bike"
                    ? "(5 Years)"
                    : ""}
                </span>
                <span className="amount" name="compulsory_pa_owner_driver">
                  {addOnsAndOthers?.selectedCpa?.includes(
                    "Compulsory Personal Accident"
                  ) &&
                  !_.isEmpty(addOnsAndOthers?.isTenure) &&
                  (!quote?.multiYearCpa || quote?.multiYearCpa * 1 === 0)
                    ? "N/A"
                    : "₹" + currencyFormater(quote?.multiYearCpa)}
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        {addOnsAndOthers?.selectedDiscount?.includes(
          "Vehicle Limited to Own Premises"
        ) ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span> Vehicle limited to own premises</span>
                <span name="vehicle_limited_to_own_premises">
                  {quote?.limitedtoOwnPremisesTP * 1 ? "- ₹" : ""}{" "}
                  {quote?.limitedtoOwnPremisesTP * 1
                    ? currencyFormater(quote?.limitedtoOwnPremisesTP)
                    : "N/A"}
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        {addOnsAndOthers?.selectedAdditions?.includes(
          "Geographical Extension"
        ) ||
        quote?.includedAdditional?.included.includes(
          "Geographical Extension"
        ) ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span> Geographical Extension</span>
                <span name="geographical_extension">
                  {quote?.geogExtensionTPPremium * 1 ? "₹" : ""}{" "}
                  {quote?.geogExtensionTPPremium * 1
                    ? currencyFormater(quote?.geogExtensionTPPremium)
                    : "N/A"}
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}

        {addOnsAndOthers?.selectedAdditions?.includes("NFPP Cover") ||
        quote?.includedAdditional?.included.includes("NFPP Cover") ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span> NFPP Cover</span>
                <span name="nfpp_cover">
                  {quote?.nfpp * 1 ? "₹" : ""}{" "}
                  {quote?.nfpp * 1 ? currencyFormater(quote?.nfpp) : "N/A"}
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span className="boldText">Total Liability Premium (B)</span>
              <span className="amount" name="total_liability_premium_b">
                ₹{" "}
                {currencyFormater(
                  totalPremiumB - (quote?.tppdDiscount * 1 || 0)
                )}
              </span>
            </DetailRow>
          </td>
        </tr>
      </tbody>
    </Table>
  );
};

export const DiscountTable = ({
  revisedNcb,
  temp_data,
  addOnsAndOthers,
  quote,
  otherDiscounts,
  totalPremiumC,
}) => {
  return (
    <Table bordered>
      <thead>
        <tr>
          <th
            style={{
              textAlign: "center",
              color:
                import.meta.env.VITE_BROKER === "RB" && "rgb(25, 102, 255)",
            }}
          >
            Own Damage Discounts
          </th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                width: "100%",
              }}
            >
              <span> Deduction of NCB</span>
              <span name="deduction_of_ncb">
                <span>{revisedNcb * 1 ? "₹" : ""} </span>
                <span>
                  {revisedNcb * 1 ? currencyFormater(revisedNcb) : "N/A"}
                </span>
              </span>
            </div>
          </td>
        </tr>
        {(temp_data?.journeyCategory !== "GCV" &&
          addOnsAndOthers?.selectedDiscount?.includes("Voluntary Discounts")) ||
        quote?.includedAdditional?.included.includes("Voluntary Discounts") ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span> Voluntary Deductible</span>
                <span name="voluntary_deductible">
                  {quote?.voluntaryExcess * 1
                    ? `₹
                                ${currencyFormater(quote?.voluntaryExcess)}`
                    : "N/A"}
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        {temp_data?.journeyCategory !== "GCV" && (
          <>
            {addOnsAndOthers?.selectedDiscount?.includes(
              "Is the vehicle fitted with ARAI approved anti-theft device?"
            ) && temp_data?.tab !== "tab2" ? (
              <tr>
                <td>
                  {" "}
                  <DetailRow>
                    <span> Anti-Theft</span>
                    <span name="anti_theft">
                      {quote?.antitheftDiscount * 1 ? "₹" : ""}{" "}
                      {quote?.antitheftDiscount * 1
                        ? currencyFormater(quote?.antitheftDiscount * 1)
                        : "N/A"}
                    </span>
                  </DetailRow>
                </td>
              </tr>
            ) : (
              <noscript />
            )}
            {quote?.premisisDiscount ? (
              <tr>
                <td>
                  {" "}
                  <DetailRow>
                    <span> Vehicle limited to own premises</span>
                    <span name="vehicle_limited_to_own_premises">
                      ₹ {currencyFormater(quote?.premisisDiscount)}
                    </span>
                  </DetailRow>
                </td>
              </tr>
            ) : (
              <noscript />
            )}
            {addOnsAndOthers?.selectedDiscount?.includes(
              "Automobile Association of India Discount"
            ) ? (
              <tr>
                <td>
                  {" "}
                  <DetailRow>
                    <span> Automobile Association of India</span>
                    <span name="automobile_association_of_india">
                      {quote?.aaiDiscount * 1 ? "₹" : ""}{" "}
                      {quote?.aaiDiscount * 1
                        ? currencyFormater(quote?.aaiDiscount * 1)
                        : "N/A"}
                    </span>
                  </DetailRow>
                </td>
              </tr>
            ) : (
              <noscript />
            )}
          </>
        )}
        {otherDiscounts * 1 ? (
          <tr>
            <td>
              {" "}
              <DetailRow>
                <span> Other Discounts</span>
                <span name="other_discounts">
                  ₹ {currencyFormater(otherDiscounts) || 0}
                </span>
              </DetailRow>
            </td>
          </tr>
        ) : (
          <noscript />
        )}
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span className="boldText"> Total Discount (C)</span>
              <span name="total_discount">
                ₹{" "}
                {currencyFormater(
                  totalPremiumC - (quote?.tppdDiscount * 1 || 0)
                )}
              </span>
            </DetailRow>
          </td>
        </tr>
      </tbody>
    </Table>
  );
};

export const AddonsTable = ({
  totalApplicableAddonsMotor,
  addonDiscountPercentage,
  quote,
  addOnsAndOthers,
  lessthan993,
  type,
  others,
  othersList,
  totalAddon,
}) => {
  return (
    <Table bordered>
      <thead>
        <tr>
          <th
            style={{
              textAlign: "center",
              color:
                import.meta.env.VITE_BROKER === "RB" && "rgb(25, 102, 255)",
            }}
          >
            {totalApplicableAddonsMotor?.includes("imt23")
              ? "Addons & Covers"
              : "Addons"}
          </th>
        </tr>
      </thead>
      <tbody>
        <>
          {totalApplicableAddonsMotor.map((item, index) => (
            <>
              {GetAddonValue(
                item,
                addonDiscountPercentage,
                quote,
                addOnsAndOthers,
                lessthan993
              ) !== "N/A" && (
                <tr
                  style={{
                    display:
                      quote?.company_alias === "reliance" &&
                      item === "roadSideAssistance" &&
                      TypeReturn(type) === "cv" &&
                      "none",
                  }}
                >
                  <td>
                    <DetailRow>
                      <span style={{ display: "flex" }}>
                        {" "}
                        {getAddonName(item, addonDiscountPercentage)}
                        {item === "zeroDepreciation" &&
                          quote?.claimsCovered && (
                            <p style={{ marginBottom: 0, marginLeft: "2px" }}>
                              ({quote?.claimsCovered})
                            </p>
                          )}
                      </span>
                      <span name={item}>
                        {GetAddonValue(
                          item,
                          addonDiscountPercentage,
                          quote,
                          addOnsAndOthers,
                          lessthan993
                        ) === "N/S" ? (
                          <Badge
                            variant="secondary"
                            style={{
                              cursor: "pointer",
                            }}
                          >
                            Not selected
                          </Badge>
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
                      </span>
                    </DetailRow>
                  </td>{" "}
                </tr>
              )}
            </>
          ))}
          {others.map(
            (item, index) =>
              item !== "lLPaidDriver" && (
                <tr>
                  <td>
                    {" "}
                    <DetailRow>
                      <span>
                        {camelToUnderscore(item) &&
                          camelToUnderscore(item)
                            .replace(/_/g, " ")
                            .split(" ")
                            .map(_.capitalize)
                            .join(" ")}
                      </span>
                      <span>
                        {" "}
                        {Number(othersList[item]) === 0 ? (
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
                          `₹ ${currencyFormater(othersList[item])}`
                        )}{" "}
                      </span>
                    </DetailRow>
                  </td>
                </tr>
              )
          )}
        </>

        <tr>
          <td>
            {" "}
            <DetailRow>
              <span className="boldText"> Total Addon Premium (D)</span>
              <span name="total_addon_premium">
                ₹ {currencyFormater(totalAddon)}
              </span>
            </DetailRow>
          </td>
        </tr>
      </tbody>
    </Table>
  );
};

export const FinalCalculation = ({
  totalPremiumA,
  quote,
  totalAddon,
  totalPremiumC,
  totalPremiumB,
  uwLoading,
  totalPremium,
  gst,
  finalPremium,
  extraLoading,
}) => {
  return (
    <Table bordered>
      <tbody>
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span className="boldText">
                {" "}
                {"Total OD Payable (A + D - C)"}
              </span>
              <span className="boldText" name="total_od_payable">
                ₹{" "}
                {currencyFormater(
                  quote?.totalOdPayable ||
                    (totalPremiumA * 1 || 0) +
                      // (quote?.totalLoadingAmount * 1 || 0) +
                      // (quote?.underwritingLoadingAmount * 1 || 0) +
                      (totalAddon * 1 || 0) -
                      ((totalPremiumC * 1 || 0) -
                        (quote?.tppdDiscount * 1 || 0)) +
                      (extraLoading * 1 || 0)
                )}
              </span>
            </DetailRow>
          </td>
        </tr>
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span className="boldText"> {"Total TP Payable (B)"}</span>
              <span className="boldText" name="total_tp_payable">
                ₹{" "}
                {currencyFormater(
                  totalPremiumB - (quote?.tppdDiscount * 1 || 0)
                )}
              </span>
            </DetailRow>
          </td>
        </tr>
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span className="boldText">
                {" "}
                {uwLoading > 0 ? "Net Premium" : "Net Premium "}
              </span>
              <span className="boldText" name="net_premium">
                ₹ {currencyFormater(totalPremium)}
              </span>
            </DetailRow>
          </td>
        </tr>
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span className="boldText"> GST </span>
              <span className="boldText" name="gst">
                ₹ {currencyFormater(gst)}
              </span>
            </DetailRow>
          </td>
        </tr>
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span className="boldText">Gross Premium (incl. GST)</span>
              <span className="boldText" name="gross_premium">
                ₹ {currencyFormater(finalPremium)}
              </span>
            </DetailRow>
          </td>
        </tr>
      </tbody>
    </Table>
  );
};


export const NewNcbTable = ({
  revisedNcb,
  temp_data,
  addOnsAndOthers,
  quote,
  otherDiscounts,
  totalPremiumC,
}) => {
  console.log(quote, "quote");
  return (
    <Table bordered>
      <tbody>
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span> New NCB</span>
              <span>{`(${quote?.ncbDiscount}%)`}</span>
            </DetailRow>
          </td>
        </tr>
        <tr>
          <td>
            {" "}
            <DetailRow>
              <span> Cover Value (IDV)</span>
              <span>{` ₹ ${currencyFormater(quote?.idv)}`}</span>
            </DetailRow>
          </td>
        </tr>
      </tbody>
    </Table>
  );
};

export default {
  IcTable,
  VehicleTable,
  PlanDetailsTable,
  LiabilityTable,
  DiscountTable,
  AddonsTable,
  FinalCalculation,
  NewNcbTable,
};
