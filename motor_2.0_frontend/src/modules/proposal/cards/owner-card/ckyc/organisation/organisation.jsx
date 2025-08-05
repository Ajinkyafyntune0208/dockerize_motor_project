import { useEffect } from "react";
import { Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { ErrorMsg } from "components";
import { useDispatch, useSelector } from "react-redux";
import { GetOrgFields } from "modules/proposal/proposal.slice";
import _ from "lodash";
import PropTypes from "prop-types";

export const Organisation = ({
  temp_data,
  fields,
  register,
  errors,
  watch,
  ckycValue,
  uploadFile,
  poi,
  cinAvailability,
}) => {
  const dispatch = useDispatch();
  const { orgFields } = useSelector((state) => state.proposal);

  const enableProprietorName =
    fields?.includes("organizationType") &&
    ["tata_aig", ""].includes(temp_data?.selectedQuote?.companyAlias) &&
    watch("organizationType") === "Proprietorship";

  //Get Organisation type
  /**
   * Effect hook to fetch organization-specific fields.
   *
   * This effect triggers a dispatch to fetch organization fields based on certain conditions.
   * It runs whenever there's a change in the `temp_data.ownerTypeId` or `fields`.
   *
   * Conditions for dispatch:
   * 1. The `ownerTypeId` in `temp_data` must be equal to 2.
   * 2. The `fields` array must include the string "organizationType".
   * 3. The `selectedQuote` within `temp_data` must have a non-empty `companyAlias`.
   *
   * If all conditions are met, it dispatches `GetOrgFields` action with the `companyAlias`
   * of the selected quote as a parameter.
   */
  useEffect(() => {
    if (
      temp_data?.ownerTypeId === 2 &&
      fields?.includes("organizationType") &&
      temp_data?.selectedQuote?.companyAlias
    )
      dispatch(
        GetOrgFields({ company_alias: temp_data?.selectedQuote?.companyAlias })
      );
  }, [temp_data?.ownerTypeId, fields]);

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

  const filteredOrganisations =
    temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C" && 
    fields?.includes("organizationType")
      ? isIdentityProofApplicable
        ? orgFields.filter((i) => ![58, 35, 60, 14].includes(i.code * 1))
        : orgFields
      : [];

  return (
    <>
      {!_.isEmpty(orgFields) && temp_data?.ownerTypeId === 2 ? (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
          <div className="py-2 fname">
            <FormGroupTag mandatory>Organization Type</FormGroupTag>
            <Form.Control
              as="select"
              size="sm"
              ref={register}
              name={`organizationType`}
              errors={errors?.organizationType}
              isInvalid={errors?.organizationType}
              style={{ cursor: "pointer" }}
            >
              {filteredOrganisations.map(({ value, type, code }, index) => (
                <option value={code}>{value}</option>
              ))}
            </Form.Control>
            {!!errors?.organizationType && (
              <ErrorMsg fontSize={"12px"}>
                {errors?.organizationType?.message}
              </ErrorMsg>
            )}
          </div>
        </Col>
      ) : (
        <noscript />
      )}
      {enableProprietorName && (
        <Col xs={12} sm={12} md={12} lg={6} xl={4} className="w-100">
          <div className="py-2 w-100">
            <FormGroupTag mandatory>{"Proprietor's Name"}</FormGroupTag>
            <div className="d-flex w-100 fname">
              <div
                style={{ maxWidth: "100%", width: "100%" }}
                className="fname1"
              >
                <Form.Control
                  ref={register}
                  errors={errors.proprietorName}
                  isInvalid={errors.proprietorName}
                  autoComplete="none"
                  name="proprietorName"
                  type="text"
                  onInput={(e) =>
                    (e.target.value =
                      e.target.value.length <= 1
                        ? ("" + e.target.value).toUpperCase()
                        : e.target.value)
                  }
                  maxLength="100"
                  placeholder={"Enter Proprietor Name"}
                  size="sm"
                  required
                />
                {!!errors?.proprietorName && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.proprietorName?.message}
                  </ErrorMsg>
                )}
              </div>
            </div>
          </div>
        </Col>
      )}
    </>
  );
};

Organisation.propTypes = {
  temp_data: PropTypes.object.isRequired,
  fields: PropTypes.array.isRequired,
  register: PropTypes.func.isRequired,
  errors: PropTypes.object.isRequired,
};
