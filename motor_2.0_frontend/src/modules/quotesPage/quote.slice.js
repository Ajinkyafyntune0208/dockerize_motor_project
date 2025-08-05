import { createSlice, current } from "@reduxjs/toolkit";
import service from "./serviceApi";
import axios from "axios";
import {
  getPremByIC,
  updateQuote,
  saveSelectedQuote,
  saveSelectedAddons,
} from "./serviceApi";
import { actionStructre, serializeError } from "../../utils";
import _ from "lodash";
import moment from "moment";
import swal from "sweetalert";
import { updateModifiedTime } from "modules/proposal/proposal.slice";
import { DummyQuote } from "./quoteUtil";
import { switchError } from "./quote-helper";

export const quoteSlice = createSlice({
  name: "quote",
  initialState: {
    loading: false,
    error: null,
    success: null,
    addOnList: [],
    voluntaryList: [],
    quotesList: [],
    quoteComprehesive: [],
    quotetThirdParty: [],
    quoteShortTerm: [],
    selectedQuote: null,
    compareQuotesList: [],
    addOnsAndOthers: {},
    updateResponse: null,
    errorIcBased: [],
    quoteListLoading: false,
    saveQuoteResponse: false,
    saveAddonsResponse: false,
    finalPremiumlist: [],
    finalPremiumlist1: [],
    saveQuoteLoader: false,
    updateQuoteLoader: false,
    masterLogos: [],
    quotesLoaded: false,
    garage: [],
    buyNowSingleQuoteUpdate: false,
    singleUpdatedQuote: false,
    multiUpdatedQuote: [],
    loader: false,
    premiumPdf: false,
    emailPdf: false,
    emailComparePdf: false,
    whatsapp: false,
    customLoad: null,
    versionId: null,
    comparePdfData: false,
    loadingCancelled: false,
    loadingFromPdf: false,
    singleQuoteError: null,
    saveQuoteError: null,
    quoteBundle: {},
    shortTerm: null,
    selectedTab: "tab1",
    zdAvailablity: [],
    showPop: false,
    shortTermType: null,
    addonConfig: null,
    cpaSet: null,
    validQuote: [],
    showingZdlp: false,
    interimLoading: false,
    quoteFill: [],
    shortlenUrl: null,
    paydLoading: null,
  },
  reducers: {
    loading: (state) => {
      state.loading = true;
      state.error = null;
      state.success = null;
    },
    setLoader: (state) => {
      state.loader = true;
    },
    setLoaderToFalse: (state) => {
      state.loader = false;
    },
    success: (state, { payload }) => {
      state.loading = null;
      state.error = null;
      state.success = payload;
    },
    error: (state, { payload }) => {
      state.loading = null;
      state.error = serializeError(payload);
      state.success = payload;
    },
    addOnList: (state, { payload }) => {
      state.addOnList = payload;
    },
    SetMasterLogoList: (state, { payload }) => {
      state.masterLogos = payload;
    },
    SetvoluntaryList: (state, { payload }) => {
      state.voluntaryList = payload;
    },
    setQuotesList: (state, { payload }, shared) => {
      state.quotesList = payload;
      state.loading = false;
      state.quoteListLoading = false;
    },
    setQuoteComprehensive: (state, { payload }) => {
      state.quoteComprehesive =
        // _.uniqBy(
        _.compact([...state.quoteComprehesive, ...payload]);
      // ,
      // "policyId"
      // )
    },

    setQuotePayD: (state, { payload }) => {
      // Extract policy ids from payload
      let policyIds = payload?.map((i) => i?.policyId);
      //Remove existing policyIds from comprehensive state
      let compQuotes =
        !_.isEmpty(state.quoteComprehesive) &&
        state.quoteComprehesive.filter((i) => !policyIds.includes(i?.policyId));
      //Merge new payload in comp quotes.
      state.quoteComprehesive = _.compact([...compQuotes, ...payload]);
    },
    UpdateQuoteComprehensive: (state, { payload }) => {
      state.quoteComprehesive = payload;
    },
    setQuoteThirdParty: (state, { payload }) => {
      state.quotetThirdParty = _.uniqBy(
        _.compact([...state.quotetThirdParty, ...payload]),
        "policyId"
      );
    },
    UpdateQuoteThirdParty: (state, { payload }) => {
      state.quotetThirdParty = payload;
    },

    setQuoteShortTerm: (state, { payload }) => {
      state.quoteShortTerm = _.uniqBy(
        _.compact([...state.quoteShortTerm, ...payload]),
        "policyId"
      );
    },

    updateQuoteShortTerm: (state, { payload }) => {
      state.quoteShortTerm = _.uniqBy(
        _.compact([...state.quoteShortTerm, ...payload]),
        "policyId"
      );
    },

    setSelectedQuote: (state, { payload }) => {
      state.selectedQuote = { ...state.selectedQuote, ...payload };
    },

    compareQuotes: (state, { payload }) => {
      const { enquiry_id, addOnsAndOthers, quotePackages, ...other } = payload;
      if (_.isEmpty(current(state)?.addOnsAndOthers) && !addOnsAndOthers) {
        state.addOnsAndOthers = JSON.parse(addOnsAndOthers);
      }
      state.quoteFill = quotePackages;
      const data = _.toArray(other);
      let quoteList = !_.isEmpty(data) ? data : [];
      if (quoteList?.length < 3) {
        let manualArray =
          quoteList?.length === 1
            ? [
                {
                  idv: "",
                  minIdv: 1,
                  maxIdv: "",
                  vehicleIdv: "",
                  qdata: null,
                  ppEnddate: "",
                  addonCover: null,
                  addonCoverDataGet: "",
                  rtoDecline: null,
                  rtoDeclineNumber: null,
                  mmvDecline: null,
                  mmvDeclineName: null,
                  policyType: "",
                  businessType: "",
                  coverType: "",
                  hypothecation: "",
                  hypothecationName: "",
                  vehicleRegistrationNo: "",
                  rtoNo: "",
                  versionId: "",
                  selectedAddon: [],
                  showroomPrice: "",
                  fuelType: "",
                  ncbDiscount: "",
                  companyName: "",
                  companyLogo: "",
                  productName: "",
                  mmvDetail: {
                    manfName: "",
                    modelName: "",
                    versionName: "",
                    fuelType: "",
                    seatingCapacity: "",
                    carryingCapacity: "",
                    cubicCapacity: "",
                    grossVehicleWeight: "",
                    vehicleType: "",
                  },
                  masterPolicyId: {
                    policyId: "",
                    policyNo: "",
                    policyStartDate: "",
                    policyEndDate: "",
                    sumInsured: "",
                    corpClientId: "",
                    productSubTypeId: "",
                    insuranceCompanyId: "",
                    status: "",
                    corpName: "",
                    companyName: "",
                    logo: "",
                    productSubTypeName: "",
                    flatDiscount: "",
                    predefineSeries: "",
                    isPremiumOnline: "",
                    isProposalOnline: "",
                    isPaymentOnline: "",
                  },
                  motorManfDate: "",
                  vehicleRegisterDate: "",
                  vehicleDiscountValues: {
                    masterPolicyId: "",
                    productSubTypeId: "",
                    segmentId: "",
                    rtoClusterId: "",
                    carAge: "",
                    aaiDiscount: "",
                    icVehicleDiscount: "",
                  },
                  basicPremium: "",
                  motorElectricAccessoriesValue: "",
                  motorNonElectricAccessoriesValue: "",
                  motorLpgCngKitValue: "",
                  "totalAccessoriesAmount(netOdPremium)": "",
                  totalOwnDamage: "",
                  tppdPremiumAmount: "",
                  compulsoryPaOwnDriver: "",
                  coverUnnamedPassengerValue: "",
                  defaultPaidDriver: "",
                  motorAdditionalPaidDriver: "",
                  cngLpgTp: "",
                  seatingCapacity: "",
                  deductionOfNcb: "",
                  antitheftDiscount: "",
                  aaiDiscount: "",
                  voluntaryExcess: "",
                  otherDiscount: "",
                  totalLiabilityPremium: "",
                  netPremium: "",
                  serviceTaxAmount: "",
                  serviceTax: "",
                  totalDiscountOd: "",
                  addOnPremiumTotal: "",
                  addonPremium: "",
                  vehicleLpgCngKitValue: "",
                  quotationNo: "",
                  premiumAmount: "",
                  serviceDataResponseerrMsg: "success",
                  userId: null,
                  productSubTypeId: "",
                  userProductJourneyId: "",
                  serviceErrCode: null,
                  serviceErrMsg: null,
                  policyStartDate: "",
                  policyEndDate: "",
                  icOf: "",
                  vehicleIn90Days: "N",
                  getPolicyExpiryDate: null,
                  getChangedDiscountQuoteid: "",
                  vehicleDiscountDetail: {
                    discountId: null,
                    discountRate: null,
                  },
                  isPremiumOnline: "",
                  isProposalOnline: "",
                  isPaymentOnline: "",
                  policyId: "",
                  insuraneCompanyId: "",
                  maxAddonsSelection: null,
                  addOnsData: {
                    inBuilt: {},
                    additional: {},
                    other: [],
                    inBuiltPremium: "",
                    additionalPremium: "",
                    otherPremium: "",
                  },
                  applicableAddons: [],
                  finalOdPremium: "",
                  finalTpPremium: "",
                  finalTotalDiscount: "",
                  finalNetPremium: "",
                  finalGstAmount: "",
                  finalPayableAmount: "",
                },
                {
                  idv: "",
                  minIdv: 1,
                  maxIdv: "",
                  vehicleIdv: "",
                  qdata: null,
                  ppEnddate: "",
                  addonCover: null,
                  addonCoverDataGet: "",
                  rtoDecline: null,
                  rtoDeclineNumber: null,
                  mmvDecline: null,
                  mmvDeclineName: null,
                  policyType: "",
                  businessType: "",
                  coverType: "",
                  hypothecation: "",
                  hypothecationName: "",
                  vehicleRegistrationNo: "",
                  rtoNo: "",
                  versionId: "",
                  selectedAddon: [],
                  showroomPrice: "",
                  fuelType: "",
                  ncbDiscount: "",
                  companyName: "",
                  companyLogo: "",
                  productName: "",
                  mmvDetail: {
                    manfName: "",
                    modelName: "",
                    versionName: "",
                    fuelType: "",
                    seatingCapacity: "",
                    carryingCapacity: "",
                    cubicCapacity: "",
                    grossVehicleWeight: "",
                    vehicleType: "",
                  },
                  masterPolicyId: {
                    policyId: "",
                    policyNo: "",
                    policyStartDate: "",
                    policyEndDate: "",
                    sumInsured: "",
                    corpClientId: "",
                    productSubTypeId: "",
                    insuranceCompanyId: "",
                    status: "",
                    corpName: "",
                    companyName: "",
                    logo: "",
                    productSubTypeName: "",
                    flatDiscount: "",
                    predefineSeries: "",
                    isPremiumOnline: "",
                    isProposalOnline: "",
                    isPaymentOnline: "",
                  },
                  motorManfDate: "",
                  vehicleRegisterDate: "",
                  vehicleDiscountValues: {
                    masterPolicyId: "",
                    productSubTypeId: "",
                    segmentId: "",
                    rtoClusterId: "",
                    carAge: "",
                    aaiDiscount: "",
                    icVehicleDiscount: "",
                  },
                  basicPremium: "",
                  motorElectricAccessoriesValue: "",
                  motorNonElectricAccessoriesValue: "",
                  motorLpgCngKitValue: "",
                  "totalAccessoriesAmount(netOdPremium)": "",
                  totalOwnDamage: "",
                  tppdPremiumAmount: "",
                  compulsoryPaOwnDriver: "",
                  coverUnnamedPassengerValue: "",
                  defaultPaidDriver: "",
                  motorAdditionalPaidDriver: "",
                  cngLpgTp: "",
                  seatingCapacity: "",
                  deductionOfNcb: "",
                  antitheftDiscount: "",
                  aaiDiscount: "",
                  voluntaryExcess: "",
                  otherDiscount: "",
                  totalLiabilityPremium: "",
                  netPremium: "",
                  serviceTaxAmount: "",
                  serviceTax: "",
                  totalDiscountOd: "",
                  addOnPremiumTotal: "",
                  addonPremium: "",
                  vehicleLpgCngKitValue: "",
                  quotationNo: "",
                  premiumAmount: "",
                  serviceDataResponseerrMsg: "success",
                  userId: null,
                  productSubTypeId: "",
                  userProductJourneyId: "",
                  serviceErrCode: null,
                  serviceErrMsg: null,
                  policyStartDate: "",
                  policyEndDate: "",
                  icOf: "",
                  vehicleIn90Days: "N",
                  getPolicyExpiryDate: null,
                  getChangedDiscountQuoteid: "",
                  vehicleDiscountDetail: {
                    discountId: null,
                    discountRate: null,
                  },
                  isPremiumOnline: "",
                  isProposalOnline: "",
                  isPaymentOnline: "",
                  policyId: "",
                  insuraneCompanyId: "",
                  maxAddonsSelection: null,
                  addOnsData: {
                    inBuilt: {},
                    additional: {},
                    other: [],
                    inBuiltPremium: "",
                    additionalPremium: "",
                    otherPremium: "",
                  },
                  applicableAddons: [],
                  finalOdPremium: "",
                  finalTpPremium: "",
                  finalTotalDiscount: "",
                  finalNetPremium: "",
                  finalGstAmount: "",
                  finalPayableAmount: "",
                },
              ]
            : quoteList?.length === 2
            ? [
                {
                  idv: "",
                  minIdv: 1,
                  maxIdv: "",
                  vehicleIdv: "",
                  qdata: null,
                  ppEnddate: "",
                  addonCover: null,
                  addonCoverDataGet: "",
                  rtoDecline: null,
                  rtoDeclineNumber: null,
                  mmvDecline: null,
                  mmvDeclineName: null,
                  policyType: "",
                  businessType: "",
                  coverType: "",
                  hypothecation: "",
                  hypothecationName: "",
                  vehicleRegistrationNo: "",
                  rtoNo: "",
                  versionId: "",
                  selectedAddon: [],
                  showroomPrice: "",
                  fuelType: "",
                  ncbDiscount: "",
                  companyName: "",
                  companyLogo: "",
                  productName: "",
                  mmvDetail: {
                    manfName: "",
                    modelName: "",
                    versionName: "",
                    fuelType: "",
                    seatingCapacity: "",
                    carryingCapacity: "",
                    cubicCapacity: "",
                    grossVehicleWeight: "",
                    vehicleType: "",
                  },
                  masterPolicyId: {
                    policyId: "",
                    policyNo: "",
                    policyStartDate: "",
                    policyEndDate: "",
                    sumInsured: "",
                    corpClientId: "",
                    productSubTypeId: "",
                    insuranceCompanyId: "",
                    status: "",
                    corpName: "",
                    companyName: "",
                    logo: "",
                    productSubTypeName: "",
                    flatDiscount: "",
                    predefineSeries: "",
                    isPremiumOnline: "",
                    isProposalOnline: "",
                    isPaymentOnline: "",
                  },
                  motorManfDate: "",
                  vehicleRegisterDate: "",
                  vehicleDiscountValues: {
                    masterPolicyId: "",
                    productSubTypeId: "",
                    segmentId: "",
                    rtoClusterId: "",
                    carAge: "",
                    aaiDiscount: "",
                    icVehicleDiscount: "",
                  },
                  basicPremium: "",
                  motorElectricAccessoriesValue: "",
                  motorNonElectricAccessoriesValue: "",
                  motorLpgCngKitValue: "",
                  "totalAccessoriesAmount(netOdPremium)": "",
                  totalOwnDamage: "",
                  tppdPremiumAmount: "",
                  compulsoryPaOwnDriver: "",
                  coverUnnamedPassengerValue: "",
                  defaultPaidDriver: "",
                  motorAdditionalPaidDriver: "",
                  cngLpgTp: "",
                  seatingCapacity: "",
                  deductionOfNcb: "",
                  antitheftDiscount: "",
                  aaiDiscount: "",
                  voluntaryExcess: "",
                  otherDiscount: "",
                  totalLiabilityPremium: "",
                  netPremium: "",
                  serviceTaxAmount: "",
                  serviceTax: "",
                  totalDiscountOd: "",
                  addOnPremiumTotal: "",
                  addonPremium: "",
                  vehicleLpgCngKitValue: "",
                  quotationNo: "",
                  premiumAmount: "",
                  serviceDataResponseerrMsg: "success",
                  userId: null,
                  productSubTypeId: "",
                  userProductJourneyId: "",
                  serviceErrCode: null,
                  serviceErrMsg: null,
                  policyStartDate: "",
                  policyEndDate: "",
                  icOf: "",
                  vehicleIn90Days: "N",
                  getPolicyExpiryDate: null,
                  getChangedDiscountQuoteid: "",
                  vehicleDiscountDetail: {
                    discountId: null,
                    discountRate: null,
                  },
                  isPremiumOnline: "",
                  isProposalOnline: "",
                  isPaymentOnline: "",
                  policyId: "",
                  insuraneCompanyId: "",
                  maxAddonsSelection: null,
                  addOnsData: {
                    inBuilt: {},
                    additional: {},
                    other: [],
                    inBuiltPremium: "",
                    additionalPremium: "",
                    otherPremium: "",
                  },
                  applicableAddons: [],
                  finalOdPremium: "",
                  finalTpPremium: "",
                  finalTotalDiscount: "",
                  finalNetPremium: "",
                  finalGstAmount: "",
                  finalPayableAmount: "",
                },
              ]
            : quoteList?.length === 3
            ? []
            : [];
        state.compareQuotesList = [...data, ...manualArray];
        state.loading = false;
      } else {
        state.compareQuotesList = data;
        state.loading = false;
      }
    },

    setCompareQuoteFull: (state, { payload }) => {
      state.compareQuotesList = [...state.compareQuotesList, ...payload];
    },

    SetaddonsAndOthers: (state, { payload }) => {
      state.addOnsAndOthers = { ...state.addOnsAndOthers, ...payload };
    },

    setUpdateResponse: (state, { payload }) => {
      state.updateResponse = payload;
      state.loading = false;
      state.updateQuoteLoader = false;
    },

    setErrorIC: (state, { payload }) => {
      state.errorIcBased = _.uniqBy(
        _.compact([...state.errorIcBased, ...payload]),
        "errorTypeIc"
      );
    },

    setQuoteListLoading: (state, { payload }) => {
      state.quoteListLoading = payload;
    },

    setSaveQuoteLoader: (state, { payload }) => {
      state.saveQuoteLoader = payload;
    },

    setUpdateQuoteLoader: (state, { payload }) => {
      state.updateQuoteLoader = payload;
    },

    saveSelectedQuoteResponse: (state, { payload }) => {
      state.saveQuoteResponse = payload;
      state.saveQuoteLoader = false;
    },

    saveSelectedAddonResponse: (state, { payload }) => {
      state.saveAddonsResponse = payload;
    },

    setFinalPremiumList: (state, { payload }) => {
      state.finalPremiumlist = _.uniqBy(
        _.compact([...state.finalPremiumlist, ...payload]),
        "policyId"
      );
      state.loading = false;
    },

    setQuotesLoaded: (state, { payload }) => {
      if (payload === 0) {
        state.quotesLoaded = false;
      } else {
        state.quotesLoaded = state.quotesLoaded + payload;
      }
    },

    clear: (state, { payload }) => {
      state.loading = null;
      state.error = null;
      state.success = null;
      state.quotesList = [];
      state.quotetThirdParty = [];
      state.quoteComprehesive = [];
      state.quoteShortTerm = [];
      state.errorIcBased = [];
      state.finalPremiumlist = [];
      state.singleUpdatedQuote = false;
    },
    setGarage: (state, { payload }) => {
      state.loader = false;
      state.garage = payload;
    },
    setBuyNowSingleQuoteUpdate: (state, { payload }) => {
      state.buyNowSingleQuoteUpdate = payload;
    },
    SetSingleUpdatedQuote: (state, { payload }) => {
      state.singleUpdatedQuote = payload;
    },
    SetMultiUpdatedQuote: (state, { payload }) => {
      state.multiUpdatedQuote = _.uniqBy(
        _.compact([...state.multiUpdatedQuote, ...payload]),
        "policyId"
      );
    },

    setPremiumPdf: (state, { payload }) => {
      state.premiumPdf = payload;
    },
    setFinalPremiumList1: (state, { payload }) => {
      state.finalPremiumlist1 = _.uniqBy(
        _.compact([...state.finalPremiumlist1, ...payload]),
        "policyId"
      );
      state.loading = false;
    },
    clearFinalPremiumList: (state, { payload }) => {
      state.finalPremiumlist1 = [];
    },
    emailPdf: (state, { payload }) => {
      state.customLoad = null;
      state.emailPdf = payload;
    },
    setEmailComparePdf: (state, { payload }) => {
      state.customLoad = null;
      state.emailComparePdf = payload;
    },
    whatsapp: (state, { payload }) => {
      state.whatsapp = payload;
    },
    setEmailPdf: (state, { payload }) => {
      state.emailPdf = null;
    },
    customLoad: (state, { payload }) => {
      state.customLoad = payload ? true : false;
    },
    setVersionId: (state, { payload }) => {
      state.versionId = payload;
    },
    setComparePdfData: (state, { payload }) => {
      state.comparePdfData = payload;
    },
    SetLoadingCancelled: (state, { payload }) => {
      state.loadingCancelled = payload;
    },
    setLoadingFromPDf: (state, { payload }) => {
      state.loadingFromPdf = payload;
    },
    setSingleQuoteError: (state, { payload }) => {
      state.singleQuoteError = serializeError(payload);
    },
    clearSingleQuoteError: (state, { payload }) => {
      state.singleQuoteError = false;
    },
    saveQuoteError: (state, { payload }) => {
      state.saveQuoteError = payload;
    },
    clearSaveQuoteError: (state, { payload }) => {
      state.saveQuoteError = false;
    },
    setQuoteBundle: (state, { payload }) => {
      state.quoteBundle = payload;
    },
    shortTerm: (state, { payload }) => {
      state.shortTerm = payload;
    },
    selectedTab: (state, { payload }) => {
      state.selectedTab = payload;
    },
    setzdAvailablity: (state, { payload }) => {
      state.zdAvailablity = payload;
    },
    setShowPop: (state, { payload }) => {
      state.showPop = payload;
    },
    shortTermType: (state, { payload }) => {
      state.shortTermType = payload;
    },
    addonConfig: (state, { payload }) => {
      state.addonConfig = payload;
    },
    setaddonConfig: (state, { payload }) => {
      state.addonConfig = "false";
    },
    cpaSet: (state, { payload }) => {
      state.cpaSet = payload;
    },
    setValidQuotes: (state, { payload }) => {
      state.validQuote = payload;
    },
    setShowingZdlp: (state, { payload }) => {
      state.showingZdlp = payload;
    },
    interimLoader: (state, { payload }) => {
      state.interimLoading =
        _.isEmpty(current(state).quoteComprehesive) &&
        _.isEmpty(current(state).quotetThirdParty) &&
        _.isEmpty(current(state).quoteShortTerm) &&
        ((!current(state).quotesLoaded &&
          current(state).errorIcBased &&
          _.isEmpty(current(state).errorIcBased)) ||
          !(
            (current(state).quotesList?.third_party
              ? current(state).quotesList?.third_party?.length
              : 0) +
            (current(state).quotesList?.comprehensive
              ? current(state).quotesList?.comprehensive?.length
              : 0) +
            (current(state).quotesList?.short_term
              ? current(state).quotesList?.short_term?.length
              : 0)
          ) ||
          _.isEmpty(current(state).errorIcBased));
    },
    setShorlenUrl: (state, { payload }) => {
      state.getShorlenUrl = payload;
    },
    paydLoading: (state, { payload }) => {
      state.paydLoading = payload;
    },
  },
});

