import React from "react";
import { Col, Form } from "react-bootstrap";
import { PincodeDetails } from "./pincode/pincode";
import { ErrorMsg } from "components";
import _ from "lodash";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import PropTypes from "prop-types";
import { FormGroupTag } from "../../../style";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

export const AddressDetails = ({
  temp_data,
  resubmit,
  verifiedData,
  fieldEditable,
  register,
  errors,
  watch,
  CardData,
  owner,
  enquiry_id,
  setValue,
  fieldsNonEditable,
}) => {
  return (
    <>
      <Col
        xs={12}
        sm={12}
        md={12}
        lg={12}
        xl={12}
        className=" mt-1"
        style={{ marginBottom: "-10px" }}
      >
        <p
          style={{
            color: Theme?.proposalHeader?.color
              ? Theme?.proposalHeader?.color
              : "#1a5105",
            fontSize: "16px",
            fontWeight: "600",
          }}
        >
          Communication Address
        </p>
      </Col>
      {
        <>
          <Col xs={12} sm={12} md={12} lg={12} xl={12} className="">
            <div className="py-2">
              <FormGroupTag mandatory>Address</FormGroupTag>
              <Form.Control
                as="textarea"
                rows={2}
                autoComplete="none"
                name="address"
                maxLength={`${
                  ["reliance", "hdfc_ergo"].includes(
                    temp_data?.selectedQuote?.companyAlias
                  )
                    ? 200
                    : 120
                }`}
                readOnly={
                  (((resubmit &&
                    !_.isEmpty(verifiedData?.includes("address"))) ||
                    (watch("address") && fieldsNonEditable)) &&
                    temp_data?.selectedQuote?.companyAlias === "sbi") ||
                  !fieldEditable
                }
                minlength="2"
                ref={register}
                onInput={(e) =>
                  (e.target.value =
                    e.target.value.length <= 1
                      ? ("" + e.target.value).toUpperCase().replace(
                          /*eslint-disable*/
                          /[^A-Za-z0-9 .,?""!@#$%^&*()_=+;:<>\/\\|}{[\]`~]/g,
                          ""
                        )
                      : e.target.value)
                }
                errors={errors?.addressLine1}
                isInvalid={errors?.addressLine1}
                size="sm"
              />
              {errors?.addressLine1 ||
              errors?.addressLine2 ||
              errors?.addressLine3 ||
              errors?.address ? (
                <ErrorMsg fontSize={"12px"}>
                  {errors?.addressLine1?.message ||
                    errors?.addressLine2?.message ||
                    errors?.addressLine3?.message ||
                    errors?.address?.message}
                </ErrorMsg>
              ) : (
                <Form.Text className="text-muted">
                  <text style={{ color: "#bdbdbd" }}>
                    {`(${watch("address")?.length}/${
                      temp_data?.selectedQuote?.companyAlias === "reliance"
                        ? 200
                        : 120
                    })`}
                  </text>
                </Form.Text>
              )}
            </div>
          </Col>
          <input
            type="hidden"
            ref={register}
            name="addressLine1"
            value={watch("address")}
          />
        </>
      }

      <PincodeDetails
        temp_data={temp_data}
        CardData={CardData}
        owner={owner}
        enquiry_id={enquiry_id}
        register={register}
        resubmit={resubmit}
        verifiedData={verifiedData}
        fieldEditable={fieldEditable}
        errors={errors}
        setValue={setValue}
        watch={watch}
        fieldsNonEditable={fieldsNonEditable}
      />
    </>
  );
};

AddressDetails.propTypes = {
  temp_data: PropTypes.object.isRequired,
  resubmit: PropTypes.bool.isRequired,
  verifiedData: PropTypes.arrayOf(PropTypes.string).isRequired,
  fieldEditable: PropTypes.bool.isRequired,
  register: PropTypes.func.isRequired,
  errors: PropTypes.object.isRequired,
  watch: PropTypes.func.isRequired,
  CardData: PropTypes.object.isRequired,
  owner: PropTypes.object.isRequired,
  enquiry_id: PropTypes.string.isRequired,
  setValue: PropTypes.func.isRequired,
};
