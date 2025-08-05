import React from "react";
import { PDFButton } from "./FeatureStyle";

const PdfButton = () => {
  return (
    <PDFButton
      className="d-flex align-items-center justify-content-center"
      onClick={() =>
        document?.getElementById("comparePdfDownload") &&
        document?.getElementById("comparePdfDownload").click()
      }
    >
      <i
        className="fa fa-download"
        aria-hidden="true"
        style={{
          fontSize: "14px",
          cursor: "pointer",
          margin: "0px 5px",
        }}
      ></i>

      <label
        className="m-0 p-0"
        style={{
          fontSize: "14px",
          paddingTop: "3px",
          cursor: "pointer",
        }}
      >
        PDF
      </label>
    </PDFButton>
  );
};

export default PdfButton;
