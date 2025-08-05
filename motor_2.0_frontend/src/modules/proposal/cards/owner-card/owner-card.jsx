/* eslint-disable */
import React, { useState, useEffect } from "react";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import { useForm, Controller } from "react-hook-form";
import _ from "lodash";
import { _haptics } from "utils";
import { clear, gender as clearGender } from "../../proposal.slice";
import CkycInfo from "../../modals/ckyc-info";
import { useDispatch, useSelector } from "react-redux";
import styled from "styled-components";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { Identities, identitiesCompany } from "../data";
import swal from "sweetalert";
import { useIdleTimer } from "react-idle-timer";
import OTPPopup from "../../otp/otp";
import CKYCLoader from "../kycpopup";
import { ownerValidation } from "../../form-section/validation";
import { redirection_ckyc, ovd_ckyc } from "../../proposal-constants";
import Info from "../../info/info-section";
import { calculateExpression } from "../../form-section/helper";
//Owner-Form
import { OwnerForm } from "./owner-form";
//CKYC
import { CkycUpdatedDetails } from "./ckyc/ckyc-details-update/ckyc-update-details";
//custom-hooks
import { useOngridPrefill } from "./custom-hooks/ongrid-prefill";
import { useCkycResponseHandler } from "./custom-hooks/ckyc-response-handler";
import { useCkycUploadStateReset } from "./custom-hooks/ckyc-reset";
import { useSamePOIAndPOA } from "./custom-hooks/same-poi-poa";
import { useCkycFetchedDetailsUpdate } from "./custom-hooks/update-fetched-ckyc";
import { useInitialCkycValue } from "./custom-hooks/initial-ckyc-value";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme1 = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const OwnerCard = ({
  onSubmitOwner,
  owner,
  CardData,
  conditionChk,
  Theme,
  lessthan768,
  lessthan376,
  enquiry_id,
  fields,
  type,
  token,
  setOwner,
  tempOwner,
  pan_file,
  setpan_file,
  poi_file,
  setpoi_file,
  poi_back_file,
  setpoi_back_file,
  poa_file,
  setpoa_file,
  poa_back_file,
  setpoa_back_file,
  form60,
  setForm60,
  form49,
  setForm49,
  photo,
  setPhoto,
  loading,
  setLoading,
  setResubmit,
  uploadFile,
  setuploadFile,
  resubmit,
  verifiedData,
  setVerifiedData,
  fileUploadError,
  ckycReset,
  setCkycReset,
  errorStep,
  isEditable,
  otpSuccess,
  formVehicle,
  panAvailability, 
  setPanAvailability
}) => {
  const {
    gender,
    occupation,
    temp_data,
    verifyCkycnum,
    ckycFields,
    ckycError,
  } = useSelector((state) => state.proposal);
  const dispatch = useDispatch();
  const { theme_conf } = useSelector((state) => state.home);
  /*--------------ckyc states-----------------*/
  const [identity, setIdentity] = useState();
  const [poi_identity, setpoi_identity] = useState();
  const [poa_identity, setpoa_identity] = useState();
  const [ckycValue, setckycValue] = useState();
  const [poi, setPoi] = useState(false);
  const [poa, setPoa] = useState(false);
  const [show, setShow] = useState(false);
  const [show2, setShow2] = useState(false);
  const [show1, setShow1] = useState(false);
  const [poi_disabled, setPoi_disabled] = useState(false);
  const [poa_disabled, setPoa_disabled] = useState(false);
  const [otp_id, setOtp_id] = useState();
  const [isRedirected, setIsRedirected] = useState(false);
  const [isCkycDetailsRejected, setisCkycDetailsRejected] = useState(false);
  const [customerDetails, setCustomerDetails] = useState();
  //CKYC states
  const [cinAvailability, setCinAvailability] = useState("YES");
  //CKYC Overlay
  const [overlay, showOverlay] = useState(true);
  /*------x-------ckyc states---------x--------*/

  /*----------------Validation Schema---------------------*/
  //prettier-ignore
  const yupValidate = yup.object({
    ...ownerValidation(
      temp_data, fields, ckycValue, poi, uploadFile,
      poi_identity, poa, poa_identity, identity, panAvailability
    ),
  });
  /*----------x------Validation Schema----------x-----------*/

  const { handleSubmit, register, errors, control, reset, setValue, watch } =
    useForm({
      defaultValues: !_.isEmpty(owner)
        ? owner
        : !_.isEmpty(CardData?.owner)
        ? CardData?.owner
        : {},
      resolver: yupResolver(yupValidate),
      mode: "onBlur",
      reValidateMode: "onBlur",
    });
  const companyAlias = !_.isEmpty(temp_data?.selectedQuote)
    ? temp_data?.selectedQuote?.companyAlias
    : "";

  // file validation from config - start
  const companyValidationRules = theme_conf?.broker_config?.file_ic_config.find(
    (rule) => rule.ic === companyAlias
  );

  // file extension validation message
  const acceptedExtensions =
    companyValidationRules && companyValidationRules?.acceptedExtensions;

  // file size calculation
  const maxFileSize =
    companyValidationRules && companyValidationRules?.maxFileSize;
  const maxSizeBytes = maxFileSize && calculateExpression(maxFileSize);
  const maxSizeMB = maxSizeBytes / 1024 / 1024;

  const acceptedExt = acceptedExtensions
    ? acceptedExtensions.join(", ")
    : [".jpg", ".jpeg", ".png"].join(", ");
  const maxSize = maxSizeMB ? maxSizeMB : 2;

  const fileValidationText = `Upload (${acceptedExt}) file up to ${maxSize}MB.`;

  // file validation from config end
  const emailId = watch("email");
  const mobileNoLead = watch("mobileNumber");
  const allFieldsReadOnly =
    temp_data?.selectedQuote?.isRenewal === "Y" && !isEditable;

  const disclaimer =
    theme_conf?.ckyc_redirection_message ||
    (import.meta.env.VITE_BROKER === "TATA"
      ? "You are being redirected to the Insurer website, for completing the Offline KYC process. TMIBASL has limited control over third-party websites and our privacy policy may not apply to them. Please ensure utmost care while sharing the details"
      : "If you choose to redirect for verification, you will be redirected to an external website.");

  //clear existing data
  useEffect(() => {
    dispatch(clearGender([]));
  }, []);

  //display ckyc overlay
  useEffect(() => {
    if (
      [...redirection_ckyc, ...ovd_ckyc] &&
      !(
        temp_data?.userProposal?.isCkycVerified === "Y" ||
        resubmit ||
        !!verifyCkycnum?.verification_status
      )
    ) {
      showOverlay(true);
    }
  }, [temp_data]);

  //prefill
  useEffect(() => {
    if (_.isEmpty(owner) && !_.isEmpty(CardData?.owner)) {
      reset(CardData?.owner);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [CardData.owner]);

  const handleOnIdle = () => {
    companyAlias === "edelweiss" &&
      isRedirected &&
      swal("Info", "Page update is required", "info").then(() => {
        window.location.reload();
      });
  };

  //timer for edelweiss
  const { getRemainingTime, getLastActiveTime } = useIdleTimer({
    timeout: 2 * 1000 * 60,
    onIdle: handleOnIdle,
    debounce: 500,
  });
  /*--------- CKYC---------*/
  //CkycReset
  useEffect(() => {
    if (ckycReset && ckycValue === "YES") {
      setckycValue("NO");
      setCkycReset(false);
    }
  }, [ckycReset, ckycValue]);

  //ckyc declarations
  const ownerIdentity = watch("identity");
  const poiIdentity = watch("poi_identity");
  const poaIdentity = watch("poa_identity");

  useEffect(() => {
    setIdentity(ownerIdentity);
    setpoi_identity(poiIdentity);
    setpoa_identity(poaIdentity);
    identity && setValue(identity, "");
    poi_identity && setValue(poi_identity, "");
    poa_identity && setValue(poa_identity, "");
  }, [ownerIdentity, poiIdentity, poaIdentity]);

  //fields to be disabled after ckyc verification completion
  //prettier-ignore
  const fieldsNonEditable =
    (temp_data?.userProposal?.isCkycVerified === "Y" || resubmit) &&
    (["hdfc_ergo", "royal_sundaram", "new_india", "edelweiss", "iffco_tokio",
      "reliance", "icici_lombard", "sbi", "future_generali"].includes(companyAlias));

  //fields not to be disabled for some ics
  const fieldEditable = true;
  // If we are getting ckyc verified then from userproposal thenstomer_details
  const prefillAndResubmit =
    companyAlias === "liberty_videocon" || companyAlias === "edelweiss";
  //ICs in which fields needs to be read only if fetch service is used.
  const renewalUploadReadOnly =
    temp_data?.isRenewalUpload &&
    ["liberty_videocon"].includes(temp_data?.selectedQuote?.companyAlias);

  useCkycFetchedDetailsUpdate({
    temp_data,
    prefillAndResubmit,
    setVerifiedData,
    setValue,
    owner,
    setResubmit,
  });

  const selectedIdentity = ownerIdentity
    ? Number(temp_data?.ownerTypeId) === 1
      ? Identities(companyAlias, uploadFile).find(
          (each) => each.id === identity
        )
      : identitiesCompany(companyAlias, uploadFile)?.find(
          (each) => each.id === identity
        )
    : "";
  const selectedpoiIdentity =
    poiIdentity && temp_data
      ? Number(temp_data?.ownerTypeId) === 1
        ? Identities(companyAlias, uploadFile).find(
            (each) => each.id === poi_identity
          )
        : identitiesCompany(companyAlias, uploadFile)?.find(
            (each) => each.id === poi_identity
          )
      : "";
  const selectedpoaIdentity =
    poaIdentity && temp_data
      ? Number(temp_data?.ownerTypeId) === 1
        ? Identities(companyAlias, uploadFile).find(
            (each) => each.id === poa_identity
          )
        : identitiesCompany(companyAlias, uploadFile)?.find(
            (each) => each.id === poa_identity
          )
      : "";

  //if poa and poi are same
  useSamePOIAndPOA({
    poiIdentity,
    poaIdentity,
    selectedpoiIdentity,
    selectedpoaIdentity,
    setPoa_disabled,
    setPoi_disabled,
    poi_identity,
    poa_identity,
    setValue,
    watch,
  });

  const ckycTypes =
    Number(temp_data?.ownerTypeId) === 1
      ? Identities(companyAlias, uploadFile)
      : identitiesCompany(companyAlias, uploadFile);

  //Success/error handling after ckyc verification status
  useCkycResponseHandler({
    temp_data,
    verifyCkycnum,
    enquiry_id,
    setOwner,
    tempOwner,
    setOtp_id,
    otp_id,
    show1,
    setShow1,
    setResubmit,
    setValue,
    setCustomerDetails,
    ckycValue,
    fields,
    uploadFile,
    setShow,
    setuploadFile,
    setckycValue,
    identity,
    setIsRedirected,
    setLoading,
    disclaimer,
    setVerifiedData,
    setShow1
  });

  useEffect(() => {
    ckycError &&
      swal("Error", ckycError, "error", {
        closeOnClickOutside: false,
      }).then(() => {
        dispatch(clear("ckycError"));
        setValue("ckycNumber", "");
        identity && setValue(identity, "");
      });
  }, [ckycError]);

  //ckyc value reset
  useCkycUploadStateReset({
    temp_data,
    ckycValue,
    fields,
    setuploadFile,
    setpoa_file,
    setpoi_file,
    setForm60,
    setPoa,
    setPoi,
  });

  //Setting CKYC Number as default on initial load of component and prefill if present
  const ckycValuePresent =
    watch("isckycPresent") || CardData?.owner?.isckycPresent;
  //Setting CKYC Availability Initially
  useInitialCkycValue({
    companyAlias,
    setckycValue,
    CardData,
    ckycValuePresent,
    fields,
  });

  /*----x---- CKYC----x----*/

  /*----------------gender config-----------------------*/
  const [radioValue, setRadioValue] = useState(watch("gender"));
  /*--------x-------gender config------------x----------*/
  //ongrid prefill
  useOngridPrefill({ temp_data, CardData, owner, setValue });

  const showCkycInstructions =
    !lessthan768 &&
    [...redirection_ckyc, ...ovd_ckyc].includes(companyAlias) &&
    !(
      temp_data?.userProposal?.isCkycVerified === "Y" ||
      resubmit ||
      !!verifyCkycnum?.verification_status
    ) &&
    overlay;

  return (
    <>
      <OwnerForm
        temp_data={temp_data}
        owner={owner}
        CardData={CardData}
        handleSubmit={handleSubmit}
        onSubmitOwner={onSubmitOwner}
        register={register}
        errors={errors}
        resubmit={resubmit}
        watch={watch}
        fields={fields}
        allFieldsReadOnly={allFieldsReadOnly}
        verifiedData={verifiedData}
        fieldsNonEditable={fieldsNonEditable}
        Controller={Controller}
        control={control}
        setValue={setValue}
        enquiry_id={enquiry_id}
        ckycValue={ckycValue}
        uploadFile={uploadFile}
        radioValue={radioValue}
        setRadioValue={setRadioValue}
        gender={gender}
        panAvailability={panAvailability}
        setPanAvailability={setPanAvailability}
        setpan_file={setpan_file}
        setckycValue={setckycValue}
        isCkycDetailsRejected={isCkycDetailsRejected}
        cinAvailability={cinAvailability}
        setCinAvailability={setCinAvailability}
        renewalUploadReadOnly={renewalUploadReadOnly}
        selectedIdentity={selectedIdentity}
        identity={identity}
        ckycFields={ckycFields}
        poi_file={poi_file}
        setpoi_file={setpoi_file}
        poi_back_file={poi_back_file}
        setpoi_back_file = {setpoi_back_file}
        fileUploadError={fileUploadError}
        fileValidationText={fileValidationText}
        poi_identity={poi_identity}
        ckycTypes={ckycTypes}
        poi_disabled={poi_disabled}
        selectedpoiIdentity={selectedpoiIdentity}
        poa_file={poa_file}
        setpoa_file={setpoa_file}
        poa_back_file={poa_back_file}
        setpoa_back_file={setpoa_back_file}
        poa_identity={poa_identity}
        selectedpoaIdentity={selectedpoaIdentity}
        poa_disabled={poa_disabled}
        photo={photo}
        setPhoto={setPhoto}
        lessthan768={lessthan768}
        acceptedExt={acceptedExt}
        form60={form60}
        form49={form49}
        setForm60={setForm60}
        setForm49={setForm49}
        token={token}
        type={type}
        fieldEditable={fieldEditable}
        conditionChk={conditionChk}
        Theme={Theme}
        pan_file={pan_file}
        errorStep={errorStep}
        occupation={occupation}
        loading={loading}
        lessthan376={lessthan376}
        setuploadFile={setuploadFile}
      />
      <CkycInfo
        show={show2}
        onHide={() => {
          setShow2(false);
          setOwner(tempOwner);
        }}
        noCloseIcon
      />
      <OTPPopup
        enquiry_id={enquiry_id}
        show={show1}
        onHide={() => setShow1(false)}
        mobileNumber={mobileNoLead}
        otpSuccess={() => otpSuccess()}
        email={emailId}
        ckyc={companyAlias === "magma"}
        otp_id={otp_id}
        companyAlias={companyAlias}
        stage={"owner card"}
      />
      <CkycUpdatedDetails
        temp_data={temp_data}
        tempOwner={tempOwner}
        customerDetails={customerDetails}
        setVerifiedData={setVerifiedData}
        setValue={setValue}
        gender={gender}
        setRadioValue={setRadioValue}
        setisCkycDetailsRejected={setisCkycDetailsRejected}
        setShow={setShow}
        show={show}
        setuploadFile={setuploadFile}
        setckycValue={setckycValue}
        fields={fields}
        lessthan768={lessthan768}
        setResubmit={setResubmit}
      />
      {showCkycInstructions ? (
        <Info
          showOverlay={showOverlay}
          isRedirection={redirection_ckyc.includes(companyAlias)}
        />
      ) : (
        <noscript />
      )}

      {fields.includes("ckyc") && (
        <CKYCLoader
          show={loading}
          TempData={temp_data}
        />
      )}
    </>
  );
};
export const StyledDatePicker = styled.div`
  .dateTimeOne .date-header {
    background: ${Theme1
      ? `${Theme1?.reactCalendar?.background} !important`
      : "#4ca729 !important"};
    border: ${Theme1
      ? `1px solid ${Theme1?.reactCalendar?.background} !important`
      : "1px solid #4ca729 !important"};
  }
  .dateTimeOne .react-datepicker__day:hover {
    background: ${Theme1
      ? `${Theme1?.reactCalendar?.background} !important`
      : "#4ca729 !important"};
    border: ${Theme1
      ? `1px solid ${Theme1?.reactCalendar?.background} !important`
      : "1px solid #4ca729 !important"};
  }
`;

export default OwnerCard;
