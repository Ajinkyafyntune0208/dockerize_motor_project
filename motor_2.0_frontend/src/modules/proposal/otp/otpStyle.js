import { Col } from "react-bootstrap";
import styled from "styled-components";

export const CloseButton = styled.button`
  position: absolute;
  top: 15px;
  right: 15px;
  display: flex;
  justify-content: center;
  width: 35px;
  height: 35px;
  color: #232222;
  text-shadow: none;
  opacity: 1;
  z-index: 1;
  border: none !important;
  font-size: 1.7rem;
  font-weight: 700;
  background: white;
  &:focus {
    outline: none;
  }
`;
export const ModalRightContentDiv = styled.div`
  margin-bottom: 25px;
  @media (max-width: 991px) {
    input,
    textarea {
      transform: translateZ(0px) !important;
    }
    display: flex;
    justify-content: center;
    & .OTPDiv {
      margin-left: 0px !important;
    }
  }
  & h2 {
    font-weight: bold;
  }
  & p {
    font-size: 15px;
    color: #4e4d4d;
    line-height: 1.8rem;
    word-spacing: 1px;
  }
  & input {
    height: ${(props) =>
      props?.lessthan767
        ? props?.ckyc
          ? "35px"
          : "50px"
        : props?.ckyc
        ? "50px"
        : "60px"};
    width: ${(props) =>
      props?.lessthan767
        ? props?.ckyc
          ? "35px"
          : "50px"
        : props?.ckyc
        ? "50px"
        : "60px"};
    margin-right: 25px;
    font-weight: bold;
    font-size: 20px;
    text-align: center;
    box-shadow: rgb(35 34 34 / 25%) 0px 2px 6px 1px,
      rgb(0 0 0 / 10%) 1px 1px 0px 0px !important;
    @media (max-width: 991px) {
      margin-right: 10px;
    }
  }
  & .OTPDiv {
    margin-left: 55px;
    font-size: 15px;
    cursor: pointer;
  }
`;

export const Heading = styled.h2`
  @media (max-width: 768px) {
    font-size: 25px;
    margin: 25px 0 0;
  }
  @media (max-width: 324px) {
    font-size: 22px;
    margin: 25px 0 0;
  }
`;

export const Paragraph = styled.p`
  @media (max-width: 768px) {
    text-align: center;
  }
`;

export const ModalLeftContentDiv = styled(Col)`
  padding-left: 0px;
  margin-top: -30px;
  & img {
    width: 280px;
  }
  @media (max-width: 991px) {
    display: flex;
    justify-content: center;
  }
  @media (max-width: 768px) {
    & img {
      width: 180px;
      height: 180px;
      object-fit: cover;
    }
  }
  @media (max-width: 320px) {
    & img {
      width: 130px;
      height: 120px;
      object-fit: cover;
    }
  }
`;

export const ResendBtn = styled.p`
  color: green !important;
  cursor: pointer;
`;
