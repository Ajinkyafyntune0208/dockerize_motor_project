import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import FilePicker from "components/filePicker/filePicker";
import { downloadFile } from "utils";

export const FormUpload = ({
  temp_data,
  panAvailability,
  form60,
  form49,
  setForm60,
  setForm49,
  watch,
  register,
}) => {
  let formType = watch("formType");

  const isRoyalSundaram =
    temp_data?.selectedQuote?.companyAlias === "royal_sundaram";
  const isTataAig = temp_data?.selectedQuote?.companyAlias === "tata_aig";

  const isShriramKitJSON =
    !["RB", "ABIBL"].includes(import.meta.env.VITE_BROKER) &&
    temp_data?.selectedQuote?.companyAlias === "shriram";

  const isIndividualOwner =
    temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "I";

  const shouldPerformAction =
    panAvailability === "NO" &&
    (isRoyalSundaram || isTataAig || isShriramKitJSON) &&
    isIndividualOwner &&
    formType;

  return (
    <>
      {shouldPerformAction && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2">
            <FormGroupTag mandatory={true}>
              {formType === "form60" ? "Upload Form 60" : "Upload Form 49A"}
            </FormGroupTag>
            <FilePicker
              file={formType === "form60" ? form60 : form49}
              setFile={formType === "form60" ? setForm60 : setForm49}
              watch={watch}
              register={register}
              name={formType}
              id={formType}
              // placeholder={selectedIdentity?.placeholder}
            />
            {formType === "form60" ? (
              <Form.Text
                className="text-muted"
                style={{ cursor: "pointer" }}
                onClick={() =>
                  downloadFile(
                    "https://incometaxindia.gov.in/forms/income-tax%20rules/103120000000007944.pdf",
                    false,
                    true
                  )
                }
              >
                <u style={{ color: "#bdbdbd" }}>Download form 60 template</u>
              </Form.Text>
            ) : (
              <noscript />
            )}
          </div>
        </Col>
      )}
    </>
  );
};
