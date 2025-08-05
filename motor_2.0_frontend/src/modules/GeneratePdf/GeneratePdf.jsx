import React, { useEffect } from "react";
import { Form } from "react-bootstrap";
import styled from "styled-components";
import { yupResolver } from "@hookform/resolvers/yup";
import * as yup from "yup";
import { useForm } from "react-hook-form";
import { ErrorMsg, brokerEmailFunction } from "components";
import { SubmitData } from "./generate.slice";
import { useDispatch, useSelector } from "react-redux";
import { downloadFile } from "utils";
import { Loader } from "components";
import { clear } from "./generate.slice";
import swal from "sweetalert";
import { useMediaPredicate } from "react-media-hook";
import _ from "lodash";

const yupValidate = yup.object({
  selection: yup.string().required("Enquiry Id is required"),
});

const GeneratePdf = () => {
  const { handleSubmit, register, errors, watch, setValue } = useForm({
    resolver: yupResolver(yupValidate),
    mode: "onBlur",
    reValidateMode: "onBlur",
  });

  const { submit, error, loading } = useSelector((state) => state.generate);
  const { theme_conf } = useSelector((state) => state.home);
  const dispatch = useDispatch();
  const selection = watch("selection");

  const submitHandler = (data) => {
    dispatch(clear());
    if (selection) {
      dispatch(
        SubmitData({
          enquiryId: data.selection,
          url: `${import.meta.env.VITE_API_BASE_URL}/generatePdf`,
        })
      );
    }
  };

  const handleClick = () => {
    if (submit?.pdf_link) {
      downloadFile(submit?.pdf_link, false, true);
      handleReset();
    }
  };

  const handleReset = () => {
    dispatch(clear());
    setValue("selection", "");
  };

  useEffect(() => {
    if (!_.isEmpty(submit)) {
      if (submit?.pdf_link) {
        swal({
          title: "Info",
          text: `Your Policy Number is ${submit?.policy_number}`,
          icon: "success",
          timer: 2000,
        });
      } else {
        swal("Error", "Pdf not found", "error");
      }
    }
    if (error) {
      swal({
        title: "Info",
        content: {
          element: "div",
          attributes: {
            innerHTML: `
              <div style="text-align: left">
                <p><strong>Dear Customer,</strong></p>
                <p>
                  Unable to retrieve the policy PDF from Insurance Company, please retry after 30 minutes. If in case PDF not available, send us an email at 
                  <a href="mailto:${
                    theme_conf?.broker_config?.brokerSupportEmail ||
                    brokerEmailFunction()
                  }" style="color: #0000FF; text-decoration: underline;">${
              theme_conf?.broker_config?.brokerSupportEmail ||
              brokerEmailFunction()
            }</a> along with Enquiry ID.
                </p>
                <p><em>Thank you.</em></p>
              </div>
            `,
          },
        },
        icon: "info",
      }).then((result) => {
        if (result) {
          handleReset();
        }
      });
    }
  }, [submit, error]);

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
                errors={errors.selection}
                name="selection"
                type="text"
                // maxLength="50"
                // minlength="2"
                placeholder="Enter Enquiry Id"
                size="md"
                onChange={() => dispatch(clear())}
              />
              {!!errors.selection && (
                <ErrorMsg fontSize={"12px"}>
                  {errors.selection.message}
                </ErrorMsg>
              )}
              {/* {error && <ErrorMsg fontSize={"12px"}>{error}</ErrorMsg>} */}
              <FormGroupTag>Policy Number</FormGroupTag>
              <Form.Control
                type="text"
                readOnly={true}
                placeholder="Policy Number"
                size="md"
                defaultValue={submit?.policy_number}
              />
              {loading && <Loader />}
              <div className="mt-3" style={{ textAlign: "right" }}>
                <DownloadButton
                  className="reset_btn"
                  style={{ cursor: "pointer", marginRight: "10px" }}
                  onClick={handleReset}
                >
                  Reset
                </DownloadButton>
                {submit?.pdf_link ? (
                  <DownloadButton
                    submit={submit}
                    style={{
                      cursor: submit?.pdf_link ? "pointer" : "not-allowed",
                    }}
                    disabled={!submit?.pdf_link}
                    onClick={handleClick}
                  >
                    Download
                  </DownloadButton>
                ) : (
                  <DownloadButton
                    type="submit"
                    className="submit_button"
                    submit={submit}
                    style={{ cursor: selection ? "pointer" : "not-allowed" }}
                    disabled={!selection}
                  >
                    Submit
                  </DownloadButton>
                )}
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};

export default GeneratePdf;

const FormGroupTag = styled(Form.Label)`
  font-size: 12px;
  font-weight: normal;
`;

const DownloadButton = styled.button`
  padding: 5px 20px;
  border-radius: 8px;
  background: ${({ submit }) => (submit?.pdf_link ? "rgb(189, 212, 0)" : "")};
`;
