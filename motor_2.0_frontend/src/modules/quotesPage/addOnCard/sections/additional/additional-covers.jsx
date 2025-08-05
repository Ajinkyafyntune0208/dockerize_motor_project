import React from "react";
import tooltip from "../../../../../assets/img/tooltip.svg";
import {
  CardBlock,
  Dropdown,
  FilterMenuBoxCheckConatiner,
  InputFieldSmall,
  SubCheckBox,
} from "../../style";
import { Checkbox, CustomTooltip, ErrorMsg } from "components";
import { BlockedSections } from "../../cardConfig";
import { Form } from "react-bootstrap";
import { errorAlert } from "../../cardConfig";
import { getCoverValue } from "modules/quotesPage/quoteUtil";
import {
  CancelAll,
  SaveAddonsData,
  SetaddonsAndOthers,
} from "modules/quotesPage/quote.slice";
import UpdateButton from "../../_components/update-btn";
import { useDispatch } from "react-redux";
import { numOnlyNoZero } from "utils";

const AdditionalCovers = (props) => {
  // prettier-ignore
  const {
    lessthan767, show_Imt23, imt23, setImt23, temp_data, gcvJourney, shortCompPolicy3, shortCompPolicy6, bike,
    register, selectedAdditions, additionalPaidDriver, setAdditionalPaidDriver, unNamedCover, unNamedCoverValue, 
    setUnNamedCoverValue, selectedLLpaidItmes, LLCountPrefillDriver, errors, numOnly, LLCountPrefillConductor, 
    LLCountPrefillCleaner, paPaidDriverGCV, setPaPaidDriverGCV, showUpdateButtonAddtions, handleSubmit,
    LLNumberDriver, LLNumberCleaner, LLNumberConductor, countries, userData, enquiry_id, isAdditionalEmpty,
    nfppValuePrefill, nfppCurrentValue
  } = props

  const dispatch = useDispatch();
  const onSubmitAdditions = () => {
    if (isAdditionalEmpty) {
      errorAlert();
    } else {
      dispatch(CancelAll(true)); // cancel all apis loading (quotes apis)
      var data = {
        selectedAdditions: selectedAdditions,
        LLpaidItmes: selectedLLpaidItmes,
        unNamedCoverValue: unNamedCoverValue,
        additionalPaidDriver: additionalPaidDriver,
        LLNumberDriver: LLNumberDriver,
        LLNumberConductor: LLNumberConductor,
        LLNumberCleaner: LLNumberCleaner,
        paPaidDriverGCV: paPaidDriverGCV,
        nfppValue: nfppCurrentValue,
        countries: countries,
      };
      var newSelectedAddition = [];
      if (selectedAdditions?.includes("PA cover for additional paid driver")) {
        let newD = {
          name: "PA cover for additional paid driver",
          sumInsured: getCoverValue(additionalPaidDriver),
        };
        newSelectedAddition.push(newD);
      }
      if (selectedAdditions?.includes("Unnamed Passenger PA Cover")) {
        let newD = {
          name: "Unnamed Passenger PA Cover",
          sumInsured: getCoverValue(unNamedCoverValue),
        };
        newSelectedAddition.push(newD);
      }
      if (selectedAdditions?.includes("LL paid driver/conductor/cleaner")) {
        let newD = {
          name: "LL paid driver/conductor/cleaner",
          selectedLLpaidItmes: selectedLLpaidItmes,
          LLNumberDriver: selectedLLpaidItmes?.includes("DriverLL")
            ? Number(LLNumberDriver)
            : 0,
          LLNumberConductor: selectedLLpaidItmes?.includes("ConductorLL")
            ? Number(LLNumberConductor)
            : 0,
          LLNumberCleaner: selectedLLpaidItmes?.includes("CleanerLL")
            ? Number(LLNumberCleaner)
            : 0,
        };
        newSelectedAddition.push(newD);
      }
      if (selectedAdditions?.includes("PA paid driver/conductor/cleaner")) {
        let newD = {
          name: "PA paid driver/conductor/cleaner",
          sumInsured: getCoverValue(paPaidDriverGCV),
        };
        newSelectedAddition.push(newD);
      }
      if (selectedAdditions?.includes("Geographical Extension")) {
        let newD = {
          name: "Geographical Extension",
          countries: countries,
        };
        newSelectedAddition.push(newD);
      }
      if (selectedAdditions?.includes("LL paid driver")) {
        let newD = {
          name: "LL paid driver",
          sumInsured: 100000,
        };
        newSelectedAddition.push(newD);
      }
      if (selectedAdditions?.includes("NFPP Cover")) {
        let newD = {
          name: "NFPP Cover",
          nfppValue: nfppCurrentValue,
        };
        newSelectedAddition.push(newD);
      }
      let data1 = {
        enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
        addonData: { additional_covers: newSelectedAddition },
      };

      dispatch(SetaddonsAndOthers(data));
      dispatch(SaveAddonsData(data1));
      // resetting cancel all apis loading so quotes will restart (quotes apis)
      dispatch(CancelAll(false));
    }
  };

  return (
    <div className={"showAddon"}>
      <CardBlock>
        {show_Imt23 && (
          <>
            <FilterMenuBoxCheckConatiner>
              <div className="filterMenuBoxCheck">
                <input
                  type="checkbox"
                  className="form-check-input"
                  id={"IMT - 23"}
                  value={"IMT - 23"}
                  defaultChecked={imt23}
                  checked={imt23}
                  onChange={(e) => {
                    setImt23(e.target.checked);
                  }}
                />

                <CustomTooltip
                  rider="true"
                  id="imtTooltipvol"
                  place={"right"}
                  customClassName="mt-3  "
                >
                  <label
                    data-tip={
                      !lessthan767 &&
                      `<h3 >IMT - 23</h3><div>Add-on for commercial vehicles covering at least 50% damage to specific parts like lamps, tires, and body panels.</div>
                      `
                    }
                    data-html={!lessthan767 && true}
                    data-for={!lessthan767 && "imtTooltipvol"}
                    className="form-check-label"
                    htmlFor={"IMT - 23"}
                  >
                    {"IMT - 23"}{" "}
                  </label>
                </CustomTooltip>
                <span style={{ marginLeft: "3px" }}>
                  {lessthan767 ? (
                    <CustomTooltip
                      rider="true"
                      id="imtTooltipvol_m"
                      place={"right"}
                      customClassName="mt-3 "
                      allowClick
                    >
                      <img
                        data-tip={`<h3 >IMT - 23</h3> <div>COVER FOR LAMPS TYRES / TUBES MUDGUARDS BONNET
                                /SIDE PARTS BUMPERS HEADLIGHTS AND PAINTWORK OF
                                DAMAGED PORTION ONLY .</div>`}
                        data-html={true}
                        data-for={"imtTooltipvol_m"}
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
          </>
        )}
        {!temp_data?.odOnly && true && (
          <>
            <div
              className={
                // gcvJourney ||
                // shortCompPolicy3 ||
                // shortCompPolicy6 ||
                bike ||
                // temp_data?.parent?.productSubTypeCode === "MISC" ||
                ""
                  ? "hideAddon"
                  : "showAddon"
              }
            >
              <div>
                <Checkbox
                  id={"PA cover for additional paid driver"}
                  fieldName={"PA cover for additional paid driver"}
                  register={register}
                  index={0}
                  name="additional"
                  tooltipData={
                    "Under this cover, the insurer pays compensation to your driver in case of any injury, disability or even death."
                  }
                />
                {selectedAdditions?.includes(
                  "PA cover for additional paid driver"
                ) && (
                  <>
                    <Dropdown
                      name="additionalPaidDriver"
                      ref={register}
                      id={"additionalPaidDriver"}
                      onChange={(e) => setAdditionalPaidDriver(e.target.value)}
                      value={additionalPaidDriver}
                    >
                      {unNamedCover.map((option, index) => (
                        <option key={index} value={option}>
                          {option}
                        </option>
                      ))}
                    </Dropdown>
                  </>
                )}
              </div>
            </div>

            <div
              className={
                gcvJourney ||
                BlockedSections(
                  import.meta.env.VITE_BROKER,
                  temp_data?.journeyCategory
                ).includes("unnamed pa cover") ||
                temp_data?.parent?.productSubTypeCode === "MISC"
                  ? "hideAddon"
                  : "showAddon"
              }
            >
              <div>
                <Checkbox
                  id={"Unnamed Passenger PA Cover"}
                  fieldName={"Unnamed Passenger PA Cover"}
                  register={register}
                  index={1}
                  name="additional"
                  tooltipData={
                    "Covers all the passengers of your car in case of death or disability due to an accident. Naming of individuals is not required"
                  }
                />

                {selectedAdditions?.includes("Unnamed Passenger PA Cover") && (
                  <>
                    <Dropdown
                      name="unNamedCovervalue"
                      ref={register}
                      id="unNamedCovervalue"
                      onChange={(e) => setUnNamedCoverValue(e.target.value)}
                      value={unNamedCoverValue}
                    >
                      {unNamedCover.map((option, index) => (
                        <option key={index} value={option}>
                          {option}
                        </option>
                      ))}
                    </Dropdown>
                  </>
                )}
              </div>
            </div>

            <div
              className={
                !gcvJourney && temp_data?.parent?.productSubTypeCode !== "MISC"
                  ? "hideAddon"
                  : "showAddon"
              }
            >
              <div>
                <Checkbox
                  id={"LL paid driver/conductor/cleaner"}
                  fieldName={"LL paid driver/conductor/cleaner"}
                  register={register}
                  index={2}
                  name="additional"
                  tooltipData={
                    "Covers owner's legal responsibility for injury/death of paid staff (driver, conductor, cleaner) while on duty."
                  }
                />

                {selectedAdditions?.includes(
                  "LL paid driver/conductor/cleaner"
                ) && (
                  <SubCheckBox>
                    <Checkbox
                      id={"DriverLL"}
                      fieldName={"Driver"}
                      register={register}
                      index={0}
                      name="LLpaidItmes"
                    />
                    {selectedLLpaidItmes?.includes("DriverLL") && (
                      <>
                        <InputFieldSmall>
                          <Form.Control
                            type="text"
                            placeholder="Enter Count"
                            name="LLNumberDriver"
                            defaultValue={LLCountPrefillDriver}
                            minlength="1"
                            ref={register}
                            onInput={(e) =>
                              (e.target.value =
                                e.target.value.length <= 1
                                  ? "" + e.target.value
                                  : e.target.value)
                            }
                            errors={errors.LLNumberDriver}
                            size="sm"
                            maxLength="1"
                            onKeyDown={numOnly}
                          />
                          {!!errors.LLNumberDriver && (
                            <ErrorMsg fontSize={"12px"}>
                              {errors.LLNumberDriver.message}
                            </ErrorMsg>
                          )}
                        </InputFieldSmall>
                      </>
                    )}
                    <Checkbox
                      id={"ConductorLL"}
                      fieldName={"Conductor/Cleaner"}
                      register={register}
                      index={1}
                      name="LLpaidItmes"
                    />
                    {selectedLLpaidItmes?.includes("ConductorLL") && (
                      <>
                        <InputFieldSmall>
                          <Form.Control
                            type="text"
                            placeholder="Enter Count"
                            name="LLNumberConductor"
                            defaultValue={LLCountPrefillConductor}
                            minlength="1"
                            maxLength="1"
                            ref={register}
                            onInput={(e) =>
                              (e.target.value =
                                e.target.value.length <= 1
                                  ? "" + e.target.value
                                  : e.target.value)
                            }
                            errors={errors.LLNumberConductor}
                            size="sm"
                            onKeyDown={numOnly}
                          />
                          {!!errors.LLNumberConductor && (
                            <ErrorMsg fontSize={"12px"}>
                              {errors.LLNumberConductor.message}
                            </ErrorMsg>
                          )}
                        </InputFieldSmall>
                      </>
                    )}
                    {/* <Checkbox
                      id={"CleanerLL"}
                      fieldName={"Cleaner"}
                      register={register}
                      index={2}
                      name="LLpaidItmes"
                    />
                    {selectedLLpaidItmes?.includes("CleanerLL") && (
                      <>
                        <InputFieldSmall>
                          <Form.Control
                            type="text"
                            placeholder="Enter Number of Cleaner"
                            name="LLNumberCleaner"
                            defaultValue={LLCountPrefillCleaner}
                            minlength="1"
                            maxLength="1"
                            ref={register}
                            onInput={(e) =>
                              (e.target.value =
                                e.target.value.length <= 1
                                  ? "" + e.target.value
                                  : e.target.value)
                            }
                            errors={errors.LLNumberCleaner}
                            size="sm"
                            onKeyDown={numOnly}
                          />
                          {!!errors.LLNumberCleaner && (
                            <ErrorMsg fontSize={"12px"}>
                              {errors.LLNumberCleaner.message}
                            </ErrorMsg>
                          )}
                        </InputFieldSmall>
                      </>
                    )} */}
                  </SubCheckBox>
                )}
              </div>

              {/* {
                <div>
                  <Checkbox
                    id={"PA paid driver/conductor/cleaner"}
                    fieldName={"PA paid driver/conductor/cleaner"}
                    register={register}
                    index={3}
                    name="additional"
                    tooltipData={""}
                  />

                  {selectedAdditions?.includes(
                    "PA paid driver/conductor/cleaner"
                  ) && (
                    <>
                      <Dropdown
                        name="paPaidDriverGCV"
                        ref={register}
                        onChange={(e) => setPaPaidDriverGCV(e.target.value)}
                        value={paPaidDriverGCV}
                      >
                        {unNamedCover.map((option, index) => (
                          <option key={index} value={option}>
                            {option}
                          </option>
                        ))}
                      </Dropdown>
                    </>
                  )}
                </div>
              } */}
            </div>

            <div
              className={
                gcvJourney ||
                temp_data?.parent?.productSubTypeCode === "MISC" ||
                bike
                  ? "hideAddon"
                  : "showAddon"
              }
            >
              <Checkbox
                id={"LL paid driver"}
                fieldName={"LL paid driver"}
                register={register}
                index={4}
                name="additional"
                tooltipData={
                  "Under this cover, the insurer shall indemnify the insured against the insured's legal liability under the Workmen's Compensation Act, 1923 , the Fatal Accidents Act, 1855 or at Common Law"
                }
              />
            </div>
          </>
        )}
        {import.meta.env.VITE_BROKER !== "OLA" && (
          <div>
            <Checkbox
              id={"Geographical Extension"}
              fieldName={"Geographical Extension"}
              register={register}
              index={5}
              name="additional"
              tooltipData={
                "Motor insurance policy can be extended to provide coverage to your private vehicles outside India as well. There are a few geographical zones where you can get the benefits of your insurance policy. The 6 neighboring countries of India where you can take the benefit of your motor insurance policy are Bangladesh, Nepal, Bhutan, Pakistan, Maldives and Sri Lanka."
              }
            />
            {selectedAdditions?.includes("Geographical Extension") && (
              <SubCheckBox>
                {[
                  "Sri Lanka",
                  "Bhutan",
                  "Nepal",
                  "Bangladesh",
                  "Pakistan",
                  "Maldives",
                ].map((item, index) => (
                  <Checkbox
                    id={item}
                    register={register}
                    fieldName={item}
                    index={index}
                    name={"country"}
                    tooltipData={""}
                  />
                ))}
              </SubCheckBox>
            )}
          </div>
        )}
        {
          <div className={!gcvJourney ? "hideAddon" : "showAddon"}>
            <Checkbox
              id={"NFPP Cover"}
              fieldName={"NFPP Cover"}
              register={register}
              index={6}
              name="additional"
              tooltipData={
                "An add-on in commercial vehicle insurance that provides coverage for injuries or death to individuals travelling in the vehicle who are <b>not paying a fare</b>."
              }
            />
          </div>
        }
        {selectedAdditions?.includes("NFPP Cover") && (
          <>
            <InputFieldSmall>
              <Form.Control
                type="text"
                placeholder="Enter value "
                name="nfppValue"
                defaultValue={nfppValuePrefill}
                minlength="2"
                ref={register}
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? "" + e.target.value.replace(/[^0-9]/gi, "")
                      : e.target.value)
                }
                errors={errors.nfppValue}
                size="sm"
                maxLength="6"
                onKeyDown={(e) => {
                  numOnlyNoZero(e);
                }}
              />
              {!!errors.nfppValue && (
                <ErrorMsg fontSize={"12px"}>
                  {errors.nfppValue.message}
                </ErrorMsg>
              )}
            </InputFieldSmall>
          </>
        )}
        {showUpdateButtonAddtions && (
          <UpdateButton
            id={"updateAdditionsButton"}
            onClick={handleSubmit(onSubmitAdditions)}
          />
        )}
      </CardBlock>
    </div>
  );
};

export default AdditionalCovers;
