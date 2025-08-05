import React from "react";
import { Modal, Button } from "react-bootstrap";
import _ from "lodash";

const PreSubmit = (props) => {
  return (
    <Modal
      {...props}
      size="md"
      aria-labelledby="contained-modal-title-vcenter"
      centered
    >
      <Modal.Header closeButton>
        <Modal.Title id="contained-modal-title-vcenter">
        Action Required
        </Modal.Title>
      </Modal.Header>
      <Modal.Body>
        <p>
          Do you want to go with existing discount or go with IIB claim Details
          ?
        </p>
      </Modal.Body>
      <Modal.Footer>
        <Button onClick={() => props.selection(true)}>IIB Claim Details</Button>
        <Button onClick={() => props.selection(false)}>Existing Discount</Button>
      </Modal.Footer>
    </Modal>
  );
};

export default PreSubmit;
