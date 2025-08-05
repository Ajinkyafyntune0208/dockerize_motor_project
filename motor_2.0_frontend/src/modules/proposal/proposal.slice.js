import { createSlice, current } from "@reduxjs/toolkit";
import service from "./serviceApi";
import {
  actionStructre,
  actionStructreBoth,
  serializeError,
  toDate,
} from "utils";
import moment from "moment";
import _ from "lodash";
import { differenceInDays } from "date-fns";
import swal from "sweetalert";

export const proposalSlice = createSlice({
  name: "proposal",
  initialState: {
    loading: false,
    prefillLoad: null,
    error: null,
    success: null,
    save: null,
    prefill: {},
    error_other: null,
    temp_data: {},
    pincode: {},
    bankIfsc: {},
    bankIfscError: "",
    carPincode: {},
    inspectionPincode : {},
    financer: [],
    agreement: [],
    gender: [],
    occupation: [],
    relation: [],
    submit: null,
    previc: [],
    submitProcess: null,
    lead: null,
    checkAddon: [],
    wording: null,
    url: null,
    saveaddon: null,
    category: [],
    usage: [],
    otp: {},
    verifyOtp: null,
    otpError: null,
    ckycError: null,
    duplicateEnquiry: null,
    adrila: {},
    icList: [],
    fields: null,
    ckycFields: null,
    ongridLoad: {},
    gridLoad: null,
    breakinEnquiry: null,
    finUrl: null,
    colors: [],
    verifyCkycnum: null,
    rskycStatus: null,
    accessToken: null,
    orgFields: null,
    industryFields: null,
    errorSpecific: null,
    prevIcTp: null,
    resentOtp: null,
    ckycLoading: null,
    proposalPdf: null,
    branchMaster: [],
    ckycErrorData: null,
    inspectionType: [],
  },
  reducers: {
    loading: (state, { payload }) => {
      // state.loading = true;
      state.error = null;
      state.success = null;
      switch (payload) {
        case "prefill":
          state.prefillLoad = true;
          break;
        default:
          state.loading = true;
          break;
      }
    },
    success: (state, { payload }) => {
      state.loading = null;
      state.error = null;
      state.success = payload;
    },
    error: (state, { payload }) => {
      state.loading = null;
      state.error = serializeError(payload);
      state.success = null;
      state.submitProcess = false;
    },
    error_other: (state, { payload }) => {
      state.loading = null;
      state.error_other = serializeError(payload);
      state.success = null;
      state.submitProcess = null;
    },
    clear: (state, { payload }) => {
      state.loading = null;
      state.error = null;
      state.success = null;
      state.error_other = null;
      state.otpError = null;
      state.ckycError = null;
      switch (payload) {
        case "pincode":
          state.pincode = {};
          break;
        case "submit":
          state.submit = null;
          break;
        case "car_pincode":
          state.carPincode = {};
          break;
        case "inspectionPincode":
          state.inspectionPincode = {};
          break;
        case "wording":
          state.wording = null;
          break;
        case "verifyOtp":
          state.verifyOtp = null;
          break;
        case "ckycError":
          state.ckycError = null;
          break;
        case "verifyCkycnum":
          state.verifyCkycnum = null;
          break;
        case "rskycStatus":
          state.rskycStatus = null;
          break;
        default:
          break;
      }
    },
    save: (state, { payload }) => {
      state.loading = null;
      state.submitProcess = false;
      state.Save = payload;
      if (
        payload?.lastProposalModifiedTime >
          current(state).temp_data?.lastProposalModifiedTime ||
        (!current(state).temp_data?.lastProposalModifiedTime &&
          payload?.lastProposalModifiedTime)
      ) {
        state.temp_data = {
          ...current(state).temp_data,
          lastProposalModifiedTime: payload?.lastProposalModifiedTime,
        };
      }
    },
    prefill: (state, { payload }) => {
      state.loading = null;
      state.prefillLoad = null;
      state.prefill = payload;
      let prefillData = {
        //login (corpId)
        corpId: payload?.corpId,
        userId: payload?.userId,
        //lead-page
        firstName: payload?.userFname,
        lastName: payload?.userLname,
        emailId: payload?.userEmail,
        mobileNo: payload?.userMobile,
        //registration-page
        journeyType:
          payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
          payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo !==
            "NEW"
            ? 1
            : payload?.corporateVehiclesQuoteRequest?.businessType ===
              "newbusiness"
            ? payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo ===
                "NEW" ||
              (payload?.corporateVehiclesQuoteRequest?.vehicleRegisterDate &&
                differenceInDays(
                  toDate(
                    payload?.corporateVehiclesQuoteRequest?.vehicleRegisterDate
                  ),
                  toDate(moment().format("DD-MM-YYYY"))
                ) >= 0)
              ? 3
              : 2
            : 2,
        regNo: payload?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo,
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
        modelId: payload?.quoteLog?.quoteDetails?.model,
        modelName:
          payload?.corporateVehiclesQuoteRequest?.modelName ||
          payload?.quoteLog?.quoteDetails?.modelName,
        //fuel-type
        fuel:
          payload?.quoteLog?.quoteDetails?.fuelType ||
          payload?.corporateVehiclesQuoteRequest?.fuelType,
        kit_val:
          payload?.quoteLog?.quoteDetails?.vehicleLpgCngKitValue ||
          payload?.corporateVehiclesQuoteRequest?.vehicleLpgCngKitValue,
        kit: payload?.quoteLog?.quoteDetails?.vehicleLpgCngKitValue ? 1 : 0,
        //variant
        versionId:
          payload?.quoteLog?.quoteDetails?.version ||
          payload?.corporateVehiclesQuoteRequest?.versionId,
        versionName:
          payload?.corporateVehiclesQuoteRequest?.versionName ||
          payload?.quoteLog?.quoteDetails?.versionName,
        //rto
        rtoNumber:
          payload?.corporateVehiclesQuoteRequest?.rtoCode ||
          payload?.quoteLog?.quoteDetails?.rto ||
          payload?.quoteLog?.quoteDetails?.rtoCode,
        //year
        regDate:
          payload?.quoteLog?.quoteDetails?.vehicleRegisterDate ||
          payload?.corporateVehiclesQuoteRequest?.vehicleRegisterDate,
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
        leadSource: payload?.leadSource,
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
        //proposal
        userProposal: payload?.userProposal,
        selectedQuote: payload?.quoteLog?.premiumJson
          ? payload?.quoteLog?.premiumJson
          : {},
        fastlaneJourney:
          payload?.corporateVehiclesQuoteRequest?.isFastlane == "Y"
            ? true
            : false,
        fastlaneNcbPopup:
          payload?.corporateVehiclesQuoteRequest?.isPopupShown == "N" &&
          (payload?.corporateVehiclesQuoteRequest?.isFastlane == "Y" ||
            payload?.corporateVehiclesQuoteRequest?.journeyType == "fastlane")
            ? true
            : false,
        odOnly:
          payload?.corporateVehiclesQuoteRequest?.policyType == "own_damage"
            ? true
            : false,

        isNcbVerified:
          payload?.corporateVehiclesQuoteRequest?.isNcbVerified === "Y"
            ? "Y"
            : "N",
        //quotes-data
        quoteLog: payload?.quoteLog,
        corporateVehiclesQuoteRequest: payload?.corporateVehiclesQuoteRequest,
        prevShortTerm:
          payload?.corporateVehiclesQuoteRequest?.prevShortTerm * 1,
        //agent data
        quoteAgent: payload?.quoteAgent,
        //journey Stage
        journeyStage: payload?.journeyStage,
        //agent details
        agentDetails: payload?.agentDetails,
        traceId: payload?.traceId,
        rtoCity: payload.corporateVehiclesQuoteRequest?.rtoCity,
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
        breakinGenerationDate: payload?.cvBreakinDetails?.breakinGenerationDate,
        breakinExpiryDate: payload?.cvBreakinDetails?.breakinExpiryDate,
        lastProposalModifiedTime: payload?.lastProposalModifiedTime,
        isRenewalUpload: payload?.isRenewalUpload,
        journeyId: payload?.journeyId,
        renewalAttributes: payload?.renewalAttributes,
        subProduct: payload?.subProduct,
        proposalExtraFields: payload?.proposalExtraFields,
        icBreakinUrl: payload?.cvBreakinDetails?.icBreakinUrl,
        icBreakinUrl: payload?.cvBreakinDetails?.icBreakinUrl,
        Source_IP: payload?.Source_IP
      };
      state.temp_data = { ...state.temp_data, ...prefillData };
    },
    set_temp_data: (state, { payload }) => {
      state.temp_data =
        payload === "clearAll" ? {} : { ...state.temp_data, ...payload };
    },
    pincode: (state, { payload }) => {
      state.pincode = payload;
    },
    bankIfsc: (state, { payload }) => {
      state.bankIfsc = payload;
    },
    bankIfscError: (state, { payload }) => {
      state.bankIfscError = payload;
      state.bankIfsc = {};
      state.bankIfsc = {};
    },
    carPincode: (state, { payload }) => {
      state.carPincode = payload;
    },
    inspectionPincode: (state, { payload }) => {
      state.inspectionPincode = payload;
    },
    financer: (state, { payload }) => {
      state.financer = payload;
    },
    agreement: (state, { payload }) => {
      state.agreement = payload;
    },
    gender: (state, { payload }) => {
      state.gender = payload;
    },
    occupation: (state, { payload }) => {
      state.occupation = payload;
    },
    relation: (state, { payload }) => {
      state.relation = payload;
    },
    submit: (state, { payload }) => {
      if (
        payload?.lastProposalModifiedTime >
          current(state).temp_data?.lastProposalModifiedTime ||
        (!current(state).temp_data?.lastProposalModifiedTime &&
          payload?.lastProposalModifiedTime)
      ) {
        state.temp_data = {
          ...current(state).temp_data,
          lastProposalModifiedTime: payload?.lastProposalModifiedTime,
        };
      }
      state.submitProcess = null;
      state.submit = payload;
    },
    prevIc: (state, { payload }) => {
      state.prevIc = payload;
    },
    prevIcTp: (state, { payload }) => {
      state.prevIcTp = payload;
    },
    submitProcess: (state, { payload }) => {
      state.submitProcess = true;
    },
    clearProcess: (state, { payload }) => {
      state.submitProcess = null;
    },
    lead: (state, { payload }) => {
      state.lead = payload;
    },
    checkAddon: (state, { payload }) => {
      state.checkAddon = payload;
    },
    error_hide: (state, { payload }) => {
      // state.checkAddon = payload;
    },
    wording: (state, { payload }) => {
      state.wording = payload;
    },
    url: (state, { payload }) => {
      state.url = payload;
    },
    saveaddon: (state, { payload }) => {
      state.saveaddon = payload;
      if (
        payload?.lastProposalModifiedTime >
          current(state).temp_data?.lastProposalModifiedTime ||
        (!current(state).temp_data?.lastProposalModifiedTime &&
          payload?.lastProposalModifiedTime)
      ) {
        state.temp_data = {
          ...current(state).temp_data,
          lastProposalModifiedTime: payload?.lastProposalModifiedTime,
        };
      }
    },
    category: (state, { payload }) => {
      state.category = payload;
    },
    usage: (state, { payload }) => {
      state.usage = payload;
    },
    otp: (state, { payload }) => {
      state.otp = payload;
    },
    verifyOtp: (state, { payload }) => {
      state.verifyOtp = payload;
    },
    otpError: (state, { payload }) => {
      state.otpError = serializeError(payload);
    },
    ckycError: (state, { payload }) => {
      state.ckycError = serializeError(payload);
    },
    setDuplicateEnquiry: (state, { payload }) => {
      state.duplicateEnquiry = payload;
    },
    setBreakinEnquiry: (state, { payload }) => {
      state.breakinEnquiry = payload;
    },
    clrDuplicateEnquiry: (state, { payload }) => {
      state.duplicateEnquiry = null;
      state.breakinEnquiry = null;
    },
    adrila: (state, { payload }) => {
      state.adrila = payload;
      state.temp_data = {
        ...state.temp_data,
        userProposal: payload?.additional_details
          ? _.mapKeys(payload?.additional_details, (value, key) =>
              _.camelCase(key)
            )
          : null,
      };
    },
    icList: (state, { payload }) => {
      state.icList = payload;
    },
    fields: (state, { payload }) => {
      state.fields = payload;
    },
    orgFields: (state, { payload }) => {
      state.orgFields = payload;
    },
    industryFields: (state, { payload }) => {
      state.industryFields = payload;
    },
    ckycFields: (state, { payload }) => {
      state.ckycFields = payload;
    },
    ongridLoad: (state, { payload }) => {
      state.ongridLoad = payload;
      state.gridLoad = false;
    },
    gridLoad: (state, { payload }) => {
      state.gridLoad = payload;
    },
    gridError: (state, { payload }) => {
      // state.gridLoad = false;
      // if (payload) {
      //   state.ongridLoad = {
      //     status:
      //       payload === "Record not found. Block journey."
      //         ? 102
      //         : payload === "This case belongs to another RenewBuy agent"
      //         ? 103
      //         : payload === "This Rc Number Blocked On Portal"
      //         ? 104
      //         : 101,
      //   };
      // }
    },
    gridErrorOverride: (state, { payload }) => {
      state.gridLoad = false;
      if (!_.isEmpty(payload)) {
        state.ongridLoad = {
          status: payload?.data?.status || 101,
          ...((payload?.data?.overrideMsg || payload?.overrideMsg) && {
            overrideMsg: payload?.data?.overrideMsg || payload?.overrideMsg,
          }),
        };
      }
    },
    finUrl: (state, { payload }) => {
      state.finUrl = payload;
    },
    colors: (state, { payload }) => {
      state.colors = payload;
    },
    verifyCkycnum: (state, { payload }) => {
      state.verifyCkycnum = payload;
      if (
        payload?.lastProposalModifiedTime >
          current(state).temp_data?.lastProposalModifiedTime ||
        (!current(state).temp_data?.lastProposalModifiedTime &&
          payload?.lastProposalModifiedTime)
      ) {
        state.temp_data = {
          ...current(state).temp_data,
          lastProposalModifiedTime: payload?.lastProposalModifiedTime,
        };
      }
    },
    rskycStatus: (state, { payload }) => {
      state.rskycStatus = payload;
      if (
        payload?.lastProposalModifiedTime >
          current(state).temp_data?.lastProposalModifiedTime ||
        (!current(state).temp_data?.lastProposalModifiedTime &&
          payload?.lastProposalModifiedTime)
      ) {
        state.temp_data = {
          ...current(state).temp_data,
          lastProposalModifiedTime: payload?.lastProposalModifiedTime,
        };
      }
    },
    accessToken: (state, { payload }) => {
      state.accessToken = payload;
    },
    errorSpecific: (state, { payload }) => {
      state.errorSpecific = serializeError(payload);
    },
    resentOtp: (state, { payload }) => {
      state.resentOtp = payload;
    },
    proposalPdf: (state, { payload }) => {
      state.proposalPdfJson = payload;
    },
    errorData: (state, { payload }) => {
      if (
        payload?.data?.lastProposalModifiedTime >
          current(state).temp_data?.lastProposalModifiedTime ||
        (!current(state).temp_data?.lastProposalModifiedTime &&
          payload?.data?.lastProposalModifiedTime)
      ) {
        state.temp_data = {
          ...current(state).temp_data,
          lastProposalModifiedTime: payload?.data?.lastProposalModifiedTime,
        };
      }
    },
    updateModifiedTime: (state, { payload }) => {
      if (
        payload?.lastProposalModifiedTime >
          current(state).temp_data?.lastProposalModifiedTime ||
        (!current(state).temp_data?.lastProposalModifiedTime &&
          payload?.lastProposalModifiedTime)
      ) {
        state.temp_data = {
          ...current(state).temp_data,
          lastProposalModifiedTime: payload?.lastProposalModifiedTime,
        };
      }
    },
    ckycLoading: (state, { payload }) => {
      state.ckycLoading = payload;
    },
    branchMaster: (state, { payload }) => {
      state.branchMaster = payload;
    },
    ckyc_error_data: (state, { payload }) => {
      state.ckycErrorData = payload;
    },
    inspectionType: (state, { payload }) => {
      state.inspectionType = payload;
    },
  },
});

