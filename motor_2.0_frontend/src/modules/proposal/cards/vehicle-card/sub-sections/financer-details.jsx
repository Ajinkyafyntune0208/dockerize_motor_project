import { ErrorMsg } from "components";
import { FormGroupTag } from "modules/proposal/style";
import { SearchInput } from "modules/proposal/typehead";
import React from "react";
import { Col, Form } from "react-bootstrap";
import _ from "lodash";
import { ToggleElem } from "../helper";

const FinancerDetails = ({
  watch,
  Theme,
  handleSearch,
  allFieldsReadOnly,
  financerList,
  temp_data,
  Controller,
  control,
  register,
  financer_sel_opt,
  FinancerInputValue,
  errors,
  Agreement,
  CardData,
  vehicle,
  AgreementTypeName,
  fields,
  companyAlias,
  branchMaster,
  lessthan376,
}) => {
  
  return (
    <>
      {ToggleElem(
        "isVehicleFinance",
        "Is your Vehicle Financed?",
        null,
        null,
        null,
        Theme,
        register,
        allFieldsReadOnly,
        lessthan376
      )}
      {watch("isVehicleFinance") ? (
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
                color: Theme?.proposalHeader?.color
                  ? Theme?.proposalHeader?.color
                  : "#1a5105",
                fontSize: "16px",
                fontWeight: "600",
              }}
            >
              Financer details
            </p>
          </Col>
          <Col xs={12} sm={12} md={12} lg={12} xl={8} className="">
            <div className="py-2 fname csip">
              <FormGroupTag mandatory>Select Financer</FormGroupTag>
              <SearchInput
                handleSearch={handleSearch}
                readOnly={allFieldsReadOnly}
                options={financerList}
                allowNew={
                  [
                    "godigit",
                    "royal_sundaram",
                    "future_generali",
                    "sbi",
                    "bajaj_allianz",
                    "oriental",
                    "nic",
                  ].includes(temp_data?.selectedQuote?.companyAlias) && true
                }
                newSelectionPrefix={"Add new financer: "}
                Controller={Controller}
                control={control}
                register={register}
                name="financer_sel"
                selected={
                  Array.isArray(financer_sel_opt) ? financer_sel_opt : false
                }
                defaultInputValue={FinancerInputValue}
              />
              {(errors?.financer_sel || errors?.nameOfFinancer) && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.financer_sel?.message ||
                    errors?.nameOfFinancer?.message}
                </ErrorMsg>
              )}
            </div>
            <>
              <input type="hidden" ref={register} name="nameOfFinancer" />
              <input type="hidden" ref={register} name="financer_name" />
              <input type="hidden" ref={register} name="fullNameFinance" />
            </>
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2 fname">
              <FormGroupTag mandatory>Financer agreement type</FormGroupTag>
              <Form.Control
                autoComplete="off"
                as="select"
                size="sm"
                ref={register}
                name="financerAgreementType"
                className="title_list"
                // readOnly={allFieldsReadOnly}
                style={{ cursor: "pointer" }}
                errors={errors?.financerAgreementType}
                isInvalid={errors?.financerAgreementType}
              >
                <option selected={true} value={"@"}>
                  Select
                </option>
                {Agreement.map(({ name, code }, index) => (
                  <option
                    selected={
                      CardData?.vehicle?.financerAgreementType === code ||
                      Agreement.length === 1 ||
                      (_.isEmpty(CardData?.vehicle) &&
                        _.isEmpty(vehicle) &&
                        name &&
                        name.toLowerCase() === "hypothecation")
                    }
                    value={code}
                  >
                    {name}
                  </option>
                ))}
              </Form.Control>
              {!!errors?.financerAgreementType && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.financerAgreementType?.message}
                </ErrorMsg>
              )}
              <input
                type="hidden"
                ref={register}
                name="agreement_type"
                value={AgreementTypeName}
              />
            </div>
          </Col>
          {fields.includes("hypothecationCity") && (
            <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
              <div className="py-2">
                <FormGroupTag mandatory>Financer (City/Branch)</FormGroupTag>
                {companyAlias === "united_india" ? (
                  <Form.Control
                    autoComplete="off"
                    as="select"
                    size="sm"
                    // readOnly={allFieldsReadOnly}
                    ref={register}
                    placeholder="Financer (City/Branch)"
                    name="hypothecationCity"
                    className="title_list"
                    isInvalid={errors?.hypothecationCity}
                  >
                    {branchMaster.map((branch) => {
                      return (
                        <option value={branch?.financierBranchCode}>
                          {branch?.branchName}
                        </option>
                      );
                    })}
                  </Form.Control>
                ) : (
                  <Form.Control
                    autoComplete="off"
                    type="text"
                    placeholder="Financer (City/Branch)"
                    name="hypothecationCity"
                    // readOnly={allFieldsReadOnly}
                    maxLength="50"
                    minlength="2"
                    ref={register}
                    onInput={(e) =>
                      (e.target.value =
                        e.target.value.length <= 1
                          ? ("" + e.target.value).toUpperCase()
                          : e.target.value)
                    }
                    errors={errors?.hypothecationCity}
                    isInvalid={errors?.hypothecationCity}
                    size="sm"
                  />
                )}
                {!!errors?.hypothecationCity && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.hypothecationCity?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
          )}
        </>
      ) : (
        <noscript />
      )}
    </>
  );
};

export default FinancerDetails;
