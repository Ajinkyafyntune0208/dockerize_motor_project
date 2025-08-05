import React, { useState, useEffect } from "react";
import styled, { createGlobalStyle } from "styled-components";
import PropTypes from "prop-types";
import { Row, Col, Button } from "react-bootstrap";
import { useHistory } from "react-router-dom";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import { useForm } from "react-hook-form";
import backButton from "../../../../assets/img/back-button.png";
import { Tile, Button as Btn } from "components";
import Popup from "components/Popup/Popup";
import { set_temp_data } from "modules/Home/home.slice";
import { useDispatch, useSelector } from "react-redux";
import moment from "moment";
import { toDate, fetchToken, _haptics } from "utils";
import { differenceInDays } from "date-fns";
import _ from "lodash";
import "./preInsurerPopup.scss";
import { useLocation } from "react-router";
import { differenceInYears } from "date-fns/esm";
import { QuoteCard, QuoteSkelton } from "../../quoteCard/defaultCard/quoteCard";
import { setTempData } from "../../filterConatiner/quoteFilter.slice";
import "./previousInsurerStyle.css";
import {
  compareQuotes,
  setBuyNowSingleQuoteUpdate,
  getSingleUpdatedQuote,
  getMultiUpdatedQuote,
  clearSingleQuoteError,
  CancelAll,
} from "../../quote.slice";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import Drawer from "@mui/material/Drawer";
import swal from "sweetalert";
//Renewal Inclusions
import { bike, car, cv12, cv6, cv3 } from "./renewal-data";
import { TypeReturn } from "modules/type";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

/*-----x---------date config-----x----------*/

// validation schema
const yupValidate = yup.object({
  expiry: yup.string().required("Expiry is required field").nullable(),
});

