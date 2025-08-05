import React, { useEffect, useState } from "react";
import { Row } from "react-bootstrap";
import { Loader } from "components";
import { useForm } from "react-hook-form";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import { useHistory, useLocation } from "react-router";
import { useDispatch, useSelector } from "react-redux";
import {
  set_temp_data,
  Enquiry,
  SaveQuoteData,
  EncryptUser,
} from "modules/Home/home.slice";
import "./lead.scss";
import swal from "sweetalert";
import _ from "lodash";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import Realistic from "components/canvas-confetti/CanvasConfetti";
import "react-toastify/dist/ReactToastify.css";
import OTPPopup from "modules/proposal/otp/otp";
import { TypeReturn } from "modules/type";
import {
  Extn,
  ProcessName,
  _GA_Event,
} from "modules/Home/steps/lead-page/helper";
import { LeadForm } from "modules/Home/steps/lead-page/lead-form";
import { LeadBanner } from "modules/Home/steps/lead-page/lead-banner";
import Consent from "modules/consent/consent";
//validation
import { validation } from "./validation";
//custom-hooks
//prettier-ignore
import { useLeadGeneration, usePrefillAPI, useTokenData,
         useTokenValidation, useHandleSuccess, useGenerateOTP
        } from './lead-page-hooks';
//Request body
//prettier-ignore
import { saveCampaignData, saveTokenData, saveConsentData, 
         saveLeadData, onSubmitLead
        } from './constructor';
//Analytics
//prettier-ignore
import { _trackProfile, _trackVerification } from "analytics/user-creation.js/user-creation";

const ls = new SecureLS();
//theme variable
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

