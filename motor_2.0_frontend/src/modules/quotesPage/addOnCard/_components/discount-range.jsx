import React from "react";
import { InputFieldSmall } from "../style";
import { Form } from "react-bootstrap";
import { set_temp_data } from "modules/Home/home.slice";
import {
  CancelAll,
  SaveAddonsData,
  SetaddonsAndOthers,
} from "modules/quotesPage/quote.slice";
import UpdateButton from "./update-btn";
import { useDispatch } from "react-redux";

const DiscountRange = ({
  temp_data,
  Theme1,
  register,
  drange,
  setDrange,
  enquiry_id,
}) => {
  const dispatch = useDispatch();

  const onSubmitDiscountRange = (selectedRange) => {
    dispatch(set_temp_data({ selectedDiscount: selectedRange }));
    dispatch(
      SaveAddonsData({
        enquiryId: enquiry_id,
        addonData: {
          agent_discount: {
            max: temp_data?.discounts?.max,
            selected: selectedRange,
          },
        },
      })
    );
    // resetting cancel all apis loading so quotes will restart (quotes apis)
    dispatch(CancelAll(false));
    dispatch(
      SetaddonsAndOthers({
        agent_discount: {
          max: temp_data?.discounts?.max,
          selected: selectedRange,
        },
      })
    );
  };

  return (
    <div style={{ padding: "10px 20px" }}>
      <p id="counter" style={{ fontSize: "13px" }}>
        {`Discount % : (${0}-${temp_data?.discounts?.max})`}
      </p>
      <InputFieldSmall fullWidth>
        <input
          style={{
            marginTop: "-10px",
            accentColor: Theme1?.Stepper?.stepperColor?.background || "#bdd400",
          }}
          type="range"
          id="discount_range"
          name="discount_range"
          register={register}
          value={drange}
          min={temp_data?.discounts?.min * 1}
          max={temp_data?.discounts?.max * 1}
          onChange={(e) => setDrange(e.target.value)}
        />
        <Form.Control
          type="text"
          value={drange}
          onKeyDown={(e) => {
            if (e.key === "Backspace" && drange === "0") {
              e.preventDefault();
            }
          }}
          onChange={(e) => {
            let value = e.target.value;
            if (value === "") {
              value = "0";
            } else if (/^[0-9]*$/.test(value)) {
              value = parseInt(value, 10);
              if (value > 100) {
                value = 100;
              }
            } else {
              value = drange;
            }
            setDrange(value.toString());
          }}
        />
      </InputFieldSmall>
      <p id="counter" style={{ fontSize: "13px" }}>
        Selected Discount % : {drange}
      </p>
      {drange && drange * 1 !== temp_data?.selectedDiscount * 1 ? (
        <UpdateButton
          style={{ marginTop: "-10px" }}
          id={"submitRange"}
          onClick={() => onSubmitDiscountRange(drange || "")}
          text={"Submit"}
        />
      ) : (
        <noscript />
      )}
    </div>
  );
};

export default DiscountRange;
