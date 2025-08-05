import React from "react";
import { Row, Col } from "react-bootstrap";
import { ListDiv, ListItem, StyledP } from "./style";
import { GradientPicker, NoOfSteppers } from "./helper";

const Stepper = ({ TypeReturn, type, temp_data, Step }) => {
  return (
    <Row
      className="w-100 mx-auto my-4 d-flex justify-content-center"
      style={{ zIndex: "999", position: "relative" }}
      id="stepper"
    >
      <Col
        sm="12"
        md="12"
        lg="12"
        xl="12"
        className="p-0 my-0 m-0 w-100 mx-auto"
      >
        <ListDiv className="wrapper-progressBar">
          <ul className="progressBar">
            <ListItem
              gradient={Step > 0 && GradientPicker("0")}
              className={
                Step > 0
                  ? `active ${NoOfSteppers(temp_data, type)}`
                  : `${NoOfSteppers(temp_data, type)}`
              }
            >
              <StyledP className="px-0 mx-0 py-1">Brand</StyledP>
            </ListItem>
            <ListItem
              gradient={Step > 1 && GradientPicker("1")}
              className={
                Step > 1
                  ? `active ${NoOfSteppers(temp_data, type)}`
                  : `${NoOfSteppers(temp_data, type)}`
              }
            >
              <StyledP className="px-0 mx-0 py-1">Model</StyledP>
            </ListItem>
            {TypeReturn(type) !== "bike" && (
              <ListItem
                gradient={Step > 2 && GradientPicker("2")}
                className={
                  Step > 2
                    ? `active ${NoOfSteppers(temp_data, type)}`
                    : `${NoOfSteppers(temp_data, type)}`
                }
              >
                <StyledP className="px-0 mx-0 py-1">Fuel Type</StyledP>
              </ListItem>
            )}
            <ListItem
              gradient={Step > 3 && GradientPicker("3")}
              className={
                Step > 3
                  ? `active ${NoOfSteppers(temp_data, type)}`
                  : `${NoOfSteppers(temp_data, type)}`
              }
            >
              <StyledP className="px-0 mx-0 py-1">Variant</StyledP>
            </ListItem>
            {Number(temp_data?.journeyType) !== 1 ||
            (Number(temp_data?.journeyType) === 1 &&
              temp_data?.regNo &&
              temp_data?.regNo[0] * 1) ? (
              <ListItem
                gradient={Step > 4 && GradientPicker("4")}
                className={
                  Step > 4
                    ? `active ${NoOfSteppers(temp_data, type)}`
                    : `${NoOfSteppers(temp_data, type)}`
                }
              >
                <StyledP className="px-0 mx-0 py-1">RTO</StyledP>
              </ListItem>
            ) : (
              <noscript />
            )}
            {Number(temp_data?.journeyType) !== 3 &&
              temp_data?.regNo !== "NEW" && (
                <ListItem
                  gradient={Step > 5 && GradientPicker("5")}
                  className={
                    Step > 5
                      ? `active ${NoOfSteppers(temp_data, type)}`
                      : `${NoOfSteppers(temp_data, type)}`
                  }
                >
                  <StyledP className="px-0 mx-0 py-1">Invoice Year</StyledP>
                </ListItem>
              )}
          </ul>
        </ListDiv>
      </Col>
    </Row>
  );
};

export default Stepper;