export const LeadPage = ({ type, lessthan767, _stToken, autoRegister }) => {
  const history = useHistory();
  const dispatch = useDispatch();
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const typeId = query.get("typeid");
  const journey_type = query.get("journey_type");
  const lead_source = query.get("lead_source");
  const utm_source = query.get("utm_source");
  const utm_medium = query.get("utm_medium");
  const utm_campaign = query.get("utm_campaign");
  const source = query.get("source");
  const vt = query.get("vt");
  const shared = query.get("shared");
  const [selected, setSelected] = useState(false);

  const {
    temp_data,
    enquiry_id,
    saveQuoteData,
    tokenData,
    tokenFailure,
    error,
    rd_link,
    leadLoad,
    theme_conf,
    tokenLoad,
  } = useSelector((state) => state.home);

  // validation schema
  const yupValidate = yup.object(validation(theme_conf, selected));

  const [btnDisable, setbtnDisable] = useState(false);
  const [consent, setConsent] = useState(true);
  const [show, setShow] = useState(false);
  const [sameNumber, setSameNumber] = useState(
    temp_data?.whatsappNo ? false : true
  );
  const [communicationUpdates, setCommunicationUpdates] = useState("");
  const { handleSubmit, register, errors, reset, watch, setValue, trigger } =
    useForm({
      resolver: yupResolver(yupValidate),
      mode: "onBlur",
      reValidateMode: "all",
    });

  //Lead-generation | jpurney stage updated to "Lead Generation",
  useLeadGeneration(dispatch, type, enquiry_id);

  //prefill previous input data
  usePrefillAPI(temp_data, reset, setSameNumber);

  //prefill using token data
  useTokenData(temp_data, tokenData, setValue);

  //Token validation and error handling incase of failure
  //prettier-ignore
  useTokenValidation(dispatch, token, journey_type, tokenFailure, rd_link, setbtnDisable)

  const [success, setSuccess] = useState(false);
  const [skip, setSkip] = useState(false);

  const otpSuccess = () => {
    if (enquiry_id?.enquiryId) {
      setSuccess(true);
      //Analytics | otp-verification | update user
      _trackVerification(type);
      swal({
        icon: "success",
        title: "Success",
        text: "Otp Verified Successfully",
      });
    }
  };

  //onSuccess
  useEffect(() => {
    if (enquiry_id?.enquiryId) {
      dispatch(set_temp_data({ enquiry_id: enquiry_id?.enquiryId }));
    }
    if (success || skip) {
      if (enquiry_id?.enquiryId) {
        dispatch(
          SaveQuoteData({
            //journey-category
            vt: vt,
            //Block back button in case of auto register
            ...(autoRegister && { blockBackButton: "Y" }),
            /*prettier-ignore*/
            ...saveCampaignData(lead_source, utm_source, utm_medium, utm_campaign, vt),
            ...saveTokenData(tokenData, token, journey_type),
            ...saveConsentData(consent),
            /*prettier-ignore*/
            ...saveLeadData(temp_data, enquiry_id, journey_type, type, source, typeId),
          })
        );
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enquiry_id, temp_data.firstName, success]);

  //on save success
  useHandleSuccess(
    dispatch,
    history,
    temp_data,
    type,
    show,
    saveQuoteData,
    enquiry_id,
    token,
    typeId,
    journey_type,
    _stToken,
    vt,
    autoRegister,
    theme_conf,
    tokenData,
    shared
  );

  useEffect(() => {
    if (error) {
      setbtnDisable(false);
    }
  }, [error]);

  //check values
  const fullName = watch("fullName");
  const mobileNo = watch("mobileNo");
  const emailId = watch("emailId");

  //prefill whatsapp No
  useEffect(() => {
    if (!temp_data?.whatsappNo && sameNumber) {
      setValue("whatsappNo", mobileNo);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mobileNo, sameNumber]);

  useEffect(() => {
    if (fullName) {
      ProcessName(fullName, setValue);
    } else {
      setValue("firstName", "");
      setValue("lastName", "");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fullName]);

  const [enqueryData, setEnqueryData] = useState({});
  const { isOtpRequired, ...otpData } = enqueryData;

  const onSubmit = (data) => {
    //GA event throw
    _GA_Event(TypeReturn("submit-click", type));
    if (
      data?.fullName ||
      data?.emailId ||
      data?.mobileNo ||
      data?.whatsappNo ||
      data?.isSkipped
    ) {
      //analytics-lead-creation
      //encryption of user ID
      if (import.meta.env.VITE_BROKER === "BAJAJ") {
        data?.mobileNo && dispatch(EncryptUser({ id: `${data?.mobileNo}` }));
        !data?.mobileNo && _trackProfile(data);
      }
      dispatch(
        set_temp_data({
          whatsappConsent: consent,
          ...data,
          analytics: data,
        })
      );
      //clearing registration
      //prettier-ignore
      const enqData = onSubmitLead(theme_conf, consent, data, tokenData, source, autoRegister);
      dispatch(Enquiry(enqData));
      setEnqueryData(enqData);
      // setTimeout(setbtnDisable(false), 3000);
    }
  };

  useEffect(() => {
    setbtnDisable(false);
  }, [show]);

  // otp modals
  useGenerateOTP(tokenData, theme_conf, enquiry_id, setShow);

  //Auto trigger Submit in case of autoregistering a skipped lead
  useEffect(() => {
    if (autoRegister) {
      setSkip(true);
      onSubmit({
        firstName: null,
        lastName: null,
        emailId: null,
        mobileNo: null,
        isSkipped: true,
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [autoRegister]);

  return !autoRegister ? (
    <>
      <div className="ml-4 my-4 w-100 mx-auto ElemFade">
        <Row className="text-center w-100 mx-auto d-flex justify-content-center">
          <div
            className={`mt-4 d-flex justify-content-center w-100 mx-auto ${
              !lessthan767 ? `flex-column` : ""
            }`}
          >
            <LeadBanner
              type={type}
              lessthan767={lessthan767}
              theme_conf={theme_conf}
            />
          </div>
        </Row>
        {(!leadLoad || (enquiry_id && enquiry_id?.corporate_id) || !skip) &&
        !tokenLoad ? (
          <>
            <LeadForm
              register={register}
              handleSubmit={handleSubmit}
              watch={watch}
              errors={errors}
              sameNumber={sameNumber}
              btnDisable={btnDisable}
              setbtnDisable={setbtnDisable}
              setSkip={setSkip}
              theme_conf={theme_conf}
              onSubmit={onSubmit}
              Theme={Theme}
              token={token}
              lessthan767={lessthan767}
              setSameNumber={setSameNumber}
              consent={consent}
              setConsent={setConsent}
              communicationUpdates={communicationUpdates}
              setCommunicationUpdates={setCommunicationUpdates}
              selected={selected}
              setSelected={setSelected}
              trigger={trigger}
              type={type}
            />
            <Extn.GlobalStyle />
            <>
              <Realistic />
              <div>
                <Extn.StyledContainer
                  autoClose={false}
                  hideProgressBar
                  position="top-center"
                  style={{
                    width: "100%",
                    marginTop: "-20px",
                    textAlign: "center",
                  }}
                />
              </div>
            </>
            {theme_conf?.broker_config?.consentModule && (
              <Consent
                selected={selected}
                setSelected={setSelected}
                lessthan767={lessthan767}
              />
            )}
            {show && !skip && (
              <OTPPopup
                enquiry_id={enquiry_id}
                show={show}
                onHide={() => setShow(false)}
                mobileNumber={mobileNo}
                email={emailId}
                otpSuccess={() => otpSuccess()}
                otpData={otpData}
                enqueryData={enqueryData}
                lead_otp
              />
            )}
          </>
        ) : (
          <Loader />
        )}
      </div>
    </>
  ) : (
    <Loader />
  );
};
