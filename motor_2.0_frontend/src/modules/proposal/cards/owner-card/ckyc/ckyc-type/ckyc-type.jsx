import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import _ from "lodash";
import { ErrorMsg } from "components";
import { Identities, identitiesCompany } from "modules/proposal/cards/data";

export const CkycType = ({
  temp_data,
  errors,
  fields,
  ckycValue,
  uploadFile,
  errorStep,
  panAvailability,
  register,
  resubmit,
  renewalUploadReadOnly,
  selectedIdentity,
  identity,
  ckycFields,
  watch,
  fieldsNonEditable,
}) => {
  let companyAlias = temp_data?.selectedQuote?.companyAlias;

  const isFutureGeneraliReadOnly =
    fieldsNonEditable && companyAlias === "future_generali";

  let isCkycTypeApplicable =
    fields.includes("ckyc") &&
    ckycValue === "NO" &&
    !uploadFile &&
    !["oriental"].includes(temp_data?.selectedQuote?.companyAlias) &&
    !(companyAlias === "bajaj_allianz" && !errorStep);

  let _ckycTypesWithoutPAN = panAvailability === "NO" || errorStep;
  let isVehicleOwnerIndividual = Number(temp_data?.ownerTypeId) === 1;

  //Is proprietor enabled
  const isProprietorEnabled = watch("organizationType") === "Proprietorship";

  //Remap ckyc types w.r.t proprietorship
  const remappedCkyc =
    isProprietorEnabled && companyAlias === "reliance"
      ? ckycFields?.proprietorship_type
      : ckycFields?.ckyc_type;

  return (
    <>
      {isCkycTypeApplicable && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4}>
          <div className="py-2 fname">
            <FormGroupTag mandatory>CKYC Type</FormGroupTag>
            <Form.Control
              as="select"
              autoComplete="none"
              size="sm"
              ref={register}
              name="identity"
              readOnly={resubmit || renewalUploadReadOnly}
              style={{
                ...((renewalUploadReadOnly || isFutureGeneraliReadOnly) && {
                  pointerEvents: "none",
                }),
                cursor: "pointer",
              }}
              className="title_list"
            >
              {" "}
              {resubmit ? (
                <option style={{ cursor: "pointer" }} value={identity}>
                  {selectedIdentity?.name}
                </option>
              ) : !_.isEmpty(remappedCkyc) ? (
                (_ckycTypesWithoutPAN
                  ? remappedCkyc?.filter((item) => item.value !== "panNumber")
                  : remappedCkyc
                ).map(({ label, value, priority }, index) => (
                  <option style={{ cursor: "pointer" }} value={value}>
                    {label}
                  </option>
                ))
              ) : isVehicleOwnerIndividual ? (
                Identities(companyAlias, uploadFile).map(
                  ({ name, id, priority }, index) => (
                    <option style={{ cursor: "pointer" }} value={id}>
                      {name}
                    </option>
                  )
                )
              ) : (
                identitiesCompany(companyAlias, uploadFile)?.map(
                  ({ name, id, priority }, index) => (
                    <option style={{ cursor: "pointer" }} value={id}>
                      {name}
                    </option>
                  )
                )
              )}
            </Form.Control>
          </div>
          {!!errors?.identity && (
            <ErrorMsg fontSize={"12px"} style={{ marginTop: "-3px" }}>
              {errors?.identity?.message}
            </ErrorMsg>
          )}
        </Col>
      )}
    </>
  );
};
