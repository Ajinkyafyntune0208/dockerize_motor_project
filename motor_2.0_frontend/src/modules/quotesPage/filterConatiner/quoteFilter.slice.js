import { createSlice } from "@reduxjs/toolkit";
import service, { saveQuoteRequestData } from "./serviceApi";
import { actionStructre, serializeError } from "utils";

export const quoteFilterSlice = createSlice({
  name: "quoteFilter",
  initialState: {
    loading: false,
    error: null,
    success: null,
    leadStage: null,
    ncbList: [],
    prevInsList: [],
    tempData: {
      idvType: "",
    },
    saveQuote: null,
    errorSpecific: null,
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
      state.loading = null;
      state.error = serializeError(payload);
      state.success = payload;
    },
    errorSpecific: (state, { payload }) => {
      state.errorSpecific = serializeError(payload);
    },
    clear: (state, { payload }) => {
      state.loading = null;
      state.error = null;
      state.errorSpecific = null;
      state.success = null;
      switch (payload) {
        case "saveQuote":
          state.saveQuote = null;
          break;
        default:
          break;
      }
    },
    ncbList: (state, { payload }) => {
      state.ncbList = payload;
    },
    prevInsList: (state, { payload }) => {
      state.prevInsList = payload;
    },

    setTempData: (state, { payload }) => {
      state.tempData = { ...state.tempData, ...payload };
    },

    saveQuoteData: (state, { payload }) => {
      state.saveQuote = payload;
    },
    leadStage: (state, { payload }) => {
      state.leadStage = payload;
    },
  },
});

export const {
  loading,
  success,
  error,
  clear,
  ncbList,
  prevInsList,
  setTempData,
  saveQuoteData,
  leadStage,
  errorSpecific,
} = quoteFilterSlice.actions;

export const NcbList = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, ncbList, error, service.ncbList, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const PrevInsList = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, prevInsList, error, service.prevInsList, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const SaveQuoteData = (payload, statusSkip) => {
  return async (dispatch) => {
    try {
      //	dispatch(loading(true));
      const response = await saveQuoteRequestData(payload);
      if (response?.data?.status) {
        !statusSkip && dispatch(saveQuoteData(response?.data?.status));
      }
      const { data, message, errors, success, errorSpecific } =
        await service.saveQuoteRequestData(payload);
      if (data?.data || success) {
        dispatch(saveQuoteData(data?.status));
      } else {
        dispatch(errorSpecific(errorSpecific));
        dispatch(error(errors || message));
        console.error("Error", errors || message);
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const SaveLead = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, leadStage, error, service.saveLeadData, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export default quoteFilterSlice.reducer;
