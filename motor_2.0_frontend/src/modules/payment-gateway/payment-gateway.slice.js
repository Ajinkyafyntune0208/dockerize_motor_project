import { createSlice } from "@reduxjs/toolkit";
import service from "./serviceApi";
import { serializeError, actionStructre, actionStructreBoth } from "utils";

export const paymentGatewaySlice = createSlice({
  name: "payment",
  initialState: {
    loading: false,
    policyLoading: false,
    error: null,
    success: null,
    user_data: {},
    order: {},
    payment_id: null,
    documentURL: [],
    payment: "",
    policy: {},
    RZerror: null,
    policyError: null,
    raw: null,
  },
  reducers: {
    loading: (state) => {
      state.loading = true;
      state.error = null;
      state.success = null;
    },
    success: (state, { payload }) => {
      state.loading = null;
      state.error = null;
      state.success = payload;
    },
    error: (state, { payload }) => {
      state.loading = false;
      state.error = serializeError(payload);
      state.success = null;
      state.policyLoading = false;
    },
    clear: (state, { payload }) => {
      state.error = null;
      state.success = null;
      state.payment = null;
      state.loading = false;
      state.policyLoading = false;
      state.RZerror = false;
      state.policyError = false;
      switch (payload) {
        case "pdf":
          state.policy = {};
          break;
        default:
          break;
      }
    },
    order: (state, { payload }) => {
      state.order = payload;
    },
    user_data: (state, { payload }) => {
      state.user_data = payload[0];
    },
    payment_id: (state, { payload }) => {
      state.payment_id = payload;
    },
    clear_info: (state) => {
      state.user_data = {};
      state.order = {};
      state.payment_id = null;
    },
    documentURL: (state, { payload }) => {
      state.documentURL = payload;
      state.loading = false;
    },
    clearDocumentURL: (state) => {
      state.documentURL = null;
      state.loading = false;
    },
    payment: (state, { payload }) => {
      state.payment = payload;
      state.loading = false;
    },
    policy: (state, { payload }) => {
      state.policy = payload;
      state.loading = false;
      state.policyLoading = false;
    },
    RZerror: (state, { payload }) => {
      state.loading = false;
      state.RZerror = serializeError(payload);
      state.success = null;
    },
    policyLoading: (state, { payload }) => {
      state.policyLoading = true;
    },
    policyError: (state, { payload }) => {
      state.policy = { empty: "" };
      state.policyError = serializeError(payload);
      state.error = serializeError(payload);
    },
    raw: (state, { payload }) => {
      state.raw = payload;
    },
  },
});

export const {
  loading,
  success,
  error,
  clear,
  order,
  user_data,
  payment_id,
  clear_info,
  documentURL,
  clearDocumentURL,
  payment,
  policy,
  RZerror,
  policyLoading,
  policyError,
  raw,
} = paymentGatewaySlice.actions;

// Action creator

// load Order
export const loadOrder = (payload) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      const {
        data,
        message,
        errors,
        success: status,
      } = await service.loadOrder(payload);
      if (status && data.data) {
        dispatch(order(data.data));
        dispatch(payment_id(data.paymentId));
      } else {
        dispatch(error(message || errors));
        console.error("Error", message || errors);
      }
    } catch (err) {
      dispatch(error("Something Went Wrong"));
      console.error("Error", err);
    }
  };
};

// submit Order
export const saveOrder = (payload, api) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      // actionStructre(dispatch, success, error, service.saveOrder, payload)
      const {
        message,
        errors,
        success: status,
        raw_response,
      } = await service.razorapi(payload, api);
      if (status) {
        dispatch(success(raw_response?.data?.redirectUrl));
        // // zoho
        // service.zohoAfterPayment(zohoData)
      } else {
        dispatch(RZerror(message || errors));
        console.error("Error", message || errors);
      }
    } catch (err) {
      dispatch(error("Something Went Wrong"));
      console.error("Error", err);
    }
  };
};

//payment
export const PaymentApi = (data, typeRoute) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      actionStructreBoth(
        dispatch,
        payment,
        error,
        service.payment,
        {
          data,
          typeRoute,
        },
        false,
        raw
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

//Policy Gen
export const PolicyGen = (data) => {
  return async (dispatch) => {
    try {
      dispatch(policyLoading());
      actionStructre(dispatch, policy, policyError, service.policyGen, data);
    } catch (err) {
      dispatch(clear());
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
export default paymentGatewaySlice.reducer;
