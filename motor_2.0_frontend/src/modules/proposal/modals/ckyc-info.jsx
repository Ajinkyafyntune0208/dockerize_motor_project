import React from "react";
import { Modal } from "react-bootstrap";
import { Button } from "components";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import _ from "lodash";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const ckycInfo = (props) => {
  return (
    <Modal
      show={props.show}
      onHide={props.onHide}
      size="lg"
      aria-labelledby="contained-modal-title-vcenter"
      centered
    >
      <Modal.Header closeButton={!props?.noCloseIcon}>
        <Modal.Title id="contained-modal-title-vcenter">
          Please Note
        </Modal.Title>
      </Modal.Header>
      <Modal.Body>
        <h4>
          CKYC Verification Failed.
        </h4>
        <p className="mt-3 mb-0 pb-0">
        No CKYC record found. Please proceed booking your policy. 
        After you make payment, you will get a link for getting your KYC done. 
        KYC is mandatory and needs to be performed on the given link within 2 days otherwise your policy will get cancelled.
        </p>
      </Modal.Body>
      <Modal.Footer>
        <Button
          type="submit"
          buttonStyle="outline-solid"
          className=""
          shadow={"none"}
          hex1={
            Theme?.proposalProceedBtn?.hex1
              ? Theme?.proposalProceedBtn?.hex1
              : "#4ca729"
          }
          hex2={
            Theme?.proposalProceedBtn?.hex2
              ? Theme?.proposalProceedBtn?.hex2
              : "#4ca729"
          }
          borderRadius="5px"
          color="white"
          onClick={props.onHide}
        >
          <text
            style={{
              fontSize: "15px",
              padding: "-20px",
              margin: "-20px -5px -20px -5px",
              fontWeight: "400",
            }}
          >
            Close
          </text>
        </Button>
      </Modal.Footer>
    </Modal>
  );
};

export default ckycInfo;
