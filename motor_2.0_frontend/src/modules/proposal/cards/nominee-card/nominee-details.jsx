import React from "react";
import { Col, Form } from "react-bootstrap";
import styled from "styled-components";
import _ from "lodash";
import DateInput from "../../DateInput";
import { ErrorMsg } from "../../../../components";
import { FormGroupTag } from "../../style";
import Radio from "@mui/material/Radio";
import RadioGroup from "@mui/material/RadioGroup";
import FormControlLabel from "@mui/material/FormControlLabel";
import FormControl from "@mui/material/FormControl";
import { toDate } from "utils";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import { subYears } from "date-fns";
import moment from "moment";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme1 = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

/*---------------date config----------------*/
const AdultCheck = subYears(new Date(Date.now() - 86400000), 18);
/*-----x---------date config-----x----------*/

export const NomineeDetails = ({
  temp_data,
  fields,
  Controller,
  control,
  PACondition,
  Tenure,
  type,
  register,
  watch,
  cpaStatus,
  NomineeBroker,
  nominee,
  CardData,
  errors,
  relation,
}) => {
  const DOB = watch("nomineeDob");
  const Relations = !_.isEmpty(relation)
    ? relation?.map(({ name, code }) => {
        return { name, code };
      })
    : [
        // { name: "Father", id: 1 },
        // { name: "Mother", id: 2 },
        // { name: "Spouse", id: 3 },
      ];

  //setting hidden i/p
  const NomineeRel = watch("nomineeRelationship");
  const NomineeRelName = Relations.filter(({ code }) => code === NomineeRel)[0]
    ?.name;

  const NomineeDob = watch("nomineeDob");
  const NomineeAge =
    NomineeDob && moment().diff(moment(NomineeDob, "DD-MM-YYYY"), "years");

  return (
    <>
      {temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType !== "C" &&
        temp_data?.corporateVehiclesQuoteRequest?.policyType !== "own_damage" &&
        fields.includes("cpaOptIn") && (
          <Col xs={12} sm={12} md={12} lg={12} xl={12} className="">
            <div className="py-2 fname" style={{ marginTop: "2px" }}>
              <FormGroupTag>Compulsory Personal Accident</FormGroupTag>
              <div>
                <FormControl>
                  <Controller
                    as={
                      <RadioGroup
                        row
                        aria-labelledby="demo-row-radio-buttons-group-label"
                        name="row-radio-buttons-group"
                        size="small"
                        sx={{ fontSize: 9 }}
                        defaultValue={
                          !PACondition
                            ? !_.isEmpty(Tenure)
                              ? "MultiYear"
                              : "OneYear"
                            : PACondition
                            ? "NO"
                            : null
                        }
                      >
                        <FormControlLabel
                          value="OneYear"
                          control={
                            <Radio
                              sx={{
                                color: `${
                                  Theme1?.proposalProceedBtn?.hex1
                                    ? Theme1?.proposalProceedBtn?.hex1
                                    : "#4ca729"
                                }`,
                                fontSize: 9,
                                "&.Mui-checked": {
                                  color: `${
                                    Theme1?.proposalProceedBtn?.hex1
                                      ? Theme1?.proposalProceedBtn?.hex1
                                      : "#4ca729"
                                  }`,
                                },
                              }}
                            />
                          }
                          label={type === "cv" ? "Yes" : "1 Year"}
                          sx={{ fontSize: 9 }}
                        />
                        {(type === "bike" || type === "car") && (
                          <FormControlLabel
                            value="MultiYear"
                            disabled={
                              !temp_data?.selectedQuote?.multiYearCpa * 1
                            }
                            control={
                              <Radio
                                sx={{
                                  color: `${
                                    Theme1?.proposalProceedBtn?.hex1
                                      ? Theme1?.proposalProceedBtn?.hex1
                                      : "#4ca729"
                                  }`,
                                  fontSize: 9,
                                  "&.Mui-checked": {
                                    color: `${
                                      Theme1?.proposalProceedBtn?.hex1
                                        ? Theme1?.proposalProceedBtn?.hex1
                                        : "#4ca729"
                                    }`,
                                  },
                                }}
                              />
                            }
                            label={`${type === "bike" ? "5 Years" : "3 Years"}`}
                            size="small"
                          />
                        )}
                        <FormControlLabel
                          value="NO"
                          control={
                            <Radio
                              sx={{
                                color: `${
                                  Theme1?.proposalProceedBtn?.hex1
                                    ? Theme1?.proposalProceedBtn?.hex1
                                    : "#4ca729"
                                }`,
                                fontSize: 9,
                                "&.Mui-checked": {
                                  color: `${
                                    Theme1?.proposalProceedBtn?.hex1
                                      ? Theme1?.proposalProceedBtn?.hex1
                                      : "#4ca729"
                                  }`,
                                },
                              }}
                            />
                          }
                          label="No"
                          sx={{ fontSize: 9 }}
                        />
                      </RadioGroup>
                    }
                    name="cpa"
                    control={control}
                  />
                </FormControl>
              </div>
            </div>
            <input
              type="hidden"
              ref={register}
              name="compulsoryPersonalAccident"
              value={
                watch("cpa") === "OneYear"
                  ? type === "cv"
                    ? "YES"
                    : "One Year"
                  : watch("cpa") === "MultiYear"
                  ? type === "bike"
                    ? "5 Years"
                    : "3 Years"
                  : watch("cpa")
              }
            />
          </Col>
        )}
      {(cpaStatus === "MultiYear" ||
        cpaStatus === "OneYear" ||
        NomineeBroker) && (
        <>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>Nominee Name</FormGroupTag>
              <Form.Control
                type="text"
                autoComplete="none"
                placeholder="Enter Nominee Name"
                name={`nomineeName`}
                // readOnly={allFieldsReadOnly}
                ref={register}
                maxLength="50"
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value).toUpperCase()
                      : e.target.value)
                }
                minlength="2"
                size="sm"
                isInvalid={errors?.nomineeName}
              />
              {!!errors?.nomineeName && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.nomineeName?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <StyledDatePicker>
              <div className="py-2 dateTimeOne">
                <FormGroupTag mandatory>Nominee DOB</FormGroupTag>
                <Controller
                  control={control}
                  name={`nomineeDob`}
                  render={({ onChange, onBlur, value, name }) => (
                    <DateInput
                      maxDate={AdultCheck}
                      value={value}
                      // readOnly={allFieldsReadOnly}
                      name={name}
                      onChange={onChange}
                      ref={register}
                      selected={
                        DOB ||
                        nominee?.nomineeDob ||
                        CardData?.nominee?.nomineeDob
                          ? toDate(
                              DOB ||
                                nominee?.nomineeDob ||
                                CardData?.nominee?.nomineeDob
                            )
                          : false
                      }
                      errors={errors?.nomineeDob}
                    />
                  )}
                />
                {!!errors?.nomineeDob && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.nomineeDob?.message}
                  </ErrorMsg>
                )}
              </div>
              <input
                type="hidden"
                name="nomineeAge"
                ref={register}
                value={NomineeAge}
              />
            </StyledDatePicker>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2 fname">
              <FormGroupTag mandatory>Relationship with the Owner</FormGroupTag>
              <Form.Control
                autoComplete="none"
                as="select"
                // readOnly={allFieldsReadOnly}
                size="sm"
                ref={register}
                name={`nomineeRelationship`}
                style={{ cursor: "pointer" }}
                errors={errors?.nomineeRelationship}
                isInvalid={errors?.nomineeRelationship}
              >
                <option selected={true} value={"@"}>
                  Select
                </option>
                {Relations.map(({ name, code }, index) => (
                  <option
                    selected={
                      CardData?.nominee?.nomineeRelationship === code ||
                      (_.isEmpty(CardData?.nominee) &&
                        _.isEmpty(nominee) &&
                        temp_data?.userProposal?.nomineeRelationship &&
                        temp_data?.userProposal?.nomineeRelationship.trim() ===
                          code.trim())
                    }
                    value={code}
                  >
                    {name}
                  </option>
                ))}
              </Form.Control>
              {!!errors?.nomineeRelationship && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.nomineeRelationship?.message}
                </ErrorMsg>
              )}
            </div>
            {watch("nomineeRelationship") && (
              <input
                type="hidden"
                name={"relationship_with_owner"}
                ref={register}
                value={NomineeRelName}
              />
            )}
          </Col>
        </>
      )}
    </>
  );
};

const StyledDatePicker = styled.div`
  .dateTimeOne .date-header {
    background: ${Theme1
      ? `${Theme1?.reactCalendar?.background} !important`
      : "#4ca729 !important"};
    border: ${Theme1
      ? `1px solid ${Theme1?.reactCalendar?.background} !important`
      : "1px solid #4ca729 !important"};
  }
  .dateTimeOne .react-datepicker__day:hover {
    background: ${Theme1
      ? `${Theme1?.reactCalendar?.background} !important`
      : "#4ca729 !important"};
    border: ${Theme1
      ? `1px solid ${Theme1?.reactCalendar?.background} !important`
      : "1px solid #4ca729 !important"};
  }
`;
