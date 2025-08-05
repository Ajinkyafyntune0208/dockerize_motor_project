import React from "react";
import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "../../../../style";
import { numOnly, toDate as DateUtil } from "utils";
import DateInput from "../../../../DateInput";
import { subYears } from "date-fns";
import { StyledDatePicker } from "../../owner-card";
import { ErrorMsg } from "components";
import PropTypes from "prop-types";
import { useAceLeadSMS } from "./other-details-hooks";
import { useDispatch } from "react-redux";

const OtherDetails = ({
  temp_data,
  register,
  errors,
  resubmit,
  watch,
  fields,
  verifiedData,
  fieldsNonEditable,
  Controller,
  control,
  owner,
  CardData,
  enquiry_id,
}) => {
  const dispatch = useDispatch();

  const AdultCheck = subYears(new Date(Date.now() - 86400000), 18);
  const DOB = watch("dob");
  const mobileNoLead = watch("mobileNumber");
  const email = watch("email");

  useAceLeadSMS(temp_data, CardData, owner, mobileNoLead, enquiry_id, dispatch);

  //based on the new Requirement(#34260) allowing user to paste the mobile number with the spaces
  //handling spaces, "-" and trimming, while pasting
  const handlePaste = (e) => {
    e.preventDefault(); // Stop the default paste behavior
    const text = e.clipboardData.getData("text"); // Get pasted content
    const onlyDigits = text.replace(/\D/g, ""); // Remove non-digit characters

    const input = e.target;
    const start = input.selectionStart;
    const end = input.selectionEnd;
    const currentValue = input.value;

    // Calculate new value length after paste
    const newLength =
      currentValue.slice(0, start).length +
      onlyDigits.length +
      currentValue.slice(end).length;

    // If total length exceeds 10 digits, stop user from pasting
    if (newLength > 10) return;

    const newValue =
      currentValue.slice(0, start) + onlyDigits + currentValue.slice(end);
    input.value = newValue;

    // Move cursor after the inserted text
    input.setSelectionRange(
      start + onlyDigits.length,
      start + onlyDigits.length
    );

    // Trigger input event so React or React Hook Form updates value
    input.dispatchEvent(new Event("input", { bubbles: true }));
  };
  return (
    <>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag mandatory>Mobile Number</FormGroupTag>
          <Form.Control
            name="mobileNumber"
            ref={register}
            type="tel"
            autoComplete="none"
            placeholder="Enter Mobile Number"
            errors={errors?.mobileNumber}
            isInvalid={errors?.mobileNumber}
            size="sm"
            onKeyDown={numOnly}
            // onPaste={(e) =>
            //   !/^\d+$/.test(e.clipboardData.getData("text")) &&
            //   e.preventDefault()
            // }
            onPaste={(e) => handlePaste(e)}
            readOnly={false}
            maxLength="10"
          />
          {!!errors?.mobileNumber && (
            <ErrorMsg fontSize={"12px"}>
              {errors?.mobileNumber?.message}
            </ErrorMsg>
          )}
        </div>
      </Col>
      {fields.includes("email") && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2">
            <FormGroupTag mandatory>Email ID</FormGroupTag>
            <Form.Control
              type="email"
              autoComplete="none"
              placeholder="Enter Email Id"
              size="sm"
              name="email"
              maxLength="50"
              ref={register}
              errors={errors?.email}
              isInvalid={errors?.email}
            />
            {!!errors?.email && (
              <ErrorMsg fontSize={"12px"}>{errors?.email?.message}</ErrorMsg>
            )}
          </div>
          <input type="hidden" ref={register} name="officeEmail" />
        </Col>
      )}
      {fields.includes("dob") && Number(temp_data?.ownerTypeId) === 1 && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <StyledDatePicker>
            <div className="py-2 dateTimeOne">
              <FormGroupTag mandatory>Date of Birth</FormGroupTag>
              <Controller
                control={control}
                name="dob"
                render={({ onChange, value, name }) => (
                  <DateInput
                    minDate={false}
                    maxDate={AdultCheck}
                    value={value}
                    name={name}
                    onChange={onChange}
                    readOnly={
                      (resubmit && verifiedData?.includes("dob")) ||
                      (watch("dob") && fieldsNonEditable)
                    }
                    ref={register}
                    selected={
                      DOB || owner?.dob || CardData?.owner?.dob
                        ? DateUtil(DOB || owner?.dob || CardData?.owner?.dob)
                        : false
                    }
                    dob={true}
                    errors={errors?.dob}
                  />
                )}
              />
              {!!errors?.dob && (
                <ErrorMsg fontSize={"12px"}>{errors?.dob?.message}</ErrorMsg>
              )}
            </div>
          </StyledDatePicker>
        </Col>
      )}
    </>
  );
};

OtherDetails.propTypes = {
  temp_data: PropTypes.object,
  register: PropTypes.func,
  errors: PropTypes.object,
  resubmit: PropTypes.bool,
  watch: PropTypes.func,
  fields: PropTypes.arrayOf(PropTypes.string),
  verifiedData: PropTypes.arrayOf(PropTypes.string),
  fieldsNonEditable: PropTypes.bool,
  Controller: PropTypes.elementType,
  control: PropTypes.object,
  owner: PropTypes.object,
  CardData: PropTypes.object,
  enquiry_id: PropTypes.string,
};

export default OtherDetails;
