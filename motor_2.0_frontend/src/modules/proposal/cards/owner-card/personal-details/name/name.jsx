import React from "react";
import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "../../../../style";
import { useNameSplitting, useResetOnOwnertypeChange } from "./name-hooks";
import { ErrorMsg } from "components";
import PropTypes from "prop-types";

const Name = ({
  temp_data,
  register,
  errors,
  resubmit,
  watch,
  fields,
  allFieldsReadOnly,
  verifiedData,
  fieldsNonEditable,
  setValue,
  CardData,
}) => {
  const FullName = watch("fullName");
  const prevOwnerType = watch("prevOwnerType");

  useNameSplitting(FullName, setValue, temp_data);
  useResetOnOwnertypeChange(temp_data, CardData, prevOwnerType, setValue);
  return (
    <>
      {Number(temp_data?.ownerTypeId) === 1 ? (
        <>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>{"Full Name"}</FormGroupTag>
              <Form.Control
                autoComplete="none"
                type="text"
                placeholder={"Enter Full Name"}
                name="fullName"
                maxLength="100"
                minlength="1"
                ref={register}
                readOnly={
                  (allFieldsReadOnly ||
                    (resubmit && verifiedData?.includes("fullName")) ||
                    (watch("fullName") && fieldsNonEditable)) &&
                  temp_data?.selectedQuote?.companyAlias !== "godigit"
                }
                onBlur={(e) => (e.target.value = e.target.value.trim())}
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value)
                          .toUpperCase()
                          .replace(/\s\s/gi, " ")
                      : e.target.value
                          .replace("..", ".")
                          .replace(/\s\s/gi, " "))
                }
                isInvalid={
                  errors?.fullName || errors?.firstName || errors?.lastName
                }
                size="sm"
              />
              {errors?.fullName || errors?.firstName || errors?.lastName ? (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.fullName?.message ||
                    errors?.firstName?.message ||
                    errors?.lastName?.message}
                </ErrorMsg>
              ) : (
                <Form.Text className="text-muted">
                  <text style={{ color: "#bdbdbd" }}>
                    (Full Name as mentioned in RC copy)
                  </text>
                </Form.Text>
              )}
            </div>
            <input type="hidden" ref={register} name="firstName" />
            <input type="hidden" ref={register} name="lastName" />
          </Col>
        </>
      ) : (
        <>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>{"Company Name"}</FormGroupTag>
              <Form.Control
                autoComplete="none"
                type="text"
                allFieldsReadOnly={allFieldsReadOnly}
                placeholder={"Enter Company Name"}
                name="firstName"
                maxLength="100"
                minlength="2"
                ref={register}
                readOnly={
                  (allFieldsReadOnly ||
                    (resubmit && verifiedData?.includes("firstName")) ||
                    (watch("firstName") && fieldsNonEditable)) && 
                  temp_data?.selectedQuote?.companyAlias !== "future_generali"  
                }
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value).toUpperCase()
                      : e.target.value)
                }
                isInvalid={errors?.firstName}
                size="sm"
              />
              {!!errors?.firstName && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.firstName?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
          {fields.includes("representativeName") && (
            <Col xs={12} sm={12} md={12} lg={6} xl={4} className="w-100">
              <div className="py-2 w-100">
                <FormGroupTag mandatory>{"Representative Name"}</FormGroupTag>
                <div className="d-flex w-100 fname">
                  <div
                    style={{ maxWidth: "100%", width: "100%" }}
                    className="fname1"
                  >
                    <Form.Control
                      ref={register}
                      errors={errors.lastName}
                      isInvalid={errors.lastName}
                      autoComplete="none"
                      name="lastName"
                      allFieldsReadOnly={allFieldsReadOnly}
                      type="text"
                      onInput={(e) =>
                        (e.target.value =
                          e.target.value.length <= 1
                            ? ("" + e.target.value).toUpperCase()
                            : e.target.value)
                      }
                      maxLength="100"
                      placeholder={"Enter Name"}
                      size="sm"
                    />
                    {!!errors?.lastName && (
                      <ErrorMsg fontSize={"12px"}>
                        {errors?.lastName?.message}
                      </ErrorMsg>
                    )}
                  </div>
                </div>
              </div>
            </Col>
          )}
        </>
      )}
      {/*Tag to keep track of the previous owner type*/}
      <input
        type="hidden"
        name={"prevOwnerType"}
        value={temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType}
        ref={register}
      />
    </>
  );
};

Name.propTypes = {
  temp_data: PropTypes.object,
  register: PropTypes.func,
  errors: PropTypes.object,
  resubmit: PropTypes.bool,
  watch: PropTypes.func,
  fields: PropTypes.arrayOf(PropTypes.string),
  allFieldsReadOnly: PropTypes.bool,
  verifiedData: PropTypes.arrayOf(PropTypes.string),
  ErrorMsg: PropTypes.elementType,
  fieldsNonEditable: PropTypes.bool,
  setValue: PropTypes.func,
  CardData: PropTypes.object,
};

export default Name;
