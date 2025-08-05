import React, { useState } from "react";
import { useForm } from "react-hook-form";
import { useMediaPredicate } from "react-media-hook";
import CustomTooltip from "../../../components/tooltip/CustomTooltip";
import { useDispatch, useSelector } from "react-redux";
import { Col } from "react-bootstrap";
import _ from "lodash";
import { numOnly, numOnlyNoZero } from "utils";
import { useLocation } from "react-router";
import CpaPopup from "../quotesPopup/cpaPopup/cpaPopup";
import ZeroDepPopup from "../quotesPopup/zeroDepPopup/zeroDepPopup";
import SecureLS from "secure-ls";
import ThemeObj from "modules/theme-config/theme-config";
import { unNamedCoverFn } from "../quoteUtil";
// prettier-ignore
import {
  useClearAll, useClearAllButtonVisibility, useFetchVoluntaryList, useGetGcvFlag,
  useGetVehicleTypeFlag, usePrefillAddonsAndOthers, useSetTabState,
  useUpdateQuotesData, useUpdateStatusTimeout,
} from "./addon-card-hook";
import Accessories from "./sections/accessories/accessories";
import AccordionWrapper from "./_components/wrapper/accordion-wrapper";
import LongTermPolices from "./sections/long-term-polices";
import Filter from "./_components/filter";
import Collapse from "./_components/collapse";
import PlanTypes from "./sections/plan-types/plan-types";
import Cpa from "./sections/cpa/cpa";
import AdditionalCovers from "./sections/additional/additional-covers";
import Discounts from "./sections/discount/discounts";
import Addons from "./sections/addons/addons";
import WeightRange from "./_components/weight-range";
import DiscountRange from "./_components/discount-range";
import { AccordionTab, AddOnTitle, CardOtherItem } from "./style";
import ClearAll from "./_components/clear-all";
import {
  useGetShortTerm3Flag,
  useGetShortTerm3FlagAce,
  useGetShortTerm6Flag,
} from "./sections/plan-types/plan-types-hook";
import { useSaveAddonData } from "./sections/addons/addons-hook";
// prettier-ignore
import { useHandleCpaChanges, useResetCpaOnSaod, useSetCpa } from "./sections/cpa/cpa-hook";
import { useAccessoriesUpdateButtonVisibility } from "./sections/accessories/accessories-hook";
import { useAdditionalUpdateButtonVisibility } from "./sections/additional/additional-hook";
import { useDiscountUpdateButtonVisibility } from "./sections/discount/discount-hook";

//---------------------Theme imports----------------------------

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme1 = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

