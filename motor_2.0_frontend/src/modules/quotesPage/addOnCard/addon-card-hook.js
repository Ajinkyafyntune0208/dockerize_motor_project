/* eslint-disable react-hooks/exhaustive-deps */
import { useEffect, useState } from "react";
import {
  CancelAll,
  SaveAddonsData,
  SetaddonsAndOthers,
  UpdateQuotesData,
  clear,
  VolunaryList as getVoluntaryList,
  updateQuoteShortTerm,
  UpdateQuoteThirdParty,
  UpdateQuoteComprehensive,
} from "../quote.slice";
import _ from "lodash";
import { set_temp_data } from "modules/Home/home.slice";
import { getCoverValue } from "../quoteUtil";
import { setTempData } from "../filterConatiner/quoteFilter.slice";

// prettier-ignore
export const useFetchVoluntaryList = (voluntaryList, dispatch, temp_data, type) => {
  useEffect(() => {
    if (voluntaryList?.length === 0) {
      dispatch(
        getVoluntaryList({
          productSubTypeId: temp_data?.productSubTypeId
            ? temp_data?.productSubTypeId
            : type === "bike"
            ? 2
            : 1,
        })
      );
    }
  }, [voluntaryList?.length]);
};

export const useSetTabState = (tab, dispatch) => {
  useEffect(() => {
    if (tab === "tab2") {
      dispatch(
        set_temp_data({
          tab: "tab2",
        })
      );
    } else {
      dispatch(
        set_temp_data({
          tab: "tab1",
        })
      );
    }
  }, [tab]);
};

export const useUpdateStatusTimeout = (saveQuote) => {
  const [upd, setUpd] = useState(true);

  useEffect(() => {
    if (saveQuote) {
      const timeoutId = setTimeout(() => {
        setUpd(false);
      }, 3000);

      return () => clearTimeout(timeoutId);
    }
  }, [saveQuote]);

  return upd;
};

