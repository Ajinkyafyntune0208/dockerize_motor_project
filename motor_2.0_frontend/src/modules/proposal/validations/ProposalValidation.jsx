import React, { useEffect, useState } from "react";
import { FormGroupTag } from "../style";
import { Form } from "react-bootstrap";
import { Controller, useForm } from "react-hook-form";
import { ErrorMsg, CompactCard } from "components";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import _ from "lodash";
import {
  ValidationConfig,
  getValidationConfig,
  getIcList,
  clear,
} from "modules/Home/home.slice";
import { useDispatch, useSelector } from "react-redux";
import styled from "styled-components";
import { Row, Col } from "react-bootstrap";
import Select from "react-select";
import swal from "sweetalert";

const yupValidate = yup.object({
  chasisNomax: yup
    .string()
    .required("Maximum Number is Required")
    .matches(/^[a-z0-9]+$/, "Must be only digits")
    .trim(),
  engineNomax: yup
    .string()
    .required("Maximum Number is Required")
    .matches(/^[0-9]+$/, "Must be only digits")
    .trim(),
  chasisNomin: yup
    .string()
    .required("Minimum Number is Required")
    .matches(/^[0-9]+$/, "Must be only digits")
    .trim(),
  engineNomin: yup
    .string()
    .required("Minimum Number is Required")
    .matches(/^[0-9]+$/, "Must be only digits")
    .trim(),
  eNumberSelectedIc: yup.string().required("IC is Required"),
  journeytype: yup.string().required("Journey Type is Required"),
});

