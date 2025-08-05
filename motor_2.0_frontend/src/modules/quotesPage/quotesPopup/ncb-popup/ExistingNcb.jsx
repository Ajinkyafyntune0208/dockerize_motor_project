import React from "react";
import { EligText } from "./styles";

const ExistingNcb = ({
  temp_data,
  myOrderedNcbList,
  ncbValue,
  register,
  lessthan767,
  getNewNcb
}) => {
  return (
    <>
      <div
        className="popupSubHead ncsSubHeadNo"
        style={{
          display: "block",
          marginBottom: "12px",
          visibility:
            temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" &&
            !temp_data?.renewalAttributes?.ncb
              ? "hidden"
              : "visible",
        }}
      >
        {temp_data?.newCar
          ? "Select your Transfered No Claim Bonus (NCB)"
          : "Please select your existing NCB"}
      </div>
      <div
        className="vehRadioWrap ncsPercentCheck"
        style={{
          display: lessthan767 ? "flex" : "block",
          marginBottom: "12px",
          justifyContent: "space-between",
        }}
      >
        {(temp_data?.corporateVehiclesQuoteRequest?.isRenewal !== "Y" ||
          temp_data?.renewalAttributes?.ncb) &&
          myOrderedNcbList.map((item, index) => (
            <>
              <input
                type="radio"
                id={item?.ncbId}
                name="existinNcb"
                value={`${item?.discountRate}%`}
                ref={register}
                defaultChecked={temp_data?.ncb === `${item?.discountRate}%`}
              />
              <label for={item?.ncbId} style={{ cursor: "pointer" }}>
                {item?.discountRate}%
              </label>
            </>
          ))}
      </div>

      <EligText>
        <div>
          Your new NCB is set to{" "}
          {(ncbValue || temp_data?.ncb) && temp_data?.prevShortTerm * 1
            ? ncbValue || temp_data?.ncb
            : getNewNcb(ncbValue || temp_data?.ncb)}
        </div>
      </EligText>
    </>
  );
};

export default ExistingNcb;
