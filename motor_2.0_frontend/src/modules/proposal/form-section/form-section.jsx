/* eslint-disable react-hooks/rules-of-hooks */
import React, { useState, useEffect, useLayoutEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { CompactCard as Card, getBrokerLogoUrl } from "components";
//prettier-ignore
import {
  scrollToTargetAdjusted, fetchToken, reloadPage,
  Encrypt, scrollToTop, toDate, createHash
} from "utils";
import { Label } from "../style";
import "../bootstrapMod.scss";
import "react-datepicker/dist/react-datepicker.css";
import _ from "lodash";
import { useHistory } from "react-router";
import swal from "sweetalert";
import moment from "moment";
import {
  Save,
  clear,
  SubmitData,
  Lead,
  Finsall,
  VerifyCkycnum,
  ckyc_error_data,
  set_temp_data,
} from "../proposal.slice";
import {
  ShareQuote,
  error_show,
  share as clearShare,
} from "modules/Home/home.slice";
import { SaveAddonsData } from "modules/quotesPage/quote.slice";
import { useMediaPredicate } from "react-media-hook";
import { differenceInDays } from "date-fns";
import { TypeReturn } from "modules/type";

//prettier-ignore
import { NomineeMandatory, amlBrokers } from "../proposal-constants";
//prettier-ignore
import {
  PreviousPolicyCondition, ODPreviousPolicyExclusion, NcbApplicable,
  TPDetailsInclusion, PostSubmit, getCkycType, selectedId, getCkycMode,
  getCkycDocumentMode, conTS, conES, conCO, conRTI, conZD, conRS, conKR, 
  conNCB, conLOPB, conEAKit, conNEAKit, conKit, AddonDeclaration, addonarr,
  HyperVergeFn
} from "./proposal-logic";
//prettier-ignore
import { Identities, identitiesCompany, MethodError, IdError } from "../cards/data";
/*-validation-*/
import { photoValidation } from "./validation";
/*---modals---*/
import OTPPopup from "../otp/otp";
import PaymentModal from "../modals/payment-modal";
import PreSubmit from "../modals/pre-submit";
import CkycMandate from "../modals/ckyc-mandate";
/*---theme---*/
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
/*---titles---*/
import { Titles, TitleState } from "./card-titles";
/*---cards---*/
import OwnerCard from "../cards/owner-card/owner-card";
import NomineeCard from "../cards/nominee-card/nominee-card";
import VehicleCard from "../cards/vehicle-card/vehicle-card";
import PolicyCard from "../cards/policy-card/policy-card";
/*---summary---*/
import SummaryOwner from "../summary/summary-owner";
import SummaryProposal from "../summary/summary-proposal";
import SummaryVehicle from "../summary/summary-vehicle";
/*---Review & Submit---*/
import FinalSubmit from "./review-submit";
//custom-hooks
import { useDropout, useCKYCMandate } from "./form-section-hooks";
//Analytics
import { _ckycMandateTracking } from "analytics/proposal-tracking/ckyc-mandate-tracking";
import { _ownerTracking } from "analytics/proposal-tracking/owner-tracking";
import { _nomineeTracking } from "analytics/proposal-tracking/nominee-tracking";
import { _vehicleTracking } from "analytics/proposal-tracking/vehicle-tracking";
import { _prevPolicyTracking } from "analytics/proposal-tracking/previous-policy-tracking";
import { _submitTracking } from "analytics/proposal-tracking/fom-submit-tracking";
//prettier-ignore
import { _paymentTracking, _shareTracking,
         _downloadTracking
        } from "analytics/proposal-tracking/payment-modal-tracking";
import { camsckyc } from "../cards/owner-card/ckyc/camsckyc/camsckyc";
import { _ckycTracking } from "analytics/proposal-tracking/ckyc-tracking";
import { CtUserLogin } from "analytics/clevertap";

const FormSection = (props) => {
  const history = useHistory();
  const dispatch = useDispatch();
  const lessthan768 = useMediaPredicate("(max-width: 768px)");
  const lessthan376 = useMediaPredicate("(max-width: 376px)");
  const _stToken = fetchToken();
  const ls = new SecureLS();
  const ThemeLS = ls.get("themeData");
  const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

  //prettier-ignore
  const { error, submit, submitProcess, finUrl,
          error_other, errorSpecific, verifyCkycnum,
          ckycErrorData
        } = useSelector((state) => state.proposal);

  //prettier-ignore
  const { share, theme_conf,  error_show: OTPerror } = useSelector((state) => state.home);
  const { cpaSet } = useSelector((state) => state.quotes);
  //modals.
  const [show, setShow] = useState(false); //otp modal
  const { paymentModal, setPaymentModal } = props; // payment confirmation modal
  const [presubmitModal, setPresubmitModal] = useState(false); // presubmit conditions modal
  const [ckycMandateModal, setCkycMandateModal] = useState(false); // ckyc-mandate modal
  const [otp_id, setotp_id] = useState();
  const [rsKycStatus, setrsKycStatus] = useState(null);
  //CKYC Documents
  const [form60, setForm60] = useState();
  const [form49, setForm49] = useState();
  const [pan_file, setpan_file] = useState();
  const [poi_file, setpoi_file] = useState();
  const [poi_back_file, setpoi_back_file] = useState();
  const [poa_file, setpoa_file] = useState();
  const [poa_back_file, setpoa_back_file] = useState();
  const [photo, setPhoto] = useState();
  const [loading, setLoading] = useState(false);
  const [resubmit, setResubmit] = useState(false);
  const [uploadFile, setuploadFile] = useState(false);
  const [verifiedData, setVerifiedData] = useState();
  const [fileUploadError, setFileUploadError] = useState(false);
  
  //BAJAJ CKYC Steps
  const [errorStep, setErrorStep] = useState(0);
  //PAN Availability
  const [panAvailability, setPanAvailability] = useState("YES");
  //fetched data
  const TempData = !_.isEmpty(props?.temp) ? props?.temp : {};
  //card data | used for prefilling
  const CardData = !_.isEmpty(TempData)
    ? TempData?.userProposal?.additonalData
      ? TempData?.userProposal?.additonalData
      : {}
    : {};
  const companyAlias = !_.isEmpty(TempData?.selectedQuote)
    ? TempData?.selectedQuote?.companyAlias
    : "";
  const Additional = !_.isEmpty(props?.Additional) ? props?.Additional : {};
  const OwnerPA = !_.isEmpty(Additional?.compulsoryPersonalAccident)
    ? Additional?.compulsoryPersonalAccident?.filter(
        ({ name }) => name === "Compulsory Personal Accident"
      )
    : [];
  const Tenure = !_.isEmpty(Additional?.compulsoryPersonalAccident)
    ? Additional?.compulsoryPersonalAccident?.filter(({ tenure }) => tenure)
    : [];
  const ReasonPA = !_.isEmpty(Additional?.compulsoryPersonalAccident)
    ? Additional?.compulsoryPersonalAccident?.filter(
        ({ reason }) => reason && reason !== "cpa not applicable to company"
      )
    : [];
  //IC's / Brokers / journey which requires nominee data in all cases and nominee card is mandatory for OD Case too in universal sompo
  const NomineeBroker =
    (NomineeMandatory(TempData?.selectedQuote?.companyAlias) &&
    (TempData?.corporateVehiclesQuoteRequest?.policyType !== "own_damage")) || companyAlias === "shriram";
  //condition check for when to display CPA on the nominee card.
  //config
  const configCon =
    props?.fields.includes("cpaOptIn") &&
    TempData?.corporateVehiclesQuoteRequest?.policyType !== "own_damage";
  const conditionChk =
    !_.isEmpty(OwnerPA) || NomineeBroker || configCon ? true : false;
  //CPA check
  const PACondition =
    !_.isEmpty(ReasonPA) &&
    TempData?.corporateVehiclesQuoteRequest?.vehicleOwnerType !== "C" &&
    TempData?.corporateVehiclesQuoteRequest?.policyType !== "own_damage"
      ? true
      : false;
  //previous policy details check
  const PolicyCon = PreviousPolicyCondition(TempData);
  //excluding validations of policy details in these cases
  const PolicyValidationExculsion = ODPreviousPolicyExclusion(TempData);
  //Is NCB Applicable?
  const isNcbApplicable = () => NcbApplicable(TempData);
  //TP details Inclusion (Renewal window)
  const tpDetailsRequired = TPDetailsInclusion(
    TempData,
    props?.TypeReturn(props?.type)
  );
  //proposal renewal conditions
  const isEditable =
    TempData?.renewalAttributes?.proposal ||
    TempData?.corporateVehiclesQuoteRequest?.isRenewal !== "Y";

  /*------------- Dropout-status -----------------*/
  const [dropout, setDropout] = useState(false);
  useDropout(props?.dropout, props?.breakinCase, rsKycStatus, setDropout);
  /*------x------ Dropout-status -------x---------*/
  useEffect(() => {
    setrsKycStatus(props?.rsKycStatus);
    dispatch(clear("rskycStatus"));
  }, [props?.rsKycStatus]);

  /*---------------------form data--------------------*/
  //saving the proposal card data in state
  const [owner, setOwner] = useState({});
  const [tempOwner, setTempOwner] = useState({});
  const [nominee, setNominee] = useState({});
  const [vehicle, setVehicle] = useState({});
  const [prepolicy, setPrepolicy] = useState({});
  /*-----------------x---form data-x------------------*/

  /*-----------------Switchers (form/summary) ------------------------*/
  const [formOwner, setFormOwner] = useState("form");
  const [formNominee, setFormNominee] = useState("hidden");
  const [formVehicle, setFormVehicle] = useState("hidden");
  const [formPrepolicy, setFormPrepolicy] = useState("hidden");
  /*-----------------Switchers section end--------------------------*/

  /*--------------------form switchers----------------------------*/
  useEffect(() => {
    if (Number(TempData?.ownerTypeId) === 2 || !conditionChk) {
      if (formOwner === "form") {
        setFormVehicle("hidden");
        setFormPrepolicy("hidden");
      }
      if (formVehicle === "form") {
        setFormPrepolicy("hidden");
      }
    } else {
      if (formOwner === "form") {
        setFormNominee("hidden");
        setFormVehicle("hidden");
        setFormPrepolicy("hidden");
      }
      if (formNominee === "form") {
        setFormVehicle("hidden");
        setFormPrepolicy("hidden");
      }
      if (formVehicle === "form") {
        setFormPrepolicy("hidden");
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [formOwner, formNominee, formVehicle, formPrepolicy]);
  /*---------x----------form switchers----------------x-----------*/

  /*------------ckyc mandate ----------------*/
  const ckycParams = { TempData, CardData, theme_conf };
  const ckycStateParams = { owner, show, setCkycMandateModal };
  useCKYCMandate(ckycParams, ckycStateParams);
  /*-----x------ckyc mandate--------x-------*/

  /*--------------------form onSubmits----------------------------*/
  //validation for format of files
  useEffect(() => {
    if (photo) {
      photoValidation(
        companyAlias,
        photo,
        setPhoto,
        theme_conf?.broker_config?.file_ic_config
      );
    }
  }, [photo]);

  useEffect(() => {
    if (poi_file) {
      photoValidation(
        companyAlias,
        poi_file,
        setpoi_file,
        theme_conf?.broker_config?.file_ic_config
      );
    }
  }, [poi_file]);

  useEffect(() => {
    if (poi_back_file) {
      photoValidation(
        companyAlias,
        poi_back_file,
        setpoi_back_file,
        theme_conf?.broker_config?.file_ic_config
      );
    }
  }, [poi_back_file]);

  useEffect(() => {
    if (poa_file) {
      photoValidation(
        companyAlias,
        poa_file,
        setpoa_file,
        theme_conf?.broker_config?.file_ic_config
      );
    }
  }, [poa_file]);

  useEffect(() => {
    if (poa_back_file) {
      photoValidation(
        companyAlias,
        poa_back_file,
        setpoa_back_file,
        theme_conf?.broker_config?.file_ic_config
      );
    }
  }, [poa_back_file]);

  useEffect(() => {
    if (pan_file) {
      photoValidation(
        companyAlias,
        pan_file,
        setpan_file,
        theme_conf?.broker_config?.file_ic_config
      );
    }
  }, [pan_file]);
  console.log("errorStep", errorStep);
  //owner
  const onSubmitOwner = async (data) => {
    if (
      //prevent direct submission of photo on mentioned ICs.
      (props?.fields?.includes("photo") &&
        !photo &&
        !["iffco_tokio", "sbi", "shriram"].includes(companyAlias)) ||
      //photo check for 2nd step of ckyc when document upload is required.
      (props?.fields?.includes("photo") &&
        !photo &&
        uploadFile &&
        ["iffco_tokio", "sbi", "shriram", "nic"].includes(companyAlias)) ||
      //Incase of file upload in iffco, photo is mandatory.
      (["iffco_tokio"].includes(companyAlias) &&
        uploadFile &&
        props?.fields?.includes("photo") &&
        !photo) ||
      //On document upload stage,
      //If POI is included in ckyc config then poi_file is mandatory except for exceptions.
      (uploadFile &&
        props?.fields?.includes("poi") &&
        !poi_file &&
        //Exceptions
        (companyAlias !== "bajaj_allianz" ||
          import.meta.env.VITE_PROD !== "YES") &&
        //TATA CIN AVAILABILITY | POI validation to be disabled if CIN is not present.
        !(
          companyAlias === "tata_aig" &&
          (TempData?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C" ||
            data?.isCinPresent === "NO") &&
          !poi_file
        ) &&
        //TATA OVD | Active on all UAT & [ACE, OLA, BAJAJ, TATA] Prods.
        (companyAlias !== "tata_aig" ||
          ["ACE", "OLA", "BAJAJ", "TATA", "HEROCARE"].includes(
            import.meta.env.VITE_BROKER
          ) ||
          import.meta.env.VITE_PROD !== "YES")) ||
      //On document upload stage,
      //If POA is included in ckyc config then poa_file is mandatory.
      (uploadFile && props?.fields?.includes("poa") && !poa_file) ||
      //If ckyc type is form 60 then form 60 file is mandatory.
      (data?.identity === "form60" && !form60) ||
      //If pan is not present then form60/form 49A is mandatory.
      (data?.isPanPresent === "NO" &&
        !form49 &&
        !form60 &&
        companyAlias !== "bajaj_allianz") ||
      //AML | When PAN is available, pan file is mandatory. | Active for shriram | No signoff present.
      (amlBrokers(companyAlias).includes(import.meta.env.VITE_BROKER) &&
        ["shriram", "royal_sundaram"].includes(companyAlias) &&
        data?.isPanPresent === "YES" &&
        !pan_file) || (uploadFile && companyAlias === "nic" && !poi_back_file)
    ) {
      setFileUploadError(true);
    } else {
      //Analytics | owner tracking
      _ownerTracking(handleType, TempData, props?.enquiry_id, data);
      //CKYC Post Submit
      let ckycPostSubmit = PostSubmit(TempData);
      //prettier-ignore
      const selectedpoiIdentity = selectedId("poi_identity", TempData, Identities, identitiesCompany, data);
      //prettier-ignore
      const selectedpoaIdentity = selectedId("poa_identity", TempData, Identities, identitiesCompany, data);

      //CKYC verification API is not fired with save API in post submit ckyc case.
      //In case of united india, hyperverge is added as an exception to be triggered on first card without ckyc field enabled.
      (props?.fields?.includes("ckyc") || companyAlias === "united_india") &&
      !ckycPostSubmit &&
      !resubmit &&
      !(uploadFile && companyAlias === "sbi")
        ? setTempOwner(data)
        : setOwner(data);

      //Document exception condition
      let docEx = uploadFile || companyAlias !== "sbi";
      let OwnerRequest = {
        ...data,
        ...(props?.fields?.includes("ckyc") &&
          !poi_file &&
          !poa_file && {
            ckycType: getCkycType(props?.fields, TempData, data),
            ckycTypeValue: props?.fields?.includes("ckyc")
              ? data?.isckycPresent === "YES"
                ? data?.ckycNumber
                : data[data?.identity]
              : null,
          }),
        ...(uploadFile &&
          companyAlias === "tata_aig" &&
          Number(TempData?.ownerTypeId) === 2 &&
          data?.isCinPresent !== "NO" &&
          props?.fields?.includes("ckyc") && {
            ckycType: "cinNumber",
            ...(data?.poi_cinNumber && { ckycTypeValue: data?.poi_cinNumber }),
          }),
        ...(!uploadFile && { clearDocuments: true }),
        stage: "1",
        lastProposalModifiedTime: TempData?.lastProposalModifiedTime,
        street: "street",
        ownerType: Number(TempData?.ownerTypeId) === 1 ? "I" : "C",
        businessType: TempData?.corporateVehiclesQuoteRequest?.businessType,
        productType: TempData?.corporateVehiclesQuoteRequest?.policyType,
        rtoCode: TempData?.corporateVehiclesQuoteRequest?.rtoCode,
        icName: TempData?.selectedQuote?.companyName,
        icId: TempData?.selectedQuote?.insuraneCompanyId,
        idv: TempData?.quoteLog?.idv,
        userProductJourneyId: props.enquiry_id,
        officeEmail: data?.email,
        ...{
          sellerType: TempData?.quoteAgent?.sellerType
            ? TempData?.quoteAgent?.sellerType
            : null,
        },
        ...(props?.token && {
          agentId: TempData?.quoteAgent?.agentId,
          agentName: TempData?.quoteAgent?.agentName,
          agentMobile: TempData?.quoteAgent?.agentMobile,
          agentEmail: TempData?.quoteAgent?.agentEmail,
          token: props?.token,
        }),
        additionalDetails: {
          review: CardData?.review,
          owner: data,
        },
        ...(companyAlias === "bajaj_allianz" &&
          errorStep * 1 &&
          uploadFile && { newFlowBajaj: "Y" }),
      };

      const ckycPayload = {
        companyAlias,
        lastProposalModifiedTime: TempData?.lastProposalModifiedTime,
        mode: getCkycMode(
          companyAlias,
          data,
          docEx && poa_file,
          docEx && poi_file,
          docEx && photo
        ),
        ...(data?.relationType && {
          relationType: data?.relationType,
          [`${data?.relationType}`]: data?.[`${data?.relationType}`],
        }),
        enquiryId: props?.enquiry_id,
        ...(ckycPostSubmit && {
          userProductJourneyId: props.enquiry_id,
          step: "5",
        }),
      };
      const formdata = new FormData();
      formdata.append("companyAlias", companyAlias);
      formdata.append(
        "mode",
        getCkycDocumentMode(companyAlias, data, poa_file, poi_file, photo)
      );
      dispatch(
        set_temp_data({
          ckycMode: getCkycDocumentMode(companyAlias, data, poa_file, poi_file, photo),
        })
      );
      formdata.append("enquiryId", props?.enquiry_id);
      companyAlias !== "nic" &&
        poa_file &&
        formdata.append(`poa_${selectedpoaIdentity?.fileKey}`, poa_file);
      companyAlias === "nic" &&
        poa_file &&
        formdata.append(`poa_${selectedpoaIdentity?.fileKey}_front`, poa_file);
      companyAlias === "nic" &&
        poa_back_file &&
        formdata.append(
          `poa_${selectedpoaIdentity?.fileKey}_back`,
          poa_back_file
        );
      companyAlias !== "nic" &&
        poi_file &&
        formdata.append(`poi_${selectedpoiIdentity?.fileKey}`, poi_file);
      companyAlias === "nic" &&
        poi_file &&
        formdata.append(`poi_${selectedpoiIdentity?.fileKey}_front`, poi_file);
      companyAlias === "nic" &&
        poi_back_file &&
        formdata.append(
          `poi_${selectedpoiIdentity?.fileKey}_back`,
          poi_back_file
        );
      form60 && formdata.append(`form60`, form60);
      form49 && formdata.append(`form49a`, form49);
      pan_file && formdata.append(`panFile`, pan_file);
      photo && formdata.append(`photo`, photo);
      data?.relationType && formdata.append("relationType", data?.relationType);
      data?.relationType &&
        formdata.append(
          `${data?.relationType}`,
          data?.[`${data?.relationType}`]
        );
      //In case of post submit ckyc, we are storing the documents in user proposal using an additional step "5" through the save API.
      (ckycPostSubmit || (uploadFile && companyAlias === "sbi")) &&
        formdata.append("userProductJourneyId", props.enquiry_id);
      (ckycPostSubmit || (uploadFile && companyAlias === "sbi")) &&
        formdata.append("step", 5);
      ckycPostSubmit &&
        formdata.append(
          "lastProposalModifiedTime",
          TempData?.lastProposalModifiedTime
        );

      if (!!resubmit) {
        //submit normally in case of ckyc verification and data prefill sent by ic
        dispatch(Save(OwnerRequest));
        companyAlias !== "reliance" && setResubmit(false);
      } else if (ckycPostSubmit) {
        // Dispatching step 1 of save normally in case of tata & digit
        dispatch(Save(OwnerRequest));
        //excluded uploaded documents in sbi in case if user uploads files and then uses PAN/CKYC number to verify
        dispatch(
          Save(
            (poa_file || poi_file || photo || form60 || form49 || pan_file) &&
              docEx
              ? formdata
              : ckycPayload
          )
        );
      } else {
        //Dispatching save and then ckyc confirmation as per the generic ckyc flow
        !(uploadFile && companyAlias === "sbi") && setLoading(true);
        //In case of united india, hyperverge is added as an exception to be triggered on first card without ckyc field enabled
        dispatch(
          Save(
            OwnerRequest,
            (props?.fields?.includes("ckyc") ||
              companyAlias === "united_india") &&
              !(uploadFile && companyAlias === "sbi")
              ? true
              : false,
            (poa_file || poi_file || photo || form60 || form49 || pan_file) &&
              docEx
              ? formdata
              : ckycPayload,
            setLoading
          )
        );
        uploadFile && companyAlias === "sbi" && dispatch(Save(formdata));
      }
      data?.mobileNumber &&
        CtUserLogin(data?.mobileNumber, false, true, TempData);
    }
  };

  //switch(owner -> nominee)
  useEffect(() => {
    if (Number(TempData?.ownerTypeId) === 2 || !conditionChk) {
      if (!_.isEmpty(owner)) {
        setFormOwner("summary");
        setFormVehicle("form");
      }
    } else {
      if (!_.isEmpty(owner)) {
        setFormOwner("summary");
        setFormNominee("form");
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [owner]);

  //nominee
  const onSubmitNominee = (data) => {
    //Analytics | nominee tracking
    _nomineeTracking(handleType, TempData, props?.enquiry_id);
    setNominee(data);
    let NomineeRequest = {
      ...data,
      //Additional payload
      stage: "2",
      lastProposalModifiedTime: TempData?.lastProposalModifiedTime,
      userProductJourneyId: props.enquiry_id,
      ownerType: Number(TempData?.ownerTypeId) === 1 ? "I" : "C",
      rtoCode: TempData?.corporateVehiclesQuoteRequest?.rtoCode,
      ...(props?.token && { token: props?.token }),
      additionalDetails: {
        review: CardData?.review,
        owner: owner,
        nominee: { ...data },
      },
    };
    dispatch(Save(NomineeRequest));
  };

  //switch(nominee -> vehicle)
  useEffect(() => {
    if (!_.isEmpty(nominee)) {
      setFormNominee("summary");
      setFormVehicle("form");
    }
  }, [nominee]);

  //vehicle
  const onSubmitVehicle = (data) => {
    //Analytics | vehicle details tracking,.
    _vehicleTracking(handleType, TempData, data);
    setVehicle(data);
    let VehicleRequest = {
      ...data,
      ...(data?.hypothecationCity && {
        financerLocation: data?.hypothecationCity,
      }),
      stage: "3",
      lastProposalModifiedTime: TempData?.lastProposalModifiedTime,
      rtoCode: TempData?.corporateVehiclesQuoteRequest?.rtoCode,
      ownerType: Number(TempData?.ownerTypeId) === 1 ? "I" : "C",
      userProductJourneyId: props.enquiry_id,
      ...(props?.token && { token: props?.token }),
      additionalDetails: {
        owner: owner,
        nominee: nominee,
        vehicle: { ...data },
      },
    };
    dispatch(Save(VehicleRequest));
  };

  //switch(vehicle -> pre-policy)
  useEffect(() => {
    if (!_.isEmpty(vehicle)) {
      setFormVehicle("summary");
      setFormPrepolicy("form");
    }
  }, [vehicle]);

  //pre-policy
  const onSubmitPrepolicy = (data) => {
    //Analytics | previous policy tracking
    //prettier-ignore
    _prevPolicyTracking(handleType, TempData, data, vehicle)
    setPrepolicy(data);
    let PrepolicyRequest = {
      ...data,
      stage: "4",
      lastProposalModifiedTime: TempData?.lastProposalModifiedTime,
      rtoCode: TempData?.corporateVehiclesQuoteRequest?.rtoCode,
      ownerType: Number(TempData?.ownerTypeId) === 1 ? "I" : "C",
      userProductJourneyId: props.enquiry_id,
      ...(props?.token && { token: props?.token }),
      additionalDetails: {
        review: CardData?.review,
        owner: owner,
        nominee: nominee,
        vehicle: vehicle,
        prepolicy: { ...data },
      },
    };
    dispatch(Save(PrepolicyRequest));
  };

  //switch(pre-policy -> review & submit)
  useEffect(() => {
    if (!_.isEmpty(prepolicy)) {
      setFormPrepolicy("summary");
    }
  }, [prepolicy]);
  /*---------x----------form onSubmits----------------x-----------*/

  /*--------------------Review & Submit Section End-------------------*/

  /*--------------- Handle Addon declaration -------------*/
  //Default selection of declared addons
  const DefaultSelect = () => {
    return AddonDeclaration(companyAlias, TempData);
  };
  const [zd_rti_condition, setZd_rti_condition] = useState({
    //Default selection of declared addons
    ...{
      ...(conTS(TempData) && { tyreSecure: DefaultSelect() }),
      ...(conES(TempData) && { engineProtector: DefaultSelect() }),
      ...(conCO(TempData) && { consumables: DefaultSelect() }),
      ...(conRTI(TempData) && { returnToInvoice: DefaultSelect() }),
      ...(conZD(TempData) && { zeroDepreciation: DefaultSelect() }),
      ...(conRS(TempData) && { roadSideAssistance: DefaultSelect() }),
      ...(conKR(TempData) && { keyReplace: DefaultSelect() }),
      ...(conNCB(TempData) && { ncbProtection: DefaultSelect() }),
      ...(conLOPB(TempData) && { lopb: DefaultSelect() }),
      ...(conEAKit(TempData) && { electricleKit: DefaultSelect() }),
      ...(conNEAKit(TempData) && { nonElectricleKit: DefaultSelect() }),
      ...(conKit(TempData) && { externalBiKit: DefaultSelect() }),
    },
    //prefilling previous selections
    ...(!_.isEmpty(TempData?.userProposal?.previousPolicyAddonsList) &&
      JSON.parse(TempData?.userProposal?.previousPolicyAddonsList)),
  });

  const ZD_preview_conditions =
    //applicable addons check
    !_.isEmpty(addonarr(TempData)) &&
    //when previous insurer is present and not new and is not third party
    (TempData?.corporateVehiclesQuoteRequest?.previousInsurer ||
      ((!TempData?.corporateVehiclesQuoteRequest?.previousInsurer ||
        TempData?.corporateVehiclesQuoteRequest?.previousInsurerCode ===
          "Not selected") &&
        TempData?.corporateVehiclesQuoteRequest?.businessType === "breakin")) &&
    TempData?.corporateVehiclesQuoteRequest?.previousInsurer !== "NEW" &&
    TempData?.selectedQuote?.policyType !== "Third Party" &&
    TempData?.corporateVehiclesQuoteRequest?.previousPolicyType !==
      "Not sure" &&
    //product specific block
    props?.TypeReturn(props?.type) !== null;
  /*-------x------- Handle Addon declaration ------x------*/

  const [finalSubmit, setFinalSubmit] = useState(true);

  const nomineeCheckCon =
    (PACondition && props?.fields.includes("cpaOptOut")) || PolicyCon;

  const finalSubmitCheck = [
    formOwner,
    Number(TempData?.ownerTypeId) !== 2 && conditionChk && formNominee,
    formVehicle,
    nomineeCheckCon && formPrepolicy,
  ];

  useEffect(() => {
    if (_.compact(finalSubmitCheck).every((elem) => elem === "summary")) {
      setFinalSubmit(true);
    } else {
      setFinalSubmit(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [formOwner, formNominee, formVehicle, formPrepolicy]);

  const PolicyId = TempData?.selectedQuote?.policyId || "";

  const _SubmitData = () => {
    dispatch(
      SubmitData(
        {
          proposalHash: createHash({
            ...owner,
            ...nominee,
            ...vehicle,
            ...prepolicy,
          }),
          lastProposalModifiedTime: TempData?.lastProposalModifiedTime,
          policyId: PolicyId,
          companyAlias: companyAlias,
          userProductJourneyId: props?.enquiry_id,
          enquiryId: props?.enquiry_id,
          rtoCode: TempData?.corporateVehiclesQuoteRequest?.rtoCode,
          ...(ZD_preview_conditions && {
            declaredAddons: zd_rti_condition,
          }),
          ...(companyAlias === "bajaj_allianz" &&
            errorStep * 1 &&
            uploadFile && { newFlowBajaj: "Y" }),
          ...(((!isNcbApplicable &&
            TempData?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate &&
            TempData?.corporateVehiclesQuoteRequest
              ?.previousPolicyExpiryDate !== "New" &&
            props?.fields?.includes("ncb") &&
            differenceInDays(
              toDate(moment().format("DD-MM-YYYY")),
              toDate(
                TempData?.corporateVehiclesQuoteRequest
                  ?.previousPolicyExpiryDate
              )
            ) < 90) ||
            props?.fields?.includes("cpaOptIn")) && {
            proposalSideCardUpdate: "Y",
          }),
          ...(TempData?.selectedQuote?.isRenewal === "Y" && {
            is_renewal: "Y",
          }),
        },
        props?.TypeReturn(props?.type)
      )
    );
  };

  const onFinalSubmit = () => {
    if (PolicyId && companyAlias && props?.enquiry_id) {
      //Analytics | ProposalFinal Submit
      _submitTracking(
        handleType,
        TempData,
        props?.enquiry_id,
        prepolicy,
        vehicle
      );
      if (TempData?.userProposal?.isInspectionDone === "Y") {
        setPaymentModal(true);
      } else {
        //pre-submit conditions here
        //Magma iib
        if (
          Number(TempData?.quoteLog?.icId) === 41 &&
          TempData?.userProposal?.iibresponserequired === "Y"
        ) {
          //when proposal is previously submitted.
          if (
            ["Payment Initiated", "payment failed"].includes(
              ["payment failed"].includes(
                TempData?.journeyStage?.stage.toLowerCase()
              )
                ? TempData?.journeyStage?.stage.toLowerCase()
                : TempData?.journeyStage?.stage
            )
          ) {
            //opening payment modal.
            setPaymentModal(true);
          } else {
            setPresubmitModal(true);
          }
        }
        // journey without presubmit conditions
        else {
          //when proposal is previously submitted.
          if (
            ["Payment Initiated", "payment failed"].includes(
              ["payment failed"].includes(
                TempData?.journeyStage?.stage.toLowerCase()
              )
                ? TempData?.journeyStage?.stage.toLowerCase()
                : TempData?.journeyStage?.stage
            ) &&
            TempData?.userProposal?.policyStartDate &&
            new Date(
              TempData?.userProposal?.policyStartDate.split("-")[2],
              TempData?.userProposal?.policyStartDate.split("-")[1] * 1 - 1,
              TempData?.userProposal?.policyStartDate.split("-")[0]
            )
              .setHours(0, 0, 0, 0)
              .valueOf() >= new Date().setHours(0, 0, 0, 0).valueOf()
          ) {
            //opening payment modal.
            setPaymentModal(true);
          } else if (
            companyAlias === "royal_sundaram" &&
            rsKycStatus?.kyc_status
          ) {
            setPaymentModal(true);
          } else {
            _SubmitData();
          }
          //proposal submit
        }
      }
    } else {
      swal(
        "Error",
        `${`Trace ID:- ${
          TempData?.traceId || props?.enquiry_id
        }.\n Error Message:- ${"Server Error"}`}`,
        "error"
      );
    }
  };
  /*--------------Pre-Submit--------------*/
  //Magma - iib
  const selection = (action) => {
    dispatch(
      SubmitData(
        {
          lastProposalModifiedTime: TempData?.lastProposalModifiedTime,
          policyId: PolicyId,
          companyAlias: companyAlias,
          userProductJourneyId: props?.enquiry_id,
          enquiryId: props?.enquiry_id,
          iibncb: action ? true : false,
          ...(ZD_preview_conditions && { declaredAddons: zd_rti_condition }),
        },
        props?.TypeReturn(props?.type)
      )
    );
  };
  /*-----x--------Pre-Submit--------x-----*/

  /*-----Finsal Validation-----*/
  const FinsallValidation = () => {
    swal({
      content: {
        element: "input",
        attributes: {
          placeholder: "Enter PAN",
          type: "text",
        },
      },
      title: "Please Note",
      text: "PAN Number is required",
      showCancelButton: true,
      closeOnClickOutside: false,
    }).then((inputValue) => {
      if (inputValue === "" || inputValue === null) {
        swal("PAN is required!").then(() => {
          //recursion on failure
          FinsallValidation();
        });
      }
      if (
        inputValue &&
        inputValue.match(
          /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/
        )
      ) {
        dispatch(
          Finsall({
            enquiryId: props?.enquiry_id,
            panNumber: inputValue,
          })
        );
      } else {
        inputValue &&
          swal("Invalid PAN Number!").then(() => {
            //recursion on failure
            FinsallValidation();
          });
      }
      setDropout(true);
    });
  };
  /*--x--Finsal Validation--x--*/
  /*--------------OTP--------------*/
  //Payment
  const payment = (isFinsal) => {
    //Analytics | Payment Modal
    //prettier-ignore
    _paymentTracking(handleType, TempData, props?.enquiry_id, prepolicy, vehicle);
    if (props?.enquiry_id) {
      if (
        !(
          Number(TempData?.quoteLog?.icId) === 32 ||
          Number(TempData?.quoteLog?.icId) === 35 ||
          import.meta.env.VITE_BROKER === "RB" ||
          import.meta.env.VITE_BROKER === "TATA"
        )
      ) {
        //Normal PG Journey
        if (!isFinsal) {
          !_.isEmpty(TempData?.agentDetails) &&
          !_.isEmpty(
            TempData?.agentDetails.find((item) => item?.category === "Essone")
          )
            ? swal({
                title: "Confirm Action",
                text: `Are you sure you want to make the payment?`,
                icon: "info",
                buttons: {
                  cancel: "Cancel",
                  catch: {
                    text: "Confirm",
                    value: "confirm",
                  },
                },
                dangerMode: true,
              }).then((caseValue) => {
                switch (caseValue) {
                  case "confirm":
                    history.push(
                      `/${props?.type}/payment-gateway?enquiry_id=${
                        props?.enquiry_id
                      }${props?.token ? `&xutm=${props?.token}` : ``}${
                        TempData?.userProposal?.isBreakinCase
                          ? `&breakin=${Encrypt(true)}`
                          : ``
                      }${props?.icr ? `&icr=${props?.icr}` : ``}${
                        _stToken ? `&stToken=${_stToken}` : ``
                      }`
                    );
                    break;
                  default:
                }
              })
            : history.push(
                `/${props?.type}/payment-gateway?enquiry_id=${
                  props?.enquiry_id
                }${props?.token ? `&xutm=${props?.token}` : ``}${
                  props?.typeId ? `&typeid=${props?.typeId}` : ``
                }${
                  TempData?.userProposal?.isBreakinCase
                    ? `&breakin=${Encrypt(true)}`
                    : ``
                }${
                  props?.journey_type
                    ? `&journey_type=${props?.journey_type}`
                    : ``
                }${props?.icr ? `&icr=${props?.icr}` : ``}${
                  _stToken ? `&stToken=${_stToken}` : ``
                }`
              );
        }
        //Finsal
        if (isFinsal) {
          if (TempData?.userProposal?.panNumber) {
            dispatch(
              Finsall({
                enquiryId: props?.enquiry_id,
                panNumber: TempData?.userProposal?.panNumber,
                isEmi: isFinsal === "finsall-emi",
              })
            );
            setDropout(true);
          } else {
            setPaymentModal(false);
            swal({
              content: {
                element: "input",
                attributes: {
                  placeholder: "Enter PAN",
                  type: "text",
                },
              },
              title: "Please Note",
              text: "PAN Number is required",
              showCancelButton: true,
              closeOnClickOutside: false,
            }).then((inputValue) => {
              if (inputValue === "" || inputValue === null) {
                swal("PAN is required!").then(() => {
                  //recursion on failure
                  FinsallValidation();
                });
              }
              if (
                inputValue &&
                inputValue.match(
                  /[a-zA-Z]{3}[PCHFATBLJG]{1}[a-zA-Z]{1}[0-9]{4}[a-zA-Z]{1}$/
                )
              ) {
                dispatch(
                  Finsall({
                    enquiryId: props?.enquiry_id,
                    panNumber: inputValue,
                    isEmi: isFinsal === "finsall-emi",
                  })
                );
              } else {
                inputValue &&
                  swal("Invalid PAN Number!").then(() => {
                    //recursion on failure
                    FinsallValidation();
                  });
              }
              setDropout(true);
            });
          }
        }
      } else {
        setPaymentModal(true);
        dispatch(
          ShareQuote(
            {
              enquiryId: props?.enquiry_id,
              notificationType: "all",
              domain: `http://${window.location.hostname}`,
              type: "otpSms",
              applicableNcb:
                TempData?.corporateVehiclesQuoteRequest?.applicableNcb,
              mobileNo: CardData?.owner?.mobileNumber,
              policyEndDate: TempData?.selectedQuote?.policyEndDate,
              policyStartDate: TempData?.selectedQuote?.policyStartDate,
              premiumAmount: TempData?.quoteLog?.finalPremiumAmount,
              productName: TempData?.selectedQuote?.productName,
              registrationNumber: CardData?.vehicle?.vehicaleRegistrationNumber,
              emailId: CardData?.owner?.email,
              link: window.location.href.replace(
                /proposal-page/g,
                "proposal-page"
              ),
              logo: getBrokerLogoUrl(),
              ic_logo:
                import.meta.env.VITE_BROKER === "KAROINSURE" &&
                companyAlias === "liberty_videocon"
                  ? TempData?.selectedQuote?.companyLogo
                  : null,
              ic_name: TempData?.selectedQuote?.companyName,
            },
            true
          )
        );
      }
    } else {
      swal("Error", "No enquiry id found", "error");
    }
  };

  // //clear previous success
  useEffect(() => {
    dispatch(error_show(""));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (share) {
      setShow(true);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [share]);

  //show otp failure
  useEffect(() => {
    if (OTPerror) {
      swal("Please note", OTPerror, "error");
    }
    return () => {
      dispatch(error_show(""));
    };
  }, [OTPerror]);

  //finsall redirection
  useEffect(() => {
    if (finUrl?.redirectionLink) {
      reloadPage(finUrl?.redirectionLink);
    }
  }, [finUrl?.redirectionLink]);

  //on otp enter
  const otpSuccess = (isBreakinCase) => {
    if (props?.enquiry_id) {
      !_.isEmpty(TempData?.agentDetails) &&
      !_.isEmpty(
        TempData?.agentDetails.find((item) => item?.category === "Essone")
      )
        ? swal({
            title: "Confirm Action",
            text: `Are you sure you want to make the payment?`,
            icon: "info",
            buttons: {
              cancel: "Cancel",
              catch: {
                text: "Confirm",
                value: "confirm",
              },
            },
            dangerMode: true,
          }).then((caseValue) => {
            switch (caseValue) {
              case "confirm":
                history.push(
                  `/${props?.type}/payment-gateway?enquiry_id=${
                    props?.enquiry_id
                  }${props?.token ? `&xutm=${props?.token}` : ``}${
                    TempData?.userProposal?.isBreakinCase
                      ? `&breakin=${Encrypt(true)}`
                      : ``
                  }${props?.icr ? `&icr=${props?.icr}` : ``}${
                    _stToken ? `&stToken=${_stToken}` : ``
                  }`
                );
                break;
              default:
            }
          })
        : !isBreakinCase
        ? companyAlias === "tata_aig" ? setPaymentModal(true) :  history.push(
            `/${props?.type}/payment-gateway?enquiry_id=${props?.enquiry_id}${
              props?.token ? `&xutm=${props?.token}` : ``
            }${props?.typeId ? `&typeid=${props?.typeId}` : ``}${
              TempData?.userProposal?.isBreakinCase
                ? `&breakin=${Encrypt(true)}`
                : ``
            }${
              props?.journey_type ? `&journey_type=${props?.journey_type}` : ``
            }${props?.icr ? `&icr=${props?.icr}` : ``}${
              _stToken ? `&stToken=${_stToken}` : ``
            }`
          )
        : history.push(
            `/${props?.type}/successful?enquiry_id=${
              props?.enquiry_id
            }&inspection_no=${isBreakinCase?.inspection_number}${
              dropout ? `&dropout=${Encrypt(true)}` : ``
            }${props?.token ? `&xutm=${props?.token}` : ``}&IC=${
              TempData?.quoteLog?.icAlias
            }${props?.typeId ? `&typeid=${props?.typeId}` : ``}${
              props?.journey_type ? `&journey_type=${props?.journey_type}` : ``
            }${
              isBreakinCase?.finalPayableAmount * 1
                ? `&xmc=${window.btoa(isBreakinCase?.finalPayableAmount)}`
                : ``
            }`
          );
    } else {
      swal("Error", "No enquiry id found", "error");
    }
  };

  /*------x-------OTP--------x-----*/

  const onSubmitFunc = () => {
    // lead trigger after submit & Email trigger
    !["Payment Initiated", "payment failed"].includes(
      ["payment failed"].includes(TempData?.journeyStage?.stage.toLowerCase())
        ? TempData?.journeyStage?.stage.toLowerCase()
        : TempData?.journeyStage?.stage
    ) &&
      (submit?.is_breakin !== "Y" ||
        (props?.TypeReturn(props?.type) === "bike" &&
          submit?.is_breakin === "Y")) &&
      import.meta.env.VITE_BROKER !== "ACE" &&
      dispatch(
        ShareQuote({
          enquiryId: props?.enquiry_id,
          notificationType: "email",
          domain: `http://${window.location.hostname}`,
          type: "proposalCreated",
          emailId: owner?.email,
          firstName: owner?.firstName,
          lastName: owner?.lastName,
          productName: TempData?.selectedQuote?.productName,
          link: window.location.href.replace(/proposal-page/g, "proposal-page"),
          logo: props?.getLogoUrl(),
          ic_logo: TempData?.selectedQuote?.companyLogo,
        })
      );
    dispatch(Lead({ enquiryId: props?.enquiry_id, leadStageId: 3 }));
    //If breakin is present
    if (
      submit?.is_breakin &&
      submit?.is_breakin === "Y"
    ) {
      dispatch(
        ShareQuote({
          enquiryId: props?.enquiry_id,
          notificationType: "all",
          domain: `http://${window.location.hostname}`,
          type: "inspectionIntimation",
          emailId: owner?.email,
          firstName: owner?.firstName,
          lastName: owner?.lastName,
          productName: TempData?.selectedQuote?.productName,
          link: window.location.href.replace(/proposal-page/g, "proposal-page"),
          logo: props?.getLogoUrl(),
        })
      );
      //breakin generation page redirect
      history.push(
        `/${props?.type}/successful?enquiry_id=${
          props?.enquiry_id
        }&inspection_no=${submit?.inspection_number}${
          dropout ? `&dropout=${Encrypt(true)}` : ``
        }${props?.token ? `&xutm=${props?.token}` : ``}&IC=${
          TempData?.quoteLog?.icAlias
        }${props?.typeId ? `&typeid=${props?.typeId}` : ``}${
          props?.journey_type ? `&journey_type=${props?.journey_type}` : ``
        }${
          submit?.finalPayableAmount * 1
            ? `&xmc=${window.btoa(submit?.finalPayableAmount)}`
            : ``
        }`
      );
    } else {
      setPaymentModal(true);
    }
  };

  //onSuccess
  useEffect(() => {
    if (submit) {
      if (companyAlias === "tata_aig" && props?.fields?.includes("ckyc")) {
        if (submit?.verification_status) {
          !!submit?.kyc_status &&
            !submit?.hidePopup &&
            swal("Success", "CKYC verified.", "success").then(() =>
              onSubmitFunc()
            );
          !!submit?.kyc_status && submit?.hidePopup && onSubmitFunc();
        } else if (!submit?.verification_status && submit?.otp_id) {
          setotp_id(submit?.otp_id);
          setShow(true);
        } else if (!submit?.verification_status && !submit?.otp_id) {
          swal("Error", submit?.message, "error");
        }
      } else if (
        !submit?.verification_status &&
        !submit?.kyc_status &&
        TempData?.selectedQuote?.companyAlias === "oriental"
      ) {
        //Analytics | CKYC Success Tracking
        _ckycTracking({
          ...TempData,
          userProposal: { ...TempData.userProposal, ...owner },
        });
        let verificationPayload = {
          companyAlias,
          mode: "ckyc",
          enquiryId: props?.enquiry_id,
          isCamCkyc: true,
          isCkycVerified: true,
        };
        camsckyc(dispatch, verificationPayload);
      } else {
        //Analytics | CKYC Success Tracking
        _ckycTracking({
          ...TempData,
          userProposal: { ...TempData.userProposal, ...owner },
        });
        !!submit?.kyc_status &&
          !submit?.hidePopup &&
          swal("Success", "CKYC verified.", "success").then(() =>
            onSubmitFunc()
          );
        !!submit?.kyc_status && submit?.hidePopup && onSubmitFunc();
        !submit?.kyc_status && submit?.kyc_url && onSubmitFunc();
        TempData?.selectedQuote?.companyAlias === "royal_sundaram" &&
          setResubmit(true);
      }
    }

    return () => {
      dispatch(clear("submit"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [submit]);
  /*---------------x----Review & Submit Section End----x--------------*/

  /*--------------------Hyperverge-post ckyc status--------------------*/
  useEffect(() => {
    if (
      verifyCkycnum &&
      !!verifyCkycnum?.verification_status &&
      companyAlias === "oriental"
    ) {
      //Analytics | CKYC Success Tracking
      _ckycTracking({
        ...TempData,
        userProposal: { ...TempData.userProposal, ...owner },
      });
      if (!verifyCkycnum?.hidePopup) {
        swal("Success", "CKYC verified.", "success").then(() => {
          //clear data
          dispatch(clear("verifyCkycnum"));
          //resubmit proposal
          _SubmitData();
        });
      } else {
        //clear data
        dispatch(clear("verifyCkycnum"));
        //resubmit proposal
        _SubmitData();
      }
    }
  }, [verifyCkycnum]);
  /*---------------x----Hyperverge-post ckyc status----x---------------*/

  /*---------------------------card titles------------------------*/
  //Title Function
  const TitleFn = (titleName, stateName) => {
    //prettier-ignore
    let { breakinCase, GenerateDulicateEnquiry, icr, TypeReturn, type } = props
    //prettier-ignore
    return Titles(titleName, stateName, TempData, rsKycStatus, setDropout, breakinCase, GenerateDulicateEnquiry, icr, TypeReturn, type, submitProcess);
  };
  //owner
  const titleOwnerSummary = TitleFn("Vehicle Owner Details", setFormOwner);
  //nominee
  const titleNomineeSummary = TitleFn(
    import.meta.env.VITE_BROKER === "OLA" &&
      TempData?.corporateVehiclesQuoteRequest?.journeyType === "embeded-excel"
      ? "Nominee Details"
      : "Nominee Details",
    setFormNominee
  );
  //vehicle
  const titleVehicleSummary = TitleFn("Vehicle Details", setFormVehicle);
  //pre-policy
  const titlePrepolicySummary = TitleFn(
    PolicyCon && PACondition && props?.fields?.includes("cpaOptOut")
      ? "Previous Policy & CPA Details"
      : PolicyCon
      ? "Previous Policy Details"
      : "CPA Details",
    setFormPrepolicy
  );

  /*--------------- Handle-page-scroll -------------*/
  //using html to scroll instead of refs
  useEffect(() => {
    if (formOwner === "form") {
      !dropout &&
        (lessthan768 ? scrollToTop() : scrollToTargetAdjusted("owner", 45));
    }
    if (formNominee === "form") {
      !dropout && scrollToTargetAdjusted("nominee", 45);
    }
    if (formVehicle === "form") {
      !dropout && scrollToTargetAdjusted("vehicle", 45);
    }
    if (formPrepolicy === "form") {
      !dropout && scrollToTargetAdjusted("prepolicy", 45);
    }
    //scroll to t&c checkbox
    if (_.compact(finalSubmitCheck).every((elem) => elem === "summary")) {
      import.meta.env.VITE_BROKER !== "OLA" &&
        !dropout &&
        scrollToTargetAdjusted("review-submit");
    }

    //eslint-disable-next-line
  }, [formOwner, formNominee, formVehicle, formPrepolicy]);
  /*-------x------- Handle-page-scroll ------x------*/

  /*--------------- Handle-dropout-fill -------------*/
  useLayoutEffect(() => {
    if (dropout && !_.isEmpty(CardData)) {
      if (!_.isEmpty(CardData?.owner)) {
        // setTimeout(() => {
        setOwner(CardData?.owner);
        setFormOwner("summary");
        // }, 1);
      }
      if (!_.isEmpty(CardData?.nominee)) {
        setTimeout(() => {
          setNominee(CardData?.nominee);
          setFormNominee("summary");
        }, 3);
      }
      if (!_.isEmpty(CardData?.vehicle)) {
        setTimeout(() => {
          setVehicle(CardData?.vehicle);
          setFormVehicle("summary");
        }, 5);
      }
      if (!_.isEmpty(CardData?.prepolicy)) {
        setTimeout(() => {
          setPrepolicy(CardData?.prepolicy);
          setFormPrepolicy("summary");
        }, 7);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [CardData, dropout]);
  /*-------x------- Handle-dropout-fill ------x------*/

  /*--------------- Handle-ckyc-reset -------------*/
  const [ckycReset, setCkycReset] = useState(false);
  /*-------x------- Handle-ckyc-reset ------x------*/

  /*--------------- Handle-step-error -------------*/
  useEffect(() => {
    if (error) {
      swal({
        title: "Error",
        text: props?.enquiry_id
          ? `${`Trace ID:- ${
              TempData?.traceId || props?.enquiry_id
            }.\n Error Message:- ${error}`}`
          : error,
        icon: "error",
        buttons: {
          ...(!MethodError.includes(error) && { cancel: "Dismiss" }),
          ...(errorSpecific && {
            catch: {
              text: "See more details",
              value: "confirm",
            },
          }),
          ...((MethodError.includes(error) ||
            (ckycErrorData?.poi_status &&
              TempData?.selectedQuote?.companyAlias === "bajaj_allianz" &&
              errorStep * 1)) &&
            TempData?.selectedQuote?.companyAlias !== "godigit" && {
              catch: {
                text: "Upload Documents",
                value: "upload",
              },
            }),
        },
        dangerMode: true,
      }).then((caseValue) => {
        switch (caseValue) {
          case "confirm":
            swal(
              "Error",
              props?.enquiry_id
                ? `${`Trace ID:- ${
                    TempData?.traceId || props?.enquiry_id
                  }.\n Error Message:- ${errorSpecific}`}`
                : errorSpecific,
              "error"
            );
            companyAlias === "bajaj_allianz" &&
              ckycErrorData?.poi_status &&
              setErrorStep(1);
            break;
          case "upload":
            companyAlias === "bajaj_allianz" && setPanAvailability("NO");
            setuploadFile(true);
            setCkycReset(true);
            break;
          default:
        }
        //step error flow
        if (
          !(
            ckycErrorData?.poi_status &&
            TempData?.selectedQuote?.companyAlias === "bajaj_allianz"
          )
        ) {
          if (Number(TempData?.ownerTypeId) !== 2 && conditionChk) {
            //general flow
            if (formOwner === "summary" && formNominee === "form") {
              setFormOwner("form");
            }
            if (formNominee === "summary" && formVehicle === "form") {
              setFormNominee("form");
            }
            if (formVehicle === "summary" && formPrepolicy === "form") {
              setFormVehicle("form");
            }
            if (
              _.compact(finalSubmitCheck).every((elem) => elem === "summary") &&
              (PolicyCon ||
                (PACondition && props?.fields?.includes("cpaOptOut")))
            ) {
              setFormPrepolicy("form");
            }
          } else {
            //flow without nominee
            if (formOwner === "summary" && formVehicle === "form") {
              setFormOwner("form");
            }
            if (formVehicle === "summary" && formPrepolicy === "form") {
              setFormVehicle("form");
            }
            if (
              _.compact(finalSubmitCheck).every((elem) => elem === "summary") &&
              (PolicyCon ||
                (PACondition && props?.fields?.includes("cpaOptOut")))
            ) {
              setFormPrepolicy("form");
            }
          }
        }
        //reloading page in certain case
        if (
          ["Payment Link Already Generated..!", "Payment Initiated"].includes(
            error
          )
        ) {
          dispatch(props?.DuplicateEnquiryId({ enquiryId: props?.enquiry_id }));
        }
        //redirecting user if payment is already done for the proposal
        if (error === "Transaction Already Completed") {
          reloadPage(
            `${window.location.protocol}//${window.location.host}${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ``
            }/payment-success${
              props?.enquiry_id ? `?enquiry_id=${props?.enquiry_id}` : ``
            }`
          );
        }
        if (
          error ===
          "Proposal integrity check failed. You will be redirected to quote page."
        ) {
          reloadPage(window.location.href.replace(/proposal-page/g, "quotes"));
        }
        if (error === "Proposal integrity check failed.") {
          reloadPage(window.location.href);
        }
      });
    }

    return () => {
      dispatch(clear());
      dispatch(ckyc_error_data(null));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [error]);

  //Open particular card on submit error
  useEffect(() => {
    if (error_other) {
      if (error_other === "Payment Initiated") {
        dispatch(props?.DuplicateEnquiryId({ enquiryId: props?.enquiry_id }));
      } else {
        //Method error - user will be prompted to choose b/w a different ID or File upload
        if (
          [...MethodError, ...IdError].includes(error_other) ||
          (ckycErrorData?.poi_status &&
            TempData?.selectedQuote?.companyAlias === "bajaj_allianz")
        ) {
          swal({
            title:
              error_other === "Please upload photograph to complete proposal."
                ? "Please Note"
                : "Error",
            text: props?.enquiry_id
              ? `${`Trace ID:- ${
                  TempData?.traceId || props?.enquiry_id
                }.\n Error Message:- ${error_other}`}`
              : error_other,
            icon:
              error_other === "Please upload photograph to complete proposal."
                ? "info"
                : "error",
            buttons: {
              ...(!(
                TempData?.selectedQuote?.companyAlias === "tata_aig" &&
                TempData?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
                  "C"
              ) && { cancel: "Try again" }),
              ...(((!IdError.includes(error_other) &&
                !(TempData?.selectedQuote?.companyAlias === "bajaj_allianz")) ||
                (ckycErrorData?.poi_status &&
                  TempData?.selectedQuote?.companyAlias === "bajaj_allianz" &&
                  errorStep * 1)) &&
                TempData?.selectedQuote?.companyAlias !== "godigit" && {
                  catch: {
                    text:
                      TempData?.selectedQuote?.companyAlias === "tata_aig" &&
                      TempData?.corporateVehiclesQuoteRequest
                        ?.vehicleOwnerType === "C"
                        ? "Try using CIN"
                        : "Upload Documents",
                    value: "confirm",
                  },
                }),
            },
            dangerMode: true,
          }).then((caseValue) => {
            switch (caseValue) {
              case "confirm":
                setCkycReset(true);
                setFormOwner("form");
                setuploadFile(true);
                companyAlias === "bajaj_allianz" && setPanAvailability("NO");
                dispatch(ckyc_error_data(null));
                break;
              default:
                if (
                  companyAlias === "bajaj_allianz" &&
                  ckycErrorData?.poi_status
                ) {
                  setErrorStep(1);
                  // setuploadFile(true);
                }
                setFormOwner("form");
                setCkycReset(true);
                break;
            }
          });
        }
      }
      if (
        error_other ===
        "Proposal integrity check failed. You will be redirected to quote page."
      ) {
        swal(
          "Please Note",
          "Proposal integrity check failed. You will be redirected to quote page.",
          "error"
        ).then(() =>
          reloadPage(window.location.href.replace(/proposal-page/g, "quotes"))
        );
      }
      if (error_other === "Proposal integrity check failed.") {
        swal("Please Note", "Proposal integrity check failed.", "error").then(
          () => reloadPage(window.location.href)
        );
      }
    }
  }, [error_other]);
  /*-------x------- Handle-step-error ------x------*/

  /*-----Handling non-availablity of Multi-Year-CPA-----*/
  useEffect(() => {
    if (
      !_.isEmpty(Tenure) &&
      !TempData?.selectedQuote?.multiYearCpa * 1 &&
      !cpaSet &&
      !PACondition
    ) {
      let data1 = {
        isProposal: true,
        enquiryId: TempData?.enquiry_id || props?.enquiry_id,
        lastProposalModifiedTime: TempData?.lastProposalModifiedTime,
        addonData: {
          compulsory_personal_accident: [
            {
              reason:
                "I have another motor policy with PA owner driver cover in my name",
            },
          ],
        },
      };
      dispatch(SaveAddonsData(data1, true));
    } else if (
      _.isEmpty(Tenure) &&
      !_.isEmpty(OwnerPA) &&
      !TempData?.selectedQuote?.compulsoryPaOwnDriver * 1 &&
      !cpaSet &&
      !PACondition
    ) {
      let data1 = {
        isProposal: true,
        enquiryId: TempData?.enquiry_id || props?.enquiry_id,
        lastProposalModifiedTime: TempData?.lastProposalModifiedTime,
        addonData: {
          compulsory_personal_accident: [
            {
              reason:
                "I have another motor policy with PA owner driver cover in my name",
            },
          ],
        },
      };
      dispatch(SaveAddonsData(data1, true));
    }
  }, [Additional]);
  /*--x--Handling non-availablity of Multi-Year-CPA--x--*/
  //Type
  const handleType = props?.TypeReturn(props?.type);

  return (
    <>
      <div>
        {/*--------------------Proposal Form-----------------------------------*/}
        <Card
          title={
            formOwner === "form" ? (
              <Label>Vehicle Owner Details</Label>
            ) : (
              titleOwnerSummary
            )
          }
          removeBottomHeader={true}
          backgroundColor={TitleState(formOwner, "state")}
          paddingTop={formOwner === "summary" ? "8px" : "4.5px"}
          borderRadius={TitleState(formOwner, "radius")}
          image={TitleState(false, "image", "image", handleType, TempData)}
          imageStyle={TitleState(
            false,
            "image",
            "image-style",
            handleType,
            TempData,
            lessthan768
          )}
          imageTagStyle={{
            boxShadow: "1.753px -3.595px 35px #d9d8d8",
            borderRadius: "15px",
            border:
              Theme?.proposalProceedBtn?.hex1 && !lessthan768
                ? `1px solid ${Theme?.proposalProceedBtn?.hex1} !important`
                : "1px solid #495057",
            background:
              import.meta.env.VITE_BROKER === "TATA" &&
              Theme?.proposalCardActive?.background
                ? Theme?.proposalCardActive?.background
                : Theme?.proposalProceedBtn?.hex1
                ? `${Theme?.proposalProceedBtn?.hex1}`
                : "rgba(125,142,3,1)",
            ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
              visibility: "hidden",
            }),
            ...(TypeReturn(props?.type) === "cv" && {
              padding: TempData?.productSubTypeId * 1 === 6 ? "0px" : "7px",
            }),
          }}
          id="owner"
        >
          {formOwner === "form" ? (
            <div className="ElemFade m-0 p-1">
              <OwnerCard
                poi_file={poi_file}
                poi_back_file={poi_back_file}
                setpoi_file={setpoi_file}
                setpoi_back_file={setpoi_back_file}
                poa_file={poa_file}
                poa_back_file={poa_back_file}
                setpoa_file={setpoa_file}
                setpoa_back_file={setpoa_back_file}
                pan_file={pan_file}
                setpan_file={setpan_file}
                form60={form60}
                setForm60={setForm60}
                form49={form49}
                setForm49={setForm49}
                photo={photo}
                setPhoto={setPhoto}
                uploadFile={uploadFile}
                setuploadFile={setuploadFile}
                verifiedData={verifiedData}
                setVerifiedData={setVerifiedData}
                fileUploadError={fileUploadError}
                onSubmitOwner={onSubmitOwner}
                owner={owner}
                setOwner={setOwner}
                tempOwner={tempOwner}
                loading={loading}
                setLoading={setLoading}
                setResubmit={setResubmit}
                resubmit={resubmit}
                CardData={CardData}
                Theme={Theme}
                conditionChk={conditionChk}
                lessthan768={lessthan768}
                lessthan376={lessthan376}
                enquiry_id={props?.enquiry_id}
                fields={props?.fields}
                type={props?.TypeReturn(props?.type)}
                token={props?.token}
                setCkycReset={setCkycReset}
                ckycReset={ckycReset}
                errorStep={errorStep}
                isEditable={isEditable}
                otpSuccess={otpSuccess}
                formVehicle={formVehicle}
                panAvailability={panAvailability}
                setPanAvailability={setPanAvailability}
              />
            </div>
          ) : (
            <SummaryOwner
              summary={owner}
              lessthan768={lessthan768}
              fields={props?.fields}
            />
          )}
        </Card>
        {/*---------------------------End of Proposal Card------------------------*/}
        {/*---------------------------Nominee Details Card-----------------------*/}
        {Number(TempData?.ownerTypeId) !== 2 && conditionChk && (
          <Card
            title={
              formNominee === "summary" ? (
                titleNomineeSummary
              ) : (
                <Label colState={formNominee}>{"Nominee Details"}</Label>
              )
            }
            backgroundColor={TitleState(formNominee, "state")}
            paddingTop={lessthan768 ? "10px" : "6px"}
            borderRadius={TitleState(formNominee, "radius")}
            removeBottomHeader={true}
            marginTop={formNominee === "hidden" ? "5px" : ""}
            id="nominee"
          >
            <div style={TitleState(formNominee, "animate")}>
              {formNominee === "form" ? (
                <div className="ElemFade m-0 p-1">
                  <NomineeCard
                    onSubmitNominee={onSubmitNominee}
                    nominee={nominee}
                    CardData={CardData}
                    Theme={Theme}
                    lessthan768={lessthan768}
                    lessthan376={lessthan376}
                    PACondition={PACondition}
                    enquiry_id={props?.enquiry_id}
                    dropout={dropout}
                    NomineeBroker={NomineeBroker}
                    type={props?.TypeReturn(props?.type)}
                    Tenure={Tenure}
                    fields={props?.fields}
                    isEditable={isEditable}
                  />
                </div>
              ) : formNominee === "summary" ? (
                <div className="m-0 p-1">
                  <SummaryProposal
                    data={nominee}
                    lessthan768={lessthan768}
                    fields={props?.fields}
                  />
                </div>
              ) : (
                <noscript />
              )}
            </div>
          </Card>
        )}
        {/*---------------x----End of Nominee Details Card--------x-----------*/}
        {/*---------------------------Vehicle Details Card-----------------------*/}
        <Card
          title={
            formVehicle === "summary" ? (
              titleVehicleSummary
            ) : (
              <Label colState={formVehicle}>Vehicle Details</Label>
            )
          }
          backgroundColor={TitleState(formVehicle, "state")}
          paddingTop={lessthan768 ? "10px" : "6px"}
          borderRadius={TitleState(formVehicle, "radius")}
          removeBottomHeader={true}
          marginTop={formVehicle === "hidden" ? "5px" : ""}
          id="vehicle"
        >
          <div style={TitleState(formVehicle, "animate")}>
            {formVehicle === "form" ? (
              <div className="ElemFade m-0 p-1">
                <VehicleCard
                  onSubmitVehicle={onSubmitVehicle}
                  vehicle={vehicle}
                  CardData={CardData}
                  Theme={Theme}
                  type={props?.type}
                  lessthan768={lessthan768}
                  lessthan376={lessthan376}
                  fields={props?.fields}
                  PolicyCon={PolicyCon}
                  TypeReturn={props?.TypeReturn}
                  enquiry_id={props?.enquiry_id}
                  token={props?.token}
                  isEditable={isEditable}
                  zd_rti_condition={zd_rti_condition}
                />
              </div>
            ) : formVehicle === "summary" ? (
              <div className="m-0 p-1">
                <SummaryVehicle
                  summary={vehicle}
                  Theme={Theme}
                  temp={TempData}
                  lessthan768={lessthan768}
                  fields={props?.fields}
                />
              </div>
            ) : (
              <noscript />
            )}
          </div>
        </Card>
        {/*---------------x----End of Vehicle Details Card--------x-----------*/}
        {/*---------------------------Policy Details Card-----------------------*/}
        {(PolicyCon ||
          (PACondition && props?.fields?.includes("cpaOptOut"))) && (
          <Card
            title={
              formPrepolicy === "summary" ? (
                titlePrepolicySummary
              ) : (
                <Label colState={formPrepolicy}>
                  {PolicyCon &&
                  PACondition &&
                  props?.fields?.includes("cpaOptOut")
                    ? "Previous Policy & CPA Details"
                    : PolicyCon
                    ? "Previous Policy Details"
                    : "CPA Details"}
                </Label>
              )
            }
            backgroundColor={TitleState(formPrepolicy, "state")}
            paddingTop={lessthan768 ? "10px" : "6px"}
            borderRadius={TitleState(formPrepolicy, "radius")}
            removeBottomHeader={true}
            marginTop={formPrepolicy === "hidden" ? "5px" : ""}
            id="prepolicy"
          >
            <div style={TitleState(formPrepolicy, "animate")}>
              {formPrepolicy === "form" ? (
                <div className="ElemFade m-0 p-1">
                  <PolicyCard
                    onSubmitPrepolicy={onSubmitPrepolicy}
                    prepolicy={prepolicy}
                    CardData={CardData}
                    prevPolicyCon={PolicyCon}
                    PACon={PACondition && props?.fields?.includes("cpaOptOut")}
                    enquiry_id={props?.enquiry_id}
                    Theme={Theme}
                    type={props?.type}
                    OwnDamage={tpDetailsRequired ? true : false}
                    lessthan768={lessthan768}
                    lessthan376={lessthan376}
                    isNcbApplicable={isNcbApplicable}
                    TypeReturn={props?.TypeReturn}
                    fields={props?.fields}
                    PolicyValidationExculsion={PolicyValidationExculsion}
                    theme_conf={theme_conf}
                    isEditable={isEditable}
                  />
                </div>
              ) : formPrepolicy === "summary" ? (
                <div className="m-0 p-1">
                  <SummaryProposal
                    data={prepolicy}
                    lessthan768={lessthan768}
                    PolicyValidationExculsion={PolicyValidationExculsion}
                  />
                </div>
              ) : (
                <noscript />
              )}
            </div>
          </Card>
        )}
        {/*---------------x----End of Policy Details Card--------x-----------*/}
        {/*---------------x----Review & Submit--------x-----------*/}
        <div id="review-submit">
          {finalSubmit ? (
            <FinalSubmit
              TempData={TempData}
              submitProcess={submitProcess}
              theme_conf={theme_conf}
              ZD_preview_conditions={ZD_preview_conditions}
              zd_rti_condition={zd_rti_condition}
              setZd_rti_condition={setZd_rti_condition}
              Theme={Theme}
              breakinCase={props?.breakinCase}
              TypeReturn={props?.TypeReturn}
              type={props?.type}
              onFinalSubmit={onFinalSubmit}
              lessthan768={lessthan768}
              companyAlias={companyAlias}
            />
          ) : (
            <noscript />
          )}
        </div>
        {/*---------------x----End of Review & Submit--------x-----------*/}
      </div>
      {/*--------------------OTP Modal-------------------*/}
      <OTPPopup
        enquiry_id={props?.enquiry_id}
        show={show}
        onHide={() => [setShow(false), dispatch(clearShare(false))]}
        mobileNumber={owner?.mobileNumber}
        otpSuccess={otpSuccess}
        email={owner?.email}
        ckyc={
          props?.fields?.includes("ckyc") &&
          companyAlias &&
          companyAlias === "tata_aig"
            ? true
            : false
        }
        otp_id={otp_id}
        companyAlias={companyAlias}
        stage={"submit"}
        formVehicle={formVehicle}
      />
      {/*---------------x----OTP Modal--------x-----------*/}
      {/*---------------------Payment Modal--------------------*/}
      <PaymentModal
        ckycPresent={props?.fields?.includes("ckyc")}
        rsKycStatus={rsKycStatus}
        setrsKycStatus={props?.setrsKycStatus}
        companyAlias={companyAlias}
        submit={submit}
        enquiry_id={props?.enquiry_id}
        show={paymentModal}
        onHide={() => setPaymentModal(false)}
        type={props?.type}
        payment={payment}
        token={props?.token}
        _proposalPdf={props._proposalPdf}
        sendQuotes={props?.sendQuotes}
        setSendQuotes={props?.setSendQuotes}
        shareProposalPayment={props?.selectedQuotehareProposalPayment}
        setShareProposalPayment={props?.setShareProposalPayment}
        proposalHash={createHash({
          ...owner,
          ...nominee,
          ...vehicle,
          ...prepolicy,
        })}
        shareEvent={() =>
          //Analytics | proposal share
          //prettier-ignore
          _shareTracking(handleType, TempData, props?.enquiry_id, prepolicy, vehicle)
        }
        downloadEvent={() =>
          //Analytics | proposal pdf download
          //prettier-ignore
          _downloadTracking(handleType, TempData, props?.enquiry_id, prepolicy, vehicle)
        }
        fields={props?.fields}
      />
      {/*---------------x----Payment Modalt--------x-----------*/}
      {/*--------------------Pre-Submit Modal-------------------*/}
      <PreSubmit
        enquiry_id={props?.enquiry_id}
        show={presubmitModal}
        onHide={() => setPresubmitModal(false)}
        selection={() => selection()}
      />
      {/*---------------x----Pre-Submit Modal--------x-----------*/}
      {/*--------------------CKYC-Mandate Modal-------------------*/}
      <CkycMandate
        theme_conf={theme_conf && theme_conf}
        enquiry_id={props?.enquiry_id}
        show={ckycMandateModal}
        onHide={() => [
          setCkycMandateModal(false),
          _ckycMandateTracking(handleType, TempData),
        ]}
      />
      {/*---------------x----CKYC-Mandate Modal--------x-----------*/}
    </>
  );
};

export default FormSection;
