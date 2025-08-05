import React, { useEffect } from "react";
import { Row, Col, Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";
import { CompactCard, Button, ErrorMsg } from "components";
import { useForm } from "react-hook-form";
import { yupResolver } from "@hookform/resolvers/yup";
import { useDispatch, useSelector } from "react-redux";
import swal from "sweetalert";
import {
  GetIc,
  clear,
  GetFields,
  fields as clearFields,
  icList as clearIcs,
} from "modules/proposal/proposal.slice";
import _ from "lodash";
import { FieldCard } from "./field-card";
import { yupValidate } from "./helper";
import { Brokers } from "config/helper";

export const FieldConfig = () => {
  const { handleSubmit, register, errors, watch } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "onBlur",
    reValidateMode: "onBlur",
  });
  const dispatch = useDispatch();
  const { error, icList, success, fields, ckycFields } = useSelector(
    (state) => state.proposal
  );

  //Broker
  const SelectedBroker = watch("broker");

  //load all company_alias
  useEffect(() => {
    //clearing previous data
    dispatch(clearIcs([]));
    dispatch(GetIc(SelectedBroker));
  }, [SelectedBroker, dispatch]);

  //error
  useEffect(() => {
    if (error) {
      swal("Error", error, "error");
    }
    return () => {
      dispatch(clear());
    };
  }, [dispatch, error]);

  //success
  useEffect(() => {
    if (success) {
      swal("Success", "Fields updated", "success");
    }
    return () => {
      dispatch(clear());
    };
  }, [dispatch, success]);

  //onChange - clearing fields
  const IC = watch("company_alias");
  const section = watch("section");
  const owner_type = watch("owner_type");

  useEffect(() => {
    dispatch(clearFields(null));
  }, [owner_type, section, IC, SelectedBroker, dispatch]);

  const onSubmit = (data) => {
    dispatch(GetFields(data, SelectedBroker));
  };

  return (
    <>
      <CompactCard
        title={`${
          import.meta.env.VITE_BROKER === "FYNTUNE" ? "Master " : ""
        }Proposal Field Configurator`}
      >
        <Form onSubmit={handleSubmit(onSubmit)}>
          <Row style={{ marginTop: "-35px" }}>
            {import.meta.env.VITE_BROKER === "FYNTUNE" && (
              <Col xs="12" sm="12" md="6" lg="4" xl="4">
                <div className="py-2 fname">
                  <FormGroupTag>Broker</FormGroupTag>
                  <Form.Control
                    autoComplete="none"
                    as="select"
                    size="sm"
                    ref={register}
                    name={`broker`}
                    style={{ cursor: "pointer" }}
                  >
                    {Brokers.map(({ url, name }) => (
                      <option
                        value={url}
                        selected={import.meta.env.VITE_BROKER === url}
                      >
                        {name}
                      </option>
                    ))}
                  </Form.Control>
                  {!!errors?.broker && (
                    <ErrorMsg fontSize={"12px"}>
                      {errors?.broker?.message}
                    </ErrorMsg>
                  )}
                </div>
              </Col>
            )}
            <Col xs="12" sm="12" md="6" lg="4" xl="4">
              <div className="py-2 fname">
                <FormGroupTag>Insurance Company</FormGroupTag>
                <Form.Control
                  autoComplete="none"
                  as="select"
                  size="sm"
                  ref={register}
                  name={`company_alias`}
                  style={{ cursor: "pointer" }}
                >
                  <option value={"@"}>Select</option>
                  <option value={"all"}>All</option>
                  {icList.map((el, index) => (
                    <option value={el}>
                      {_.capitalize(el.replace(/_/gi, " "))}
                    </option>
                  ))}
                </Form.Control>
                {!!errors?.company_alias && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.company_alias?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
            <Col xs="12" sm="12" md="6" lg="4" xl="4">
              <div className="py-2 fname">
                <FormGroupTag>Section</FormGroupTag>
                <Form.Control
                  autoComplete="none"
                  as="select"
                  size="sm"
                  ref={register}
                  name={`section`}
                  style={{ cursor: "pointer" }}
                >
                  <option value={"all"}>All</option>
                  <option value={"car"}>Car</option>
                  <option value={"bike"}>Bike</option>
                  <option value={"cv"}>Cv</option>
                </Form.Control>
                {!!errors?.section && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.section?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
            <Col xs="12" sm="12" md="6" lg="4" xl="4">
              <div className="py-2 fname">
                <FormGroupTag>Owner Type</FormGroupTag>
                <Form.Control
                  autoComplete="none"
                  as="select"
                  size="sm"
                  ref={register}
                  name={`owner_type`}
                  style={{ cursor: "pointer" }}
                >
                  <option value={"I"}>Individual</option>
                  <option value={"C"}>Company</option>
                </Form.Control>
                {!!errors?.owner_type && (
                  <ErrorMsg fontSize={"12px"}>
                    {errors?.owner_type?.message}
                  </ErrorMsg>
                )}
              </div>
            </Col>
            <Col
              sm={12}
              lg={12}
              md={12}
              xl={12}
              className="d-flex justify-content-end mt-3 mx-auto"
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
                  Get Fields
                </text>
              </Button>
            </Col>
          </Row>
        </Form>
      </CompactCard>
      {fields && (
        <FieldCard
          ic={IC}
          section={section}
          owner_type={owner_type}
          fields={fields}
          ckycFields={ckycFields}
          Broker={SelectedBroker}
        />
      )}
    </>
  );
};
