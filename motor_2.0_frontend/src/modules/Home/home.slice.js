import { createSlice } from "@reduxjs/toolkit";
import service from "./serviceApi";
import {
  actionStructre,
  serializeError,
  actionStructreBoth,
  toDate,
  DataDecrypt,
  reloadPage,
} from "utils";
import moment from "moment";
import _ from "lodash";
import { differenceInDays } from "date-fns";

export const homeSlice = createSlice({
  name: "home",
  initialState: {
    loading: false,
    error: null,
    success: null,
    enquiry_id: null,
    type: [],
    vehicleType: [],
    brandType: [],
    modelType: [],
    temp_data: {},
    rto: [],
    variant: [],
    prefill: {},
    saveQuoteData: null,
    saveQuoteData1: null,
    prefillLoading: false,
    tokenFailure: null,
    tokenData: [],
    category: [],
    share: null,
    theme_conf: {},
    theme_conf_error: null,
    theme_conf_success: null,
    getFuel: [],
    fastLaneData: false,
    fastLaneRenewalData: false,
    fueldelay: null,
    stepperLoad: null,
    leadPg: null,
    regPg: null,
    typePg: null,
    stepper1: null,
    stepper2: null,
    stepper3: null,
    fuelCheck: [],
    rtoCities: [],
    rtoCitiesInfo: [],
    rd_link: null,
    leadLoad: null,
    validationConfig: {},
    validationConfigPost: {},
    isRedirectionDone: "N",
    isRenewalRedirection: "N",
    prefillPolicyNumber: null,
    icList: [],
    frontendurl: null,
    tabClick: null,
    exp_error: null,
    delivery: null,
    gstStatus: null,
    ndslUrl: null,
    faq: null,
    faqPost: null,
    errorSpecific: null,
    configTab: "theme",
    error_show: "",
    overrideMsg: "",
    communicationPreference: [],
    feedback: [],
    encryptUser: null,
    tokenLoad: null,
    tokenStatus: null,
    resumeJourney: {},
  },
  reducers: {
    tokenStatus: (state, { payload }) => {
      state.tokenStatus = payload;
    },
    loading: (state) => {
      state.loading = true;
      state.error = null;
      state.success = null;
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
      state.stepperLoad = null;
      state.stepper1 = null;
      state.stepper2 = null;
      state.leadLoad = null;
      state.stepper3 = null;
    },
    errorSpecific: (state, { payload }) => {
      state.errorSpecific = serializeError(payload);
    },
    clear: (state, { payload }) => {
      state.loading = null;
      state.error = null;
      state.errorSpecific = null;
      state.success = null;
      state.stepper1 = null;
      switch (payload) {
        case "enquiry_id":
          state.enquiry_id = null;
          break;
        case "saveQuoteData":
          state.saveQuoteData = null;
          // state.leadLoad = null;
          // state.overrideMsg = "";
          break;
        case "saveQuoteData1":
          state.saveQuoteData1 = null;
          break;
        case "share":
          state.share = null;
          break;
        case "token":
          state.tokenData = null;
          state.tokenFailure = null;
          break;
        case "fuelCheck":
          state.fuelCheck = [];
          state.stepper2 = null;
          break;
        default:
          break;
      }
    },
    enquiry_id: (state, { payload }) => {
      state.loading = null;
      state.enquiry_id = payload;
      state.leadLoad = true;
    },
    type: (state, { payload }) => {
      state.loading = null;
      state.type = payload;
    },
    vehicleType: (state, { payload }) => {
      state.loading = null;
      state.vehicleType = payload;
    },
    brandType: (state, { payload }) => {
      state.loading = null;
      state.brandType = payload;
    },
    modelType: (state, { payload }) => {
      state.loading = null;
      state.modelType = payload;
      state.stepper3 = false;
    },
    set_temp_data: (state, { payload }) => {
      state.temp_data = { ...state.temp_data, ...payload };
    },
    // clear_temp_data: (state) => {
    //   state.temp_data = { };
    // },
    rto: (state, { payload }) => {
      state.loading = null;
      state.rto = payload?.allRtoData ? payload?.allRtoData : [];
      state.rtoCities = !_.isEmpty(payload?.city)
        ? payload?.city?.map((x) => x.toLowerCase())
        : [];
      state.rtoCitiesInfo = payload?.cityRto ? payload?.cityRto : [];
    },
    variant: (state, { payload }) => {
      state.loading = null;
      state.variant = payload;
    },
    prefill: (state, { payload }) => {
      state.loading = null;
      state.prefillLoading = null;
      state.prefill = payload;
      state.stepperLoad = null;
      let prefillData = {
        //login (corpId)
        corpId: payload?.corpId,
        userId: payload?.userId,
        //lead-page
        firstName: payload?.userFname,
        lastName: payload?.userLname,
        emailId: payload?.userEmail,
        mobileNo: payload?.userMobile,
        whatsappNo:
          payload?.userWhatsappNo ||
          payload?.corporateVehiclesQuoteRequest?.userWhatsappNo,
        //registration-page
        journeyType:
          payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
          payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo !==
            "NEW"
            ? 1
            : payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo ===
                "NEW" ||
              (payload?.corporateVehiclesQuoteRequest?.vehicleRegisterDate &&
                differenceInDays(
                  toDate(
                    payload?.corporateVehiclesQuoteRequest?.vehicleRegisterDate
                  ),
                  toDate(moment().format("DD-MM-YYYY"))
                ) >= 0)
            ? 3
            : 2,
        regNo: payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo,
        ...(payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
          !(
            payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo[0] * 1
          ) && {
            regNo1:
              payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
              payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo !==
                "NEW"
                ? `${
                    payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo?.split(
                      "-"
                    )[0]
                  }-${
                    payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo?.split(
                      "-"
                    )[1]
                  }`
                : "",
            regNo2:
              payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
              payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo !==
                "NEW"
                ? `${
                    _.compact(
                      payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo?.split(
                        "-"
                      )
                    )?.length === 4
                      ? payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo?.split(
                          "-"
                        )[2]
                      : ""
                  }`
                : "",
            regNo3:
              payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
              payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo !==
                "NEW"
                ? `${
                    _.compact(
                      payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo?.split(
                        "-"
                      )
                    )?.length === 4
                      ? payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo?.split(
                          "-"
                        )[3]
                      : _.compact(
                          payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo?.split(
                            "-"
                          )
                        )[2]
                  }`
                : "",
          }),
        //vehicle-type
        productSubTypeId: payload?.productSubTypeId,
        productSubTypeCode: payload?.subProduct?.productSubTypeCode,
        gcvCarrierType: payload?.corporateVehiclesQuoteRequest?.gcvCarrierType,
        parent: payload?.subProduct?.parent,
        //vehicle-details
        //brand
        manfId: payload?.quoteLog?.quoteDetails?.manfactureId,
        manfName: payload?.quoteLog?.quoteDetails?.manfactureName,
        //model
        modelId:
          payload?.corporateVehiclesQuoteRequest?.model ||
          payload?.quoteLog?.quoteDetails?.model,
        modelName:
          payload?.corporateVehiclesQuoteRequest?.modelName ||
          payload?.quoteLog?.quoteDetails?.modelName,
        //fuel-type
        fuel:
          payload?.corporateVehiclesQuoteRequest?.fuelType ||
          payload?.quoteLog?.quoteDetails?.fuelType,
        kit_val:
          payload?.corporateVehiclesQuoteRequest?.vehicleLpgCngKitValue ||
          payload?.quoteLog?.quoteDetails?.vehicleLpgCngKitValue,
        kit: payload?.quoteLog?.quoteDetails?.vehicleLpgCngKitValue ? 1 : 0,
        //variant
        versionId:
          payload?.corporateVehiclesQuoteRequest?.versionId ||
          payload?.quoteLog?.quoteDetails?.version,
        versionName:
          payload?.corporateVehiclesQuoteRequest?.versionName ||
          payload?.quoteLog?.quoteDetails?.versionName,
        selectedGvw: payload?.corporateVehiclesQuoteRequest?.selectedGvw,
        defaultGvw: payload?.corporateVehiclesQuoteRequest?.defaultGvw,
        seatingCapacity: payload?.quoteLog?.quoteDetails?.seatingCapacity * 1,
        //rto
        rtoNumber:
          payload?.corporateVehiclesQuoteRequest?.rtoCode ||
          payload?.quoteLog?.quoteDetails?.rto ||
          payload?.quoteLog?.quoteDetails?.rtoCode,
        //year
        regDate:
          payload?.corporateVehiclesQuoteRequest?.vehicleRegisterDate ||
          payload?.quoteLog?.quoteDetails?.vehicleRegisterDate,
        manfDate:
          payload?.corporateVehiclesQuoteRequest?.manufactureYear ||
          payload?.quoteLog?.quoteDetails?.manufactureYear,
        //journey-type
        ownerTypeId:
          payload?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "I"
            ? 1
            : payload?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
            ? 2
            : null,
        //quoteData
        ncb: payload?.corporateVehiclesQuoteRequest?.previousNcb
          ? payload?.corporateVehiclesQuoteRequest?.previousNcb + "%"
          : 0,
        newNcb: payload?.corporateVehiclesQuoteRequest?.applicableNcb
          ? payload?.corporateVehiclesQuoteRequest?.applicableNcb + "%"
          : 0,
        prevIc: payload?.corporateVehiclesQuoteRequest?.previousInsurerCode,
        prevIcFullName: payload?.corporateVehiclesQuoteRequest?.previousInsurer,
        expiry:
          payload?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate,
        currentPolicyType: payload?.corporateVehiclesQuoteRequest?.businessType,
        noClaimMade:
          payload?.corporateVehiclesQuoteRequest?.isClaim === "Y"
            ? false
            : true,
        policyType: payload?.corporateVehiclesQuoteRequest?.previousPolicyType,
        leadJourneyEnd: payload?.leadStageId >= 2 ? true : false,
        vehicleLpgCngKitValue:
          payload?.quoteLog?.quoteDetails?.vehicleLpgCngKitValue || 0,
        vehicleIdv: payload?.corporateVehiclesQuoteRequest?.editIdv,
        isIdvChanged:
          payload?.corporateVehiclesQuoteRequest?.isIdvChanged === "Y"
            ? true
            : false,
        isOdDiscountApplicable:
          payload?.corporateVehiclesQuoteRequest?.isOdDiscountApplicable === "Y"
            ? true
            : false,
        vehicleIdvType:
          payload?.corporateVehiclesQuoteRequest?.idvChangedType || "avgIdv",
        addons: !_.isEmpty(payload?.addons) ? payload?.addons[0] : [],

        carOwnership:
          payload?.corporateVehiclesQuoteRequest?.ownershipChanged === "Y"
            ? true
            : false,

        newCar:
          payload?.corporateVehiclesQuoteRequest?.businessType ===
          "newbusiness",

        breakIn:
          payload?.corporateVehiclesQuoteRequest?.businessType === "breakin",

        // journeyType
        journeyCategory: payload?.subProduct?.parent?.productSubTypeCode,
        //parent category
        journeySubCategory: payload?.subProduct?.productSubTypeCode,
        //journey Stage
        journeyStage: payload?.journeyStage,

        //fastlane status
        fastlaneJourney:
          payload?.corporateVehiclesQuoteRequest?.isFastlane == "Y"
            ? true
            : false,
        fastlaneNcbPopup:
          payload?.corporateVehiclesQuoteRequest?.isPopupShown == "N" &&
          (payload?.corporateVehiclesQuoteRequest?.isFastlane == "Y" ||
            payload?.corporateVehiclesQuoteRequest?.journeyType == "fastlane")
            ? // ||
              // (payload?.corporateVehiclesQuoteRequest?.journeyType == "ongrid" && import.meta.env.VITE_BROKER !== 'OLA')
              true
            : false,
        odOnly:
          payload?.corporateVehiclesQuoteRequest?.policyType == "own_damage"
            ? true
            : false,

        isNcbVerified:
          payload?.corporateVehiclesQuoteRequest?.isNcbVerified === "Y"
            ? "Y"
            : "N",
        prevShortTerm:
          payload?.corporateVehiclesQuoteRequest?.prevShortTerm * 1,
        //proposal
        userProposal: payload?.userProposal,
        traceId: payload?.traceId,
        //agent details
        agentDetails: payload?.agentDetails,
        rtoCity: payload.corporateVehiclesQuoteRequest?.rtoCity,
        corporateVehiclesQuoteRequest: payload?.corporateVehiclesQuoteRequest,
        quoteLog: payload?.quoteLog,
        isClaim: payload?.isClaim?.corporateVehiclesQuoteRequest,
        isClaimVerified:
          payload?.corporateVehiclesQuoteRequest?.isClaimVerified,
        isToastShown: payload?.corporateVehiclesQuoteRequest?.isToastShown,
        isRedirectionDone:
          payload?.corporateVehiclesQuoteRequest?.isRedirectionDone,
        isRenewalRedirection:
          payload?.corporateVehiclesQuoteRequest?.isRenewalRedirection,
        prefillPolicyNumber:
          payload?.corporateVehiclesQuoteRequest?.prefillPolicyNumber,
        isNcbConfirmed: payload?.corporateVehiclesQuoteRequest?.isNcbConfirmed,
        infoToaster: payload?.corporateVehiclesQuoteRequest?.infoToaster,
        previousPolicyTypeIdentifier:
          payload?.corporateVehiclesQuoteRequest?.previousPolicyTypeIdentifier,
        isMultiYearPolicy:
          payload?.corporateVehiclesQuoteRequest?.isMultiYearPolicy,
        previousPolicyTypeIdentifierCode:
          payload?.corporateVehiclesQuoteRequest
            ?.previousPolicyTypeIdentifierCode,
        renewalRegistration:
          payload?.corporateVehiclesQuoteRequest?.renewalRegistration,
        discounts: payload?.discounts,
        selectedDiscount:
          !_.isEmpty(payload?.addons) &&
          payload?.addons[0]?.agentDiscount?.selected * 1,
        isRenewalUpload: payload?.isRenewalUpload,
        isNcbEditable: payload?.isNcbEditable,
        leadSource: payload?.leadSource,
        renewalAttributes: payload?.renewalAttributes,
        oldJourneyData: payload?.oldJourneyData,
        vahaanService:
          payload?.corporateVehiclesQuoteRequest?.journeyType &&
          !["embeded-excel"].includes(
            payload?.corporateVehiclesQuoteRequest?.journeyType
          ) &&
          payload?.corporateVehiclesQuoteRequest?.journeyType,
        blockBackButton:
          payload?.additionalDetails?.additionalData?.blockBackButton,
        journeyId: payload?.journeyId,
        subProduct: payload?.subProduct,
        proposalExtraFields: payload?.proposalExtraFields,
        //invoice-date
        vehicleInvoiceDate:
          payload?.corporateVehiclesQuoteRequest?.vehicleInvoiceDate ||
          payload?.corporateVehiclesQuoteRequest?.vehicleRegisterDate,
        Source_IP: payload?.Source_IP,
        isPopupShown: payload?.corporateVehiclesQuoteRequest?.isPopupShown,
      };
      state.temp_data = { ...state.temp_data, ...prefillData };
      state.isRedirectionDone =
        payload?.corporateVehiclesQuoteRequest?.isRedirectionDone === "Y"
          ? "Y"
          : "N";
    },
    saveQuoteData: (state, { payload }) => {
      state.saveQuoteData = payload;
      state.leadLoad = null;
    },
    saveQuoteData1: (state, { payload }) => {
      state.saveQuoteData1 = payload;
      state.leadLoad = null;
    },
    setPrefillLoading: (state) => {
      state.prefillLoading = true;
      state.error = null;
      state.success = null;
    },
    tokenData: (state, { payload }) => {
      state.tokenData = !_.isEmpty(payload) ? payload : {};
      let agentData = !_.isEmpty(payload)
        ? [_.mapKeys(payload, (value, key) => _.camelCase(key))]
        : [];

      var keyMap = {
        sellerName: "agentName",
        sellerId: "agentId",
      };

      var agentDataRestructured = agentData.map(function (obj) {
        return _.mapKeys(obj, function (value, key) {
          return keyMap[key];
        });
      });

      state.temp_data = {
        ...state.temp_data,
        agentDetails: agentDataRestructured,
      };
    },
    tokenFailure: (state, { payload }) => {
      state.tokenFailure = payload;
    },
    category: (state, { payload }) => {
      state.category = payload;
    },
    share: (state, { payload }) => {
      state.share = payload;
    },
    theme_conf_error: (state, { payload }) => {
      state.theme_conf_error = payload;
      state.theme_conf_success = false;
    },
    theme_conf_success: (state, { payload }) => {
      state.theme_conf_success = payload;
    },
    theme_conf: (state, { payload }) => {
      state.theme_conf = { ...state.theme_conf, ...payload };
      state.theme_conf_success = true;
    },
    getFuel: (state, { payload }) => {
      state.getFuel = payload;
      state.fueldelay = false;
    },
    setFastLane: (state, { payload }) => {
      state.fastLaneData = payload;
    },
    setFastLaneRenewal: (state, { payload }) => {
      state.fastLaneRenewalData = payload;
    },
    vahaanConfig: (state, { payload }) => {
      const decryptedData = DataDecrypt(payload); // Decrypt the payload
      state.vahaanConfig = JSON.parse(decryptedData);
    },
    fueldelay: (state, { payload }) => {
      state.fueldelay = true;
    },
    stepperLoad: (state, { payload }) => {
      if (payload) {
        state.stepperLoad = null;
      } else {
        state.stepperLoad = true;
      }
    },
    error_fastlane: (state, { payload }) => {
      if (payload?.showMessage) {
        state.fastLaneData = { showMessage: payload?.showMessage, status: 101 };
      } else {
        state.fastLaneData = { status: 101 };
      }
    },

    error_fastlane_renewal: (state, { payload }) => {
      state.fastLaneRenewalData = { status: 101 };
    },
    loadStep: (state, { payload }) => {
      state.stepper1 = true;
    },
    cancelLoad: (state, { payload }) => {
      state.stepper1 = null;
      state.stepper2 = null;
      state.stepper3 = null;
    },
    fuelCheck: (state, { payload }) => {
      state.fuelCheck = payload;
      state.getFuel = payload;
      state.stepper1 = null;
      state.stepper2 = null;
    },
    loadStep2: (state, { payload }) => {
      state.stepper2 = true;
    },
    rd_link: (state, { payload }) => {
      state.rd_link = payload;
    },
    leadLoad: (state, { payload }) => {
      state.leadLoad = true;
    },
    cancelLeadLoad: (state, { payload }) => {
      state.leadLoad = false;
    },
    loadStep3: (state, { payload }) => {
      state.stepper3 = true;
    },
    validationConfig: (state, { payload }) => {
      state.validationConfig = payload;
    },
    validationConfigPost: (state, { payload }) => {
      state.validationConfigPost = payload;
    },
    setRedirectionFlag: (state, { payload }) => {
      state.isRedirectionDone = "Y";
    },
    icList: (state, { payload }) => {
      state.icList = payload;
    },
    frontendurl: (state, { payload }) => {
      state.frontendurl = payload;
    },
    tabClick: (state, { payload }) => {
      state.tabClick = payload;
    },
    exp_error: (state, { payload }) => {
      state.exp_error = payload;
    },
    delivery: (state, { payload }) => {
      state.delivery = payload;
    },
    gstStatus: (state, { payload }) => {
      state.gstStatus = payload;
    },
    ndslUrl: (state, { payload }) => {
      state.ndslUrl = payload;
    },
    faq: (state, { payload }) => {
      state.faq = payload;
    },
    faqPost: (state, { payload }) => {
      state.faqPost = payload;
    },
    configTab: (state, { payload }) => {
      state.configTab = payload;
    },
    error_show: (state, { payload }) => {
      state.error_show = payload;
    },
    overrideMsg: (state, { payload }) => {
      state.overrideMsg = payload;
    },
    communicationPreference: (state, { payload }) => {
      state.communicationPreference = payload;
    },
    feedback: (state, { payload }) => {
      state.feedback = payload;
    },
    encryptUser: (state, { payload }) => {
      state.encryptUser = payload;
    },
    encryptError: (state, { payload }) => {
      // state.encryptUser = payload;
    },
    tokenLoad: (state, { payload }) => {
      state.tokenLoad = payload;
    },

    resumeJourney: (state, { payload }) => {
      state.loading = null;
      state.resumeJourney = payload;
    },
  },
});