export const AddOnsCard = ({
  tab,
  type,
  setShortTerm3,
  setShortTerm6,
  policyTypeCode,
  setRelevant,
  isRelevant,
  setRenewalFilter,
  renewalFilter,
  setSortBy,
  sortBy,
  longTerm2,
  longTerm3,
  setLongterm2,
  setLongterm3,
  setQuoteComprehesiveGrouped,
  setQuoteComprehesiveGrouped1,
  setUngroupedQuoteShortTerm3,
  setUngroupedQuoteShortTerm6,
  setGroupedQuoteShortTerm3,
  setGroupedQuoteShortTerm6,
  setQuoteShortTerm3,
  setQuoteShortTerm6,
  setQuoteTpGrouped1,
  gstToggle,
  setGstToggle,
}) => {
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const userData = useSelector((state) => state.home);
  const { temp_data, theme_conf, vahaanConfig } = useSelector((state) => state.home);
  const { voluntaryList, addOnsAndOthers } = useSelector(
    (state) => state.quotes
  );
  const { tempData, saveQuote } = useSelector((state) => state.quoteFilter);
  const dispatch = useDispatch();
  const { handleSubmit, register, watch, errors, setValue } = useForm();
  // -------------------defining all states--------------------------------
  const [accordionId, setAccordionId] = useState(0);
  const [showUpdateButtonAccesories, setShowUpdateButtonAccesories] =
    useState(false);
  const [showUpdateButtonAddtions, setShowUpdateButtonAdditions] =
    useState(false);
  const [showUpdateButtonDiscount, setShowUpdateButtonDiscount] =
    useState(false);
  const [eventKey, setEventKey] = useState(false);
  const [openAll, setOpenAll] = useState(false);
  const unNamedCover = unNamedCoverFn(type);

  //---------------calling voluntary list api-----------------------------
  useFetchVoluntaryList(voluntaryList, dispatch, temp_data, type);

  var newVoluntaryList = voluntaryList?.map((a) => a.deductibleAmount);
  const volDiscount = [...newVoluntaryList];

  const sh3Enable =
    import.meta.env.VITE_BROKER === "ACE" &&
    type === "cv" &&
    temp_data?.journeyCategory === "PCV" &&
    temp_data?.journeySubCategory === "TAXI" &&
    !temp_data?.newCar &&
    theme_conf?.broker_config?.threeMonthShortTermEnable === "yes";

  const [annualCompPolicy, setAnnualCompPolicy] = useState(!sh3Enable);
  const [shortCompPolicy3, setShortCompPolicy3] = useState(sh3Enable);
  const [shortCompPolicy6, setShortCompPolicy6] = useState(false);

  const cpaConditionCheck = !temp_data?.odOnly && !temp_data?.newCar;

  const themeConfCpa = theme_conf?.broker_config?.cpa;

  const [cpa, setCpa] = useState(
    !_.isEmpty(themeConfCpa)
      ? themeConfCpa === "Yes" && cpaConditionCheck
      : ["ABIBL", "RB", "OLA", "SPA", "BAJAJ", "POLICYERA"].includes(
          import.meta.env.VITE_BROKER
        ) && cpaConditionCheck
  );

  const [multiCpa, setMultiCpa] = useState(false);
  //states for cpa fetch
  const [onCpaChange, setOnCpaChange] = useState(false);
  const [cpaFetch, setCpaFetch] = useState(0);
  //Popup states
  const [cpaPopup, setCpaPopup] = useState(false);
  const [zDPopup, setZDPopup] = useState(false);

  //----------------------addon states-------------------------
  const [rsa, setRsa] = useState(false);
  const [zeroDep, setZeroDep] = useState(false);
  const [imt23, setImt23] = useState(false);
  const [keyReplace, setKeyReplace] = useState(false);
  const [engineProtector, setEngineProtector] = useState(false);
  const [ncbProtection, setNcbProtectiont] = useState(false);
  const [consumables, setConsumables] = useState(false);
  const [tyreSecure, setTyreSecure] = useState(false);
  const [returnToInvoice, setReturnToInvoice] = useState(false);
  const [lopb, setLopb] = useState(false);
  const [emergencyMedicalExpenses, setEmergencyMedicalExpenses] =
    useState(false);
  const [rsa2, setRsa2] = useState(false);
  const [windshield, setWindShield] = useState(false);
  const [emiprotection, setEmiprotection] = useState(false);
  const [additionalTowing, setAdditionalTowing] = useState(false);
  const [batteryprotect, setBatteryprotect] = useState(false);
  const [wrange, setWrange] = useState(temp_data?.selectedGvw);
  const [drange, setDrange] = useState(0);
  const accesories = watch("accesories");
  const selectedAccesories = accesories?.filter(Boolean) || [];
  const additional = watch("additional");

  const selectedAdditions = additional?.filter(Boolean);
  const discount = watch("discount");
  const selectedDiscount = discount?.filter(Boolean);
  //gcv additions
  const LLpaidItmes = watch("LLpaidItmes");
  const selectedLLpaidItmes = LLpaidItmes?.filter(Boolean) || [];
  //geo-extension countries
  const countries = watch("country") || [];
  //input textFields
  const ElectricAmount = watch("amountElectric") || 0;
  const NonElectricAmount = watch("amountNonElectric") || 0;
  const ExternalAmount = watch("amountLpg") || 0;
  const TrailerAmount = watch("amountTrailer") || 0;
  const LLNumberDriver = watch("LLNumberDriver") || 0;
  const LLNumberConductor = watch("LLNumberConductor") || 0;
  const LLNumberCleaner = watch("LLNumberCleaner") || 0;
  const nfppCurrentValue = watch("nfppValue") || 0;
  const MemberId = watch("memberId") || 0;
  const [volDiscountValue, setVolDiscountValue] = useState(volDiscount[0]);
  const [unNamedCoverValue, setUnNamedCoverValue] = useState(unNamedCover[0]);
  const [paPaidDriverGCV, setPaPaidDriverGCV] = useState(unNamedCover[0]);
  const [additionalPaidDriver, setAdditionalPaidDriver] = useState(
    unNamedCover[0]
  );

  const handleRelevantPolicy = (e) => {
    setRelevant((prev) => !prev);
  };

  const handleRenewalFilter = (e) => {
    setRenewalFilter((prev) => !prev);
  };
  const show_Imt23 = type === "cv" && tab === "tab1";

  //--------------------setting Short term flag ----------------------------
  // prettier-ignore
  // short term 3 flag for ace pcv text
  useGetShortTerm3FlagAce(temp_data, setShortCompPolicy3, setAnnualCompPolicy, setShortCompPolicy6, sh3Enable)

  // short term 3 flg
  useGetShortTerm3Flag(shortCompPolicy3, setShortTerm3);

  // short term 6 flag
  useGetShortTerm6Flag(shortCompPolicy6, setShortTerm6);

  // set cpa basis on reason and theme config
  useSetCpa(temp_data, setCpa, cpa, theme_conf);

  // setting tabs in redux on click of tab
  useSetTabState(tab, dispatch);

  const isAdditionalEmpty =
    (LLpaidItmes?.includes("DriverLL") &&
      (!LLNumberDriver || LLNumberDriver * 1 === 0)) ||
    (LLpaidItmes?.includes("ConductorLL") &&
      (!LLNumberConductor || LLNumberConductor * 1 === 0)) ||
    (LLpaidItmes?.includes("CleanerLL") &&
      (!LLNumberCleaner || LLNumberCleaner * 1 === 0)) ||
    (selectedAdditions?.includes("Auto Mobile") && !MemberId);

  //resetting cpa in case of saod (if it's preselected)
  useResetCpaOnSaod(cpa, temp_data, setCpa, setMultiCpa);

  //----------updateApiAddonsAndOthers / updateQuote REquest data api functionality // updating anything from quote page
  // this below useEffect is used for updating anything from quote page written in addon as this api was made to update addons

  const upd = useUpdateStatusTimeout(saveQuote);

  // update quotes data
  // prettier-ignore
  const updateQuotesProps = { saveQuote, upd, dispatch, userData, enquiry_id, temp_data,
    tempData, addOnsAndOthers, policyTypeCode, setQuoteComprehesiveGrouped, setQuoteComprehesiveGrouped1,
    setUngroupedQuoteShortTerm3, setUngroupedQuoteShortTerm6, setGroupedQuoteShortTerm3,
    setGroupedQuoteShortTerm6, setQuoteShortTerm3, setQuoteShortTerm6, setQuoteTpGrouped1, cpaFetch }

  useUpdateQuotesData(updateQuotesProps);

  //---------------- update buttons conditions accesories-----------------------
  // prettier-ignore
  const accessoriesProps = { 
      selectedAccesories, addOnsAndOthers, setShowUpdateButtonAccesories, ElectricAmount,
      NonElectricAmount, ExternalAmount, TrailerAmount,
    }
  useAccessoriesUpdateButtonVisibility(accessoriesProps);

  // ------------------------update buttons conditions additions-----------------------
  // prettier-ignore
  const accessoriesButtonProps = {
    selectedAdditions, addOnsAndOthers, temp_data, setShowUpdateButtonAdditions, unNamedCoverValue,
    additionalPaidDriver, LLNumberDriver, LLNumberConductor, LLNumberCleaner, paPaidDriverGCV,
    countries, selectedLLpaidItmes, nfppCurrentValue
  }
  useAdditionalUpdateButtonVisibility(accessoriesButtonProps);

  // -----------------update buttons conditions discount-----------------------
  // prettier-ignore
  const discountButtonProps = { selectedDiscount, addOnsAndOthers, setShowUpdateButtonDiscount, volDiscountValue }

  useDiscountUpdateButtonVisibility(discountButtonProps);

  //--------------------- handleAddonChange-------------------------
  // prettier-ignore
  const addonDataProps = {
    upd, rsa2, rsa, zeroDep, imt23, keyReplace, engineProtector, ncbProtection, consumables, tyreSecure,
    returnToInvoice, lopb, emergencyMedicalExpenses, windshield, emiprotection, additionalTowing, batteryprotect,
    userData, enquiry_id, dispatch, addOnsAndOthers, gstToggle
  }

  useSaveAddonData(addonDataProps);

  //---------------------handling cpa changes-------------------------
  // prettier-ignore
  const cpaChangesProps = { upd, temp_data, cpa, multiCpa, userData, enquiry_id, dispatch, type, onCpaChange, setOnCpaChange, setCpaFetch }
  useHandleCpaChanges(cpaChangesProps);

  // ----------------prefill addoncard-------------------------

  const [amountElectricPrefill, setAmountElectricPrefill] = useState("");
  const [amountNonElectricPrefill, setAmountNonElectricPrefill] = useState("");
  const [amountCngPrefill, setAmountEngPrefill] = useState("");
  const [amountTrailerPrefill, setAmountTrailerPrefill] = useState(0);
  const [LLCountPrefillDriver, setLLCountPrefillDriver] = useState(0);
  const [LLCountPrefillConductor, setLLCountPrefillConductor] = useState(0);
  const [LLCountPrefillCleaner, setLLCountPrefillCleaner] = useState(0);
  const [nfppValuePrefill, setNfppValuePrefill] = useState(0);

  //prefill api functinalities

  // prettier-ignore
  const prefillProps = {
    temp_data, setAmountElectricPrefill, setAmountNonElectricPrefill, setAmountTrailerPrefill,
    setAmountEngPrefill, setValue, setLLCountPrefillCleaner, setLLCountPrefillConductor, 
    setLLCountPrefillDriver, setUnNamedCoverValue, setAdditionalPaidDriver, setPaPaidDriverGCV,
    setVolDiscountValue, setRsa, setRsa2, setZeroDep, setImt23, setCpa, setConsumables,
    setDrange, setEmergencyMedicalExpenses, setEngineProtector, setKeyReplace, setLopb, setMultiCpa,
    setNcbProtectiont, setWindShield, setReturnToInvoice, setTyreSecure, setEmiprotection, setBatteryprotect, dispatch, 
    cpa, multiCpa, additionalTowing, setAdditionalTowing, setNfppValuePrefill, gstToggle, setGstToggle,
  }

  usePrefillAddonsAndOthers(prefillProps);

  // GCV journey flag setting
  const gcvJourney = useGetGcvFlag(temp_data);

  // car/bike flag setting
  const { motor, bike } = useGetVehicleTypeFlag(type);

  // clear all button  (clearing all selections on addon card)

  const [clearAll, setClearAll] = useState(0);
  const [clearButtonCondition, setClearButtonCondition] = useState(false);

  // prettier-ignore
  const clearAllProps = {
      clearAll, setCpa, setMultiCpa, setRsa, setRsa2, setZeroDep, setImt23, setKeyReplace,
      setEngineProtector, setNcbProtectiont, setConsumables, setTyreSecure, setReturnToInvoice, setLopb,
      setEmergencyMedicalExpenses, setWindShield, setEmiprotection, setBatteryprotect, setRelevant, setAdditionalTowing, setRenewalFilter,
      selectedDiscount, selectedAdditions, selectedAccesories, setValue, temp_data, dispatch, enquiry_id
    };
  // clear all condition effect
  useClearAll(clearAllProps);

  // clear all button visibility
  // prettier-ignore
  const clearAllButtonVisibilityProps = {
      cpa, multiCpa, setClearButtonCondition, addOnsAndOthers, selectedDiscount,
      selectedAdditions, selectedAccesories, isRelevant, renewalFilter, temp_data,
    }

  useClearAllButtonVisibility(clearAllButtonVisibilityProps);

  const isLongTermPolicyExist =
    type === "bike" &&
    import.meta.env.VITE_BROKER === "RB" &&
    import.meta.env.VITE_PROD !== "YES" &&
    !temp_data?.newCar &&
    !temp_data?.odOnly;

  const isPlanTypeAvailable =
    (import.meta.env?.VITE_BROKER === "OLA" ||
      import.meta.env?.VITE_BROKER === "ACE" ||
      import.meta.env?.VITE_BROKER === "HEROCARE" ||
      import.meta.env?.VITE_BROKER === "FYNTUNE" ||
      theme_conf?.broker_config?.sixMonthShortTermEnable === "yes" ||
      theme_conf?.broker_config?.threeMonthShortTermEnable === "yes") &&
    type === "cv" &&
    ((temp_data?.journeyCategory === "PCV" &&
      temp_data?.journeySubCategory === "TAXI") ||
      //Brokers in which short term is enabled in GCV
      (import.meta.env.VITE_BROKER === "ACE" &&
        temp_data?.journeyCategory === "GCV"));

  const isShortTermApplicable =
    tab === "tab1" && isPlanTypeAvailable && !temp_data?.newCar;

  //flag value to show or hide Gvw Weight Range
  const hideGvwRange = vahaanConfig?.data?.gvwRange
  console.log( hideGvwRange,"showGvwRange");
  return (
    <CardOtherItem style={{ overflow: "visible" }}>
      <ClearAll
        clearButtonCondition={clearButtonCondition}
        setClearAll={setClearAll}
        clearAll={clearAll}
      />
      <Col
        lg={12}
        md={12}
        style={{
          textAlign: "left",
          padding: lessthan767 ? "0px 12px 16px 12px" : "16px 12px",
        }}
      >
        <Collapse
          eventKey={eventKey}
          setEventKey={setEventKey}
          setAccordionId={setAccordionId}
          accordionId={accordionId}
          setOpenAll={setOpenAll}
        />
        <AddOnTitle className="d-flex pl-1">
          <span>
            <CustomTooltip
              rider="true"
              id="RiderInbuilt__Tooltip"
              place={"right"}
              customClassName="mt-3 riderPageTooltip "
            >
              <div
                data-tip="<h3 >Addons and Covers</h3> <div>Additional covers which you may add in your policy for better financial protection of your car or the individuals traveling in your car.</div>"
                data-html={true}
                data-for="RiderInbuilt__Tooltip"
              >
                Addons & Covers
              </div>
            </CustomTooltip>
          </span>
        </AddOnTitle>
        <Col md={12} style={{ padding: "0" }}>
          <AccordionTab>
            {/* Show Relevant Policy Toggle */}
            <Filter
              lessthan767={lessthan767}
              Theme1={Theme1}
              isRelevant={isRelevant}
              handleRelevantPolicy={handleRelevantPolicy}
              temp_data={temp_data}
              renewalFilter={renewalFilter}
              handleRenewalFilter={handleRenewalFilter}
            />
            {/* Long Term Policies */}
            {isLongTermPolicyExist && (
              <AccordionWrapper
                eventKey={eventKey}
                setEventKey={setEventKey}
                id={`planType${accordionId}`}
                openAll={openAll}
                setOpenAll={setOpenAll}
                lessthan767={lessthan767}
                heading={"Long Term Policy"}
                content={
                  <LongTermPolices
                    tab={tab}
                    longTerm2={longTerm2}
                    setLongterm2={setLongterm2}
                    longTerm3={longTerm3}
                    setLongterm3={setLongterm3}
                  />
                }
              />
            )}
            {isShortTermApplicable && (
              <AccordionWrapper
                eventKey={eventKey}
                setEventKey={setEventKey}
                id={`planType${accordionId}`}
                openAll={openAll}
                setOpenAll={setOpenAll}
                lessthan767={lessthan767}
                heading={"Plan Type"}
                content={
                  <PlanTypes
                    annualCompPolicy={annualCompPolicy}
                    setShortCompPolicy3={setShortCompPolicy3}
                    setAnnualCompPolicy={setAnnualCompPolicy}
                    setShortCompPolicy6={setShortCompPolicy6}
                    theme_conf={theme_conf}
                    shortCompPolicy3={shortCompPolicy3}
                    shortCompPolicy6={shortCompPolicy6}
                    sortBy={sortBy}
                    setSortBy={setSortBy}
                  />
                }
              />
            )}
            {temp_data?.ownerTypeId === 1 && !temp_data?.odOnly && (
              <AccordionWrapper
                eventKey={eventKey}
                setEventKey={setEventKey}
                id={`cpa${accordionId}`}
                openAll={openAll}
                setOpenAll={setOpenAll}
                lessthan767={lessthan767}
                heading={"CPA"}
                content={
                  <Cpa
                    cpa={cpa}
                    setCpa={setCpa}
                    setMultiCpa={setMultiCpa}
                    lessthan767={lessthan767}
                    type={type}
                    temp_data={temp_data}
                    multiCpa={multiCpa}
                    setOnCpaChange={setOnCpaChange}
                  />
                }
              />
            )}
            <AccordionWrapper
              eventKey={eventKey}
              id={`addons${accordionId}`}
              setEventKey={setEventKey}
              openAll={openAll}
              setOpenAll={setOpenAll}
              lessthan767={lessthan767}
              heading={"Addons"}
              content={
                <Addons
                  lessthan767={lessthan767}
                  tab={tab}
                  zeroDep={zeroDep}
                  setZeroDep={setZeroDep}
                  temp_data={temp_data}
                  rsa={rsa}
                  setRsa={setRsa}
                  setRsa2={setRsa2}
                  rsa2={rsa2}
                  gcvJourney={gcvJourney}
                  consumables={consumables}
                  setConsumables={setConsumables}
                  motor={motor}
                  bike={bike}
                  keyReplace={keyReplace}
                  setKeyReplace={setKeyReplace}
                  engineProtector={engineProtector}
                  setEngineProtector={setEngineProtector}
                  ncbProtection={ncbProtection}
                  setNcbProtectiont={setNcbProtectiont}
                  tyreSecure={tyreSecure}
                  setTyreSecure={setTyreSecure}
                  returnToInvoice={returnToInvoice}
                  setReturnToInvoice={setReturnToInvoice}
                  lopb={lopb}
                  setLopb={setLopb}
                  emergencyMedicalExpenses={emergencyMedicalExpenses}
                  setEmergencyMedicalExpenses={setEmergencyMedicalExpenses}
                  windshield={windshield}
                  setWindShield={setWindShield}
                  emiprotection={emiprotection}
                  setEmiprotection={setEmiprotection}
                  additionalTowing={additionalTowing}
                  setAdditionalTowing={setAdditionalTowing}
                  batteryprotect={batteryprotect}
                  setBatteryprotect={setBatteryprotect}
                />
              }
            />
            {/* accessories component  */}
            <AccordionWrapper
              id={`accessories${accordionId}`}
              eventKey={eventKey}
              setEventKey={setEventKey}
              openAll={openAll}
              setOpenAll={setOpenAll}
              lessthan767={lessthan767}
              heading={"Accessories"}
              content={
                <Accessories
                  tab={tab}
                  register={register}
                  selectedAccesories={selectedAccesories}
                  amountElectricPrefill={amountElectricPrefill}
                  numOnlyNoZero={numOnlyNoZero}
                  amountNonElectricPrefill={amountNonElectricPrefill}
                  errors={errors}
                  bike={bike}
                  temp_data={temp_data}
                  amountCngPrefill={amountCngPrefill}
                  gcvJourney={gcvJourney}
                  amountTrailerPrefill={amountTrailerPrefill}
                  showUpdateButtonAccesories={showUpdateButtonAccesories}
                  handleSubmit={handleSubmit}
                  accesories={accesories}
                  ElectricAmount={ElectricAmount}
                  NonElectricAmount={NonElectricAmount}
                  ExternalAmount={ExternalAmount}
                  TrailerAmount={TrailerAmount}
                  userData={userData}
                  enquiry_id={enquiry_id}
                />
              }
            />
            {/* additional cover  */}
            <AccordionWrapper
              eventKey={eventKey}
              setEventKey={setEventKey}
              id={`addition${accordionId}`}
              openAll={openAll}
              setOpenAll={setOpenAll}
              lessthan767={lessthan767}
              heading={"Additional Covers"}
              content={
                <AdditionalCovers
                  lessthan767={lessthan767}
                  show_Imt23={show_Imt23}
                  imt23={imt23}
                  setImt23={setImt23}
                  temp_data={temp_data}
                  gcvJourney={gcvJourney}
                  shortCompPolicy3={shortCompPolicy3}
                  shortCompPolicy6={shortCompPolicy6}
                  bike={bike}
                  register={register}
                  selectedAdditions={selectedAdditions}
                  additionalPaidDriver={additionalPaidDriver}
                  setAdditionalPaidDriver={setAdditionalPaidDriver}
                  unNamedCover={unNamedCover}
                  unNamedCoverValue={unNamedCoverValue}
                  setUnNamedCoverValue={setUnNamedCoverValue}
                  selectedLLpaidItmes={selectedLLpaidItmes}
                  LLCountPrefillDriver={LLCountPrefillDriver}
                  errors={errors}
                  numOnly={numOnly}
                  LLCountPrefillConductor={LLCountPrefillConductor}
                  LLCountPrefillCleaner={LLCountPrefillCleaner}
                  paPaidDriverGCV={paPaidDriverGCV}
                  setPaPaidDriverGCV={setPaPaidDriverGCV}
                  showUpdateButtonAddtions={showUpdateButtonAddtions}
                  handleSubmit={handleSubmit}
                  LLNumberDriver={LLNumberDriver}
                  LLNumberCleaner={LLNumberCleaner}
                  LLNumberConductor={LLNumberConductor}
                  countries={countries}
                  userData={userData}
                  enquiry_id={enquiry_id}
                  isAdditionalEmpty={isAdditionalEmpty}
                  nfppCurrentValue={nfppCurrentValue}
                  nfppValuePrefill={nfppValuePrefill}
                />
              }
            />
            {(import.meta.env.VITE_BROKER !== "ACE" || type !== "cv") && (
              <div className={"showAddon"}>
                <AccordionWrapper
                  eventKey={eventKey}
                  setEventKey={setEventKey}
                  id={`discount${accordionId}`}
                  openAll={openAll}
                  setOpenAll={setOpenAll}
                  lessthan767={lessthan767}
                  heading={" Discounts/Deductibles"}
                  content={
                    <Discounts
                      gcvJourney={gcvJourney}
                      tab={tab}
                      register={register}
                      type={type}
                      selectedDiscount={selectedDiscount}
                      volDiscount={volDiscount}
                      volDiscountValue={volDiscountValue}
                      setVolDiscountValue={setVolDiscountValue}
                      bike={bike}
                      temp_data={temp_data}
                      showUpdateButtonDiscount={showUpdateButtonDiscount}
                      handleSubmit={handleSubmit}
                      userData={userData}
                      enquiry_id={enquiry_id}
                    />
                  }
                />
              </div>
            )}
            {/* range start  */}
            {temp_data?.journeyCategory === "GCV" && hideGvwRange &&
            temp_data?.selectedGvw * 1 ? (
              <AccordionWrapper
                eventKey={eventKey}
                setEventKey={setEventKey}
                id={`range${accordionId}`}
                openAll={openAll}
                setOpenAll={setOpenAll}
                lessthan767={lessthan767}
                heading={
                  <CustomTooltip
                    rider="true"
                    id="gcv_Tooltip"
                    place={"right"}
                    customClassName="mt-3 riderPageTooltip "
                  >
                    <div
                      data-tip="<h3 >Gross Vehicle Weight</h3> <div>This applicapble on Reliance quote</div>"
                      data-html={true}
                      data-for="gcv_Tooltip"
                    >
                      Gross Vehicle Weight
                    </div>
                  </CustomTooltip>
                }
                content={
                  <WeightRange
                    temp_data={temp_data}
                    Theme1={Theme1}
                    register={register}
                    wrange={wrange}
                    setWrange={setWrange}
                  />
                }
              />
            ) : (
              <noscript />
            )}
            {/* Discount range start  */}
            {tab === "tab1" && !_.isEmpty(temp_data?.discounts) ? (
              <AccordionWrapper
                eventKey={eventKey}
                setEventKey={setEventKey}
                id={`discount${accordionId}`}
                openAll={openAll}
                setOpenAll={setOpenAll}
                lessthan767={lessthan767}
                heading={
                  <CustomTooltip
                    rider="true"
                    id="discount_range_Tooltip"
                    place={"right"}
                    customClassName="mt-3 riderPageTooltip "
                  >
                    <div
                      data-tip="<h3 >Discount</h3> <div>This applicapble on Reliance quote</div>"
                      data-html={true}
                      data-for="gcv_Tooltip"
                    >
                      Discount %
                    </div>
                  </CustomTooltip>
                }
                content={
                  <DiscountRange
                    temp_data={temp_data}
                    Theme1={Theme1}
                    register={register}
                    drange={drange}
                    setDrange={setDrange}
                    enquiry_id={enquiry_id}
                  />
                }
              />
            ) : (
              <noscript />
            )}
          </AccordionTab>
        </Col>
      </Col>
      {cpaPopup && temp_data?.ownerTypeId === 1 && (
        <CpaPopup
          show={cpaPopup && temp_data?.ownerTypeId === 1}
          setCpa={setCpa}
          onClose={setCpaPopup}
          cpa={cpa}
        />
      )}

      {zDPopup && (
        <ZeroDepPopup
          show={zDPopup}
          setZeroDep={setZeroDep}
          onClose={setZDPopup}
          zeroDep={zeroDep}
        />
      )}
    </CardOtherItem>
  );
};
