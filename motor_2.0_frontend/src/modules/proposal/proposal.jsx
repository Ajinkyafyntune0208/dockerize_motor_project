import React, { useEffect, useState, useMemo } from "react";
import { useLocation, useHistory } from "react-router";
import swal from "sweetalert";
import { BackButton, getBrokerLogoUrl, FloatButton } from "components";
import _ from "lodash";
import InfoCard from "modules/proposal/cards/info-card/info-card";
import FormSection from "modules/proposal/form-section/form-section";
import { useDispatch, useSelector } from "react-redux";
//prettier-ignore
import { Url, DuplicateEnquiryId, adrila } from "modules/proposal/proposal.slice";
//prettier-ignore
import { reloadPage, Disable_B2C, journeyProcessProposal, PostTransaction, fetchToken } from "utils";
import { useMediaPredicate } from "react-media-hook";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
//prettier-ignore
import { LinkTrigger, clear as clr, getValidationConfig } from "modules/Home/home.slice";
import { TypeReturn } from "modules/type";
import { useIdleTimer } from "react-idle-timer";
import TimeoutPopup from "modules/quotesPage/AbiblPopup/TimeoutPopup";
//prettier-ignore
import { H4Tag, DivTag1, DivTag2, RowTag, StyledDiv } from 'modules/proposal/style'
//request constructs
import ProposalSkeleton from "./loader/proposal-skeleton";
import { _proposalPdf } from "./proposal-pdf/proposal-pdf";
//prettier-ignore
import { useEnquiryValidation, useProposalExpiry, usePrefill,
         useCpaStatusClear, useAdrilaCall, useAvailableAddons,
         useAdrilaPrefill, useReloadPostEnquiryDuplication,
         useFieldConfig, useEnquiryIncrement, useErrorHandling,
         useAccessControl, useProfileTracking,
         useWaierExpiry, useProposalTracking
        } from "./proposal-hooks";
import { _trackProfile } from "analytics/user-creation.js/user-creation";
import SendQuotes from "components/Popup/sendQuote/SendQuotes";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

