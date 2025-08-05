import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { panMandatoryIC } from "../../../../proposal-constants";
import { ErrorMsg } from "components";
import { PanAlternatives } from "./pan-alternatives/pan-alternatives";
import { AML } from "./pan-alternatives/AML/aml";
import { FormUpload } from "./pan-alternatives/form-upload/form-upload";

export const PanCard = ({
  temp_data,
  owner,
  fields,
  identity,
  poa_identity,
  poi_identity,
  panAvailability,
  ckycValue,
  register,
  watch,
  errors,
  resubmit,
  fieldsNonEditable,
  renewalUploadReadOnly,
  allFieldsReadOnly,
  pan_file,
  setpan_file,
  fileUploadError,
  form60,
  form49,
  setForm60,
  setForm49,
}) => {
  const companyAlias = temp_data?.selectedQuote?.companyAlias;

  const isPanRequired = (ckycValue, fields, identity, temp_data) => {
    const { selectedQuote } = temp_data || {};
    const { totalPayableAmountWithAddon, finalPayableAmount } =
      selectedQuote || {};
    const isPanNumberIdentity = identity === "panNumber";
    const isQuoteAmountAboveThreshold =
      totalPayableAmountWithAddon > 100000 || finalPayableAmount >= 100000;
    const isPanMandatoryForCompany = panMandatoryIC.includes(companyAlias);
    const isKitNotJson =
      ["RB", "ABIBL"].includes(import.meta.env.VITE_BROKER) &&
      companyAlias === "shriram";
    const isPanAvailable =
      panAvailability === "YES" && companyAlias === "shriram";
    const isPanMandatoryWithNoCkycValue =
      ["bajaj_allianz","universal_sompo", "sbi"].includes(companyAlias) && ckycValue === "NO";

    return (
      (ckycValue === "NO" && fields.includes("ckyc") && isPanNumberIdentity) ||
      isQuoteAmountAboveThreshold ||
      isPanMandatoryForCompany ||
      isKitNotJson ||
      isPanAvailable ||
      isPanMandatoryWithNoCkycValue
    );
  };
  const isPanReadOnly = () => {
    const isHdfcErgoReadOnly =
      fieldsNonEditable &&
      temp_data?.selectedQuote?.companyAlias === "hdfc_ergo";
    const isFutureGeneraliReadOnly =
      fieldsNonEditable &&
      temp_data?.selectedQuote?.companyAlias === "future_generali";  
    const isRelianceRenewalUpload =
      temp_data?.selectedQuote?.companyAlias === "reliance" &&
      temp_data.isRenewalUpload;
    const isPanNumberProvided =
      temp_data?.userProposal?.panNumber && watch("panNumber");
    const readOnly =
      resubmit ||
      (isPanNumberProvided &&
        (isHdfcErgoReadOnly ||
          isFutureGeneraliReadOnly ||
          isRelianceRenewalUpload ||
          renewalUploadReadOnly));

    return readOnly;
  };

  const isPanApplicable =
    (fields.includes("panNumber") || identity === "panNumber") &&
    poi_identity !== "panNumber" &&
    poa_identity !== "panNumber";

  return (
    <>
      {isPanApplicable && (
        <>
          <Col
            xs={12}
            sm={12}
            md={12}
            lg={6}
            xl={4}
            style={
              panAvailability === "NO" && companyAlias !== "royal_sundaram"
                ? { display: "none" }
                : {}
            }
          >
            {panAvailability === "YES" ? (
              <div className="py-2">
                <FormGroupTag
                  mandatory={isPanRequired(
                    ckycValue,
                    fields,
                    identity,
                    temp_data
                  )}
                >
                  PAN No
                </FormGroupTag>
                <Form.Control
                  type="text"
                  autoComplete="none"
                  placeholder="Enter PAN No"
                  size="sm"
                  ref={register}
                  name="panNumber"
                  readOnly={isPanReadOnly()}
                  maxLength="10"
                  onInput={(e) =>
                    (e.target.value = ("" + e.target.value)
                      .replace(/[^A-Za-z0-9]/gi, "")
                      .toUpperCase())
                  }
                  isInvalid={errors?.panNumber}
                />
                {errors?.panNumber ? (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.panNumber?.message}
                  </ErrorMsg>
                ) : (
                  <Form.Text className="text-muted">
                    <text style={{ color: "#bdbdbd" }}>e.g AAAPL1234C</text>
                  </Form.Text>
                )}
              </div>
            ) : (
              <PanAlternatives
                temp_data={temp_data}
                owner={owner}
                register={register}
                allFieldsReadOnly={allFieldsReadOnly}
              />
            )}
          </Col>
          {/*Shriram AML Logic*/}
          <AML
            temp_data={temp_data}
            panAvailability={panAvailability}
            pan_file={pan_file}
            setpan_file={setpan_file}
            watch={watch}
            register={register}
            fileUploadError={fileUploadError}
          />
        </>
      )}
      <FormUpload
        temp_data={temp_data}
        panAvailability={panAvailability}
        form60={form60}
        form49={form49}
        setForm60={setForm60}
        setForm49={setForm49}
        watch={watch}
        register={register}
      />
    </>
  );
};
