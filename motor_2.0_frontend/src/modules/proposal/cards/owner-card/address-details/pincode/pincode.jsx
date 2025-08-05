import React, { useEffect } from "react";
import { Col, Form } from "react-bootstrap";
import _ from "lodash";
import { numOnly } from "utils";
import { ErrorMsg } from "components";
import { Pincode, clear } from "../../../../proposal.slice";
import { FormGroupTag } from "../../../../style";
import { useDispatch, useSelector } from "react-redux";
import PropTypes from "prop-types";

export const PincodeDetails = ({
  temp_data,
  CardData,
  owner,
  enquiry_id,
  register,
  resubmit,
  verifiedData,
  fieldEditable,
  errors,
  setValue,
  watch,
  fieldsNonEditable,
}) => {
  const dispatch = useDispatch();
  const { pincode: pin } = useSelector((state) => state.proposal);
  const PinCode = watch("pincode");

  //get state-city
  useEffect(() => {
    let companyAlias = temp_data?.selectedQuote?.companyAlias;
    if (PinCode?.length === 6 && companyAlias) {
      dispatch(
        Pincode({
          companyAlias: companyAlias,
          pincode: PinCode,
          enquiryId: enquiry_id,
          rtoCode: temp_data?.corporateVehiclesQuoteRequest?.rtoCode,
        })
      );
    } else {
      dispatch(clear("pincode"));
    }
  }, [PinCode]);

  useEffect(() => {
    if (!_.isEmpty(pin)) {
      setValue("state", pin?.state?.state_name);
      setValue("stateId", pin?.state?.state_id);
      if (pin?.city?.length === 1) {
        setValue("city", pin?.city[0]?.city_name);
        setValue("cityId", pin?.city[0]?.city_id);
      }
    } else {
      setValue("state", "");
      setValue("stateId", "");
      setValue("city", "");
      setValue("cityId", "");
    }
  }, [pin]);

  // auto selecting if only one option is present.
  const city =
    watch("city") ||
    owner?.city ||
    CardData?.owner?.city ||
    (!_.isEmpty(pin?.city) &&
      pin?.city?.length === 1 &&
      pin?.city[0]?.city_name);

  useEffect(() => {
    if (city) {
      const city_id = pin?.city?.filter(({ city_name }) => city_name === city);
      !_.isEmpty(city_id)
        ? setValue("cityId", city_id[0]?.city_id)
        : setValue("cityId", owner?.cityId || CardData?.owner?.cityId);
    }
  }, [city, pin]);

  const pincodeOptions = () => {
    // Determine if a pin code option should be selected based on various conditions.
    const selectedPin = (city_name) => {
      return (
        CardData?.owner?.city?.trim() === city_name?.trim() ||
        (pin?.city?.length === 1 && !CardData?.owner?.city?.trim()) ||
        (_.isEmpty(CardData?.owner) &&
          _.isEmpty(owner) &&
          temp_data?.userProposal?.city &&
          temp_data?.userProposal?.city.trim() === city_name?.trim())
      );
    };


    return (
      <>
        {/* Always include a default "Select" option. */}
        <option selected={true} value={"@"}>
          Select
        </option>

        {/* Map through each city in the pin object to generate an option element. */}
        {pin?.city?.map(({ city_name }, index) => (
          <option
            selected={selectedPin(city_name)}
            value={city_name}
            key={index}
          >
            {city_name}
          </option>
        ))}
      </>
    );
  };

  const disableAfterKyc = ((resubmit && !_.isEmpty(verifiedData?.includes("pincode"))) || 
  (watch("pincode") && fieldsNonEditable)) &&
  temp_data?.selectedQuote?.companyAlias === "sbi" ||
!fieldEditable

  return (
    <>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag mandatory>Pincode</FormGroupTag>
          <Form.Control
            name="pincode"
            autoComplete="none"
            ref={register}
            readOnly={
              disableAfterKyc
            }
            type="tel"
            placeholder="Enter Pincode"
            errors={errors?.pincode}
            isInvalid={errors?.pincode}
            size="sm"
            onKeyDown={numOnly}
            maxLength="6"
          />
          {!!errors?.pincode && (
            <ErrorMsg fontSize={"12px"}>{errors?.pincode?.message}</ErrorMsg>
          )}
        </div>
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2">
          <FormGroupTag mandatory>State</FormGroupTag>
          <Form.Control
            name="state"
            ref={register}
            type="text"
            placeholder="Select State"
            style={{ cursor: "not-allowed" }}
            errors={errors?.state}
            isInvalid={errors?.state}
            size="sm"
            readOnly
          />
          {!!errors?.state && (
            <ErrorMsg fontSize={"12px"}>{errors?.state?.message}</ErrorMsg>
          )}
        </div>
        <input
          name="stateId"
          ref={register}
          type="hidden"
          value={pin?.state?.state_id}
        />
      </Col>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2 fname" style={{  ...(disableAfterKyc && {cursor: "not-allowed"}) }}>
          <FormGroupTag mandatory>City</FormGroupTag>
          <Form.Control
            as="select"
            size="sm"
            ref={register}
            name={`city`}
            errors={errors?.city}
            isInvalid={errors?.city}
            style={{ cursor: "pointer", ...(disableAfterKyc && {pointerEvents: "none", cursor: "not-allowed !important"}) }}
          >
            {pincodeOptions()}
          </Form.Control>
          {!!errors?.city && (
            <ErrorMsg fontSize={"12px"}>{errors?.city?.message}</ErrorMsg>
          )}
        </div>
        <input name="cityId" ref={register} type="hidden" />
      </Col>
    </>
  );
};

PincodeDetails.propTypes = {
  temp_data: PropTypes.object.isRequired,
  CardData: PropTypes.object.isRequired,
  owner: PropTypes.object.isRequired,
  enquiry_id: PropTypes.string.isRequired,
  register: PropTypes.func.isRequired,
  resubmit: PropTypes.bool.isRequired,
  verifiedData: PropTypes.arrayOf(PropTypes.string).isRequired,
  fieldEditable: PropTypes.bool.isRequired,
  errors: PropTypes.object.isRequired,
  setValue: PropTypes.func.isRequired,
  watch: PropTypes.func.isRequired,
};
