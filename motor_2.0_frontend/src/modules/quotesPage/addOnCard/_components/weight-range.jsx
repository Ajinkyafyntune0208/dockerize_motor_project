import React from "react";
import { set_temp_data } from "modules/Home/home.slice";
import UpdateButton from "./update-btn";
import { useDispatch } from "react-redux";

const WeightRange = ({ temp_data, Theme1, register, wrange, setWrange }) => {
  const dispatch = useDispatch();

  const onSubmitRange = (selectedRange) => {
    selectedRange && dispatch(set_temp_data({ selectedGvw: selectedRange }));
  };

  return (
    <div style={{ padding: "10px 20px" }}>
      <p id="counter" style={{ fontSize: "13px" }}>
        {" "}
        {`GVW Range : (${
          temp_data?.defaultGvw * 1 - temp_data?.defaultGvw * 0.1
        }-${temp_data?.defaultGvw * 1 + temp_data?.defaultGvw * 0.1})`}
      </p>
      <input
        style={{
          marginTop: "-10px",
          accentColor: Theme1?.Stepper?.stepperColor?.background || "#bdd400",
        }}
        type="range"
        id="range"
        name="range"
        register={register}
        value={wrange || temp_data?.selectedGvw * 1}
        min={`${temp_data?.defaultGvw * 1 - temp_data?.defaultGvw * 0.1}`}
        max={`${temp_data?.defaultGvw * 1 + temp_data?.defaultGvw * 0.1}`}
        onChange={(e) => setWrange(e.target.value)}
      />
      <p id="counter" style={{ fontSize: "13px" }}>
        Selected GVW : {wrange || temp_data?.selectedGvw}
      </p>
      {((wrange && wrange * 1 !== temp_data?.selectedGvw * 1) || "") && (
        <UpdateButton
          style={{ marginTop: "-10px" }}
          id={"submitRange"}
          onClick={() => onSubmitRange(wrange || "")}
          text={"Submit"}
        />
      )}
    </div>
  );
};

export default WeightRange;
