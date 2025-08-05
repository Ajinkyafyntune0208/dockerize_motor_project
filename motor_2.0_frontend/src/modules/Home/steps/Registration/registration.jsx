import React, { useEffect, useState } from "react";
import { Row, Col } from "react-bootstrap";
import { Button, BackButton, Loader, SimpleModal } from "components";
import { useForm } from "react-hook-form";
import { yupResolver } from "@hookform/resolvers/yup";
import { useHistory } from "react-router";
import { useDispatch, useSelector } from "react-redux";
import swal from "sweetalert";
import { journeyProcess, _haptics } from "utils";
import _ from "lodash";
import { Url, DuplicateEnquiryId } from "modules/proposal/proposal.slice";
import ThemeObj from "modules/theme-config/theme-config";
//prettier-ignore
import { CancelAll, clear as clr, setQuotesList } from "modules/quotesPage/quote.slice";
import SecureLS from "secure-ls";
//prettier-ignore
import { SaveQuoteData, set_temp_data, Category,
         getFastLaneDatas, setFastLane, tabClick as TabClick,
        } from "modules/Home/home.slice";
import JourneyMismatch from "./journey-mismatch";
//prettier-ignore
import { vahaanConstants, journeyMismatchFn, _bhCheck,
         enforceLogin, isRegValid, _isAndroidWebView, noBack
        } from "./helper";
import { yupValidate } from "./validation";
//prettier-ignore
import { GlobalStyle, StyledH4, StyledH3, StyledBack, Stylediv,
         SpanTag, TextTag
        } from "./styles";
//custom-hooks
//prettier-ignore
import { useFrontendURL, useFastlaneResponse, usePrefill_RC, 
         useDuplicateEnquiry, usePostTransactionHandler,
         useSuccessRedirection
         } from "./registration-hooks";
//prettier-ignore
import { getVahaanPayload, setFastlaneRequest, setSaveQuoteRequest,
         onSubmitRegistration, onSubmitSave,
        } from "./reg-constructor";
import RenewalButton from "./renewal-button";
import RegInput from "./reg-input";
//Analytics
import {
  _rcTracking,
  _newBusiness,
  _norcTracking,
  _pageVisit,
} from "analytics/input-pages/registration-tracking";
import { useLocation } from "react-router";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

