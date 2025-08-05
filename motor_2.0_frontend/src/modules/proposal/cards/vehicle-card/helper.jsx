import React from "react";
import { Col } from "react-bootstrap";
import { TopDiv } from "./vehicle-card";
import _ from "lodash";
import { subMonths } from 'date-fns'
import { _haptics } from "utils";
import swal from "sweetalert";

export const ToggleElem = (
  name,
  content,
  defaultChecked,
  readOnly,
  checked,
  Theme,
  register,
  allFieldsReadOnly,
  lessthan376
) => (
  <Col xs={12} sm={12} md={12} lg={12} xl={12} className="">
    <TopDiv>
      <div className="toggleRadio m-0" id={name}>
        <label
          className="selectLabel ml-0"
          style={{
            color: Theme?.questionsProposal?.color
              ? Theme?.questionsProposal?.color
              : "#00CC00",
            letterSpacing: "1px",
            fontSize: lessthan376 ? "11px" : "12px",
          }}
        >
          {content}
        </label>
        <label className="selectLabel ml-0">No</label>
        <label
          toggleBackgroundColor={
            Theme?.questionsProposal?.toggleBackgroundColor
          }
          className="switch yes-switch"
        >
          <input
            name={name}
            type="checkbox"
            id={name}
            className="min-y"
            checked={checked}
            defaultChecked={defaultChecked}
            ref={register}
            readOnly={readOnly || allFieldsReadOnly}
          />
          <span className="slider round"></span>
        </label>
        <label className="selectLabel">Yes</label>
      </div>
    </TopDiv>
  </Col>
);

export const getDefaultVehicleValues = (
  temp_data,
  vehicle,
  regNo,
  CardData
) => {
  if (!_.isEmpty(vehicle)) {
    if (
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo[0] * 1
    ) {
      return { ...vehicle };
    } else if (
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo === "NEW"
    ) {
      return vehicle?.regNo1 ===
        temp_data?.corporateVehiclesQuoteRequest?.rtoCode
        ? { ...vehicle }
        : {
            ...vehicle,
            regNo1: temp_data?.corporateVehiclesQuoteRequest?.rtoCode,
          };
    } else if (vehicle?.vehicaleRegistrationNumber === regNo) {
      return {
        ...vehicle,
        registrationDate:
          temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate ||
          temp_data?.quoteLog?.quoteDetails?.vehicleRegisterDate,
      };
    } else {
      return {
        ...vehicle,
        vehicaleRegistrationNumber: regNo,
        regNo1: temp_data?.regNo1 || temp_data?.rtoNumber,
        regNo2:
          temp_data?.regNo2 ||
          (regNo !== "NEW" && vehicle?.regNo2 ? vehicle?.regNo2 : ""),
        regNo3:
          temp_data?.regNo3 ||
          (regNo !== "NEW" && vehicle?.regNo3 ? vehicle?.regNo3 : ""),
        registrationDate:
          temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate ||
          temp_data?.quoteLog?.quoteDetails?.vehicleRegisterDate,
      };
    }
  } else if (!_.isEmpty(CardData.vehicle)) {
    if (
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo &&
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo[0] * 1
    ) {
      return { ...CardData?.vehicle };
    } else if (
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegistrationNo === "NEW"
    ) {
      return vehicle?.regNo1 ===
        temp_data?.corporateVehiclesQuoteRequest?.rtoCode
        ? { ...CardData?.vehicle }
        : {
            ...CardData?.vehicle,
            regNo1: temp_data?.corporateVehiclesQuoteRequest?.rtoCode,
          };
    } else if (CardData?.vehicle?.vehicaleRegistrationNumber === regNo) {
      return {
        ...CardData?.vehicle,
        registrationDate:
          temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate ||
          temp_data?.quoteLog?.quoteDetails?.vehicleRegisterDate,
      };
    } else {
      return {
        ...CardData?.vehicle,
        vehicaleRegistrationNumber: regNo,
        regNo1: temp_data?.regNo1 || temp_data?.rtoNumber,
        regNo2:
          temp_data?.regNo2 ||
          (regNo !== "NEW" && CardData?.vehicle?.regNo2
            ? CardData?.vehicle?.regNo2
            : ""),
        regNo3:
          temp_data?.regNo3 ||
          (regNo !== "NEW" && CardData?.vehicle?.regNo3
            ? CardData?.vehicle?.regNo3
            : ""),
        registrationDate:
          temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate ||
          temp_data?.quoteLog?.quoteDetails?.vehicleRegisterDate,
      };
    }
  }

  return {};
};

export const _dateConfig = (temp_data) => {
  const ManfVal =
    !_.isEmpty(temp_data) &&
    subMonths(
      new Date(
        temp_data?.manfDate.split("-")[1],
        temp_data?.manfDate.split("-")[0] * 1 - 1,
        "01"
      ),
      15
    );
  const ManfValMax =
    !_.isEmpty(temp_data) &&
    new Date(
      temp_data?.manfDate.split("-")[1],
      temp_data?.manfDate.split("-")[0] * 1 - 1,
      "01"
    );

    return { ManfVal, ManfValMax }
}

export const handleIDVChange = (idvChange) => {
  if (idvChange) {
    swal(
      "Please Note",
      "The customization of your body or chassis IDV could potentially lead to an adjustment in your premium amount.",
      "info"
    );
    _haptics([100, 0, 50]);
  } else {
    _haptics([100, 0, 50]);
  }
};

