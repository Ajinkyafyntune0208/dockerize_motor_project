import { ShortlenUrl } from "modules/quotesPage/quote.slice";
import { QRCodeCanvas } from "qrcode.react";
import React from "react";
import { useEffect } from "react";
import { Modal } from "react-bootstrap";
import { useDispatch, useSelector } from "react-redux";
import styled, { createGlobalStyle } from "styled-components";
import _ from "lodash";
import { useLocation } from "react-router";
import { _deliveryTracking } from "analytics/proposal-tracking/payment-modal-tracking";

export const QrModal = (props) => {
  const location = useLocation();
  const loc = location.pathname ? location.pathname.split("/") : "";
   const baseName = import.meta.env.VITE_BASENAME === "NA" ?  "" : import.meta.env.VITE_BASENAME
  const redirectionUrl = `${window.location.origin}${baseName ?  `/${baseName}` : ""}/resume-journey${window.location.search}`
  const { getShorlenUrl } = useSelector((state) => state.quotes);
  const { temp_data } = useSelector((state) => state.proposal);
  const dispatch = useDispatch();
  useEffect(() => {
    if (!_.isEmpty(redirectionUrl)) {
      dispatch(ShortlenUrl({ url: redirectionUrl }));
    }
    if (
      window.location.href.includes("/proposal-page") &&
      !_.isEmpty(temp_data) &&
      props?.show
    ) {
      _deliveryTracking(props?.type, temp_data, "QR Code");
    }
  }, [props.show]);

  const heading =
    loc[2] === "quotes" && !props.sendPdf
      ? "Scan the QR Code to share the quote page URL."
      : loc[2] === "quotes" && props.sendPdf
      ? "Scan the QR Code to download premium breakup pdf."
      : loc[2] === "compare-quote"
      ? "Scan the QR Code to share the compare page URL."
      : loc[2] === "proposal-page"
      ? "Scan the QR code to share the proposal URL."
      : loc[1] === "payment-success"
      ? "Scan the QR code to share the Payment successful URL."
      : "Scan the QR code to share URL.";

  return (
    <Modal
      {...props}
      size="lg"
      aria-labelledby="contained-modal-title-vcenter"
      centered
      backdrop={"static"}
      keyboard={false}
      style={{ zIndex: 9999999 }}
    >
      <Modal.Header closeButton>
        <Modal.Title id="contained-modal-title-vcenter">{heading}</Modal.Title>
      </Modal.Header>
      {_.isEmpty(getShorlenUrl?.url) ? (
        <Container>Generating qr code. please wait...</Container>
      ) : (
        <Modal.Body>
          <Container>
            <QRCodeCanvas
              value={getShorlenUrl?.url}
              size={200}
              className="qrCanvas"
            />
          </Container>
        </Modal.Body>
      )}
      <GlobalStyle />
    </Modal>
  );
};

const GlobalStyle = createGlobalStyle`
    body {
        .modal-content {
        width: 500px !important;
        height: 290px;
        right: 0;
        left: 0;
        margin: auto;
        @media only screen and (max-width: 767px) { 
          width: auto !important;
          position: absolute;
          bottom: 5px;
          z-index: 9999999999;
        }
    }
    #contained-modal-title-vcenter {
        font-size: 12px;
    }
    .top-info {
      z-index: 1 !important;
    }
    }
`;

const Container = styled.div`
  height: 200px;
  display: flex;
  justify-content: center;
  align-items: center;
  @media only screen and (max-width: 767px) {
    .qrCanvas {
      padding: 5px 0;
    }
  }
`;
