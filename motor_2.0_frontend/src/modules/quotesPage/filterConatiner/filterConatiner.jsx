import React, { useState, useEffect, useRef } from "react";
import { useForm } from "react-hook-form";
import { addDays, differenceInDays, differenceInMonths } from "date-fns";
import moment from "moment";
import { Row, Col } from "react-bootstrap";
import { useDispatch, useSelector } from "react-redux";
import _ from "lodash";
import { useLocation } from "react-router";
import { toDate, vahaanServicesName } from "utils";
import { useMediaPredicate } from "react-media-hook";
import { TypeReturn } from "modules/type";
import PropTypes from "prop-types";
import { useOutsideClick } from "hoc";
// components import
import PolicyTypePopup from "../quotesPopup/policyTypePopup/policyTypePopup";
import IDVPopup from "../quotesPopup/idvPopup/IDVPopup";
import NCBPopup from "../quotesPopup/ncb-popup";
import VehicleDetails from "../quotesPopup/vehicleDetails/vehicleDetails";
import EditInfoPopup from "../quotesPopup/editDetailsPopup/editDetails";
import EditInfoPopup2 from "../quotesPopup/editDetailsPopup/editDetails2";
import { set_temp_data } from "modules/Home/home.slice";
import { Toaster, ToasterPolicyChange } from "components";
// prettier-ignore
import { NcbList as getNcb, PrevInsList as getPrevInsList, SaveQuoteData, setTempData, SaveLead} from "./quoteFilter.slice";
import JourneyCategoryPopup from "../quotesPopup/journeyCategoryPopup/journeyCategoryPopup";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import AbiblPopup from "../AbiblPopup/AbiblPopup";
import Style from "./style";
// prettier-ignore
import { bundledPolicyFunc, calcOdFunc, pevPolicyfunc, quoteDataFunc } from "./helper";
import { SkeletonLoader } from "./SkeletonLoader";
import NcbSection from "./component/NcbSection";
import InspectionAlert from "./component/InspectionAlert";
import PrevPolicySection from "./component/PrevPolicySection";
import PrevPolicyType from "./component/PrevPolicyType";
import VehicleDetail from "./component/VehicleDetail";
import IDVSection from "./component/mobile/IDVSection";
import EligibleNcb from "./component/mobile/EligiableNcb";
import PrevPolicyTypeMobile from "./component/mobile/PrevPolicyTypeMobile";
import PrevPolicyExpiry from "./component/mobile/PrevPolicyExpiry";
import VehicleDetailMobile from "./component/mobile/VehicleDetailMobile";
import RegDate from "./component/mobile/RegDate";
import VehicleDetailMobile2 from "./component/mobile/VehicleDetailMobile2";
import PrevPolicyPopup from "../quotesPopup/prevInsurerPopup/previous-policy/prev-policy-popup";

/*---------------date config----------------*/
const policyMax = addDays(new Date(Date.now() - 86400000), 45);
/*-----x---------date config-----x----------*/

