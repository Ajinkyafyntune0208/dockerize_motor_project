import React, { useState, useEffect } from "react";
import { Row } from "react-bootstrap";
import { BackButton, Loader } from "components";
import { useHistory } from "react-router";
import "./style.css";
import { useDispatch, useSelector } from "react-redux";
import {
  set_temp_data,
  brandType,
  SaveQuoteData,
} from "modules/Home/home.slice";
import _ from "lodash";
import { scrollToTargetAdjusted, isB2B } from "utils";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { useMediaPredicate } from "react-media-hook";
import { GlobleStyle, StyledH3, StyledBack } from "./style";
//prettier-ignore
import { useVehicleData, useLeadGeneration, usePaymentStatus,
         usePostTransactions, usePrefill, useSuccessAndErrorHandling
        } from './vehicle-type-hooks';
import VehicleCategory from "./vehicle-category";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

export const VehicleType = (props) => {
  //prettier-ignore
  const { enquiry_id, type, token, errorProp, typeId,
          lessthan767, journey_type, _stToken, shared
        } = props

  const dispatch = useDispatch();
  const history = useHistory();

  const { vehicleType, temp_data, loading, saveQuoteData, theme_conf } =
    useSelector((state) => state.home);
  const { duplicateEnquiry } = useSelector((state) => state.proposal);

  const lessthan963 = useMediaPredicate("(max-width: 963px)");
  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const lessthan400 = useMediaPredicate("(max-width: 400px)");
  const lessthan350 = useMediaPredicate("(max-width: 350px)");

  /*---------------- back button---------------------*/
  const back = () => {
    if (temp_data?.isRenewalRedirection === "Y") {
      history.push(
        `/${type}/renewal?enquiry_id=${temp_data?.enquiry_id || enquiry_id}${
          token ? `&xutm=${token}` : ``
        }${typeId ? `&typeid=${typeId}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${_stToken ? `&stToken=${_stToken}` : ``}`
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

  //load vehicle data
  useVehicleData(dispatch, enquiry_id);

  const [selected, setSelected] = useState(false);
  // eslint-disable-next-line no-unused-vars
  const [gcvCarrierType, setgcvCarrierType] = useState(false);
  const [btnDisable, setbtnDisable] = useState(false);

  //Url
  useLeadGeneration(dispatch, temp_data, enquiry_id);

  //generate new enquiry id | Throw alert if payment status is pending
  const urlParams = { typeId, type, token, journey_type, _stToken, shared };
  usePaymentStatus(dispatch, duplicateEnquiry, urlParams);

  //Journey already submitted
  usePostTransactions(temp_data, enquiry_id, _stToken);

  // prefill data
  usePrefill(temp_data, setSelected, setgcvCarrierType);

  //onSuccess
  const restParams = {
    temp_data,
    saveQuoteData,
    history,
    errorProp,
    setbtnDisable,
  };
  useSuccessAndErrorHandling(
    dispatch,
    { ...urlParams, enquiry_id },
    restParams
  );

  const onSubmit = (VehicalType, cType) => {
    let productSubTypeId = vehicleType?.filter(
      ({ productSubTypeId }) => Number(productSubTypeId) === Number(VehicalType)
    );
    let data = {
      productSubTypeId:
        Number(VehicalType) || Number(productSubTypeId[0]?.productSubTypeId),
      gcvCarrierType: cType,
    };
    dispatch(
      set_temp_data({
        leadJourneyEnd: true,
        leadStageId: 2,
        productSubTypeCode: productSubTypeId[0]?.productSubTypeCode,
        productCategoryName: productSubTypeId[0]?.productCategoryName,
        ...data,
      })
    );
    dispatch(
      SaveQuoteData({
        stage: "3",
        userProductJourneyId: enquiry_id,
        ...(isB2B(temp_data) && token && { token: token }),
        enquiryId: enquiry_id,
        productSubTypeName: productSubTypeId[0]?.productSubTypeCode,
        ...(journey_type && {
          journeyType: journey_type,
        }),
        ...data,
      })
    );
    setTimeout(setbtnDisable(false), 2000);
    dispatch(brandType([]));
  };

  useEffect(() => {
    if (!loading) scrollToTargetAdjusted("targetDiv", 45);
  }, [loading]);

  return (
    <>
      {!loading ? (
        <>
          <StyledBack
            className={lessthan767 ? "ml-1 backBtn" : "backBtn"}
            hide={["ACE CRM"].includes(temp_data?.leadSource)}
          >
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
          </StyledBack>
          <div
            className="ml-4 my-4 ElemFade"
            id={"targetDiv"}
            style={{
              ...(!lessthan350 &&
                lessthan767 && { position: "relative", top: "-37.53px" }),
            }}
          >
            <Row className="text-center w-100">
              <div className="mt-4 d-flex flex-column justify-content-center w-100">
                <StyledH3 className="text-center w-100">
                  {lessthan767
                    ? lessthan400
                      ? "Select vehicle type"
                      : "Select type of vehicle"
                    : "Choose the type of your vehicle"}
                </StyledH3>
              </div>
            </Row>
            <VehicleCategory
              vehicleType={vehicleType}
              lessthan963={lessthan963}
              lessthan600={lessthan600}
              lessthan400={lessthan400}
              btnDisable={btnDisable}
              setbtnDisable={setbtnDisable}
              onSubmit={onSubmit}
              selected={selected}
              Theme={Theme}
            />
          </div>
        </>
      ) : (
        <Loader />
      )}
      <GlobleStyle />
    </>
  );
};
