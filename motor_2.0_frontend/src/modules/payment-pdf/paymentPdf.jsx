import React, { useEffect } from "react";
import { Form } from "react-bootstrap";
import styled from "styled-components";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import { useForm } from "react-hook-form";
import { ErrorMsg } from "components";
import { useDispatch, useSelector } from "react-redux";
import { Loader } from "components";
import { useMediaPredicate } from "react-media-hook";
import _ from "lodash";
import { SubmitData, clear } from "./paymentPdf.slice";
import { generatePaymentStatusHTML } from "./html";
import swal from "sweetalert";

// const yupValidate = yup.object({
//   enquiryId: yup.string().required("Enquiry Id is required"),
//   orderId: yup.string().required("Order Id is required"),
// });

const PaymentPdf = () => {
  const { handleSubmit, register, errors, watch, setValue, reset } = useForm({
    // resolver: yupResolver(yupValidate),
    mode: "onBlur",
    reValidateMode: "onBlur",
  });

  const { submit, error, loading } = useSelector(
    (state) => state.paymentStatus
  );
  const dispatch = useDispatch();
  const enquiryId = watch("enquiryId");
  const orderId = watch("orderId");

  const submitHandler = (data) => {
    dispatch(clear());
    if (enquiryId || orderId) {
      dispatch(
        SubmitData({
          enquiryId: data.enquiryId,
          orderId: data.orderId,
          url: `${import.meta.env.VITE_API_BASE_URL}/onepay/kmd-tr-status`,
        })
      );
    }
  };

  const paymentData = submit?.[0]?.data?.response && JSON.parse(submit && submit[0]?.data?.response);

  useEffect(() => {
    if (submit && submit[0]?.data) {
      const newTab = window.open("", "_blank");
      const paymentStatusHTML = generatePaymentStatusHTML(paymentData);

      paymentData && newTab.document.open();
      paymentData && newTab.document.write(paymentStatusHTML);
      paymentData && newTab.document.close();
      dispatch(clear());
    }
  }, [paymentData, submit]);

  useEffect(() => {
    if (error) {
      swal("", error, "error").then(() => reset({}));
    }

    return () => {
      dispatch(clear());
    };
  }, [error]);

  const handleReset = () => {
    dispatch(clear());
    setValue("enquiryId", "");
    setValue("orderId", "");
  };

  const lessthan767 = useMediaPredicate("(max-width: 767px)");

  return (
    <div>
      <div>
        <div
          className="row"
          style={{ marginTop: "100px", padding: "0px 25px" }}
        >
          <div
            className="col-md-6 mx-auto"
            style={{
              boxShadow: "1px 4px 26px 7px #dcdcdc",
              padding: "20px 30px",
              borderRadius: "8px",
            }}
          >
            <h4
              className="text-center"
              style={{ fontSize: lessthan767 && "1rem" }}
            >
              Policy Number Generator
            </h4>
            <form onSubmit={handleSubmit(submitHandler)}>
              <FormGroupTag>Enquiry Id</FormGroupTag>
              <Form.Control
                ref={register}
                errors={errors.enquiryId}
                name="enquiryId"
                type="text"
                // maxLength="50"
                // minlength="2"
                placeholder="Enter Enquiry Id"
                size="md"
                onChange={() => dispatch(clear())}
              />
              {!!errors.enquiryId && (
                <ErrorMsg fontSize={"12px"}>
                  {errors.enquiryId.message}
                </ErrorMsg>
              )}

              <FormGroupTag>Order Id</FormGroupTag>
              <Form.Control
                ref={register}
                errors={errors.orderId}
                name="orderId"
                type="text"
                // maxLength="50"
                // minlength="2"
                placeholder="Enter Enquiry Id"
                size="md"
                onChange={() => dispatch(clear())}
              />
              {!!errors.orderId && (
                <ErrorMsg fontSize={"12px"}>{errors.orderId.message}</ErrorMsg>
              )}

              {loading && <Loader />}
              <div className="mt-3" style={{ textAlign: "right" }}>
                <DownloadButton
                  className="reset_btn"
                  style={{ cursor: "pointer", marginRight: "10px" }}
                  onClick={handleReset}
                >
                  Reset
                </DownloadButton>

                <DownloadButton
                  type="submit"
                  className="submit_button"
                  submit={submit}
                  style={{
                    cursor: enquiryId || orderId ? "pointer" : "not-allowed",
                  }}
                  disabled={!enquiryId && !orderId}
                >
                  Submit
                </DownloadButton>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PaymentPdf;

const FormGroupTag = styled(Form.Label)`
  font-size: 12px;
  font-weight: normal;
`;

const DownloadButton = styled.button`
  padding: 5px 20px;
  border-radius: 8px;
  background: ${({ submit }) =>
    submit && submit[0]?.data?.url ? "rgb(189, 212, 0)" : ""};
`;
