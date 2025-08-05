import { Col } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { StyledDatePicker } from "../../owner-card";
import { ErrorMsg } from "components";
import DateInput from "../../../../DateInput";
import { toDate as DateUtil } from "utils";

export const IncorporationDate = ({
  Controller,
  control,
  allFieldsReadOnly,
  resubmit,
  verifiedData,
  fieldsNonEditable,
  register,
  owner,
  CardData,
  errors,
  watch,
  fields,
  temp_data,
}) => {
  const DOB = watch("dob");
  return (
    <>
      {fields.includes("ckyc") && Number(temp_data?.ownerTypeId) === 2 && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <StyledDatePicker>
            <div className="py-2 dateTimeOne">
              <FormGroupTag mandatory>Date of Incorporation</FormGroupTag>
              <Controller
                control={control}
                name="dob"
                render={({ onChange, value, name }) => (
                  <DateInput
                    minDate={false}
                    maxDate={new Date()}
                    value={value}
                    name={name}
                    onChange={onChange}
                    readOnly={
                      allFieldsReadOnly ||
                      (resubmit && verifiedData?.includes("dob")) ||
                      (watch("dob") && fieldsNonEditable)
                    }
                    ref={register}
                    selected={
                      DOB || owner?.dob || CardData?.owner?.dob
                        ? DateUtil(DOB || owner?.dob || CardData?.owner?.dob)
                        : false
                    }
                    incorporation={true}
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
