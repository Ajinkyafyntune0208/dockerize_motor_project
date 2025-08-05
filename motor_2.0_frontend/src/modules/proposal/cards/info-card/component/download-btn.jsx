import React from "react";
import { DivDownload, RowTag, SpanDownload } from "../info-style";

const DownloadBtn = ({ wording, Theme }) => {
  return (
    <RowTag xs={1} sm={1} md={1} lg={1} xl={1}>
      <DivDownload>
        <a
          href={
            wording?.pdfUrl ? `${wording?.pdfUrl}` : "/" || "/"
          }
          target="_blank"
          rel="noopener noreferrer"
          download
          className="brochure"
          style={
            Theme?.sideCardProposal?.linkColor
              ? { color: Theme?.sideCardProposal?.linkColor }
              : {}
          }
        >
          <img
            src={`${
              import.meta.env.VITE_BASENAME !== "NA"
                ? `/${import.meta.env.VITE_BASENAME}`
                : ""
            }/assets/images/pdf.png`}
            alt="BrocureImage"
            height="36"
            style={{ paddingRight: "10px" }}
          />
          <SpanDownload>Download Policy Wording</SpanDownload>
        </a>
      </DivDownload>
    </RowTag>
  );
};

export default DownloadBtn;