export const Registration = ({
  enquiry_id,
  type,
  token,
  errorProp,
  lessthan767,
  lessthan400,
  lessthan330,
  typeId,
  isMobileIOS,
  journey_type,
  stepper1,
  TypeReturn,
  reg_no_url,
  _stToken,
  shared,
}) => {
  /*---------------- back button---------------------*/
  const back = () => {
    dispatch(setFastLane(false));
    history.push(
      `/${type}/lead-page${typeId ? `?typeid=${typeId}` : ``}${
        journey_type ? `&journey_type=${journey_type}` : ``
      }`
    );
  };
  /*----------x----- back button-------x-------------*/
  const history = useHistory();
  const dispatch = useDispatch();
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const rcNum = query.get("rcNum");
  const url = new URL(window.location.href);
  const params = new URLSearchParams(url.search);

  //prettier-ignore
  const { temp_data, saveQuoteData, category, fastLaneData,
          frontendurl, tabClick, theme_conf, overrideMsg, vahaanConfig
        } = useSelector((state) => state.home);
  const { duplicateEnquiry } = useSelector((state) => state.proposal);

  const [btnDisable, setbtnDisable] = useState(false);
  const [buffer, setBuffer] = useState(false);
  const [preventAutoSubmit, setPreventAutoSubmit] = useState(false);

  const { register, errors, setValue, watch } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });

  //Fetching frontend links of all products
  useFrontendURL(dispatch, enquiry_id, frontendurl);
  //Is Operation System IOS
  const isAndroidWebView = _isAndroidWebView(temp_data);

  //modal state
  const [show, setShow] = useState(false);
  const [bhModal, setBhModal] = useState(false);

  useEffect(() => {
    //Cancel token
    dispatch(CancelAll(true));
    //quotes clr
    dispatch(setQuotesList([]));
    //quotes slice state clear
    dispatch(clr());
  }, []);

  //journey mismatch
  useEffect(() => {
    if (
      (fastLaneData?.ft_product_code !== TypeReturn(type) ||
        fastLaneData?.sub_section) &&
      vahaanConstants(vahaanConfig, type) &&
      fastLaneData?.status !== 101
    ) {
      setBuffer(false);
      setbtnDisable(false);
      setShow(fastLaneData?.sub_section || fastLaneData?.ft_product_code);
    }
  }, [fastLaneData]);

  //Url
  //prettier-ignore
  useEffect(() => {
    //CRM Skip change 16-06-23
    journeyProcess(dispatch, Url, DuplicateEnquiryId ,enquiry_id, temp_data, "Lead Generation", false, false, import.meta.env.VITE_BROKER === "BAJAJ" &&  TypeReturn(type))
  }, [temp_data?.journeyStage?.stage]);

  //generate new enquiry id.
  useDuplicateEnquiry(
    dispatch,
    duplicateEnquiry,
    type,
    token,
    typeId,
    journey_type,
    _stToken,
    shared
  );

  //Journey already submitted
  usePostTransactionHandler(temp_data, enquiry_id, _stToken);

  //redirected Prefill (Renewbuy)
  useEffect(() => {
    if (reg_no_url && reg_no_url !== "NULL") {
      setValue("regNo", reg_no_url);
    }
  }, [reg_no_url]);

  //prefill
  const regIpCheck = watch("regNo") || "";

  //prefilling the registration number after reload
  useEffect(() => {
    if (rcNum) {
      setValue("regNo", rcNum);
    }
  }, [rcNum]);

  //auto procedding if rc number is prefilled
  useEffect(() => {
    if (rcNum && regIpCheck && !preventAutoSubmit) {
      onSubmit(1);
    }
  }, [regIpCheck, rcNum]);
  //Check for BH
  const isRegBH = temp_data?.regNo && temp_data?.regNo[0] * 1;
  //THis hook is used to prefill the registration number input.
  usePrefill_RC(temp_data, regIpCheck, isRegBH, setValue);

  //onSuccess | handle redirection
  //grouping parameters
  const stateParams = {
    temp_data,
    saveQuoteData,
    fastLaneData,
    theme_conf,
    setPreventAutoSubmit,
  };
  const urlParams = {
    enquiry_id,
    token,
    typeId,
    _stToken,
    params,
    rcNum,
  };
  const typeParams = { TypeReturn, type, journey_type, TabClick };
  const otherParams = { dispatch, setBuffer, history, setValue, overrideMsg };

  useSuccessRedirection(stateParams, urlParams, typeParams, otherParams);

  //onError
  useEffect(() => {
    if (errorProp) {
      setbtnDisable(false);
      setBuffer(false);
    }
  }, [errorProp]);

  //Product SubType (only when product category is not CV)
  useEffect(() => {
    if (TypeReturn(type) !== "cv" && TypeReturn(type)) {
      dispatch(Category({ productType: TypeReturn(type) }));
    }
  }, [type]);

  // const [limit, setLimit] = useState(false);
  useEffect(() => {
    if (!_.isEmpty(temp_data)) {
      //Analytics CleverTap
      !token && _pageVisit(TypeReturn(type), temp_data);
      // setLimit(true);
    }
  }, [temp_data]);

  const regIp = watch("regNo") || "";
  const regSplit = regIp && regIp.split("-");

  //varibles for reg inputs
  let regNo1 = regIp
    ? regSplit.length >= 2
      ? `${regSplit[0]}-${regSplit[1]}`
      : ""
    : "";
  regNo1 = regNo1 ? regNo1.replace(/\s/g, "") : ""; //trim white-spaces
  let regNo2 = regIp ? (regSplit.length === 4 ? regSplit[2] : "") : "";
  regNo2 = regNo2 ? regNo2.replace(/\s/g, "") : ""; //trim white-spaces
  let regNo3 = regIp
    ? regSplit.length === 4
      ? regSplit[3]
      : regSplit.length === 3
      ? regSplit[2]
      : ""
    : "";

  //fastlane logic to be discarded
  const onSubmitFastLane = (AltRc) => {
    if (
      isAndroidWebView &&
      import.meta.env.VITE_BROKER === "RB" &&
      !localStorage?.SSO_user_motor
    ) {
      enforceLogin(setbtnDisable, setBuffer);
    } else {
      let registration_no =
        regIp && regIp[0] * 1
          ? regIp
          : regNo2
          ? `${regNo1}-${regNo2}-${regNo3}`
          : `${regNo1}--${regNo3}`;
      if (regIp && !(regIp[0] * 1 && regNo1 && regNo3)) {
        dispatch(
          set_temp_data({
            regNo: `${regNo1}-${regNo2}-${regNo3}`,
            regNo1,
            regNo2,
            regNo3,
          })
        );
      }
      //Analytivs
      _rcTracking(registration_no, TypeReturn(type), temp_data);
      setbtnDisable(true);
      tabClick && dispatch(TabClick(false));
      if (vahaanConstants(vahaanConfig, type)) {
        setBuffer(true);

        const data = {
          ...getVahaanPayload(
            temp_data,
            enquiry_id,
            registration_no,
            journey_type,
            TypeReturn(type)
          ),
        };
        dispatch(getFastLaneDatas(data));
      } else {
        onSubmit(1);
      }
    }
  };

  //Dispatch BH RC submit
  const _bhReg = (journeyType) => {
    let _typeReturn = TypeReturn(type);
    let categoryParams = { journeyType, regIp, category, type };
    //prettier-ignore
    let otherLinkParams = { token, enquiry_id, _typeReturn, journey_type };
    tabClick && dispatch(TabClick(false));
    //prettier-ignore
    dispatch(
        set_temp_data(setFastlaneRequest(categoryParams))
      );
    dispatch(
      SaveQuoteData(
        setSaveQuoteRequest({ ...otherLinkParams, ...categoryParams })
      )
    );
  };

  //Dispatch General RC Submit
  const _genReg = (journeyType) => {
    if (isRegValid(regIp, regNo1, regNo2, regNo3)) {
      let _type = TypeReturn(type);
      let regParams = { regIp, regNo1, regNo2, regNo3 };
      //prettier-ignore
      let UrlParams = { enquiry_id, journeyType, category, _type, journey_type };
      dispatch(set_temp_data(onSubmitRegistration(regParams, UrlParams)));
      dispatch(SaveQuoteData(onSubmitSave(regParams, UrlParams)));
      setTimeout(() => setbtnDisable(false), 2000);
    } else {
      swal("Warning", "Invalid Registration Number", "warning").then(() =>
        setTimeout(() => setbtnDisable(false), 1000)
      );
    }
  };

  //Non-vahhan submit
  const _nonVahaanSubmit = (journeyType) => {
    if (
      vahaanConstants(vahaanConfig, type) &&
      Number(journeyType) === 1 &&
      ![101, 108].includes(fastLaneData?.status)
    ) {
      onSubmitFastLane();
    } else {
      if (
        (Number(journeyType) === 1 &&
          ((regNo1 && regNo3) || (regIp && regIp[0] * 1))) ||
        [2, 3].includes(Number(journeyType))
      ) {
        //Analytics | Without RC & new business tracking
        if (Number(journeyType === 2)) {
          _norcTracking(TypeReturn(type), temp_data);
        } else if (Number(journeyType === 3)) {
          _newBusiness(TypeReturn(type), temp_data);
        }
        Number(journeyType) !== 1 ? _bhReg(journeyType) : _genReg(journeyType);
      } else {
        swal("Error", "Please fill all the details", "error").then(() =>
          setTimeout(() => setbtnDisable(false), 1000)
        );
      }
    }
  };

  const onSubmit = (journeyType) => {
    if (isRegValid(regIp, regNo1, regNo2, regNo3) || journeyType * 1 !== 1) {
      //Check for BH number | Check if BH is enabled on broker
      if (_bhCheck(regIpCheck)) {
        setBhModal(true);
        setValue("regNo", "");
        setBuffer(false);
      } else {
        if (
          isAndroidWebView &&
          import.meta.env.VITE_BROKER === "RB" &&
          !localStorage?.SSO_user_motor
        ) {
          enforceLogin(setbtnDisable, setBuffer);
        } else {
          dispatch(CancelAll(false));
          _nonVahaanSubmit(journeyType);
        }
      }
    }
  };

  //policy & business type calc
  let funcParams = { dispatch, onSubmit, setBuffer, setbtnDisable, setValue };
  let journeyParams = { enquiry_id, token, TypeReturn, type, journey_type };
  let restParams = { temp_data, fastLaneData, regIp, regNo1, regNo2, regNo3 };
  // eslint-disable-next-line react-hooks/rules-of-hooks
  useFastlaneResponse(funcParams, journeyParams, restParams);

  /*-----journey change-----*/
  const journeyChange = () => {
    //Invoking journey mismatch fn.
    journeyMismatchFn(frontendurl, TypeReturn, show, enquiry_id, token);
  };
  /*--x--journey change--x--*/
  return !stepper1 ? (
    <>
      <StyledBack className={lessthan767 ? "ml-1 backBtn" : "backBtn"}>
        {noBack(theme_conf, token, temp_data) &&
          temp_data?.blockBackButton &&
          temp_data?.blockBackButton !== "Y" && (
            <BackButton
              type="button"
              onClick={back}
              BlockLayout={theme_conf?.isIpBlocked}
            >
              {!lessthan767 ? (
                <>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className=""
                    viewBox="0 0 24 24"
                  >
                    <path d="M11.67 3.87L9.9 2.1 0 12l9.9 9.9 1.77-1.77L3.54 12z" />
                    <path d="M0 0h24v24H0z" fill="none" />
                  </svg>
                  <text style={{ color: "black" }}>Back</text>
                </>
              ) : (
                <img
                  src={`${
                    import.meta.env.VITE_BASENAME !== "NA"
                      ? `/${import.meta.env.VITE_BASENAME}`
                      : ""
                  }/assets/images/back-button.png`}
                  alt="bck"
                />
              )}
            </BackButton>
          )}
      </StyledBack>
      <Stylediv
        className="mt-1 mb-4 w-100 mx-auto d-flex flex-column align-content-center ElemFade mx-auto"
        tabletResponsive={!lessthan330 && lessthan767}
      >
        <Row className="text-center w-100 mx-auto">
          <div className="mt-4 d-flex flex-column align-content-center w-100 mx-auto">
            <StyledH3
              className={`text-center w-100 ${lessthan767 ? "mt-4" : "mt-1"}`}
              isMobileIOS={isMobileIOS}
            >
              {lessthan767 ? (
                <>
                  Issue policy in
                  <SpanTag Theme={Theme} isMobileIOS={isMobileIOS}>
                    &nbsp;2&nbsp;
                  </SpanTag>
                  {`minutes`}
                </>
              ) : (
                "Let's begin with your vehicle registration number"
              )}
            </StyledH3>
          </div>
        </Row>
        <RegInput
          register={register}
          setValue={setValue}
          watch={watch}
          errors={errors}
          onSubmitFastLane={onSubmitFastLane}
          onSubmit={onSubmit}
          buffer={buffer}
          setbtnDisable={setbtnDisable}
          btnDisable={btnDisable}
          stepper1={stepper1}
          setBuffer={setBuffer}
        />
        <Row className="w-100 d-flex no-wrap mt-2 justify-content-center mx-auto">
          <Col
            sm="12"
            md="12"
            lg="12"
            xl="12"
            className="text-center mx-auto d-flex justify-content-center mt-4 w-100"
          >
            <StyledH4 className="text-center w-100 mx-auto">
              {lessthan767 ? "-------- OR --------" : "OR"}
            </StyledH4>
          </Col>
          <Col
            sm="12"
            md="12"
            lg="12"
            xl="12"
            className="text-center mt-4 d-flex flex-wrap justify-content-center p-0 my-2 mx-auto"
          >
            {import.meta.env.VITE_BROKER !== "KAROINSURE" && <Button
              className="mx-2 my-2"
              disabled={btnDisable}
              buttonStyle="outline-solid"
              hex1={Theme?.Registration?.otherBtn?.hex1 || "#006400"}
              hex2={Theme?.Registration?.otherBtn?.hex2 || "#228B22"}
              shadow={"none"}
              style={{
                ...(lessthan767 && { width: lessthan400 ? "92%" : "80%" }),
              }}
              onClick={() => {
                _haptics([100, 0, 50]);
                onSubmit(2);
                setbtnDisable(true);
              }}
              borderRadius={lessthan767 ? "30px" : "5px"}
              type="submit"
            >
              <TextTag
                lessthan767
                className={lessthan767 ? "px-0 py-1 m-0" : "p-0 m-0"}
              >
                Proceed without Vehicle Number
              </TextTag>
            </Button>}
            <Button
              className="mx-2 my-2"
              disabled={btnDisable}
              buttonStyle="outline-solid"
              hex1={
                lessthan767 && import.meta.env.VITE_BROKER !== "RB"
                  ? "#fff"
                  : Theme?.Registration?.otherBtn?.hex1 || "#006400"
              }
              hex2={
                lessthan767 && import.meta.env.VITE_BROKER !== "RB"
                  ? "#fff"
                  : Theme?.Registration?.otherBtn?.hex2 || "#228B22"
              }
              borderRadius={lessthan767 ? "30px" : "5px"}
              color={
                lessthan767 && import.meta.env.VITE_BROKER !== "RB"
                  ? Theme?.Registration?.otherBtn?.hex1 || "#006400"
                  : ""
              }
              shadow={"none"}
              style={{
                ...(lessthan767 && { width: lessthan400 ? "92%" : "80%" }),
              }}
              onClick={() => {
                _haptics([100, 0, 50]);
                onSubmit(3);
                setbtnDisable(true);
              }}
              type="submit"
            >
              <TextTag className="p-0 m-0">
                Got a New Vehicle? Click Here!
              </TextTag>
            </Button>
          </Col>
          <RenewalButton btnDisable={btnDisable} type={type} />
        </Row>
      </Stylediv>
      {/*--------------------Journey Mismatch Modal-------------------*/}
      <JourneyMismatch
        enquiry_id={enquiry_id}
        show={TypeReturn(show)}
        onHide={() => setShow(false)}
        setValue={setValue}
        journeyChange={journeyChange}
        clearFastLane={() => dispatch(setFastLane(false))}
        frontendurl={frontendurl}
      />
      {/*---------------x----Journey Mismatch Modal--------x-----------*/}
      <SimpleModal show={bhModal} onHide={() => setBhModal(false)} />
      <GlobalStyle />
    </>
  ) : (
    <Loader />
  );
};
