import React from "react";
import { Row, Col, Button, Modal } from "react-bootstrap";
import styled, { createGlobalStyle } from "styled-components";

export const SimpleModal = (props) => {
  return (
    <Modal
      {...props}
      size="lg"
      aria-labelledby="contained-modal-title-vcenter"
      centered
      backdrop={"static"}
      keyboard={false}
    >
      <Modal.Body>
        <div style={{ padding: "10px" }}>
          <Row style={{ width: "100%", margin: "0" }}>
            <Col xs={2} lg={2} md={2} className="p-0 m-0 text-center">
              <img
                className="happyImage"
                src={`https://t4.ftcdn.net/jpg/01/10/88/59/240_F_110885907_yOVoAyyRCOt8HtLNdpz8FQvYz1vk2xer.jpg`}
              />
            </Col>
            <Col
              xs={10}
              style={{ padding: "0", display: "flex", alignItems: "center" }}
            >
              <h5 className="h5Content">
                We will soon start catering to Bharat (BH) series registrations.
                Please check again after a few days.
              </h5>
            </Col>
          </Row>
        </div>
      </Modal.Body>
      <Modal.Footer>
        <div className="linkWrapper">
          <Button onClick={() => props?.onHide()} variant="link">
            Enter another registration Number
          </Button>
        </div>
      </Modal.Footer>
      <GlobalStyle />
    </Modal>
  );
};

const GlobalStyle = createGlobalStyle`
.modal-content{
    width: 50%;
    margin: auto;
    @media (max-width:767px){
        width: 100%;
    }
}
.happyImage{
    width: auto;
    height: 45px;
    /* @media (max-width:767px){
        height: 35px;

    } */
}
.h5Content{
    margin:0px;
    font-size:14px;
    text-align:left;
    color ${({ theme }) => theme?.links?.color && theme?.links?.color}
   
}
@media(max-width:767px){
    .h5Content{
        font-size:13px !important;
           }
        }
.modal-body{
    padding: 1rem 1rem 0px !important;
}
.linkWrapper{
    display: flex;
    justify-content:center;
    align-items:center;
    width:100%;
    .btn-link, .btn.focus, .btn:focus{
        box-shadow:none;
        font-size:15px;
    }
}
.modal-footer{
    border-top:0px;
    padding: 0px 0px 10px;

}
`;
