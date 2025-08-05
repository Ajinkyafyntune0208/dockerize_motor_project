import { ErrorMsg } from "components";
import { FormGroupTag } from "modules/proposal/style";
import React from "react";
import { Col, Form } from "react-bootstrap";
import { StyledDatePicker } from "../policy-card";
import { Controller } from "react-hook-form";
import moment from "moment";
import { SubTitleFn } from "../helper";
import DateInput from "modules/proposal/DateInput";
import _ from "lodash";
import { toDate } from "utils";

const CpaDetails = ({
  prevPolicyCon,
  Theme,
  PACon,
  lessthan768,
  register,
  allFieldsReadOnly,
  theme_conf,
  CardData,
  watch,
  errors,
  Previnsurer,
  control,
  CpaFmDate,
  prepolicy,
  CpaToDate,
  numOnly,
  reasonCpa,
  cpaPolicyNo,
  CpaSumIns,
}) => {
  //cpa opt out reasons
  const cpaOptOutReasons =
    theme_conf?.broker_config?.cpaOptOutReasons &&
    !_.isEmpty(theme_conf?.broker_config?.cpaOptOutReasons)
      ? theme_conf?.broker_config?.cpaOptOutReasons
      : [
          "I have another PA policy with cover amount of INR 15 Lacs or more",
          "I do not have a valid driving license.",
        ];

  return (
    <>
      {prevPolicyCon && SubTitleFn(Theme, "PA Exclusion Details")}
      {PACon && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2 fname">
            <FormGroupTag mandatory>
              {lessthan768
                ? "Reason for not opting for personal accident cover"
                : "CPA opt-out reason"}
            </FormGroupTag>
            <Form.Control
              autoComplete="off"
              as="select"
              size="sm"
              ref={register}
              readOnly={allFieldsReadOnly}
              placeholder="CPA OPT-OUT REASON"
              name={`reason`}
              style={{ cursor: "pointer" }}
            >
              {cpaOptOutReasons.map((item) => {
                return (
                  <option
                    id={item}
                    key={item}
                    selected={
                      CardData?.prepolicy?.reason &&
                      CardData?.prepolicy?.reason === item
                    }
                    value={item}
                  >
                    {item}
                  </option>
                );
              })}
            </Form.Control>
          </div>
        </Col>
      )}
      {watch("reason") !== `I do not have a valid driving license.` ? (
        <>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2 fname">
              <FormGroupTag mandatory>CPA Insurance company</FormGroupTag>
              <Form.Control
                autoComplete="off"
                as="select"
                size="sm"
                ref={register}
                placeholder="CPA Insurance company"
                // readOnly={allFieldsReadOnly}
                name={`cPAInsComp`}
                style={{ cursor: "pointer" }}
                errors={errors?.cPAInsComp}
                isInvalid={errors?.cPAInsComp}
              >
                <option selected={true} value={"@"}>
                  Select
                </option>
                {Previnsurer.map(({ name, code }, index) => (
                  <option
                    selected={
                      CardData?.prepolicy?.cPAInsComp &&
                      CardData?.prepolicy?.cPAInsComp === code
                    }
                    value={code}
                  >
                    {name}
                  </option>
                ))}
              </Form.Control>
              {!!errors?.cPAInsComp && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.cPAInsComp?.message}
                </ErrorMsg>
              )}
            </div>
            <input type="hidden" name={"CpaInsuranceCompany"} ref={register} />
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <StyledDatePicker>
              <div className="py-2 dateTimeOne">
                <FormGroupTag mandatory>CPA Policy Start Date</FormGroupTag>
                <Controller
                  control={control}
                  name={`cPAPolicyFmDt`}
                  render={({ onChange, onBlur, value, name }) => (
                    <DateInput
                      value={value}
                      name={name}
                      // readOnly={allFieldsReadOnly}
                      minDate={
                        moment().add(1, "days").subtract(10, "years")?._d
                      }
                      selected={
                        CpaFmDate ||
                        prepolicy?.cPAPolicyFmDt ||
                        CardData?.prepolicy?.cPAPolicyFmDt
                          ? toDate(
                              CpaFmDate ||
                                prepolicy?.cPAPolicyFmDt ||
                                CardData?.prepolicy?.cPAPolicyFmDt
                            )
                          : false
                      }
                      maxDate={new Date()}
                      onChange={onChange}
                      ref={register}
                      errors={errors?.cPAPolicyFmDt}
                    />
                  )}
                />
                {!!errors?.cPAPolicyFmDt && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.cPAPolicyFmDt?.message}
                  </ErrorMsg>
                )}
              </div>
            </StyledDatePicker>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <StyledDatePicker>
              <div className="py-2 dateTimeOne">
                <FormGroupTag mandatory>CPA Policy End Date</FormGroupTag>
                <Controller
                  control={control}
                  name={`cPAPolicyToDt`}
                  render={({ onChange, onBlur, value, name }) => (
                    <DateInput
                      value={value}
                      name={name}
                      // maxDate={toDate(temp_data?.selectedQuote?.policyStartDate)}
                      minDate={
                        CpaFmDate
                          ? moment(
                              moment(CpaFmDate, "DD-MM-YYYY")
                                .add(1, "years")
                                .subtract(1, "days")
                            )?._d
                          : moment()?._d
                      }
                      maxDate={
                        CpaFmDate
                          ? moment(CpaFmDate, "DD-MM-YYYY")
                              .add(10, "years")
                              .subtract(1, "days")?._d
                          : moment().add(10, "years").subtract(1, "days")?._d
                      }
                      selected={
                        CpaToDate ||
                        prepolicy?.cPAPolicyToDt ||
                        CardData?.prepolicy?.cPAPolicyToDt
                          ? toDate(
                              CpaToDate ||
                                prepolicy?.cPAPolicyToDt ||
                                CardData?.prepolicy?.cPAPolicyToDt
                            )
                          : false
                      }
                      onChange={onChange}
                      ref={register}
                      readOnly={CpaFmDate ? false : true}
                      errors={errors?.cPAPolicyToDt}
                    />
                  )}
                />
                {!!errors?.cPAPolicyToDt && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.cPAPolicyToDt?.message}
                  </ErrorMsg>
                )}
              </div>
            </StyledDatePicker>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>CPA Policy Number</FormGroupTag>
              <Form.Control
                type="text"
                autoComplete="off"
                placeholder="Enter CPA Policy Number"
                name={`cPAPolicyNo`}
                ref={register}
                // readOnly={allFieldsReadOnly}
                maxLength="50"
                onInput={(e) =>
                  (e.target.value = e.target.value
                    .toUpperCase()
                    .replace(/[^a-zA-Z0-9-/]/g, ""))
                }
                minlength="2"
                size="sm"
                errors={errors?.cPAPolicyNo}
                isInvalid={errors?.cPAPolicyNo}
              />
              {!!errors?.cPAPolicyNo && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.cPAPolicyNo?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>CPA Sum Insured</FormGroupTag>
              <Form.Control
                autoComplete="off"
                name="cPASumInsured"
                ref={register}
                type="tel"
                onInput={(e) => (e.target.value = e.target.value * Number(1))}
                placeholder="Enter Sum Insured"
                errors={errors?.cPASumInsured}
                isInvalid={errors?.cPASumInsured}
                size="sm"
                onKeyDown={numOnly}
                defaultValue={"1500000"}
                // readOnly={allFieldsReadOnly}
              />
              {!!errors?.cPASumInsured && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.cPASumInsured?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
        </>
      ) : (
        <noscript />
      )}
      {reasonCpa !== "I do not have a valid driving license." && (
        <>
          <input
            type="hidden"
            name={"cpaPolicyNumber"}
            ref={register}
            value={cpaPolicyNo}
          />
          <input
            type="hidden"
            name={"cpaPolicyStartDate"}
            ref={register}
            value={CpaFmDate}
          />
          <input
            type="hidden"
            name={"cpaPolicyEndDate"}
            ref={register}
            value={CpaToDate}
          />
          <input
            type="hidden"
            name={"cpaSumInsured"}
            ref={register}
            value={CpaSumIns}
          />{" "}
        </>
      )}
    </>
  );
};

export default CpaDetails;