export const Proposal = (props) => {
  const [sendQuotes, setSendQuotes] = useState(false); //share quotes state
  const [shareProposalPayment, setShareProposalPayment] = useState(false); //share payment modal state
  const [paymentModal, setPaymentModal] = useState(false); // payment confirmation modal
  const [rsKycStatus, setrsKycStatus] = useState();
  const history = useHistory();
  const dispatch = useDispatch();
  //prettier-ignore
  const { temp_data: temp, error_other, prefillLoad, duplicateEnquiry, fields,
          breakinEnquiry, rskycStatus, adrilaStatus, errorSpecific, checkAddon,
          ckycErrorData
        } = useSelector((state) => state.proposal);
  const { typeAccess } = useSelector((state) => state.login);
  const { saveQuoteData, theme_conf, encryptUser } = useSelector(
    (state) => state.home
  );
  const { cpaSet } = useSelector((state) => state.quotes);
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const typeId = query.get("typeid");
  const journey_type = query.get("journey_type");
  const key = query.get("key");
  const shared = query.get("shared");
  const icr = query.get("icr");
  const dropout =
    query.get("dropout") ||
    (["Proposal Accepted", "Payment Initiated", "payment failed"].includes(
      ["payment failed"].includes(temp?.journeyStage?.stage.toLowerCase())
        ? temp?.journeyStage?.stage.toLowerCase()
        : temp?.journeyStage?.stage
    )
      ? "true"
      : false);
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const selectedQuote = !_.isEmpty(temp?.selectedQuote)
    ? temp?.selectedQuote
    : {};
  const Additional = !_.isEmpty(temp?.addons) ? temp?.addons : {};
  const { type } = props?.match?.params;
  const _stToken = fetchToken();
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const showBackButton =
    (!(temp?.userProposal?.isBreakinCase === "Y") ||
      (TypeReturn(type) === "bike" &&
        temp?.userProposal?.isBreakinCase === "Y" &&
        selectedQuote?.companyAlias !== "godigit" &&
        selectedQuote?.companyAlias !== "icici_lombard" &&
        selectedQuote?.companyAlias !== "united_india")) &&
    import.meta.env?.VITE_BROKER !== "ABIBL" &&
    !lessthan767 &&
    !icr;

  let userAgent = navigator.userAgent;
  let isMobileIOS = false; //initiate as false
  // device detection
  if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream && lessthan767) {
    isMobileIOS = true;
  }
  /*--------------- Access-Control-------------------*/
  //Access-Control
  useAccessControl(history, typeAccess, type);

  useEffect(() => {
    rskycStatus && setrsKycStatus(rskycStatus);
  }, [rskycStatus]);

  const checkSellerType = !_.isEmpty(temp?.agentDetails)
    ? temp?.agentDetails?.map((seller) => seller.sellerType)
    : [];

  //---------------Temp B2C block-------------------------
  useEffect(() => {
    Disable_B2C(temp, checkSellerType, token, journey_type, true, theme_conf);
  }, [token, temp]);

  useEffect(() => {
    document.body.style.position = "relative";
    document.body.style.height = "auto";
    document.body.style.overflowY = "auto";
  }, []);

  //Link-Click & Delivery
  useEffect(() => {
    key && dispatch(LinkTrigger({ key: key }));
  }, [key]);

  //Analytics | user profile tracking
  useProfileTracking(dispatch, temp, encryptUser);
  /*---------------- back button---------------------*/
  const back = () => {
    history.push(
      `/${type}/quotes?enquiry_id=${enquiry_id}${
        token ? `&xutm=${token}` : ``
      }${typeId ? `&typeid=${typeId}` : ``}${
        journey_type ? `&journey_type=${journey_type}` : ``
      }${_stToken ? `&stToken=${_stToken}` : ``}${
        shared ? `&shared=${shared}` : ``
      }`
    );
  };
  /*----------x----- back button-------x-------------*/
  //no enquiry id
  const urlParams = { enquiry_id, journey_type, _stToken, typeId, shared };
  useEnquiryValidation(dispatch, history, { ...urlParams, type, token });

  //prefill Api
  usePrefill(dispatch, enquiry_id);

  //onError
  //prettier-ignore
  useErrorHandling(dispatch, temp, enquiry_id, error_other, errorSpecific, ckycErrorData);

  //clearing cpa status
  useCpaStatusClear(dispatch, enquiry_id, cpaSet);

  //clearing adrila status
  useEffect(() => {
    dispatch(adrila(null));
  }, []);

  //on adrila call on page
  useAdrilaCall(dispatch, enquiry_id, adrilaStatus);

  //get available addons/accessories
  useAvailableAddons(dispatch, temp, enquiry_id);

  //Adreilla Prefill
  let _typeReturn = TypeReturn(type);
  useAdrilaPrefill(dispatch, temp, enquiry_id, _typeReturn);

  // On breakin Status or resubmission after payment success
  useEffect(() => {
    PostTransaction(temp, false, false, enquiry_id, true, _stToken);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp?.journeyStage?.stage]);

  //Url
  useMemo(() => {
    journeyProcessProposal(dispatch, Url, enquiry_id, temp, "Proposal Drafted");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp?.journeyStage?.stage]);

  /*
  This hook has two useEffects 
  *The first useEffect monitors changes in the duplicateEnquiry object and reloads the page with specific query parameters if the enquiryId is present. 
  *The second useEffect is similar but focuses on the breakinEnquiry object, reloading the page with relevant parameters when its enquiryId is present. 
  *Both includes cleanup logic to clear the duplicate enquiry state.
  */
  //Grouping parameters
  const linkParams = { type, token, journey_type, typeId, _stToken, shared };
  const otherParams = { temp, duplicateEnquiry, breakinEnquiry, dropout };
  useReloadPostEnquiryDuplication(dispatch, linkParams, otherParams);

  const GenerateDulicateEnquiry = (breakinExp) => {
    //If Payment has been initiated and left incomplete then a new Enquiry Id is generated for the user.
    if (
      temp?.journeyStage?.stage === "Payment Initiated" ||
      temp?.journeyStage?.stage.toLowerCase() === "payment failed" ||
      breakinExp
    ) {
      dispatch(
        DuplicateEnquiryId(
          {
            enquiryId: enquiry_id,
            ...(breakinExp && { isBreakinExpired: true }),
          },
          breakinExp
        )
      );
    }
  };

  const innerWidth = window.innerWidth;

  //Field Configurator
  useFieldConfig(dispatch, temp, type);

  //This hook is used to handle proposal expiry
  useProposalExpiry(
    dispatch,
    temp,
    GenerateDulicateEnquiry,
    type,
    enquiry_id,
    setPaymentModal
  );

  //This hook is used to handle breakin case for IC Waiver for x days
  useWaierExpiry(temp);

  //clearing saveQuotedata on load
  useEffect(() => {
    dispatch(clr("saveQuoteData"));
  }, []);

  //after saveQuoteData
  useEffect(() => {
    if (saveQuoteData) {
      swal("Please Note", "This proposal has expired", "info").then(() =>
        reloadPage(window.location.href.replace(/proposal-page/g, "quotes"))
      );
    }
    return () => {
      dispatch(clr("saveQuoteData"));
    };
  }, [saveQuoteData]);

  //proposal-validations
  useEffect(() => {
    dispatch(getValidationConfig());
  }, []);

  //Inactivity Timeout
  const [timerShow, setTimerShow] = useState(false);
  const handleOnIdle = () => {
    setTimerShow(true);
  };

  // eslint-disable-next-line no-unused-vars
  const { getRemainingTime, getLastActiveTime } = useIdleTimer({
    timeout:
      (theme_conf?.broker_config?.time_out * 1
        ? theme_conf?.broker_config?.time_out * 1
        : 15) *
      1000 *
      60,
    onIdle: handleOnIdle,
    debounce: 500,
  });

  //Increment enquiry id
  useEnquiryIncrement(temp, GenerateDulicateEnquiry, theme_conf);

  //proposal pdf ext
  //prettier-ignore
  const _proposalPdfExt = () =>  _proposalPdf(temp, checkAddon, TypeReturn(type), fields, Theme, enquiry_id);

  //Analytics | Proposal Page Tracking
  useProposalTracking(temp);

  return (
    <StyledDiv innerWidth={innerWidth} isMobileIOS={isMobileIOS}>
      <div
        className="backBtn"
        style={!lessthan767 ? { paddingBottom: "30px" } : {}}
      >
        {showBackButton && (
          <BackButton
            type="button"
            onClick={back}
            id="back_proposal"
            style={lessthan767 ? {} : { zIndex: "9999" }}
            BlockLayout={theme_conf?.isIpBlocked}
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              className=""
              viewBox="0 0 24 24"
            >
              <path d="M11.67 3.87L9.9 2.1 0 12l9.9 9.9 1.77-1.77L3.54 12z" />
              <path d="M0 0h24v24H0z" fill="none" />
            </svg>
            <text style={{ color: "black" }}>Back</text>
          </BackButton>
        )}
      </div>
      {!prefillLoad && !_.isEmpty(fields) ? (
        <RowTag className="row-dimension-design" id="pdf-content">
          <DivTag1
            className={`col-12 col-lg-3 col-xs-12 col-sm-12 col-md-4 ${
              lessthan767 ? "mb-4" : ""
            }`}
          >
            <InfoCard
              selectedQuote={selectedQuote}
              enquiry_id={enquiry_id}
              Additional={Additional}
              type={type}
              token={token}
              Theme={Theme}
              breakinCase={temp?.userProposal?.isBreakinCase === "Y"}
              lessthan767={lessthan767}
              GenerateDulicateEnquiry={GenerateDulicateEnquiry}
              journey_type={journey_type}
              typeId={typeId}
              icr={icr}
              TypeReturn={TypeReturn}
              shared={shared}
            />
          </DivTag1>
          <DivTag2 className="col-12 col-lg-9 col-sm-12 col-xs-12 col-md-8">
            <H4Tag>{"Almost Done! Just a few more details."}</H4Tag>
            <FormSection
              rsKycStatus={rsKycStatus}
              setrsKycStatus={setrsKycStatus}
              temp={temp}
              Additional={Additional}
              token={token}
              enquiry_id={enquiry_id}
              dropout={dropout}
              type={type}
              breakinCase={temp?.userProposal?.isBreakinCase === "Y"}
              getLogoUrl={getBrokerLogoUrl}
              typeId={typeId}
              GenerateDulicateEnquiry={GenerateDulicateEnquiry}
              DuplicateEnquiryId={DuplicateEnquiryId}
              journey_type={journey_type}
              icr={icr}
              fields={fields ? fields : []}
              TypeReturn={TypeReturn}
              _proposalPdf={_proposalPdfExt}
              setPaymentModal={setPaymentModal}
              paymentModal={paymentModal}
              sendQuotes={sendQuotes}
              setSendQuotes={setSendQuotes}
              shareProposalPayment={shareProposalPayment}
              setShareProposalPayment={setShareProposalPayment}
            />
          </DivTag2>
        </RowTag>
      ) : (
        <ProposalSkeleton />
      )}
      <TimeoutPopup
        enquiry_id={enquiry_id}
        show={timerShow}
        onClose={() => setTimerShow(false)}
        type={_typeReturn}
        TempData={temp}
      />
      <FloatButton />
      {sendQuotes && (
        <SendQuotes
          show={sendQuotes}
          onClose={setSendQuotes}
          type={type}
          shareProposalPayment={shareProposalPayment}
          setShareProposalPayment={setShareProposalPayment}
        />
      )}
    </StyledDiv>
  );
};
