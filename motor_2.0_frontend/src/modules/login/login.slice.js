import { createSlice } from "@reduxjs/toolkit";
import service from "./serviceApi";
import { actionStructre, serializeError } from "utils";
import SecureLS from "secure-ls";

const ls = new SecureLS();
export const loginSlice = createSlice({
  name: "login",
  initialState: {
    loading: null,
    error: null,
    success: null,
    login: null,
    corpId: null,
    userId: null,
    typeAccess: [],
    removeToken: [],
    postThemeConfig: {},
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
    clear: (state, { payload }) => {
      state.loading = null;
      state.error = null;
      state.success = null;
    },
    login: (state, { payload }) => {
      state.login = false;
      state.login = payload;
    },
    setCorpId: (state, { payload }) => {
      state.corpId = payload;
    },
    setUserId: (state, { payload }) => {
      state.userId = payload;
    },
    typeAccess: (state, { payload }) => {
      state.typeAccess = payload;
    },
    removeToken: (state, { payload }) => {
      state.removeToken = payload;
    },
    postThemeConfig: (state, { payload }) => {
      state.postThemeConfig = payload;
    },
  },
});

export const {
  loading,
  success,
  error,
  clear,
  login,
  setCorpId,
  setUserId,
  typeAccess,
  removeToken,
  postThemeConfig,
} = loginSlice.actions;

export const Login = (data) => {
  return async (dispatch) => {
    try {
      const response = await service.loginApi(data);
      if (response?.data?.status) {
        ls.set("corpId", response.data?.corpId);
        ls.set("userId", response.data?.userId);
        dispatch(login(response.data?.msg));
        dispatch(setCorpId(response.data?.corpId));
        dispatch(setUserId(response.data?.userId));
      } else {
        dispatch(error(response?.data?.msg));
      }
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const TypeAccess = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, typeAccess, error, service.typeAccess, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

export const RemoveToken = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(dispatch, removeToken, error, service.remove_Token, data);
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};

// theme data
export const themeConfig = (data) => {
  return async (dispatch) => {
    try {
      actionStructre(
        dispatch,
        postThemeConfig,
        error,
        service.postThemeConfig,
        data
      );
    } catch (err) {
      dispatch(error("Something went wrong"));
      console.error("Error", err);
    }
  };
};
export const userIdentifier = [
  "paras7",
  "dipraj5",
  "nirmal3",
  "abhishek1",
];

export const authPdf = [
  "authorizeRehit"
]

export default loginSlice.reducer;
