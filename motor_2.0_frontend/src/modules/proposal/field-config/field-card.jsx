import React, { useState, useEffect } from "react";
import { Row, Col, Form } from "react-bootstrap";
import { CompactCard, Button, ErrorMsg } from "components";
import { useForm, Controller } from "react-hook-form";
import { useDispatch, useSelector } from "react-redux";
import swal from "sweetalert";
import { clear, SetFields } from "modules/proposal/proposal.slice";
import _ from "lodash";
import Checkbox from "components/inputs/checkbox/checkbox";
import { fieldList } from "./helper";
import Select from "react-select";
import { FormGroupTag } from "modules/proposal/style";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";

export const FieldCard = ({
  ic,
  owner_type,
  section,
  fields,
  Broker,
  ckycFields,
}) => {
  const [poi, SetPoi] = useState(false);
  const [poa, SetPoa] = useState(false);
  const [ckyc, setCkyc] = useState(false);
  const yupValidate = yup.object({
    ...(poi && { poilist: yup.string().required("Required") }),
    ...(poa && { poalist: yup.string().required("Required") }),
    ...(ckyc && { ckyc_type: yup.string().required("Required") }),
  });

  const { handleSubmit, register, setValue, watch, errors, control } = useForm({
    defaultValues: fields ? fields : {},
    resolver: yupResolver(yupValidate),
    mode: "onBlur",
    reValidateMode: "onBlur",
  });

  const dispatch = useDispatch();
  const fieldsWatch = watch("fields");

  useEffect(() => {
    SetPoi(fieldsWatch?.includes("poi"));
    SetPoa(fieldsWatch?.includes("poa"));
    setCkyc(fieldsWatch?.includes("ckyc"));
  }, [fieldsWatch]);

  //prefill
  useEffect(() => {
    if (!_.isEmpty(fields)) {
      //setval Array

      const fieldsArray = fields?.fields ? fields?.fields : fields;
      const setVal = [
        fieldsArray?.includes("gstNumber") && "gstNumber",
        fieldsArray?.includes("maritalStatus") && "maritalStatus",
        fieldsArray?.includes("occupation") && "occupation",
        fieldsArray?.includes("panNumber") && "panNumber",
        fieldsArray?.includes("dob") && "dob",
        fieldsArray?.includes("gender") && "gender",
        fieldsArray?.includes("vehicleColor") && "vehicleColor",
        fieldsArray?.includes("hypothecationCity") && "hypothecationCity",
        fieldsArray?.includes("cpaOptOut") && "cpaOptOut",
        fieldsArray?.includes("email") && "email",
        fieldsArray?.includes("pucNo") && "pucNo",
        fieldsArray?.includes("pucExpiry") && "pucExpiry",
        fieldsArray?.includes("representativeName") && "representativeName",
        fieldsArray?.includes("cpaOptIn") && "cpaOptIn",
        fieldsArray?.includes("ncb") && "ncb",
        fieldsArray?.includes("InspectionType") && "InspectionType",
        fieldsArray?.includes("ckyc") && "ckyc",
        fieldsArray?.includes("fileupload") && "fileupload",
        fieldsArray?.includes("poi") && "poi",
        fieldsArray?.includes("poa") && "poa",
        fieldsArray?.includes("photo") && "photo",
        fieldsArray?.includes("fatherName") && "fatherName",
        fieldsArray?.includes("relationType") && "relationType",
        fieldsArray?.includes("inspectionType") && "inspectionType",
      ];
      setValue("fields", setVal);
    }
  }, [fields]);

  //prefilling multiselects
  useEffect(() => {
    if (ckycFields) {
      !_.isEmpty(ckycFields?.poilist) &&
        setValue("poilist", ckycFields?.poilist);
      !_.isEmpty(ckycFields?.poalist) &&
        setValue("poalist", ckycFields?.poalist);
      !_.isEmpty(ckycFields?.ckyc_type) &&
        setValue("ckyc_type", ckycFields?.ckyc_type);
    }
  }, [ckycFields]);

  const watchPoi = watch("poilist");
  const watchPoa = watch("poalist");
  const watchCkyc = watch("ckyc_type");

  const onSubmit = (data) => {
    let request = {
      company_alias: ic,
      owner_type,
      section,
      fields: {
        fields: data?.fields.map((el) => (el ? el : 0)),
        poilist: poi ? watchPoi : [],
        poalist: poa ? watchPoa : [],
        ckyc_type: ckyc ? watchCkyc : [],
        modifiedBy: atob(localStorage?.configKey).replace(/[0-9]/g, ""),
      },
    };
    dispatch(SetFields(request, Broker));
  };

  const excludes = [
    "occupation",
    "maritalStatus",
    "dob",
    "gender",
    "cpaOptIn",
    "cpaOptOut",
  ];
  const excludesIndividual = ["representativeName"];

  const poiDetails = [
    {
      value: "panNumber",
      label: "PAN Number",
      placeholder: "Upload PAN Card Image",
      length: 10,
      fileKey: "panCard",
    },
    {
      value: "gstNumber",
      label: "GST Number",
      placeholder: "Upload GST Number Certificate",
      length: 15,
      fileKey: "gst_certificate",
    },
    {
      value: "drivingLicense",
      label: "Driving License Number",
      placeholder: "Upload Driving License Image",
      fileKey: "driving_license",
    },
    {
      value: "voterId",
      label: "Voter ID Number",
      placeholder: "Upload Voter ID Card Image",
      fileKey: "voter_card",
    },
    {
      value: "passportNumber",
      label: "Passport Number",
      placeholder: "Upload Passport Image",
      length: 8,
      fileKey: "passport_image",
    },
    {
      value: "eiaNumber",
      label: "e-Insurance Account Number",
      fileKey: "eiaNumber",
    },
    {
      value: "aadharNumber",
      label: "Adhaar Number",
      placeholder: "Upload Adhaar Card Image",
      length: 12,
      fileKey: "aadharCard",
    },
    {
      value: "cinNumber",
      label: "CIN Number",
      placeholder: "Upload CIN Number Certificate",
      fileKey: "cinNumber",
    },
    {
      label: "NREGA Job Card",
      value: "nregaJobCard",
      placeholder: "Upload NREGA Card Image",
      length: 18,
      fileKey: "nrega_job_card_image",
    },
    {
      label: "National Population Letter",
      value: "nationalPopulationRegisterLetter",
      placeholder: "Upload Letter",
      length: 20,
      fileKey: "national_population_register_letter_image",
    },
    {
      label: "Registration Certificate",
      value: "registrationCertificate",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "registration_certificate_image",
    },
    {
      label: "Certificate of Incorporation",
      value: "cretificateOfIncorporaion",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "certificate_of_incorporation_image",
    },
  ];

  const poaDetails = [
    {
      value: "gstNumber",
      label: "GST Number",
      placeholder: "Upload GST Number Certificate",
      length: 15,
      fileKey: "gst_certificate",
    },
    {
      value: "drivingLicense",
      label: "Driving License Number",
      placeholder: "Upload Driving License Image",
      fileKey: "driving_license",
    },
    {
      value: "voterId",
      label: "Voter ID Number",
      placeholder: "Upload Voter ID Card Image",
      fileKey: "voter_card",
    },
    {
      value: "passportNumber",
      label: "Passport Number",
      placeholder: "Upload Passport Image",
      length: 8,
      fileKey: "passport_image",
    },
    {
      value: "eiaNumber",
      label: "e-Insurance Account Number",
      fileKey: "eiaNumber",
    },
    {
      value: "aadharNumber",
      label: "Adhaar Number",
      placeholder: "Upload Adhaar Card Image",
      length: 12,
      fileKey: "aadharCard",
    },
    {
      label: "NREGA Job Card",
      value: "nregaJobCard",
      placeholder: "Upload NREGA Card Image",
      length: 18,
      fileKey: "nrega_job_card_image",
    },
    {
      label: "National Population Letter",
      value: "nationalPopulationRegisterLetter",
      placeholder: "Upload Letter",
      length: 20,
      fileKey: "national_population_register_letter_image",
    },
    {
      label: "Registration Certificate",
      value: "registrationCertificate",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "registration_certificate_image",
    },
    {
      label: "Certificate of Incorporation",
      value: "cretificateOfIncorporaion",
      placeholder: "Upload Certificate",
      length: 20,
      fileKey: "certificate_of_incorporation_image",
    },
  ];

  return (
    <CompactCard title="Field List">
      <Form onSubmit={handleSubmit(onSubmit)}>
        <Row style={{ marginTop: "-30px" }}>
          {fieldList.map((item, index) =>
            (!excludes.includes(item) || owner_type === "I") &&
            (!excludesIndividual.includes(item) || owner_type === "C") ? (
              <Col sm="6" md="4" lg="3" xl="3" className="my-1">
                <Checkbox
                  id={item}
                  register={register}
                  fieldName={_.capitalize(
                    item
                      .replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`)
                      .replace(/_/gi, " ")
                  )}
                  index={index}
                  name={"fields"}
                  tooltipData={""}
                />
              </Col>
            ) : (
              <noscript />
            )
          )}
        </Row>
        <Row>
          {watch("fields")?.includes("poi") && (
            <Col lg="6" md="6" sm="12">
              <div className="py-2 fname">
                <FormGroupTag>Proof of Identity List</FormGroupTag>
                <Controller
                  control={control}
                  defaultValue={ckycFields?.poilist || []}
                  name="poilist"
                  render={({ onChange, onBlur, value, name }) => (
                    <Select
                      options={poiDetails}
                      closeMenuOnSelect={false}
                      isMulti={true}
                      name={name}
                      ref={register}
                      value={value}
                      onChange={onChange}
                      onBlur={onBlur}
                    />
                  )}
                />
                {!!errors?.poilist && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.poilist?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
          )}
          {watch("fields")?.includes("poa") && (
            <Col lg="6" md="6" sm="12">
              <div className="py-2 fname">
                <FormGroupTag>Proof of Address List</FormGroupTag>
                <Controller
                  control={control}
                  defaultValue={ckycFields?.poalist || []}
                  name="poalist"
                  render={({ onChange, onBlur, value, name }) => (
                    <Select
                      options={poaDetails}
                      closeMenuOnSelect={false}
                      isMulti={true}
                      name={name}
                      ref={register}
                      value={value}
                      onChange={onChange}
                      onBlur={onBlur}
                    />
                  )}
                />
                {!!errors?.poalist && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.poalist?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
          )}
          {watch("fields")?.includes("ckyc") && (
            <Col lg="6" md="6" sm="12">
              <div className="py-2 fname">
                <FormGroupTag>CKYC Type</FormGroupTag>
                <Controller
                  control={control}
                  name="ckyc_type"
                  defaultValue={ckycFields?.ckyc_type || []}
                  render={({ onChange, onBlur, value, name }) => (
                    <Select
                      options={poiDetails}
                      closeMenuOnSelect={false}
                      isMulti={true}
                      name={name}
                      ref={register}
                      value={value}
                      onChange={onChange}
                      onBlur={onBlur}
                    />
                  )}
                />
                {!!errors?.ckyc_type && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.ckyc_type?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
          )}
        </Row>

        <Row>
          <Col
            sm={12}
            lg={12}
            md={12}
            xl={12}
            className="d-flex justify-content-end mt-5 mx-auto"
          >
            <Button
              type="submit"
              buttonStyle="outline-solid"
              className=""
              hex1={"#4ca729"}
              hex2={"#4ca729"}
              borderRadius="5px"
              color="white"
            >
              <text
                style={{
                  fontSize: "15px",
                  padding: "-20px",
                  margin: "-20px -5px -20px -5px",
                  fontWeight: "400",
                }}
              >
                Save Fields
              </text>
            </Button>
          </Col>
        </Row>
      </Form>
    </CompactCard>
  );
};
