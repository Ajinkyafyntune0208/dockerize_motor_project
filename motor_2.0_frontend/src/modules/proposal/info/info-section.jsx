import React, { useState } from "react";
import styled, { keyframes, css } from "styled-components";
import { HiLightBulb } from "react-icons/hi";
import { IoCloseCircleOutline } from "react-icons/io5";

const Info = ({ isRedirection, showOverlay }) => {
  const [toasterOpen, setToasterOpen] = useState(true);
  return (
    <Container isOpen={toasterOpen}>
      <Paragraph>
        <span>
          <HiLightBulb
            style={{
              color: "#fee402",
              marginLeft: "-17px",
              marginBottom: "4px",
            }}
            size={"30px"}
          />
          <span style={{ fontWeight: "800", paddingleft: 0 }}>Quick Tip! </span>
          <span style={{ float: "right", cursor: "pointer" }}>
            <IoCloseCircleOutline
              onClick={() => {
                setToasterOpen(false);
                setTimeout(() => showOverlay(false), 500);
              }}
              size={"25px"}
            />
          </span>
        </span>
        <span>
          {isRedirection ? (
            <>
              <ul style={{ padding: 0 }}>
                <li>
                  Incase you have previously done your KYC using a{" "}
                  <b>Proof of Identity</b>, then please select that ID and
                  complete your CKYC.
                </li>
                <li>
                  Not completed your KYC? Please select an ID, enter the details
                  and <b>redirect for verification</b>.
                </li>
              </ul>
            </>
          ) : (
            <>
              <ul style={{ padding: 0 }}>
                <li>
                  Incase you have previously done your KYC using a{" "}
                  <b>Proof of Identity</b>, then please select that ID and
                  complete your CKYC.
                </li>
                <li>
                  Not completed your KYC? Please select an ID, enter the details
                  and <b>upload all the required documents</b>.
                </li>
              </ul>
            </>
          )}
        </span>
      </Paragraph>
    </Container>
  );
};

export default Info;

// Define animation keyframes
const fadeIn = keyframes`
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
`;

const fadeOut = keyframes`
  from {
    opacity: 1;
    transform: translateY(0);
  }
  to {
    opacity: 0;
    transform: translateX(50px);
  }
`;

const Container = styled.div`
  max-width: 600px;
  background-color: white;
  position: fixed;
  right: 0px;
  bottom: 5px;
  padding: 15px 23px;
  box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 12px;
  z-index: 9999999;
  border: ${({ theme }) =>
    `0.5px solid ${theme.questionsProposal?.color}` || ""};
  border-radius: 5px;
  animation: ${({ isOpen }) =>
    isOpen
      ? css`
          ${fadeIn} 0.5s ease-out
        `
      : css`
          ${fadeOut} 0.5s ease-in
        `};
`;

const Paragraph = styled.div`
  text-align: justify;
  span {
    font-weight: 600;
  }
`;
