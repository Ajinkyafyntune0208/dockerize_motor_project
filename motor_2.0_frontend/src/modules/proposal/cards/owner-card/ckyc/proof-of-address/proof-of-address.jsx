import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import _ from "lodash";
import { Identities, identitiesCompany } from "modules/proposal/cards/data";
import { ErrorMsg } from "components";
import FilePicker from "components/filePicker/filePicker";
import { _disableCIN } from "modules/proposal/proposal-constants";

export const ProofOfAddress = ({
  temp_data,
  poa_file,
  setpoa_file,
  poa_back_file,
  setpoa_back_file,
  fields,
  uploadFile,
  ckycValue,
  ckycFields,
  register,
  errors,
  fileUploadError,
  watch,
  poa,
  ckycTypes,
  poa_identity,
  selectedpoaIdentity,
  poa_disabled,
  fileValidationText,
}) => {
  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  const enableUpload =
    fields.includes("ckyc") && uploadFile && (fields.includes("poa") || poa);
  const isProofOfAddressApplicable = enableUpload && ckycValue === "NO";
  const organizationType = watch("organizationType");

  const renderConfigPOA = () => {
    let poa_options = [];
    if (_disableCIN(companyAlias, organizationType, "poa")) {
      poa_options = ckycFields?.poalist?.filter(
        (item) => !["cinNumber"].includes(item?.value)
      );
    } else {
      poa_options = ckycFields?.poalist;
    }
    return poa_options.map(({ label, value, priority }, index) => (
      <option
        style={{ cursor: "pointer" }}
        // selected={"@"}
        value={value}
      >
        {label}
      </option>
    ));
  };

  const renderPOA = () => {
    return !_.isEmpty(ckycFields?.poalist)
      ? renderConfigPOA()
      : Number(temp_data?.ownerTypeId) === 1
      ? Identities(companyAlias, uploadFile, false, true).map(
          ({ name, id, priority }, index) => (
            <option style={{ cursor: "pointer" }} value={id}>
              {name}
            </option>
          )
        )
      : identitiesCompany(companyAlias, uploadFile, false, true)?.map(
          ({ name, id, priority }, index) => (
            <option style={{ cursor: "pointer" }} value={id}>
              {name}
            </option>
          )
        );
  };

  const enable_poa =
    (organizationType &&
      !["58", "35", "60", "14"].includes(organizationType) &&
      companyAlias === "sbi") ||
    temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "I" ||
    companyAlias !== "sbi";
    
  return (
    <>
      {isProofOfAddressApplicable && enable_poa && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4}>
          <div className="py-2 fname">
            <FormGroupTag mandatory>Proof of Address</FormGroupTag>
            <Form.Control
              as="select"
              autoComplete="none"
              size="sm"
              ref={register}
              name="poa_identity"
              className="title_list"
              style={{ cursor: "pointer" }}
            >
              {renderPOA()}
            </Form.Control>
          </div>
          {!!errors?.poa_identity && (
            <ErrorMsg fontSize={"12px"} style={{ marginTop: "-3px" }}>
              {errors?.poa_identity?.message}
            </ErrorMsg>
          )}
        </Col>
      )}
      {enableUpload &&
        ckycTypes.map((each) => {
          if (
            ckycValue === "NO" &&
            each.id === poa_identity &&
            poa_identity !== "doi" &&
            poa_identity !== "form60"
          ) {
            return (
              <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
                <div className="py-2">
                  <FormGroupTag mandatory>
                    {selectedpoaIdentity?.name}
                  </FormGroupTag>
                  <Form.Control
                    type="text"
                    autoComplete="none"
                    placeholder={`Enter ${selectedpoaIdentity?.name}`}
                    size="sm"
                    ref={register}
                    name={`poa_${poa_identity}`}
                    readOnly={poa_disabled}
                    maxLength={selectedpoaIdentity?.length}
                    onInput={(e) =>
                      (e.target.value = e.target.value
                        .replace(/[^A-Za-z0-9]/gi, "")
                        .toUpperCase())
                    }
                  />
                  {errors[`poa_${poa_identity}`] && (
                    <ErrorMsg fontSize={"12px"}>
                      {errors[`poa_${poa_identity}`]?.message}
                    </ErrorMsg>
                  )}
                </div>
              </Col>
            );
          }
        })}
      {enableUpload && enable_poa && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2">
            <FormGroupTag mandatory>Upload File</FormGroupTag>
            <FilePicker
              file={poa_file}
              setFile={setpoa_file}
              watch={watch}
              register={register}
              name={selectedpoaIdentity?.fileKey}
              id={selectedpoaIdentity?.fileKey}
              placeholder={selectedpoaIdentity?.placeholder}
            />
            {!poa_file && fileUploadError ? (
              <ErrorMsg fontSize={"12px"}>Please Upload document</ErrorMsg>
            ) : (
              <Form.Text className="text-muted">
                <text style={{ color: "#bdbdbd" }}>{fileValidationText}</text>
              </Form.Text>
            )}
          </div>
        </Col>
      )}
      {enableUpload && enable_poa && companyAlias === "nic" && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2">
            <FormGroupTag mandatory>Upload File Backside</FormGroupTag>
            <FilePicker
              file={poa_back_file}
              setFile={setpoa_back_file}
              watch={watch}
              register={register}
              name={selectedpoaIdentity?.backfileKey}
              id={selectedpoaIdentity?.backfileKey}
              placeholder={selectedpoaIdentity?.placeholder}
            />
            {!poa_back_file && fileUploadError ? (
              <ErrorMsg fontSize={"12px"}>
                Please Upload document Backside
              </ErrorMsg>
            ) : (
              <Form.Text className="text-muted">
                <text style={{ color: "#bdbdbd" }}>{fileValidationText}</text>
              </Form.Text>
            )}
          </div>
        </Col>
      )}
    </>
  );
};
