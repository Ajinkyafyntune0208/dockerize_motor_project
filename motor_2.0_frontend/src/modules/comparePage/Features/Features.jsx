import React, { useEffect, useState, useMemo } from "react";
import "./features.scss";
import { useDispatch, useSelector } from "react-redux";
import { useLocation } from "react-router";
import { Badge } from "react-bootstrap";
import {
  SetaddonsAndOthers,
  SaveAddonsData,
} from "../../quotesPage/quote.slice";
import { useMediaPredicate } from "react-media-hook";
import ThemeObj from "modules/theme-config/theme-config";
import _, { isEmpty } from "lodash";
import SecureLS from "secure-ls";
import { TypeReturn } from "modules/type";
import PropTypes from "prop-types";
import { BlockedSections } from "modules/quotesPage/addOnCard/cardConfig";
import { PlanOptionHead, TopDiv, VehicleDetails } from "./FeatureStyle";
import MenuCheckbox from "./MenuCheckbox";
import PdfButton from "./PdfButton";
import Badges from "./Badges";
import { getAddonKey } from "modules/quotesPage/quoteUtil";

function Features({
  type,
  quote,
  scrollPosition,
  zdlp,
  setZdlp,
  claimList,
  setZdlp_gdd,
  claimList_gdd,
}) {
  const ls = new SecureLS();
  const ThemeLS = ls.get("themeData");
  const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;
  const lessThan768 = useMediaPredicate("(max-width: 768px)");
  const dispatch = useDispatch();
  const location = useLocation();
  const { temp_data } = useSelector((state) => state.home);
  const { addOnsAndOthers, shortTerm } = useSelector((state) => state.quotes);
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  let AddonDataPrefill = temp_data?.addons;

  let addonPrefill = AddonDataPrefill?.addons?.map((item) => item.name);

  //addons
  const [cpa, setCpa] = useState(
    addOnsAndOthers?.selectedCpa?.includes("Compulsory Personal Accident") &&
      _.isEmpty(addOnsAndOthers?.isTenure)
      ? true
      : null
  );
  const [rsa, setRsa] = useState(
    addOnsAndOthers?.selectedAddons?.includes("roadSideAssistance")
      ? true
      : null
  );
  const [zeroDep, setZeroDep] = useState(
    addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation") ? true : false
  );
  //gcv
  const [imt23, setImt23] = useState(
    addOnsAndOthers?.selectedAddons?.includes("imt23") ? true : false
  );
  //motor
  const [keyReplace, setKeyReplace] = useState(
    addOnsAndOthers?.selectedAddons?.includes("keyReplace") ? true : false
  );
  const [engineProtector, setEngineProtector] = useState(
    addOnsAndOthers?.selectedAddons?.includes("engineProtector") ? true : false
  );
  const [ncbProtection, setNcbProtectiont] = useState(
    addOnsAndOthers?.selectedAddons?.includes("ncbProtection") ? true : false
  );
  const [consumables, setConsumables] = useState(
    addOnsAndOthers?.selectedAddons?.includes("consumables") ? true : false
  );
  const [tyreSecure, setTyreSecure] = useState(
    addOnsAndOthers?.selectedAddons?.includes("tyreSecure") ? true : false
  );
  const [returnToInvoice, setReturnToInvoice] = useState(
    addOnsAndOthers?.selectedAddons?.includes("returnToInvoice") ? true : false
  );
  const [lopb, setLopb] = useState(
    addOnsAndOthers?.selectedAddons?.includes("lopb") ? true : false
  );
  const [emergencyMedicalExpenses, setEmergencyMedicalExpenses] = useState(
    addOnsAndOthers?.selectedAddons?.includes("emergencyMedicalExpenses")
      ? true
      : false
  );
  const [windshield, setWindShield] = useState(
    addOnsAndOthers?.selectedAddons?.includes("windShield") ? true : false
  );
  const [emiprotection, setEmiprotection] = useState(
    addOnsAndOthers?.selectedAddons?.includes("emiProtection") ? true : false
  );
  const [additionalTowing, setAdditionalTowing] = useState(
    addOnsAndOthers?.selectedAddons?.includes("additionalTowing") ? true : false
  );
  const [batteryprotect, setBatteryProtect] = useState(
    addOnsAndOthers?.selectedAddons?.includes("batteryProtect") ? true : false
  );
  const [cpa1, setCpa1] = useState(
    !_.isEmpty(addOnsAndOthers?.isTenure) ? true : false
  );

  useEffect(() => {
    const checkAddons = temp_data?.addons?.addons?.map((i) =>
      getAddonKey(i?.name)
    );

    const checkCpa = temp_data?.addons?.compulsoryPersonalAccident?.[0]?.name;

    const checkMultiCpa =
      temp_data?.addons?.compulsoryPersonalAccident?.[0]?.tenure;

    if (!_.isEmpty(temp_data?.addons)) {
      if (
        checkCpa?.includes("Compulsory Personal Accident") &&
        !checkMultiCpa
      ) {
        setCpa(true);
        setCpa1(false);
      } else {
        setCpa(false);
        setCpa1(false);
      }

      if (checkCpa?.includes("Compulsory Personal Accident") && checkMultiCpa) {
        setCpa1(true);
        setCpa(false);
      } else {
        setCpa1(false);
      }

      if (checkAddons?.includes("roadSideAssistance")) {
        setRsa(true);
      } else {
        setRsa(false);
      }
      if (checkAddons?.includes("zeroDepreciation")) {
        setZeroDep(true);
      } else {
        setZeroDep(false);
      }
      if (checkAddons?.includes("imt23")) {
        setImt23(true);
      } else {
        setImt23(false);
      }
      //motor addons
      if (checkAddons?.includes("keyReplace")) {
        setKeyReplace(true);
      } else {
        setKeyReplace(false);
      }
      if (checkAddons?.includes("engineProtector")) {
        setEngineProtector(true);
      } else {
        setEngineProtector(false);
      }
      if (checkAddons?.includes("ncbProtection")) {
        setNcbProtectiont(true);
      } else {
        setNcbProtectiont(false);
      }
      if (checkAddons?.includes("consumables")) {
        setConsumables(true);
      } else {
        setConsumables(false);
      }
      if (checkAddons?.includes("tyreSecure")) {
        setTyreSecure(true);
      } else {
        setTyreSecure(false);
      }
      if (checkAddons?.includes("returnToInvoice")) {
        setReturnToInvoice(true);
      } else {
        setReturnToInvoice(false);
      }
      if (checkAddons?.includes("lopb")) {
        setLopb(true);
      } else {
        setLopb(false);
      }
      if (checkAddons?.includes("emergencyMedicalExpenses")) {
        setEmergencyMedicalExpenses(true);
      } else {
        setEmergencyMedicalExpenses(false);
      }
      if (checkAddons?.includes("windShield")) {
        setWindShield(true);
      } else {
        setWindShield(false);
      }
      if (checkAddons?.includes("emiProtection")) {
        setEmiprotection(true);
      } else {
        setEmiprotection(false);
      }
      if (checkAddons?.includes("additionalTowing")) {
        setAdditionalTowing(true);
      } else {
        setAdditionalTowing(false);
      }
      if (checkAddons?.includes("batteryProtect")) {
        setBatteryProtect(true);
      } else {
        setBatteryProtect(false);
      }
    }
  }, [temp_data?.ownerTypeId]);

  useEffect(() => {
    var addons = [];
    var addons2 = [];

    if (!_.isEmpty(temp_data?.addons)) {
      if (rsa) {
        addons.push("roadSideAssistance");
        addons2.push({ name: "Road Side Assistance" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (zeroDep) {
        let claimCovered =
          !isEmpty(addOnsAndOthers?.dbStructure?.addonData?.addons) &&
          addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
            (x) => x?.name === "Zero Depreciation"
          )?.[0]?.claimCovered;
        addons.push("zeroDepreciation");
        addons2.push({
          name: "Zero Depreciation",
          ...(claimCovered && { claimCovered: claimCovered }),
        });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (imt23) {
        addons.push("imt23");
        addons2.push({ name: "IMT - 23" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      //motor addons
      if (keyReplace) {
        addons.push("keyReplace");
        addons2.push({ name: "Key Replacement" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (engineProtector) {
        addons.push("engineProtector");
        addons2.push({ name: "Engine Protector" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (ncbProtection) {
        addons.push("ncbProtection");
        addons2.push({ name: "NCB Protection" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (consumables) {
        addons.push("consumables");
        addons2.push({ name: "Consumable" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (tyreSecure) {
        addons.push("tyreSecure");
        addons2.push({ name: "Tyre Secure" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (returnToInvoice) {
        addons.push("returnToInvoice");
        addons2.push({ name: "Return To Invoice" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (lopb) {
        addons.push("lopb");
        addons2.push({ name: "Loss of Personal Belongings" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (emergencyMedicalExpenses) {
        addons.push("emergencyMedicalExpenses");
        addons2.push({ name: "Emergency Medical Expenses" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (windshield) {
        addons.push("windShield");
        addons2.push({ name: "Wind Shield" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (emiprotection) {
        addons.push("emiProtection");
        addons2.push({ name: "EMI Protection" });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (additionalTowing) {
        let sumInsured =
          !isEmpty(addOnsAndOthers?.dbStructure?.addonData?.addons) &&
          addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
            (x) => x?.name === "Additional Towing"
          )?.[0]?.sumInsured;
        addons.push("additionalTowing");
        addons2.push({
          name: "Additional Towing",
          ...(sumInsured && { sumInsured: sumInsured }),
        });
      } else {
        addons.push(false);
        addons2.push(false);
      }
      if (batteryprotect) {
        addons.push("batteryProtect");
        addons2.push({ name: "Battery Protect" });
      } else {
        addons.push(false);
        addons2.push(false);
      }

      var data = {
        selectedAddons: addons.filter(Boolean),
      };
      var data1 = {
        enquiryId: temp_data?.enquiry_id || enquiry_id,
        //	type: "addons",
        addonData: { addons: addons2.filter(Boolean) },
      };

      dispatch(SetaddonsAndOthers(data));
      dispatch(SaveAddonsData(data1));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    rsa,
    zeroDep,
    imt23,
    keyReplace,
    engineProtector,
    ncbProtection,
    consumables,
    tyreSecure,
    returnToInvoice,
    lopb,
    emergencyMedicalExpenses,
    windshield,
    emiprotection,
    additionalTowing,
    batteryprotect,
    temp_data?.ownerTypeId,
  ]);

  useEffect(() => {
    if (temp_data?.ownerTypeId === 1 && !_.isEmpty(temp_data?.addons)) {
      console.log("check", temp_data?.addons);
      //check if tempdata has cpa or multi year cpa
      const cpaCheck = temp_data?.addons?.compulsoryPersonalAccident?.[0]?.name;

      if (!cpa && !cpa1 && (cpa !== null && cpa1 !== null)) {
        console.log("check", cpa, cpa1);  
        var selectedCpa = [];
        var tenureConst = [];
        var data1 = {
          enquiryId: temp_data?.enquiry_id || enquiry_id,
          //type: "compulsory_personal_accident",
          addonData: {
            compulsory_personal_accident: [
              {
                reason:
                  "I have another motor policy with PA owner driver cover in my name",
              },
            ],
          },
        };

        dispatch(SaveAddonsData(data1));
      } else if (cpa) {
        var selectedCpa = ["Compulsory Personal Accident"];
        var tenureConst = [];
        var data1 = {
          enquiryId: temp_data?.enquiry_id || enquiry_id,
          isTenure: tenureConst,
          //type: "compulsory_personal_accident",
          addonData: {
            compulsory_personal_accident: [
              { name: "Compulsory Personal Accident" },
            ],
          },
        };

        dispatch(SaveAddonsData(data1));
      } else if (cpa1) {
        var selectedCpa = ["Compulsory Personal Accident"];
        var tenureConst = [type === "car" ? 3 : 5];
        var data1 = {
          enquiryId: temp_data?.enquiry_id || enquiry_id,
          isTenure: tenureConst,
          addonData: {
            compulsory_personal_accident: [
              {
                name: "Compulsory Personal Accident",
                tenure: type === "car" ? 3 : 5,
              },
            ],
          },
        };

        dispatch(SaveAddonsData(data1));
      }

      var data = {
        selectedCpa: selectedCpa,
        isTenure: tenureConst,
      };
      dispatch(SetaddonsAndOthers(data));
    } else if (temp_data?.ownerTypeId === 2 && !_.isEmpty(temp_data?.addons)) {
      var data2 = {
        selectedCpa: [],
      };
      dispatch(SetaddonsAndOthers(data2));
      var data1 = {
        enquiryId: temp_data?.enquiry_id || enquiry_id,
        type: "compulsory_personal_accident",
        addonData: {
          compulsory_personal_accident: [
            { reason: "cpa not applicable to company" },
          ],
        },
      };
      dispatch(SaveAddonsData(data1));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cpa, cpa1]);

  return (
    <>
      <TopDiv>
        <div className="compare-page-features">
          <div className="top-info ">
            <VehicleDetails
              fixed={
                lessThan768
                  ? false
                  : scrollPosition >
                    (Theme?.QuoteBorderAndFont?.scrollHeight
                      ? Theme?.QuoteBorderAndFont?.scrollHeight
                      : 68)
                  ? true
                  : false
              }
            >
              <div className="vehicleName">
                {" "}
                {temp_data?.quoteLog?.quoteDetails?.manfactureName}-
                {temp_data?.quoteLog?.quoteDetails?.modelName}-
                {temp_data?.quoteLog?.quoteDetails?.versionName}-
                {temp_data?.quoteLog?.quoteDetails?.fuelType}-
                {TypeReturn(type) !== "bike" && quote?.mmvDetail?.cubicCapacity}
                {TypeReturn(type) !== "bike" && "CC"}-
                {TypeReturn(type).toUpperCase()}-{temp_data?.rtoNumber}
              </div>
              <div className="policyType">
                {quote?.policyType === "Comprehensive" &&
                temp_data?.newCar &&
                TypeReturn(type) !== "cv"
                  ? `Bundled(1 yr OD + ${
                      TypeReturn(type) === "car" ? 3 : 5
                    } yr TP)`
                  : quote?.policyType}{" "}
              </div>
              <div className="dates">
                Reg Date:{" "}
                <span name="reg_date">{quote?.vehicleRegisterDate}</span>
              </div>
              {!temp_data?.breakIn && (
                <div className="dates">
                  Policy Start Date:{" "}
                  <span name="policy_start_date">{quote?.policyStartDate}</span>
                </div>
              )}
              {temp_data?.corporateVehiclesQuoteRequest?.selectedGvw && (
                <div className="dates">
                  Gross Vehicle Weight (lbs):{" "}
                  {temp_data?.corporateVehiclesQuoteRequest?.selectedGvw}
                </div>
              )}
              {/* pdf button  */}
              <PdfButton />
            </VehicleDetails>
          </div>

          <ul className="cd-features-list">
            <li>
              <PlanOptionHead className="planOptionHead">
                <p className="planOptionText">Insurers USP</p>
              </PlanOptionHead>

              <div className="planOptionName icContent"></div>

              <div className="planOptionName icContent"> </div>
              <div className="planOptionName icContent"></div>
            </li>
            <li>
              <PlanOptionHead className="planOptionHead">
                <p className="planOptionText">Premium Breakup</p>
              </PlanOptionHead>

              <div
                className="planOptionName"
                style={{ borderTop: lessThan768 ? "none" : "" }}
              >
                Own Damage Premium
              </div>
              <div className="planOptionName">
                <p>Third Party Premium</p>
              </div>
              <div className="planOptionName">Addon Premium</div>
              <div className="planOptionName">
                Total Discount (NCB {quote?.ncbDiscount}% Incl.)
              </div>
              <div
                className="planOptionName"
                // style={{ fontFamily: "Inter-SemiBold" }}
              >
                GST
              </div>
              <div
                className="planOptionName"
                style={{ fontFamily: "Inter-SemiBold" }}
              >
                Gross Premium (incl GST)
              </div>
            </li>

            <li>
              <PlanOptionHead className="planOptionHead">
                <p className="planOptionText addOnDetails">
                  {TypeReturn(type) === "cv"
                    ? "Addon & Cover Details"
                    : "Addon Details"}
                </p>
              </PlanOptionHead>

              <div
                className="planOptionName longNameText"
                style={{
                  borderTop: lessThan768 ? "none" : "",
                  display:
                    (temp_data?.odOnly ||
                      temp_data?.corporateVehiclesQuoteRequest
                        ?.vehicleOwnerType === "C") &&
                    "none",
                }}
              >
                <MenuCheckbox
                  id={"Compulsory Personal Accident"}
                  value={"Compulsory Personal Accident"}
                  defaultChecked={cpa && !cpa1}
                  checked={cpa && !cpa1}
                  onChange={(e) => {
                    setCpa(!cpa);
                    setCpa1(false);
                  }}
                  t_id={"cpa1__Tooltipvol"}
                  onInput={() => {
                    setCpa(!cpa);
                    setCpa1(false);
                  }}
                  t_text={
                    "<h3 >Compulsory Personal Accident</h3> <div>Compulsory Personal Accident cover protects you against partial, total disability, or death caused due to an accident. As per the IRDAI notice. Personal Accident (PA) Cover is mandatory if the car is owned by an individual.</div>"
                  }
                  data-for={"cpa1__Tooltipvol"}
                  htmlFor={"Compulsory Personal Accident"}
                  v_value={"Compulsory Personal Accident"}
                />
              </div>
              {TypeReturn(type) !== "cv" && temp_data?.newCar && (
                <div
                  className="planOptionName longNameText"
                  style={{
                    borderTop: lessThan768 ? "none" : "",
                    display:
                      (temp_data?.odOnly ||
                        temp_data?.corporateVehiclesQuoteRequest
                          ?.vehicleOwnerType === "C") &&
                      "none",
                  }}
                >
                  <MenuCheckbox
                    id={"Compulsory Personal Accident 1"}
                    value={"Compulsory Personal Accident 1"}
                    defaultChecked={cpa1}
                    checked={cpa1}
                    onChange={(e) => {
                      setCpa1(!cpa1);
                      setCpa(false);
                    }}
                    t_id={"cpa1__Tooltipvol"}
                    onInput={() => {
                      setCpa(!cpa);
                      setCpa1(false);
                    }}
                    t_text={
                      "<h3 >Compulsory Personal Accident</h3> <div>Compulsory Personal Accident cover protects you against partial, total disability, or death caused due to an accident. As per the IRDAI notice. Personal Accident (PA) Cover is mandatory if the car is owned by an individual.</div>"
                    }
                    data_for={"cpa1__Tooltipvol"}
                    htmlFor={"Compulsory Personal Accident 1"}
                    v_value={`Compulsory Personal Accident (${
                      TypeReturn(type) === "car" ? "3" : "5"
                    } years)`}
                  />
                </div>
              )}
              <div className="planOptionName longNameText">
                <MenuCheckbox
                  id={"Zero Depreciation"}
                  value={"Zero Depreciation"}
                  defaultChecked={zeroDep}
                  checked={zeroDep}
                  onChange={(e) => {
                    setZeroDep(e.target.checked);
                  }}
                  t_id={"zero__Tooltipvol"}
                  onInput={() => setZeroDep(!zeroDep)}
                  t_text={
                    "<h3 >Zero Depreciation</h3> <div>Also called Nil Depreciation cover or Bumper-to-Bumper cover. An add-on which gives you complete cover on any body parts of the car excluding tyres and batteries. Insurer will pay entire cost of body parts, ignoring the year-on-year depreciation in value of these parts.</div>"
                  }
                  data_for={"zero__Tooltipvol"}
                  htmlFor={"Zero Depreciation"}
                  v_value={"Zero Depreciation"}
                />
              </div>
              <div className="planOptionName longNameText">
                <MenuCheckbox
                  id={"Road Side Assistance"}
                  value={"Road Side Assistance"}
                  defaultChecked={rsa}
                  checked={rsa}
                  onChange={(e) => {
                    setRsa(e.target.checked);
                  }}
                  t_id={"rsa__Tooltipvol"}
                  onInput={() => setRsa(!rsa)}
                  t_text={
                    "<h3 >Road Side Assistance</h3> <div>Roadside Assistance Coverage means a professional technician comes to your rescue when your car breaks down in the middle of the journey leaving you stranded.</div>"
                  }
                  data_for={"rsa__Tooltipvol"}
                  htmlFor={"Road Side Assistance"}
                  v_value={"Road Side Assistance"}
                  noMargin
                />
              </div>
              {TypeReturn(type) === "cv" && (
                <>
                  <div className="planOptionName longNameText">
                    <MenuCheckbox
                      id={"IMT - 23"}
                      value={"IMT - 23"}
                      defaultChecked={imt23}
                      checked={imt23}
                      onChange={(e) => {
                        setImt23(e.target.checked);
                      }}
                      t_id={"imtTooltipvol"}
                      onInput={() => setImt23(!imt23)}
                      t_text={
                        "<h3 >IMT - 23</h3> <div>COVER FOR LAMPS TYRES / TUBES MUDGUARDS BONNET /SIDE PARTS BUMPERS HEADLIGHTS AND PAINTWORK OF DAMAGED PORTION ONLY .</div>"
                      }
                      data_for={"imtTooltipvol"}
                      htmlFor={"IMT - 23"}
                      v_value={"IMT - 23"}
                    />
                  </div>
                  {temp_data?.journeyCategory !== "GCV" && (
                    <div className="planOptionName longNameText">
                      <MenuCheckbox
                        id={"Consumable"}
                        value={"Consumable"}
                        defaultChecked={consumables}
                        checked={consumables}
                        onChange={(e) => {
                          setConsumables(e.target.checked);
                        }}
                        t_id={"consumableTooltipvol"}
                        onInput={() => setConsumables(!consumables)}
                        t_text={
                          "<h3 >Consumable</h3> <div>The consumables in car insurance are those items that are subject to the constant wear and tear. They are continuously consumed by the car during its life for e.g nut and bolt, screw, washer, grease, lubricant, clips, A/C gas, bearings, distilled water, engine oil, oil filter, fuel filter, break oil and related parts.</div>"
                        }
                        data_for={"consumableTooltipvol"}
                        htmlFor={"Consumable"}
                        v_value={"Consumable"}
                      />
                    </div>
                  )}
                </>
              )}
              {(TypeReturn(type) === "car" || TypeReturn(type) === "bike") && (
                <>
                  <div
                    className="planOptionName longNameText"
                    style={{ display: TypeReturn(type) === "bike" && "none" }}
                  >
                    <MenuCheckbox
                      id={"Key Replacement"}
                      value={"Key Replacement"}
                      defaultChecked={keyReplace}
                      checked={keyReplace}
                      onChange={(e) => {
                        setKeyReplace(e.target.checked);
                      }}
                      t_id={"keyTooltipvol"}
                      onInput={() => setKeyReplace(!keyReplace)}
                      t_text={
                        "<h3 >Key Replacement</h3> <div>An add-on which covers cost of car keys and lock replacement or locksmith charges incase of your car keys is stolen.</div>"
                      }
                      data_for={"keyTooltipvol"}
                      htmlFor={"Key Replacement"}
                      v_value={"Key Replacement"}
                    />
                  </div>
                  <div className="planOptionName longNameText">
                    <MenuCheckbox
                      id={"Engine Protector"}
                      value={"Engine Protector"}
                      defaultChecked={engineProtector}
                      checked={engineProtector}
                      onChange={(e) => {
                        setEngineProtector(e.target.checked);
                      }}
                      t_id={"engTooltipvol"}
                      onInput={() => setEngineProtector(!engineProtector)}
                      t_text={
                        "<h3 >Engine Protector</h3> <div>Engine protection cover in car insurance provides coverage towards damages or losses to the insured vehicle’s engine. The add-on compensates you for the replacement or repair of your car’s engine or parts.</div>"
                      }
                      data_for={"engTooltipvol"}
                      htmlFor={"Engine Protector"}
                      v_value={"Engine Protector"}
                    />
                  </div>
                  <div
                    className="planOptionName longNameText"
                    style={{ display: TypeReturn(type) === "bike" && "none" }}
                  >
                    <MenuCheckbox
                      id={"NCB Protection"}
                      value={"NCB Protection"}
                      defaultChecked={ncbProtection}
                      checked={ncbProtection}
                      onChange={(e) => {
                        setNcbProtectiont(e.target.checked);
                      }}
                      t_id={"ncbProtTooltipvol"}
                      onInput={() => setNcbProtectiont(!ncbProtection)}
                      t_text={
                        "<h3 >NCB Protection</h3> <div>The NCB Protector protects Your Earned No claim Bonus, in the event of an Own Damage claim made for Partial Loss including claims for Windshield glass, Total Loss, and Theft of vehicle/ accessories. The No Claim Bonus will not get impacted for the first 2 claims preferred during the course of this policy per year.</div>"
                      }
                      data_for={"ncbProtTooltipvol"}
                      htmlFor={"NCB Protection"}
                      v_value={"NCB Protection"}
                    />
                  </div>
                  <div className="planOptionName longNameText">
                    <MenuCheckbox
                      id={"Consumable"}
                      value={"Consumable"}
                      defaultChecked={consumables}
                      checked={consumables}
                      onChange={(e) => {
                        setConsumables(e.target.checked);
                      }}
                      t_id={"consumableTooltipvol"}
                      onInput={() => setConsumables(!consumables)}
                      t_text={
                        "<h3 >Consumable</h3> <div>The consumables in car insurance are those items that are subject to the constant wear and tear. They are continuously consumed by the car during its life for e.g nut and bolt, screw, washer, grease, lubricant, clips, A/C gas, bearings, distilled water, engine oil, oil filter, fuel filter, break oil and related parts.</div>"
                      }
                      data_for={"consumableTooltipvol"}
                      htmlFor={"Consumable"}
                      v_value={"Consumable"}
                    />
                  </div>
                  <div
                    className="planOptionName longNameText"
                    style={{ display: TypeReturn(type) === "bike" && "none" }}
                  >
                    <MenuCheckbox
                      id={"Tyre Secure"}
                      value={"Tyre Secure"}
                      defaultChecked={tyreSecure}
                      checked={tyreSecure}
                      onChange={(e) => {
                        setTyreSecure(e.target.checked);
                      }}
                      t_id={"tyreTooltipvol"}
                      onInput={() => setTyreSecure(!tyreSecure)}
                      t_text={
                        "<h3 >Tyre Secure</h3> <div>This is an add-on cover which covers the damages to the tyre of the car caused due to accidental external means. The cost of tyre replacement, rebalancing, removal and refitting is covered.</div>"
                      }
                      data_for={"tyreTooltipvol"}
                      htmlFor={"Tyre Secure"}
                      v_value={"Tyre Secure"}
                    />
                  </div>
                  <div className="planOptionName longNameText">
                    <MenuCheckbox
                      id={"Return To Invoice"}
                      value={"Return To Invoice"}
                      defaultChecked={returnToInvoice}
                      checked={returnToInvoice}
                      onChange={(e) => {
                        setReturnToInvoice(e.target.checked);
                      }}
                      t_id={"roiTooltipvol"}
                      onInput={() => setReturnToInvoice(!returnToInvoice)}
                      t_text={
                        "<h3 >Return To Invoice</h3> <div>Return to Invoice is an add-on option which covers the gap between the insured declared value and the invoice value of your car along with the registration and other applicable taxes.</div>"
                      }
                      data_for={"roiTooltipvol"}
                      htmlFor={"Return To Invoice"}
                      v_value={"Return To Invoice"}
                    />
                  </div>
                  <div
                    className="planOptionName longNameText"
                    style={{ display: TypeReturn(type) === "bike" && "none" }}
                  >
                    <MenuCheckbox
                      id={"Loss of Personal Belongings"}
                      value={"Loss of Personal Belongings"}
                      defaultChecked={lopb}
                      checked={lopb}
                      onChange={(e) => {
                        setLopb(e.target.checked);
                      }}
                      t_id={"lopb__Tooltipvol"}
                      onInput={() => setLopb(!lopb)}
                      t_text={
                        "<h3 >Loss of Personal Belongings</h3> <div>With this cover in place, your insurer will cover losses arising due to damage or theft of your personal Belongings from the insured car as per the terms and conditions of the policy.</div>"
                      }
                      data_for={"lopb__Tooltipvol"}
                      htmlFor={"Loss of Personal Belongings"}
                      v_value={"Loss of Personal Belongings"}
                    />
                  </div>
                </>
              )}
              <div
                className="planOptionName longNameText"
                style={
                  TypeReturn(type) === "cv"
                    ? {
                        display: "none",
                      }
                    : {}
                }
              >
                <MenuCheckbox
                  id={"Emergency Medical Expenses"}
                  value={"Emergency Medical Expenses"}
                  defaultChecked={emergencyMedicalExpenses}
                  checked={emergencyMedicalExpenses}
                  onChange={(e) => {
                    setEmergencyMedicalExpenses(e.target.checked);
                  }}
                  t_id={"emeTooltipvol"}
                  onInput={() =>
                    setEmergencyMedicalExpenses(!emergencyMedicalExpenses)
                  }
                  t_text={"<h3 >Emergency Medical Expenses</h3> <div></div>"}
                  data_for={"emeTooltipvol"}
                  htmlFor={"Emergency Medical Expenses"}
                  v_value={"Emergency Medical Expenses"}
                />
              </div>
              <div className="planOptionName longNameText">
                <MenuCheckbox
                  id={"Wind Shield"}
                  value={"Wind Shield"}
                  defaultChecked={windshield}
                  checked={windshield}
                  onChange={(e) => {
                    setWindShield(e.target.checked);
                  }}
                  t_id={"windshieldTooltipvol"}
                  onInput={() => setWindShield(!windshield)}
                  t_text={"<h3 >Wind Shield</h3> <div></div>"}
                  data_for={"windshieldTooltipvol"}
                  htmlFor={"Wind Shield"}
                  v_value={"Wind Shield"}
                />
              </div>
              <div className="planOptionName longNameText">
                <MenuCheckbox
                  id={"EMI Protection"}
                  value={"EMI Protection"}
                  defaultChecked={emiprotection}
                  checked={emiprotection}
                  onChange={(e) => {
                    setEmiprotection(e.target.checked);
                  }}
                  t_id={"emiprotectionTooltipvol"}
                  onInput={() => setEmiprotection(!emiprotection)}
                  t_text={"<h3 >EMI Protection</h3> <div></div>"}
                  data_for={"emiprotectionTooltipvol"}
                  htmlFor={"EMI Protection"}
                  v_value={"EMI Protection"}
                />
              </div>
              <div className="planOptionName longNameText">
                <MenuCheckbox
                  id={"Additional Towing"}
                  value={"Additional Towing"}
                  defaultChecked={additionalTowing}
                  checked={additionalTowing}
                  onChange={(e) => {
                    setAdditionalTowing(e.target.checked);
                  }}
                  t_id={"additionaltowingTooltipvol"}
                  onInput={() => setAdditionalTowing(!additionalTowing)}
                  t_text={"<h3 >Additional Towing</h3> <div></div>"}
                  data_for={"additionaltowingTooltipvol"}
                  htmlFor={"Additional Towing"}
                  v_value={"Additional Towing"}
                />
              </div>
              <div className="planOptionName longNameText">
                <MenuCheckbox
                  id={"Battery Protect"}
                  value={"Battery Protect"}
                  defaultChecked={batteryprotect}
                  checked={batteryprotect}
                  onChange={(e) => {
                    setBatteryProtect(e.target.checked);
                  }}
                  t_id={"batteryprotectTooltipvol"}
                  onInput={() => setBatteryProtect(!batteryprotect)}
                  t_text={"<h3 >Battery Protect</h3> <div></div>"}
                  data_for={"batteryprotectTooltipvol"}
                  htmlFor={"Battery Protect"}
                  v_value={"Battery Protect"}
                />
              </div>
            </li>
            <li>
              <PlanOptionHead className="planOptionHead">
                <p className="planOptionText">Accessories</p>
              </PlanOptionHead>
              <div
                className="planOptionName "
                style={{ borderTop: lessThan768 ? "none" : "" }}
              >
                Electrical Accessories{" "}
                {addOnsAndOthers?.selectedAccesories?.includes(
                  "Electrical Accessories"
                ) && (
                  <Badges
                    value={`₹ ${addOnsAndOthers?.vehicleElectricAccessories}`}
                  />
                )}{" "}
              </div>
              <div className="planOptionName">
                Non Electrical Accessories{" "}
                {addOnsAndOthers?.selectedAccesories?.includes(
                  "Non-Electrical Accessories"
                ) && (
                  <Badges
                    value={`₹ ${addOnsAndOthers?.vehicleNonElectricAccessories}`}
                  />
                )}{" "}
              </div>
              <div
                className="planOptionName"
                style={{ display: TypeReturn(type) === "bike" && "none" }}
              >
                Bi Fuel Kit
                {addOnsAndOthers?.selectedAccesories?.includes(
                  "External Bi-Fuel Kit CNG/LPG"
                ) && (
                  <Badges value={`₹ ${addOnsAndOthers?.externalBiFuelKit}`} />
                )}{" "}
              </div>
            </li>
            <li>
              <PlanOptionHead className="planOptionHead">
                <p className="planOptionText additionalCovers">
                  Additional Covers
                </p>
              </PlanOptionHead>
              {temp_data.journeyCategory !== "GCV" && !temp_data?.odOnly && (
                <>
                  {BlockedSections(
                    import.meta.env.VITE_BROKER,
                    temp_data?.journeyCategory
                  )?.includes("unnamed pa cover") ? (
                    <noscript />
                  ) : (
                    <div
                      className="planOptionName"
                      style={{ borderTop: lessThan768 ? "none" : "" }}
                    >
                      Unnamed Passenger PA cover
                      {addOnsAndOthers?.selectedAdditions?.includes(
                        "Unnamed Passenger PA Cover"
                      ) && (
                        <Badges value={addOnsAndOthers?.unNamedCoverValue} />
                      )}
                    </div>
                  )}
                  <div
                    className="planOptionName"
                    style={{
                      display:
                        shortTerm ||
                        temp_data?.journeyCategory === "MISC" ||
                        TypeReturn(type) === "bike"
                          ? "none"
                          : "",
                    }}
                  >
                    PA cover for additional paid driver
                    {addOnsAndOthers?.selectedAdditions?.includes(
                      "PA cover for additional paid driver"
                    ) && (
                      <Badges value={addOnsAndOthers?.additionalPaidDriver} />
                    )}
                  </div>
                  <div className="planOptionName">LL Paid Driver </div>
                </>
              )}
              {temp_data.journeyCategory === "GCV" &&
                !temp_data?.odOnly &&
                !shortTerm && (
                  <>
                    <div className="planOptionName">
                      LL paid driver/conductor/cleaner
                    </div>
                    <div className="planOptionName">
                      PA paid driver/conductor/cleaner
                    </div>
                  </>
                )}
              {import.meta.env.VITE_BROKER !== "OLA" && (
                <div className="planOptionName">Geographical Extension </div>
              )}
              {temp_data?.ownerTypeId === 2 && (
                <li>
                  <PlanOptionHead className="planOptionHead">
                    <p className="planOptionText additionalCovers">
                      Other Covers
                    </p>
                  </PlanOptionHead>
                  <div className="planOptionName">
                    Legal Liability To Employee{" "}
                  </div>
                </li>
              )}
              {temp_data.journeyCategory === "GCV" && (
                <div
                  className="planOptionName"
                  style={{ borderTop: lessThan768 ? "none" : "" }}
                >
                  NFPP Cover
                  {addOnsAndOthers?.selectedAdditions?.includes(
                    "NFPP Cover"
                  ) && <Badges value={addOnsAndOthers?.nfpp} />}
                </div>
              )}
            </li>

            {BlockedSections(
              import.meta.env.VITE_BROKER,
              temp_data?.journeyCategory
            )?.includes("unnamed pa cover") ? (
              <noscript />
            ) : (
              <li>
                <PlanOptionHead className="planOptionHead">
                  <p className="planOptionText"> Discounts/Deductibles </p>
                </PlanOptionHead>
                {temp_data.journeyCategory !== "GCV" && (
                  <div
                    className="planOptionName "
                    style={{ borderTop: lessThan768 ? "none" : "" }}
                  >
                    Vehicle is fitted with ARAI{" "}
                    {addOnsAndOthers?.selectedDiscount?.includes(
                      "Is the vehicle fitted with ARAI approved anti-theft device?"
                    ) && <Badges value={"Yes"} />}
                  </div>
                )}
                {TypeReturn(type) !== "cv" &&
                  !BlockedSections(import.meta.env.VITE_BROKER).includes(
                    "voluntary discount"
                  ) && (
                    <div className="planOptionName">
                      Voluntary Deductible{" "}
                      {addOnsAndOthers?.selectedDiscount?.includes(
                        "Voluntary Discounts"
                      ) && (
                        <Badges
                          value={`₹ ${addOnsAndOthers?.volDiscountValue}`}
                        />
                      )}{" "}
                    </div>
                  )}
                {type === "cv" && (
                  <div className="planOptionName">
                    Vehicle Limited to Own Premises{" "}
                  </div>
                )}
                <div
                  className="planOptionName"
                  style={{ display: temp_data?.odOnly && "none" }}
                >
                  TPPD Cover
                </div>
              </li>
            )}
          </ul>
        </div>
      </TopDiv>
    </>
  );
}

export default Features;

// propType
Features.prototype = {
  type: PropTypes.string,
  quote: PropTypes.object,
  scrollPosition: PropTypes.number,
  zdlp: PropTypes.string,
  setZdlp: PropTypes.func,
  claimList: PropTypes.object,
  setZdlp_gdd: PropTypes.func,
  claimList_gdd: PropTypes.object,
};
// DefaultTypes
Features.defaultProps = {
  type: "",
  quote: {},
  zdlp: "",
  setZdlp: () => {},
  claimList: {},
  setZdlp_gdd: () => {},
  claimList_gdd: {},
};
