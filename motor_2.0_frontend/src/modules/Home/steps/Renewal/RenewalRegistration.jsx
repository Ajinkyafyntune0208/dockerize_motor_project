/* eslint-disable no-mixed-operators */
/* eslint-disable react-hooks/exhaustive-deps */
import React, { useState, useEffect } from "react";
import _ from "lodash";
import { Spinner } from "react-bootstrap";
import moment from "moment";
import { toDate, Encrypt, reloadPage, RedirectFn, _haptics } from "utils";
import {
  SaveQuoteData,
  set_temp_data,
  Category,
  clear,
  getFastLaneRenewalDatas,
  setFastLaneRenewal,
  tabClick as TabClick,
  getFrontendUrl,
  overrideMsg as clearMsg,
} from "modules/Home/home.slice";
import {
  CancelAll,
  clear as clr,
  setQuotesList,
  error,
} from "modules/quotesPage/quote.slice";
import {
  SaveLead,
  SaveQuoteData as SaveQuoteDataQuotes,
  error as quotesError,
} from "../../../quotesPage/filterConatiner/quoteFilter.slice";
import { TextInput, Button, BackButton } from "components";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { differenceInMonths, differenceInDays } from "date-fns";
import swal from "sweetalert";
import { useForm } from "react-hook-form";
import * as yup from "yup";
import { yupResolver } from "@hookform/resolvers/yup";
import { useHistory } from "react-router";
import { useDispatch, useSelector } from "react-redux";
import { TypeReturn } from "modules/type";
import JourneyMismatch from "modules/Home/steps/Registration/journey-mismatch";
//prettier-ignore
import { vahaanConstants, onPaste, SingleKey, 
         onChangeSingle, _refocusOnReplace 
        } from "../Registration/helper";