export const useUpdateQuotesData = (updateQuotesProps) => {
  // prettier-ignore
  const { saveQuote, upd, dispatch, userData, enquiry_id, temp_data,
    tempData, addOnsAndOthers, policyTypeCode, setQuoteComprehesiveGrouped, setQuoteComprehesiveGrouped1,
    setUngroupedQuoteShortTerm3, setUngroupedQuoteShortTerm6, setGroupedQuoteShortTerm3,
    setGroupedQuoteShortTerm6, setQuoteShortTerm3, setQuoteShortTerm6, setQuoteTpGrouped1,
    cpaFetch } = updateQuotesProps;

  const clearPreviousQuotes = async () => {
    await setQuoteComprehesiveGrouped([]);
    await setQuoteComprehesiveGrouped1([]);
    await setUngroupedQuoteShortTerm3([]);
    await setUngroupedQuoteShortTerm6([]);
    await setGroupedQuoteShortTerm3([]);
    await setGroupedQuoteShortTerm6([]);
    await setQuoteShortTerm3([]);
    await setQuoteShortTerm6([]);
    await setQuoteTpGrouped1([]);
    await dispatch(UpdateQuoteComprehensive([]));
    await dispatch(UpdateQuoteThirdParty([]));
    await dispatch(updateQuoteShortTerm([]));
  };

  useEffect(() => {
    if (saveQuote && !upd) {
      //clearing previous quotes
      clearPreviousQuotes();
      dispatch(CancelAll(true)); // cancel all apis loading (quotes apis)
      var data = {
        enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
        vehicleIdv: tempData.idvChoosed,
        idvChangedType: tempData?.idvType,
        vehicleElectricAccessories: Number(
          addOnsAndOthers?.vehicleElectricAccessories
        ),
        vehicleNonElectricAccessories: Number(
          addOnsAndOthers?.vehicleNonElectricAccessories
        ),
        externalBiFuelKit: Number(addOnsAndOthers?.externalBiFuelKit),
        OwnerDriverPaCover: addOnsAndOthers?.selectedCpa?.includes(
          "Compulsory Personal Accident"
        )
          ? "Y"
          : "N",
        antiTheft: addOnsAndOthers?.selectedDiscount?.includes(
          "Is the vehicle fitted with ARAI approved anti-theft device?"
        )
          ? "Y"
          : "N",
        UnnamedPassengerPaCover: addOnsAndOthers?.selectedAdditions?.includes(
          "Unnamed Passenger PA Cover"
        )
          ? getCoverValue(addOnsAndOthers?.unNamedCoverValue)
          : null,
        nfppValue: addOnsAndOthers?.nfppValue
          ? Number(addOnsAndOthers?.nfppValue)
          : null,
        voluntarydeductableAmount:
          addOnsAndOthers?.volDiscountValue !== "None" &&
          addOnsAndOthers?.selectedDiscount?.includes("Voluntary Discounts")
            ? addOnsAndOthers?.volDiscountValue
            : 0,

        isClaim: temp_data?.noClaimMade ? "N" : "Y",
        previousNcb: temp_data?.ncb ? temp_data?.ncb?.slice(0, -1) : 0,
        applicableNcb: temp_data?.carOwnership
          ? 0
          : temp_data?.newNcb
          ? temp_data?.newNcb?.slice(0, -1)
          : 0,
        previousInsurer:
          userData.temp_data?.prevIcFullName?.length !== "NEW"
            ? userData.temp_data?.prevIcFullName === "New"
              ? "NEW"
              : userData.temp_data?.prevIcFullName
            : "NEW",
        previousInsurerCode:
          userData.temp_data?.prevIc !== "New"
            ? userData.temp_data?.prevIc === "New"
              ? "NEW"
              : userData.temp_data?.prevIc
            : "NEW",

        manufactureYear: temp_data?.manfDate,
        policyExpiryDate:
          userData.temp_data?.expiry === "Not Sure" ||
          userData.temp_data?.expiry === "New"
            ? "New"
            : userData.temp_data?.expiry,
        vehicleRegisterDate: temp_data?.regDate,
        previousPolicyType: !temp_data?.newCar
          ? tempData?.policyType === "New"
            ? "Not sure"
            : tempData?.policyType
          : "NEW",
        ownershipChanged: temp_data?.carOwnership ? "Y" : "N",
        isIdvChanged:
          tempData.idvChoosed && tempData.idvChoosed !== 0 ? "Y" : "N",
        businessType: temp_data?.newCar
          ? "newbusiness"
          : temp_data?.breakIn
          ? "breakin"
          : "rollover",
        policyType: temp_data?.odOnly ? "own_damage" : "comprehensive",
        vehicleOwnerType: userData.temp_data?.ownerTypeId === 1 ? "I" : "C",
        version: temp_data?.versionId,
        versionName: temp_data?.versionName,
        fuelType: temp_data?.fuel,
        gcvCarrierType: temp_data?.gcvCarrierType,
        isPopupShown: temp_data?.isPopupShown === "Y" ? "Y" : "N",
        isNcbVerified: temp_data?.isNcbVerified === "Y" ? "Y" : "N",
        isRenewal: temp_data?.isRenewal === "Y" ? "Y" : "N",
        isOdDiscountApplicable:
          temp_data?.isOdDiscountApplicable === "Y" ? "Y" : "N",
        zeroDepInLastPolicy:
          import.meta.env.VITE_BROKER === "ABIBL"
            ? "Y"
            : temp_data?.zeroDepInLastPolicy && temp_data?.zeroDepInLastPolicy,
        rtoNumber: temp_data?.rtoNumber,
        rto: temp_data?.rtoNumber,
        rtoId: temp_data?.rtoNumber,
        rtoCode: temp_data?.rtoNumber,
        vehicleRegisterAt: temp_data?.rtoNumber,
        manfId: temp_data?.manfId,
        manfName: temp_data?.manfName,
        manfactureId: temp_data?.manfId,
        manfactureName: temp_data?.manfName,
        modelId: temp_data?.modelId,
        modelName: temp_data?.modelName,
        model: temp_data?.modelId,
        prevShortTerm: temp_data?.prevShortTerm * 1,
        isClaimVerified: temp_data?.isClaimVerified,
        isToastShown: temp_data?.isToastShown,
        isRedirectionDone: temp_data?.isRedirectionDone,
        selectedGvw: temp_data?.selectedGvw,
        defaultGvw: temp_data?.defaultGvw,
        infoToaster: temp_data?.infoToaster,
        previousPolicyTypeIdentifier: temp_data?.previousPolicyTypeIdentifier,
        isMultiYearPolicy:
          !_.isEmpty(temp_data?.regDate?.split("-")) &&
          temp_data?.regDate?.split("-")[2] * 1 === 2019 &&
          temp_data?.isMultiYearPolicy
            ? "Y"
            : "N",
        previousPolicyTypeIdentifierCode: policyTypeCode(),
        isNcbConfirmed: temp_data?.isNcbConfirmed,
        vehicleInvoiceDate: temp_data?.vehicleInvoiceDate,
      };
      dispatch(UpdateQuotesData(data));
      dispatch(clear());
      // resetting cancel all apis loading so quotes will restart (quotes apis)
      dispatch(CancelAll(false));
    }
  }, [
    saveQuote,
    addOnsAndOthers?.selectedAccesories,
    addOnsAndOthers?.vehicleElectricAccessories,
    addOnsAndOthers?.vehicleNonElectricAccessories,
    addOnsAndOthers?.externalBiFuelKit,
    addOnsAndOthers?.selectedAdditions,
    addOnsAndOthers?.unNamedCoverValue,
    addOnsAndOthers?.additionalPaidDriver,
    addOnsAndOthers?.selectedDiscount,
    addOnsAndOthers?.volDiscountValue,
    addOnsAndOthers?.agent_discount,
    tempData?.idvChoosed,
    tempData?.idvType,
    temp_data?.ncb,
    temp_data?.expiry,
    temp_data?.prevIc,
    userData.temp_data?.prevIcFullName,
    temp_data?.manfDate,
    temp_data?.regDate,
    userData.temp_data?.expiry,
    tempData?.policyType,
    temp_data?.noClaimMade,
    temp_data?.newCar,
    temp_data?.breakIn,
    temp_data?.carOwnership,
    temp_data?.ownerTypeId,
    temp_data?.gcvCarrierType,
    temp_data?.fuel,
    temp_data?.versionId,
    temp_data?.versionName,
    temp_data?.odOnly,
    temp_data?.reloaded,
    temp_data?.isPopupShown,
    temp_data?.isNcbVerified,
    temp_data?.zeroDepInLastPolicy,
    temp_data?.rtoNumber,
    temp_data?.prevShortTerm,
    // temp_data?.isOdDiscountApplicable,
    temp_data?.isClaimVerified,
    temp_data?.isToastShown,
    temp_data?.isRedirectionDone,
    temp_data?.selectedGvw,
    temp_data?.previousPolicyTypeIdentifier,
    temp_data?.isMultiYearPolicy,
    temp_data?.vehicleInvoiceDate,
    cpaFetch
  ]);
};

