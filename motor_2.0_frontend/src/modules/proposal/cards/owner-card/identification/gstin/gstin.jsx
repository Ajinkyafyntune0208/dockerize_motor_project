import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { ErrorMsg } from "components";

export const GSTIN = ({
  temp_data,
  fields,
  poa_identity,
  poi_identity,
  identity,
  token,
  register,
  errors,
  type,
  resubmit,
  watch
}) => {
  const isGSTApplicable = fields.includes("gstNumber");
  const isFieldNotInCKYC =
    (!poa_identity || poa_identity !== "gstNumber") &&
    (!poi_identity || poi_identity !== "gstNumber");

  const renderField = isGSTApplicable && isFieldNotInCKYC;

  const isMandatory =
    (temp_data?.selectedQuote?.companyAlias !== "sbi" &&
      ["kotak", "united_india", "liberty_videocon", "royal_sundaram"].includes(
        temp_data?.selectedQuote?.companyAlias
      )  &&
      temp_data?.ownerTypeId === 2) ||
    //commented due to paytm united india issue
    // && !token
    (identity === "gstNumber" && isGSTApplicable);

  return (
    <>
      {renderField && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2">
            <FormGroupTag mandatory={isMandatory}>{`GSTIN`}</FormGroupTag>
            <Form.Control
              type="text"
              autoComplete="none"
              placeholder="Enter GSTIN"
              size="sm"
              ref={register}
              name="gstNumber"
              maxLength={"15"}
              onInput={(e) =>
                (e.target.value = ("" + e.target.value)
                  .replace(/[^A-Za-z0-9]/gi, "")
                  .toUpperCase())
              }
              isInvalid={errors?.gstNumber}
              readOnly={
                resubmit &&
                (temp_data?.userProposal?.gstNumber &&
                watch("gstNumber") &&
                  temp_data?.selectedQuote?.companyAlias === "reliance")
              }
            />
            {errors?.gstNumber ? (
              <ErrorMsg fontSize={"12px"}>
                {errors?.gstNumber?.message}
              </ErrorMsg>
            ) : (
              <Form.Text className="text-muted">
                <text style={{ color: "#bdbdbd" }}>e.g 18AABCU9603R1ZM</text>
              </Form.Text>
            )}
          </div>
        </Col>
      )}
    </>
  );
};
