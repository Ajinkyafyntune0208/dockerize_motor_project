import Modal from "react-bootstrap/Modal";
import React from "react";
import Spinner from "react-bootstrap/Spinner";
const CKYCLoader = (props) => {
  return (
    <Modal
      {...props}
      size="md"
      aria-labelledby="contained-modal-title-vcenter"
      centered
      background="static"
    >
      <Modal.Header>
        <Modal.Title id="contained-modal-title-vcenter">
          Please Note
        </Modal.Title>
      </Modal.Header>
      <Modal.Body>
        <div className="d-flex">
          <Spinner
            animation="border"
            align="center"
            variant="primary"
            className="mr-2"
          />
          <p className="mt-1">
            Kindly wait
            {props?.TempData?.selectedQuote?.companyAlias === "edelweiss"
              ? " 5 to 7 "
              : " few "}
            minutes until CKYC is verified.
          </p>
        </div>
      </Modal.Body>
    </Modal>
  );
};

export default CKYCLoader;
