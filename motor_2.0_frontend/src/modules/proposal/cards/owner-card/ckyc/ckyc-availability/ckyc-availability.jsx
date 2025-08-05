import { Col, ToggleButton } from "react-bootstrap";
import { FormGroupTag, ButtonGroupTag } from "modules/proposal/style";
import { ErrorMsg } from "components";
import _ from "lodash";

export const CkycAvailability = ({
  temp_data,
  CardData,
  fields,
  ckycValue,
  setckycValue,
  setValue,
  register,
  errors,
  isCkycDetailsRejected,
  fieldsNonEditable,
}) => {
  const enableField =
    fields &&
    fields?.includes("ckyc") &&
    !(
      temp_data?.quoteLog?.premiumJson?.companyAlias === "bajaj_allianz" &&
      temp_data?.quoteLog?.finalPremiumAmount * 1 <= 50000
    ) 
  const excludeCkycNumber = fields?.includes("ckycQuestion");

  return (
    <>
      {enableField && (
        <Col
          xs={12}
          sm={12}
          md={12}
          lg={6}
          xl={4}
          style={{
            ...(excludeCkycNumber ? { display: "none" } : {}),
          }}
        >
          <FormGroupTag style={{ paddingTop: "10px" }} mandatory>
            Do you have CKYC Number?
          </FormGroupTag>
          <div className="" style={{ width: "100%", paddingTop: "2px" }}>
            <ButtonGroupTag toggle style={{ width: "100%" }}>
              {["YES", "NO"].map((radio, idx) => (
                <ToggleButton
                  style={{
                    width: "100%",
                    minHeight: "32px",
                  }}
                  key={idx}
                  className={`${idx === 0 ? "mr-4" : "mr-0"} "mb-2"`}
                  type="radio"
                  variant="secondary"
                  ref={register}
                  size="sm"
                  tabIndex={"0"}
                  name="ckycPresent"
                  value={radio}
                  checked={ckycValue === radio}
                  onInput={() =>
                    !excludeCkycNumber && setValue("ckycValue", radio)
                  }
                  readOnly={excludeCkycNumber}
                  onChange={(e) => {
                    !(CardData?.owner?.isckycPresent && fieldsNonEditable) &&
                      !excludeCkycNumber &&
                      setckycValue(e.target.value);
                  }}
                >
                  {_.capitalize(radio)}
                </ToggleButton>
              ))}
            </ButtonGroupTag>
          </div>
          <input
            type="hidden"
            name="isckycPresent"
            value={ckycValue}
            ref={register}
          />
          <input
            type="hidden"
            name="isCkycDetailsRejected"
            value={isCkycDetailsRejected ? "Y" : "N"}
            ref={register}
          />
          {!!errors?.ckycPresent && (
            <ErrorMsg fontSize={"12px"} style={{ marginTop: "-3px" }}>
              {errors?.ckycPresent?.message}
            </ErrorMsg>
          )}
        </Col>
      )}
    </>
  );
};
