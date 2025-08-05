/* eslint-disable react-hooks/exhaustive-deps */
import { useEffect, useMemo, useState } from "react";
import {
  clear,
  Prefill,
  adrila,
  CheckAddon,
  AdrilaLoad,
  clrDuplicateEnquiry,
  GetFields,
} from "modules/proposal/proposal.slice";
import moment from "moment";
import { reloadPage, Encrypt, AccessControl } from "utils";
import swal from "sweetalert";
//prettier-ignore
import { rollover_breakin_constructor, nb_construct, expiry_construct } from "./request-helper";
import { SaveQuoteData, Prefill as PrefillHome } from "modules/Home/home.slice";
import { differenceInDays, differenceInHours, toDate } from "date-fns";
import _ from "lodash";
import { cpaSet as setCpa } from "modules/quotesPage/quote.slice";
import { MethodError, IdError } from "modules/proposal/cards/data";
import { EncryptUser, encryptUser as clearUser } from "modules/Home/home.slice";
import { _trackProfile } from "analytics/user-creation.js/user-creation";
import { TypeCategory, TypeReturn } from "modules/type";
import { saod } from "modules/Home/steps/Registration/helper";
import { _proposalPageTracking } from "analytics/proposal-tracking/proposal-tracking";

export const useAccessControl = (history, typeAccess, type) => {
  useEffect(() => {
    if (!_.isEmpty(typeAccess)) {
      AccessControl(type, typeAccess, history);
    }
  }, [typeAccess]);
};

export const useEnquiryValidation = (dispatch, history, urlParams) => {
  const { enquiry_id, type, token, typeId, journey_type, _stToken, shared } = urlParams;
  useEffect(() => {
    if (!enquiry_id) {
      history.replace(
        `/${type}/lead-page${token ? `?xutm=${token}` : ``}${
          typeId ? `&typeid=${typeId}` : ``
        }${journey_type ? `&journey_type=${journey_type}` : ``}${
          _stToken ? `&stToken=${_stToken}` : ``
        }${shared ? `&shared=${shared}` : ``}`
      );
    }
    return () => {
      dispatch(clear());
    };
  }, [enquiry_id]);
};

