/* eslint-disable react-hooks/exhaustive-deps */
import { useEffect } from "react";
import _ from "lodash";
import moment from "moment";
import { getFrontendUrl } from "modules/Home/home.slice";
// prettier-ignore
import { SaveQuoteData, set_temp_data, setFastLane, 
         clear, overrideMsg as clearMsg 
        } from "modules/Home/home.slice";
// prettier-ignore
import { SaveLead, SaveQuoteData as SaveQuoteDataQuotes } from "modules/quotesPage/filterConatiner/quoteFilter.slice";
import { CancelAll } from "modules/quotesPage/quote.slice";
import { vahaanConstants } from "./helper";
//prettier-ignore
import { toDate, PaymentIncomplete, PostTransaction, reloadPage, Encrypt, _haptics } from "utils";
import { differenceInDays } from "date-fns";
import swal from "sweetalert";
//prettier-ignore
import { setFastlaneState, getVahaanData, getUserData,
         getPolicyData, getVehicleDetails, journeyTrackers,
         updatePolicyExpiry, fastLaneDataObject
        } from './reg-constructor';
import { clrDuplicateEnquiry } from "modules/proposal/proposal.slice";
import { useSelector } from "react-redux";

export const useFrontendURL = (dispatch, enquiry_id, frontendurl) => {
  useEffect(() => {
    enquiry_id &&
      _.isEmpty(frontendurl) &&
      dispatch(getFrontendUrl({ enquiry_id }));
  }, [enquiry_id]);
};

export const useFastlaneResponse = (funcParams, journeyParams, restParams) => {
  let { dispatch, onSubmit, setBuffer, setbtnDisable, setValue } = funcParams;
  let { enquiry_id, token, TypeReturn, type, journey_type } = journeyParams;
  let { temp_data, fastLaneData, regIp, regNo1, regNo2, regNo3 } = restParams;

  const { vahaanConfig } = useSelector((state) => state.home);
  useEffect(() => {
    if (
      fastLaneData?.status === 100 &&
      (!_.isEmpty(fastLaneData?.results) ||
        import.meta.env?.VITE_BROKER === "ACE") &&
      vahaanConstants(vahaanConfig, type)
    ) {
      //Storing fastlane data
      let vehicleData =
        !_.isEmpty(fastLaneData?.results) && fastLaneData?.results[0]?.vehicle;
      //setting internal state
      set_temp_data(setFastlaneState(fastLaneData));
      //Request body for save quote request data.
      const quoteData = {
        enquiryId: temp_data?.enquiry_id || enquiry_id,
        ...getVahaanData(vehicleData, fastLaneData, TypeReturn, type),
        ...getUserData(temp_data),
        ...getPolicyData(temp_data),
        ...getVehicleDetails(regIp, regNo1, regNo2, regNo3, TypeReturn(type)),
        ...journeyTrackers(temp_data, enquiry_id, journey_type, token),
        ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
          frontendTags: JSON.stringify({ hideRenewal: true }),
        }),

        manufacturerId: fastLaneData?.additional_details?.manufacturerId,
        manufacturerYear: fastLaneData?.additional_details?.manufacturerYear,
      };
      //home state
      setTimeout(
        () =>
          (fastLaneData?.ft_product_code === TypeReturn(type) ||
            !fastLaneData?.ft_product_code) &&
          !fastLaneData?.sub_section &&
          dispatch(
            SaveQuoteData({
              isRenewalRedirection: "N",
              ...updatePolicyExpiry(quoteData),
              // ...(isPartner === "Y" && { frontendTags: "Y" }),
              ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
                frontendTags: JSON.stringify({ hideRenewal: true }),
              }),
              ...(localStorage?.SSO_user && {
                tokenResp: localStorage?.SSO_user,
              }),
              lsq_stage: "RC Submitted",
              ...(fastLaneData?.additional_details?.policyExpiryDate
                ? differenceInDays(
                    toDate(fastLaneData?.additional_details?.policyExpiryDate),
                    toDate(moment().format("DD-MM-YYYY"))
                  ) < 45
                  ? { ...fastLaneData?.additional_details }
                  : updatePolicyExpiry(fastLaneData?.additional_details)
                : {
                    ...(localStorage?.SSO_user && {
                      tokenResp: localStorage?.SSO_user,
                    }),
                    ...updatePolicyExpiry(fastLaneData?.additional_details),
                  }),
              manfactureName: fastLaneData?.additional_details?.manfactureName,
              manfactureId: fastLaneData?.additional_details?.manfactureId,
              model: fastLaneData?.additional_details?.model,
              modelName: fastLaneData?.additional_details?.modelName,
              version: fastLaneData?.additional_details?.version,
              versionName: fastLaneData?.additional_details?.versionName,
              manufacturerYear:
                fastLaneData?.additional_details?.manufacturerYear,
              policyExpiryDate:
                fastLaneData?.additional_details?.policyExpiryDate,
            })
          ),
        50
      );
      // quotesFilter state
      (fastLaneData?.ft_product_code === TypeReturn(type) ||
        !fastLaneData?.ft_product_code) &&
        !fastLaneData?.sub_section &&
        setTimeout(
          () =>
            dispatch(
              SaveQuoteDataQuotes(
                {
                  ...updatePolicyExpiry(quoteData),
                  lsq_stage: "Quote Seen",
                  ...(fastLaneData?.additional_details?.policyExpiryDate
                    ? differenceInDays(
                        toDate(
                          fastLaneData?.additional_details?.policyExpiryDate
                        ),
                        toDate(moment().format("DD-MM-YYYY"))
                      ) < 45
                      ? { ...fastLaneData?.additional_details }
                      : updatePolicyExpiry(fastLaneData?.additional_details)
                    : {
                        ...(localStorage?.SSO_user && {
                          tokenResp: localStorage?.SSO_user,
                        }),
                        ...updatePolicyExpiry(fastLaneData?.additional_details),
                      }),

                  manfactureName:
                    fastLaneData?.additional_details?.manfactureName,
                  manfactureId: fastLaneData?.additional_details?.manfactureId,
                  model: fastLaneData?.additional_details?.model,
                  modelName: fastLaneData?.additional_details?.modelName,
                  version: fastLaneData?.additional_details?.version,
                  versionName: fastLaneData?.additional_details?.versionName,
                  manufacturerYear:
                    fastLaneData?.additional_details?.manufacturerYear,
                  policyExpiryDate:
                    fastLaneData?.additional_details?.policyExpiryDate,
                },
                fastLaneData?.RenwalData === "Y"
                  ? false
                  : true
              )
            ),
          50
        );
      dispatch(
        SaveLead({
          enquiryId: temp_data?.enquiry_id || enquiry_id,
          leadStageId: 2,
        })
      );
      dispatch(
        set_temp_data({
          fastlaneJourney: true,
          fastlaneNcbPopup: true,
          isRenewalRedirection: "N",
          ...(fastLaneData?.additional_details?.manfactureId && {
            manfName: fastLaneData?.additional_details?.manfactureName,
            manfactureId: fastLaneData?.additional_details?.manfactureId,
            manfId: fastLaneData?.additional_details?.manfactureId,
            modelId: fastLaneData?.additional_details?.model,
            modelName: fastLaneData?.additional_details?.modelName,
            versionId: fastLaneData?.additional_details?.version,
            versionName: fastLaneData?.additional_details?.versionName,
            expiry: fastLaneData?.additional_details?.policyExpiryDate,
            vahaanService: fastLaneData?.additional_details?.vahan_service_code,
          }),
        })
      );

      // dispatch(SaveQuoteDataQuotesKey(null));
    } else {
      if (
        [101, 108].includes(fastLaneData?.status * 1) &&
        !fastLaneData?.showMessage
      ) {
        onSubmit(1);
      } else {
        fastLaneData?.showMessage &&
          swal("Info", fastLaneData?.showMessage, "info").then(() => [
            dispatch(setFastLane(false)),
            setBuffer(false),
            setbtnDisable(false),
            setValue("regNo", ""),
          ]);
      }
    }
  }, [fastLaneData]);
};

