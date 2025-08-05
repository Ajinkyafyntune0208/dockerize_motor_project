import styled, { createGlobalStyle } from "styled-components";
import { Row, Col } from "react-bootstrap";

export const GlobalStyle = createGlobalStyle`
body {
 
  .renewBtn{
    background-color: #f3f7f5;
    color: ${({ theme }) => theme?.Registration?.otherBtn?.hex1 || "#006400"};
    font-family: ${({ theme }) => theme?.fontFamily && theme?.fontFamily};
  
  }
  .renewBtn:hover{
    text-decoration: none;
    color: ${({ theme }) => theme?.Registration?.otherBtn?.hex2 || "#228B22"};
  }
}
`;

export const StyledCol = styled(Col)`
  @media (max-width: 992px) {
    display: flex;
    justify-content: center;
  }
  @media (max-width: 767px) {
    display: flex;
    justify-content: center;
    padding: 0 !important;
  }
`;

export const StyledRow = styled(Row)`
  @media (max-width: 992px) {
    display: flex !important;
    flex-direction: column !important;
    align-content: center !important;
    width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    flex-wrap: no-wrap !important;
  }
`;

export const RowTag = styled(Row)`
  ${({ theme }) =>
    theme.fontFamily &&
    `.input {
    font-family: ${theme.fontFamily} || "sans-serif"} !important;
  }`}
`;

export const StyledH4 = styled.h4`
  color: rgb(74, 74, 74);
  font-size: 27px;
  @media (max-width: 767px) {
    font-size: 20px;
  }
`;

export const StyledH3 = styled.h3`
  color: ${({ theme }) => theme.regularFont?.fontColor || "rgb(74, 74, 74)"};
  ${import.meta.env.VITE_BROKER === "TATA" &&
  ` background: linear-gradient(to right, #00bcd4 0%, #ae15d4 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;`}
  font-size: 30px;
  font-family: ${({ theme }) =>
    theme.regularFont?.headerFontFamily || "sans-serif"};
  @media (max-width: 767px) {
    font-size: ${(isMobileIOS) => (isMobileIOS ? "18px" : "20px")};
    font-weight: 800;
  }
`;

export const StyledBack = styled.div`
  padding-bottom: 30px;
  margin-top: -20px;
  z-index: 999;
  ${import.meta.env.VITE_BROKER === "ABIBL"
    ? `@media (max-width: 780px) {
    position: relative;
    top: -120px;
    left: -10%;
  }
  @media (max-width: 769px) {
    position: relative;
    top: -125px;
    left: -11%;
  }
  @media (max-width: 600px) {
    position: relative;
    top: -120px;
    left: -10%;
  }`
    : `@media (max-width: 780px) {
      position: relative;
      top: -73px;
      left: -10%;
    }
    @media (max-width: 769px) {
      position: relative;
      top: -125px;
      left: -11%;
    }
    @media (max-width: 600px) {
      position: relative;
      top: -73px;
      left: -10%;
    }`}
`;

export const Stylediv = styled.div`
  /* z-index: ${() =>
    import.meta.env.VITE_BROKER === "RB" && "-9999 !important"};
  margin: auto; */
  ${({ tabletResponsive }) =>
    tabletResponsive ? `position: relative; top: -42px;` : ""}
`;

export const SpanTag = styled.span`
  ${({ Theme, isMobileIOS }) =>
    `color: ${Theme?.Registration?.proceedBtn?.background || "#bdd400"};
     font-size: ${isMobileIOS ? "18px" : "20px"};`}
`;

export const LabelTag = styled.text`
  ${({ theme, regNo1, regNo3, regIp }) => `
    color:
        ${
          (regNo1 && regNo3) || (regIp && regIp[0] * 1 && regIp.length > 10)
            ? theme?.Registration?.proceedBtn?.color
              ? theme?.Registration?.proceedBtn?.color
              : "black"
            : "black"
        };
  `}
`;

export const TextTag = styled.label`
  ${({ lessthan767 }) =>
    `
        cursor: pointer;
        font-size: ${lessthan767 ? "11px" : "12px"};
        `}
`;