export const useProposalExpiry = (
  dispatch,
  temp,
  GenerateDulicateEnquiry,
  type,
  enquiry_id,
  setPaymentModal
) => {
  useEffect(() => {
    //excluding breakin journey s and bike product
    //Rollover to Breakin Transition.
    if (
      !_.isEmpty(temp) &&
      temp?.corporateVehiclesQuoteRequest?.businessType !== "breakin" &&
      temp?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate &&
      temp?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate !== "New" &&
      ![
        "Policy Issued",
        "Policy Issued, but pdf not generated",
        "Policy Issued And PDF Generated",
        "payment success",
      ].includes(
        ["payment success"].includes(temp?.journeyStage?.stage.toLowerCase())
          ? temp?.journeyStage?.stage.toLowerCase()
          : temp?.journeyStage?.stage
      )
    ) {
      //comparing expiry date with current date
      let [day, month, year] =
        temp?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate.split(
          "-"
        );
      let expDateObj = new Date(year, month * 1 - 1, day);
      if (
        expDateObj.setHours(0, 0, 0, 0).valueOf() <
        new Date().setHours(0, 0, 0, 0).valueOf()
      ) {
        //changing bussinessType
        dispatch(
          SaveQuoteData({
            ...rollover_breakin_constructor(temp, enquiry_id, type),
          })
        );
        swal("Please Note", "This proposal has been expired", "info").then(() =>
          reloadPage(window.location.href.replace(/proposal-page/, "quotes"))
        );
      }
    }

    //policy date < current date
    if (
      temp?.userProposal?.policyStartDate &&
      ![
        "Policy Issued",
        "Policy Issued, but pdf not generated",
        "Policy Issued And PDF Generated",
        "payment success",
      ].includes(
        ["payment success"].includes(temp?.journeyStage?.stage.toLowerCase())
          ? temp?.journeyStage?.stage.toLowerCase()
          : temp?.journeyStage?.stage
      )
    ) {
      let [day, month, year] = temp?.userProposal?.policyStartDate.split("-");
      let policyStDateObj = new Date(year, month * 1 - 1, day);
      if (
        policyStDateObj.setHours(0, 0, 0, 0).valueOf() <
        new Date().setHours(0, 0, 0, 0).valueOf()
      ) {
        if (temp?.corporateVehiclesQuoteRequest?.businessType !== "breakin") {
          //changing registration date
          dispatch(
            SaveQuoteData({
              ...expiry_construct(temp, enquiry_id),
            })
          );
        } else {
          setPaymentModal(false);
          swal("Please Note", "This proposal has expired", "info").then(() =>
            GenerateDulicateEnquiry(true)
          );
        }
      }
    }

    if (
      import.meta.env.VITE_BROKER === "ACE" &&
      temp?.userProposal?.createdAt &&
      ![
        "Policy Issued",
        "Policy Issued, but pdf not generated",
        "Policy Issued And PDF Generated",
        "payment success",
      ].includes(
        ["payment success"].includes(temp?.journeyStage?.stage.toLowerCase())
          ? temp?.journeyStage?.stage.toLowerCase()
          : temp?.journeyStage?.stage
      ) && false
    ) {
      const formattedDate = moment(temp?.userProposal?.createdAt)._d;

      if (
        formattedDate.setHours(0, 0, 0, 0).valueOf() <=
        new Date().setHours(0, 0, 0, 0).valueOf() - 24 * 60 * 60 * 1000
      ) {
        if (temp?.corporateVehiclesQuoteRequest?.businessType !== "breakin") {
          //changing registration date
          setPaymentModal(false);
          swal("Please Note", "This proposal has expired", "info").then(() =>
            GenerateDulicateEnquiry()
          );
        }
      }
    }

    //72 hrs - ICICI/ 24 hrs - RSA & FG breakin condition ( bike excluded )
    if (
      type !== "bike" &&
      !_.isEmpty(temp) &&
      temp?.corporateVehiclesQuoteRequest?.businessType === "breakin" &&
      ((temp?.selectedQuote?.companyAlias === "icici_lombard" &&
        temp?.breakinGenerationDate) ||
        (temp?.selectedQuote?.companyAlias === "royal_sundaram" &&
          temp?.breakinExpiryDate) ||
        (temp?.selectedQuote?.companyAlias === "future_generali" &&
          temp?.breakinExpiryDate) ||
        (temp?.selectedQuote?.companyAlias === "kotak" &&
          temp?.breakinExpiryDate))
    ) {
      // checking for 72 hours/3 days limit.
      //To be moved on config
      let expTime =
        temp?.selectedQuote?.companyAlias === "icici_lombard"
          ? 216
          : temp?.selectedQuote?.companyAlias === "kotak"
          ? 48
          : 24;
      if (
        differenceInHours(moment()?._d, moment(temp?.breakinExpiryDate)?._d) >
        expTime
      ) {
        if (
          temp?.userProposal?.policyStartDate &&
          ![
            "Policy Issued",
            "Policy Issued, but pdf not generated",
            "Policy Issued And PDF Generated",
            "payment success",
          ].includes(
            ["payment success"].includes(
              temp?.journeyStage?.stage.toLowerCase()
            )
              ? temp?.journeyStage?.stage.toLowerCase()
              : temp?.journeyStage?.stage
          )
        )
        setPaymentModal(false);
        {
          swal("Please Note", "This proposal has expired", "info").then(() =>
            GenerateDulicateEnquiry(true)
          );
        }
      }
    }

    //NB redirection if Registration date < current date
    if (temp?.corporateVehiclesQuoteRequest?.businessType === "newbusiness") {
      if (temp?.corporateVehiclesQuoteRequest?.vehicleRegisterDate) {
        let [day, month, year] =
          temp?.corporateVehiclesQuoteRequest?.vehicleRegisterDate.split("-");
        let regtDateObj = new Date(year, month * 1 - 1, day);

        if (
          regtDateObj.setHours(0, 0, 0, 0).valueOf() <
          new Date().setHours(0, 0, 0, 0).valueOf()
        ) {
          //changing registration date to current date
          dispatch(
            SaveQuoteData({
              ...nb_construct(temp, enquiry_id),
            })
          );
        }
      }
    }
  }, [temp]);
};

export const usePrefill = (dispatch, enquiry_id) => {
  useMemo(() => {
    dispatch(Prefill({ enquiryId: enquiry_id }));
  }, [enquiry_id]);
};

export const useCpaStatusClear = (dispatch, enquiry_id, cpaSet) => {
  useEffect(() => {
    if (cpaSet) {
      dispatch(Prefill({ enquiryId: enquiry_id }, true));
      dispatch(PrefillHome({ enquiryId: enquiry_id }, true));
    }

    return () => {
      dispatch(setCpa(false));
    };
  }, [cpaSet]);
};

