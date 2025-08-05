import { Col } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import DateInput from "../../../../DateInput";
import { StyledDatePicker } from "../../owner-card";
import { ErrorMsg } from "components";

export const IncorporationDate = ({
  identity,
  ckycValue,
  Controller,
  control,
  register,
  errors,
  fieldsNonEditable,
  temp_data,
}) => {
  const isFutureGeneraliReadOnly =
    fieldsNonEditable &&
    temp_data?.selectedQuote?.companyAlias === "future_generali";

  return (
    <>
      {identity && identity === "doi" && ckycValue === "NO" && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <StyledDatePicker>
            <div className="py-2 dateTimeOne">
              <FormGroupTag mandatory>Date of Incorporation</FormGroupTag>
              <Controller
                control={control}
                name="doi"
                render={({ onChange, onBlur, value, name }) => (
                  <DateInput
                    minDate={false}
                    maxDate={new Date()}
                    value={value}
                    name={name}
                    onChange={onChange}
                    readOnly={isFutureGeneraliReadOnly}
                    ref={register}
                    // selected={
                    //   DOB || owner?.dob || CardData?.owner?.dob
                    //     ? DateUtil(DOB || owner?.dob || CardData?.owner?.dob)
                    //     : false
                    // }
                    incorporation={true}
                    errors={errors?.doi}
                  />
                )}
              />
              {!!errors?.doi && (
                <ErrorMsg fontSize={"12px"}>{errors?.doi?.message}</ErrorMsg>
              )}
            </div>
          </StyledDatePicker>
        </Col>
      )}
    </>
  );
};