const ProposalValidation = () => {
  const [prefillData, setPrefillData] = useState([]);
  const {
    validationConfig: validation,
    icList,
    error,
  } = useSelector((state) => state.home);
  const [data, setData] = useState();

  const { register, errors, watch, control, handleSubmit, reset, setValue } =
    useForm({
      resolver: yupResolver(yupValidate),
      mode: "all",
      reValidateMode: "onBlur",
    });

  const selectedIc = watch("eNumberSelectedIc");
  const selectedjourney = watch("journeytype");

  useEffect(() => {
    !_.isEmpty(validation)
      ? setPrefillData([...validation])
      : setPrefillData([]);
  }, [validation, selectedIc, selectedjourney]);

  //  errors
  useEffect(() => {
    if (error) {
      swal("alert !", error, "error");
    }
    return () => {
      dispatch(clear());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [error]);

  const options = icList?.map((item) => {
    return {
      label: _.capitalize(item.replace(/_/gi, " ")),
      name: item,
      id: item,
      value: item,
    };
  });

  const journeytype = [
    {
      label: "New",
      name: "NEW",
      id: "New",
      value: "NEW",
    },
    {
      label: "Rollover",
      name: "Rollover",
      id: "Rollover",
      value: "Rollover",
    },
  ];

  const dispatch = useDispatch();

  useEffect(() => {
    dispatch(getValidationConfig());
    dispatch(getIcList());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedIc, selectedjourney]);

  // prefilling form
  useEffect(() => {
    if (!_.isEmpty(data)) {
      reset(data);
    }
  }, [data]);

  useEffect(() => {
    if (
      !_.isEmpty(selectedIc) &&
      !_.isEmpty(validation) &&
      !_.isEmpty(selectedjourney)
    ) {
      const daataa = validation?.map(
        (item) => item?.IcName === selectedIc?.value && item
      );
      const daata = _.compact(daataa)[0];
      if (!_.isEmpty(daata)) {
        setValue("chasisNomax", daata[selectedjourney?.value]?.chasisNomax);
        setValue("chasisNomin", daata[selectedjourney?.value]?.chasisNomin);
        setValue("engineNomax", daata[selectedjourney?.value]?.engineNomax);
        setValue("engineNomin", daata[selectedjourney?.value]?.engineNomin);
      } else {
        setValue("chasisNomax", "");
        setValue("chasisNomin", "");
        setValue("engineNomax", "");
        setValue("engineNomin", "");
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedIc, validation, selectedjourney]);

  const onSubmit = (data) => {
    if (theError === true) {
      return swal("Error", "Please fill all the details", "error");
    } else {
      const index = prefillData
        .map((item) => item?.IcName)
        .indexOf(selectedIc?.name);
      if (index !== -1) {
        prefillData.splice(index, 1);
        setPrefillData(prefillData);
      }

      let VData = new Object();
      VData.IcName = selectedIc?.value;
      VData[selectedjourney?.value] = {
        engineNomax: data?.engineNomax,
        engineNomin: data?.engineNomin,
        chasisNomax: data?.chasisNomax,
        chasisNomin: data?.chasisNomin,
      };
      const singleIc = _.compact(
        validation?.map((item) => item.IcName === selectedIc?.value && item)
      );

      let response = [...prefillData, { ...singleIc[0], ...VData }];
      dispatch(ValidationConfig(response));
      swal("Success", "response submitted successfully", "success");
    }
  };

  let theError;

  return (
    <>
      <CompactCard title={"Proposal Validations"}>
        <Form onSubmit={handleSubmit(onSubmit)}>
          <div className="row">
            <div className="col-12 mx-auto">
              <Row style={{ paddingTop: "10px", paddingBottom: "10px" }}>
                <Col lg={6} md={6}>
                  <span
                    style={{
                      margin: "8px",
                      display: "flex",
                      fontSize: "15px",
                      fontWeight: "bold",
                    }}
                  >
                    Select IC
                  </span>
                  <Controller
                    control={control}
                    name="eNumberSelectedIc"
                    render={({ onChange, value, name }) => (
                      <Select
                        name={name}
                        value={value}
                        options={options}
                        classNamePrefix="select"
                        closeMenuOnSelect={true}
                        ref={register}
                        onChange={onChange}
                      />
                    )}
                  />
                  {errors && errors?.eNumberSelectedIc && (
                    <ErrorMsg fontSize={"12px"}>
                      {errors?.eNumberSelectedIc?.message}
                    </ErrorMsg>
                  )}
                </Col>
                <Col lg={6} md={6}>
                  <span
                    className="mb-0.5rem"
                    style={{
                      margin: "8px",
                      display: "flex",
                      fontSize: "15px",
                      fontWeight: "bold",
                    }}
                  >
                    Select New/Rollover
                  </span>
                  <Controller
                    control={control}
                    name="journeytype"
                    render={({ onChange, value, name }) => (
                      <Select
                        name={name}
                        value={value}
                        options={journeytype}
                        classNamePrefix="select"
                        closeMenuOnSelect={true}
                        ref={register}
                        onChange={onChange}
                      />
                    )}
                  />
                  {errors && errors?.businessType && (
                    <ErrorMsg fontSize={"12px"}>
                      {errors?.businessType?.message}
                    </ErrorMsg>
                  )}
                </Col>
              </Row>

              <div
                style={{
                  display: "flex",
                  width: "100%",
                  marginTop: "18px",
                }}
              >
                <FormGroupTag
                  style={{
                    display: "flex",
                    fontSize: "15px",
                    fontWeight: "bold",
                  }}
                >
                  Engine Number{" "}
                </FormGroupTag>
              </div>
              <>
                <Row>
                  <Col lg={6} md={6} sm={12}>
                    <StyledSpan>Min</StyledSpan>
                    <div className="py-2 w-100">
                      <Form.Control
                        name={`engineNomin`}
                        ref={register}
                        type="number"
                        placeholder="Enter Minimum Number"
                        size="sm"
                        maxLength="10"
                      />
                      {errors && errors?.engineNomin && (
                        <ErrorMsg fontSize={"12px"}>
                          {errors?.engineNomin?.message}
                        </ErrorMsg>
                      )}
                    </div>
                  </Col>
                  <Col lg={6} md={6} sm={12}>
                    <StyledSpan>Max</StyledSpan>
                    <div className="py-2 w-100">
                      <Form.Control
                        name={`engineNomax`}
                        ref={register}
                        type="number"
                        min={"1"}
                        placeholder="Enter Maximum Number"
                        size="sm"
                      />

                      {errors && errors?.engineNomax && (
                        <ErrorMsg fontSize={"12px"}>
                          {errors?.engineNomax?.message}
                        </ErrorMsg>
                      )}
                    </div>
                  </Col>
                </Row>
              </>

              <div
                style={{
                  display: "flex",
                  width: "100%",
                  marginTop: "18px",
                }}
              >
                <FormGroupTag style={{ fontSize: "15px", fontWeight: "bold" }}>
                  Chassis Number{" "}
                </FormGroupTag>
              </div>
              <>
                <Row>
                  <Col lg={6} md={6} sm={12}>
                    <StyledSpan>Min</StyledSpan>
                    <div className="py-2 w-100">
                      <Form.Control
                        name={`chasisNomin`}
                        ref={register}
                        type="number"
                        placeholder="Enter Minimum Number"
                        size="sm"
                        maxLength="10"
                      />
                      {errors && errors?.chasisNomin && (
                        <ErrorMsg fontSize={"12px"}>
                          {errors?.chasisNomin?.message}
                        </ErrorMsg>
                      )}
                    </div>
                  </Col>
                  <Col lg={6} md={6} sm={12}>
                    <StyledSpan>Max</StyledSpan>
                    <div className="py-2 w-100">
                      <Form.Control
                        name={`chasisNomax`}
                        ref={register}
                        type="number"
                        placeholder="Enter Maximum Number"
                        errors={errors?.chasisNomax}
                        size="sm"
                      />
                      {errors && errors?.chasisNomax && (
                        <ErrorMsg fontSize={"12px"}>
                          {errors?.chasisNomax?.message}
                        </ErrorMsg>
                      )}
                    </div>
                  </Col>
                </Row>
              </>

              {watch("pNumberSelectedIc") &&
                watch("pNumberSelectedIc").map((x) => (
                  <>
                    <div style={{ fontSize: "13px" }}>{x?.label}</div>
                    <div className="row">
                      <div className="col-4 d-flex">
                        <div className="py-2 w-100">
                          <Form.Control
                            name={`policyNo.${x.label}.min`}
                            ref={register}
                            type="number"
                            placeholder="Enter Minimum Number"
                            size="sm"
                            maxLength="10"
                          />
                        </div>
                        <span className="ml-2" style={{ marginTop: "11px" }}>
                          Min
                        </span>
                      </div>
                      <div className="col-4 d-flex">
                        <div className="py-2 w-100">
                          <Form.Control
                            name={`policyNo.${x.label}.max`}
                            ref={register}
                            type="number"
                            placeholder="Enter Maximum Number"
                            onTouchEnd
                            size="sm"
                          />
                        </div>
                        <span className="ml-2" style={{ marginTop: "11px" }}>
                          Max
                        </span>
                      </div>
                    </div>
                  </>
                ))}
              <Row>
                <Col lg={6} md={6}></Col>
                <Col lg={6} md={6}>
                  <div
                    className="text-center py-3 mt-1rem "
                    style={{
                      display: "flex",
                      justifyContent: "space-around",
                      marginTop: "20px",
                    }}
                  >
                    <SubmitBtn type="submit">Apply Validations</SubmitBtn>
                    <SubmitBtn
                      onClick={() => dispatch(ValidationConfig([]))}
                      type="reset"
                    >
                      reset
                    </SubmitBtn>
                  </div>
                </Col>
              </Row>
            </div>
          </div>
        </Form>
      </CompactCard>
    </>
  );
};

const SubmitBtn = styled.button`
  padding: 8px 30px;
  border: none;
  background: green;
  color: #fff;
  border-radius: 20px;
`;

const StyledSpan = styled.span`
  font-size: 15px;
`;

export default ProposalValidation;