export const {
  loading,
  success,
  error,
  clear,
  addOnList,
  setQuotesList,
  setQuoteComprehensive,
  setQuoteThirdParty,
  setSelectedQuote,
  compareQuotes,
  SetvoluntaryList,
  SetaddonsAndOthers,
  setUpdateResponse,
  setErrorIC,
  setQuoteListLoading,
  saveSelectedQuoteResponse,
  saveSelectedAddonResponse,
  setFinalPremiumList,
  setFinalPremiumList1,
  setSaveQuoteLoader,
  setUpdateQuoteLoader,
  SetMasterLogoList,
  setQuotesLoaded,
  UpdateQuoteComprehensive,
  UpdateQuoteThirdParty,
  setGarage,
  setBuyNowSingleQuoteUpdate,
  SetSingleUpdatedQuote,
  setLoader,
  setPremiumPdf,
  clearFinalPremiumList,
  emailPdf,
  setEmailComparePdf,
  setEmailPdf,
  customLoad,
  setCompareQuoteFull,
  setQuoteShortTerm,
  setVersionId,
  setLoaderToFalse,
  setComparePdfData,
  SetMultiUpdatedQuote,
  whatsapp,
  SetLoadingCancelled,
  setLoadingFromPDf,
  setSingleQuoteError,
  clearSingleQuoteError,
  saveQuoteError,
  clearSaveQuoteError,
  setQuoteBundle,
  shortTerm,
  selectedTab,
  setzdAvailablity,
  setShowPop,
  shortTermType,
  addonConfig,
  setaddonConfig,
  cpaSet,
  setValidQuotes,
  setShowingZdlp,
  interimLoader,
  setShorlenUrl,
  setQuotePayD,
  paydLoading,
  updateQuoteShortTerm,
} = quoteSlice.actions;