//prettier-ignore
import { StyledBack, Container, Header, Logo, HeaderContent,
         HeadText, HeaderBody, ColorText, HrLine, Body,
         InputContainer, MoreContent, ORLine, GlobalStyle
        } from './style';

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const RenewalRegistration = ({
  stepper1,
  type,
  enquiry_id,
  journey_type,
  typeId,
  token,
  errorProp,
  lessthan767,
  isPartner,
  policy_no_url,
  reg_no_url,
  _stToken,
}) => {
  // validation schema
  const yupValidate = yup.object({
    regNo1: yup
      .string()
      .matches(/^[A-Z]{2}[-][0-9\s]{1,2}$/, "Invalid RTO Number")
      .required("Registration No. is required"),
    regNo2: yup
      .string()
      .matches(/^[A-Za-z\s]{1,3}$/, "Invalid Number")
      .nullable()
      .transform((v, o) => (o === "" ? null : v)),
    regNo3: yup
      .string()
      .required("Number is required")
      .matches(/^[0-9]{4}$/, "Invalid Number"),
  });

  /*---------------- back button---------------------*/
  const back = () => {
    history.push(
      `/${type}/registration?enquiry_id=${temp_data?.enquiry_id || enquiry_id}${
        token ? `&xutm=${token}` : ``
      }${typeId ? `&typeid=${typeId}` : ``}${
        journey_type ? `&journey_type=${journey_type}` : ``
      }${_stToken ? `&stToken=${_stToken}` : ``}`
    );
  };
  /*----------x----- back button-------x-------------*/

  const { register, errors, setValue, watch } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "all",
    reValidateMode: "onBlur",
  });

  const [btnDisable, setbtnDisable] = useState(false);
  const [buffer, setBuffer] = useState(false);

  //modal state
  const [show, setShow] = useState(false);

  const {
    temp_data,
    saveQuoteData,
    category,
    fastLaneRenewalData,
    tabClick,
    frontendurl,
    theme_conf,
    overrideMsg,
    vahaanConfig,
  } = useSelector((state) => state.home);
  const history = useHistory();
  const dispatch = useDispatch();

  //Renewbuy
  const forceSSO =
    _.isEmpty(temp_data?.agentDetails?.filter((o) => o?.sellerType === "U")) &&
    import.meta.env.VITE_BASE_URL !==
      "https://apipreprodmotor.rbstaging.in/api" &&
    window.location.hostname !== "localhost";

  //Initial boot
  useEffect(() => {
    enquiry_id &&
      _.isEmpty(frontendurl) &&
      dispatch(getFrontendUrl({ enquiry_id }));
  }, [enquiry_id]);

  const onSubmit = (journeyType, policy) => {
    dispatch(CancelAll(false));
    tabClick && dispatch(TabClick(false));
    if (
      vahaanConstants(vahaanConfig, type) &&
      (Number(journeyType) === 1 || !policy) &&
      fastLaneRenewalData?.status !== 101 &&
      fastLaneRenewalData?.status !== 108
    ) {
      onSubmitFastLaneRenewal();
    } else {
      if (
        (Number(journeyType) === 1 && isRegComplete) ||
        Number(journeyType) === 2 ||
        Number(journeyType) === 3
      ) {
        if (Number(journeyType) !== 1) {
          dispatch(
            set_temp_data({
              journeyWithoutRegno: "Y",
              journeyType,
              regNo: null,
              regNo1: null,
              regNo2: null,
              regNo3: null,
              regDate: null,
              fastlaneRenewalJourney: false,
              isRenewalRedirection: "Y",
              prefillPolicyNumber: policyNumber,
              ...(TypeReturn(type) !== "cv" &&
                TypeReturn(type) &&
                !_.isEmpty(category) && {
                  productSubTypeId: category?.product_sub_type_id,
                  productSubTypeCode: category?.product_sub_type_code,
                  productSubTypeName: category?.product_sub_type_code,
                }),
              //clearing vehicle type
              // productSubTypeId: null,
              // productSubTypeCode: null,
              // productCategoryName: null,
              // carrierType: null,
            })
          );
          dispatch(
            SaveQuoteData({
              ...(localStorage?.SSO_user && {
                tokenResp: JSON.parse(localStorage?.SSO_user),
              }),
              ...((policy_no_url || reg_no_url) && {
                renewalRegistration: "Y",
              }),
              stage: "2",
              ...(isPartner === "Y" && { frontendTags: "" }),
              vehicleRegistrationNo: Number(journeyType) === 3 ? "NEW" : "NULL",
              journeyWithoutRegno: "Y",
              userProductJourneyId: enquiry_id,
              enquiryId: enquiry_id,
              vehicleRegisterDate: "NULL",
              policyExpiryDate: "NULL",
              previousInsurerCode: "NULL",
              previousInsurer: "NULL",
              previousPolicyType: "NULL",
              businessType: "NULL",
              policyType: "NULL",
              previousNcb: "NULL",
              applicableNcb: "NULL",
              fastlaneRenewalJourney: false,
              isRenewalRedirection: "Y",
              prefillPolicyNumber: policyNumber,
              ...(journey_type && {
                journeyType: journey_type,
              }),
              ...(TypeReturn(type) !== "cv" &&
                TypeReturn(type) &&
                !_.isEmpty(category) && {
                  productSubTypeId: category?.product_sub_type_id,
                  productSubTypeCode: category?.product_sub_type_code,
                  productSubTypeName: category?.product_sub_type_code,
                }),
            })
          );
        } else {
          if (
            regIp?.[0] * 1 ||
            (regNo1 &&
              regNo2 &&
              regNo3 &&
              `${regNo1}-${regNo2}-${regNo3}`.match(
                /^[A-Z]{2}[-][0-9]{1,2}[-\s][A-Z0-9]{1,3}[-\s][0-9]{4}$/
              )) ||
            (regNo1 &&
              !regNo2 &&
              regNo3 &&
              `${regNo1}-${regNo3}`.match(
                /^[A-Z]{2}[-][0-9]{1,2}[-\s][0-9]{4}$/
              ))
          ) {
            dispatch(
              set_temp_data({
                isRenewalRedirection: "Y",
                journeyWithoutRegno: "N",
                prefillPolicyNumber: policyNumber,
                journeyType,
                regNo1,
                regNo2,
                regNo3,
                regNo:
                  regIp?.[0] * 1
                    ? regIp
                    : regNo2
                    ? `${
                        Number(regNo1.split("-")[1]) < 10
                          ? `${regNo1.split("-")[0]}-0${Number(
                              regNo1.split("-")[1]
                            )}`
                          : regNo1
                      }-${regNo2}-${regNo3}`
                    : `${
                        Number(regNo1.split("-")[1]) < 10
                          ? `${regNo1.split("-")[0]}-0${Number(
                              regNo1.split("-")[1]
                            )}`
                          : regNo1
                      }--${regNo3}`,
                vehicleRegisterDate: null,
                fastlaneRenewalJourney: false,
                frontendTags: "",
                corporateVehiclesQuoteRequest: {
                  ...temp_data?.corporateVehiclesQuoteRequest,
                  frontendTags: "",
                },
                ...(TypeReturn(type) !== "cv" &&
                  TypeReturn(type) &&
                  !_.isEmpty(category) && {
                    productSubTypeId: category?.product_sub_type_id,
                    productSubTypeCode: category?.product_sub_type_code,
                    productSubTypeName: category?.product_sub_type_code,
                  }),
                //clearing vehicle TypeReturn(type)
                // productSubTypeId: null,
                // productSubTypeCode: null,
                // productCategoryName: null,
                // carrierType: null,
              })
            );
            dispatch(
              SaveQuoteData({
                ...(localStorage?.SSO_user && {
                  tokenResp: JSON.parse(localStorage?.SSO_user),
                }),
                ...((policy_no_url || reg_no_url) && {
                  renewalRegistration: "Y",
                }),
                stage: "2",
                journeyWithoutRegno: "N",
                vehicleRegistrationNo:
                  regIp?.[0] * 1
                    ? regIp
                    : regNo2
                    ? `${
                        Number(regNo1.split("-")[1]) < 10
                          ? `${regNo1.split("-")[0]}-0${Number(
                              regNo1.split("-")[1]
                            )}`
                          : regNo1
                      }-${regNo2}-${regNo3}`
                    : `${
                        Number(regNo1.split("-")[1]) < 10
                          ? `${regNo1.split("-")[0]}-0${Number(
                              regNo1.split("-")[1]
                            )}`
                          : regNo1
                      }--${regNo3}`,
                rtoNumber:
                  !regIp?.[0] * 1 && Number(regNo1.split("-")[1]) < 10
                    ? `${regNo1.split("-")[0]}-0${Number(regNo1.split("-")[1])}`
                    : regNo1,
                rto:
                  !regIp?.[0] * 1 && Number(regNo1.split("-")[1]) < 10
                    ? `${regNo1.split("-")[0]}-0${Number(regNo1.split("-")[1])}`
                    : regNo1,
                userProductJourneyId: enquiry_id,
                vehicleRegisterAt:
                  !regIp?.[0] * 1 && Number(regNo1.split("-")[1]) < 10
                    ? `${regNo1.split("-")[0]}-0${Number(regNo1.split("-")[1])}`
                    : regNo1,
                ...(isPartner === "Y" && { frontendTags: "" }),
                ...(journey_type && {
                  journeyType: journey_type,
                }),
                enquiryId: enquiry_id,
                vehicleRegisterDate: "NULL",
                policyExpiryDate: "NULL",
                previousInsurerCode: "NULL",
                previousInsurer: "NULL",
                previousPolicyType: "NULL",
                businessType: "NULL",
                policyType: "NULL",
                previousNcb: "NULL",
                applicableNcb: "NULL",
                isRenewalRedirection: "Y",
                prefillPolicyNumber: policyNumber,
                fastlaneRenewalJourney: false,
                ...(TypeReturn(type) !== "cv" &&
                  TypeReturn(type) &&
                  !_.isEmpty(category) && {
                    productSubTypeId: category?.product_sub_type_id,
                    productSubTypeCode: category?.product_sub_type_code,
                    productSubTypeName: category?.product_sub_type_code,
                  }),
              })
            );
            setTimeout(setbtnDisable(false), 2000);
          } else {
            swal("Warning", "Invalid Registration Number", "warning").then(() =>
              setTimeout(setbtnDisable(false), 1000)
            );
          }
        }
      } else {
        swal("Error", "Please fill all the details", "error").then(() =>
          setTimeout(setbtnDisable(false), 1000)
        );
      }
    }
  };

  //fastlane logic to be discarded
  const onSubmitFastLaneRenewal = (policy) => {
    setbtnDisable(true);
    if (vahaanConstants(vahaanConfig, type)) {
      setBuffer(true);
      const registration_no =
        regIp?.[0] * 1
          ? regIp
          : regNo2
          ? `${regNo1}-${regNo2}-${regNo3}`
          : regNo3
          ? `${regNo1}--${regNo3}`
          : "";
      const data = {
        ...(localStorage?.SSO_user && {
          tokenResp: JSON.parse(localStorage?.SSO_user),
        }),
        enquiryId: temp_data?.enquiry_id || enquiry_id,
        ...(policy && registration_no
          ? {
              registration_no: registration_no,
              unformatted_reg_no: registration_no.replace(/-/gi, ""),
              policyNumber: policyNumber,
            }
          : !policy
          ? {
              registration_no: registration_no,
              unformatted_reg_no: registration_no.replace(/-/gi, ""),
            }
          : !registration_no && policy
          ? {
              policyNumber: policyNumber,
              registration_no: "NULL",
            }
          : {
              policyNumber: policyNumber,
              registration_no: registration_no,
            }),
        ...(TypeReturn(type) !== "cv" && {
          productSubType: TypeReturn(type) === "car" ? 1 : 2,
        }),
        ...(journey_type && {
          journeyType: journey_type,
        }),
        section: TypeReturn(type),
        ...(reg_no_url &&
          registration_no.replace(/-/g, "").replace(/0/g, "") ===
            reg_no_url.replace(/-/g, "").replace(/0/g, "") && {
            vendor_rc: reg_no_url,
          }),
      };
      dispatch(getFastLaneRenewalDatas(data));
    } else {
      onSubmit(policy ? 2 : 1, "policy");
    }
  };

  // useEffect(() => {
  //   dispatch(CancelAll(true));
  //   dispatch(clr());
  //   dispatch(clear());
  //   dispatch(quotesError(null));
  //   dispatch(error(null));
  // }, []);

  useEffect(() => {
    //Cancel token
    dispatch(CancelAll(true));
    //quotes clr
    dispatch(setQuotesList([]));
    //quotes slice state clear
    dispatch(clr());
    dispatch(clear());
    // dispatch(quotesError(null));
  }, []);

  // useEffect(() => {
  //   //Cancel token
  //   dispatch(CancelAll(true));
  //   //quotes clr
  //   dispatch(setQuotesList([]));
  //   //quotes slice state clear
  //   dispatch(clr());
  // }, []);

  /*---x---Handling changes for Inputs---x---*/
  //watch reg no input
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
  let policyNumber = watch("policyNo");

  //finding number of blocks
  const segmentIndexes = regIp && regIp.split("-");
  //finding the length of the rto block
  const segmentInfo =
    regIp &&
    segmentIndexes &&
    !_.isEmpty(segmentIndexes) &&
    segmentIndexes?.length >= 1
      ? segmentIndexes[1]?.length
      : "";

  //finding the length of middle segment
  let midBlockCheck =
    regIp &&
    regIp.length > (Number(segmentInfo) === 1 ? 4 : 5) &&
    regIp.split("")[Number(segmentInfo) === 1 ? 5 : 6] * 1;

  const MidsegmentInfo =
    regIp &&
    !midBlockCheck &&
    segmentIndexes &&
    !_.isEmpty(segmentIndexes) &&
    segmentIndexes?.length >= 2
      ? segmentIndexes[2]?.length
      : "";

  //setting maxlength of regFeild & indicating if the middle block is empty.
  let maxlen =
    regIp &&
    segmentInfo &&
    regIp.length > (Number(segmentInfo) === 1 ? 4 : 5) &&
    regIp.split("")[Number(segmentInfo) === 1 ? 5 : 6] * 1
      ? Number(segmentInfo) === 1
        ? "9"
        : "10"
      : Number(segmentInfo) === 1
      ? Number(MidsegmentInfo) === 1
        ? "11"
        : Number(MidsegmentInfo) === 2
        ? "12"
        : "13"
      : Number(MidsegmentInfo) === 1
      ? "12"
      : Number(MidsegmentInfo) === 2
      ? "13"
      : "14";

  //redirected Prefill (Renewbuy)
  useEffect(() => {
    if (reg_no_url && reg_no_url !== "NULL") {
      setValue("regNo", reg_no_url);
    }
    if (policy_no_url && policy_no_url !== "NULL") {
      setValue("policyNo", policy_no_url);
    }
  }, [reg_no_url, policy_no_url]);

  //prefill
  const regIpCheck = watch("regNo") || "";
  useEffect(() => {
    if (
      temp_data?.regNo &&
      (!regIpCheck || regIpCheck === temp_data?.regNo) &&
      !reg_no_url
    ) {
      temp_data?.regNo !== "NEW" &&
        setValue(
          "regNo",
          temp_data?.regNo1?.[0] * 1
            ? temp_data?.regNo
            : temp_data?.regNo1
            ? `${temp_data?.regNo1.split("-")[0]}-${
                temp_data?.regNo1.split("-")[1]
              }-${temp_data?.regNo2}-${temp_data?.regNo3}`.replace(/--/g, "-")
            : ""
        );
    }
    if (temp_data?.prefillPolicyNumber) {
      setValue("policyNo", temp_data?.prefillPolicyNumber);
    }
  }, [temp_data]);

  //onSuccess
  useEffect(() => {
    if (saveQuoteData && TypeReturn(type)) {
      setBuffer(false);
      //fast lane redirection
      if (fastLaneRenewalData?.status === 100) {
        fastLaneRenewalData?.RenwalData === "Y" &&
          ["Third Party", "Third-party"].includes(
            fastLaneRenewalData?.additional_details?.previousPolicyType
          ) &&
          dispatch(TabClick(true));

        dispatch(
          set_temp_data({
            isRenewalRedirection: "Y",
            prefillPolicyNumber: policyNumber,
            //storing data in redux for fastlane / ongrid
            ...(fastLaneRenewalData?.additional_details && {
              policyType:
                fastLaneRenewalData?.additional_details.previousPolicyType,
              productSubTypeId:
                fastLaneRenewalData?.additional_details?.productSubTypeId,
              newCar:
                fastLaneRenewalData?.additional_details?.businessType ===
                "newbusiness",
              breakIn:
                fastLaneRenewalData?.additional_details?.businessType ===
                "breakin",
              prevIc:
                fastLaneRenewalData?.additional_details?.previousInsurerCode,
              odOnly:
                fastLaneRenewalData?.additional_details?.policyType ===
                "own_damage",
            }),
          })
        );

        dispatch(CancelAll(false));
        setTimeout(() => {
          if (
            fastLaneRenewalData?.redirection_data?.is_redirection &&
            fastLaneRenewalData?.redirection_data?.redirection_url
          ) {
            reloadPage(fastLaneRenewalData?.redirection_data?.redirection_url);
          } else {
            //partial mmv logic
            if (!_.isEmpty(fastLaneRenewalData?.results)) {
              let { manfactureId, model, version, vehicleRegisterDate } =
                fastLaneRenewalData?.additional_details;
              //all data fetched
              if (manfactureId && model && version && vehicleRegisterDate) {
                history.push(
                  `/${type}/quotes?enquiry_id=${
                    temp_data?.enquiry_id || enquiry_id
                  }${token ? `&xutm=${token}` : ``}${
                    typeId ? `&typeid=${typeId}` : ``
                  }${journey_type ? `&journey_type=${journey_type}` : ``}${
                    _stToken ? `&stToken=${_stToken}` : ``
                  }`
                );
              }
              // reg date missing
              else if (manfactureId && model && version) {
                history.push(
                  `/${type}/vehicle-details?enquiry_id=${
                    temp_data?.enquiry_id || enquiry_id
                  }${token ? `&xutm=${token}` : ``}${
                    typeId ? `&typeid=${typeId}` : ``
                  }&stepperfill=${Encrypt("date")}${
                    journey_type ? `&journey_type=${journey_type}` : ``
                  }${_stToken ? `&stToken=${_stToken}` : ``}`
                );
              }
              //version missing
              else if (manfactureId && model) {
                history.push(
                  `/${type}/vehicle-details?enquiry_id=${
                    temp_data?.enquiry_id || enquiry_id
                  }${token ? `&xutm=${token}` : ``}${
                    typeId ? `&typeid=${typeId}` : ``
                  }&stepperfill=4${
                    journey_type ? `&journey_type=${journey_type}` : ``
                  }${_stToken ? `&stToken=${_stToken}` : ``}`
                );
              }
              //all data fetched
              else if (manfactureId) {
                history.push(
                  `/${type}/vehicle-details?enquiry_id=${
                    temp_data?.enquiry_id || enquiry_id
                  }${token ? `&xutm=${token}` : ``}${
                    typeId ? `&typeid=${typeId}` : ``
                  }&stepperfill=2${
                    journey_type ? `&journey_type=${journey_type}` : ``
                  }${_stToken ? `&stToken=${_stToken}` : ``}`
                );
              } else {
                history.push(
                  `/${type}/vehicle-details?enquiry_id=${
                    temp_data?.enquiry_id || enquiry_id
                  }${token ? `&xutm=${token}` : ``}${
                    typeId ? `&typeid=${typeId}` : ``
                  }&stepperfill=1${
                    journey_type ? `&journey_type=${journey_type}` : ``
                  }${_stToken ? `&stToken=${_stToken}` : ``}`
                );
              }
            }
          }
        }, 1000);
      }
      //Non fast lane redirection
      else {
        setBuffer(false);
        if (TypeReturn(type) === "cv" && TypeReturn(type)) {
          !typeId &&
            (!_.isEmpty(fastLaneRenewalData)
              ? swal({
                  title: "Please Note",
                  text:
                    overrideMsg ||
                    theme_conf?.broker_config?.fastlane_error_message ||
                    import.meta.env.VITE_BROKER === "BAJAJ"
                      ? window.location.href.includes("general-insurance")
                        ? "Sorry, we are not active with the selected plan digitally. Please reach out to your RM or visit our branch."
                        : "Sorry, we are not active with the selected plan for online renewal at the moment. Please proceed offline."
                      : "We are unable to fetch your vehicle details at this moment. Please input your vehicle Make, Model, RTO details and proceed",
                  icon: "info",
                  buttons: {
                    catch: {
                      text:
                        import.meta.env.VITE_BROKER === "BAJAJ"
                          ? "Go to homepage"
                          : "Re-enter",
                      value: "confirm",
                    },
                    ...(!theme_conf?.broker_config?.journey_block &&
                      !overrideMsg &&
                      import.meta.env.VITE_BROKER !== "BAJAJ" && {
                        No: {
                          text: `Proceed`,
                          value: "No",
                        },
                      }),
                  },
                  dangerMode: true,
                  closeOnClickOutside: false,
                }).then((caseValue) => {
                  switch (caseValue) {
                    case "confirm":
                      _haptics([100, 0, 50]);
                      import.meta.env.VITE_BROKER === "BAJAJ"
                        ? reloadPage(
                            theme_conf?.broker_config?.broker_asset
                              ?.other_failure_url?.url || RedirectFn(token)
                          )
                        : setValue("regNo", "");
                      break;
                    case "No":
                      history.push(
                        `/${type}/vehicle-type?enquiry_id=${
                          temp_data?.enquiry_id || enquiry_id
                        }${token ? `&xutm=${token}` : ``}${
                          typeId ? `&typeid=${typeId}` : ``
                        }${
                          journey_type ? `&journey_type=${journey_type}` : ``
                        }${_stToken ? `&stToken=${_stToken}` : ``}`
                      );
                      break;
                    default:
                  }
                })
              : history.push(
                  `/${type}/vehicle-type?enquiry_id=${
                    temp_data?.enquiry_id || enquiry_id
                  }${token ? `&xutm=${token}` : ``}${
                    typeId ? `&typeid=${typeId}` : ``
                  }${journey_type ? `&journey_type=${journey_type}` : ``}${
                    _stToken ? `&stToken=${_stToken}` : ``
                  }`
                ));
        } else {
          if (temp_data?.productSubTypeCode && TypeReturn(type)) {
            !_.isEmpty(fastLaneRenewalData)
              ? swal({
                  title: "Please Note",
                  text:
                    overrideMsg ||
                    theme_conf?.broker_config?.fastlane_error_message ||
                    import.meta.env.VITE_BROKER === "BAJAJ"
                      ? window.location.href.includes("general-insurance")
                        ? "Sorry, we are not active with the selected plan digitally. Please reach out to your RM or visit our branch."
                        : "Sorry, we are not active with the selected plan for online renewal at the moment. Please proceed offline."
                      : "We are unable to fetch your vehicle details at this moment. Please input your vehicle Make, Model, RTO details and proceed",
                  icon: "info",
                  buttons: {
                    catch: {
                      text:
                        import.meta.env.VITE_BROKER === "BAJAJ"
                          ? "Go to homepage"
                          : "Re-enter",
                      value: "confirm",
                    },
                    ...(!theme_conf?.broker_config?.journey_block &&
                      !overrideMsg &&
                      import.meta.env.VITE_BROKER !== "BAJAJ" && {
                        No: {
                          text: `Proceed`,
                          value: "No",
                        },
                      }),
                  },
                  dangerMode: true,
                  closeOnClickOutside: false,
                }).then((caseValue) => {
                  switch (caseValue) {
                    case "confirm":
                      _haptics([100, 0, 50]);
                      import.meta.env.VITE_BROKER === "BAJAJ"
                        ? reloadPage(
                            theme_conf?.broker_config?.broker_asset
                              ?.other_failure_url?.url || RedirectFn(token)
                          )
                        : setValue("regNo", "");
                      break;
                    case "No":
                      history.push(
                        `/${type}/vehicle-details?enquiry_id=${
                          temp_data?.enquiry_id || enquiry_id
                        }${token ? `&xutm=${token}` : ``}${
                          typeId ? `&typeid=${typeId}` : ``
                        }${
                          journey_type ? `&journey_type=${journey_type}` : ``
                        }${_stToken ? `&stToken=${_stToken}` : ``}`
                      );
                      break;
                    default:
                  }
                })
              : history.push(
                  `/${type}/vehicle-details?enquiry_id=${
                    temp_data?.enquiry_id || enquiry_id
                  }${token ? `&xutm=${token}` : ``}${
                    typeId ? `&typeid=${typeId}` : ``
                  }${journey_type ? `&journey_type=${journey_type}` : ``}${
                    _stToken ? `&stToken=${_stToken}` : ``
                  }`
                );
          }
        }
      }
    }

    return () => {
      dispatch(clear("saveQuoteData"));
      saveQuoteData && dispatch(setFastLaneRenewal(null));
      setTimeout(() => dispatch(clearMsg("")), 500);
    };
  }, [saveQuoteData, temp_data]);

  //policy & business type calc
  //SAOD
  const saod = (regDate) => {
    let b = "01-09-2018";
    let c = regDate;
    let d = moment().format("DD-MM-YYYY");
    let diffDaysOd = c && b && differenceInDays(toDate(c), toDate(b));
    let diffMonthsOdCar = c && d && differenceInMonths(toDate(d), toDate(c));
    let diffDayOd = c && d && differenceInDays(toDate(d), toDate(c));

    return (
      (diffDaysOd >= 0 && diffDayOd > 270 && TypeReturn(type) === "bike") ||
      (diffDayOd > 270 && diffMonthsOdCar < 34 && TypeReturn(type) === "car")
    );
  };

  useEffect(() => {
    if (
      fastLaneRenewalData?.status === 100 &&
      vahaanConstants(vahaanConfig, type)
    ) {
      let vehicleData =
        !_.isEmpty(fastLaneRenewalData?.results) &&
        fastLaneRenewalData?.results[0]?.vehicle;
      set_temp_data({
        newCar:
          fastLaneRenewalData?.additional_details?.businessType ===
          "newbusiness",
        breakIn:
          fastLaneRenewalData?.additional_details?.businessType === "breakin",
      });
      const quoteData = {
        ...(localStorage?.SSO_user && {
          tokenResp: JSON.parse(localStorage?.SSO_user),
        }),
        enquiryId: temp_data?.enquiry_id || enquiry_id,
        vehicleRegistrationNo: regNo2
          ? `${
              Number(regNo1.split("-")[1]) < 10
                ? `${regNo1.split("-")[0]}-0${Number(regNo1.split("-")[1])}`
                : regNo1
            }-${regNo2}-${regNo3}`
          : `${
              Number(regNo1.split("-")[1]) < 10
                ? `${regNo1.split("-")[0]}-0${Number(regNo1.split("-")[1])}`
                : regNo1
            }--${regNo3}`,
        userProductJourneyId: temp_data?.enquiry_id || enquiry_id,
        corpId: temp_data?.corpId,
        userId: temp_data?.userId,
        ...(TypeReturn(type) !== "cv" && {
          productSubTypeId: TypeReturn(type) === "car" ? 1 : 2,
        }), // from api
        fullName: temp_data?.firstName + " " + temp_data?.lastName,
        firstName: temp_data?.firstName,
        lastName: temp_data?.lastName,
        emailId: temp_data?.emailId,
        mobileNo: temp_data?.mobileNo,
        ...(journey_type && {
          journeyType: journey_type,
        }),
        ...(vehicleData && {
          policyType: saod(
            fastLaneRenewalData?.results[0]?.vehicle?.regn_dt
              .split("/")
              .join("-")

          )
            ? "own_damage"
            : "comprehensive",
        }),
        ...(vehicleData && {
          businessType:
            Number(fastLaneRenewalData?.results[0]?.vehicle?.manu_yr) ===
            Number(new Date().getFullYear())
              ? "newbusiness"
              : "rollover",
        }),
        rto:
          Number(regNo1.split("-")[1]) < 10
            ? `${regNo1.split("-")[0]}-0${Number(regNo1.split("-")[1])}`
            : regNo1,
        ...(vehicleData && {
          manufactureYear:
            `${moment().format("DD-MM-YYYY").split("-")[1]}` -
            `${fastLaneRenewalData?.results[0]?.vehicle?.manu_yr}`,
        }),
        ...(vehicleData && {
          version: fastLaneRenewalData?.results[0]?.vehicle?.vehicle_cd,
        }), // from api
        ...(vehicleData && {
          versionName: fastLaneRenewalData?.results[0]?.vehicle?.fla_variant,
        }), // from api
        vehicleRegisterAt:
          Number(regNo1.split("-")[1]) < 10
            ? `${regNo1.split("-")[0]}-0${Number(regNo1.split("-")[1])}`
            : regNo1,
        vehicleRegisterDate:
          vehicleData?.regn_dt?.split("/").join("-") || "01-10-2016",
        ...(vehicleData && {
          vehicleRegisterDate: fastLaneRenewalData?.results[0]?.vehicle?.regn_dt
            .split("/")
            .join("-"),
        }),
        vehicleInvoiceDate:
          fastLaneRenewalData?.additional_details?.vehicleInvoiceDate ||
          fastLaneRenewalData?.additional_details?.vehicleRegisterDate,
        vehicleOwnerType: "I", // from api
        ...(vehicleData && {
          policyExpiryDate: fastLaneRenewalData?.results[0]?.insurance
            ?.insurance_upto
            ? fastLaneRenewalData?.results[0]?.insurance?.insurance_upto
                .split("/")
                .join("-")
            : moment().format("DD-MM-YYYY"),
        }),
        hasExpired: "no", //from api
        isNcb: "Yes", //from api
        isClaim: "N", //from api
        ...(vehicleData && {
          fuelType:
            fastLaneRenewalData?.results[0]?.vehicle?.fla_fuel_type_desc ||
            "PETROL",
        }),
        vehicleUsage: 2, //from api
        vehicleLpgCngKitValue: "", //from api,
        previousInsurer: temp_data?.prevIcFullName, //from api
        previousInsurerCode: temp_data?.prevIc, //from api
        previousPolicyType: "Comprehensive", //from api
        ...(vehicleData && {
          modelName: fastLaneRenewalData?.results[0]?.vehicle?.fla_model_desc,
          manfactureName:
            fastLaneRenewalData?.results[0]?.vehicle?.fla_maker_desc,
        }),
        ownershipChanged: "N", //from api
        ...(vehicleData && {
          engineNo:
            !_.isEmpty(fastLaneRenewalData?.results) &&
            fastLaneRenewalData?.results[0]?.vehicle?.eng_no,
          chassisNo:
            !_.isEmpty(fastLaneRenewalData?.results) &&
            fastLaneRenewalData?.results[0]?.vehicle?.chasi_no,
          vehicleColor:
            !_.isEmpty(fastLaneRenewalData?.results) &&
            fastLaneRenewalData?.results[0]?.vehicle?.color,
        }),
        leadJourneyEnd: true,
        stage: 11,
        preventKafkaPush: true,
      };
      // quotesFilter state
      (fastLaneRenewalData?.ft_product_code === TypeReturn(type) ||
        !fastLaneRenewalData?.ft_product_code) &&
        dispatch(
          SaveQuoteDataQuotes(
            {
              ...(isPartner === "Y" && { frontendTags: "" }),
              isRenewalRedirection: "Y",
              prefillPolicyNumber: policyNumber,
              ...((policy_no_url || reg_no_url) && {
                renewalRegistration: "Y",
              }),
              ..._.pick(
                quoteData,
                _.without(_.keys(quoteData), "policyExpiryDate")
              ),
              ...(fastLaneRenewalData?.additional_details?.policyExpiryDate
                ? differenceInDays(
                    toDate(
                      fastLaneRenewalData?.additional_details?.policyExpiryDate
                    ),
                    toDate(moment().format("DD-MM-YYYY"))
                  ) < 45
                  ? { ...fastLaneRenewalData?.additional_details }
                  : _.pick(
                      fastLaneRenewalData?.additional_details,
                      _.without(
                        _.keys(fastLaneRenewalData?.additional_details),
                        "policyExpiryDate"
                      )
                    )
                : { ...fastLaneRenewalData?.additional_details }),
            },
            fastLaneRenewalData?.RenwalData === "Y"
              ? false
              : ["HEROCARE", "KAROINSURE"].includes(import.meta.env.VITE_BROKER)
          )
        );
      //home state
      (fastLaneRenewalData?.ft_product_code === TypeReturn(type) ||
        !fastLaneRenewalData?.ft_product_code) &&
        dispatch(
          SaveQuoteData({
            ...(isPartner === "Y" && { frontendTags: "" }),
            isRenewalRedirection: "Y",
            prefillPolicyNumber: policyNumber,
            ..._.pick(
              quoteData,
              _.without(_.keys(quoteData), "policyExpiryDate")
            ),
            ...(fastLaneRenewalData?.additional_details?.policyExpiryDate
              ? differenceInDays(
                  toDate(
                    fastLaneRenewalData?.additional_details?.policyExpiryDate
                  ),
                  toDate(moment().format("DD-MM-YYYY"))
                ) < 45
                ? { ...fastLaneRenewalData?.additional_details }
                : _.pick(
                    fastLaneRenewalData?.additional_details,
                    _.without(
                      _.keys(fastLaneRenewalData?.additional_details),
                      "policyExpiryDate"
                    )
                  )
              : {
                  ..._.pick(
                    fastLaneRenewalData?.additional_details,
                    _.without(
                      _.keys(fastLaneRenewalData?.additional_details),
                      "policyExpiryDate"
                    )
                  ),
                }),
          })
        );
      dispatch(
        SaveLead({
          enquiryId: temp_data?.enquiry_id || enquiry_id,
          leadStageId: 2,
        })
      );
      dispatch(
        set_temp_data({
          fastlaneRenewalJourney: true,
          fastlaneNcbPopup: true,
          frontendTags: "",
          corporateVehiclesQuoteRequest: {
            ...temp_data?.corporateVehiclesQuoteRequest,
            frontendTags: "",
          },
          vehicleInvoiceDate:
            fastLaneRenewalData?.additional_details?.vehicleInvoiceDate ||
            fastLaneRenewalData?.additional_details?.vehicleRegisterDate,
        })
      );

      // dispatch(SaveQuoteDataQuotesKey(null));
    } else {
      if (
        (fastLaneRenewalData?.status * 1 === 101 ||
          fastLaneRenewalData?.status * 1 === 108) &&
        !fastLaneRenewalData?.showMessage
      ) {
        onSubmit(regIp ? 1 : 2, "policy");
      } else {
        fastLaneRenewalData?.showMessage &&
          swal("Info", fastLaneRenewalData?.showMessage, "info").then(() => [
            dispatch(setFastLaneRenewal(false)),
            setBuffer(false),
            setbtnDisable(false),
            setValue("regNo", ""),
            setValue("policyNo", ""),
          ]);
      }
    }
  }, [fastLaneRenewalData]);

  //journey mismatch
  useEffect(() => {
    if (
      fastLaneRenewalData?.ft_product_code !== TypeReturn(type) &&
      // TypeReturn(type) !== "cv" &&
      vahaanConstants(vahaanConfig, type) &&
      fastLaneRenewalData?.status !== 101
    ) {
      setBuffer(false);
      setbtnDisable(false);
      setShow(fastLaneRenewalData?.ft_product_code);
    }
  }, [fastLaneRenewalData]);

  //onError
  useEffect(() => {
    if (errorProp) {
      setbtnDisable(false);
      setBuffer(false);
    }
  }, [errorProp]);

  /*-----journey change-----*/
  const journeyChange = () => {
    if (frontendurl) {
      if (frontendurl?.car_frontend_url && TypeReturn(show) === "car") {
        reloadPage(
          `${frontendurl?.car_frontend_url}/renewal?enquiry_id=${enquiry_id}${
            token ? `&xutm=${token}` : ""
          }${_stToken ? `&stToken=${_stToken}` : ``}`
        );
      } else if (
        frontendurl?.bike_frontend_url &&
        TypeReturn(show) === "bike"
      ) {
        reloadPage(
          `${frontendurl?.bike_frontend_url}/renewal?enquiry_id=${enquiry_id}${
            token ? `&xutm=${token}` : ""
          }${_stToken ? `&stToken=${_stToken}` : ``}`
        );
      } else if (frontendurl?.cv_frontend_url && TypeReturn(show) === "cv") {
        reloadPage(
          `${frontendurl?.cv_frontend_url}/renewal?enquiry_id=${enquiry_id}${
            token ? `&xutm=${token}` : ""
          }${_stToken ? `&stToken=${_stToken}` : ``}`
        );
      }
    }
  };
  /*--x--journey change--x--*/

  //Product SubType (only when product category is not CV)
  useEffect(() => {
    if (TypeReturn(type) !== "cv" && TypeReturn(type)) {
      dispatch(Category({ productType: TypeReturn(type) }));
    }
  }, [type]);

  //Read Only
  const notEditable = reg_no_url || temp_data?.renewalRegistration === "Y";

  //Eval if reg no is complete
  const isRegComplete =
    (regNo1 && regNo3) || (regIp && regIp[0] * 1 && regIp.length > 10);

  //BH check
  let isBH = regIp && regIp[0] * 1;

  //Signle key params
  const inputParams = {
    setValue,
    stepper1,
    setBuffer,
    onSubmitFastLaneRenewal,
  };
  const regInputStateParams = { temp_data, MidsegmentInfo, segmentInfo, isBH };

  return (
    <>
      <StyledBack className={lessthan767 ? "ml-1 backBtn" : "backBtn"}>
        {!notEditable ? (
          <BackButton type="button" onClick={back}>
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
        ) : (
          <noscript />
        )}
      </StyledBack>
      <Container>
        <Header>
          <Logo>
            <img
              src={`${
                import.meta.env.VITE_BASENAME !== "NA"
                  ? `/${import.meta.env.VITE_BASENAME}`
                  : ""
              }/assets/images/stop-watch.png`}
              alt="Stop-watch"
              width="100%"
            />
          </Logo>
          <HeaderContent>
            <HeadText>Instant Renewal</HeadText>
            <HeaderBody>
              Renew the policy bought at{" "}
              <ColorText>
                {import.meta.env.VITE_BROKER === "RB" ||
                import.meta.env.VITE_BROKER === "BAJAJ"
                  ? import.meta.env.VITE_TITLE
                  : import.meta.env.VITE_BROKER}
              </ColorText>{" "}
              last year
            </HeaderBody>
          </HeaderContent>
        </Header>
        <HrLine />
        <Body>
          <p
            style={{
              fontWeight: "600",
              marginLeft: lessthan767 ? "19px" : "30px",
            }}
          >
            Renew With Registration Number
          </p>
          <InputContainer>
            <TextInput
              className="inputStyle"
              placeholder="Enter Registration No. (MH-04-AR-7070)"
              type="text"
              name="regNo"
              placeholderColor={"#FFFFF"}
              ref={register}
              maxLength={maxlen}
              disabled={buffer}
              readOnly={notEditable}
              onPaste={(e) => onPaste(e)}
              onKeyUp={(e) => (e) =>
                SingleKey(e, inputParams, regInputStateParams)}
              onKeyDown={(e) => (e) =>
                SingleKey(e, inputParams, regInputStateParams)}
              onChange={(e) =>
                onChangeSingle(e, segmentInfo, MidsegmentInfo, isBH)
              }
              onInput={(e) => {
                //keeping i/p blur when -- is replaced & validations are met then refocusing.
                _refocusOnReplace(e);
              }}
            />
            {
              <Button
                id={"proceedBtn"}
                className="proceedBtnStyle"
                buttonStyle="outline-solid"
                style={
                  isRegComplete || !btnDisable
                    ? { ...(lessthan767 && { width: "100%" }) }
                    : {
                        cursor: "not-allowed",
                        ...(lessthan767 && { width: "100%" }),
                      }
                }
                hex1={
                  isRegComplete
                    ? Theme?.Registration?.proceedBtn?.background || "#bdd400"
                    : "#e7e7e7"
                }
                hex2={
                  isRegComplete
                    ? Theme?.Registration?.proceedBtn?.background || "#bdd400"
                    : "#e7e7e7"
                }
                borderRadius={lessthan767 ? "30px" : "5px"}
                disabled={(isRegComplete ? false : true) || btnDisable}
                onClick={() => {
                  onSubmit(1);
                  setbtnDisable(true);
                }}
                height="60px"
                type="submit"
              >
                {!buffer && (
                  <text
                    style={{
                      color: isRegComplete
                        ? Theme?.Registration?.proceedBtn?.color
                          ? Theme?.Registration?.proceedBtn?.color
                          : "black"
                        : "black",
                    }}
                  >
                    {"Renew"}
                  </text>
                )}
                {buffer && !policyNumber && (
                  <>
                    {["", "mx-1", ""].map((i) => {
                      return (
                        <Spinner
                          variant="light"
                          as="span"
                          animation="grow"
                          size="sm"
                          className={i}
                        />
                      );
                    })}
                  </>
                )}
              </Button>
            }
          </InputContainer>
          <MoreContent>
            <ORLine
              style={
                notEditable
                  ? {
                      visibility: "hidden",
                      margin: "0px 100px 0px 100px",
                    }
                  : {}
              }
            >
              OR
            </ORLine>
            <p
              style={{
                fontWeight: "600",
                textAlign: "left",
                marginLeft: lessthan767 ? "19px" : "30px",
              }}
            >
              {notEditable
                ? "Your previous policy number is"
                : "Renew With Previous Policy Number"}
            </p>
          </MoreContent>
          <InputContainer>
            <TextInput
              className="inputStyle"
              placeholder="Enter previous policy number"
              type="text"
              name="policyNo"
              placeholderColor={"#FFFFF"}
              ref={register}
              disabled={buffer}
              readOnly={notEditable}
              onChange={(e) => (e.target.value = e.target.value.toUpperCase())}
            />
            {
              <Button
                id={"proceedBtn"}
                className="proceedBtnStyle"
                buttonStyle="outline-solid"
                style={
                  (policyNumber || !btnDisable) && !notEditable
                    ? { ...(lessthan767 && { width: "100%" }) }
                    : {
                        cursor: "not-allowed",
                        ...(lessthan767 && { width: "100%" }),
                        ...(notEditable && { visibility: "hidden" }),
                      }
                }
                hex1={
                  policyNumber && !notEditable
                    ? Theme?.Registration?.proceedBtn?.background || "#bdd400"
                    : "#e7e7e7"
                }
                hex2={
                  policyNumber && !notEditable
                    ? Theme?.Registration?.proceedBtn?.background || "#bdd400"
                    : "#e7e7e7"
                }
                borderRadius={lessthan767 ? "30px" : "5px"}
                disabled={
                  (policyNumber ? false : true) || btnDisable || notEditable
                }
                onClick={() => {
                  // onSubmit(1);
                  onSubmitFastLaneRenewal("policy");
                  setbtnDisable(true);
                }}
                height="60px"
                type="submit"
              >
                {!buffer && (
                  <text
                    style={{
                      color:
                        policyNumber && !notEditable
                          ? Theme?.Registration?.proceedBtn?.color
                            ? Theme?.Registration?.proceedBtn?.color
                            : "black"
                          : "black",
                    }}
                  >
                    {"Renew"}
                  </text>
                )}
                {buffer && !(regNo1 && regNo3) && (
                  <>
                    <Spinner
                      variant="light"
                      as="span"
                      animation="grow"
                      size="sm"
                    />
                    <Spinner
                      variant="light"
                      as="span"
                      animation="grow"
                      size="sm"
                      className={"mx-1"}
                    />
                    <Spinner
                      variant="light"
                      as="span"
                      animation="grow"
                      size="sm"
                    />
                  </>
                )}
              </Button>
            }
          </InputContainer>
        </Body>
      </Container>
      {/*--------------------Journey Mismatch Modal-------------------*/}
      <JourneyMismatch
        enquiry_id={enquiry_id}
        show={TypeReturn(show)}
        onHide={() => setShow(false)}
        setValue={setValue}
        journeyChange={journeyChange}
        clearFastLane={() => dispatch(setFastLaneRenewal(false))}
        Renewal
        frontendurl={frontendurl}
      />
      {/*---------------x----Journey Mismatch Modal--------x-----------*/}
      <GlobalStyle disabledBackdrop={false} />
    </>
  );
};

export default RenewalRegistration;
