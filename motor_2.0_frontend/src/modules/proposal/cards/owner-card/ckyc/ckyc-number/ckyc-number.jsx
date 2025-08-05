import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { ErrorMsg } from "components";

export const CkycNumber = ({
  fields,
  ckycValue,
  register,
  resubmit,
  watch,
  fieldsNonEditable,
  errors,
}) => {
  return (
    <>
      {fields.includes("ckyc") && ckycValue === "YES" && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4}>
          <div className="py-2">
            <FormGroupTag mandatory>CKYC Number</FormGroupTag>
            <Form.Control
              type="text"
              autoComplete="none"
              placeholder={`Enter CKYC Number`}
              size="sm"
              ref={register}
              name={"ckycNumber"}
              maxLength={14}
              readOnly={resubmit || (watch("ckycNumber") && fieldsNonEditable)}
              onInput={(e) =>
                (e.target.value = ("" + e.target.value)
                  .replace(/[^A-Za-z0-9]/gi, "")
                  .toUpperCase())
              }
            />
            {errors?.ckycNumber && (
              <ErrorMsg fontSize={"12px"}>
                {errors?.ckycNumber?.message}
              </ErrorMsg>
            )}
          </div>
        </Col>
      )}
    </>
  );
};
