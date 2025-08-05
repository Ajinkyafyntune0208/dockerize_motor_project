import React from "react";
import { currencyFormater } from "utils";

const IdvPopupInput = ({ register, data, idvType, spanData, idvval }) => {
  return (
    <label className="panel-heading ratioButton IDVRatio">
      <input
        type="radio"
        className="idvInputClass"
        name="idvType"
        ref={register}
        value={idvType}
        defaultChecked={data?.idvType === idvType}
      />
      <span className="checkmark"></span>
      <span
        className={`checkBoxTextIdv ${
          spanData === idvType ? "txtCheckedBold" : ""
        }`}
      >
        {idvType === "ownIDV"
          ? "Choose your own IDV"
          : idvType === "lowIdv"
          ? `Lowest IDV (starting from): ₹ ${currencyFormater(idvval)}`
          : `Highest IDV : ₹ ${currencyFormater(idvval)}`}
      </span>
    </label>
  );
};
export default IdvPopupInput;