export const useAdrilaCall = (dispatch, enquiry_id, adrilaStatus) => {
  const [adrilaLimit, setAdrilaLimit] = useState(false);
  useEffect(() => {
    if (adrilaStatus && !adrilaLimit) {
      dispatch(Prefill({ enquiryId: enquiry_id }, true));
      dispatch(PrefillHome({ enquiryId: enquiry_id }, true));
      setAdrilaLimit(true);
    }

    return () => {
      dispatch(adrila(null));
    };
  }, [adrilaStatus]);
};

export const useAvailableAddons = (dispatch, temp, enquiry_id) => {
  const [addonLimit, setAddonLimit] = useState(false);
  useEffect(() => {
    if (!addonLimit && temp?.quoteLog?.icId) {
      dispatch(
        CheckAddon({
          icId: temp?.quoteLog?.icId,
          enquiryId: enquiry_id,
          conpanyAlias: temp?.selectedQuote?.companyAlias,
        })
      );
      setAddonLimit(true);
    }
  }, [temp]);
};

//using this only for vahaan validation now.
export const useAdrilaPrefill = (dispatch, temp, enquiry_id, type) => {
  useEffect(() => {
    if (
      temp?.regNo &&
      temp?.regNo !== "NEW" &&
      import.meta.env.VITE_BROKER === "ACE" &&
      !temp?.userProposal &&
      _.isEmpty(temp?.userProposal)
    ) {
      dispatch(
        AdrilaLoad({
          registration_no: temp?.regNo,
          enquiryId: enquiry_id,
          type: "PRO",
          section: TypeReturn(type),
          //using this only for vahaan validation now
          vehicleValidation: "Y",
        }, true)
      );
    }
  }, [temp?.regNo]);
};

export const useReloadPostEnquiryDuplication = (
  dispatch,
  urlParams,
  otherParams
) => {
  const { type, token, journey_type, typeId, _stToken, shared } = urlParams;
  const { temp, duplicateEnquiry, breakinEnquiry, dropout } = otherParams;

  const redirectionOnReload = (enquiryId, breakin) => {
    return `${window.location.protocol}//${window.location.host}${
      import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ``
    }/${type}/${
      temp?.selectedQuote?.companyAlias === "royal_sundaram"
        ? "quotes"
        : "proposal-page"
    }?enquiry_id=${enquiryId}${token ? `&xutm=${token}` : ""}${
      dropout && breakin ? `&dropout=${Encrypt(true)}` : ""
    }${journey_type ? `&journey_type=${journey_type}` : ``}${
      typeId ? `&typeid=${typeId}` : ``
    }${_stToken ? `&stToken=${_stToken}` : ``}${
      shared ? `&shared=${shared}` : ``
    }`;
  };

  //duplicate enquiry
  useEffect(() => {
    if (duplicateEnquiry?.enquiryId) {
      reloadPage(redirectionOnReload(duplicateEnquiry?.enquiryId));
    }
    return () => {
      dispatch(clrDuplicateEnquiry());
    };
  }, [duplicateEnquiry]);

  //breakin enquiry
  useEffect(() => {
    if (breakinEnquiry?.enquiryId) {
      reloadPage(redirectionOnReload(breakinEnquiry?.enquiryId, true));
    }
    return () => {
      dispatch(clrDuplicateEnquiry());
    };
  }, [breakinEnquiry]);
};

export const useFieldConfig = (dispatch, temp, type) => {
  const [fieldsPresent, setFieldPresent] = useState(0);
  useEffect(() => {
    if (
      (!fieldsPresent || fieldsPresent < 3) &&
      temp?.selectedQuote?.companyAlias &&
      temp?.corporateVehiclesQuoteRequest?.vehicleOwnerType
    ) {
      dispatch(
        GetFields({
          company_alias: temp?.selectedQuote?.companyAlias,
          section: TypeReturn(type),
          owner_type: temp?.corporateVehiclesQuoteRequest?.vehicleOwnerType,
        })
      );
      setFieldPresent((prev) => prev + 1);
    }
  }, [
    temp?.selectedQuote?.companyAlias,
    temp?.corporateVehiclesQuoteRequest?.vehicleOwnerType,
  ]);
};

export const useEnquiryIncrement = (
  temp,
  GenerateDulicateEnquiry,
  theme_conf
) => {
  useEffect(() => {
    if (
      ["Payment Initiated", "payment failed"].includes(
        ["payment failed"].includes(temp?.journeyStage?.stage.toLowerCase())
          ? temp?.journeyStage?.stage.toLowerCase()
          : temp?.journeyStage?.stage
      ) &&
      temp?.corporateVehiclesQuoteRequest?.businessType !== "breakin" &&
      theme_conf?.broker_config?.allow_multipayment
    ) {
      swal({
        title: "Payment Attempted for Existing Trace ID",
        text: `Payment was attempted for the existing Trace ID. To retry, the Proposal Trace ID will be updated. Please proceed after the update.`,
        icon: "info",
        buttons: {
          catch: {
            text: "Confirm",
            value: "confirm",
          },
        },
        dangerMode: true,
      }).then((caseValue) => {
        switch (caseValue) {
          case "confirm":
            return GenerateDulicateEnquiry();
          // break;
          default:
        }
      });
    }
  }, [temp, theme_conf]);
};