export const usePrefillAddonsAndOthers = (prefillProps) => {
  // prettier-ignore
  const {
    temp_data, setAmountElectricPrefill, setAmountNonElectricPrefill, setAmountTrailerPrefill,
    setAmountEngPrefill, setValue, setLLCountPrefillCleaner, setLLCountPrefillConductor, 
    setLLCountPrefillDriver, setUnNamedCoverValue, setAdditionalPaidDriver, setPaPaidDriverGCV,
    setVolDiscountValue, setRsa, setRsa2, setZeroDep, setImt23, setCpa, setConsumables,
    setDrange, setEmergencyMedicalExpenses, setEngineProtector, setKeyReplace, setLopb, setMultiCpa,
    setNcbProtectiont, setWindShield, setReturnToInvoice, setTyreSecure, setEmiprotection, dispatch, 
    cpa, multiCpa, additionalTowing, setAdditionalTowing, setBatteryprotect, setNfppValuePrefill, setGstToggle,
  } = prefillProps;

  useEffect(() => {
    let AddonDataPrefill = temp_data?.addons;
    let accesoriesPrefil = AddonDataPrefill?.accessories?.map(
      (item) => item.name
    );

    let additionalCoversPrefil = AddonDataPrefill?.additionalCovers?.map(
      (item) => item.name
    );

    let discountPrefill = AddonDataPrefill?.discounts?.map((item) => item.name);
    let discountPercentagePrefill = temp_data?.addons?.agentDiscount?.selected;
    let addonPrefill = AddonDataPrefill?.addons?.map((item) => item.name);

    let gstPrefill = _.compact(
      AddonDataPrefill?.frontendTags?.map((item) => item.gstToggle)
    );

    let cpaPrefill = _.compact(
      AddonDataPrefill?.compulsoryPersonalAccident?.map((item) => item.name)
    );

    let isTenure = _.compact(
      AddonDataPrefill?.compulsoryPersonalAccident?.map((item) => item.tenure)
    );
    let AmountElectrical = _.filter(AddonDataPrefill?.accessories, {
      name: "Electrical Accessories",
    }).map((v) => v.sumInsured);
    let AmountNonElectrical = _.filter(AddonDataPrefill?.accessories, {
      name: "Non-Electrical Accessories",
    }).map((v) => v.sumInsured);
    let AmountCng = _.filter(AddonDataPrefill?.accessories, {
      name: "External Bi-Fuel Kit CNG/LPG",
    }).map((v) => v.sumInsured);

    let AmountTrailer = _.filter(AddonDataPrefill?.accessories, {
      name: "Trailer",
    }).map((v) => v.sumInsured);

    let AmountPaCover = _.filter(AddonDataPrefill?.additionalCovers, {
      name: "PA cover for additional paid driver",
    }).map((v) => v.sumInsured);

    let AmountUnNamedCover = _.filter(AddonDataPrefill?.additionalCovers, {
      name: "Unnamed Passenger PA Cover",
    }).map((v) => v.sumInsured);

    let LLItmesPrefill = _.filter(AddonDataPrefill?.additionalCovers, {
      name: "LL paid driver/conductor/cleaner",
    }).map((v) => v.selectedLLpaidItmes);

    let LLCountDriver = _.filter(AddonDataPrefill?.additionalCovers, {
      name: "LL paid driver/conductor/cleaner",
    }).map((v) => v.lLNumberDriver);
    let LLCountConductor = _.filter(AddonDataPrefill?.additionalCovers, {
      name: "LL paid driver/conductor/cleaner",
    }).map((v) => v.lLNumberConductor);
    let LLCountCleaner = _.filter(AddonDataPrefill?.additionalCovers, {
      name: "LL paid driver/conductor/cleaner",
    }).map((v) => v.lLNumberCleaner);

    let AmountPaCoverGCV = _.filter(AddonDataPrefill?.additionalCovers, {
      name: "PA paid driver/conductor/cleaner",
    }).map((v) => v.sumInsured);

    let AmountVolIns = _.filter(AddonDataPrefill?.discounts, {
      name: "voluntary_insurer_discounts",
    }).map((v) => v.sumInsured);

    let prefillCountries = _.filter(AddonDataPrefill?.additionalCovers, {
      name: "Geographical Extension",
    }).map((v) => v.countries);

    let nfppValuePrefill = _.filter(AddonDataPrefill?.additionalCovers, {
      name: "NFPP Cover",
    }).map((v) => v.nfppValue);

    //prefilll accesories

    var newAccesoriesPrefilled = [];
    if (accesoriesPrefil?.includes("Electrical Accessories")) {
      newAccesoriesPrefilled.push("Electrical Accessories");
      setAmountElectricPrefill(AmountElectrical[0]);
    } else {
      newAccesoriesPrefilled.push(false);
    }
    if (accesoriesPrefil?.includes("Non-Electrical Accessories")) {
      newAccesoriesPrefilled.push("Non-Electrical Accessories");
      setAmountNonElectricPrefill(AmountNonElectrical[0]);
    } else {
      newAccesoriesPrefilled.push(false);
    }
    if (accesoriesPrefil?.includes("External Bi-Fuel Kit CNG/LPG")) {
      newAccesoriesPrefilled.push("External Bi-Fuel Kit CNG/LPG");

      if (accesoriesPrefil?.includes("External Bi-Fuel Kit CNG/LPG")) {
        setAmountEngPrefill(AmountCng[0]);
      }
    } else {
      newAccesoriesPrefilled.push(false);
    }
    if (accesoriesPrefil?.includes("Trailer")) {
      newAccesoriesPrefilled.push("Trailer");
      setAmountTrailerPrefill(AmountTrailer[0]);
    } else {
      newAccesoriesPrefilled.push(false);
    }

    setValue("accesories", newAccesoriesPrefilled);

    //prefilll additions

    var newAdditionsPrefilled = [];
    var newLLPrefillItmes = [];
    if (
      additionalCoversPrefil?.includes("PA cover for additional paid driver")
    ) {
      newAdditionsPrefilled.push("PA cover for additional paid driver");
    } else {
      newAdditionsPrefilled.push(false);
    }
    if (additionalCoversPrefil?.includes("Unnamed Passenger PA Cover")) {
      newAdditionsPrefilled.push("Unnamed Passenger PA Cover");
    } else {
      newAdditionsPrefilled.push(false);
    }
    if (additionalCoversPrefil?.includes("LL paid driver/conductor/cleaner")) {
      if (LLItmesPrefill[0]?.includes("DriverLL")) {
        newLLPrefillItmes.push("DriverLL");
        setLLCountPrefillDriver(LLCountDriver[0]);
      } else {
        newLLPrefillItmes.push(false);
      }
      if (LLItmesPrefill[0]?.includes("ConductorLL")) {
        newLLPrefillItmes.push("ConductorLL");
        setLLCountPrefillConductor(LLCountConductor[0]);
      } else {
        newLLPrefillItmes.push(false);
      }
      if (LLItmesPrefill[0]?.includes("CleanerLL")) {
        newLLPrefillItmes.push("CleanerLL");
        setLLCountPrefillCleaner(LLCountCleaner[0]);
      } else {
        newLLPrefillItmes.push(false);
      }

      newAdditionsPrefilled.push("LL paid driver/conductor/cleaner");
    } else {
      newAdditionsPrefilled.push(false);
    }
    if (additionalCoversPrefil?.includes("PA paid driver/conductor/cleaner")) {
      newAdditionsPrefilled.push("PA paid driver/conductor/cleaner");
    } else {
      newAdditionsPrefilled.push(false);
    }
    if (additionalCoversPrefil?.includes("LL paid driver")) {
      newAdditionsPrefilled.push("LL paid driver");
    } else {
      newAdditionsPrefilled.push(false);
    }
    if (additionalCoversPrefil?.includes("Geographical Extension")) {
      newAdditionsPrefilled.push("Geographical Extension");
    } else {
      newAdditionsPrefilled.push(false);
    }
    if (additionalCoversPrefil?.includes("NFPP Cover")) {
      newAdditionsPrefilled.push("NFPP Cover");
      setNfppValuePrefill(nfppValuePrefill[0]);
    } else {
      newAdditionsPrefilled.push(false);
    }

    setValue("additional", newAdditionsPrefilled);

    setTimeout(() => {
      !_.isEmpty(prefillCountries) && setValue("country", prefillCountries[0]);
    }, 2000);
    setTimeout(() => {
      setValue("LLpaidItmes", newLLPrefillItmes);
    }, 2000);
    setUnNamedCoverValue(getCoverValue(AmountUnNamedCover[0], true));
    setAdditionalPaidDriver(getCoverValue(AmountPaCover[0], true));

    setPaPaidDriverGCV(getCoverValue(AmountPaCoverGCV[0], true));

    //prefillLLpaidDriverItems

    //prefill discount

    var newDiscountPrefilled = [];
    if (discountPrefill?.includes("anti-theft device")) {
      newDiscountPrefilled.push(
        "Is the vehicle fitted with ARAI approved anti-theft device?"
      );
    } else {
      newDiscountPrefilled.push(false);
    }
    if (discountPrefill?.includes("voluntary_insurer_discounts")) {
      newDiscountPrefilled.push("Voluntary Discounts");
    } else {
      newDiscountPrefilled.push(false);
    }

    if (discountPrefill?.includes("Vehicle Limited to Own Premises")) {
      newDiscountPrefilled.push("Vehicle Limited to Own Premises");
    } else {
      newDiscountPrefilled.push(false);
    }

    if (discountPrefill?.includes("TPPD Cover")) {
      newDiscountPrefilled.push("TPPD Cover");
    } else {
      newDiscountPrefilled.push(false);
    }

    setValue("discount", newDiscountPrefilled);

    setVolDiscountValue(
      AmountVolIns ? (AmountVolIns[0] > 0 ? AmountVolIns[0] : 2500) : 2500
    );

    //prefill addons
    if (
      addonPrefill?.includes("Road Side Assistance") &&
      addonPrefill?.includes("Road Side Assistance 2")
    ) {
      setRsa(true);
    } else {
      if (addonPrefill?.includes("Road Side Assistance")) {
        setRsa(true);
      } else {
        setRsa(false);
      }
      if (addonPrefill?.includes("Road Side Assistance 2")) {
        setRsa2(true);
      } else {
        setRsa2(false);
      }
    }

    if (addonPrefill?.includes("Zero Depreciation")) {
      setZeroDep(true);
    } else {
      setZeroDep(false);
    }
    if (addonPrefill?.includes("IMT - 23")) {
      setImt23(true);
    } else {
      setImt23(false);
    }

    //mototaddonprefill

    if (addonPrefill?.includes("Key Replacement")) {
      setKeyReplace(true);
    } else {
      setKeyReplace(false);
    }
    if (addonPrefill?.includes("Engine Protector")) {
      setEngineProtector(true);
    } else {
      setEngineProtector(false);
    }
    if (addonPrefill?.includes("NCB Protection")) {
      setNcbProtectiont(true);
    } else {
      setNcbProtectiont(false);
    }
    if (addonPrefill?.includes("Consumable")) {
      setConsumables(true);
    } else {
      setConsumables(false);
    }
    if (addonPrefill?.includes("Tyre Secure")) {
      setTyreSecure(true);
    } else {
      setTyreSecure(false);
    }
    if (addonPrefill?.includes("Return To Invoice")) {
      setReturnToInvoice(true);
    } else {
      setReturnToInvoice(false);
    }
    if (addonPrefill?.includes("Loss of Personal Belongings")) {
      setLopb(true);
    } else {
      setLopb(false);
    }
    if (addonPrefill?.includes("Emergency Medical Expenses")) {
      setEmergencyMedicalExpenses(true);
    } else {
      setEmergencyMedicalExpenses(false);
    }
    if (addonPrefill?.includes("Wind Shield")) {
      setWindShield(true);
    } else {
      setWindShield(false);
    }
    if (addonPrefill?.includes("Additional Towing")) {
      setAdditionalTowing(true);
    } else {
      setAdditionalTowing(false);
    }
    if (addonPrefill?.includes("EMI Protection")) {
      setEmiprotection(true);
    } else {
      setEmiprotection(false);
    }
    if (addonPrefill?.includes("Battery Protect")) {
      setBatteryprotect(true);
    } else {
      setBatteryprotect(false);
    }

    //gst prefill
    if (gstPrefill?.includes("Y")) {
      setGstToggle(true);
    } else if (gstPrefill?.includes("N")) {
      setGstToggle(false);
    }

    //prefill Cpa
    if (!_.isEmpty(cpaPrefill)) {
      if (
        !cpaPrefill?.includes("Compulsory Personal Accident") ||
        temp_data?.odOnly
      ) {
        setCpa(false);
        setMultiCpa(false);
      } else {
        if (!_.isEmpty(_.compact(isTenure))) {
          setMultiCpa(true);
          setCpa(false);
        } else {
          setCpa(true);
          setMultiCpa(false);
        }
      }
    }
    let cpaPrefillReason = AddonDataPrefill?.compulsoryPersonalAccident?.map(
      (item) => item.reason
    );
    if (cpaPrefillReason) {
      if (cpaPrefillReason.length !== 0) {
        dispatch(
          setTempData({
            cpaReason: cpaPrefillReason[0],
          })
        );
      } else {
        dispatch(
          setTempData({
            cpaReason: false,
          })
        );
      }
    }

    //prefill discount %
    discountPercentagePrefill * 1 && setDrange(discountPercentagePrefill);

    //-----------------prefill redux---------------------------

    var dataRedux = {
      selectedCpa:
        temp_data?.ownerTypeId === 1 &&
        (cpa || multiCpa) &&
        cpaPrefill?.includes("Compulsory Personal Accident")
          ? ["Compulsory Personal Accident"]
          : [],
      isTenure: _.compact(isTenure),
      selectedAccesories: newAccesoriesPrefilled?.filter(Boolean),
      vehicleElectricAccessories: newAccesoriesPrefilled?.includes(
        "Electrical Accessories"
      )
        ? AmountElectrical[0]
        : 0,
      vehicleNonElectricAccessories: newAccesoriesPrefilled?.includes(
        "Non-Electrical Accessories"
      )
        ? AmountNonElectrical[0]
        : 0,
      externalBiFuelKit: newAccesoriesPrefilled?.includes(
        "External Bi-Fuel Kit CNG/LPG"
      )
        ? AmountCng[0]
        : 0,
      trailerCover: newAccesoriesPrefilled?.includes("Trailer")
        ? AmountTrailer[0]
        : 0,

      selectedAdditions: newAdditionsPrefilled?.filter(Boolean),
      unNamedCoverValue: getCoverValue(AmountUnNamedCover[0] * 1, true),
      additionalPaidDriver: getCoverValue(AmountPaCover[0] * 1, true),
      paPaidDriverGCV: getCoverValue(AmountPaCoverGCV[0] * 1, true),

      LLNumberDriver: newAdditionsPrefilled?.includes(
        "LL paid driver/conductor/cleaner"
      )
        ? LLCountDriver[0] > 0
          ? Number(LLCountDriver[0])
          : 0
        : 0,
      LLNumberConductor: newAdditionsPrefilled?.includes(
        "LL paid driver/conductor/cleaner"
      )
        ? LLCountConductor[0] > 0
          ? Number(LLCountConductor[0])
          : 0
        : 0,
      LLNumberCleaner: newAdditionsPrefilled?.includes(
        "LL paid driver/conductor/cleaner"
      )
        ? Number(LLCountCleaner[0]) > 0
          ? Number(LLCountCleaner[0])
          : 0
        : 0,

      selectedDiscount: newDiscountPrefilled?.filter(Boolean),
      volDiscountValue: AmountVolIns
        ? AmountVolIns[0] > 0
          ? AmountVolIns[0]
          : 2500
        : 2500,
      selectedInsurer: [],

      LLpaidItmes: newAdditionsPrefilled?.includes(
        "LL paid driver/conductor/cleaner"
      )
        ? newLLPrefillItmes?.filter(Boolean)
        : [],
      countries: !_.isEmpty(prefillCountries) ? prefillCountries[0] : [],
      nfppValue: !_.isEmpty(nfppValuePrefill) ? nfppValuePrefill[0] : 0,
    };

    dispatch(
      SetaddonsAndOthers({
        ...dataRedux,
        dbStructure: { addonData: { addons: AddonDataPrefill?.addons } },
      })
    );
  }, [temp_data?.addons, temp_data?.vehicleLpgCngKitValue]);
};

