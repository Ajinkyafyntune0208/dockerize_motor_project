import { ErrorMsg } from "components";
import { FormGroupTag } from "modules/proposal/style";
import React from "react";
import { Col, Form } from "react-bootstrap";

const BodyAndChassisIdv = ({
  allFieldsReadOnly,
  register,
  errors,
  temp_data,
}) => {
  return (
    <>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag>Body IDV</FormGroupTag>
          <Form.Control
            autoComplete="off"
            readOnly={allFieldsReadOnly}
            name="bodyIdv"
            ref={register}
            type="text"
            onInput={(e) =>
              (e.target.value = e.target.value.replace(/[^0-9]/gi, ""))
            }
            placeholder="Enter Body IDV"
            errors={errors?.bodyIdv}
            isInvalid={errors?.bodyIdv}
            size="sm"
          />
          {!!errors?.bodyIdv ? (
            <ErrorMsg fontSize={"12px"}>{errors?.bodyIdv?.message}</ErrorMsg>
          ) : (
            <Form.Text className="text-muted">
              <text style={{ color: "#bdbdbd" }}>
                {`Body IDV should be within ${temp_data?.selectedQuote?.minBodyIDV} and ${temp_data?.selectedQuote?.maxBodyIDV}`}
              </text>
            </Form.Text>
          )}
        </div>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag>Chassis IDV</FormGroupTag>
          <Form.Control
            autoComplete="off"
            readOnly={allFieldsReadOnly}
            name="chassisIdv"
            ref={register}
            type="text"
            onInput={(e) =>
              (e.target.value = e.target.value.replace(/[^0-9]/gi, ""))
            }
            placeholder="Enter Chassis IDV"
            errors={errors?.chassisIdv}
            isInvalid={errors?.chassisIdv}
            size="sm"
          />
          {!!errors?.chassisIdv ? (
            <ErrorMsg fontSize={"12px"}>{errors?.chassisIdv?.message}</ErrorMsg>
          ) : (
            <Form.Text className="text-muted">
              <text style={{ color: "#bdbdbd" }}>
                {`Chassis IDV should be within ${temp_data?.selectedQuote?.minChassisIDV} and ${temp_data?.selectedQuote?.maxChassisIDV}`}
              </text>
            </Form.Text>
          )}
        </div>
      </Col>
    </>
  );
};

export default BodyAndChassisIdv;
