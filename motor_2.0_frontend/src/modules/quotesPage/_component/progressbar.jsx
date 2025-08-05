import React from "react";
import { ProgressBar, Row } from "react-bootstrap";
import { ProgrssBarContainer } from "../quotesStyle";

const Progressbar = ({ progressPercent }) => {
  return (
    <Row>
      <ProgrssBarContainer>
        <ProgressBar striped variant="info" now={progressPercent} animated />
      </ProgrssBarContainer>
    </Row>
  );
};

export default Progressbar;
