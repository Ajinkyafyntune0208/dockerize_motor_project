import React from "react";
import { Col } from "react-bootstrap";
import { Extn } from "./helper";

const CorporateDiscount = () => {
  const { Ribbon } = Extn;
  return (
    <Col
      sm="12"
      md="12"
      lg="12"
      xl="12"
      className="d-flex justify-content-center mt-2 noOutLine"
    >
      <Ribbon className="ribbon" type="button">
        Enter Your Corporate Email-ID for Additional Discount
      </Ribbon>
    </Col>
  );
};

export default CorporateDiscount;
