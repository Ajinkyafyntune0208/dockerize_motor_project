import React from "react";
import { useLocation } from "react-router-dom";
import styled from "styled-components";
import { Row } from "react-bootstrap";

export default function ErrorPage() {
  const location = useLocation();
  const query = new URLSearchParams(location.search);
  let error = query.get("error");
  error = error && JSON.parse(atob(error))?.msg;

  return (
    <Row
      className="text-center w-100 mx-auto"
      style={{ display: "flex", height: "90vh" }}
    >
      <Top className="mx-auto" style={{ width: "50%" }}>
        <div className="mt-4 d-flex justify-content-center w-100">
          <img
            src={`${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ""
            }/assets/images/remove.png`}
            alt="errImg"
            height="100"
            width="100"
            className="failure_image"
          />
        </div>
        <div className="mt-4 d-flex flex-column justify-content-center w-100">
          <h4
            className="text-center w-100 text-danger font-weight-bold oops_text"
            style={{ fontSize: "2.3rem" }}
          >
            Oops!
          </h4>
          <h4 className="text-center w-100 text-danger font-weight-bold transaction_text">
            {error ? error : "Something went wrong."}
          </h4>
        </div>
        <div className="mt-4 d-flex flex-column justify-content-center w-100">
          <p
            className="text-center w-100 error_text"
            style={{ fontSize: "1.1rem", color: "red" }}
          >
            Please Contact us at<b> customersupport@renewbuy.com</b>
          </p>
        </div>
      </Top>
    </Row>
  );
}

const Top = styled.div`
  font-family: ${({ theme }) => theme.Payment?.fontFamily || ""};
  font-weight: ${({ theme }) => theme.Payment?.fontWeight || ""};
  margin: auto;
  @media (max-width: 767px) {
    width: 100% !important;
    padding: 0 30px;
    .failure_image {
      height: 50px;
      width: 50px;
    }
    .oops_text {
      font-size: 1.3rem !important;
    }
    .transaction_text {
      font-size: 1rem !important;
    }
    .error_text {
      font-size: 0.9rem !important;
    }
    .content_text {
      font-size: 0.8rem !important;
    }
  }
`;
