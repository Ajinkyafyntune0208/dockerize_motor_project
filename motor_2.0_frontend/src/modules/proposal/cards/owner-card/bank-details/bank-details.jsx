import React, { useEffect, useState } from "react";
import { Col, Form, ToggleButton } from "react-bootstrap";
import { FormGroupTag, ButtonGroupTag } from "modules/proposal/style";
import { ErrorMsg } from "components";
import PropTypes from "prop-types";
import { IFSC, bankIfscError as clearBankIfsc } from "modules/proposal/proposal.slice";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import _ from "lodash";
import { useDispatch, useSelector } from "react-redux"; 
import swal from "sweetalert";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

export const BankDetails = ({
  errors,
  watch,
  register,
  allFieldsReadOnly,
  temp_data,
  CardData,
  owner,
  setValue,
  enquiry_id,
}) => {
  const pep = [
    { name: "Yes", value: "Yes" },
    { name: "No", value: "No" },
  ];

  const ifscCode = watch("ifsc");
  const dispatch = useDispatch();
  const { bankIfsc, bankIfscError } = useSelector((state) => state.proposal)
  const [radioValue3, setRadioValue3] = useState(watch("pepStatus"));
  const [radioValue4, setRadioValue4] = useState(watch("gogreenStatus"));

  useState(() => {
    if (
      _.isEmpty(CardData?.owner?.pepStatus) &&
      _.isEmpty(owner?.pepStatus) &&
      !watch("pepStatus")
    ) {
      setRadioValue3("No");
      setValue("pepStatus", "No");
    }
    if (
      _.isEmpty(CardData?.owner?.gogreenStatus) &&
      _.isEmpty(owner?.gogreenStatus) &&
      !watch("gogreenStatus")
    ) {
      setRadioValue4("No");
      setValue("gogreenStatus", "No");
    }
  }, [owner, CardData?.owner]);

 
  useEffect(() => {
      if (temp_data?.selectedQuote?.companyAlias === "sbi" && ifscCode && ifscCode.match(/^[A-Za-z]{4}[a-zA-Z0-9]{7}$/)) {
        dispatch(
          IFSC({
            userProductJourneyId: enquiry_id,
            ifsc: ifscCode
          })
        );
      }
  }, [ifscCode]);


  useEffect(() => {
      if (temp_data?.selectedQuote?.companyAlias === "sbi") {
        bankIfsc?.bank_name && setValue("bankName", bankIfsc?.bank_name)
        bankIfsc?.bank_branch && setValue("branchName", bankIfsc?.bank_branch)
    }
  }, [bankIfsc]);

  //Error Handling
  useEffect(() => {
      if (temp_data?.selectedQuote?.companyAlias === "sbi" && bankIfscError) {
        setValue("bankName", "")
        setValue("branchName", "")
        swal("Error", bankIfscError , "error")
    }

    return () => {
      dispatch(clearBankIfsc(null))
    }
  }, [bankIfscError]);

  return (
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
          Bank Details
        </p>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="w-100">
        <div className="py-2 w-100">
          <FormGroupTag mandatory>{"IFSC"}</FormGroupTag>
          <div className="d-flex w-100 fname">
            <div style={{ maxWidth: "100%", width: "100%" }} className="fname1">
              <Form.Control
                ref={register}
                errors={errors.ifsc}
                isInvalid={errors.ifsc}
                autoComplete="none"
                name="ifsc"
                allFieldsReadOnly={allFieldsReadOnly}
                type="text"
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value.toUpperCase())
                      : e.target.value.toUpperCase())
                }
                maxLength="100"
                placeholder={"Enter IFSC"}
                size="sm"
              />
              {!!errors?.ifsc && (
                <ErrorMsg fontSize={"12px"}>{errors?.ifsc?.message}</ErrorMsg>
              )}
            </div>
          </div>
        </div>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="w-100">
        <div className="py-2 w-100">
          <FormGroupTag mandatory>{"Bank Name"}</FormGroupTag>
          <div className="d-flex w-100 fname">
            <div style={{ maxWidth: "100%", width: "100%" }} className="fname1">
              <Form.Control
                ref={register}
                errors={errors.bankName}
                isInvalid={errors.bankName}
                autoComplete="none"
                name="bankName"
                allFieldsReadOnly={allFieldsReadOnly}
                readOnly = {temp_data?.selectedQuote?.companyAlias === "sbi"}
                type="text"
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value).toUpperCase()
                      : e.target.value)
                }
                maxLength="100"
                placeholder={"Enter Bank Name"}
                size="sm"
              />
              {!!errors?.bankName && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.bankName?.message}
                </ErrorMsg>
              )}
            </div>
          </div>
        </div>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="w-100">
        <div className="py-2 w-100">
          <FormGroupTag mandatory>{"Account Number"}</FormGroupTag>
          <div className="d-flex w-100 fname">
            <div style={{ maxWidth: "100%", width: "100%" }} className="fname1">
              <Form.Control
                ref={register}
                errors={errors.accountNumber}
                isInvalid={errors.accountNumber}
                autoComplete="none"
                name="accountNumber"
                allFieldsReadOnly={allFieldsReadOnly}
                type="text"
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value).toUpperCase()
                      : e.target.value)
                }
                maxLength="100"
                placeholder={"Enter Account Number"}
                size="sm"
              />
              {!!errors?.accountNumber && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.accountNumber?.message}
                </ErrorMsg>
              )}
            </div>
          </div>
        </div>
      </Col>

    {temp_data?.selectedQuote?.companyAlias === "sbi" &&
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="w-100">
        <div className="py-2 w-100">
          <FormGroupTag mandatory>{"Branch Name"}</FormGroupTag>
          <div className="d-flex w-100 fname">
            <div style={{ maxWidth: "100%", width: "100%" }} className="fname1">
              <Form.Control
                ref={register}
                errors={errors.branchName}
                isInvalid={errors.branchName}
                autoComplete="none"
                name="branchName"
                allFieldsReadOnly={allFieldsReadOnly}
                readOnly = {temp_data?.selectedQuote?.companyAlias === "sbi"}
                type="text"
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value.toUpperCase())
                      : e.target.value.toUpperCase())
                }
                maxLength="100"
                placeholder={"Enter Branch Name"}
                size="sm"
              />
               {!!errors?.branchName && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.branchName?.message}
                </ErrorMsg>
              )}
            </div>
          </div>
        </div>
      </Col>}

      {temp_data?.selectedQuote?.companyAlias === "universal_sompo" && (
        <>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <FormGroupTag style={{ paddingTop: "10px" }} mandatory>
              Politically Exposed Person
            </FormGroupTag>
            <div className="" style={{ width: "100%", paddingTop: "2px" }}>
              <ButtonGroupTag toggle style={{ width: "100%" }}>
                {pep.map((item, idx) => (
                  <ToggleButton
                    style={{
                      minWidth: "fill-available",
                      width: "fill-available",
                      minHeight: "32px",
                    }}
                    key={idx}
                    className={item.value === "Yes" ? "mb-2 mr-4" : "mb-2"}
                    type="radio"
                    variant="secondary"
                    ref={register}
                    readOnly={allFieldsReadOnly}
                    size="sm"
                    name="pep"
                    tabIndex={"0"}
                    id={`index-key6${idx}`}
                    onKeyDown={(e) => {
                      if (e.keyCode === 32 && !allFieldsReadOnly) {
                        e.preventDefault();
                        document.getElementById(`index-key6${idx}`) &&
                          document.getElementById(`index-key6${idx}`).click();
                      }
                    }}
                    value={item.name}
                    checked={radioValue3 === item.name}
                    onChange={(e) => {
                      !allFieldsReadOnly && setRadioValue3(e.target.value);
                    }}
                  >
                    {item.name}
                  </ToggleButton>
                ))}
              </ButtonGroupTag>
            </div>
            <input
              type="hidden"
              name="pepStatus"
              value={radioValue3}
              ref={register}
            />
            {!!errors?.pepStatus && (
              <ErrorMsg fontSize={"12px"} style={{ marginTop: "-3px" }}>
                {errors?.pepStatus.message}
              </ErrorMsg>
            )}
          </Col>
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <FormGroupTag style={{ paddingTop: "10px" }} mandatory>
              Do you need policy hard copy
            </FormGroupTag>
            <div className="" style={{ width: "100%", paddingTop: "2px" }}>
              <ButtonGroupTag toggle style={{ width: "100%" }}>
                {pep.map((item, idx) => (
                  <ToggleButton
                    style={{
                      minWidth: "fill-available",
                      width: "fill-available",
                      minHeight: "32px",
                    }}
                    key={idx}
                    className={item.value === "Yes" ? "mb-2 mr-4" : "mb-2"}
                    type="radio"
                    variant="secondary"
                    ref={register}
                    readOnly={allFieldsReadOnly}
                    size="sm"
                    name="gogreen"
                    tabIndex={"0"}
                    id={`index-key7${idx}`}
                    onKeyDown={(e) => {
                      if (e.keyCode === 32 && !allFieldsReadOnly) {
                        e.preventDefault();
                        document.getElementById(`index-key7${idx}`) &&
                          document.getElementById(`index-key7${idx}`).click();
                      }
                    }}
                    value={item.name}
                    checked={radioValue4 === item.name}
                    onChange={(e) => {
                      !allFieldsReadOnly && setRadioValue4(e.target.value);
                    }}
                  >
                    {item.name}
                  </ToggleButton>
                ))}
              </ButtonGroupTag>
            </div>
            <input
              type="hidden"
              name="gogreenStatus"
              value={radioValue4}
              ref={register}
            />
            {!!errors?.gogreenStatus && (
              <ErrorMsg fontSize={"12px"} style={{ marginTop: "-3px" }}>
                {errors?.gogreenStatus.message}
              </ErrorMsg>
            )}
          </Col>
        </>
      )}
    </>
  );
};