export const useSuccessRedirection = (
  stateParams,
  urlParams,
  typeParams,
  otherParams
) => {
  const {
    temp_data,
    saveQuoteData,
    fastLaneData,
    theme_conf,
    setPreventAutoSubmit,
  } = stateParams;
  const { enquiry_id, token, typeId, _stToken, params, rcNum } = urlParams;
  const { TypeReturn, type, journey_type, TabClick } = typeParams;
  const { dispatch, setBuffer, history, setValue, overrideMsg } = otherParams;

  const redirectToVehicleType = (route, stepperfill) => {
    history.push(
      `/${type}/${route}?enquiry_id=${temp_data?.enquiry_id || enquiry_id}${
        token ? `&xutm=${token}` : ``
      }${typeId ? `&typeid=${typeId}` : ``}${
        stepperfill ? `&stepperfill=${stepperfill}` : ``
      }${journey_type ? `&journey_type=${journey_type}` : ``}${
        _stToken ? `&stToken=${_stToken}` : ``
      }`
    );
  };

  useEffect(() => {
    if (saveQuoteData && TypeReturn(type)) {
      setBuffer(false);
      //fast lane redirection
      if (fastLaneData?.status === 100) {
        fastLaneData?.RenwalData === "Y" &&
          ["Third Party", "Third-party"].includes(
            fastLaneData?.additional_details?.previousPolicyType
          ) &&
          dispatch(TabClick(true));
        dispatch(
          set_temp_data({
            leadJourneyEnd: true,
            leadStageId: 2,
            isRenewalRedirection: "N",
            ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
              frontendTags: JSON.stringify({ hideRenewal: true }),
            }),
            //storing data in redux for fastlane / ongrid
            ...(fastLaneData?.additional_details &&
              fastLaneDataObject(fastLaneData)),
          })
        );
        dispatch(CancelAll(false));
        setTimeout(() => {
          if (
            fastLaneData?.redirection_data?.is_redirection &&
            fastLaneData?.redirection_data?.redirection_url
          ) {
            reloadPage(fastLaneData?.redirection_data?.redirection_url);
          } else {
            //partial mmv logic
            if (!_.isEmpty(fastLaneData?.results)) {
              let { manfactureId, model, version, vehicleRegisterDate, rto } =
                fastLaneData?.additional_details;
              //all data fetched
              if (
                manfactureId &&
                model &&
                version &&
                vehicleRegisterDate &&
                rto
              ) {
                redirectToVehicleType("quotes");
              }
              // reg date missing
              else if (manfactureId && model && version && rto) {
                redirectToVehicleType("vehicle-details", Encrypt("date"));
              }
              // rto missing
              else if (manfactureId && model && version) {
                redirectToVehicleType("vehicle-details", "5");
              }
              //version missing
              else if (manfactureId && model) {
                redirectToVehicleType("vehicle-details", "4");
              }
              //all data fetched
              else if (manfactureId) {
                redirectToVehicleType("vehicle-details", "2");
              } else {
                redirectToVehicleType("vehicle-details", "1");
              }
            }
          }
        }, 1000);
      }
      //Non fast lane redirection
      else {
        setBuffer(false);
        if (TypeReturn(type) === "cv" && TypeReturn(type)) {
          !_.isEmpty(fastLaneData)
            ? swal({
                title: "Please Note",
                text:
                  overrideMsg ||
                  theme_conf?.broker_config?.fastlane_error_message ||
                  "We are unable to fetch your vehicle details at this moment. Please input your vehicle Make, Model, RTO details and proceed",
                icon: "info",
                buttons: {
                  catch: {
                    text: "Edit Reg. No.",
                    value: "confirm",
                  },
                  ...(!theme_conf?.broker_config?.journey_block &&
                    !overrideMsg && {
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
                    setValue("regNo", "");
                    break;
                  case "No":
                    redirectToVehicleType("vehicle-type");
                    break;
                  default:
                }
              })
            : redirectToVehicleType("vehicle-type");
        } else {
 
          if (temp_data?.productSubTypeCode && TypeReturn(type))
            if (!_.isEmpty(fastLaneData)) {
              swal({
                title: "Please Note",
                text:
                  overrideMsg ||
                  theme_conf?.broker_config?.fastlane_error_message ||
                  "We are unable to fetch your vehicle details at this moment. Please input your vehicle Make, Model, RTO details and proceed",
                icon: "info",
                buttons: {
                  catch: {
                    text: "Edit Reg. No.",
                    value: "confirm",
                  },
                  ...(!theme_conf?.broker_config?.journey_block &&
                    !overrideMsg && {
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
                    setValue("regNo", "");
                    if (rcNum) {
                      setPreventAutoSubmit(true); // deleting the query parameter which was added
                    }
                    break;
                  case "No":
                    redirectToVehicleType("vehicle-details");
                    break;
                  default:
                }
              });
            } else {
              redirectToVehicleType("vehicle-details");
            }
            dispatch(
              SaveLead({
                enquiryId: temp_data?.enquiry_id || enquiry_id,
                leadStageId: 2,
              })
            );
        }
      }
    }

    return () => {
      dispatch(clear("saveQuoteData"));
      saveQuoteData && dispatch(setFastLane(null));
      setTimeout(() => dispatch(clearMsg("")), 500);
    };
  }, [saveQuoteData, temp_data]);
};

export const usePrefill_RC = (temp_data, regIpCheck, isRegBH, setValue) => {
  useEffect(() => {
    if (temp_data?.regNo && (!regIpCheck || regIpCheck === temp_data?.regNo)) {
      temp_data?.regNo &&
        temp_data?.regNo !== "NEW" &&
        setValue(
          "regNo",
          isRegBH || !temp_data?.regNo1
            ? temp_data?.regNo
            : `${temp_data?.regNo1.split("-")[0]}-${
                temp_data?.regNo1.split("-")[1]
              }-${temp_data?.regNo2}-${temp_data?.regNo3}`.replace(/--/g, "-")
        );
    }
  }, [temp_data]);
};

export const useDuplicateEnquiry = (
  dispatch,
  duplicateEnquiry,
  type,
  token,
  typeId,
  journey_type,
  _stToken,
  shared
) => {
  useEffect(() => {
    if (duplicateEnquiry?.enquiryId) {
      //prettier-ignore
      PaymentIncomplete(type, token, duplicateEnquiry?.enquiryId, typeId, journey_type, "registration", _stToken, shared)
    }
    return () => {
      dispatch(clrDuplicateEnquiry());
    };
  }, [duplicateEnquiry]);
};

export const usePostTransactionHandler = (temp_data, enquiry_id, _stToken) => {
  useEffect(() => {
    PostTransaction(temp_data, false, false, enquiry_id, _stToken);
  }, [temp_data?.journeyStage?.stage]);
};
