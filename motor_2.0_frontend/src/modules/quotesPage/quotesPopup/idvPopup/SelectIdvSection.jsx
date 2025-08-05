import React from "react";
import { Form } from "react-bootstrap";
import { InputFieldSmall } from "./IdvPopupStyle";

//prettier-ignore
const SelectIdvSection = ({
  register, SelectedIdv, idvSelected, tempData,
  getLowestIdv, getHighestIdv, currencyFormater,
  idvError, errors, numOnly, handleInputChange,
  acronymCurrency, availQuotesInRange,
}) => {
  return (
    <>
      <div className="paymentTermRadioWrap">
        <label className="panel-heading ratioButton IDVRatio">
          <input
            type="radio"
            className="idvInputClass"
            name="idvType"
            ref={register}
            value="lowIdv"
            defaultChecked={
              idvSelected === "min_idv"
                ? true
                : tempData?.idvType === "lowIdv"
                ? true
                : false
            }
          />
          <span className="checkmark"></span>
          <span
            className={`checkBoxTextIdv ${
              SelectedIdv === `lowIdv` ? "txtCheckedBold" : ""
            }`}
          >
            Lowest IDV (starting from) : ₹ {currencyFormater(getLowestIdv())}
          </span>
        </label>
      </div>

      <div className="paymentTermRadioWrap">
        <label className="panel-heading ratioButton IDVRatio">
          <input
            type="radio"
            className="idvInputClass"
            name="idvType"
            ref={register}
            value="highIdv"
            defaultChecked={
              idvSelected === "max_idv"
                ? true
                : tempData?.idvType === "highIdv"
                ? true
                : false
            }
          />
          <span className="checkmark"></span>
          <span
            className={`checkBoxTextIdv ${
              SelectedIdv === `highIdv` ? "txtCheckedBold" : ""
            }`}
          >
            Highest IDV: ₹ {currencyFormater(getHighestIdv())}
          </span>
        </label>
      </div>
      <div className="paymentTermRadioWrap">
        <label className="panel-heading ratioButton IDVRatio">
          <input
            type="radio"
            className="idvInputClass"
            name="idvType"
            ref={register}
            value="ownIDV"
            defaultChecked={tempData?.idvType === "ownIDV" ? true : false}
          />
          <span className="checkmark"></span>
          <span
            className={`checkBoxTextIdv ${
              SelectedIdv === "ownIDV" ? "txtCheckedBold" : ""
            }`}
          >
            Choose your own IDV
          </span>
        </label>
        {(SelectedIdv === "ownIDV" || tempData?.idvType === "ownIDV") && (
          <div className="paymentTermRadioContent">
            <InputFieldSmall idvError={idvError}>
              <Form.Control
                type="number"
                placeholder="Eg. ₹ 11,21,280"
                name="customIdv"
                maxLength="9"
                defaultValue={tempData?.idvType ? tempData?.idvChoosed : ""}
                minlength="2"
                ref={register}
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? "" + e.target.value
                      : e.target.value)
                }
                errors={errors.customIdv}
                size="sm"
                onKeyDown={numOnly}
                onChange={handleInputChange}
              />
              <div className="acronymCurrency">{acronymCurrency || "0.00"}</div>
              <div className="quotesInIDVRange">
                <p>
                  <span className="badgeBack">
                    {availQuotesInRange} Insurers
                  </span>
                  offer a quote for the selected IDV
                </p>
              </div>
            </InputFieldSmall>
            <div className="idvCustmTxt">
              Enter value between ₹ {getLowestIdv()} and ₹ {getHighestIdv()}
            </div>
          </div>
        )}
      </div>
    </>
  );
};

export default SelectIdvSection;
