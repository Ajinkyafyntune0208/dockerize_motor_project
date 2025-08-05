import { createSlice } from "@reduxjs/toolkit";
import service from "./serviceApi";
import { serializeError, actionStructre, actionStructreBoth } from "utils";

export const inspectionSlice = createSlice({
	name: "inspection",
	initialState: {
		loading: false,
		error: null,
		success: null,
		vehicleType: [],
		submit: null,
		prevIc: [],
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
		},
		clear: (state, { payload }) => {
			state.error = null;
			state.success = null;
			state.payment = null;
			switch (payload) {
				case "submit":
					state.submit = null;
					break;
				default:
					break;
			}
		},
		vehicleType: (state, { payload }) => {
			state.loading = null;
			state.vehicleType = payload;
		},
		submit: (state, { payload }) => {
			state.loading = null;
			state.submit = payload;
		},
		prevIc: (state, { payload }) => {
			state.loading = null;
			state.prevIc = payload;
		},
	},
});

export const { loading, success, error, clear, vehicleType, submit, prevIc } =
	inspectionSlice.actions;

// Action creator

export const VehicleType = (data) => {
	return async (dispatch) => {
		try {
			dispatch(loading());
			actionStructre(dispatch, vehicleType, error, service.vehicleType, data);
		} catch (err) {
			dispatch(error("Something went wrong"));
			console.error("Error", err);
		}
	};
};

export const SubmitData = (data) => {
	return async (dispatch) => {
		try {
			dispatch(loading());
			actionStructreBoth(dispatch, submit, error, service.submit, data);
		} catch (err) {
			dispatch(error("Something went wrong"));
			console.error("Error", err);
		}
	};
};

export const PrevIc = (data) => {
	return async (dispatch) => {
		try {
			dispatch(loading());
			actionStructre(dispatch, prevIc, error, service.prevIc, data);
		} catch (err) {
			dispatch(error("Something went wrong"));
			console.error("Error", err);
		}
	};
};

export default inspectionSlice.reducer;
