import React from "react";
import { Modal } from "react-bootstrap";
import CircularProgress from "@mui/material/CircularProgress";

function ProposalFinalModal(props) {
  return (
    <Modal
      {...props}
      size="md"
      aria-labelledby="contained-modal-title-vcenter"
      centered
      backdrop="static"
    >
      <Modal.Body className="text-center">
        {(
          <CircularProgress size={64} thickness={4} style={{ margin: "20px auto" }} />
        )}
        <p className="mt-2 font-weight-bold">
        Kindly refrain from refreshing or exiting this page until the process has been successfully completed.
        </p>
      </Modal.Body>
    </Modal>
  );
}

export default ProposalFinalModal;
