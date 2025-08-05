import { ErrorMsg } from "components";
import { FormGroupTag } from "modules/proposal/style";
import React from "react";
import { Col, Form } from "react-bootstrap";
import { StyledDatePicker } from "../vehicle-card";
import { Controller } from "react-hook-form";
import DateInput from "modules/proposal/DateInput";
import { TypeReturn } from "modules/type";
import _ from "lodash";
import BodyAndChassisIdv from "./idv";
import { numOnly } from "utils";
import { icWithColorMandatory } from "../constants";

const VehicleDetails = ({
  temp_data,
  register,
  errors,
  regNo,
  OnGridLoad,
  regNo2,
  regNo3,
  VehicleRegNo,
  RegNo1,
  RegNo2,
  RegNo3,
  allFieldsReadOnly,
  control,
  ManfVal,
  ManfValMax,
  type,
  category,
  CardData,
  vehicle,
  usage,
  fields,
  colors,
}) => {
  return (
    <>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag mandatory>Vehicle Registration No.</FormGroupTag>
          {/*---BH Reg No.---*/}
          {temp_data?.regNo && temp_data?.regNo[0] * 1 ? (
            <div className="regGrp">
              <Form.Control
                autoComplete="off"
                type="text"
                name="regNo"
                readOnly={temp_data?.regNo}
                ref={register}
                onInput={(e) => (e.target.value = e.target.value.toUpperCase())}
                errors={errors?.vehicaleRegistrationNumber}
                size="sm"
                defaultValue={temp_data?.regNo}
                style={{ cursor: "not-allowed" }}
              />
            </div>
          ) : (
            <div className="regGrp">
              <div className="regSplit">
                <Form.Control
                  autoComplete="off"
                  type="text"
                  name="regNo1"
                  readOnly
                  ref={register}
                  onInput={(e) =>
                    (e.target.value = e.target.value.toUpperCase())
                  }
                  errors={
                    errors?.vehicaleRegistrationNumber ||
                    errors?.regNo1?.message
                  }
                  size="sm"
                  defaultValue={temp_data?.regNo1 || temp_data?.rtoNumber}
                  style={{ cursor: "not-allowed" }}
                />
              </div>
              <div className="regSplit">
                <Form.Control
                  autoComplete="off"
                  type="text"
                  name="regNo2"
                  readOnly={
                    regNo &&
                    (temp_data?.corporateVehiclesQuoteRequest
                      ?.journeyWithoutRegno === "N" ||
                      regNo === "NEW")
                  }
                  ref={register}
                  onBlur={() => OnGridLoad()}
                  onInput={(e) =>
                    (e.target.value = e.target.value
                      .replace(/[^A-Za-z0-9\s]/gi, "")
                      .toUpperCase())
                  }
                  onChange={(e) =>
                    e.target.value.length === 3
                      ? document.querySelector(`input[name=regNo3]`).focus()
                      : ""
                  }
                  errors={
                    errors?.vehicaleRegistrationNumber ||
                    errors?.regNo2?.message
                  }
                  size="sm"
                  defaultValue={regNo2}
                />
              </div>
              <div className="regSplit">
                <Form.Control
                  autoComplete="off"
                  type="text"
                  name="regNo3"
                  readOnly={
                    regNo &&
                    (temp_data?.corporateVehiclesQuoteRequest
                      ?.journeyWithoutRegno === "N" ||
                      regNo === "NEW")
                  }
                  ref={register}
                  maxLength={"4"}
                  onBlur={() => OnGridLoad()}
                  onInput={(e) =>
                    (e.target.value = e.target.value.replace(/[^0-9]/g, ""))
                  }
                  errors={
                    errors?.vehicaleRegistrationNumber ||
                    errors?.regNo3?.message
                  }
                  size="sm"
                  defaultValue={regNo3}
                  onKeyDown={numOnly}
                />
              </div>
            </div>
          )}
          {!!errors?.vehicaleRegistrationNumber ||
          errors?.regNo1 ||
          errors?.regNo2 ||
          errors?.regNo3 ? (
            <ErrorMsg fontSize={"12px"}>
              {errors?.vehicaleRegistrationNumber?.message ||
                errors?.regNo1?.message ||
                errors?.regNo2?.message ||
                errors?.regNo3?.message}
            </ErrorMsg>
          ) : (
            <Form.Text className="text-muted">
              <text style={{ color: "#bdbdbd" }}>e.g MH-04-AR-7070</text>
            </Form.Text>
          )}
          <input
            type="hidden"
            ref={register}
            name="vehicaleRegistrationNumber"
            value={
              (VehicleRegNo && VehicleRegNo[0] * 1) || ""
                ? VehicleRegNo
                : temp_data?.corporateVehiclesQuoteRequest
                    ?.vehicleRegistrationNo !== "NEW"
                ? `${RegNo1?.replace(/\s/g, "")}-${RegNo2?.replace(
                    /\s/g,
                    ""
                  )}-${RegNo3?.replace(/\s/g, "")}`
                : "NEW"
            }
          />
        </div>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag mandatory>
            {temp_data?.corporateVehiclesQuoteRequest?.fuelType === "ELECTRIC"
              ? "Motor/Battery Number"
              : "Engine Number"}
          </FormGroupTag>
          <Form.Control
            autoComplete="off"
            type="text"
            readOnly={allFieldsReadOnly}
            placeholder={
              temp_data?.corporateVehiclesQuoteRequest?.fuelType === "ELECTRIC"
                ? "Enter Motor/Battery Number"
                : "Enter Engine Number"
            }
            maxLength={"40"}
            name="engineNumber"
            minlength="5"
            ref={register}
            onInput={(e) => (e.target.value = e.target.value.toUpperCase())}
            errors={errors?.engineNumber}
            isInvalid={errors?.engineNumber}
            size="sm"
          />
          {!!errors?.engineNumber && (
            <ErrorMsg fontSize={"12px"}>
              {errors?.engineNumber?.message}
            </ErrorMsg>
          )}
        </div>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag mandatory>Chassis Number</FormGroupTag>
          <Form.Control
            autoComplete="off"
            readOnly={allFieldsReadOnly}
            name="chassisNumber"
            ref={register}
            type="text"
            onInput={(e) => (e.target.value = e.target.value.toUpperCase())}
            maxLength={"40"}
            placeholder="Enter Chassis Number"
            errors={errors?.chassisNumber}
            isInvalid={errors?.chassisNumber}
            size="sm"
          />
          {!!errors?.chassisNumber && (
            <ErrorMsg fontSize={"12px"}>
              {errors?.chassisNumber?.message}
            </ErrorMsg>
          )}
        </div>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <StyledDatePicker disabled={true}>
          <div className="py-2 dateTimeOne">
            <FormGroupTag mandatory>{"Registration Date"}</FormGroupTag>
            <Controller
              control={control}
              name="registrationDate"
              render={({ onChange, onBlur, value, name }) => (
                <DateInput
                  minDate={false}
                  value={value}
                  name={name}
                  onChange={onChange}
                  ref={register}
                  readOnly
                />
              )}
            />
          </div>
        </StyledDatePicker>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <StyledDatePicker>
          <div className="py-2 dateTimeOne">
            <FormGroupTag mandatory>{"Manufacture Month & Year"}</FormGroupTag>
            <Controller
              control={control}
              name="vehicleManfYear"
              render={({ onChange, onBlur, value, name }) => (
                <DateInput
                  minDate={ManfVal}
                  maxDate={ManfValMax}
                  value={value}
                  name={name}
                  showMonthYearPicker
                  onChange={onChange}
                  ref={register}
                  //done as per the prodoct requirement(#31316)
                  readOnly={true}
                  errors={errors?.vehicleManfYear}
                />
              )}
            />
            {!!errors?.vehicleManfYear && (
              <ErrorMsg fontSize={"12px"}>
                {errors?.vehicleManfYear?.message}
              </ErrorMsg>
            )}
          </div>
        </StyledDatePicker>
      </Col>
      {temp_data?.selectedQuote?.minBodyIDV * 1 ? (
        <BodyAndChassisIdv
          allFieldsReadOnly={allFieldsReadOnly}
          register={register}
          errors={errors}
          temp_data={temp_data}
        />
      ) : (
        <noscript />
      )}
      {(Number(temp_data?.quoteLog?.icId) === 20 ||
        temp_data?.selectedQuote?.companyAlias === "reliance") &&
        TypeReturn(type) === "cv" &&
        Number(temp_data?.productSubTypeId) === 6 &&
        temp_data?.parent?.productSubTypeCode !== "GCV" && (
          <>
            <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
              <div className="py-2 fname">
                <FormGroupTag mandatory>Vehicle Category</FormGroupTag>
                <Form.Control
                  autoComplete="off"
                  as="select"
                  size="sm"
                  readOnly={allFieldsReadOnly}
                  ref={register}
                  name="vehicleCategory"
                  className="title_list"
                  id={"vehicleCategory"}
                  isInvalid={errors?.vehicleCategory}
                >
                  {category.map(
                    ({ vehicleCategory, vehicleCategoryId }, index) => (
                      <option
                        selected={
                          Number(CardData?.vehicle?.vehicleCategory) ===
                            Number(vehicleCategoryId) ||
                          (_.isEmpty(CardData?.vehicle) &&
                            _.isEmpty(vehicle) &&
                            vehicleCategory &&
                            vehicleCategory.toLowerCase() === "taxi")
                        }
                        value={vehicleCategoryId}
                      >
                        {vehicleCategory}
                      </option>
                    )
                  )}
                </Form.Control>
                {!!errors?.vehicleCategory && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.vehicleCategory?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
            <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
              <div className="py-2 fname">
                <FormGroupTag mandatory>Vehicle Usage Type</FormGroupTag>
                <Form.Control
                  autoComplete="off"
                  as="select"
                  size="sm"
                  readOnly={allFieldsReadOnly}
                  ref={register}
                  name="vehicleUsageType"
                  className="title_list"
                  isInvalid={errors?.vehicleUsageType}
                >
                  <option selected={true} value={"@"}>
                    Select
                  </option>
                  {usage.map(
                    ({ vehicleUsageType, vehicleUsageTypeId }, index) => (
                      <option
                        selected={
                          Number(CardData?.vehicle?.vehicleUsageType) ===
                            Number(vehicleUsageTypeId) ||
                          Number(temp_data?.userProposal?.vehicleUsageType) ===
                            Number(vehicleUsageTypeId) ||
                          (_.isEmpty(CardData?.vehicle) &&
                            _.isEmpty(vehicle) &&
                            vehicleUsageType &&
                            import.meta.env.VITE_BROKER === "OLA" &&
                            vehicleUsageType.toLowerCase() === "others")
                        }
                        value={vehicleUsageTypeId}
                      >
                        {vehicleUsageType}
                      </option>
                    )
                  )}
                </Form.Control>
                {!!errors?.vehicleUsageType && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.vehicleUsageType?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
          </>
        )}
      {fields.includes("hazardousType") &&
        temp_data?.parent?.productSubTypeCode === "GCV" && (
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2 fname">
              <FormGroupTag mandatory>Hazardous Type</FormGroupTag>
              <Form.Control
                autoComplete="off"
                as="select"
                size="sm"
                readOnly={allFieldsReadOnly}
                ref={register}
                name="hazardousType"
                className="title_list"
                id={"hazardousType"}
                isInvalid={errors?.hazardousType}
              >
                <option value={"Non-Hazardous"}>Non-Hazardous</option>
                <option value={"Hazardous"}>Hazardous</option>
              </Form.Control>
              {!!errors?.hazardousType && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.hazardousType?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
        )}
      {fields.includes("vehicleColor") ? (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2">
            <FormGroupTag
              mandatory={
                (temp_data?.selectedQuote?.companyAlias === "sbi" &&
                  TypeReturn(type) !== "cv") ||
                icWithColorMandatory.includes(
                  temp_data?.selectedQuote?.companyAlias
                )
              }
            >{`Vehicle Color`}</FormGroupTag>
            {["sbi", "universal_sompo", "new_india", "nic"].includes(
              temp_data?.selectedQuote?.companyAlias
            ) ? (
              <div className="fname">
                <Form.Control
                  as="select"
                  size="sm"
                  ref={register}
                  name={`vehicleColor`}
                  errors={errors?.vehicleColor}
                  isInvalid={errors?.vehicleColor}
                  style={{ cursor: "pointer" }}
                >
                  <option selected={true} value={"@"}>
                    Select
                  </option>

                  {colors?.map((color, index) => (
                    <option
                      selected={
                        CardData?.vehicle?.vehicleColor === color ||
                        temp_data?.userProposal?.vehicleColor === color
                      }
                      value={color}
                    >
                      {color}
                    </option>
                  ))}
                </Form.Control>
              </div>
            ) : (
              <Form.Control
                autoComplete="off"
                type="text"
                placeholder="Enter Vehicle Color"
                size="sm"
                name="vehicleColor"
                onInput={(e) =>
                  (e.target.value = ("" + e.target.value).toUpperCase())
                }
                maxLength="50"
                ref={register}
                errors={errors?.vehicleColor}
                isInvalid={errors?.vehicleColor}
              />
            )}
            {!!errors?.vehicleColor && (
              <ErrorMsg fontSize={"12px"}>
                {errors?.vehicleColor?.message}
              </ErrorMsg>
            )}
          </div>
        </Col>
      ) : (
        <noscript />
      )}
    </>
  );
};

export default VehicleDetails;