export const {
  loading,
  success,
  error,
  clear,
  type,
  vehicleType,
  brandType,
  modelType,
  set_temp_data,
  rto,
  enquiry_id,
  variant,
  prefill,
  saveQuoteData,
  saveQuoteData1,
  setPrefillLoading,
  tokenStatus,
  tokenFailure,
  tokenData,
  category,
  share,
  theme_conf,
  theme_conf_error,
  theme_conf_success,
  getFuel,
  setFastLane,
  setFastLaneRenewal,
  vahaanConfig,
  fueldelay,
  stepperLoad,
  error_fastlane,
  error_fastlane_renewal,
  loadStep,
  Lead,
  fuelCheck,
  loadStep2,
  rd_link,
  leadLoad,
  cancelLeadLoad,
  cancelLoad,
  loadStep3,
  validationConfig,
  validationConfigPost,
  setRedirectionFlag,
  setRenewalRedirectionFlag,
  icList,
  frontendurl,
  tabClick,
  exp_error,
  delivery,
  gstStatus,
  Live_Status,
  ndslUrl,
  faq,
  faqPost,
  errorSpecific,
  configTab,
  error_show,
  overrideMsg,
  communicationPreference,
  feedback,
  encryptUser,
  encryptError,
  tokenLoad,
  clear_temp_data,
  resumeJourney,
} = homeSlice.actions;

