import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { Identities, identitiesCompany } from "modules/proposal/cards/data";
import _ from "lodash";
import { ErrorMsg } from "components";
import FilePicker from "components/filePicker/filePicker";
import { _disableCIN } from "modules/proposal/proposal-constants";

export const ProofOfIdentity = ({
  temp_data,
  poi,
  uploadFile,
  poi_file,
  setpoi_file,
  poi_back_file,
  setpoi_back_file,
  fields,
  ckycValue,
  cinAvailability,
  allFieldsReadOnly,
  register,
  panAvailability,
  errors,
  watch,
  resubmit,
  fileUploadError,
  fileValidationText,
  poi_identity,
  ckycFields,
  ckycTypes,
  poi_disabled,
  selectedpoiIdentity,
}) => {
  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  const isIdentityProofApplicable =
    fields.includes("ckyc") &&
    ckycValue === "NO" &&
    uploadFile &&
    (fields.includes("poi") || poi) &&
    !(
      temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
      temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C" &&
      cinAvailability === "NO"
    );
  const organizationType = watch("organizationType");
  const enable_poi =
    (organizationType &&
      !["58", "35", "60", "14"].includes(organizationType) &&
      companyAlias === "sbi") ||
    temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "I" ||
    companyAlias !== "sbi";
  /**
   * Renders a list of options based on certain conditions from the ckycFields.
   * @returns {JSX.Element[]} An array of JSX option elements to be rendered.
   */
  const _renderConfigPOI = () => {
    let poi_options = [];
    if (
      panAvailability === "NO" &&
      _disableCIN(companyAlias, organizationType)
    ) {
      poi_options = ckycFields?.poilist?.filter(
        (item) => !["panNumber", "cinNumber"].includes(item?.value)
      );
    } else if (panAvailability === "NO") {
      poi_options = ckycFields?.poilist?.filter(
        (item) => item.value !== "panNumber"
      );
    } else if (_disableCIN(companyAlias, organizationType)) {
      poi_options = ckycFields?.poilist?.filter(
        (item) => item.value !== "cinNumber"
      );
    } else {
      poi_options = ckycFields?.poilist;
    }
    return poi_options.map(({ label, value, priority }, index) => (
      <option
        style={{ cursor: "pointer" }}
        // selected={"@"}
        value={value}
      >
        {label}
      </option>
    ));
  };

  /**
   * Renders Points of Interest (POI) based on certain conditions.
   * If the POI list is not empty, it renders the configured POI.
   * If the owner type ID is 1, it renders a list of company identities as options.
   * Otherwise, it renders a list of company identities as options.
   * @returns {JSX.Element} The rendered POI elements.
   */
  const renderPOI = () => {
    return !_.isEmpty(ckycFields?.poilist)
      ? _renderConfigPOI()
      : Number(temp_data?.ownerTypeId) === 1
      ? Identities(companyAlias, uploadFile, true, false)?.map(
          ({ name, id, priority }, index) => (
            <option
              style={{ cursor: "pointer" }}
              // selected={"@"}
              value={id}
            >
              {name}
            </option>
          )
        )
      : identitiesCompany(companyAlias, uploadFile, true, false)?.map(
          ({ name, id, priority }, index) => (
            <option
              style={{ cursor: "pointer" }}
              // selected={"@"}
              value={id}
            >
              {name}
            </option>
          )
        );
  };


  return (
    <>
      {isIdentityProofApplicable && enable_poi && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} style={{ display: "" }}>
          <div className="py-2 fname">
            <FormGroupTag mandatory>Proof of Identity</FormGroupTag>
            <Form.Control
              as="select"
              autoComplete="none"
              size="sm"
              ref={register}
              name="poi_identity"
              readOnly={allFieldsReadOnly}
              className="title_list"
              style={{ cursor: "pointer", pointerEvents: poi_disabled || resubmit ? "none": "unset" }}
            >
              {renderPOI()}
            </Form.Control>
          </div>
          {!!errors?.poi_identity && (
            <ErrorMsg fontSize={"12px"} style={{ marginTop: "-3px" }}>
              {errors?.poi_identity?.message}
            </ErrorMsg>
          )}
        </Col>
      )}
      {fields.includes("ckyc") &&
        uploadFile &&
        (fields.includes("poi") || poi) &&
        ckycTypes.map((each) => {
          if (
            ckycValue === "NO" &&
            each.id === poi_identity &&
            poi_identity !== "doi" &&
            poi_identity !== "form60"
          ) {
            return (
              <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
                <div className="py-2">
                  <FormGroupTag mandatory>
                    {selectedpoiIdentity?.name}
                  </FormGroupTag>
                  <Form.Control
                    type="text"
                    autoComplete="none"
                    placeholder={`Enter ${selectedpoiIdentity?.name}`}
                    size="sm"
                    ref={register}
                    name={`poi_${poi_identity}`}
                    readOnly={poi_disabled || resubmit}
                    maxLength={selectedpoiIdentity?.length}
                    onInput={(e) =>
                      (e.target.value = e.target.value
                        .replace(/[^A-Za-z0-9]/gi, "")
                        .toUpperCase())
                    }
                  />
                  {errors[`poi_${poi_identity}`] ? (
                    <ErrorMsg fontSize={"12px"}>
                      {errors[`poi_${poi_identity}`]?.message}
                    </ErrorMsg>
                  ) : (
                    <Form.Text className="text-muted"></Form.Text>
                  )}
                </div>
              </Col>
            );
          }
        })}
      {fields.includes("ckyc") &&
        uploadFile &&
        (fields.includes("poi") || poi) &&
        companyAlias !== "bajaj_allianz" &&
        // ||
        //   import.meta.env.VITE_PROD !== "YES"
        (companyAlias !== "tata_aig" ||
          ["ACE", "BAJAJ", "OLA", "TATA", "HEROCARE"].includes(
            import.meta.env.VITE_BROKER
          )) &&
        !(
          temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
          (temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C" ||
            cinAvailability === "NO")
        ) &&
        enable_poi && (
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>Upload File</FormGroupTag>
              <FilePicker
                file={poi_file}
                setFile={setpoi_file}
                watch={watch}
                register={register}
                name={selectedpoiIdentity?.fileKey}
                id={selectedpoiIdentity?.fileKey}
                placeholder={selectedpoiIdentity?.placeholder}
              />
              {!poi_file && fileUploadError ? (
                <ErrorMsg fontSize={"12px"}>Please Upload document</ErrorMsg>
              ) : (
                <Form.Text className="text-muted">
                  <text style={{ color: "#bdbdbd" }}>{fileValidationText}</text>
                </Form.Text>
              )}
            </div>
          </Col>
        )}
      {fields.includes("ckyc") &&
        uploadFile &&
        (fields.includes("poi") || poi) &&
        companyAlias !== "bajaj_allianz" &&
        // ||
        //   import.meta.env.VITE_PROD !== "YES"
        (companyAlias !== "tata_aig" ||
          ["ACE", "BAJAJ", "OLA", "TATA", "HEROCARE"].includes(
            import.meta.env.VITE_BROKER
          )) &&
        !(
          temp_data?.selectedQuote?.companyAlias === "tata_aig" &&
          (temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C" ||
            cinAvailability === "NO")
        ) &&
        enable_poi &&
        companyAlias === "nic" && (
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <div className="py-2">
              <FormGroupTag mandatory>Upload File Backside</FormGroupTag>
              <FilePicker
                file={poi_back_file}
                setFile={setpoi_back_file}
                watch={watch}
                register={register}
                name={selectedpoiIdentity?.backfileKey}
                id={selectedpoiIdentity?.backfileKey}
                placeholder={selectedpoiIdentity?.placeholder}
                required
              />
              {!poi_back_file && fileUploadError ? (
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
