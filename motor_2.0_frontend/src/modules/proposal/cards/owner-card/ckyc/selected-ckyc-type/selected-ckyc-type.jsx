import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { ErrorMsg } from "components";

export const SelectedCkycType = ({
  temp_data,
  fields,
  uploadFile,
  resubmit,
  watch,
  identity,
  fieldsNonEditable,
  ckycValue,
  selectedIdentity,
  register,
  errors,
  ckycTypes,
}) => {
  const isCkycTypeApplicable = fields.includes("ckyc") && !uploadFile;
  const isReadOnly =
    (resubmit || (watch(identity) && fieldsNonEditable)) &&
    ["hdfc_ergo", "reliance"].includes(temp_data?.selectedQuote?.companyAlias)

  return (
    <>
      {isCkycTypeApplicable &&
        ckycTypes.map((each) => {
          if (
            ckycValue === "NO" &&
            each.id === identity &&
            identity !== "doi" &&
            identity !== "panNumber" &&
            identity !== "gstNumber" &&
            identity !== "form60"
          ) {
            return (
              <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
                <div className="py-2">
                  <FormGroupTag mandatory>
                    {selectedIdentity?.name}
                  </FormGroupTag>
                  <Form.Control
                    type="text"
                    autoComplete="none"
                    placeholder={`Enter ${selectedIdentity?.name}`}
                    size="sm"
                    ref={register}
                    name={identity}
                    readOnly={isReadOnly}
                    maxLength={selectedIdentity?.length}
                    onInput={(e) =>
                      (e.target.value = ![
                        "udyog",
                        "udyam",
                        "passportFileNumber",
                      ].includes(identity)
                        ? e.target.value
                            .replace(/[^A-Za-z0-9]/gi, "")
                            .toUpperCase()
                        : e.target.value)
                    }
                  />
                  {errors[identity] && (
                    <ErrorMsg fontSize={"12px"}>
                      {errors[identity]?.message}
                    </ErrorMsg>
                  )}
                </div>
              </Col>
            );
          }
        })}
    </>
  );
};
