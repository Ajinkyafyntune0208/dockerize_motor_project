import { createSlice } from "@reduxjs/toolkit";
import service from "./serviceApi";
import { serializeError } from "utils";

export const paymentPdfSlice = createSlice({
  name: "paymentPdf",
  initialState: {
    loading: false,
    error: null,
    success: null,
    submit: null,
  },
  reducers: {
    loading: (state) => {
      state.loading = true;
      state.error = null;
      state.success = null;
      state.submit = null;
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
      state.submit = null;
    },
    submit: (state, { payload }) => {
      state.loading = null;
      state.submit = payload;
      state.error = null;
    },
    clear: (state) => {
      state.loading = null;
      state.error = null;
      state.success = null;
      state.submit = null;
    },
  },
});

export const { loading, success, error, submit, clear } =
  paymentPdfSlice.actions;

export const SubmitData = (payload) => {
  return async (dispatch) => {
    try {
      dispatch(loading());
      const { data, message, errors, success } = await service.submit(payload);
      if (data?.data || success) {
        dispatch(submit(data?.data || message));
      } else {
        dispatch(error(errors || message));
        console.error("Error", errors || message);
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export default paymentPdfSlice.reducer;