export const Enquiry = (data, loadCheck) => {
  return async (dispatch) => {
    try {
      dispatch(leadLoad());
      loadCheck && dispatch(loading());
      actionStructre(
        dispatch,
        enquiry_id,
        error,
        service.enquiry,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(cancelLeadLoad());
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const Type = (data) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      actionStructre(dispatch, type, error, service.type, data, errorSpecific);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const VehicleType = (data) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      actionStructre(
        dispatch,
        vehicleType,
        error,
        service.vehicleType,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const BrandType = (data, exception) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      actionStructre(
        dispatch,
        brandType,
        exception ? exp_error : error,
        service.brandType,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const ModelType = (data, loadCheck, exception) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      loadCheck && dispatch(loadStep3());
      actionStructre(
        dispatch,
        modelType,
        exception ? exp_error : error,
        service.modelType,
        data,
        errorSpecific
      );
    } catch (err) {
      loadCheck && dispatch(cancelLoad());
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const Rto = (data, exception) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      actionStructre(
        dispatch,
        rto,
        exception ? exp_error : error,
        service.rto,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const Variant = (data, exception) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      actionStructre(
        dispatch,
        variant,
        exception ? exp_error : error,
        service.variantType,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
//token validation for payment success direction
export const TokenStatus = (data) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      actionStructre(
        dispatch,
        tokenStatus,
        error,
        service.tokenValidate,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Prefill
export const Prefill = (data, check, noloading) => {
  return async (dispatch) => {
    try {
      if (check && !noloading) {
        dispatch(stepperLoad());
      } else {
        !noloading && dispatch(loading());
        !noloading && dispatch(setPrefillLoading());
      }
      actionStructre(
        dispatch,
        prefill,
        error,
        service.prefill,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      dispatch(stepperLoad("cancel"));
      console.error("Error", err);
    }
  };
};

//stepSave
export const SaveQuoteData = (data, loadingType, renew) => {
  return async (dispatch) => {
    try {
      !loadingType && dispatch(loadStep());
      loadingType && dispatch(loadStep2());
      actionStructre(
        dispatch,
        renew ? saveQuoteData1 : saveQuoteData,
        error,
        service.save,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(cancelLoad());
      dispatch(cancelLeadLoad());
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//callUs
export const CallUs = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        success,
        error,
        service.callUs,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//shareQuote
export const ShareQuote = (data, check) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        check ? share : success,
        check ? error_show : error,
        service.shareQuote,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//callUs
export const TokenValidation = (payload) => {
  return async (dispatch) => {
    try {
      dispatch(tokenLoad(true));
      const { data, message, errors, success, raw_response } =
        await service.tokenVal(payload);
      if (data?.data || success) {
        dispatch(tokenData(data?.data || message));
        dispatch(tokenLoad(false));
      } else {
        dispatch(tokenLoad(false));
        dispatch(rd_link(raw_response?.data?.redirection_link || ""));
        dispatch(tokenFailure(errors || message));
        console.error("Error", errors || message);
      }
    } catch (err) {
      dispatch(tokenLoad(false));
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//shareQuote
export const Category = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        category,
        error,
        service.subType,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//shareQuote
export const ThemeConf = (Broker, payload) => {
  return async (dispatch) => {
    try {
      dispatch(theme_conf_success(false));
      const {
        data,
        message,
        errors,
        success: s,
      } = await service.themeService(Broker, payload);
      if (data?.data || s) {
        dispatch(theme_conf(data?.data || message));
      } else {
        dispatch(theme_conf_error(errors || message));
        console.error("Error", errors || message);
      }
    } catch (err) {
      dispatch(theme_conf_error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
export const ThemeConfPost = (payload, Broker) => {
  return async (dispatch) => {
    try {
      dispatch(theme_conf_success(false));
      const {
        data,
        message,
        errors,
        success: s,
      } = await service.themeServicePost(payload, Broker);
      if (data?.data || s) {
        dispatch(theme_conf(data?.data || message));
      } else {
        dispatch(theme_conf_error(errors || message));
        console.error("Error", errors || message);
      }
    } catch (err) {
      dispatch(theme_conf_error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Get Fuel Type
export const getFuelType = (data, exception) => {
  return async (dispatch) => {
    try {
      // dispatch(fueldelay());
      dispatch(loading());
      actionStructre(
        dispatch,
        getFuel,
        exception ? exp_error : error,
        service.getFuel,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
//fastlane data
export const getFastLaneDatas = (payload) => {
  return async (dispatch) => {
    try {
      // dispatch(fueldelay());
      dispatch(loading());
      const {
        data,
        message,
        errors,
        success,
        showMessage,
        overrideMsg: msg,
      } = await service.getFastLane(payload);
      if (msg) {
        dispatch(overrideMsg(msg));
      }
      if ((data?.data || success) && !showMessage) {
        dispatch(setFastLane(data?.data || message));
      } else {
        dispatch(
          error_fastlane(
            showMessage ? { showMessage: message } : errors || message
          )
        );
      }
    } catch (err) {
      dispatch(setFastLane({ status: 101 }));
      console.error("Error", err);
    }
  };
};

//fastlane data for Renewal
export const getFastLaneRenewalDatas = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        setFastLaneRenewal,
        error_fastlane_renewal,
        service.getFastLaneRenewal,
        data,
        false,
        overrideMsg
      );
    } catch (err) {
      dispatch(setFastLaneRenewal({ status: 101 }));
      console.error("Error", err);
    }
  };
};

//vahaan config
export const getVahaanConfig = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        vahaanConfig,
        error_fastlane_renewal,
        service.getVahaanConfig,
        data,
        false,
        overrideMsg
      );
    } catch (err) {
      dispatch(vahaanConfig({ status: 101 }));
      console.error("Error", err);
    }
  };
};

//whatsapp
export const TriggerWhatsapp = (data, check) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        success,
        error,
        service.whatsappTrigger,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Get Fuel Type
export const FuelTypeCheck = (data) => {
  return async (dispatch) => {
    try {
      dispatch(loadStep2());
      actionStructre(
        dispatch,
        fuelCheck,
        error,
        service.getFuel,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(cancelLoad());
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Link -Click & Delivery
export const LinkTrigger = (data, con) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        con ? delivery : success,
        error,
        service.linkTrigger,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//shareQuote
export const ValidationConfig = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        validationConfigPost,
        error,
        service.validationService,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
//shareQuote
export const getValidationConfig = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        validationConfig,
        error,
        service.getValidationService,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const getIcList = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, icList, error, service.getIcList, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const getFrontendUrl = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        frontendurl,
        error,
        service.getFrontendUrl,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const getNdslUrl = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, ndslUrl, error, service.ndsl, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const postFaq = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, faqPost, error, service.postFaq, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const getFaq = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, faq, error, service.getFaq, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const postCommunicationPreference = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        communicationPreference,
        error,
        service.postCommunicationPreference,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const postFeedback = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, feedback, error, service.postFeedback, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const EncryptUser = (payload) => {
  return async (dispatch) => {
    try {
      const { data } = await service.encryptUser(payload);
      if (data?.encryptData) {
        dispatch(encryptUser(data?.encryptData));
      } else {
        console.log("Webengage encryption failure");
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const RedirectURL = (payload) => {
  const failureRedirection = `${window.location.origin}/${
    import.meta.env.VITE_BASENAME
  }/landing-page`;

  return async (dispatch) => {
    try {
      dispatch(loading());
      const { data, message, success } = await service.resumeJourney(payload);
      if (data?.data || success) {
        reloadPage(data?.data?.urlLink);
      } else {
        reloadPage(failureRedirection);
      }
    } catch (err) {
      reloadPage(failureRedirection);
      console.error("Error", err);
    }
  };
};

export default homeSlice.reducer;
