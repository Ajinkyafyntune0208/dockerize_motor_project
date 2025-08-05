/* eslint-disable react-hooks/exhaustive-deps */
import React, { useState, useEffect } from "react";
import { Row, Button } from "react-bootstrap";
import "./style.css";
import { Brand, Model, FuelType, Variant, City, YearCM } from "./steps";
import { scrollToTargetAdjusted, isB2B } from "utils";
import { useHistory } from "react-router";
import { BackButton, Loader } from "components";
import { useSelector, useDispatch } from "react-redux";
import { clear, Prefill } from "modules/Home/home.slice";
import _ from "lodash";
import { useMediaPredicate } from "react-media-hook";
import { clear as ClearQuotesData } from "modules/quotesPage/quote.slice";
import { TitleFn, Switcher } from "./helper";
import { StyledDiv, StyledH3, StyledBack, GlobalStyle } from "./style";
import Stepper from "./stepper";
//prettier-ignore
import { useSavedStep, useStepperPrefill, useJourneyProcess,
         usePaymentStatus, usePostTransaction, useJourneyCompletion
        } from "./car-details-hooks";

export const CarDetails = (props) => {
  //prettier-ignore
  const { enquiry_id, type, token, typeId, stepperfill, _stToken,
          isMobileIOS, journey_type, savedStep, TypeReturn, shared
        } = props;
  /*---------------- back button---------------------*/
  const back = () => {
    if (
      temp_data?.agentDetails?.[0] &&
      import.meta.env.VITE_BROKER === "HEROCARE"
    ) {
      reloadPage(theme_conf?.broker_config?.broker_asset?.logo_url?.url);
    } else if (TypeReturn(type) === "cv" && TypeReturn(type)) {
      !typeId &&
        history.push(
          `/${type}/vehicle-type?enquiry_id=${
            temp_data?.enquiry_id || enquiry_id
          }${token ? `&xutm=${token}` : ``}${
            typeId ? `&typeid=${typeId}` : ``
          }${journey_type ? `&journey_type=${journey_type}` : ``}${
            _stToken ? `&stToken=${_stToken}` : ``
          }${shared ? `&shared=${shared}` : ``}`
        );
      typeId &&
        history.push(
          `/${type}/registration?enquiry_id=${
            temp_data?.enquiry_id || enquiry_id
          }${token ? `&xutm=${token}` : ``}${
            typeId ? `&typeid=${typeId}` : ``
          }${journey_type ? `&journey_type=${journey_type}` : ``}${
            _stToken ? `&stToken=${_stToken}` : ``
          }${shared ? `&shared=${shared}` : ``}`
        );
    } else if (temp_data?.isRenewalRedirection === "Y") {
      history.push(
        `/${type}/renewal?enquiry_id=${temp_data?.enquiry_id || enquiry_id}${
          token ? `&xutm=${token}` : ``
        }${typeId ? `&typeid=${typeId}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${_stToken ? `&stToken=${_stToken}` : ``}${
          shared ? `&shared=${shared}` : ``
        }`
      );
    } else {
      history.push(
        `/${type}/registration?enquiry_id=${
          temp_data?.enquiry_id || enquiry_id
        }${token ? `&xutm=${token}` : ``}${typeId ? `&typeid=${typeId}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${_stToken ? `&stToken=${_stToken}` : ``}${
          shared ? `&shared=${shared}` : ``
        }`
      );
    }
  };
  /*----------x----- back button-------x-------------*/
  const [Step, setStep] = useState(1);
  const history = useHistory();
  const dispatch = useDispatch();

  const { temp_data, loading, stepperLoad, theme_conf } = useSelector(
    (state) => state.home
  );
  const { duplicateEnquiry } = useSelector((state) => state.proposal);

  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const lessthan400 = useMediaPredicate("(max-width: 400px)");
  const lessthan330 = useMediaPredicate("(max-width: 330px)");
  //clearing quotes page data on browser back button

  useEffect(() => {
    dispatch(ClearQuotesData());
  }, []);

  //update temp-data
  useEffect(() => {
    if (!_.isEmpty(temp_data)) {
      dispatch(Prefill({ enquiryId: enquiry_id }));
    }
  }, []);

  //center auto scroll
  useEffect(() => {
    if (!loading) scrollToTargetAdjusted("stepper", 45);
  }, [loading]);

  const stepFn = (stepNo, newStep) => {
    dispatch(clear("saveQuoteData"));
    setStep(Number(newStep));
  };

  //preselecting last saved step
  const urlParams = {
    enquiry_id,
    token,
    journey_type,
    typeId,
    _stToken,
    shared,
  };
  useSavedStep(savedStep, setStep, urlParams);

  useStepperPrefill(stepperfill, setStep);

  //Url
  const joruneyParams = { enquiry_id, Step, type };
  useJourneyProcess(dispatch, temp_data, joruneyParams);

  //generate new enquiry id.
  const statusParam = { typeId, type, token, journey_type, _stToken, shared };
  usePaymentStatus(dispatch, duplicateEnquiry, statusParam);

  //Journey already submitted
  usePostTransaction(temp_data, enquiry_id, _stToken);

  //After Completion
  const restParams = { token, journey_type, enquiry_id, typeId, type, shared };
  useJourneyCompletion(temp_data, history, { ...restParams, _stToken, Step });

  return stepperLoad ? (
    <Loader />
  ) : (
    <StyledDiv lessthan600={lessthan600} Step={Step}>
      {((!(Step > 1) && lessthan600) || !lessthan600) && (
        <StyledBack className="backBtn">
          <BackButton
            type="button"
            onClick={back}
            BlockLayout={theme_conf?.isIpBlocked}
          >
            {!lessthan600 ? (
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
        </StyledBack>
      )}
      {!lessthan600 && (
        <Stepper
          TypeReturn={TypeReturn}
          type={type}
          temp_data={temp_data}
          Step={Step}
        />
      )}
      <Row
        className={`w-100 ${lessthan600 ? "" : "mt-4"} mx-auto`}
        style={lessthan600 ? { marginTop: "-15px" } : {}}
      >
        <Row className="mx-auto d-flex w-100 ">
          <Button
            className={lessthan600 ? "mb-2 mt-4" : "my-2"}
            size="sm"
            variant="light"
            onClick={() => Switcher(Step, setStep, temp_data, TypeReturn(type))}
            disabled={Step > 1 ? false : true}
            style={
              Step > 1
                ? {
                    ...(lessthan600 && {
                      position: "relative",
                      top: "5.5px",
                      left: lessthan400 ? "3px" : "1px",
                      zIndex: 999,
                    }),
                  }
                : { visibility: "hidden" }
            }
          >
            <img
              src={`${
                import.meta.env.VITE_BASENAME !== "NA"
                  ? `/${import.meta.env.VITE_BASENAME}`
                  : ""
              }/assets/images/back-button.png`}
              alt="bck"
            />
          </Button>
          <StyledH3
            style={{ ...(lessthan600 && Step > 1 && { marginTop: "10px" }) }}
            className={`text-center w-100 ${lessthan600 ? "mb-2" : "mb-4"}`}
          >
            {TitleFn(Step, lessthan600)}
          </StyledH3>
        </Row>
        <div
          translate="no"
          className={`text-center w-100`}
          style={
            !lessthan330 && lessthan600
              ? { position: "relative", top: "-41px" }
              : {}
          }
        >
          {Step === 1 && (
            <Brand
              stepFn={stepFn}
              enquiry_id={temp_data?.enquiry_id || enquiry_id}
              token={isB2B(temp_data) && token}
              type={type}
              TypeReturn={TypeReturn}
              _stToken={_stToken}
            />
          )}
          {Step === 2 && (
            <Model
              stepFn={stepFn}
              enquiry_id={temp_data?.enquiry_id || enquiry_id}
              token={isB2B(temp_data) && token}
              type={type}
              TypeReturn={TypeReturn}
              _stToken={_stToken}
            />
          )}
          {Step === 3 && (
            <FuelType
              stepFn={stepFn}
              enquiry_id={temp_data?.enquiry_id || enquiry_id}
              token={isB2B(temp_data) && token}
              TypeReturn={TypeReturn}
              _stToken={_stToken}
              type={type}
            />
          )}
          {Step === 4 && (
            <Variant
              stepFn={stepFn}
              enquiry_id={temp_data?.enquiry_id || enquiry_id}
              token={isB2B(temp_data) && token}
              type={type}
              TypeReturn={TypeReturn}
              _stToken={_stToken}
            />
          )}
          {Step === 5 && (
            <City
              stepFn={stepFn}
              enquiry_id={temp_data?.enquiry_id || enquiry_id}
              isMobileIOS={isMobileIOS}
              token={isB2B(temp_data) && token}
              type={type}
              TypeReturn={TypeReturn}
              _stToken={_stToken}
            />
          )}
          {Step === 6 &&
            (Number(temp_data?.journeyType) !== 3 ||
              temp_data?.regNo !== "NEW") && (
              <YearCM
                stepFn={stepFn}
                enquiry_id={temp_data?.enquiry_id || enquiry_id}
                token={isB2B(temp_data) && token}
                type={type}
                TypeReturn={TypeReturn}
                _stToken={_stToken}
              />
            )}
        </div>
      </Row>
      <GlobalStyle />
    </StyledDiv>
  );
};
