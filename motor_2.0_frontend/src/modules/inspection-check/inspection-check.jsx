import React, { useEffect } from "react";
import { CompactCard, Button, ErrorMsg } from "components";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import { useForm, Controller } from "react-hook-form";
import styled from "styled-components";
import { Form, Row, Col } from "react-bootstrap";
import { useDispatch, useSelector } from "react-redux";
import { VehicleType, clear, PrevIc, SubmitData } from "./inspection.slice";
import swal from "sweetalert";
import { useHistory } from "react-router";
import { reloadPage } from "utils";

const yupValidate = yup.object({
  inspectionNo: yup.string().required("Inspection No. is required"),
});

export const Inspection = () => {
  const dispatch = useDispatch();
  const history = useHistory();
  const { error, submit } = useSelector((state) => state.inspection);

  const { handleSubmit, register, errors, watch } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "onBlur",
    reValidateMode: "onBlur",
  });

  //onError
  useEffect(() => {
    if (error) {
      swal("Error", error, "error");
    }
    return () => {
      dispatch(clear());
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [error]);

  //onSuccess
  useEffect(() => {
    if (submit) {
      reloadPage(submit?.proposalUrl);
    }

    return () => {
      dispatch(clear("submit"));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [submit]);

  const onSubmit = (data) => {
    dispatch(SubmitData(data));
  };

  return (
    <StyledDiv className="h-100 w-100 mt-5 px-5">
      <StyledH3 className="text-center w-100 mx-auto mt-3">
        Check your Inspection status
      </StyledH3>
      <div className="px-5">
        <CompactCard title="Inspection Status">
          <Form
            onSubmit={handleSubmit(onSubmit)}
            className="mt-3 px-1"
            style={{ marginBottom: "-10px" }}
          >
            <Row style={{ margin: "-60px -20px 20px -30px" }} className="p-2">
              <Col xs={12} sm={12} md={12} lg={12} xl={12} className="">
                <div className="py-2">
                  <FormGroupTag>Inspection Number</FormGroupTag>
                  <Form.Control
                    type="text"
                    placeholder="Inspection Number"
                    name={`inspectionNo`}
                    ref={register}
                    maxLength="50"
                    minlength="2"
                    size="sm"
                  />
                  {!!errors?.inspectionNo && (
                    <ErrorMsg fontSize={"12px"}>
                      {errors?.inspectionNo.message}
                    </ErrorMsg>
                  )}
                </div>
              </Col>
              <Col
                sm="12"
                md="12"
                lg="12"
                xl="12"
                className="d-flex justify-content-end mt-4"
              >
                <Button
                  buttonStyle="outline-solid"
                  borderRadius={"10px"}
                  type="submit"
                >
                  Check Inspection
                </Button>
              </Col>
            </Row>
          </Form>
        </CompactCard>
      </div>
    </StyledDiv>
  );
};

const FormGroupTag = styled(Form.Label)`
  font-size: 12px;
  font-weight: normal;
`;

const StyledDiv = styled.div``;

const StyledH3 = styled.h3`
  color: rgb(74, 74, 74);
  font-size: 30px;
  @media (max-width: 767px) {
    font-size: 25px;
  }
`;