export const {
  loading,
  success,
  error,
  clear,
  save,
  prefill,
  error_other,
  pincode,
  bankIfsc,
  carPincode,
  inspectionPincode,
  financer,
  agreement,
  gender,
  occupation,
  relation,
  submit,
  prevIc,
  submitProcess,
  clearProcess,
  lead,
  checkAddon,
  error_hide,
  wording,
  url,
  saveaddon,
  category,
  usage,
  otp,
  verifyOtp,
  otpError,
  ckycError,
  setDuplicateEnquiry,
  clrDuplicateEnquiry,
  adrila,
  icList,
  fields,
  orgFields,
  industryFields,
  ckycFields,
  ongridLoad,
  gridLoad,
  gridError,
  setBreakinEnquiry,
  finUrl,
  colors,
  verifyCkycnum,
  rskycStatus,
  accessToken,
  set_temp_data,
  errorSpecific,
  prevIcTp,
  resentOtp,
  errorData,
  updateModifiedTime,
  ckycLoading,
  proposalPdf,
  branchMaster,
  ckyc_error_data,
  gridErrorOverride,
  bankIfscError,
  inspectionType,
} = proposalSlice.actions;

export const Save = (payload, ckyc, ckycPayload, setLoading) => {
  return async (dispatch) => {
    dispatch(submitProcess());
    try {
      if (!!ckyc) {
        const {
          data,
          message,
          errors,
          success: s,
          errorSpecific,
        } = await service.save(payload);
        dispatch(ckycLoading(true));
        if (data?.data || s) {
          dispatch(ckycLoading(false));

          dispatch(save(data?.data || message));
          dispatch(VerifyCkycnum(ckycPayload, setLoading));
        } else {
          dispatch(ckycLoading(false));
          errorSpecific && dispatch(errorSpecific(errorSpecific));
          dispatch(error(errors || message));
          console.error("Error", errors || message);
          setLoading(false);
        }
      } else {
        actionStructre(dispatch, save, error, service.save, payload);
      }
    } catch (err) {
      !!ckyc && dispatch(ckycLoading(false));
      dispatch(clearProcess());
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//generate token for united india ckyc
export const AccessToken = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        accessToken,
        error,
        service.accessToken,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//submit
export const SubmitData = (data, typeRoute) => {
  return async (dispatch) => {
    try {
      dispatch(submitProcess());
      actionStructreBoth(
        dispatch,
        submit,
        error_other,
        service.submit,
        {
          data,
          typeRoute,
        },
        errorSpecific,
        errorData,
        false,
        ckyc_error_data
      );
    } catch (err) {
      dispatch(clearProcess());
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Prefill
export const Prefill = (data, check) => {
  return async (dispatch) => {
    try {
      if (!check) {
        dispatch(loading("prefill"));
      }
      actionStructre(
        dispatch,
        prefill,
        error_other,
        service.prefill,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//pincode
export const Pincode = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        pincode,
        error_other,
        service.pincode,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Bank IFSC Code
export const IFSC = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        bankIfsc,
        bankIfscError,
        service.bankIfsc,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Car pincode
export const CarPincode = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        carPincode,
        error_other,
        service.pincode,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
export const InspectionPincode = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        inspectionPincode,
        error_other,
        service.pincode,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
//financer
export const getFinancer = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        financer,
        error_other,
        service.financer,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//agreement
export const getAgreement = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        agreement,
        error_other,
        service.agreement,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//gender
export const getGender = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        gender,
        error_other,
        service.gender,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
//inspection Type
export const getInspectionType = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        inspectionType,
        error_other,
        service.inspectionType,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
}
//occupation
export const getOccupation = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        occupation,
        error_other,
        service.occupation,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Relations
export const getRelation = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        relation,
        error_other,
        service.relations,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Relations
export const PrevIc = (data, TP) => {
  return async (dispatch) => {
    try {
      !TP &&
        actionStructre(
          dispatch,
          prevIc,
          error_other,
          service.prevIc,
          data,
          errorSpecific
        );
      TP &&
        actionStructre(
          dispatch,
          prevIcTp,
          error_other,
          service.prevIc,
          data,
          errorSpecific
        );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//trigger lead
export const Lead = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        lead,
        error_other,
        service.saveLeadData,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//trigger lead
export const CheckAddon = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        checkAddon,
        error_hide,
        service.checkAddon,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Wordings
export const Wording = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, wording, error_hide, service.wording, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Url
export const Url = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, url, error_hide, service.url, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Save addon
export const SaveAddon = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, saveaddon, error_hide, service.saveAddons, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Save addon
export const Category = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, category, error_hide, service.category, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Save addon
export const Usage = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, usage, error_hide, service.usage, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//get OTP
export const OTP = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, otp, error_hide, service.otp, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//verify OTP
export const VerifyOTP = (payload, setLoading) => {
  return async (dispatch) => {
    try {
      const {
        data,
        message,
        errors,
        success: s,
      } = await service.verifyOtp(payload);
      if (data?.data || s) {
        dispatch(verifyOtp(data?.data || message));
        setLoading(false);
      } else {
        dispatch(error_other(errors || message));
        console.error("Error", errors || message);
        setLoading(false);
      }
    } catch (err) {
      dispatch(error_other("Something went wrong"));
      console.error("Error", err);
      setLoading(false);
    }
  };
};

//verify ckyc number
export const VerifyCkycnum = (payload, setLoading) => {
  return async (dispatch) => {
    try {
      const {
        data,
        message,
        errors,
        success: s,
        errorSpecific,
      } = await service.verifyCkycnum(payload);
      if (data?.data || s) {
        errorSpecific && dispatch(error(message));
        errorSpecific && dispatch(errorSpecific(errorSpecific));
        dispatch(verifyCkycnum(data?.data || message));
        setLoading && setLoading(false);
      } else {
        dispatch(error(message));
        errorSpecific && dispatch(errorSpecific(errorSpecific));
        console.error("Error", errors || message);
        setLoading && setLoading(false);
      }
    } catch (err) {
      setLoading && dispatch(error("Something went wrong"));
      setLoading && console.error("Error", err);
      setLoading && setLoading(false);
    }
  };
};

export const VerifyGodigitKyc = (payload, setLoading) => {
  return async (dispatch) => {
    try {
      const {
        data,
        message,
        errors,
        success: s,
        errorSpecific,
      } = await service.godigitKyc(payload);
      if (data?.data || s) {
        dispatch(verifyCkycnum(data?.data || message));
        setLoading(false);
      } else {
        errorSpecific && dispatch(errorSpecific(errorSpecific));
        dispatch(error(errors || message));
        console.error("Error", errors || message);
        setLoading(false);
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const VerifyRSKyc = (payload, setLoading) => {
  return async (dispatch) => {
    try {
      const {
        data,
        message,
        errors,
        success: s,
        errorSpecific,
      } = await service.RSKyc(payload);
      if (data?.data || s) {
        dispatch(rskycStatus(data?.data || message));
        setLoading(false);
      } else {
        errorSpecific && dispatch(errorSpecific(errorSpecific));
        dispatch(error(errors || message));
        console.error("Error", errors || message);
        setLoading(false);
      }
    } catch (err) {
      // dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Duplicate Enquiry
export const DuplicateEnquiryId = (data, breakinCase) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        breakinCase ? setBreakinEnquiry : setDuplicateEnquiry,
        error_other,
        service.duplicateEnquiry,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//verify OTP
export const AdrilaLoad = (payload, type) => {
  return async (dispatch) => {
    try {
      dispatch(gridLoad(true));
      if (type) {
        const { data, message, errors, success, errorSpecific, error } =
            await service.adrila(payload);
          if (data.data && success) {
            dispatch(ongridLoad(data.data || message));
          } else {
            console.log("datadatadata", data)
            dispatch(gridErrorOverride({...data, ...(message && {overrideMsg: data?.overrideMsg || data?.message })}))
          }

      } else {
        actionStructre(dispatch, adrila, error_hide, service.adrila, payload);
      }
    } catch (err) {
      type && dispatch(gridLoad(false));
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//all ic
export const GetIc = (baseUrl) => {
  return async (dispatch) => {
    try {
      const {
        data,
        message,
        errors,
        success: s,
      } = await service.getIc(baseUrl);
      if (data?.data || s) {
        dispatch(icList(data?.data || message));
      } else {
        dispatch(error(errors || message));
        console.error("Error", errors || message);
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//all fields
export const GetFields = (payload, Broker) => {
  return async (dispatch) => {
    try {
      const {
        data,
        message,
        errors,
        success: s,
      } = await service.fields(payload, Broker);
      if (data?.data || s) {
        dispatch(fields(data?.data?.fields || data?.data || message));
        dispatch(ckycFields(data?.data || message));
      } else {
        dispatch(error(errors || message));
        console.error("Error", errors || message);
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//all fields
export const SetFields = (payload, Broker) => {
  return async (dispatch) => {
    try {
      const {
        data,
        message,
        errors,
        success: s,
      } = await service.setFields(payload, Broker);
      if (data?.data || s) {
        dispatch(success(data?.data || message));
      } else {
        dispatch(error(errors || message));
        console.error("Error", errors || message);
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//all organization types
export const GetOrgFields = (payload) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, orgFields, error, service.GetOrgfields, payload);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
//industry types
export const GetIndustryfields = (payload) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        industryFields,
        error,
        service.GetIndustryfields,
        payload
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Finsall
export const Finsall = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        finUrl,
        error,
        service.finsall,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Color Master
export const getColor = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, colors, error_other, service.sbiColors, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

// resent otp
export const ResentOtp = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        resentOtp,
        error,
        service.resentOtp,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

// proposal pdf
export const ProposalPdf = (data, isMobile) => {
  return async (dispatch) => {
    try {
      const response = await service.proposalPdf(data, true);
      if (response?.data?.data) {
        let a = document.createElement("a");
        a.href = "data:application/octet-stream;base64," + response?.data?.data;
        import.meta.env.VITE_BROKER === "GRAM" &&
          window &&
          window.Android &&
          window.Android.downloadFile(
            "data:application/octet-stream;base64," + response?.data?.data
          );
        import.meta.env.VITE_BROKER === "RB" &&
          isMobile &&
          window.postMessage(
            `${"data:application/octet-stream;base64," + response?.data?.data}`,
            "*"
          );
        a.download = `${moment().format("DD-MM-YYYY")} proposal.pdf`;
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

export const FetchBranch = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        branchMaster,
        error,
        service.branchMaster,
        data,
        errorSpecific
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export default proposalSlice.reducer;
