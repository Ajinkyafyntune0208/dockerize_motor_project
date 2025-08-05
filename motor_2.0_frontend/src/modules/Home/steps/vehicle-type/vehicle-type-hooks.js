import { useEffect } from "react";
import { VehicleType as Type, clear } from "modules/Home/home.slice";
import { journeyProcess, PaymentIncomplete, PostTransaction } from "utils";
import {
  Url,
  DuplicateEnquiryId,
  clrDuplicateEnquiry,
} from "modules/proposal/proposal.slice";

export const useVehicleData = (dispatch, enquiry_id) => {
  useEffect(() => {
    dispatch(Type({ enquiry_id: enquiry_id }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
};

export const useLeadGeneration = (dispatch, temp_data, enquiry_id) => {
  useEffect(() => {
    journeyProcess(
      dispatch,
      Url,
      DuplicateEnquiryId,
      enquiry_id,
      temp_data,
      "Lead Generation"
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.journeyStage?.stage]);
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

export const usePostTransactions = (temp_data, enquiry_id, _stToken) => {
  useEffect(() => {
    PostTransaction(temp_data, false, false, enquiry_id, _stToken);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data?.journeyStage?.stage]);
};

export const usePrefill = (temp_data, setSelected, setgcvCarrierType) => {
  useEffect(() => {
    if (temp_data?.productSubTypeId) {
      setSelected(temp_data?.productSubTypeId);
    }
    if (temp_data?.gcvCarrierType) {
      setgcvCarrierType(temp_data?.gcvCarrierType);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [temp_data]);
};

export const useSuccessAndErrorHandling = (dispatch, urlParams, restParams) => {
  const { typeId, token, type, journey_type, _stToken, enquiry_id, shared } = urlParams;
  const { temp_data, saveQuoteData, history, errorProp, setbtnDisable } =
    restParams;

  //onSuccess
  useEffect(() => {
    if (saveQuoteData) {
      history.push(
        `/${type}/vehicle-details?enquiry_id=${
          temp_data?.enquiry_id || enquiry_id
        }${token ? `&xutm=${token}` : ``}${typeId ? `&typeid=${typeId}` : ``}${
          journey_type ? `&journey_type=${journey_type}` : ``
        }${_stToken ? `&stToken=${_stToken}` : ``}${
          shared ? `&shared=${shared}` : ``
        }`
      );
    }

    return () => {
      dispatch(clear("saveQuoteData"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [saveQuoteData]);

  //onError
  useEffect(() => {
    if (errorProp) {
      setbtnDisable(false);
    }

    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [errorProp]);
};
