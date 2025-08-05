import { useEffect } from "react";
import { journeyProcess, PaymentIncomplete, PostTransaction } from "utils";
import {
  Url,
  DuplicateEnquiryId,
  clrDuplicateEnquiry,
} from "modules/proposal/proposal.slice";

export const useSavedStep = (savedStep, setStep, urlParams) => {
  const { enquiry_id, token, journey_type, typeId, _stToken, shared } = urlParams;
  useEffect(() => {
    if (savedStep * 1) {
      setStep(savedStep * 1);

      //removing the query param
      var queryUrl = window.location.search.substring(1);
      if (queryUrl.length) {
        // are the new history methods available ?
        if (
          window.history !== undefined &&
          window.history.pushState !== undefined
        ) {
          // if pushstate exists, add a new state to the history, this changes the url without reloading the page
          const newurl =
            window.location.protocol +
            "//" +
            window.location.host +
            window.location.pathname +
            `?enquiry_id=${enquiry_id}${token ? `&xutm=${token}` : ``}${
              typeId ? `&typeid=${typeId}` : ``
            }${journey_type ? `&journey_type=${journey_type}` : ``}${
              _stToken ? `&stToken=${_stToken}` : ``
            }${shared ? `&shared=${shared}` : ``}`;
          window.history.pushState({ path: newurl }, "", newurl);
        }
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [savedStep]);
};

export const useStepperPrefill = (stepperfill, setStep) => {
  useEffect(() => {
    if (stepperfill * 1) {
      setStep(stepperfill * 1);
    } else if (stepperfill && !stepperfill * 1) {
      setStep(6);
    }
  }, [stepperfill]);
};

export const useJourneyProcess = (dispatch, temp_data, urlParams) => {
  const { enquiry_id, Step, type } = urlParams;
  useEffect(() => {
    //prettier-ignore
    journeyProcess(dispatch, Url, DuplicateEnquiryId ,enquiry_id, temp_data, "Lead Generation",Step ? `&stepNo=${Step}` : `` , type)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.journeyStage?.stage, Step]);
};

export const usePaymentStatus = (dispatch, duplicateEnquiry, urlParams) => {
  const { typeId, type, token, journey_type, _stToken, shared } = urlParams;
  useEffect(() => {
    if (duplicateEnquiry?.enquiryId) {
      PaymentIncomplete(
        type,
        token,
        duplicateEnquiry?.enquiryId,
        typeId,
        journey_type,
        "vehicle-type",
        _stToken,
        shared
      );
    }
    return () => {
      dispatch(clrDuplicateEnquiry());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [duplicateEnquiry]);
};

export const usePostTransaction = (temp_data, enquiry_id, _stToken) => {
  useEffect(() => {
    PostTransaction(temp_data, false, false, enquiry_id, _stToken);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.journeyStage?.stage]);
};

export const useJourneyCompletion = (temp_data, history, urlParams) => {
  const { token, journey_type, enquiry_id, typeId, _stToken, Step, type, shared } =
    urlParams;
  useEffect(() => {
    if (Number(temp_data?.journeyType) === 3 || temp_data?.regNo === "NEW") {
      if (Step > 5) {
        history.push(
          `/${type}/quotes?enquiry_id=${temp_data?.enquiry_id || enquiry_id}${
            token ? `&xutm=${token}` : ``
          }${journey_type ? `&journey_type=${journey_type}` : ``}${
            typeId ? `&typeid=${typeId}` : ``
          }${_stToken ? `&stToken=${_stToken}` : ``}${
            shared ? `&shared=${shared}` : ``
          }`
        );
      }
    } else {
      if (Step > 6) {
        history.push(
          `/${type}/quotes?enquiry_id=${temp_data?.enquiry_id || enquiry_id}${
            token ? `&xutm=${token}` : ``
          }${journey_type ? `&journey_type=${journey_type}` : ``}${
            typeId ? `&typeid=${typeId}` : ``
          }${_stToken ? `&stToken=${_stToken}` : ``}${
            shared ? `&shared=${shared}` : ``
          }`
        );
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [Step]);
};