export const FilterContainer = ({ reviewData, type, typeId, ...others }) => {
  // prettier-ignore
  const { quote, allQuoteloading, setPopupOpen, isMobileIOS, theme_conf,
          assistedMode, showPrevPopUp, ConfigNcb, policyTypeCode,
        } = others;

  const ls = new SecureLS();
  const ThemeLS = ls.get("themeData");
  const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;
  const lessthan993 = useMediaPredicate("(max-width: 993px)");
  const lessthan600 = useMediaPredicate("(max-width: 600px)");
  const lessthan400 = useMediaPredicate("(max-width: 400px)");
  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const dispatch = useDispatch();
  const { tempData, saveQuote, ncbList, prevInsList } = useSelector(
    (state) => state.quoteFilter
  );
  const { updateQuoteLoader, addonConfig } = useSelector(
    (state) => state.quotes
  );
  const loginData = useSelector((state) => state.login);
  const { register, watch, control, errors } = useForm({});

  const userData = useSelector((state) => state.home);
  const regDate = watch("regDate");
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const [prevPopup, setPrevPopup] = useState(false);
  const [ncbPopup, setNcbPopup] = useState(false);
  const [idvPopup, setIdvPopup] = useState(false);
  const [policyType, setPolicyType] = useState(tempData?.policyType || false);
  const { prefillLoading } = useSelector((state) => state.home);
  const [dateEditor, setDateEditor] = useState(false);
  const [newCar, setNewCar] = useState(false);
  const [timer, setTimer] = useState(false);
  const [policyPopup, setPolicyPopup] = useState(false);
  const [vehicleDetailsPopup, setVehicleDetailsPopup] = useState(false);
  const [journeyCategoryPopup, setJourneyCategoryPopup] = useState(false);
  const [editInfoPopup, setEditInfoPopup] = useState(false);
  const [editInfoPopup2, setEditInfoPopup2] = useState(false);
  const [editDate, setEditDate] = useState(false);

  //------------setting policy type from home slice to quote filter slice temp data (on reload)-------------------
  useEffect(() => {
    if (userData.temp_data?.policyType) {
      dispatch(
        setTempData({
          policyType: userData.temp_data?.policyType,
        })
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userData.temp_data?.policyType]);

  //setting initial policy type whem state is empty
  useEffect(() => {
    !policyType && setPolicyType(tempData?.policyType);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tempData?.policyType]);

  setTimeout(() => {
    (addonConfig ||
      (!["ACE", "RB"].includes(import.meta.env.VITE_BROKER) &&
        import.meta.env.VITE_BROKER !== "KAROINSURE")) &&
      setTimer(true);
  }, 500);

  //--------------------setting new business logics and data------------------

  useEffect(() => {
    if (location.pathname === `/${type}/quotes` && !prefillLoading) {
      if (userData?.temp_data?.regNo === "NEW") {
        setNewCar(true);
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userData?.temp_data?.regNo]);

  useEffect(() => {
    if (location.pathname === `/${type}/quotes` && !prefillLoading && timer) {
      let b = moment().format("DD-MM-YYYY");
      let c = userData.temp_data?.vehicleInvoiceDate;
      let manufacturerDate = userData.temp_data?.manfDate;
      let diffManfDays =
      manufacturerDate && b && differenceInDays(toDate(b), toDate(manufacturerDate));
      let diffMonthsRollOver =
        c && b && differenceInMonths(toDate(b), toDate(c));
      if (
        userData.temp_data.journeyType === 3 ||
        diffMonthsRollOver === 0 ||
        (diffMonthsRollOver < 9 && diffMonthsRollOver && diffManfDays < 270) ||
        userData.temp_data.regNo === "NEW"
      ) {
        setNewCar(true);
        setPrevPopup(false);
        dispatch(
          set_temp_data({
            newCar: true,
          })
        );
      } else {
        setNewCar(false);
        dispatch(
          set_temp_data({
            newCar: false,
          })
        );
        if (userData.temp_data?.expiry || assistedMode) {
          setPrevPopup(false);
        } else {
          setPrevPopup(true);
        }
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    tempData,
    policyType,
    userData.temp_data?.policyType,
    userData.temp_data?.leadJourneyEnd,
    prefillLoading,
    timer,
    userData.temp_data?.vehicleInvoiceDate,
    userData.temp_data.regNo,
  ]);

  useEffect(() => {
    if (
      prevPopup ||
      ncbPopup ||
      idvPopup ||
      editInfoPopup ||
      editInfoPopup ||
      editDate ||
      policyPopup ||
      vehicleDetailsPopup ||
      journeyCategoryPopup
    ) {
      document.body.style.position = "fixed";
      document.body.style.overflowY = "hidden";
      document.body.style.width = "100%";
    } else {
      document.body.style.position = "relative";
      document.body.style.height = "auto";
      document.body.style.overflowY = "auto";
    }
  }, [
    prevPopup,
    ncbPopup,
    idvPopup,
    editInfoPopup,
    editInfoPopup2,
    editDate,
    policyPopup,
    vehicleDetailsPopup,
    journeyCategoryPopup,
  ]);

  //-----------------------------	date editor display logic----------------------------

  useEffect(() => {
    if (regDate && location.pathname === `/${type}/quotes`) {
      dispatch(
        set_temp_data({
          regDate: regDate,
          manfDate: regDate && `${regDate.slice(3)}`,
        })
      );
      setDateEditor(false);
    }

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [regDate]);

  //--------------------------------getting ncb values api-------------------------------

  useEffect(() => {
    if (ncbList.length === 0 && location.pathname === `/${type}/quotes`) {
      dispatch(getNcb());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tempData?.ncbValue]);

  const [prevList, setPrevList] = useState(true);

  //---------------------------getting prev ic api------------------------------------
  useEffect(() => {
    if (
      prevInsList?.length === 0 &&
      prevList &&
      location.pathname === `/${type}/quotes`
    ) {
      dispatch(getPrevInsList({enquiryId: enquiry_id}));
      setPrevList(false);
    }

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [prevInsList]);
  //------------------------------------ new buisness ncb logic----------------------------

  useEffect(() => {
    if (location.pathname === `/${type}/quotes`) {
      let b = moment().format("DD-MM-YYYY");
      let c = userData.temp_data?.vehicleInvoiceDate;
      let e = `01-${userData.temp_data?.manfDate}`;
      let diffDaysNB = c && b && differenceInDays(toDate(b), toDate(c));
      let diffDaysManf = b && e && differenceInDays(toDate(b), toDate(e));
      let diffMonthsRollOver =
        c && b && differenceInMonths(toDate(b), toDate(c));
      if (
        userData.temp_data.journeyType === 3 ||
        (diffMonthsRollOver === 0 && diffDaysNB > 1) ||
        (diffMonthsRollOver < 9 &&
          ((diffDaysNB > 1 && diffDaysManf <= 270) ||
            TypeReturn(type) === "cv"))
      ) {
        dispatch(
          set_temp_data({
            ncb: "0%",
            expiry: "New",
            noClaimMade: true,
            policyExpired: true,
            prevYearNcb: "0%",
            newNcb: "0%",
            prevIc: "New",
            prevIcFullName: "New",
            newCar: true,
            leadJourneyEnd: true,
          })
        );

        dispatch(
          setTempData({
            policyType: "New",
          })
        );
      } else {
        dispatch(
          setTempData({
            newCar: false,
          })
        );
      }
    }

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tempData?.ncbValue, userData.temp_data?.regDate]);

  //-------------------------setting od only-----------------------------
  const [odOnly, setOdOnly] = useState(false);

  useEffect(() => {
    let b = "01-09-2018";
    let c = userData.temp_data?.vehicleInvoiceDate;
    let d = moment().format("DD-MM-YYYY");
    let e = `01-${userData.temp_data?.manfDate}`;
    let diffDaysOd = c && b && differenceInDays(toDate(c), toDate(b));
    let diffMonthsOdCar = c && d && differenceInMonths(toDate(d), toDate(c));
    let diffDayOd = c && d && differenceInDays(toDate(d), toDate(c));
    let diffDaysManf = d && e && differenceInDays(toDate(d), toDate(e));

    if (
      ((diffDaysOd >= 0 &&
        diffDayOd > 270 &&
        diffMonthsOdCar < 58 &&
        TypeReturn(type) === "bike") ||
        (diffDayOd > 270 &&
          diffMonthsOdCar < 34 &&
          TypeReturn(type) === "car") ||
        (diffDayOd > 1 &&
          diffMonthsOdCar < 9 &&
          diffDaysManf > 270 &&
          TypeReturn(type) !== "cv")) &&
      !(
        tempData?.policyType === "Not sure" ||
        userData?.temp_data?.policyType === "Not sure" ||
        userData?.temp_data?.previousPolicyTypeIdentifier === "Y" ||
        tempData?.previousPolicyTypeIdentifier === "Y"
      )
    ) {
      dispatch(
        set_temp_data({
          odOnly: true,
        })
      );
      setOdOnly(true);
    } else {
      dispatch(
        set_temp_data({
          odOnly: false,
        })
      );
      setOdOnly(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    userData.temp_data?.vehicleInvoiceDate,
    userData.temp_data?.regDate,
    userData.temp_data?.policyType,
    userData.temp_data?.previousPolicyTypeIdentifier,
  ]);
  //-----------------------------------setJourneyType------------------------------------
  useEffect(() => {
    if (userData.temp_data?.ownerTypeId === 2) {
      dispatch(
        set_temp_data({
          ownerTypeId: 2,
        })
      );
    } else {
      dispatch(
        set_temp_data({
          ownerTypeId: 1,
        })
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userData.temp_data?.ownerTypeId]);

  const isVahaanService =
    userData?.temp_data.corporateVehiclesQuoteRequest?.journeyType ||
    userData?.temp_data?.vahaanService;

  const vahaanServiceName = vahaanServicesName?.includes(
    userData?.temp_data.corporateVehiclesQuoteRequest?.journeyType ||
      userData?.temp_data?.vahaanService
  );

  //------ In case of fastlane/ongrid ~ RB NCB is to be confirmed atleast once----
  //setting flag userData.temp_data?.isNcbConfirmed
  useEffect(() => {
    if (
      !userData.temp_data?.isNcbConfirmed &&
      userData.temp_data?.expiry &&
      userData.temp_data?.corporateVehiclesQuoteRequest?.previousInsurer !==
        "NEW" &&
      isVahaanService &&
      vahaanServiceName &&
      theme_conf?.broker_config?.ncbconfig === "Yes" &&
      userData?.temp_data?.isPopupShown === "N"
    ) {
      showPrevPopUp({}, {});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userData.temp_data?.expiry]);
  //nitial save quote request data api always calls on quote page load except fastlane
  useEffect(() => {
    if (location.pathname === `/${type}/quotes` && !saveQuote) {
      if (
        userData.temp_data &&
        userData.temp_data?.leadJourneyEnd === true &&
        userData.temp_data?.expiry
        // &&
        //mandatory NCB popup condition
        //prev INS popup should always be asked on quote page load through (fastlane/ongrid) ~ RB
        // (userData.temp_data?.isNcbConfirmed ||
        //   userData.temp_data?.corporateVehiclesQuoteRequest?.previousInsurer ===
        //     "NEW" || userData.temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo === "NEW")
      ) {
        const quoteData = quoteDataFunc(
          userData,
          enquiry_id,
          loginData,
          newCar,
          tempData,
          odOnly,
          policyTypeCode
        );
        dispatch(SaveQuoteData(quoteData));
        dispatch(
          SaveLead({
            enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
            leadStageId: 2,
          })
        );
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    userData.temp_data?.leadJourneyEnd,
    userData.temp_data?.expiry,
    userData.temp_data?.isClaimVerified,
    userData.temp_data?.isNcbConfirmed,
  ]);

  const dateOutRef = useRef(null);
  useOutsideClick(dateOutRef, () => setDateEditor(false));

  //------------------------------- adjust scroll-------------------------------------------

  const [scrollPosition, setScrollPosition] = useState(0);
  const handleScroll = () => {
    const position = window.pageYOffset;
    setScrollPosition(position);
  };

  useEffect(() => {
    window.addEventListener("scroll", handleScroll, { passive: true });
    return () => {
      window.removeEventListener("scroll", handleScroll);
    };
  }, []);

  //----------------------------higlight fastlane  abibl conditions---------------------------
  const [toasterShown, setToasterShown] = useState(true);
  const [callToaster, setCallToaster] = useState(false);

  //---------------ABIBL Popup Changes-------------------------

  const [showAbiblPopup, setShowAbiblPopup] = useState(false);

  useEffect(() => {
    if (
      (userData?.temp_data?.expiry || userData?.temp_data?.newCar) &&
      toasterShown &&
      // !lessthan993 &&
      userData?.temp_data?.fastlaneNcbPopup &&
      // type === "bike" &&
      import.meta.env.VITE_BROKER === "FYNTUNE"
    ) {
      setTimeout(() => {
        // setCallToaster(true);
        setShowAbiblPopup(true);
      }, 2000);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userData?.temp_data?.expiry, toasterShown, userData?.temp_data?.newCar]);

  //-----------------------------------------toaster policy change---------------------------------

  const [toasterPolicyChange, setToasterPolicyChange] = useState(false);
  const [policyChangeToast, setPolicyChangeToast] = useState(false);

  useEffect(() => {
    if (toasterPolicyChange && import.meta.env.VITE_BROKER === "GRAM") {
      setPolicyChangeToast(true);
    }
  }, [toasterPolicyChange]);

  //-------------------------------------------getting lowest idv-----------------------------------
  const getLowestIdv = () => {
    let Min = _.minBy(quote, (obj) => {
      const idv = obj.minIdv;
      if (!idv) {
        return Infinity;
      }
      return idv;
    });
    return parseInt(Min?.minIdv);
  };

  // checking any popup open or not
  useEffect(() => {
    if (
      idvPopup ||
      editInfoPopup ||
      journeyCategoryPopup ||
      vehicleDetailsPopup ||
      ncbPopup ||
      prevPopup ||
      policyPopup ||
      editInfoPopup2
    ) {
      setPopupOpen(true);
    } else {
      setPopupOpen(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    idvPopup,
    editInfoPopup,
    journeyCategoryPopup,
    vehicleDetailsPopup,
    ncbPopup,
    prevPopup,
    policyPopup,
    editInfoPopup2,
  ]);

  const prevPolicy = pevPolicyfunc(newCar, tempData, userData);
  let calculatedOd = calcOdFunc(userData, type, tempData);
  const bundledPolicy = bundledPolicyFunc(calculatedOd, userData, type);

  let inspectionCase =
    (userData?.temp_data?.breakIn ||
      (tempData?.policyType === "Third-party" &&
        userData?.temp_data?.tab !== "tab2")) &&
    TypeReturn(type) !== "bike" &&
    userData?.temp_data?.tab !== "tab2";

  let text = tempData?.policyType === "Third-party" ? "Third-party" : "expired";

  const inspectionAlertCondition =
    (userData?.temp_data?.breakIn ||
      (tempData?.policyType === "Third-party" &&
        userData?.temp_data?.tab !== "tab2")) &&
    TypeReturn(type) !== "bike" &&
    userData?.temp_data?.tab !== "tab2";

  //letting the fields editable on the basic of certian conditions
  const isEditable =
    !newCar &&
    // tempData?.policyType !== "Not sure" &&
    (userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !== "Y" ||
      (userData?.temp_data?.corporateVehiclesQuoteRequest?.businessType ===
        "rollover" &&
        userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !==
          "Y") ||
      (userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" &&
        userData?.temp_data?.corporateVehiclesQuoteRequest?.businessType ===
          "breakin") ||
      (!!userData?.temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
        import.meta.env.VITE_BROKER === "BAJAJ"));

  return (
    <>
      {/* ------ Mobile View Section ------- */}
      <Col style={{ ...(!lessthan993 && { display: "none" }) }}>
        <Style.FilterContainerMobile>
          <Style.FilterMobileTop highlighted={showAbiblPopup}>
            <Row>
              <VehicleDetailMobile2
                lessthan600={lessthan600}
                isMobileIOS={isMobileIOS}
                newCar={newCar}
                userData={userData}
              />
              <RegDate
                lessthan600={lessthan600}
                isMobileIOS={isMobileIOS}
                userData={userData}
                allQuoteloading={allQuoteloading}
                setEditInfoPopup={setEditInfoPopup}
                isEditable={isEditable}
              />
              <VehicleDetailMobile
                lessthan600={lessthan600}
                isMobileIOS={isMobileIOS}
                userData={userData}
                setEditInfoPopup2={setEditInfoPopup2}
              />
              <PrevPolicyExpiry
                lessthan600={lessthan600}
                isMobileIOS={isMobileIOS}
                userData={userData}
                newCar={newCar}
                tempData={tempData}
                setEditDate={setEditDate}
                setPrevPopup={setPrevPopup}
                isEditable={isEditable}
              />
            </Row>
          </Style.FilterMobileTop>
          <Style.FilterMobileBottom>
            <PrevPolicyTypeMobile
              isMobileIOS={isMobileIOS}
              userData={userData}
              lessthan400={lessthan400}
              lessthan600={lessthan600}
              newCar={newCar}
              setPolicyPopup={setPolicyPopup}
              tempData={tempData}
              bundledPolicy={bundledPolicy}
              prevPolicy={prevPolicy}
              isEditable={isEditable}
            />
            <EligibleNcb
              isMobileIOS={isMobileIOS}
              userData={userData}
              location={location}
              type={type}
              tempData={tempData}
              newCar={newCar}
              setNcbPopup={setNcbPopup}
            />
            <IDVSection
              isMobileIOS={isMobileIOS}
              userData={userData}
              setIdvPopup={setIdvPopup}
              tempData={tempData}
              getLowestIdv={getLowestIdv}
            />
          </Style.FilterMobileBottom>
        </Style.FilterContainerMobile>
        {inspectionCase && (
          <Style.AlertCoverMobile>
            Vehicle inspection is required as your previous policy is {text}
          </Style.AlertCoverMobile>
        )}
      </Col>
      {/* ------ Mobile View Section End ------- */}
      <>
        <Style.FilterContainerMain
          blockLayout={theme_conf?.isIpBlocked}
          style={{
            ...(lessthan993 && { display: "none" }),
          }}
          scroll={
            scrollPosition >
            (Theme?.QuoteBorderAndFont?.scrollHeight
              ? Theme?.QuoteBorderAndFont?.scrollHeight
              : 78.4)
          }
          highlighted={showAbiblPopup}
        >
          {prefillLoading || updateQuoteLoader ? (
            <SkeletonLoader />
          ) : (
            <Style.FilterMenuWrap>
              <ToasterPolicyChange
                callToaster={policyChangeToast}
                setCall={setPolicyChangeToast}
                setToasterShown={setToasterPolicyChange}
                content={"Policy Expiry is assumed as you have not selected it"}
                type={type}
                Theme={Theme}
              />

              <Toaster
                callToaster={callToaster}
                setCall={setCallToaster}
                setToasterShown={setToasterShown}
                content={"Not your vehicle details please edit it"}
                buttonText={"Edit"}
                setEdit={setEditInfoPopup}
                type={type}
                Theme={Theme}
              />
              <Style.FilterMenuRow>
                <Row style={{ margin: "auto" }}>
                  <VehicleDetail
                    showAbiblPopup={showAbiblPopup}
                    location={location}
                    type={type}
                    reviewData={reviewData}
                    userData={userData}
                    newCar={newCar}
                    setToasterShown={setToasterShown}
                    setShowAbiblPopup={setShowAbiblPopup}
                    setEditInfoPopup2={setEditInfoPopup2}
                    lessthan767={lessthan767}
                  />
                  {/* previous policy type and ownership  */}
                  <PrevPolicyType
                    userData={userData}
                    newCar={newCar}
                    allQuoteloading={allQuoteloading}
                    location={location}
                    type={type}
                    reviewData={reviewData}
                    tempData={tempData}
                    bundledPolicy={bundledPolicy}
                    prevPolicy={prevPolicy}
                    setPolicyPopup={setPolicyPopup}
                    setJourneyCategoryPopup={setJourneyCategoryPopup}
                    isEditable={isEditable}
                  />
                  {/* registration on and previous policu section  */}
                  <PrevPolicySection
                    userData={userData}
                    location={location}
                    type={type}
                    reviewData={reviewData}
                    newCar={newCar}
                    tempData={tempData}
                    setPrevPopup={setPrevPopup}
                    setEditDate={setEditDate}
                    setEditInfoPopup={setEditInfoPopup}
                    dateEditor={dateEditor}
                    dateOutRef={dateOutRef}
                    control={control}
                    policyMax={policyMax}
                    register={register}
                    errors={errors}
                    isEditable={isEditable}
                  />
                  <NcbSection
                    userData={userData}
                    location={location}
                    type={type}
                    reviewData={reviewData}
                    tempData={tempData}
                    newCar={newCar}
                    setNcbPopup={setNcbPopup}
                  />
                </Row>
              </Style.FilterMenuRow>
            </Style.FilterMenuWrap>
          )}
        </Style.FilterContainerMain>
        {inspectionAlertCondition && (
          <InspectionAlert
            scrollPosition={scrollPosition}
            Theme={Theme}
            tempData={tempData}
          />
        )}
        {policyPopup && (
          <PolicyTypePopup
            setPolicy={setPolicyType}
            policyType={policyType}
            show={policyPopup}
            onClose={setPolicyPopup}
            setPreviousPopup={setPrevPopup}
            type={TypeReturn(type)}
            setToasterPolicyChange={setToasterPolicyChange}
          />
        )}

        {prevPopup && (
          <PrevPolicyPopup
            show={prevPopup}
            onClose={setPrevPopup}
            edit={editDate}
            setEdit={setEditDate}
            type={TypeReturn(type)}
            showPrevPopUp={showPrevPopUp}
            assistedMode={assistedMode}
            ConfigNcb={ConfigNcb}
          />
        )}
        {ncbPopup && <NCBPopup show={ncbPopup} onClose={setNcbPopup} />}
        {vehicleDetailsPopup && (
          <VehicleDetails
            show={vehicleDetailsPopup}
            setNcb={setVehicleDetailsPopup}
            onClose={setVehicleDetailsPopup}
          />
        )}
        {journeyCategoryPopup && (
          <JourneyCategoryPopup
            show={journeyCategoryPopup}
            onClose={setJourneyCategoryPopup}
          />
        )}
        {editInfoPopup && (
          <EditInfoPopup
            show={editInfoPopup}
            onClose={setEditInfoPopup}
            type={type}
            TypeReturn={TypeReturn}
            isEditable={isEditable}
          />
        )}
        {editInfoPopup2 && (
          <EditInfoPopup2
            show={editInfoPopup2}
            onClose={setEditInfoPopup2}
            type={type}
            TypeReturn={TypeReturn}
          />
        )}
        {idvPopup && (
          <IDVPopup show={idvPopup} onClose={setIdvPopup} quote={quote} />
        )}
        {showAbiblPopup && (
          <AbiblPopup
            type={type}
            typeId={typeId}
            token={token}
            enquiryId={enquiry_id}
            show={showAbiblPopup}
            setShow={setShowAbiblPopup}
            editPopUp={editInfoPopup}
            setEditPopUp={setEditInfoPopup}
            setToasterShown={setToasterShown}
          />
        )}
      </>
    </>
  );
};

FilterContainer.propTypes = {
  reviewData: PropTypes.object,
  type: PropTypes.string,
  typeId: PropTypes.string,
  quote: PropTypes.object,
  allQuoteloading: PropTypes.bool,
  setPopupOpen: PropTypes.func,
  isMobileIOS: PropTypes.bool,
  assistedMode: PropTypes.bool,
  showPrevPopUp: PropTypes.func,
  ConfigNcb: PropTypes.bool,
  policyTypeCode: PropTypes.string,
};