export const useGetGcvFlag = (temp_data) => {
  const [gcvJourney, setgcvJourney] = useState(false);

  useEffect(() => {
    if (temp_data?.journeyCategory === "GCV") {
      setgcvJourney(true);
    } else {
      setgcvJourney(false);
    }
  }, [gcvJourney, temp_data?.journeyCategory]);
  return gcvJourney;
};

export const useGetVehicleTypeFlag = (type) => {
  const [motor, setMotor] = useState(false);
  const [bike, setBike] = useState(false);

  useEffect(() => {
    if (type === "car") {
      setMotor(true);
    } else if (type === "bike") {
      setBike(true);
    } else {
      setMotor(false);
      setBike(false);
    }
  }, [type]);

  return { motor, bike };
};

export const useClearAll = (clearAllProps) => {
  // prettier-ignore
  const {
    clearAll, setCpa, setMultiCpa, setRsa, setRsa2, setZeroDep, setImt23, setKeyReplace,
    setEngineProtector, setNcbProtectiont, setConsumables, setTyreSecure, setReturnToInvoice, setLopb,
    setEmergencyMedicalExpenses, setWindShield, setEmiprotection, setAdditionalTowing, setBatteryprotect, setRelevant, setRenewalFilter,
    selectedDiscount, selectedAdditions, selectedAccesories, setValue, temp_data, dispatch, enquiry_id,
  } = clearAllProps;

  useEffect(() => {
    if (clearAll > 0) {
      setCpa(false);
      setMultiCpa(false);
      setRsa(false);
      setRsa2(false);
      setZeroDep(false);
      setImt23(false);
      setKeyReplace(false);
      setEngineProtector(false);
      setNcbProtectiont(false);
      setConsumables(false);
      setTyreSecure(false);
      setReturnToInvoice(false);
      setLopb(false);
      setEmergencyMedicalExpenses(false);
      setWindShield(false);
      setEmiprotection(false);
      setAdditionalTowing(false);
      setBatteryprotect(false);
      setRelevant(false);
      setRenewalFilter(false);

      if (
        !_.isEmpty(selectedDiscount) ||
        !_.isEmpty(selectedAdditions) ||
        !_.isEmpty(selectedAccesories)
      ) {
        setValue("accesories", [false, false, false, false]);
        setValue("additional", [
          false,
          false,
          false,
          false,
          false,
          false,
          false,
        ]);
        setValue("discount", [false, false, false, false]);

        var data1 = {
          enquiryId: temp_data?.enquiry_id || enquiry_id,
          addonData: {
            accessories: [],
            discounts: [],
            additional_covers: [],
          },
        };

        dispatch(SaveAddonsData(data1));

        var data = {
          selectedDiscount: [],
          selectedAdditions: [],
          selectedAccesories: [],
          vehicleElectricAccessories: 0,
          vehicleNonElectricAccessories: 0,
          externalBiFuelKit: 0,
          trailerCover: 0,
          LLNumberDriver: 0,
          LLNumberConductor: 0,
          LLNumberCleaner: 0,
          nfppValue: 0,
          LLpaidItmes: [],
          countries: [],
          isTenure: [],
          selectedCpa: [],
        };
        dispatch(CancelAll(true)); // cancel all apis loading (quotes apis)
        dispatch(SetaddonsAndOthers(data));
        dispatch(CancelAll(false)); // resetting cancel all apis loading so quotes will restart (quotes apis)
      }
    }
  }, [clearAll]);
};