export const useErrorHandling = (
  dispatch,
  temp,
  enquiry_id,
  error_other,
  errorSpecific,
  ckycErrorData
) => {
  useEffect(() => {
    if (
      error_other &&
      !(
        [...MethodError, ...IdError].includes(error_other) ||
        (temp?.selectedQuote?.companyAlias === "bajaj_allianz" &&
          temp?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "I" &&
          ckycErrorData?.poi_status)
      )
    ) {
      swal({
        title: "Error",
        text: enquiry_id
          ? `${`Trace ID:- ${
              temp?.traceId ? temp?.traceId : enquiry_id
            }.\n Error Message:- ${error_other}`}`
          : error_other,
        icon: "error",
        buttons: {
          cancel: "Ok",
          ...(errorSpecific && {
            catch: {
              text: "See more details",
              value: "confirm",
            },
          }),
        },
        dangerMode: true,
      }).then((caseValue) => {
        if (error_other === "Payment Initiated") {
          window.location.reload();
        } else {
          switch (caseValue) {
            case "confirm":
              swal(
                "Error",
                enquiry_id
                  ? `${`Trace ID:- ${
                      temp?.traceId ? temp?.traceId : enquiry_id
                    }.\n Error Message:- ${errorSpecific}`}`
                  : errorSpecific,
                "error"
              );
              break;
            default:
          }
        }
      });
    }
    return () => {
      dispatch(clear());
    };
  }, [error_other]);
};

export const useProfileTracking = (dispatch, temp, encryptUser) => {
  const credentials =
    temp?.firstName && temp?.lastName && temp?.mobileNo && temp?.emailId;

  const [cipher, setCipher] = useState(false);
  useEffect(() => {
    if (!cipher && credentials && !!window?.webengage) {
      dispatch(EncryptUser({ id: `${temp?.mobileNo}` }));
      setCipher(true);
    }
  }, [temp]);

  const [tracked, setTracked] = useState(false);
  useEffect(() => {
    if (credentials && encryptUser && !tracked) {
      _trackProfile({
        fullName: `${temp?.firstName} ${temp?.lastName}`,
        emailId: temp?.emailId,
        mobileNo: temp?.mobileNo,
        id: encryptUser,
      });
      setTracked(true);
      dispatch(clearUser(null));
    }
  }, [temp, encryptUser]);
};

/**
 * Custom hook to check the expiry of the inspection waiver period and
 * display an alert if it has expired.
 *
 * @param {object} temp - Object containing the quoteLog and
 * premiumJson properties.
 */
export const useWaierExpiry = (temp) => {
  // Get the waiver period from the quoteLog.premiumJson object.
  let waiverPeriod = temp?.quoteLog?.premiumJson?.waiverExpiry;

  // Effect hook to check the waiver period.
  useEffect(() => {
    if (!!waiverPeriod) {
      // Convert the waiverPeriod to a Date object.
      const proposalDay = moment(
        temp?.quoteLog?.premiumJson?.waiverExpiry,
        "DD-MM-YYYY"
      ).toDate();

      // Get today's date.
      const today = moment().toDate();

      // Calculate the difference in days between today and the proposalDay.
      const diff = +differenceInDays(proposalDay, today);

      // If the waiver period has expired, display an alert.
      if (diff < 0) {
        swal(
          "Info",
          `Your break-in waiver period has expired, necessitating an inspection. Consequently, this proposal is no longer valid, and you will be redirected to the quote page.`,
          "info",
          {
            closeOnClickOutside: false,
          }
        ).then(() =>
          reloadPage(window.location.href.replace(/proposal-page/g, "quotes"))
        );
      }
    }
  }, [waiverPeriod]);
};

export const useProposalTracking = (temp_data) => {
  const [trackCount, setTrackCount] = useState(false);
  useEffect(() => {
    if (
      !trackCount &&
      !_.isEmpty(temp_data) &&
      temp_data?.corporateVehiclesQuoteRequest?.businessType
    ) {
      setTrackCount(true);
      _proposalPageTracking(temp_data);
    }
  }, [temp_data]);
};
