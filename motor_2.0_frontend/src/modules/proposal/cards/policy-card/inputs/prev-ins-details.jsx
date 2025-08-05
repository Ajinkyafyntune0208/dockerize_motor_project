import { ErrorMsg } from "components";
import { FormGroupTag } from "modules/proposal/style";
import React from "react";
import { Form, Col } from "react-bootstrap";
import { StyledDatePicker } from "../policy-card";
import { Controller } from "react-hook-form";
import DateInput from "modules/proposal/DateInput";
import moment from "moment";
import { differenceInDays } from "date-fns";
import ClaimDetails from "./claim-details";
import NcbInputs from "./ncb-details";
import { toDate } from "utils";

const PreviousInsurerInputs = ({
  allFieldsReadOnly,
  register,
  errors,
  Previnsurer,
  CardData,
  temp_data,
  control,
  previousPolicyExpiry,
  isNcbApplicable,
  fields,
  prepolicy,
  watch,
}) => {
  return (
    <>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2 fname">
          <FormGroupTag mandatory>Previous Insurance Company</FormGroupTag>
          <Form.Control
            autoComplete="off"
            readOnly={allFieldsReadOnly}
            as="select"
            size="sm"
            ref={register}
            placeholder="Insurance Company"
            name={`previousInsuranceCompany`}
            style={{ cursor: "pointer" }}
            errors={errors?.previousInsuranceCompany}
            isInvalid={errors?.previousInsuranceCompany}
          >
            {!CardData?.prepolicy?.previousInsuranceCompany &&
              !temp_data?.userProposal?.previousInsurer &&
              (!temp_data?.corporateVehiclesQuoteRequest?.previousInsurer ||
                temp_data?.corporateVehiclesQuoteRequest?.previousInsurer ===
                  "NEW") && (
                <option selected={true} value={"@"}>
                  Select
                </option>
              )}
            {Previnsurer.map(({ name, code }, index) => (
              <option
                selected={
                  CardData?.prepolicy?.previousInsuranceCompany
                    ? CardData?.prepolicy?.previousInsuranceCompany === code
                    : temp_data?.corporateVehiclesQuoteRequest
                        ?.previousInsurer &&
                      temp_data?.corporateVehiclesQuoteRequest
                        ?.previousInsurer !== "XYZ"
                    ? temp_data?.corporateVehiclesQuoteRequest
                        ?.previousInsurer === name
                    : temp_data?.userProposal?.previousInsurer === name
                }
                value={code}
              >
                {name}
              </option>
            ))}
          </Form.Control>
          {!!errors?.previousInsuranceCompany && (
            <ErrorMsg fontSize={"12px"}>
              {errors?.previousInsuranceCompany?.message}
            </ErrorMsg>
          )}
        </div>
        <input type="hidden" name={"InsuranceCompanyName"} ref={register} />
      </Col>
      {temp_data?.prevShortTerm ? (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <StyledDatePicker>
            <div className="py-2 dateTimeOne">
              <FormGroupTag mandatory>Previous policy start date</FormGroupTag>
              <Controller
                control={control}
                name={`previousPolicyStartDate`}
                render={({ onChange, onBlur, value, name }) => (
                  <DateInput
                    // readOnly={allFieldsReadOnly}
                    minDate={
                      previousPolicyExpiry
                        ? moment(previousPolicyExpiry, "DD-MM-YYYY").subtract(
                            1,
                            "years"
                          )?._d
                        : false
                    }
                    maxDate={
                      moment(previousPolicyExpiry, "DD-MM-YYYY").subtract(
                        2,
                        "months"
                      )?._d
                    }
                    value={value}
                    name={name}
                    onChange={onChange}
                    ref={register}
                    errors={errors?.previousPolicyStartDate}
                  />
                )}
              />
              {!!errors?.previousPolicyStartDate && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.previousPolicyStartDate?.message}
                </ErrorMsg>
              )}
              <input
                name="previousPolicyExpiryDate"
                type="hidden"
                ref={register}
                value={previousPolicyExpiry}
              />
            </div>
          </StyledDatePicker>
        </Col>
      ) : (
        <noscript />
      )}
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <StyledDatePicker disabled={true}>
          <div className="py-2 dateTimeOne">
            <FormGroupTag mandatory>Date of expiry</FormGroupTag>
            <Controller
              control={control}
              name={`prevPolicyExpiryDate`}
              render={({ onChange, onBlur, value, name }) => (
                <DateInput
                  value={value}
                  name={name}
                  onChange={onChange}
                  ref={register}
                  readOnly
                  errors={errors?.prevPolicyExpiryDate}
                />
              )}
            />
            {!!errors?.prevPolicyExpiryDate && (
              <ErrorMsg fontSize={"12px"}>
                {errors?.prevPolicyExpiryDate?.message}
              </ErrorMsg>
            )}
            <input
              name="previousPolicyExpiryDate"
              type="hidden"
              ref={register}
              value={previousPolicyExpiry}
            />
          </div>
        </StyledDatePicker>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag mandatory>Policy Number</FormGroupTag>
          <Form.Control
            autoComplete="off"
            type="text"
            placeholder="Enter Policy Number"
            name={`previousPolicyNumber`}
            // readOnly={allFieldsReadOnly}
            ref={register}
            maxLength="50"
            onInput={(e) =>
              (e.target.value = e.target.value
                .toUpperCase()
                .replace(/[^a-zA-Z0-9-/|]/g, ""))
            }
            minlength="2"
            size="sm"
            errors={errors?.previousPolicyNumber}
            isInvalid={errors?.previousPolicyNumber}
          />
          {!!errors?.previousPolicyNumber && (
            <ErrorMsg fontSize={"12px"}>
              {errors?.previousPolicyNumber?.message}
            </ErrorMsg>
          )}
        </div>
      </Col>
      {!isNcbApplicable &&
      temp_data?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate &&
      temp_data?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate !==
        "New" &&
      fields.includes("ncb") &&
      differenceInDays(
        toDate(moment().format("DD-MM-YYYY")),
        toDate(
          temp_data?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate
        )
      ) < 90 ? (
        <ClaimDetails
          register={register}
          allFieldsReadOnly={allFieldsReadOnly}
          temp_data={temp_data}
          CardData={CardData}
          watch={watch}
          prepolicy={prepolicy}
          errors={errors}
        />
      ) : (
        /*Hidden inputs to send required values*/
        !fields.includes("ncb") &&
        temp_data?.selectedQuote?.policyType !== "Third Party" && (
          <NcbInputs register={register} temp_data={temp_data} />
        )
      )}
    </>
  );
};

export default PreviousInsurerInputs;
