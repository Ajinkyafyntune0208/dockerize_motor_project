import { ErrorMsg } from "components";
import { FormGroupTag } from "modules/proposal/style";
import React from "react";
import { Form, Col } from "react-bootstrap";
import { StyledDatePicker } from "../policy-card";
import { Controller } from "react-hook-form";
import DateInput from "modules/proposal/DateInput";
import { differenceInDays, subDays, subYears } from "date-fns";
import moment from "moment";
import { SubTitleFn } from "../helper";
import { toDate } from "utils";

const TpDetails = ({
  PolicyValidationExculsion,
  Theme,
  temp_data,
  register,
  watch,
  allFieldsReadOnly,
  errors,
  CardData,
  PrevinsurerTp,
  control,
  ODlastYr,
  TPStartDate,
  prepolicy,
}) => {
  //TP policy Number Read Only Condition
  const TpReadOnly =
    (ODlastYr && !PolicyValidationExculsion) || allFieldsReadOnly;

  const getMinDate = () => {
    const policyExpiryDate =
      temp_data?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate;
    const registerDate =
      temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate;

    if (ODlastYr && policyExpiryDate) {
      return subYears(subDays(new Date(policyExpiryDate), 30), 1);
    }

    if (registerDate) {
      return subDays(new Date(registerDate), 365);
    }

    return new Date("2018-08-01");
  };

  const getSelectedDate = () => {
    const policyExpiryDate =
      temp_data?.corporateVehiclesQuoteRequest?.previousPolicyExpiryDate;

    if (!TPStartDate && ODlastYr && policyExpiryDate) {
      return moment(policyExpiryDate, "DD-MM-YYYY")
        .subtract(1, "years")
        .add(1, "days")
        .toDate();
    }

    return TPStartDate ||
      prepolicy?.tpStartDate ||
      CardData?.prepolicy?.tpStartDate
      ? toDate(
          TPStartDate ||
            prepolicy?.tpStartDate ||
            CardData?.prepolicy?.tpStartDate
        )
      : false;
  };

  return (
    <>
      {!PolicyValidationExculsion && SubTitleFn(Theme, "TP Details")}
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2 fname">
          <FormGroupTag mandatory>TP Insurance Company</FormGroupTag>
          {differenceInDays(
            toDate(moment().format("DD-MM-YYYY")),
            toDate(
              temp_data?.corporateVehiclesQuoteRequest?.vehicleRegisterDate
            ) <= 365
          ) &&
          temp_data?.corporateVehiclesQuoteRequest?.previousPolicyType !==
            "Not sure" ? (
            <>
              <Form.Control
                type="text"
                autoComplete="off"
                // placeholder="Enter TP Policy Number"
                name={`disabled_id`}
                ref={register}
                disabled={true}
                size="sm"
              />
              <input
                type="hidden"
                ref={register}
                value={watch("previousInsuranceCompany")}
                name="tpInsuranceCompany"
              />
            </>
          ) : (
            (
              <Form.Control
                autoComplete="off"
                as="select"
                size="sm"
                ref={register}
                placeholder="TP Insurance Company"
                name={`tpInsuranceCompany`}
                readOnly={allFieldsReadOnly}
                style={{ cursor: "pointer" }}
                errors={errors?.tpInsuranceCompany}
                isInvalid={errors?.tpInsuranceCompany}
              >
                {(!temp_data?.corporateVehiclesQuoteRequest?.previousInsurer ||
                  temp_data?.corporateVehiclesQuoteRequest?.previousInsurer ===
                    "NEW" ||
                  "s") &&
                  !CardData?.prepolicy?.tpInsuranceCompany &&
                  (temp_data?.userProposal?.tpInsuranceCompany || "") && (
                    <option selected={true} value={"@"}>
                      Select
                    </option>
                  )}
                {PrevinsurerTp.map(({ name, code }, index) => (
                  <option
                    selected={
                      CardData?.prepolicy?.tpInsuranceCompany
                        ? CardData?.prepolicy?.tpInsuranceCompany === code
                        : temp_data?.userProposal?.tpInsuranceCompany
                        ? temp_data?.userProposal?.tpInsuranceCompany === name
                        : temp_data?.corporateVehiclesQuoteRequest
                            ?.previousInsurer === name
                    }
                    value={code}
                  >
                    {name}
                  </option>
                ))}
              </Form.Control>
            ) || <noscript />
          )}

          {!!errors?.tpInsuranceCompany && (
            <ErrorMsg fontSize={"12px"}>
              {errors?.tpInsuranceCompany?.message}
            </ErrorMsg>
          )}
        </div>
        <input type="hidden" name={"tpInsuranceCompanyName"} ref={register} />
      </Col>

      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag mandatory>TP Policy Number</FormGroupTag>
          <Form.Control
            type="text"
            autoComplete="off"
            placeholder="Enter TP Policy Number"
            name={`tpInsuranceNumber`}
            ref={register}
            readOnly={
              TpReadOnly &&
              (temp_data?.selectedQuote?.isRenewal !== "Y" ||
                temp_data?.isRenewalUpload)
            }
            maxLength="50"
            onInput={(e) =>
              (e.target.value = e.target.value
                .toUpperCase()
                .replace(/[^a-zA-Z0-9-/]/g, ""))
            }
            minlength="2"
            size="sm"
            errors={errors?.tpInsuranceNumber}
            isInvalid={errors?.tpInsuranceNumber}
          />
          {!!errors?.tpInsuranceNumber && (
            <ErrorMsg fontSize={"12px"}>
              {errors?.tpInsuranceNumber?.message}
            </ErrorMsg>
          )}
        </div>
      </Col>

      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <StyledDatePicker>
          <div className="py-2 dateTimeOne">
            <FormGroupTag mandatory>TP Policy Start Date</FormGroupTag>
            <Controller
              control={control}
              name={`tpStartDate`}
              render={({ onChange, onBlur, value, name }) => (
                <DateInput
                  value={value}
                  name={name}
                  // minDate={getMinDate()}
                  maxDate={new Date()}
                  onChange={onChange}
                  ref={register}
                  readOnly={
                    allFieldsReadOnly && import.meta.env.VITE_BROKER !== "BAJAJ"
                  }
                  selected={getSelectedDate()}
                  errors={errors?.tpStartDate}
                />
              )}
            />

            {!!errors?.tpStartDate && (
              <ErrorMsg fontSize={"12px"}>
                {errors?.tpStartDate?.message}
              </ErrorMsg>
            )}
          </div>
        </StyledDatePicker>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <StyledDatePicker>
          <div className="py-2 dateTimeOne">
            <FormGroupTag mandatory>TP Policy End Date</FormGroupTag>
            <Controller
              control={control}
              name={`tpEndDate`}
              render={({ onChange, onBlur, value, name }) => (
                <DateInput
                  value={value}
                  name={name}
                  onChange={onChange}
                  ref={register}
                  readOnly={true}
                  errors={errors?.tpEndDate}
                />
              )}
            />
            {!!errors?.tpEndDate && (
              <ErrorMsg fontSize={"12px"}>
                {errors?.tpEndDate?.message}
              </ErrorMsg>
            )}
          </div>
        </StyledDatePicker>
      </Col>
    </>
  );
};

export default TpDetails;
