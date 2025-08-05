import React, { useState, useMemo } from "react";
import { Col, Row } from "react-bootstrap";
import { FloatButton } from "components";
import { useLocation, useHistory } from "react-router";
import { useDispatch, useSelector } from "react-redux";
import { Container, FormContainer, Avatar } from "./style";
import { Registration, CarDetails, LeadPage, VehicleType } from "./steps";
import _ from "lodash";
import { fetchToken } from "utils";
import { useMediaPredicate } from "react-media-hook";
import RenewalRegistration from "modules/Home/steps/Renewal/RenewalRegistration";
import TimeoutPopup from "modules/quotesPage/AbiblPopup/TimeoutPopup";
import { useIdleTimer } from "react-idle-timer";
import { TypeReturn } from "modules/type";
//custom-hooks
//prettier-ignore
import { useCheckEnquiry, useErrorHandling, useAccessControl, 
         usePrefill, useLinkTrigger, useB2CAccess, getAvatar
        } from 'modules/Home/home-page-hooks'
import { _trackProfile } from "analytics/user-creation.js/user-creation";

export const Home = (props) => {
  const dispatch = useDispatch();
  const { error, temp_data, theme_conf, errorSpecific, encryptUser } =
    useSelector((state) => state.home);
  const { typeAccess } = useSelector((state) => state.login);

  const location = useLocation();
  const history = useHistory();
  const query = new URLSearchParams(location.search);

  const enquiry_id = query.get("enquiry_id");
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const typeId = query.get("typeid");
  const stepperfill = query.get("stepperfill");
  const journey_type = query.get("journey_type");
  const key = query.get("key");
  const savedStep = query.get("stepNo");
  const isPartner = query.get("is_partner");
  const reg_no_url = query.get("reg_no");
  const policy_no_url = query.get("policy_no");
  const shared = query.get("shared");
  const _stToken = fetchToken();
  const { type } = props?.match?.params;

  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const lessthan400 = useMediaPredicate("(max-width: 400px)");
  const lessthan330 = useMediaPredicate("(max-width: 330px)");

  //IOS check.
  let userAgent = navigator.userAgent;
  let isMobileIOS = false; //initiate as false
  // device detection
  if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream && lessthan767) {
    isMobileIOS = true;
  }

  //Link-Click & Delivery
  useLinkTrigger(dispatch, key);

  const checkSellerType = !_.isEmpty(temp_data?.agentDetails)
    ? temp_data?.agentDetails?.map((seller) => seller.sellerType)
    : [];

  //B2C block
  useB2CAccess(temp_data, checkSellerType, token, journey_type);

  //Access-Control
  useAccessControl(type, typeAccess, history);

  //check enquiry - This hooks checks whether the URL has a valid enquiry id embedded in it.
  //Also used to check if breakin is already generted.
  useCheckEnquiry(temp_data, location, type, history, enquiry_id, token);

  //Prefill Api
  usePrefill(dispatch, enquiry_id);

  //Error Handling | Token data missing error check
  useErrorHandling(dispatch, error, temp_data, type, enquiry_id, errorSpecific, token, journey_type, typeId, _stToken, history);

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

  //Analytics | Register User
  const [counter, setCounter] = useState(false)
  useMemo(() => {
    if (encryptUser && !_.isEmpty(temp_data?.analytics) && !counter) {
      _trackProfile({ ...temp_data?.analytics, id: encryptUser });
      setCounter(true)
    }
  }, [encryptUser, temp_data?.analytics]);

  return (
    <>
      <Container>
        <FormContainer
          width={location.pathname === `/${type}/vehicle-type` ? "780px" : ""}
        >
          {!theme_conf?.isIpBlocked && (
            <Row>
              {import.meta.env.VITE_BROKER !== "ABIBL" && !lessthan767 ? (
                <Col className="landing-laxmi mx-auto" xl={3} lg={3} md={3}>
                  <div className="review-details3 text-center">
                    <Avatar type={type}
                      src={getAvatar(TypeReturn(type))}
                      alt="avatarImage"
                    />
                  </div>
                </Col>
              ) : (
                <noscript />
              )}
            </Row>
          )}
          {[`/${type}/lead-page`, `/${type}/auto-register`].includes(location.pathname) && (
            <LeadPage
              type={type}
              lessthan767={lessthan767}
              TypeReturn={TypeReturn}
              _stToken={_stToken}
              autoRegister={location.pathname === `/${type}/auto-register`}
            />
          )}
          {location.pathname === `/${type}/registration` && (
            <Registration
              enquiry_id={enquiry_id}
              type={type}
              token={token}
              errorProp={error}
              lessthan767={lessthan767}
              lessthan400={lessthan400}
              lessthan330={lessthan330}
              typeId={typeId}
              isMobileIOS={isMobileIOS}
              journey_type={journey_type}
              TypeReturn={TypeReturn}
              isPartner={isPartner}
              reg_no_url={reg_no_url}
              _stToken={_stToken}
              shared={shared}
            />
          )}
          {location.pathname === `/${type}/renewal` && (
            <RenewalRegistration
              enquiry_id={enquiry_id}
              type={type}
              token={token}
              errorProp={error}
              lessthan767={lessthan767}
              lessthan400={lessthan400}
              lessthan330={lessthan330}
              typeId={typeId}
              isMobileIOS={isMobileIOS}
              journey_type={journey_type}
              TypeReturn={TypeReturn}
              isPartner={isPartner}
              policy_no_url={policy_no_url}
              reg_no_url={reg_no_url}
              _stToken={_stToken}
              shared={shared}
            />
          )}
          {location.pathname === `/${type}/vehicle-type` && (
            <VehicleType
              enquiry_id={enquiry_id}
              type={type}
              token={token}
              errorProp={error}
              typeId={typeId}
              lessthan767={lessthan767}
              isMobileIOS={isMobileIOS}
              journey_type={journey_type}
              TypeReturn={TypeReturn}
              isPartner={isPartner}
              _stToken={_stToken}
              shared={shared}
            />
          )}
          {location.pathname === `/${type}/vehicle-details` && (
            <CarDetails
              enquiry_id={enquiry_id}
              type={type}
              token={token}
              errorProp={error}
              typeId={typeId}
              stepperfill={stepperfill}
              isMobileIOS={isMobileIOS}
              journey_type={journey_type}
              savedStep={savedStep}
              TypeReturn={TypeReturn}
              isPartner={isPartner}
              _stToken={_stToken}
              shared={shared}
            />
          )}
        </FormContainer>
      </Container>
      <FloatButton />
      <TimeoutPopup
        enquiry_id={enquiry_id}
        show={timerShow}
        onClose={() => setTimerShow(false)}
        type={TypeReturn(type)}
        TempData={temp_data}
      />
    </>
  );
};
