import { useEffect, useState } from "react";
import { ErrorMsg } from "components";
import _ from "lodash";
import { FormGroupTag } from "../../../../style";
import { Col, Form } from "react-bootstrap";

export const Relation = ({
  temp_data,
  fields,
  resubmit,
  verifiedData,
  fieldsNonEditable,
  errors,
  register,
  ckycValue,
  uploadFile,
  watch,
  allFieldsReadOnly,
}) => {
  const relationTypeIp = watch("relationType");
  const [relationType, setRelationType] = useState(false);

  useEffect(() => {
    if (relationTypeIp) {
      setRelationType(relationTypeIp);
    }
  }, [relationTypeIp]);

  const handleInput = (e) =>
    (e.target.value = e.target.value.replace(/[^A-Za-z\s]/gi, ""));

  return (
    <>
      {fields.includes("fatherName") &&
        !fields.includes("relationType") &&
        ckycValue === "NO" && (
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>Father's Name</FormGroupTag>
              <Form.Control
                type="text"
                autoComplete="none"
                placeholder="Enter Father's Name"
                size="sm"
                name="fatherName"
                maxLength="50"
                readOnly={
                  allFieldsReadOnly
                  // ||
                  // (resubmit && verifiedData?.includes("fatherName")) ||
                  // (watch("fatherName") && fieldsNonEditable)
                }
                onInput={(e) => handleInput(e)}
                ref={register}
                errors={errors?.fatherName}
                isInvalid={errors?.fatherName}
              />
              {!!errors?.fatherName && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.fatherName?.message}
                </ErrorMsg>
              )}
            </div>
          </Col>
        )}
      {fields.includes("relationType") &&
        ((ckycValue === "NO" && uploadFile) ||
          (temp_data?.selectedQuote?.companyAlias === "magma" &&
            temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
              "I")) && (
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2 fname">
              <FormGroupTag mandatory>Relation Type</FormGroupTag>
              <Form.Control
                as="select"
                // readOnly={allFieldsReadOnly}
                size="sm"
                ref={register}
                name={`relationType`}
                errors={errors?.relationType}
                isInvalid={errors?.relationType}
                style={{ cursor: "pointer" }}
              >
                {[
                  { type: "Father", value: "fatherName" },
                  { type: "Mother", value: "motherName" },
                  { type: "Spouse", value: "spouseName" },
                ].map(({ value, type }, index) => (
                  <option value={value}>{type}</option>
                ))}
              </Form.Control>
              {!!errors?.city && (
                <ErrorMsg fontSize={"12px"}>{errors?.city?.message}</ErrorMsg>
              )}
            </div>
          </Col>
        )}
      {fields.includes("relationType") &&
        watch("relationType") &&
        ((ckycValue === "NO" && uploadFile) ||
          (temp_data?.selectedQuote?.companyAlias === "magma" &&
            temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType ===
              "I")) && (
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>
                {_.startCase(watch("relationType"))}
              </FormGroupTag>
              <Form.Control
                key={`name-${relationTypeIp}`}
                type="text"
                autoComplete="none"
                placeholder={`Enter ${_.startCase(watch("relationType"))}`}
                size="sm"
                // required
                name={watch("relationType")}
                maxLength="50"
                readOnly={
                  (resubmit && verifiedData?.includes(watch("relationType"))) ||
                  (watch("relationType") && fieldsNonEditable)
                }
                onInput={(e) => handleInput(e)}
                onBlur={(e) => handleInput(e)}
                ref={register}
                errors={errors?.relType}
                isInvalid={errors?.relType}
              />
              {!!errors?.relType && (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.relType?.message}
                </ErrorMsg>
              )}
            </div>
            <input
              key={`name-hidden-${relationTypeIp}`}
              name="relType"
              type="hidden"
              value={watch(`${watch("relationType")}`)}
              ref={register}
            />
          </Col>
        )}
    </>
  );
};
