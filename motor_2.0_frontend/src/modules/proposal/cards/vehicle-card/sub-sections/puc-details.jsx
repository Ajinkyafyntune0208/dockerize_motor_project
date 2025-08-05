import { ErrorMsg } from "components";
import { FormGroupTag } from "modules/proposal/style";
import React, { useEffect } from "react";
import { Col, Form } from "react-bootstrap";
import { StyledDatePicker } from "../vehicle-card";
import { Controller } from "react-hook-form";
import DateInput from "modules/proposal/DateInput";
import { toDate as DateUtil } from "utils";
import { ToggleElem } from "../helper";
import _ from "lodash";

const PucDetails = ({
  fields,
  temp_data,
  pucMandatory,
  register,
  errors,
  control,
  PUC_EXP,
  vehicle,
  CardData,
  Theme,
  allFieldsReadOnly,
  lessthan376,
  setValue,
}) => {
  useEffect(() => {
    if (_.isEmpty(CardData?.vehicle)) {
      !vehicle?.pucNo &&
        ((temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
          temp_data?.corporateVehiclesQuoteRequest?.rtoCode.includes("DL") &&
          temp_data?.corporateVehiclesQuoteRequest?.businessType !==
            "newbusiness") ||
          temp_data?.selectedQuote?.companyAlias !== "tata_aig") &&
        temp_data?.userProposal?.pucNo &&
        setValue("pucNo", temp_data?.userProposal?.pucNo);
      !vehicle?.pucExpiry &&
        ((temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
          temp_data?.corporateVehiclesQuoteRequest?.rtoCode.includes("DL") &&
          temp_data?.corporateVehiclesQuoteRequest?.businessType !==
            "newbusiness") ||
          temp_data?.selectedQuote?.companyAlias !== "tata_aig") &&
        temp_data?.userProposal?.pucExpiry &&
        setValue("pucExpiry", temp_data?.userProposal?.pucExpiry);
    }
  }, [CardData?.vehicle]);

  const KARO = import.meta.env.VITE_BROKER === "KAROINSURE";
  const pucValidationRSA = !(
    temp_data?.selectedQuote?.companyAlias === "royal_sundaram" &&
    temp_data?.productSubTypeCode === "BIKE" &&
    temp_data?.corporateVehiclesQuoteRequest?.businessType === "newbusiness" &&
    KARO
  );

  return (
    <>
      {((fields.includes("pucNo") && pucValidationRSA) ||
        temp_data?.selectedQuote?.companyAlias === "tata_aig") && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2">
            <FormGroupTag mandatory={pucMandatory}>{`PUC Number`}</FormGroupTag>
            <Form.Control
              autoComplete="off"
              type="text"
              placeholder="Enter PUC Number"
              size="sm"
              name="pucNo"
              // readOnly={allFieldsReadOnly}
              onInput={(e) =>
                (e.target.value = ("" + e.target.value).toUpperCase())
              }
              maxLength="50"
              ref={register}
              errors={errors?.pucNo}
              isInvalid={errors?.pucNo}
            />
            {!!errors?.pucNo && (
              <ErrorMsg fontSize={"12px"}>{errors?.pucNo?.message}</ErrorMsg>
            )}
          </div>
        </Col>
      )}
      {fields.includes("pucExpiry") && pucValidationRSA && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <StyledDatePicker>
            <div className="py-2 dateTimeOne">
              <FormGroupTag mandatory={pucMandatory}>
                PUC Expiry Date
              </FormGroupTag>
              <Controller
                control={control}
                name="pucExpiry"
                render={({ onChange, onBlur, value, name }) => (
                  <DateInput
                    minDate={new Date()}
                    value={value}
                    name={name}
                    onChange={onChange}
                    ref={register}
                    selected={
                      PUC_EXP ||
                      vehicle?.pucExpiry ||
                      CardData?.vehicle?.pucExpiry
                        ? DateUtil(
                            PUC_EXP ||
                              vehicle?.pucExpiry ||
                              CardData?.vehicle?.pucExpiry
                          )
                        : false
                    }
                    errors={errors?.pucExpiry}
                  />
                )}
              />
              {!!errors?.pucExpiry && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.pucExpiry?.message}
                </ErrorMsg>
              )}
            </div>
          </StyledDatePicker>
        </Col>
      )}
      <div style={{ display: "none" }}>
        {ToggleElem(
          "isValidPuc",
          "Do you have a valid PUC Certificate?",
          true,
          true,
          true,
          Theme,
          register,
          allFieldsReadOnly,
          lessthan376
        )}
      </div>
    </>
  );
};

export default PucDetails;
