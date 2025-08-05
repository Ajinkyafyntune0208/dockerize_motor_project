import { ErrorMsg } from "components";
import { FormGroupTag } from "modules/proposal/style";
import React from "react";
import { Form, Col } from "react-bootstrap";
import _ from "lodash";
import { ToggleElem } from "../helper";
import { numOnly } from "utils";

const VehicleRegistrationAddress = ({
  allFieldsReadOnly,
  watch,
  Theme,
  register,
  errors,
  pin,
  CardData,
  temp_data,
  vehicle,
  lessthan376,
}) => {
  return (
    <>
      {ToggleElem(
        "isCarRegistrationAddressSame",
        "Is your Vehicle registration address same as communication address?",
        true,
        allFieldsReadOnly ? allFieldsReadOnly : null,
        allFieldsReadOnly ? allFieldsReadOnly : null,
        Theme,
        register,
        allFieldsReadOnly,
        lessthan376
      )}
      {!watch("isCarRegistrationAddressSame") && (
        <>
          <Col
            xs={12}
            sm={12}
            md={12}
            lg={12}
            xl={12}
            className=" mt-1"
            style={{ marginBottom: "-10px" }}
          >
            <p
              style={{
                color:
                  import.meta.env.VITE_BROKER !== "OLA"
                    ? "black"
                    : Theme?.proposalHeader?.color
                    ? Theme?.proposalHeader?.color
                    : "#1a5105",
                fontSize: "16px",
                fontWeight: "600",
              }}
            >
              Vehicle Registration Address
            </p>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>Address Line 1</FormGroupTag>
              <Form.Control
                autoComplete="off"
                type="text"
                placeholder="Address Line 1"
                name="carRegistrationAddress1"
                maxLength="50"
                minlength="2"
                ref={register}
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value)
                          .toUpperCase()
                          .replace(
                            /[^A-Za-z 0-9 \.,\?""!@#\$%\^&\*\(\)-_=\+;:<>\/\\\|\}\{\[\]`~]*/g,
                            ""
                          )
                      : e.target.value)
                }
                errors={errors?.carRegistrationAddress1}
                isInvalid={errors?.carRegistrationAddress1}
                size="sm"
              />
              {!!errors?.carRegistrationAddress1 && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.carRegistrationAddress1?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>Address Line 2</FormGroupTag>
              <Form.Control
                autoComplete="off"
                type="text"
                // readOnly={allFieldsReadOnly}
                placeholder="Address Line 2"
                name="carRegistrationAddress2"
                maxLength="50"
                minlength="2"
                ref={register}
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value)
                          .toUpperCase()
                          .replace(
                            /[^A-Za-z 0-9 \.,\?""!@#\$%\^&\*\(\)-_=\+;:<>\/\\\|\}\{\[\]`~]*/g,
                            ""
                          )
                      : e.target.value)
                }
                errors={errors?.carRegistrationAddress2}
                isInvalid={errors?.carRegistrationAddress2}
                size="sm"
              />
              {!!errors?.carRegistrationAddress2 && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.carRegistrationAddress2?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag>Address Line 3</FormGroupTag>
              <Form.Control
                autoComplete="off"
                type="text"
                placeholder="Address Line 3"
                name="carRegistrationAddress3"
                maxLength="50"
                // readOnly={allFieldsReadOnly}
                minlength="2"
                ref={register}
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value)
                          .toUpperCase()
                          .replace(
                            /[^A-Za-z 0-9 \.,\?""!@#\$%\^&\*\(\)-_=\+;:<>\/\\\|\}\{\[\]`~]*/g,
                            ""
                          )
                      : e.target.value)
                }
                errors={errors?.carRegistrationAddress3}
                isInvalid={errors?.carRegistrationAddress3}
                size="sm"
              />
              {!!errors?.carRegistrationAddress3 && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.carRegistrationAddress3?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>Pincode</FormGroupTag>
              <Form.Control
                autoComplete="off"
                name="carRegistrationPincode"
                ref={register}
                type="tel"
                // readOnly={allFieldsReadOnly}
                placeholder="Pincode"
                errors={errors?.carRegistrationPincode}
                isInvalid={errors?.carRegistrationPincode}
                size="sm"
                onKeyDown={numOnly}
                maxLength="6"
              />
              {!!errors?.carRegistrationPincode && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.carRegistrationPincode?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>State</FormGroupTag>
              <Form.Control
                name="carRegistrationState"
                ref={register}
                type="text"
                placeholder="State"
                errors={errors?.carRegistrationState}
                isInvalid={errors?.carRegistrationState}
                size="sm"
                readOnly
                style={{ cursor: "not-allowed" }}
              />
              {!!errors?.carRegistrationState && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.carRegistrationState?.message}
                </ErrorMsg>
              )}
            </div>
            <input
              name="carRegistrationStateId"
              ref={register}
              type="hidden"
              value={pin?.state?.state_id}
            />
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2 fname">
              <FormGroupTag mandatory>City</FormGroupTag>
              <Form.Control
                as="select"
                size="sm"
                // readOnly={allFieldsReadOnly}
                ref={register}
                name={`carRegistrationCity`}
                errors={errors?.carRegistrationCity}
                isInvalid={errors?.carRegistrationCity}
                style={{ cursor: "pointer" }}
              >
                <option selected value={"@"}>
                  Select
                </option>
                {pin?.city?.map(({ city_name, city_id }, index) => (
                  <option
                    selected={
                      CardData?.vehicle?.carRegistrationCity?.trim() ===
                        city_name?.trim() ||
                      (pin?.city?.length === 1 &&
                        !CardData?.vehicle?.carRegistrationCity?.trim()) ||
                      (_.isEmpty(CardData?.vehicle) &&
                        _.isEmpty(vehicle) &&
                        temp_data?.userProposal?.carRegistrationCity &&
                        temp_data?.userProposal?.carRegistrationCity.trim() ===
                          city_name?.trim())
                    }
                    value={city_name}
                  >
                    {city_name}
                  </option>
                ))}
              </Form.Control>
              {!!errors?.carRegistrationCity && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.carRegistrationCity?.message}
                </ErrorMsg>
              )}
            </div>
            <input name="carRegistrationCityId" ref={register} type="hidden" />
          </Col>
        </>
      )}
    </>
  );
};

export default VehicleRegistrationAddress;