export const AddOnList = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, addOnList, error, service.addOnList, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const setCompareData = (allData) => {
  return async (dispatch) => {
    try {
      const { data, message, errors, success } = await service.postCompareData(
        allData
      );
      if (data.data.data || success) {
        dispatch(compareQuotes(data.data.data || message));
      } else {
        dispatch(error(errors || message));
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const getCompareData = (getData) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      const { data, message, errors, success } = await service.fetchCompareData(
        getData
      );
      if (data.data.data || success) {
        dispatch(compareQuotes(data.data.data || message));
      } else {
        dispatch(error(errors || message));
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const VolunaryList = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        SetvoluntaryList,
        error,
        service.voluntaryList,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
export const MasterLogoList = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        SetMasterLogoList,
        error,
        service.masterLogoList,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const getQuotesData = (payload, shared) => {
  return async (dispatch) => {
    try {
      // reset dummy tile
      dispatch(CancelAll(true));
      dispatch(loading(true));
      dispatch(setQuoteListLoading(true));
      const { data, message, errors, success } = await service.getQuotes(
        payload
      );
      if (data?.data || success) {
        dispatch(CancelAll(false));
        //process data based on shared ICs
        if (shared && shared !== "true") {
          const filterQuotes = (quoteArr) => {
            return quoteArr.filter((quote) =>
              shared.split(",").includes(quote.companyAlias)
            );
          };
          let comprehensive = filterQuotes(data?.data?.comprehensive);
          let short_term = filterQuotes(data?.data?.short_term);
          let third_party = filterQuotes(data?.data?.third_party);
          dispatch(
            setQuotesList({ comprehensive, short_term, third_party } || message)
          );
        } else {
          dispatch(CancelAll(false));
          dispatch(setQuotesList(data?.data || message));
        }
      } else {
        dispatch(CancelAll(false));
        dispatch(error(errors || message));
      }
    } catch (err) {
      dispatch(CancelAll(false));
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

let ourRequest = axios.CancelToken.source();

export const CancelAll = (data) => {
  return async (dispatch) => {
    dispatch(setQuotesLoaded(0));
    if (data) {
      dispatch(SetLoadingCancelled(true));
      ourRequest.cancel();
    } else {
      dispatch(SetLoadingCancelled(false));
      ourRequest = axios.CancelToken.source();
    }
  };
};

export const getPremData = (ic, icId, data, typePolicy, quote, typeUrl) => {
  return async (dispatch) => {
    try {
      dispatch(interimLoader());
      let quotes = quote;
      const response = !data?.blockedMessage
        ? await getPremByIC(ic, data, typeUrl, ourRequest)
        : {
            status: false,
            message: switchError(data?.blockStatusCode, data?.blockedMessage),
          };
      if (
        response?.data?.data &&
        Array.isArray(response?.data?.data) &&
        import.meta.env.VITE_PROD !== "YES"
      ) {
        dispatch(setQuotesLoaded(1));
        let b = response?.data?.data?.map((item) => {
          let errorMessage = serializeError(item?.message);
          if (item?.data?.FTS_VERSION_ID) {
            dispatch(setVersionId(item?.data?.FTS_VERSION_ID));
          }

          if (item?.data) {
            quotes = [
              {
                company_alias: ic,
                companyId: icId,
                ...item?.data,
                commission: data?.commission,
                ...(data?.premiumTypeCode && {
                  premiumTypeCode: data?.premiumTypeCode,
                }),
              },
            ];
            if (typePolicy === "comprehensive") {
              dispatch(setQuoteComprehensive(quotes));
            } else if (typePolicy === "third_party") {
              dispatch(setQuoteThirdParty(quotes));
            } else if (typePolicy === "shortTerm") {
              dispatch(setQuoteShortTerm(quotes));
            }
          } else if (
            item?.status === "false" ||
            !item?.success ||
            !item?.status
          ) {
            let responseError = [
              {
                ic: ic,
                message: errorMessage,
                type: typePolicy,
                ...(data?.premiumTypeCode && {
                  premiumTypeCode: data?.premiumTypeCode,
                }),
                zeroDepError: item?.data?.zeroDep,
                errorTypeIc: `${ic}${typePolicy}${
                  item?.data?.zeroDep ? "ZD" : ""
                }`,
              },
            ];
            dispatch(setErrorIC(responseError));
          }
        });
      } else {
        let errorMessage = serializeError(response?.message);
        if (response?.data?.FTS_VERSION_ID) {
          dispatch(setVersionId(response?.data?.FTS_VERSION_ID));
        }

        if (response?.data?.data && response?.data?.status) {
          dispatch(setQuotesLoaded(1));
          quotes = [
            {
              company_alias: ic,
              companyId: icId,
              ...response?.data?.data,
              commission: data?.commission,
              ...(data?.premiumTypeCode && {
                premiumTypeCode: data?.premiumTypeCode,
              }),
            },
          ];
          if (typePolicy === "comprehensive") {
            dispatch(setQuoteComprehensive(quotes));
          } else if (typePolicy === "third_party") {
            dispatch(setQuoteThirdParty(quotes));
          } else if (typePolicy === "shortTerm") {
            dispatch(setQuoteShortTerm(quotes));
          }
        } else if (
          response?.status === "false" ||
          !response?.success ||
          !response?.status
        ) {
          dispatch(setQuotesLoaded(1));
          //set dummy tile
          if (response?.data?.data?.dummyTile) {
            if (typePolicy === "comprehensive") {
              dispatch(
                setQuoteComprehensive([
                  ...quotes,
                  {
                    ...DummyQuote("Comprehensive"),
                    companyAlias: ic,
                    companyLogo: response?.data?.data?.companyLogo,
                    dummyTile: "Y",
                    policyId: 234323,
                    redirection_url: response?.data?.data?.redirection_url,
                  },
                ])
              );
            } else if (typePolicy === "third_party") {
              dispatch(
                setQuoteThirdParty([
                  ...quotes,
                  {
                    ...DummyQuote("Third Party"),
                    companyAlias: ic,
                    companyLogo: response?.data?.data?.companyLogo,
                    dummyTile: "Y",
                    policyId: 234324,
                    redirection_url: response?.data?.data?.redirection_url,
                  },
                ])
              );
            } else if (typePolicy === "shortTerm") {
              dispatch(
                setQuoteShortTerm([
                  ...quotes,
                  {
                    ...DummyQuote("Short Term"),
                    companyAlias: ic,
                    companyLogo: response?.data?.data?.companyLogo,
                    dummyTile: "Y",
                    policyId: 234325,
                    redirection_url: response?.data?.data?.redirection_url,
                  },
                ])
              );
            }
          } else {
            let responseError = [
              {
                ic: ic,
                message: errorMessage,
                type: typePolicy,
                zeroDepError: response?.data?.zeroDep,
                ...(data?.premiumTypeCode && {
                  premiumTypeCode: data?.premiumTypeCode,
                }),
                errorTypeIc: `${ic}${typePolicy}${
                  response?.data?.zeroDep ? "ZD" : ""
                }`,
              },
            ];
            dispatch(setErrorIC(responseError));
          }
        }
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      dispatch(setQuotesLoaded(1));
    }
  };
};

export const getSingleUpdatedQuote = (ic, icId, data, typeUrl) => {
  return async (dispatch) => {
    try {
      const response = await getPremByIC(ic, data, typeUrl);
      if (response?.data?.data) {
        let quotesData = {
          company_alias: ic,
          companyId: icId,
          ...response?.data?.data,
          commission: data?.commission,
        };

        dispatch(SetSingleUpdatedQuote(quotesData));
      } else {
        if (
          response?.status === "false" ||
          !response?.success ||
          !response?.status
        )
          dispatch(
            setSingleQuoteError(
              response?.data?.error ||
                response?.data?.errors ||
                response?.data?.message ||
                "Something went wrong"
            )
          );
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      dispatch(setSingleQuoteError("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//This will override the existing pay as you drive quote.
export const getPayAsYourDrive = (
  ic,
  icId,
  data,
  typePolicy,
  quote,
  typeUrl
) => {
  return async (dispatch) => {
    try {
      dispatch(paydLoading(ic));
      let quotes = quote;
      const response = await getPremByIC(ic, data, typeUrl);

      if (
        response?.data &&
        Array.isArray(response?.data) &&
        import.meta.env.VITE_PROD !== "YES"
      ) {
        let b = response?.data?.map((item) => {
          let errorMessage = serializeError(item?.message);
          if (item?.data?.FTS_VERSION_ID) {
            dispatch(setVersionId(item?.data?.FTS_VERSION_ID));
          }

          if (item?.data) {
            quotes = [
              {
                company_alias: ic,
                companyId: icId,
                ...item?.data,
                commission: data?.commission,
                ...(data?.premiumTypeCode && {
                  premiumTypeCode: data?.premiumTypeCode,
                }),
              },
            ];
            if (typePolicy === "comprehensive") {
              dispatch(setQuotePayD(quotes));
              dispatch(paydLoading(false));
            }
            dispatch(paydLoading(false));
          } else if (
            item?.status === "false" ||
            !item?.success ||
            !item?.status
          ) {
            let responseError = [
              {
                ic: ic,
                message: errorMessage,
                type: typePolicy,
                zeroDepError: item?.data?.zeroDep,
                ...(data?.premiumTypeCode && {
                  premiumTypeCode: data?.premiumTypeCode,
                }),
                errorTypeIc: `${ic}${typePolicy}${
                  item?.data?.zeroDep ? "ZD" : ""
                }`,
              },
            ];

            dispatch(paydLoading(false));
            dispatch(setErrorIC(responseError));
          }
        });
      } else {
        let item = response?.data;
        let errorMessage = serializeError(item?.message);
        if (item?.data?.FTS_VERSION_ID) {
          dispatch(setVersionId(item?.data?.FTS_VERSION_ID));
        }

        if (item?.data) {
          quotes = [
            {
              company_alias: ic,
              companyId: icId,
              ...item?.data,
              commission: data?.commission,
              ...(data?.premiumTypeCode && {
                premiumTypeCode: data?.premiumTypeCode,
              }),
            },
          ];
          if (typePolicy === "comprehensive") {
            dispatch(setQuotePayD(quotes));
            dispatch(paydLoading(false));
          }
          dispatch(paydLoading(false));
        } else if (
          item?.status === "false" ||
          !item?.success ||
          !item?.status
        ) {
          let responseError = [
            {
              ic: ic,
              message: errorMessage,
              type: typePolicy,
              zeroDepError: item?.data?.zeroDep,
              ...(data?.premiumTypeCode && {
                premiumTypeCode: data?.premiumTypeCode,
              }),
              errorTypeIc: `${ic}${typePolicy}${
                item?.data?.zeroDep ? "ZD" : ""
              }`,
            },
          ];

          dispatch(paydLoading(false));
          dispatch(setErrorIC(responseError));
        }
      }
      dispatch(paydLoading(false));
    } catch (err) {
      dispatch(paydLoading(false));
      dispatch(error("Something went wrong"));
    }
  };
};

export const getMultiUpdatedQuote = (ic, icId, data, typeUrl) => {
  return async (dispatch) => {
    try {
      const response = await getPremByIC(ic, data, typeUrl);
      if (response?.data?.data) {
        let quotes = [
          {
            company_alias: ic,
            companyId: icId,
            ...response?.data?.data,
            commission: data?.commission,
          },
        ];

        dispatch(SetMultiUpdatedQuote(quotes));
      } else {
        if (
          response?.status === "false" ||
          !response?.success ||
          !response?.status
        )
          dispatch(
            setSingleQuoteError(
              response?.data?.error ||
                response?.data?.errors ||
                response?.data?.message ||
                "Something went wrong"
            )
          );
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const UpdateQuotesData = (data, skipLoad) => {
  return async (dispatch) => {
    try {
      skipLoad !== "Y" && dispatch(setUpdateQuoteLoader(true));

      const response = await updateQuote(data);
      if (response?.data?.status && skipLoad !== "Y") {
        dispatch(setUpdateResponse(response?.data?.status));
        // dispatch(setQuotesList([]));
        // dispatch(clear());
      } else {
        skipLoad !== "Y" && dispatch(setUpdateQuoteLoader(false));
      }
    } catch (err) {
      skipLoad !== "Y" && dispatch(setUpdateQuoteLoader(false));
      skipLoad !== "Y" && dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const SaveQuotesData = (data) => {
  return async (dispatch) => {
    try {
      dispatch(setSaveQuoteLoader(true));
      const response = await saveSelectedQuote(data);
      let errorMessage = serializeError(response?.message);
      dispatch(setSaveQuoteLoader(false));
      if (response?.data?.status) {
        dispatch(saveSelectedQuoteResponse(response?.data?.status));
      } else if (!response?.data?.status) {
        if (
          errorMessage === "Payment Link Already Generated..!" ||
          errorMessage === "Transaction Already Completed" ||
          errorMessage === "Payment Initiated"
        ) {
          dispatch(saveQuoteError(errorMessage));
        }
        errorMessage !== "Transaction Already Completed" &&
          swal(
            "Error",
            data?.enquiryId
              ? `${`Trace ID:- ${
                  data?.traceId ? data?.traceId : data?.enquiryId
                }.\n Error Message:- ${errorMessage}`}`
              : errorMessage,
            "error"
          );
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
      swal(
        "Error",
        data?.enquiryId
          ? `${`Trace ID:- ${
              data?.traceId ? data?.traceId : data?.enquiryId
            }.\n Error Message:- ${err}`}`
          : err,
        "error"
      );
    }
  };
};

export const SaveAddonsData = (data, proposal, setters) => {
  let { setCpaFetch, setOnCpaChange } = setters || {};
  return async (dispatch) => {
    data?.onCpaChange && dispatch(setUpdateQuoteLoader(true));
    data?.onCpaChange && dispatch(CancelAll(true));
    try {
      //	dispatch(loading(true));
      const response = await saveSelectedAddons({
        ...data,
        isDefaultCoverChanged: "Y",
      });

      if (response?.data?.data?.lastProposalModifiedTime) {
        dispatch(updateModifiedTime(response?.data?.data));
      }
      if (response?.data?.status) {
        dispatch(saveSelectedAddonResponse(response?.data?.status));
        data?.onCpaChange && dispatch(CancelAll(false));
        data?.onCpaChange && setCpaFetch((prev) => prev + 1);
        data?.onCpaChange && setOnCpaChange(false);
        data?.onCpaChange && dispatch(setUpdateQuoteLoader(false));
        proposal && dispatch(cpaSet(response?.data?.status));
      } else {
        data?.onCpaChange && dispatch(CancelAll(false));
        proposal && dispatch(cpaSet(false));
        data?.onCpaChange && setOnCpaChange(false);
        dispatch(setUpdateQuoteLoader(false));
      }
    } catch (err) {
      data?.onCpaChange && dispatch(CancelAll(false));
      data?.onCpaChange && setOnCpaChange(false);
      dispatch(error("Something went wrong"));
      proposal && dispatch(cpaSet(false));
      dispatch(setUpdateQuoteLoader(false));
      console.error("Error", err);
    }
  };
};

export const GarageList = (allData) => {
  return async (dispatch) => {
    try {
      dispatch(setLoader());
      const { data, message, errors, success } = await service.garage(allData);
      if (data.data || success) {
        dispatch(setGarage(data.data || message));
      } else {
        dispatch(setLoaderToFalse());
        dispatch(error(errors || message));
        console.error("Error", errors || message);
      }
    } catch (err) {
      dispatch(setLoaderToFalse());
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const getPremPdf = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, setPremiumPdf, error, service.premPdf, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const EmailPdf = (data) => {
  return async (dispatch) => {
    try {
      dispatch(customLoad(true));
      actionStructre(dispatch, emailPdf, error, service.emailPdf, data);
    } catch (err) {
      dispatch(customLoad(false));
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const EmailComparePdf = (data) => {
  return async (dispatch) => {
    try {
      dispatch(customLoad(true));
      actionStructre(
        dispatch,
        setEmailComparePdf,
        error,
        service.emailComparePdf,
        data
      );
    } catch (err) {
      dispatch(customLoad(false));
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const DownloadPremiumBreakup = (data, isMobile) => {
  //Mobile app - Download pdf / RB
  import.meta.env.VITE_BROKER === "RB" &&
    isMobile &&
    window?.ReactNativeWebView &&
    window.ReactNativeWebView.postMessage(
      `${import.meta.env.VITE_API_BASE_URL}/premiumBreakupPdf`,
      data
    );
  return async (dispatch) => {
    try {
      const response = await service.downloadPremiumBreakup(data, true);
      if (response?.data?.data) {
        let a = document.createElement("a");
        a.href = "data:application/octet-stream;base64," + response?.data?.data;
        import.meta.env.VITE_BROKER === "GRAM" &&
          window &&
          window.Android &&
          window.Android.downloadFile(
            "data:application/octet-stream;base64," + response?.data?.data
          );
        //Mobile app - Download pdf / RB
        import.meta.env.VITE_BROKER === "RB" &&
          isMobile &&
          window?.ReactNativeWebView &&
          window.ReactNativeWebView.postMessage(
            JSON.stringify([
              "premium_pdf",
              `${
                "data:application/octet-stream;base64," + response?.data?.data
              }`,
            ])
          );
        a.download = `${moment().format("DD-MM-YYYY")} premium Breakup.pdf`;
        a.click();
      } else {
        swal("Something went wrong in pdf generation");
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      swal("Something went wrong in pdf generation");
      console.error("Error", err);
    }
  };
};

export const Whatsapp = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        whatsapp,
        error,
        service.whatsappNotification,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const AddonConfig = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        addonConfig,
        setaddonConfig,
        service.addonConfig,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      dispatch(setaddonConfig("false"));
      console.error("Error", err);
    }
  };
};

export const ShortlenUrl = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        setShorlenUrl,
        error,
        service.getShorlenUrl,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export default quoteSlice.reducer;