const PrevInsurerPopup2 = ({
  show,
  onClose,
  selectedId,
  type,
  selectedCompanyName,
  selectedCompanyAlias,
  selectedIcId,
  applicableAddonsLits,
  lessthan767,
  lessthan993,
  typeId,
  journey_type,
  homeStateData,
  setHomeStateData,
  filterStateData,
  setFilterStateData,
  assistedMode,
  setAssistedMode,
  onCloseAssisted,
  shortTerm3,
  shortTerm6,
  isComprehensive,
  // rti
}) => {
  const { handleSubmit, register, watch, control, errors, setValue } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });
  const history = useHistory();
  const location = useLocation();
  const _stToken = fetchToken();
  const query = new URLSearchParams(location.search);
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const shared = query.get("shared");
  const { temp_data, isRedirectionDone } = useSelector((state) => state.home);
  const [step, setStep] = useState(1);
  const prevIns = watch("prevIns") || temp_data?.prevIc;
  const prevInsothers = watch("prevInsOthers");
  const [ownerShipChange, setOwnerShipChange] = useState(
    temp_data?.carOwnership ? true : false
  );

  const enquiry_id = query.get("enquiry_id");

  const { prevInsList } = useSelector((state) => state.quoteFilter);
  const {
    quotesList,
    addOnsAndOthers,
    updateQuoteLoader,
    singleUpdatedQuote,
    multiUpdatedQuote,
    buyNowSingleQuoteUpdate,
    singleQuoteError,
  } = useSelector((state) => state.quotes);

  // page 2 logic
  const dispatch = useDispatch();

  const [noClaimMade, setNoClaimMade] = useState(
    temp_data?.noClaimMade === false ? temp_data?.noClaimMade : true
  );

  const [prevNcb, setPrevNcb] = useState(
    import.meta.env.VITE_BROKER === "OLA" ? true : false
  );
  const [prevNcb1, setPrevNcb1] = useState(
    import.meta.env.VITE_BROKER === "OLA" ? true : false
  );
  const [serviceHit, setServiceHit] = useState(false);
  const [revisedPremLoaded, setRevisedPremLoader] = useState(true);

  //prefill
  const getCalculatedNcb = (yearDiff) => {
    if (yearDiff > 8) {
      return "50%";
    } else {
      switch (yearDiff * 1) {
        case 1:
          return "0%";
        case 2:
          return "20%";
        case 3:
          return "25%";
        case 4:
          return "35%";
        case 5:
          return "45%";
        case 6:
          return "50%";
        case 7:
          return "50%";
        case 8:
          return "50%";
        default:
          return "0%";
      }
    }
  };

  //NCB logic
  const ncb =
    watch("ncb") ||
    temp_data?.ncb ||
    (temp_data?.regDate &&
      getCalculatedNcb(
        temp_data?.regDate &&
          differenceInYears(
            toDate(moment().format("DD-MM-YYYY")),
            toDate(temp_data?.regDate)
          )
      ));
  const expiry = temp_data?.expiry;
  let a = expiry;
  let b = moment().format("DD-MM-YYYY");

  let diffDays = a && b && differenceInDays(toDate(b), toDate(a));

  const onSubmitPageNoZeroDep = async (data) => {
    if (location.pathname === `/${type}/compare-quote`) {
      dispatch(compareQuotes([]));
      history.push(
        `/${type}/quotes?enquiry_id=${enquiry_id}${
          token ? `&xutm=${token}` : ``
        }${typeId ? `&typeid=${typeId}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${_stToken ? `&stToken=${_stToken}` : ``}${
          shared ? `&shared=${shared}` : ``
        }`
      );
    }
    if (document.getElementById(`clearAllAddons`)) {
      document.getElementById(`clearAllAddons`).click();
    }
    onClose(false);
  };

  const onSubmitPagePrevIns = async (data) => {
    if (location.pathname === `/${type}/compare-quote`) {
      dispatch(compareQuotes([]));
      history.push(
        `/${type}/quotes?enquiry_id=${enquiry_id}${
          token ? `&xutm=${token}` : ``
        }${typeId ? `&typeid=${typeId}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${_stToken ? `&stToken=${_stToken}` : ``}${
          shared ? `&shared=${shared}` : ``
        }`
      );
    }

    onClose(false);
  };

  const [zeroDep, setZeroDep] = useState(false);
  useEffect(() => {
    if (
      !_.isEmpty(applicableAddonsLits) &&
      applicableAddonsLits?.includes("Zero Depreciation")
    ) {
      setZeroDep(true);
    } else {
      setZeroDep(false);
    }
  }, []);

  const [rti, setRti] = useState(false);
  useEffect(() => {
    if (
      !_.isEmpty(applicableAddonsLits) &&
      applicableAddonsLits?.includes("Return To Invoice")
    ) {
      setRti(true);
    } else {
      setRti(false);
    }
  }, []);
  const onSubmitPage2 = async (data) => {
    if (
      selectedCompanyAlias === "tata_aig" &&
      ((!_.isEmpty(applicableAddonsLits) &&
        applicableAddonsLits?.includes("Zero Depreciation") &&
        prevNcb) ||
        (!_.isEmpty(applicableAddonsLits) &&
          applicableAddonsLits?.includes("Return To Invoice") &&
          prevNcb1)) &&
      false
    ) {
    } else {
      dispatch(setBuyNowSingleQuoteUpdate(true));
      dispatch(
        set_temp_data({
          isNcbConfirmed: true,
          ncb: ncb ? ncb : "0%",
          noClaimMade: noClaimMade,
          policyExpired: diffDays > 0 ? true : false,
          newNcb:
            noClaimMade && !ownerShipChange
              ? ncb === "50%"
                ? "50%"
                : temp_data?.prevShortTerm * 1
                ? ncb
                : getNewNcb(ncb)
              : "0%",
          carOwnership: ownerShipChange,
          prevIc: prevIns,
          prevIcFullName: prevInsList.filter(
            (i) => i.companyAlias === prevIns
          )[0]?.previousInsurer,
          isNcbVerified: "Y",
          // isToastShown: "Y",
          isPopupShown: "Y",
          fastlaneNcbPopup: false,
          isRedirectionDone: isRedirectionDone,
          isRenewal:
            selectedCompanyAlias ===
              prevInsList.filter((i) => i.companyAlias === prevIns)[0]
                ?.companyAlias && //OLA
            (((selectedCompanyAlias === "acko" ||
              selectedCompanyAlias === "godigit") &&
              (import.meta.env?.VITE_API_BASE_URL ===
                "https://api-ola-uat.fynity.in/api" ||
                import.meta.env?.VITE_API_BASE_URL ===
                  "https://olaapi.fynity.in/api")) ||
              //ACE
              ((selectedCompanyAlias === "tata_aig" ||
                selectedCompanyAlias === "godigit" ||
                selectedCompanyAlias === "reliance") &&
                import.meta.env?.VITE_BROKER === "ACE"))
              ? "Y"
              : "N",
          ...(!_.isEmpty(applicableAddonsLits) &&
          applicableAddonsLits?.includes("Zero Depreciation")
            ? {
                zeroDepInLastPolicy:
                  import.meta.env?.VITE_API_BASE_URL === "ABIBL" ||
                  import.meta.env.VITE_API_BASE_URL ===
                    "https://apimotor.fynity.in/api" ||
                  import.meta.env?.VITE_BROKER === "ACE" ||
                  import.meta.env?.VITE_BROKER === "GRAM"
                    ? "Y"
                    : !prevNcb
                    ? "Y"
                    : "N",
              }
            : import.meta.env?.VITE_API_BASE_URL === "ABIBL" ||
              import.meta.env.VITE_API_BASE_URL ===
                "https://apimotor.fynity.in/api" ||
              import.meta.env?.VITE_BROKER === "ACE" ||
              import.meta.env?.VITE_BROKER === "GRAM"
            ? { zeroDepInLastPolicy: "Y" }
            : { zeroDepInLastPolicy: "Y" }),
        })
      );
      assistedMode && !_.isEmpty(homeStateData) && dispatch(CancelAll(true));
      assistedMode &&
        !_.isEmpty(homeStateData) &&
        dispatch(
          set_temp_data({
            ...homeStateData,
            isNcbConfirmed: true,
          })
        );
      assistedMode &&
        !_.isEmpty(filterStateData) &&
        dispatch(
          setTempData({
            ...filterStateData,
            isNcbConfirmed: true,
          })
        );
      if (
        selectedCompanyAlias === "icici_lombard" &&
        TypeReturn(type) === "car" &&
        !noClaimMade
      ) {
        setStep(6);
      } else if (
        (selectedCompanyAlias ===
          prevInsList.filter((i) => i.companyAlias === prevIns)[0]
            ?.companyAlias &&
          !assistedMode &&
          import.meta.env?.VITE_BROKER !== "ACE" &&
          import.meta.env?.VITE_BROKER !== "OLA" &&
          import.meta.env?.VITE_BROKER !== "HEROCARE") ||
        //renewal unblock
        (selectedCompanyAlias ===
          prevInsList.filter((i) => i.companyAlias === prevIns)[0]
            ?.companyAlias &&
          !assistedMode &&
          /*------ cv ------*/
          //Comprehensive Annual
          ((TypeReturn(type) === "cv" &&
            isComprehensive &&
            !shortTerm6 &&
            !shortTerm3 &&
            !cv12.includes(selectedCompanyAlias)) ||
            //short term 3
            (TypeReturn(type) === "cv" &&
              isComprehensive &&
              !shortTerm6 &&
              shortTerm3 &&
              !cv3.includes(selectedCompanyAlias)) ||
            //short term 6
            (TypeReturn(type) === "cv" &&
              isComprehensive &&
              shortTerm6 &&
              !shortTerm3 &&
              !cv6.includes(selectedCompanyAlias)) ||
            /*--x--- cv ---x--*/
            /*------ car ------*/
            (TypeReturn(type) === "car" &&
              isComprehensive &&
              !car.includes(selectedCompanyAlias)) ||
            /*--x--- car ---x--*/
            /*------ bike ------*/
            (TypeReturn(type) === "bike" &&
              isComprehensive &&
              !bike.includes(selectedCompanyAlias))))
        /*--x--- bike ---x--*/
      ) {
        dispatch(
          set_temp_data({
            isPopupShown: "Y",
            prevIc: prevIns,
            isNcbConfirmed: true,
            prevIcFullName: prevInsList.filter(
              (i) => i.companyAlias === prevIns
            )[0]?.previousInsurer,
          })
        );

        setStep(4);
        dispatch(setBuyNowSingleQuoteUpdate(false));
        //	onClose(false);
        if (location.pathname === `/${type}/compare-quote`) {
          dispatch(compareQuotes([]));
          history.push(
            `/${type}/quotes?enquiry_id=${enquiry_id}${
              token ? `&xutm=${token}` : ``
            }${typeId ? `&typeid=${typeId}` : ``}${
              journey_type ? `&journey_type=${journey_type}` : ``
            }${shared ? `&shared=${shared}` : ``}`
          );
        }
      } else {
        !assistedMode &&
          setTimeout(() => {
            setRevisedPremLoader(false);
          }, 1500);
        !assistedMode && setStep(3);
        assistedMode && !_.isEmpty(homeStateData) && dispatch(CancelAll(false));
        assistedMode && onCloseAssisted();
      }
    }
  };
  //---------------redirect to proposal after buy now succeed-----------------

  const onSubmitPage1 = (data) => {
    if (diffDays < 91) {
      setStep(2);
    } else {
      dispatch(
        set_temp_data({
          ncb: "0%",
          newNcb: "0%",
          prevIc: prevIns,
          isNcbConfirmed: true,
          prevIcFullName: prevInsList.filter(
            (i) => i.companyAlias === prevIns
          )[0]?.previousInsurer,

          isPopupShown: "Y",
        })
      );
      assistedMode && !_.isEmpty(homeStateData) && dispatch(CancelAll(true));
      assistedMode &&
        !_.isEmpty(homeStateData) &&
        dispatch(
          set_temp_data({
            ...homeStateData,
            isNcbConfirmed: true,
          })
        );
      assistedMode &&
        !_.isEmpty(filterStateData) &&
        dispatch(
          setTempData({
            ...filterStateData,
            isNcbConfirmed: true,
          })
        );
      !assistedMode &&
        setTimeout(() => {
          setRevisedPremLoader(false);
        }, 1500);
      !assistedMode && setStep(3);
      assistedMode && !_.isEmpty(homeStateData) && dispatch(CancelAll(false));
      assistedMode && onCloseAssisted();
    }
  };

  const handleNoPrev = () => {
    dispatch(
      set_temp_data({
        ncb: "0%",
        expiry: "New",
        policyExpired: false,
        newNcb: "0%",
        prevIc: "New",
        prevIcFullName: "New",
        noClaimMade: true,
        leadJourneyEnd: true,
        carOwnership: false,
        breakIn: true,
        isNcbConfirmed: true,
      })
    );
    assistedMode && !_.isEmpty(homeStateData) && dispatch(CancelAll(true));
    assistedMode &&
      !_.isEmpty(homeStateData) &&
      dispatch(
        set_temp_data({
          ...homeStateData,
          isNcbConfirmed: true,
        })
      );
    assistedMode &&
      !_.isEmpty(filterStateData) &&
      dispatch(
        setTempData({
          ...filterStateData,
          isNcbConfirmed: true,
        })
      );
    !assistedMode &&
      setTimeout(() => {
        setRevisedPremLoader(false);
      }, 1500);
    !assistedMode && setStep(3);
    assistedMode && !_.isEmpty(homeStateData) && dispatch(CancelAll(false));
    assistedMode && onCloseAssisted();
  };
  const length = !_.isEmpty(prevInsList) ? prevInsList?.length : 0;
  const TileModels = !_.isEmpty(prevInsList)
    ? length > 8
      ? prevInsList.slice(0, 25)
      : prevInsList
    : [];

  const insData = !_.isEmpty(prevInsList)
    ? prevInsList?.map(({ companyAlias }) => {
        return {
          companyAlias,
          label: companyAlias,
          name: companyAlias,
          value: companyAlias,
          id: companyAlias,
        };
      })
    : [];

  const TileModelsDropDown = !_.isEmpty(insData)
    ? length > 8
      ? insData.slice(8, 25)
      : insData
    : [];

  const { ncbList, tempData } = useSelector((state) => state.quoteFilter);
  const myOrderedNcbList = _.sortBy(ncbList, (o) => o.discountRate);
  const [newNcb, setNewNcb] = useState(false);

  let today = moment().format("DD-MM-YYYY");
  let regDate = temp_data?.regDate;
  let diffYear =
    regDate && today && differenceInYears(toDate(today), toDate(regDate));
  const getNewNcb = (ncb) => {
    switch (ncb) {
      case "0%":
        return "20%";
      case "20%":
        return "25%";
      case "25%":
        return "35%";
      case "35%":
        return "45%";
      case "45%":
        return "50%";
      default:
        return "0%";
    }
  };

  //auto open

  //updated Quote
  const [updatedQuote, setUpdatedQuote] = useState([]);

  useEffect(() => {
    if (singleUpdatedQuote) {
      setUpdatedQuote(singleUpdatedQuote);
    }
  }, [singleUpdatedQuote]);

  //loaderUpdate
  const [loaderNewQuote, setLoaderNewQuote] = useState(false);
  useEffect(() => {
    if (step === 3) {
      setTimeout(() => {
        setLoaderNewQuote(true);
      }, 1000);
    } else {
      setLoaderNewQuote(false);
    }
  }, [step]);

  useEffect(() => {
    if (
      buyNowSingleQuoteUpdate &&
      !updateQuoteLoader &&
      loaderNewQuote &&
      selectedCompanyAlias &&
      !serviceHit
    ) {
      const data = {
        enquiryId: temp_data?.enquiry_id || enquiry_id,
        policyId: selectedId,
      };
      const ic = selectedCompanyAlias;
      const icId = selectedIcId;
      const typeUrl = TypeReturn(type);
      setServiceHit(true);
      dispatch(getSingleUpdatedQuote(ic, icId, data, typeUrl));
    }
  }, [buyNowSingleQuoteUpdate, updateQuoteLoader, loaderNewQuote, quotesList]);

  //multi updating quote filter

  const [filteredMultiUpdatedQuote, setFilteredMultiUpdatedQuote] =
    useState(multiUpdatedQuote);

  useEffect(() => {
    let filteredQuoteZeroDep = multiUpdatedQuote.filter(
      (i) => i?.masterPolicyId?.zeroDep === "0"
    );
    setFilteredMultiUpdatedQuote(filteredQuoteZeroDep);
  }, [multiUpdatedQuote]);

  //---drawer for mobile
  const [drawer, setDrawer] = useState(false);

  useEffect(() => {
    if (lessthan767 && show) {
      setTimeout(() => {
        setDrawer(true);
      }, 50);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [show]);

  //clearSingleQuoteError
  useEffect(() => {
    if (singleQuoteError) {
      swal(
        "Error",
        enquiry_id
          ? `${`Trace ID:- ${
              temp_data?.traceId ? temp_data?.traceId : enquiry_id
            }.\n Error Message:- ${singleQuoteError}`}`
          : singleQuoteError,
        "error"
      );
    }
    return () => [dispatch(clearSingleQuoteError()), onClose(false)];
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [singleQuoteError]);

  const vahanPreviousInsurer =
    temp_data?.corporateVehiclesQuoteRequest?.previousInsurer;

  const logoOfvahanPreviousInsurer = TileModels?.find(
    (i) => i?.previousInsurer === vahanPreviousInsurer
  )?.logo;

  const content = (
    <>
      <Body>
        <Page1 display={step === 1}>
          <Row>
            {logoOfvahanPreviousInsurer && (
              <div className="previousInsurer">
                <RegiHeading style={{ margin: "0px" }}>
                  Your Previous Insurance Provider
                </RegiHeading>{" "}
                <Tile
                  prevIns
                  logo={logoOfvahanPreviousInsurer}
                  register={register}
                  name={"prevIns"}
                  height={"60px"}
                  setValue={setValue}
                  Selected={prevIns || temp_data?.prevIc}
                  onClick={onSubmitPage1}
                  Imgheight={"auto"}
                  ImgWidth={"100px"}
                  shadow={`0px 0px 5px 0px ${Theme?.Tile?.color}`}
                />
              </div>
            )}

            <ModelWrap>
              {logoOfvahanPreviousInsurer ? (
                <RegiHeading>
                  Do you want to change the Previous Insurance Provider?
                </RegiHeading>
              ) : (
                <RegiHeading>
                  Who was your previous insurance provider?
                </RegiHeading>
              )}

              <TileConatiner>
                <Row className="mx-auto">
                  {!_.isEmpty(prevInsList) ? (
                    TileModels?.map(
                      ({ previousInsurer, companyAlias, logo }, index) => (
                        <Col
                          xs="6"
                          sm="6"
                          md="6"
                          lg="6"
                          xl="6"
                          className="d-flex justify-content-center mx-auto forcedWidth"
                          style={{
                            ...(lessthan767 && {
                              paddingLeft: "10px",
                              paddingRight: "10px",
                            }),
                          }}
                        >
                          <Tile
                            prevIns
                            logo={logo}
                            id={companyAlias}
                            register={register}
                            name={"prevIns"}
                            value={companyAlias}
                            height={"60px"}
                            setValue={setValue}
                            Selected={prevIns || temp_data?.prevIc}
                            onClick={onSubmitPage1}
                            Imgheight={"auto"}
                            ImgWidth={lessthan767 ? "100%" : "100px"}
                            border={"1px solid gray"}
                          />
                        </Col>
                      )
                    )
                  ) : (
                    <Col
                      sm="12"
                      md="12"
                      lg="12"
                      xl="12"
                      className="d-flex flex-column justify-content-center align-content-center"
                    >
                      <img
                        src={`${
                          import.meta.env.VITE_BASENAME !== "NA"
                            ? `/${import.meta.env.VITE_BASENAME}`
                            : ""
                        }/assets/images/nodata3.png`}
                        alt="nodata"
                        height="200"
                        width="200"
                        className="mx-auto"
                      />
                      <label
                        className="text-secondary text-center mt-1"
                        style={{ fontSize: "16px" }}
                      >
                        No Data Found
                      </label>
                    </Col>
                  )}
                </Row>
              </TileConatiner>
              <TabContinueWrap></TabContinueWrap>
            </ModelWrap>
          </Row>
        </Page1>
        <Page2 display={step === 2}>
          <Row>
            <BackBtn
              onClick={() => {
                setStep(step - 1);
              }}
            >
              <img src={backButton} alt="backButton" />
            </BackBtn>
            <ModelWrap ncbPopup>
              <Row
                className="w-100"
                style={{
                  marginLeft: "8px",
                  marginTop: "10px",
                  ...(lessthan767 && {
                    marginTop: "10px",
                  }),
                }}
              >
                <>
                  <Row
                    className={`${
                      { lessthan767 } && "w-100"
                    } d-flex justify-content-center mt-1 mx-auto`}
                  >
                    <Col
                      sm="6"
                      md="6"
                      lg="6"
                      xl="6"
                      className="d-flex justify-content-center px-3 mt-2"
                    >
                      <RegiHeading3>
                        {" "}
                        Did you make a claim in your existing policy?
                      </RegiHeading3>
                    </Col>
                    <div className="px-5 d-flex justify-content-center mx-auto ncbColConatinerPrevPolicy">
                      <Col
                        sm="6"
                        md="6"
                        lg="6"
                        xl="6"
                        className="d-flex justify-content-center px-3 mt-2"
                      >
                        <Button
                          style={{
                            width: "150px",
                          }}
                          size={lessthan767 ? "lg" : ""}
                          onClick={() => setNoClaimMade(false)}
                          variant={
                            !noClaimMade
                              ? Theme?.journeyType?.buttonVariant ||
                                Theme?.buttonVariantScheme?.[0] ||
                                "success"
                              : Theme?.journeyType?.outlineVariant ||
                                Theme?.outlineButtonVariantScheme?.[0] ||
                                "outline-success"
                          }
                        >
                          Yes
                        </Button>
                      </Col>
                      <Col
                        sm="6"
                        md="6"
                        lg="6"
                        xl="6"
                        className="d-flex justify-content-center px-3 mt-2"
                      >
                        <Button
                          style={{
                            width: "150px",
                          }}
                          size={lessthan767 ? "lg" : ""}
                          onClick={() => setNoClaimMade(true)}
                          variant={
                            noClaimMade
                              ? Theme?.journeyType?.buttonVariant ||
                                Theme?.buttonVariantScheme?.[0] ||
                                "success"
                              : Theme?.journeyType?.outlineVariant ||
                                Theme?.outlineButtonVariantScheme?.[0] ||
                                "outline-success"
                          }
                        >
                          No
                        </Button>
                      </Col>
                    </div>
                  </Row>
                  {!(selectedCompanyAlias === "hdfc_ergo") && (
                    <Row
                      className={`${
                        { lessthan767 } && "w-100"
                      } d-flex justify-content-center mt-3 mx-auto `}
                    >
                      <Col
                        sm="6"
                        md="6"
                        lg="6"
                        xl="6"
                        className="d-flex justify-content-center mt-2"
                      >
                        <RegiHeading3>
                          {" "}
                          Did vehicle's ownership change in the last 12 months?
                        </RegiHeading3>
                      </Col>
                      <div className="px-5 d-flex justify-content-center mt-2 ncbColConatinerPrevPolicy">
                        <Col
                          sm="6"
                          md="6"
                          lg="6"
                          xl="6"
                          className="d-flex justify-content-center "
                        >
                          <Button
                            style={{
                              width: "150px",
                            }}
                            size={lessthan767 ? "lg" : ""}
                            onClick={() => setOwnerShipChange(true)}
                            variant={
                              ownerShipChange
                                ? Theme?.journeyType?.buttonVariant ||
                                  Theme?.buttonVariantScheme?.[0] ||
                                  "success"
                                : Theme?.journeyType?.outlineVariant ||
                                  Theme?.outlineButtonVariantScheme?.[0] ||
                                  "outline-success"
                            }
                          >
                            Yes
                          </Button>
                        </Col>
                        <Col
                          sm="6"
                          md="6"
                          lg="6"
                          xl="6"
                          className="d-flex justify-content-center "
                        >
                          <Button
                            style={{
                              width: "150px",
                            }}
                            size={lessthan767 ? "lg" : ""}
                            onClick={() => setOwnerShipChange(false)}
                            variant={
                              !ownerShipChange
                                ? Theme?.journeyType?.buttonVariant ||
                                  Theme?.buttonVariantScheme?.[0] ||
                                  "success"
                                : Theme?.journeyType?.outlineVariant ||
                                  Theme?.outlineButtonVariantScheme?.[0] ||
                                  "outline-success"
                            }
                          >
                            No
                          </Button>
                        </Col>
                      </div>
                    </Row>
                  )}
                  {
                    <Row className="w-100 d-flex justify-content-center mt-3 mx-auto">
                      <div className="px-5 d-flex flex-column align-content-center mx-auto mt-4">
                        <RegiHeading>
                          {"Enter your Existing NCB (No Claim Bonus)"}
                        </RegiHeading>

                        <NcbWrapPrev className="ncbWrapPrev">
                          <div
                            className="vehRadioWrap ncsPercentCheck ncbLists "
                            style={{ display: "block" }}
                          >
                            {myOrderedNcbList.map((item, index) => (
                              <>
                                <input
                                  type="radio"
                                  id={item?.ncbId}
                                  name="ncb"
                                  value={`${item?.discountRate}%`}
                                  ref={register}
                                  defaultChecked={
                                    temp_data?.leadJourneyEnd
                                      ? temp_data?.ncb
                                        ? temp_data?.ncb ===
                                          `${item?.discountRate}%`
                                        : `${item?.discountRate}%` ===
                                          getCalculatedNcb(diffYear)
                                      : `${item?.discountRate}%` ===
                                        getCalculatedNcb(diffYear)
                                  }
                                  onInput={() =>
                                    setNewNcb(
                                      noClaimMade && !ownerShipChange
                                        ? myOrderedNcbList[index + 1]
                                            ?.discountRate + "%"
                                        : "0%"
                                    )
                                  }
                                />

                                <label
                                  style={{ cursor: "pointer" }}
                                  for={item?.ncbId}
                                >
                                  {item?.discountRate}%
                                </label>
                              </>
                            ))}
                          </div>
                        </NcbWrapPrev>
                      </div>
                    </Row>
                  }
                </>
                {true ? (
                  <noscript></noscript>
                ) : (
                  <div style={{ width: "100%" }}>
                    {zeroDep && (
                      <Row
                        className="w-100 d-flex justify-content-center mt-4 mx-auto "
                        style={{
                          ...(temp_data?.breakIn && {
                            visibility: "hidden",
                          }),
                        }}
                      >
                        <Col
                          sm="6"
                          md="6"
                          lg="6"
                          xl="6"
                          className="d-flex justify-content-center px-3 mt-2"
                        >
                          <RegiHeading3>
                            {" "}
                            Was zero depreciation a part of your previous
                            policy?
                          </RegiHeading3>
                        </Col>

                        <div className="px-5 d-flex justify-content-center mx-auto ncbColConatinerPrevPolicy">
                          <Col
                            sm="6"
                            md="6"
                            lg="6"
                            xl="6"
                            className="d-flex justify-content-center px-3 mt-2"
                          >
                            <Button
                              style={{
                                width: "100%",
                              }}
                              size={lessthan767 ? "lg" : ""}
                              onClick={() => setPrevNcb(false)}
                              variant={
                                !prevNcb
                                  ? Theme?.journeyType?.buttonVariant ||
                                    Theme?.buttonVariantScheme?.[0] ||
                                    "success"
                                  : Theme?.journeyType?.outlineVariant ||
                                    Theme?.outlineButtonVariantScheme?.[0] ||
                                    "outline-success"
                              }
                            >
                              Yes
                            </Button>
                          </Col>
                          <Col
                            sm="6"
                            md="6"
                            lg="6"
                            xl="6"
                            className="d-flex justify-content-center px-3 mt-2"
                          >
                            <Button
                              style={{
                                width: "100%",
                              }}
                              size={lessthan767 ? "lg" : ""}
                              onClick={() => setPrevNcb(true)}
                              variant={
                                prevNcb
                                  ? Theme?.journeyType?.buttonVariant ||
                                    Theme?.buttonVariantScheme?.[0] ||
                                    "success"
                                  : Theme?.journeyType?.outlineVariant ||
                                    Theme?.outlineButtonVariantScheme?.[0] ||
                                    "outline-success"
                              }
                            >
                              No
                            </Button>
                          </Col>
                        </div>
                      </Row>
                    )}

                    {selectedCompanyAlias === "tata_aig" && rti && (
                      <>
                        <Row className="w-100 d-flex justify-content-center mt-4 mx-auto ">
                          <Col
                            sm="6"
                            md="6"
                            lg="6"
                            xl="6"
                            className="d-flex justify-content-center px-3 mt-2"
                          >
                            <RegiHeading3>
                              {" "}
                              Was return to invoice a part of your previous
                              policy?
                            </RegiHeading3>
                          </Col>

                          <div className="px-5 d-flex justify-content-center mx-auto ncbColConatinerPrevPolicy">
                            <Col
                              sm="6"
                              md="6"
                              lg="6"
                              xl="6"
                              className="d-flex justify-content-center px-3 mt-2"
                            >
                              <Button
                                style={{
                                  width: "100%",
                                }}
                                size={lessthan767 ? "lg" : ""}
                                onClick={() => setPrevNcb1(false)}
                                variant={
                                  !prevNcb1
                                    ? Theme?.journeyType?.buttonVariant ||
                                      Theme?.buttonVariantScheme?.[0] ||
                                      "success"
                                    : Theme?.journeyType?.outlineVariant ||
                                      Theme?.outlineButtonVariantScheme?.[0] ||
                                      "outline-success"
                                }
                              >
                                Yes
                              </Button>
                            </Col>
                            <Col
                              sm="6"
                              md="6"
                              lg="6"
                              xl="6"
                              className="d-flex justify-content-center px-3 mt-2"
                            >
                              <Button
                                style={{
                                  width: "100%",
                                }}
                                size={lessthan767 ? "lg" : ""}
                                onClick={() => setPrevNcb1(true)}
                                variant={
                                  prevNcb1
                                    ? Theme?.journeyType?.buttonVariant ||
                                      Theme?.buttonVariantScheme?.[0] ||
                                      "success"
                                    : Theme?.journeyType?.outlineVariant ||
                                      Theme?.outlineButtonVariantScheme?.[0] ||
                                      "outline-success"
                                }
                              >
                                No
                              </Button>
                            </Col>
                          </div>
                        </Row>
                      </>
                    )}
                  </div>
                )}

                <NCBCalcMessage>
                  <div className="ncb_msg">
                    <div className="image"></div>
                    <p className="messagetxt">
                      {!noClaimMade && ownerShipChange
                        ? "Since you have made claim in your existing policy & changed ownership, your NCB will be reset to 0%"
                        : ownerShipChange || !noClaimMade
                        ? !noClaimMade
                          ? "Since you have made claim in your existing policy, your NCB will be reset to 0%"
                          : "Since you have changed ownership, your NCB will be reset to 0%"
                        : "NCB % is assumed considering no claims made and no ownership changed in existing policy"}
                      <b></b>.
                    </p>
                  </div>
                </NCBCalcMessage>
              </Row>

              <Col
                sm="12"
                md="12"
                lg="12"
                xl="12"
                className="d-flex justify-content-center mt-5"
                style={{
                  ...(lessthan767 && {
                    paddingLeft: "30px",
                  }),
                }}
              >
                <Btn
                  buttonStyle="outline-solid"
                  hex1={Theme?.Registration?.otherBtn?.hex1 || "#bdd400"}
                  hex2={Theme?.Registration?.otherBtn?.hex2 || "#bdd400"}
                  borderRadius="10px"
                  onClick={() => [_haptics([100, 0, 50]), onSubmitPage2()]}
                >
                  Proceed
                </Btn>
              </Col>
            </ModelWrap>
          </Row>
        </Page2>
        <Page3 display={step === 3}>
          <Row>
            {/* <BackBtn
							onClick={() => {
								if (temp_data?.prevIc === "New") {
									setStep(1);
								} else {
									setStep(step - 1);
								}
							}}
						>
							<img src={backButton} />
						</BackBtn> */}
            <ModelWrap reviced>
              <RegiHeading>Revised premium after change in NCB </RegiHeading>
              {TypeReturn(type) === "car" &&
              selectedCompanyAlias === "godigit" &&
              addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation") &&
              addOnsAndOthers?.selectedAddons?.length === 1 &&
              import.meta.env.VITE_BROKER !== "RB" &&
              false ? (
                <>
                  {!_.isEmpty(filteredMultiUpdatedQuote) ? (
                    <Row>
                      {filteredMultiUpdatedQuote?.map((item, index) => (
                        <QuoteCard
                          quote={filteredMultiUpdatedQuote[index]}
                          popupCard={true}
                          multiPopupCard={true}
                          type={type}
                          lessthan767={lessthan767}
                          typeId={typeId}
                          journey_type={journey_type}
                        />
                      ))}
                    </Row>
                  ) : (
                    <>
                      <QuoteSkelton
                        style={
                          {
                            // ...(!lessthan767 && {
                            //   marginLeft: "50px",
                            //   marginRight: "50px",
                            // }),
                          }
                        }
                        popupCard={true}
                        type={type}
                        maxAddonsMotor={[]}
                        multiPopupCard={true}
                      />
                    </>
                  )}
                </>
              ) : (
                <QuoteCardContainer
                  style={{
                    ...(!lessthan767 && {
                      width: "100%",
                      margin: "auto",
                    }),
                  }}
                  // style={{
                  //   ...(!lessthan767 && {
                  //     marginLeft: "50px",
                  //     marginRight: "50px",
                  //   }),
                  // }}
                >
                  {singleUpdatedQuote ? (
                    <QuoteCard
                      quote={singleUpdatedQuote}
                      popupCard={true}
                      type={type}
                      lessthan767={lessthan767}
                      typeId={typeId}
                      journey_type={journey_type}
                    />
                  ) : !singleUpdatedQuote ? (
                    <QuoteSkelton popupCard={true} lessthan767={lessthan767} />
                  ) : (
                    // <NoQuote>
                    // 	<img
                    // 		src="/assets/images/nodata3.png"
                    // 		alt="nodata"
                    // 		height="200"
                    // 		width="200"
                    // 		className="mx-auto"
                    // 	/>
                    // 	<label
                    // 		className="text-secondary text-center mt-1"
                    // 		style={{ fontSize: "16px" }}
                    // 	>
                    // 		No Quote Found
                    // 	</label>
                    // </NoQuote>
                    <QuoteSkelton
                      popupCard={true}
                      type={type}
                      maxAddonsMotor={[]}
                    />
                  )}
                </QuoteCardContainer>
              )}

              <NCBDeclaration>
                *Please confirm that the NCB% declared by you is accurate. If
                found incorrect, insurer may reject your claim.
              </NCBDeclaration>
            </ModelWrap>
          </Row>
        </Page3>
        <Page4 display={step === 4}>
          <Row>
            <LoaderPrevInsurer>
              {/* <div className="loadingText">
								We are not allowing renewal at the moment please select
								different insusrance provider
							</div> */}
              <div
                className="row"
                style={{
                  display: "flex",
                  justifyContent: "center",
                  marginTop: "10px",
                }}
              >
                <div className="col-10 mx-2 top-heading">
                  <p className="top-heading-header">
                    <strong>Please Choose your Insurance</strong>
                  </p>
                  <p className="text-muted top-heading-description">
                    Oops! Considering your present policy is with{" "}
                    {temp_data.prevIcFullName}, the insurer does not grant you
                    to buy a {temp_data.prevIcFullName} policy. Sorry for the
                    inconvenience
                  </p>
                </div>
                <div className="col-10 mx-2 other_options">
                  <div className="row no-gutters">
                    <div className="col-1">
                      <img
                        src={`${
                          import.meta.env.VITE_BASENAME !== "NA"
                            ? `/${import.meta.env.VITE_BASENAME}`
                            : ""
                        }/assets/images/verify.png`}
                        className="other_options_image"
                      />
                    </div>
                    <div className="col-10 other_options_text_div">
                      <p className="other_options_text">
                        <span>Hurray, still you have other options.</span>
                      </p>
                    </div>
                    <div className="choose_button_div">
                      <button
                        className="choose_button"
                        onClick={onSubmitPagePrevIns}
                      >
                        Choose From others
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              {/* <div className="loadingText">
								Please wait while we get you updated Quotes
							</div>
							<div className="lds-facebook">
								<div></div>
								<div></div>
								<div></div>
							</div> */}
            </LoaderPrevInsurer>
          </Row>
        </Page4>
        <Page5 display={step === 5}>
          <Row>
            <LoaderPrevInsurer>
              {/* <div className="loadingText">
								We are not allowing renewal at the moment please select
								different insusrance provider
							</div> */}
              <div
                className="row"
                style={{
                  display: "flex",
                  justifyContent: "center",
                  marginTop: "20px",
                }}
              >
                <div className="col-10 mx-2 top-heading">
                  <p className="top-heading-header">
                    <strong>
                      Please Choose plan without zero depreciation
                    </strong>
                  </p>
                  <p className="text-muted top-heading-description">
                    Oops! Considering your previous policy is without zero
                    depreciation , the insurer does not grant you to buy a
                    policy with zero depreciation. Sorry for the inconvenience
                  </p>
                </div>
                <div className="col-10 mx-2 other_options">
                  <div className="row no-gutters">
                    <div className="col-1">
                      <img
                        src={`${
                          import.meta.env.VITE_BASENAME !== "NA"
                            ? `/${import.meta.env.VITE_BASENAME}`
                            : ""
                        }/assets/images/verify.png`}
                        className="other_options_image"
                      />
                    </div>
                    <div className="col-10 other_options_text_div">
                      <p className="other_options_text">
                        <span>Hurray, still you have other options.</span>
                      </p>
                    </div>
                    <div className="choose_button_div">
                      <button
                        className="choose_button"
                        onClick={onSubmitPageNoZeroDep}
                      >
                        Choose From plans without zero depreciation
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              {/* <div className="loadingText">
								Please wait while we get you updated Quotes
							</div>
							<div className="lds-facebook">
								<div></div>
								<div></div>
								<div></div>
							</div> */}
            </LoaderPrevInsurer>
          </Row>
        </Page5>
        <Page6 display={step === 6}>
          <Row>
            <LoaderPrevInsurer>
              {/* <div className="loadingText">
								We are not allowing renewal at the moment please select
								different insusrance provider
							</div> */}
              <div
                className="row"
                style={{
                  display: "flex",
                  justifyContent: "center",
                  marginTop: "20px",
                }}
              >
                <div className="col-10 mx-2 top-heading">
                  <p className="top-heading-header">
                    <strong>Please choose another Insurance provider</strong>
                  </p>
                  <p className="text-muted top-heading-description">
                    Oops! Considering you have made claim in previous policy,
                    the insurer does not grant you to buy a policy Sorry for the
                    inconvenience
                  </p>
                </div>
                <div className="col-10 mx-2 other_options">
                  <div className="row no-gutters">
                    <div className="col-1">
                      <img
                        src={`${
                          import.meta.env.VITE_BASENAME !== "NA"
                            ? `/${import.meta.env.VITE_BASENAME}`
                            : ""
                        }/assets/images/verify.png`}
                        className="other_options_image"
                      />
                    </div>
                    <div className="col-10 other_options_text_div">
                      <p className="other_options_text">
                        <span>Hurray, still you have other options.</span>
                      </p>
                    </div>
                    <div className="choose_button_div">
                      <button
                        className="choose_button"
                        onClick={onSubmitPageNoZeroDep}
                      >
                        Choose From other IC
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              {/* <div className="loadingText">
								Please wait while we get you updated Quotes
							</div>
							<div className="lds-facebook">
								<div></div>
								<div></div>
								<div></div>
							</div> */}
            </LoaderPrevInsurer>
          </Row>
        </Page6>
      </Body>
    </>
  );

  // const Page2 = (
  // 	<>
  // 		<Body></Body>
  // 	</>
  // );

  return !lessthan767 ? (
    <Popup
      height={lessthan993 ? "100%" : step === 4 ? "auto" : "auto"}
      width={lessthan993 ? "100%" : step === 4 ? "520px" : "650px"}
      top="40%"
      show={show}
      onClose={onClose}
      content={content}
      position="middle"
      //	backGround="grey"
      outside={step === 5 || step === 4 || assistedMode}
      overFlowDisable={true}
      hiddenClose={step === 5 || step === 4 || assistedMode}
      //	backGroundImage={true}
      svgPosition
    />
  ) : (
    <>
      <React.Fragment key={"bottom"} style={{ borderRadius: "5% 5% 0% 0%" }}>
        <Drawer
          anchor={"bottom"}
          open={drawer}
          onClose={() => {
            setDrawer(false);
            onClose(false);
          }}
          onOpen={() => setDrawer(true)}
          ModalProps={{
            keepMounted: true,
          }}
          elevation={30}
        >
          <MobileDrawerBody>
            {step !== 5 && step !== 4 && !assistedMode && (
              <CloseButton
                onClick={() => {
                  setDrawer(false);
                  onClose(false);
                }}
              >
                <svg
                  version="1.1"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                  style={{ height: "25px" }}
                >
                  <path
                    fill={"#000"}
                    d="M12,2c-5.53,0 -10,4.47 -10,10c0,5.53 4.47,10 10,10c5.53,0 10,-4.47 10,-10c0,-5.53 -4.47,-10 -10,-10Zm5,13.59l-1.41,1.41l-3.59,-3.59l-3.59,3.59l-1.41,-1.41l3.59,-3.59l-3.59,-3.59l1.41,-1.41l3.59,3.59l3.59,-3.59l1.41,1.41l-3.59,3.59l3.59,3.59Z"
                  ></path>
                  <path fill="none" d="M0,0h24v24h-24Z"></path>
                </svg>
              </CloseButton>
            )}
            {content}
          </MobileDrawerBody>
        </Drawer>
      </React.Fragment>

      <GlobalStyle
        disabledBackdrop={
          step === 5 || step === 4 || assistedMode ? true : false
        }
      />
    </>
  );
};

// PropTypes
PrevInsurerPopup2.propTypes = {
  show: PropTypes.bool,
  onClose: PropTypes.func,
};

// DefaultTypes
PrevInsurerPopup2.defaultProps = {
  show: false,
  onClose: () => {},
};

//comment

const GlobalStyle = createGlobalStyle`
body {
	.MuiDrawer-paperAnchorBottom {
		border-radius: 3% 3% 0px 0px;
		z-index: 99999 !important;
	}
	.css-1u2w381-MuiModal-root-MuiDrawer-root {
    z-index: 100000 !important;
  }
	.css-i9fmh8-MuiBackdrop-root-MuiModal-backdrop {
    pointer-events: ${({ disabledBackdrop }) =>
      disabledBackdrop ? "none !important" : ""};
  }
}
`;
const Body = styled.div`
  padding: 0 15px 15px;
  position: relative;
  margin-top: 15px;
  @media (max-width: 993px) {
  }
`;
const ModelWrap = styled.div`
  float: left;
  width: 100%;
  padding: 10px 22px 22px 22px;
  min-height: ${(props) => (props?.reviced ? " 480px" : "360px")};
  height: ${(props) =>
    props?.ncbPopup ? "560px" : props?.reviced ? " 480px" : "460px"};
  max-height: ${(props) =>
    props?.ncbPopup ? "580px" : props?.reviced ? " 480px" : "560px"};
  //overflow-y: scroll;
  //	margin-top: 30px;

  .btn-danger {
    color: #fff;
    background-color: ${({ theme }) =>
      theme.prevPolicy?.color1 || theme?.prevPop?.background || "#fb6c46"};
    border-color: ${({ theme }) =>
      theme.prevPolicy?.color2 || theme?.prevPop?.color || "#fb6c47"};

    &:focus,
    &.focus {
      box-shadow: ${({ theme }) =>
        theme.prevPolicy?.boxShadow || theme.prevPop?.boxShadow};
    }
  }

  .btn-outline-danger {
    color: ${({ theme }) =>
      theme.prevPolicy?.color1 || theme?.prevPop?.background || "#fb6c46"};
    border-color: ${({ theme }) =>
      theme.prevPolicy?.color2 || theme?.prevPop?.color || "#fb6c47"};
  }

  .btn-outline-danger:not(:disabled):not(.disabled).active,
  .btn-outline-danger:not(:disabled):not(.disabled):active,
  .show > .btn-outline-danger.dropdown-toggle {
    color: #fff;
    background-color: ${({ theme }) =>
      theme.prevPolicy?.color1 || theme?.prevPop?.background || "#fb6c46"};
    border-color: ${({ theme }) =>
      theme.prevPolicy?.color2 || theme?.prevPop?.color || "#fb6c47"};
  }

  .btn-outline-danger:hover {
    color: #fff;
    background-color: ${({ theme }) =>
      theme.prevPolicy?.color1 || theme?.prevPop?.background || "#fb6c46"};
    border-color: ${({ theme }) =>
      theme.prevPolicy?.color2 || theme?.prevPop?.color || "#fb6c47"};
  }

  @media (max-width: 993px) {
    max-height: 600px;
    height: auto;
    overflow-x: ${(props) => (props?.ncbPopup ? "clip" : "clip")} !important;
    padding: ${(props) =>
      props?.ncbPopup
        ? "30px 22px 22px 22px"
        : "10px 22px 22px 22px"} !important;
  }

  @media (max-width: 767px) {
    padding: ${(props) =>
      props?.ncbPopup ? "0px 10px 0px 0px" : "10px 22px 22px 22px"} !important;
    min-height: 510px;
  }
`;
const RegiHeading = styled.div`
  text-align: center !important;
  font-family: ${({ theme }) =>
    theme.regularFont?.fontFamily || " Merriweather, Georgia, serif"};
  font-weight: 600;
  font-size: 19px;
  line-height: 24px;
  color: #333;
  width: 100%;
  text-align: left;
  margin-top: 5px;
  margin-bottom: 0px;
  @media (max-width: 767px) {
    font-size: 15px;
  }
`;

const RegiHeading2 = styled.div`
  text-align: center !important;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 17px;
  line-height: 24px;
  color: #333;
  width: 100%;
  text-align: left;
  margin-top: 54px;
  margin-bottom: 24px;
`;
const RegiHeading3 = styled.div`
  text-align: left !important;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 16px;
  line-height: 24px;
  color: #333;
  margin-top: 7.5px;
  white-space: nowrap;
  @media (max-width: 993px) {
    white-space: break-spaces;
    font-size: 14px;
  }
  @media (max-width: 550px) {
    margin-bottom: 15px;
  }
`;
const TabContinueWrap = styled.div`
  /* float: left; */
  position: relative;
  bottom: 0px;
  top: 10px;
  left: 0;
  width: 100%;
  text-align: center;
  margin-top: 0;
  & div {
    font-size: 13px;
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
    color: #000;
    text-decoration: underline;
    cursor: pointer;
    margin-top: 8px;
  }
`;

const TileConatiner = styled.div`
  position: relative;
  left: 0px;
  top: 0px;

  .forcedWidth {
    flex: 0 0 19.666667%;
    max-width: 19.666667%;
  }
  @media (max-width: 993px) {
    .forcedWidth {
      flex: 0 0 33.33337%;
      max-width: 33.33337%;
    }
  }
`;

const BackBtn = styled.div`
  border: none;
  background: none;
  color: #808080;
  font-size: 14px;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  margin-top: -1px;
  margin-left: 16px;
  display: block;
  position: absolute;
  text-transform: uppercase;
  cursor: pointer;
  @media (max-width: 993px) {
    margin-top: 0px;
    margin-left: 15px;
  }
`;

const NcbWrapPrev = styled.div`
  .vehRadioWrap input:checked + label {
    background-color: ${({ theme }) =>
      theme.QuoteBorderAndFont?.journeyCategoryButtonColor || "#000"};
    color: #fff;
  }
  .ncbLists {
    display: flex !important;
    justify-content: center;
  }
  @media only screen and (max-width: 390px) and (min-width: 320px) {
    max-width: 320px;
  }
`;

const Page1 = styled.div`
  display: ${(props) => (props.display ? "block" : "none")};
`;

const Page2 = styled.div`
  display: ${(props) => (props.display ? "block" : "none")};
`;
const Page3 = styled.div`
  display: ${(props) => (props.display ? "block" : "none")};
`;

const Page4 = styled.div`
  display: ${(props) => (props.display ? "block" : "none")};
`;

const Page5 = styled.div`
  display: ${(props) => (props.display ? "block" : "none")};
`;

const Page6 = styled.div`
  display: ${(props) => (props.display ? "block" : "none")};
`;

const QuoteCardContainer = styled.div`
  // margin: ${(props) => (props.lessthan767 ? "0px 0px" : "0px 50px")};
  display: flex;
  justify-content: center;
  align-items: center;
`;

const PremReviced = styled.div`
  display: flex;
  justify-content: center;
  margin-top: 20px;
  align-items: center;
  .listing {
    margin: 10px auto;
    padding: 0px;
    max-width: 230px;
  }
  .listing li {
    display: block;
    list-style: none;
    font-size: 14px;
    font-weight: 400;
    padding-bottom: 5px;
  }
  .listing li.old_premium b.old_price {
    font-weight: 400;
    position: relative;
    display: inline-block;
    color: #696969;
  }
  .listing li b.current_premium {
    color: #fc4804;
    font-weight: 500;
  }
  .listing li b {
    float: right;
  }
  .listing li span {
    float: left;
  }
`;

const NoQuote = styled.div`
  width: 100%;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  margin-top: 100px;
`;

const NCBDeclaration = styled.div`
  width: 95%;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  margin-top: 20px;
  font-size: 15px;
  text-align: center;
  position: absolute;
  bottom: 10;
  @media (max-width: 768px) {
    width: 85%;
  }
`;

const NCBCalcMessage = styled.div`
  display: flex;
  justify-content: center;
  align-content: center;
  align-items: center;
  width: 100%;
  .ncb_msg {
    background: #f9ffc8;
    line-height: normal;
    padding-left: 0px;
    display: flex;
    align-items: center;
    margin-left: auto;
    margin-right: auto;
    margin-top: 10px;
    width: 80%;
    height: 95px;
    border-radius: 10px;
  }
  .ncb_msg .image {
    background-image: url(${import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ""}/assets/images/icon/bulb.png);
    background-repeat: no-repeat;
    width: 160px;
    // width: 93px;
    height: 83px;
    left: -28px;
  }
  .newpopup_wrapper .ncb_msg p {
    color: #172b4d;
    line-height: normal;
    font-size: 12px;
  }
  .messagetxt {
    margin-top: 10px;
    margin-left: 10px;
  }
  @media (max-width: 768px) {
    .ncb_msg .image {
      display: none;
    }
    .ncb_msg {
      width: 90%;
      padding-left: 0px;
      height: 70px;
    }
  }
`;

const LoaderPrevInsurer = styled.div`
  overflow: hidden;

  overflow: hidden;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  margin-top: 20px;
  text-align: center !important;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `Merriweather, Georgia, serif`};
  font-weight: 600;
  font-size: 20px;

  .loadingText {
    margin-bottom: 20px;
    padding: 10px;
  }
  .lds-facebook {
    display: inline-block;
    position: relative;
    width: 80px;
    height: 80px;
  }
  .lds-facebook div {
    display: inline-block;
    position: absolute;
    left: 8px;
    width: 16px;
    background: #b6c725;
    animation: lds-facebook 1.2s cubic-bezier(0, 0.5, 0.5, 1) infinite;
  }
  .lds-facebook div:nth-child(1) {
    left: 8px;
    animation-delay: -0.24s;
  }
  .lds-facebook div:nth-child(2) {
    left: 32px;
    animation-delay: -0.12s;
  }
  .lds-facebook div:nth-child(3) {
    left: 56px;
    animation-delay: 0;
  }
  @keyframes lds-facebook {
    0% {
      top: 8px;
      height: 64px;
    }
    50%,
    100% {
      top: 24px;
      height: 32px;
    }
  }

  .top-heading {
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `basier_squareregular`};
    background: rgba(255, 0, 0, 0.06);
    padding: 16px;
    text-align: left;
    border-radius: 8px;
    letter-spacing: 0.5px;
  }
  .top-heading-header {
    font-size: 1.1rem;
    margin-bottom: 6px;
  }
  .top-heading-description {
    font-size: 0.85rem;
  }
  .other_options {
    text-align: left;
    margin-top: 30px;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `basier_squareregular`};
  }
  .other_options_image {
    width: 45px;
    height: 45px;
  }
  .other_options_text_div {
    display: flex;
    align-items: center;
  }
  .other_options_text {
    font-size: 0.9rem;
    padding-left: 20px;
    letter-spacing: 1px;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `basier_squareregular`};
    margin: 0;
    @media (max-width: 768px) {
      padding-left: 30px;
    }
  }
  .choose_button_div {
    width: 100%;
    margin-top: 30px;
    margin-bottom: 20px;
    text-align: center;
  }
  .choose_button {
    font-size: 1rem;
    width: 75%;
    padding: 8px 0px;
    background: ${({ theme }) => theme?.QuoteCard?.color || "#bdd400"};
    border-radius: 10px;
    letter-spacing: 1px;
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
    color: #fff;
    border: none;
    @media (max-width: 768px) {
      width: 100%;
      font-size: 0.85rem;
    }
  }
`;
const PaymentTermTitle = styled.div`
  float: left;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 16px;
  line-height: 20px;
  color: #333;
  padding-bottom: 10px;
  border-bottom: solid 1px #e3e4e8;
`;

const PopupSubTitle = styled.div`
  float: left;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  font-size: 14px;
  line-height: 20px;
  color: #333;
  margin-top: 16px;
  margin-bottom: 16px;
`;

const ApplyButton = styled.button`
  width: 117px;
  height: 32px;
  border-radius: 4px;
  background-color: ${({ theme }) => theme.QuotePopups?.color || " #f3ff91"};
  border: ${({ theme }) => theme.QuotePopups?.border || "  solid 1px #bdd400"};
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 15px;
  line-height: 20px;
  color: #000;
  /* text-transform: uppercase; */
  margin: 0;
  float: right;
  border-radius: 50px;
  margin-right: 10px;
`;

const CloseButtonCpa = styled.button`
  width: 117px;
  height: 32px;
  border-radius: 4px;
  background-color: #a2a9ab;
  border: solid 1px #a2a9ab;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 15px;
  line-height: 20px;
  color: #000;
  /* text-transform: uppercase; */
  margin: 0;
  float: right;
  border-radius: 50px;
  margin-right: 20px;
`;
const MobileDrawerBody = styled.div`
  width: 100%;
  border-radius: 3px 3px 0px 0px;
`;
const CloseButton = styled.div`
  display: ${({ hiddenClose }) => (hiddenClose ? "none" : "block")};
  position: absolute;
  top: 10px;
  right: 10px;
  cursor: pointer;
  z-index: 1111;
  &:hover {
    text-decoration: none;
    color: #363636;
  }
`;
export default PrevInsurerPopup2;
