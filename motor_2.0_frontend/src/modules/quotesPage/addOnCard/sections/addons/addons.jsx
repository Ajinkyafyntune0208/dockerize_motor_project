import React from "react";
import tooltip from "../../../../../assets/img/tooltip.svg";
import { CardBlock, FilterMenuBoxCheckConatiner } from "../../style";
import { CustomTooltip } from "components";

const Addons = (props) => {
  // prettier-ignore
  const {
    lessthan767, tab, zeroDep, setZeroDep, temp_data, rsa, setRsa, setRsa2, rsa2, gcvJourney,
    consumables, setConsumables, motor, bike, keyReplace, setKeyReplace, engineProtector, setEngineProtector,
    ncbProtection, setNcbProtectiont, tyreSecure, setTyreSecure, returnToInvoice, setReturnToInvoice, lopb, setLopb,
    emergencyMedicalExpenses, setEmergencyMedicalExpenses, windshield, setWindShield, emiprotection, setEmiprotection,
    additionalTowing, setAdditionalTowing, batteryprotect, setBatteryprotect,
  } = props

  const fuelType = temp_data?.corporateVehiclesQuoteRequest?.fuelType;

  return (
    <>
      {tab === "tab1" ? (
        <CardBlock>
          <FilterMenuBoxCheckConatiner>
            <div className="filterMenuBoxCheck">
              <input
                type="checkbox"
                className="form-check-input"
                id={"Zero Depreciation"}
                value={"Zero Depreciation"}
                defaultChecked={zeroDep}
                checked={zeroDep}
                onChange={(e) => {
                  setZeroDep(e.target.checked);
                }}
              />

              <CustomTooltip
                rider="true"
                id="zero__Tooltipvol"
                place={"right"}
                customClassName="mt-3"
              >
                <label
                  data-tip={
                    !lessthan767 &&
                    "<h3 >Zero Depreciation</h3> <div>Also called Nil Depreciation cover or Bumper-to-Bumper cover. An add-on which gives you complete cover on any body parts of the car excluding tyres and batteries. Insurer will pay entire cost of body parts, ignoring the year-on-year depreciation in value of these parts.</div>"
                  }
                  data-html={!lessthan767 && true}
                  data-for={!lessthan767 && "zero__Tooltipvol"}
                  className={"form-check-label"}
                  htmlFor={"Zero Depreciation"}
                >
                  {"Zero Depreciation"}{" "}
                </label>
              </CustomTooltip>
              <span style={{ marginLeft: "3px" }}>
                {lessthan767 ? (
                  <CustomTooltip
                    rider="true"
                    id="zero__Tooltipvol_m"
                    place={"left"}
                    customClassName="mt-3 "
                    allowClick
                  >
                    <img
                      data-tip="<h3 >Zero Depreciation</h3> <div>Also called Nil Depreciation cover or Bumper-to-Bumper cover. An add-on which gives you complete cover on any body parts of the car excluding tyres and batteries. Insurer will pay entire cost of body parts, ignoring the year-on-year depreciation in value of these parts.</div>"
                      data-html={true}
                      data-for="zero__Tooltipvol_m"
                      src={tooltip}
                      alt="tooltip"
                    />
                  </CustomTooltip>
                ) : (
                  <noscript />
                )}
              </span>
            </div>
          </FilterMenuBoxCheckConatiner>

          {temp_data?.parent?.productSubTypeCode !== "MISC" && (
            <FilterMenuBoxCheckConatiner>
              <div className="filterMenuBoxCheck">
                <input
                  type="checkbox"
                  className="form-check-input"
                  id={"Road Side Assistance"}
                  value={"Road Side Assistance"}
                  defaultChecked={rsa}
                  checked={rsa}
                  onChange={(e) => {
                    setRsa(e.target.checked);
                    setRsa2(false);
                  }}
                />

                <CustomTooltip
                  rider="true"
                  id="rsa__Tooltipvol"
                  place={"right"}
                  customClassName="mt-3  "
                >
                  <label
                    data-tip={
                      !lessthan767 &&
                      "<h3 >Road Side Assistance (Plan B)</h3> <div>This add on covers road side assistance for repair on the spot, Battery jump start, Flat type, Emergency towing on breakdown/accident, Fuel supply, Lost keys, Pickup vehicle in case of driver disability, Message relay, Arrangement of rental vehicle, Arrangement of accommodation, Referring a legal advisor, Referring a Hospital, and Extraction or removal of vehicle.</div>"
                    }
                    data-html={!lessthan767 && true}
                    data-for={!lessthan767 && "rsa__Tooltipvol"}
                    className="form-check-label"
                    htmlFor={"Road Side Assistance"}
                  >
                    {"Road Side Assistance"}
                  </label>
                </CustomTooltip>
                <span style={{ marginLeft: "3px" }}>
                  {lessthan767 ? (
                    <CustomTooltip
                      rider="true"
                      id="rsa__Tooltipvol_m"
                      place={"left"}
                      customClassName="mt-3 "
                      allowClick
                    >
                      <img
                        data-tip="<h3 >Road Side Assistance (Plan B)</h3> <div>This add on covers road side assistance for repair on the spot, Battery jump start, Flat type, Emergency towing on breakdown/accident, Fuel supply, Lost keys, Pickup vehicle in case of driver disability, Message relay, Arrangement of rental vehicle, Arrangement of accommodation, Referring a legal advisor, Referring a Hospital, and Extraction or removal of vehicle.</div>"
                        data-html={true}
                        data-for="rsa__Tooltipvol_m"
                        src={tooltip}
                        alt="tooltip"
                      />
                    </CustomTooltip>
                  ) : (
                    <noscript />
                  )}
                </span>
              </div>
            </FilterMenuBoxCheckConatiner>
          )}

          {temp_data?.parent?.productSubTypeCode !== "MISC" &&
            import.meta.env.VITE_BROKER === "SRIYAH" &&
            (motor || bike) && (
              <FilterMenuBoxCheckConatiner>
                <div className="filterMenuBoxCheck">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    id={"Road Side Assistance 2"}
                    value={"Road Side Assistance 2"}
                    defaultChecked={rsa2}
                    checked={rsa2}
                    onChange={(e) => {
                      setRsa(false);
                      setRsa2(e.target.checked);
                    }}
                  />

                  <CustomTooltip
                    rider="true"
                    id="rsa2__Tooltipvol"
                    place={"right"}
                    customClassName="mt-3  "
                  >
                    <label
                      data-tip={
                        !lessthan767 &&
                        "<h3 >RSA (for royal sundaram only)</h3> <div>This add on covers road side assistance for repair on the spot, Battery jump start, Flat type, Emergency towing on breakdown/accident, Fuel supply, Lost keys, Pickup vehicle in case of driver disability, Message relay, Arrangement of rental vehicle, Arrangement of accommodation, Referring a legal advisor, Referring a Hospital, Extraction or removal of vehicle, Taxi benefits, Ambulance charges, Additional coverage on towing on breakdown/accident, and accommodation benefits.</div>"
                      }
                      data-html={!lessthan767}
                      data-for={!lessthan767 && "rsa2__Tooltipvol"}
                      className="form-check-label"
                      htmlFor={"Road Side Assistance 2"}
                    >
                      {"Road Side Assistance (₹ 49)"}{" "}
                    </label>
                  </CustomTooltip>
                  <span style={{ marginLeft: "3px" }}>
                    {lessthan767 ? (
                      <CustomTooltip
                        rider="true"
                        id="rsa__Tooltipvol_m"
                        place={"left"}
                        customClassName="mt-3 "
                        allowClick
                      >
                        <img
                          data-tip="<h3 >Road Side Assistance</h3> <div>Roadside Assistance Coverage means a professional technician comes to your rescue when your car breaks down in the middle of the journey leaving you stranded.</div>"
                          data-html={true}
                          data-for="rsa__Tooltipvol_m"
                          src={tooltip}
                          alt="tooltip"
                          // className="toolTipRiderChild"
                        />
                      </CustomTooltip>
                    ) : (
                      <noscript />
                    )}
                  </span>
                </div>
              </FilterMenuBoxCheckConatiner>
            )}

          {temp_data?.parent?.productSubTypeCode !== "MISC" && (
            <FilterMenuBoxCheckConatiner>
              <div className="filterMenuBoxCheck">
                <input
                  type="checkbox"
                  className="form-check-input"
                  id={"Consumable"}
                  value={"Consumable"}
                  defaultChecked={consumables}
                  checked={consumables}
                  onChange={(e) => {
                    setConsumables(e.target.checked);
                  }}
                />

                <CustomTooltip
                  rider="true"
                  id="consumableTooltipvol"
                  place={"right"}
                  customClassName="mt-3  "
                >
                  <label
                    data-tip={
                      !lessthan767 &&
                      "<h3 >Consumable</h3> <div>The consumables in car insurance are those items that are subject to the constant wear and tear. They are continuously consumed by the car during its life for e.g nut and bolt, screw, washer, grease, lubricant, clips, A/C gas, bearings, distilled water, engine oil, oil filter, fuel filter, break oil and related parts.</div>"
                    }
                    data-html={!lessthan767 && true}
                    data-for={!lessthan767 && "consumableTooltipvol"}
                    className="form-check-label"
                    htmlFor={"Consumable"}
                  >
                    {"Consumable"}{" "}
                  </label>
                </CustomTooltip>
                <span style={{ marginLeft: "3px" }}>
                  {lessthan767 ? (
                    <CustomTooltip
                      rider="true"
                      id="consumableTooltipvol_m"
                      place={"left"}
                      customClassName="mt-3 "
                      allowClick
                    >
                      <img
                        data-tip="<h3 >Consumable</h3> <div>The consumables in car insurance are those items that are subject to the constant wear and tear. They are continuously consumed by the car during its life for e.g nut and bolt, screw, washer, grease, lubricant, clips, A/C gas, bearings, distilled water, engine oil, oil filter, fuel filter, break oil and related parts.</div>"
                        data-html={true}
                        data-for="consumableTooltipvol_m"
                        src={tooltip}
                        alt="tooltip"
                      />
                    </CustomTooltip>
                  ) : (
                    <noscript />
                  )}
                </span>
              </div>
            </FilterMenuBoxCheckConatiner>
          )}
          {
            <>
              {motor && (
                <FilterMenuBoxCheckConatiner>
                  <div className="filterMenuBoxCheck">
                    <input
                      type="checkbox"
                      className="form-check-input"
                      id={"Key Replacement"}
                      value={"Key Replacement"}
                      defaultChecked={keyReplace}
                      checked={keyReplace}
                      onChange={(e) => {
                        setKeyReplace(e.target.checked);
                      }}
                    />
                    <CustomTooltip
                      rider="true"
                      id="keyTooltipvol"
                      place={"right"}
                      customClassName="mt-3  "
                    >
                      <label
                        className="form-check-label"
                        htmlFor={"Key Replacement"}
                        data-tip={
                          !lessthan767 &&
                          "<h3 >Key Replacement</h3> <div>An add-on which covers cost of car keys and lock replacement or locksmith charges incase of your car keys is stolen.</div>"
                        }
                        data-html={!lessthan767 && true}
                        data-for={!lessthan767 && "keyTooltipvol"}
                        alt="tooltip"
                      >
                        {"Key Replacement"}{" "}
                      </label>
                    </CustomTooltip>
                    <span style={{ marginLeft: "3px" }}>
                      {lessthan767 ? (
                        <CustomTooltip
                          rider="true"
                          id="keyTooltipvol_m"
                          place={"right"}
                          customClassName="mt-3 "
                          allowClick
                        >
                          <img
                            data-tip={
                              "<h3 >Key Replacement</h3> <div>An add-on which covers cost of car keys and lock replacement or locksmith charges incase of your car keys is stolen.</div>"
                            }
                            data-html={true}
                            data-for={"keyTooltipvol_m"}
                            src={tooltip}
                            alt="tooltip"
                          />
                        </CustomTooltip>
                      ) : (
                        <noscript />
                      )}
                    </span>
                  </div>
                </FilterMenuBoxCheckConatiner>
              )}

              {temp_data?.parent?.productSubTypeCode !== "MISC" &&
                (motor || bike) &&
                fuelType &&
                fuelType !== "ELECTRIC" && (
                  <FilterMenuBoxCheckConatiner>
                    <div className="filterMenuBoxCheck">
                      <input
                        type="checkbox"
                        className="form-check-input"
                        id={"Engine Protector"}
                        value={"Engine Protector"}
                        defaultChecked={engineProtector}
                        checked={engineProtector}
                        onChange={(e) => {
                          setEngineProtector(e.target.checked);
                        }}
                      />

                      <CustomTooltip
                        rider="true"
                        id="engTooltipvol"
                        place={"right"}
                        customClassName="mt-3  "
                      >
                        <label
                          className="form-check-label"
                          htmlFor={"Engine Protector"}
                          data-tip={
                            !lessthan767 &&
                            "<h3 >Engine Protector</h3> <div>Engine protection cover in car insurance provides coverage towards damages or losses to the insured vehicle’s engine. The add-on compensates you for the replacement or repair of your car’s engine or parts.</div>"
                          }
                          data-html={!lessthan767 && true}
                          data-for={!lessthan767 && "engTooltipvol"}
                        >
                          {"Engine Protector"}{" "}
                        </label>
                      </CustomTooltip>
                      <span style={{ marginLeft: "3px" }}>
                        {lessthan767 ? (
                          <CustomTooltip
                            rider="true"
                            id="engTooltipvol_m"
                            place={"right"}
                            customClassName="mt-3 "
                            allowClick
                          >
                            <img
                              data-tip={
                                "<h3 >Engine Protector</h3> <div>Engine protection cover in car insurance provides coverage towards damages or losses to the insured vehicle’s engine. The add-on compensates you for the replacement or repair of your car’s engine or parts.</div>"
                              }
                              data-html={true}
                              data-for={"engTooltipvol_m"}
                              src={tooltip}
                              alt="tooltip"
                            />
                          </CustomTooltip>
                        ) : (
                          <noscript />
                        )}
                      </span>
                    </div>
                  </FilterMenuBoxCheckConatiner>
                )}

              {!bike &&
                temp_data?.parent?.productSubTypeCode !== "MISC" &&
                (motor || bike) && (
                  <FilterMenuBoxCheckConatiner>
                    <div className="filterMenuBoxCheck">
                      <input
                        type="checkbox"
                        className="form-check-input"
                        id={"NCB Protection"}
                        value={"NCB Protection"}
                        // value={ncbProtection}
                        defaultChecked={ncbProtection}
                        checked={ncbProtection}
                        onChange={(e) => {
                          setNcbProtectiont(e.target.checked);
                        }}
                      />

                      <CustomTooltip
                        rider="true"
                        id="ncbProtTooltipvol"
                        place={"right"}
                        customClassName="mt-3  "
                      >
                        <label
                          className="form-check-label"
                          data-tip={
                            !lessthan767 &&
                            "<h3 >NCB Protection</h3> <div>The NCB Protector protects Your Earned No claim Bonus, in the event of an Own Damage claim made for Partial Loss including claims for Windshield glass, Total Loss, and Theft of vehicle/ accessories. The No Claim Bonus will not get impacted for the first 2 claims preferred during the course of this policy per year.</div>"
                          }
                          data-html={!lessthan767 && true}
                          data-for={!lessthan767 && "ncbProtTooltipvol"}
                          htmlFor={"NCB Protection"}
                        >
                          {"NCB Protection"}{" "}
                        </label>
                      </CustomTooltip>
                      <span style={{ marginLeft: "3px" }}>
                        {lessthan767 ? (
                          <CustomTooltip
                            rider="true"
                            id="ncbProtTooltipvol_m"
                            place={"right"}
                            customClassName="mt-3 "
                            allowClick
                          >
                            <img
                              data-tip={
                                "<h3 >NCB Protection</h3> <div>The NCB Protector protects Your Earned No claim Bonus, in the event of an Own Damage claim made for Partial Loss including claims for Windshield glass, Total Loss, and Theft of vehicle/ accessories. The No Claim Bonus will not get impacted for the first 2 claims preferred during the course of this policy per year.</div>"
                              }
                              data-html={true}
                              data-for={"ncbProtTooltipvol_m"}
                              src={tooltip}
                              alt="tooltip"
                            />
                          </CustomTooltip>
                        ) : (
                          <noscript />
                        )}
                      </span>
                    </div>
                  </FilterMenuBoxCheckConatiner>
                )}

              {!bike && temp_data?.parent?.productSubTypeCode !== "MISC" && (
                <FilterMenuBoxCheckConatiner>
                  <div className="filterMenuBoxCheck">
                    <input
                      type="checkbox"
                      className="form-check-input"
                      id={"Tyre Secure"}
                      value={"Tyre Secure"}
                      // value={tyreSecure}
                      defaultChecked={tyreSecure}
                      checked={tyreSecure}
                      onChange={(e) => {
                        setTyreSecure(e.target.checked);
                      }}
                    />

                    <CustomTooltip
                      rider="true"
                      id="tyreTooltipvol"
                      place={"right"}
                      customClassName="mt-3  "
                    >
                      <label
                        data-tip={
                          !lessthan767 &&
                          "<h3 >Tyre Secure</h3> <div>This is an add-on cover which covers the damages to the tyre of the car caused due to accidental external means. The cost of tyre replacement, rebalancing, removal and refitting is covered.</div>"
                        }
                        data-html={!lessthan767 && true}
                        data-for={!lessthan767 && "tyreTooltipvol"}
                        className="form-check-label"
                        htmlFor={"Tyre Secure"}
                      >
                        {"Tyre Secure"}{" "}
                      </label>
                    </CustomTooltip>
                    <span style={{ marginLeft: "3px" }}>
                      {lessthan767 ? (
                        <CustomTooltip
                          rider="true"
                          id="tyreTooltipvol_m"
                          place={"right"}
                          customClassName="mt-3 "
                          allowClick
                        >
                          <img
                            data-tip={
                              "<h3 >Tyre Secure</h3> <div>This is an add-on cover which covers the damages to the tyre of the car caused due to accidental external means. The cost of tyre replacement, rebalancing, removal and refitting is covered.</div>"
                            }
                            data-html={true}
                            data-for={"tyreTooltipvol_m"}
                            src={tooltip}
                            alt="tooltip"
                          />
                        </CustomTooltip>
                      ) : (
                        <noscript />
                      )}
                    </span>
                  </div>
                </FilterMenuBoxCheckConatiner>
              )}
              {temp_data?.parent?.productSubTypeCode !== "MISC" && (
                <FilterMenuBoxCheckConatiner>
                  <div className="filterMenuBoxCheck">
                    <input
                      type="checkbox"
                      className="form-check-input"
                      id={"Return To Invoice"}
                      value={"Return To Invoice"}
                      defaultChecked={returnToInvoice}
                      checked={returnToInvoice}
                      onChange={(e) => {
                        setReturnToInvoice(e.target.checked);
                      }}
                    />

                    <CustomTooltip
                      rider="true"
                      id="roiTooltipvol"
                      place={"right"}
                      customClassName="mt-3  "
                    >
                      <label
                        data-tip={
                          !lessthan767 &&
                          "<h3 >Return To Invoice</h3> <div>Return to Invoice cover is an add-on cover offered in a comprehensive vehicle insurance plan. It allows the insured customer to receive full compensation, i.e. the last complete invoice value of their vehicle, in case it has been stolen or damaged beyond repair.</div>"
                        }
                        data-html={!lessthan767 && true}
                        data-for={!lessthan767 && "roiTooltipvol"}
                        className="form-check-label"
                        htmlFor={"Return To Invoice"}
                      >
                        {"Return To Invoice"}{" "}
                      </label>
                    </CustomTooltip>
                    <span style={{ marginLeft: "3px" }}>
                      {lessthan767 ? (
                        <CustomTooltip
                          rider="true"
                          id="roiTooltipvol_m"
                          place={"right"}
                          customClassName="mt-3 "
                          allowClick
                        >
                          <img
                            data-tip={
                              "<h3 >Return To Invoice</h3> <div>Return to Invoice is an add-on option which covers the gap between the insured declared value and the invoice value of your car along with the registration and other applicable taxes.</div>"
                            }
                            data-html={true}
                            data-for={"roiTooltipvol_m"}
                            src={tooltip}
                            alt="tooltip"
                          />
                        </CustomTooltip>
                      ) : (
                        <noscript />
                      )}
                    </span>
                  </div>
                </FilterMenuBoxCheckConatiner>
              )}
              {!bike &&
                temp_data?.parent?.productSubTypeCode !== "MISC" &&
                (motor || bike) && (
                  <FilterMenuBoxCheckConatiner>
                    <div className="filterMenuBoxCheck">
                      <input
                        type="checkbox"
                        className="form-check-input"
                        id={"Loss of Personal Belongings"}
                        value={"Loss of Personal Belongings"}
                        defaultChecked={lopb}
                        checked={lopb}
                        onChange={(e) => {
                          setLopb(e.target.checked);
                        }}
                      />

                      <CustomTooltip
                        rider="true"
                        id="lopb__Tooltipvol"
                        place={"right"}
                        customClassName="mt-3  "
                      >
                        <label
                          data-tip={
                            !lessthan767 &&
                            "<h3 >Loss of Personal Belongings</h3> <div>With this cover in place, your insurer will cover losses arising due to damage or theft of your personal Belongings from the insured car as per the terms and conditions of the policy.</div>"
                          }
                          data-html={!lessthan767 && true}
                          data-for={!lessthan767 && "lopb__Tooltipvol"}
                          className="form-check-label"
                          htmlFor={"Loss of Personal Belongings"}
                        >
                          {"Loss of Personal Belongings"}{" "}
                        </label>
                      </CustomTooltip>
                      <span style={{ marginLeft: "3px" }}>
                        {lessthan767 ? (
                          <CustomTooltip
                            rider="true"
                            id="lopb__Tooltipvol_m"
                            place={"left"}
                            customClassName="mt-3 "
                            allowClick
                          >
                            <img
                              data-tip={
                                "<h3 >Loss of Personal Belongings</h3> <div>With this cover in place, your insurer will cover losses arising due to damage or theft of your personal Belongings from the insured car as per the terms and conditions of the policy.</div>"
                              }
                              data-html={true}
                              data-for={"lopb__Tooltipvol_m"}
                              src={tooltip}
                              alt="tooltip"
                            />
                          </CustomTooltip>
                        ) : (
                          <noscript />
                        )}
                      </span>
                    </div>
                  </FilterMenuBoxCheckConatiner>
                )}
              {temp_data?.parent?.productSubTypeCode !== "MISC" && (
                <FilterMenuBoxCheckConatiner>
                  <div className="filterMenuBoxCheck">
                    <input
                      type="checkbox"
                      className="form-check-input"
                      id={"Emergency Medical Expenses"}
                      value={"Emergency Medical Expenses"}
                      defaultChecked={emergencyMedicalExpenses}
                      checked={emergencyMedicalExpenses}
                      onChange={(e) => {
                        setEmergencyMedicalExpenses(e.target.checked);
                      }}
                    />
                    <CustomTooltip
                      rider="true"
                      id="tyreTooltipvol"
                      place={"right"}
                      customClassName="mt-3  "
                    >
                      <label
                        data-tip={
                          !lessthan767 &&
                          "<h3 >Emergency Medical Expenses</h3> <div>Emergency Medical Expenses Cover compensates medical costs for injuries sustained in a motor accident.</div>"
                        }
                        data-html={!lessthan767 && true}
                        data-for={!lessthan767 && "tyreTooltipvol"}
                        className="form-check-label"
                        htmlFor={"Emergency Medical Expenses"}
                      >
                        {"Emergency Medical Expenses"}{" "}
                      </label>
                    </CustomTooltip>
                    <span style={{ marginLeft: "3px" }}>
                      {lessthan767 ? (
                        <CustomTooltip
                          rider="true"
                          id="tyreTooltipvol_m"
                          place={"right"}
                          customClassName="mt-3 "
                          allowClick
                        >
                          <img
                            data-tip={
                              "<h3 >Emergency Medical Expenses</h3> <div>Emergency Medical Expenses Cover compensates medical costs for injuries sustained in a motor accident.</div>"
                            }
                            data-html={true}
                            data-for={"tyreTooltipvol_m"}
                            src={tooltip}
                            alt="tooltip"
                          />
                        </CustomTooltip>
                      ) : (
                        <noscript />
                      )}
                    </span>
                  </div>
                </FilterMenuBoxCheckConatiner>
              )}
              {/* Windshield addon  */}
              {motor && (
                <FilterMenuBoxCheckConatiner>
                  <div className="filterMenuBoxCheck">
                    <input
                      type="checkbox"
                      className="form-check-input"
                      id={"Wind Shield"}
                      value={"Wind Shield"}
                      defaultChecked={windshield}
                      checked={windshield}
                      onChange={(e) => {
                        setWindShield(e.target.checked);
                      }}
                    />
                    <CustomTooltip
                      rider="true"
                      id="windshieldTooltipvol"
                      place={"right"}
                      customClassName="mt-3  "
                    >
                      <label
                        data-tip={
                          !lessthan767 &&
                          "<h3 >Wind Shield</h3> <div>Windshield Glass Cover is an add-on cover for your car insurance policy that covers the cost of repairing or replacing your windshield glass in case of damage due to accidents.</div>"
                        }
                        data-html={!lessthan767 && true}
                        data-for={!lessthan767 && "windshieldTooltipvol"}
                        className="form-check-label"
                        htmlFor={"Wind Shield"}
                      >
                        {"Wind Shield"}{" "}
                      </label>
                    </CustomTooltip>
                    <span style={{ marginLeft: "3px" }}>
                      {lessthan767 ? (
                        <CustomTooltip
                          rider="true"
                          id="windshieldTooltipvol_m"
                          place={"right"}
                          customClassName="mt-3 "
                          allowClick
                        >
                          <img
                            data-tip={
                              "<h3 >Wind Shield</h3> <div>Windshield Glass Cover is an add-on cover for your car insurance policy that covers the cost of repairing or replacing your windshield glass in case of damage due to accidents.</div>"
                            }
                            data-html={true}
                            data-for={"windshieldTooltipvol_m"}
                            src={tooltip}
                            alt="tooltip"
                          />
                        </CustomTooltip>
                      ) : (
                        <noscript />
                      )}
                    </span>
                  </div>
                </FilterMenuBoxCheckConatiner>
              )}

              {/* EMI Protection addon */}
              <FilterMenuBoxCheckConatiner>
                <div className="filterMenuBoxCheck">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    id={"EMI Protection"}
                    value={"EMI Protection"}
                    defaultChecked={emiprotection}
                    checked={emiprotection}
                    onChange={(e) => {
                      setEmiprotection(e.target.checked);
                    }}
                  />
                  <CustomTooltip
                    rider="true"
                    id="emiprotectionTooltipvol"
                    place={"right"}
                    customClassName="mt-3  "
                  >
                    <label
                      data-tip={
                        !lessthan767 &&
                        "<h3 >EMI Protection</h3> <div>EMI Protection Cover ensures EMI payments during vehicle repairs after accidents, minimizing financial burden.</div>"
                      }
                      data-html={!lessthan767 && true}
                      data-for={!lessthan767 && "emiprotectionTooltipvol"}
                      className="form-check-label"
                      htmlFor={"EMI Protection"}
                    >
                      {"EMI Protection"}
                    </label>
                  </CustomTooltip>
                  <span style={{ marginLeft: "3px" }}>
                    {lessthan767 ? (
                      <CustomTooltip
                        rider="true"
                        id="windshieldTooltipvol_m"
                        place={"right"}
                        customClassName="mt-3 "
                        allowClick
                      >
                        <img
                          data-tip={
                            "<h3 >EMI Protection</h3> <div>EMI Protection Cover ensures EMI payments during vehicle repairs after accidents, minimizing financial burden.</div>"
                          }
                          data-html={true}
                          data-for={"windshieldTooltipvol_m"}
                          src={tooltip}
                          alt="tooltip"
                        />
                      </CustomTooltip>
                    ) : (
                      <noscript />
                    )}
                  </span>
                </div>
              </FilterMenuBoxCheckConatiner>

              {/* Additional Towing addon */}
              <FilterMenuBoxCheckConatiner>
                <div className="filterMenuBoxCheck">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    id={"Additional Towing"}
                    value={"Additional Towing"}
                    defaultChecked={additionalTowing}
                    checked={additionalTowing}
                    onChange={(e) => {
                      setAdditionalTowing(e.target.checked);
                    }}
                  />
                  <CustomTooltip
                    rider="true"
                    id="additionalTowingTooltipvol"
                    place={"right"}
                    customClassName="mt-3  "
                  >
                    <label
                      data-tip={
                        !lessthan767 &&
                        "<h3 >Additional Towing</h3> <div>Additional Towing Cover reimburses extra towing charges if your vehicle breaks down or is damaged.</div>"
                      }
                      data-html={!lessthan767 && true}
                      data-for={!lessthan767 && "additionalTowingTooltipvol"}
                      className="form-check-label"
                      htmlFor={"Additional Towing"}
                    >
                      {"Additional Towing"}{" "}
                    </label>
                  </CustomTooltip>
                  <span style={{ marginLeft: "3px" }}>
                    {lessthan767 ? (
                      <CustomTooltip
                        rider="true"
                        id="additionalTowingTooltipvol_m"
                        place={"right"}
                        customClassName="mt-3 "
                        allowClick
                      >
                        <img
                          data-tip={
                            "<h3 >Additional Towing</h3> <div>Additional Towing Cover reimburses extra towing charges if your vehicle breaks down or is damaged.</div>"
                          }
                          data-html={true}
                          data-for={"additionalTowingTooltipvol_m"}
                          src={tooltip}
                          alt="tooltip"
                        />
                      </CustomTooltip>
                    ) : (
                      <noscript />
                    )}
                  </span>
                </div>
              </FilterMenuBoxCheckConatiner>

              {/* This Add On will only be visible in Electric Vehicles */}
              {/* Battery Protect Addon */}
              {temp_data?.fuel === "ELECTRIC" && (
                // import.meta.env.VITE_PROD === "NO" && (
                  <FilterMenuBoxCheckConatiner>
                    <div className="filterMenuBoxCheck">
                      <input
                        type="checkbox"
                        className="form-check-input"
                        id={"Battery Protect"}
                        value={"Battery Protect"}
                        defaultChecked={batteryprotect}
                        checked={batteryprotect}
                        onChange={(e) => {
                          setBatteryprotect(e.target.checked);
                        }}
                      />
                      <CustomTooltip
                        rider="true"
                        id="batteryprotectTooltipvol"
                        place={"right"}
                        customClassName="mt-3  "
                      >
                        <label
                          data-tip={
                            !lessthan767 &&
                            "<h3 >Battery Protect</h3> <div>Battery Protection Cover in motor insurance safeguards against battery damage, theft, or repair costs.</div>"
                          }
                          data-html={!lessthan767}
                          data-for={!lessthan767 && "batteryprotectTooltipvol"}
                          className="form-check-label"
                          htmlFor={"Battery Protect"}
                        >
                          {"Battery Protect"}
                        </label>
                      </CustomTooltip>
                      <span style={{ marginLeft: "3px" }}>
                        {lessthan767 ? (
                          <CustomTooltip
                            rider="true"
                            id="batteryprotectTooltipvol_m"
                            place={"right"}
                            customClassName="mt-3 "
                            allowClick
                          >
                            <img
                              data-tip={
                                "<h3 >Battery Protect</h3> <div>Battery Protection Cover in motor insurance safeguards against battery damage, theft, or repair costs.</div>"
                              }
                              data-html={true}
                              data-for={"batteryprotectTooltipvol_m"}
                              src={tooltip}
                              alt="tooltip"
                            />
                          </CustomTooltip>
                        ) : (
                          <noscript />
                        )}
                      </span>
                    </div>
                  </FilterMenuBoxCheckConatiner>
                )}
            </>
          }
        </CardBlock>
      ) : (
        <noscript />
      )}
    </>
  );
};

export default Addons;