export const useClearAllButtonVisibility = (clearAllButtonVisibilityProps) => {
  // prettier-ignore
  const {
    cpa, multiCpa, setClearButtonCondition, addOnsAndOthers, selectedDiscount,
    selectedAdditions, selectedAccesories, isRelevant, renewalFilter, temp_data,
  } = clearAllButtonVisibilityProps;

  useEffect(() => {
    if (cpa || multiCpa) {
      setClearButtonCondition(true);
    } else if (!_.isEmpty(multiCpa)) {
      setClearButtonCondition(true);
    } else if (!_.isEmpty(addOnsAndOthers?.selectedAddons)) {
      setClearButtonCondition(true);
    } else if (!_.isEmpty(selectedDiscount)) {
      setClearButtonCondition(true);
    } else if (!_.isEmpty(selectedAdditions)) {
      setClearButtonCondition(true);
    } else if (!_.isEmpty(selectedAccesories)) {
      setClearButtonCondition(true);
    } else if (isRelevant) {
      setClearButtonCondition(true);
    } else if (
      renewalFilter &&
      !temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
      temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y"
    ) {
      setClearButtonCondition(true);
    } else {
      setClearButtonCondition(false);
    }
  }, [
    selectedDiscount,
    selectedAccesories,
    selectedAdditions,
    addOnsAndOthers?.selectedAddons,
    cpa,
    multiCpa,
  ]);
};
