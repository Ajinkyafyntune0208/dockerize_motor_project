import { FormGroupTag } from "modules/proposal/style";
import React from "react";
import { Col, Form } from "react-bootstrap";
import _ from "lodash";
import { useDispatch, useSelector } from "react-redux";
import { useLocation } from "react-router-dom";
import swal from "sweetalert";
import { useEffect } from "react";
import {
  clear,
  getInspectionType,
  InspectionPincode,
  Pincode,
} from "modules/proposal/proposal.slice";
import { ErrorMsg } from "components";
import { numOnly } from "utils";


//The component is visible on the Proposal page inside the Vehicle Card in breakin case
const VehicleInspectionType = ({
  register,
  allFieldsReadOnly,
  zd_rti_condition,
  companyAlias,
  errors,
  watch,
  CardData,
  vehicle, //save vehicle data
  setValue
}) => {
  const dispatch = useDispatch();
  const location = useLocation();
  const inspectionTypesOptions = useSelector(
    (state) => state.proposal.inspectionType
  );
  const { temp_data, inspectionPincode } = useSelector((state) => state.proposal);
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  const inspectionType = watch("inspectionType");
  useEffect(() => {
    dispatch(
      getInspectionType({ companyAlias: companyAlias, enquiryId: enquiry_id })
    );
  }, [companyAlias, enquiry_id]);
  const PinCode = watch("inspectionPincode");

  //get state-city through api
  useEffect(() => {
    let companyAlias = temp_data?.selectedQuote?.companyAlias;
    if (PinCode?.length === 6 && companyAlias) {
      dispatch(
        InspectionPincode({
          companyAlias: companyAlias,
          pincode: PinCode,
          enquiryId: enquiry_id,
          rtoCode: temp_data?.corporateVehiclesQuoteRequest?.rtoCode,
        })
      );
    } else {
      dispatch(clear("inspectionPincode"));
    }
  }, [PinCode]);

  //prefill state and city
  useEffect(() => {
    if (!_.isEmpty(inspectionPincode)) {
      setValue("inspectionState", inspectionPincode?.state?.state_name);
      setValue("inspectionStateId", inspectionPincode?.state?.state_id);
      if (inspectionPincode?.city?.length === 1) {
        setValue("inspectionCity", inspectionPincode?.city[0]?.city_name);
        setValue("inspectionCityId", inspectionPincode?.city[0]?.city_id);
      }
    } else {
      setValue("inspectionState", "");
      setValue("inspectionStateId", "");
      setValue("inspectionCity", "");
      setValue("inspectionCityId", "");
    }
  }, [inspectionPincode]);

  // auto selecting if only one option is present.
  const city =
    watch("inspectionCity") ||
    vehicle?.city ||
    CardData?.vehicle?.city ||
    (!_.isEmpty(inspectionPincode?.city) &&
    inspectionPincode?.city?.length === 1 &&
    inspectionPincode?.city[0]?.city_name);
  useEffect(() => {
    if (city) {
      const city_id = inspectionPincode?.city?.filter(({ city_name }) => city_name === city);
      !_.isEmpty(city_id)
        ? setValue("inspectionCityId", city_id[0]?.city_id)
        : setValue(
            "inspectionCityId",
            vehicle?.cityId || CardData?.vehicle?.cityId
          );
    }
  }, [city, inspectionPincode]);

  const pincodeOptions = () => {
    // Determine if a pin code option should be selected based on various conditions.
    const selectedPin = (city_name) => {
      return (
        CardData?.vehicle?.city?.trim() === city_name?.trim() ||
        (inspectionPincode?.city?.length === 1 && !CardData?.vehicle?.city?.trim()) ||
        (_.isEmpty(CardData?.vehicle) &&
          _.isEmpty(vehicle) &&
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
        {inspectionPincode?.city?.map(({ city_name }, index) => (
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
  return (
    <>
      <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
        <div className="py-2 fname">
          <FormGroupTag mandatory>Inspection type</FormGroupTag>
          <Form.Control
            autoComplete="off"
            as="select"
            size="sm"
            ref={register}
            name="inspectionType"
            className="title_list"
            readOnly={allFieldsReadOnly}
            style={{ cursor: "pointer" }}
          >
            {inspectionTypesOptions?.map((item, index) => (
              <option key={index} value={item}>
                {item}
              </option>
            ))}
          </Form.Control>
          {_.isEmpty(_.compact(zd_rti_condition)) && (
            <Form.Text className="text-muted">
              <text style={{ color: "#bdbdbd" }}>
                (Required based on the following Addon Declaration)
              </text>
            </Form.Text>
          )}
        </div>
      </Col>
      {/* {when the user select inspectype type manual then only this(inspectionAddress) field will be visible} */}
      {inspectionType &&
        inspectionType?.toUpperCase() === "MANUAL" &&
        companyAlias === "reliance" && (
          <>
            <Col
              xs={12}
              sm={12}
              md={12}
              lg={12}
              xl={12}
              className=" mt-1"
              // style={{ marginBottom: "-10px" }}
            >
              <div className="py-2">
                <FormGroupTag mandatory>Inspection Address</FormGroupTag>
                <Form.Control
                  as="textarea"
                  rows={2}
                  autoComplete="none"
                  spellCheck="false"
                  required={true}
                  name="inspectionAddress"
                  maxLength={`${
                    ["reliance", "hdfc_ergo"].includes(
                      temp_data?.selectedQuote?.companyAlias
                    )
                      ? 200
                      : 120
                  }`}
                  // readOnly={
                  //   (((resubmit && !_.isEmpty(verifiedData?.includes("address"))) ||
                  //     (watch("address") && fieldsNonEditable)) &&
                  //     temp_data?.selectedQuote?.companyAlias === "sbi") ||
                  //   !fieldEditable
                  // }
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
                  errors={errors?.inspectionAddress}
                  isInvalid={errors?.inspectionAddress}
                  size="sm"
                />
                {errors?.inspectionAddress ||
                errors?.inspectionAddress ||
                errors?.inspectionAddress ||
                errors?.address ? (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.inspectionAddress?.message ||
                      errors?.inspectionAddress?.message ||
                      errors?.inspectionAddress?.message ||
                      errors?.inspectionAddress?.message}
                  </ErrorMsg>
                ) : (
                  <Form.Text className="text-muted">
                    <text style={{ color: "#bdbdbd" }}>
                      {`(${watch("inspectionAddress")?.length}/${
                        temp_data?.selectedQuote?.companyAlias === "reliance"
                          ? 200
                          : 120
                      })`}
                    </text>
                  </Form.Text>
                )}
              </div>
            </Col>
            <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
              <div className="py-2">
                <FormGroupTag mandatory>Pincode</FormGroupTag>
                <Form.Control
                  name="inspectionPincode"
                  autoComplete="none"
                  required={true}
                  ref={register}
                  type="tel"
                  placeholder="Enter Pincode"
                  errors={errors?.inspectionPincode}
                  isInvalid={errors?.inspectionPincode}
                  size="sm"
                  onKeyDown={numOnly}
                  maxLength="6"
                />
                {!!errors?.inspectionPincode && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.inspectionPincode?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
            <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
              <div className="py-2">
                <FormGroupTag mandatory>State</FormGroupTag>
                <Form.Control
                  name="inspectionState"
                  ref={register}
                  type="text"
                  required={true}
                  placeholder="Select State"
                  style={{ cursor: "not-allowed" }}
                  errors={errors?.inspectionState}
                  isInvalid={errors?.inspectionState}
                  size="sm"
                  readOnly
                />
                {!!errors?.inspectionState && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.inspectionState?.message}
                  </ErrorMsg>
                )}
              </div>
              <input
                name="inspectionStateId"
                ref={register}
                type="hidden"
                value={inspectionPincode?.inspectionState?.inspectionStateId}
              />
            </Col>
            <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
              <div
                className="py-2 fname"
              >
                <FormGroupTag mandatory>City</FormGroupTag>
                <Form.Control
                  as="select"
                  size="sm"
                  ref={register}
                  name={`inspectionCity`}
                  required={true}
                  errors={errors?.inspectionCity}
                  isInvalid={errors?.inspectionCity}
                  style={{
                    cursor: "pointer",
                  }}
                >
                  {pincodeOptions()}
                </Form.Control>
                {!!errors?.city && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.inspectionCity?.message}
                  </ErrorMsg>
                )}
              </div>
              <input name="inspectionCityId" ref={register} type="hidden" />
            </Col>
          </>
        )}
    </>
  );
};

export default VehicleInspectionType;
