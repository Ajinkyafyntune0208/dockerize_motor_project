import styled from "styled-components";
import { Form, ButtonGroup, Col, Row } from "react-bootstrap";
import ThemeObj from "modules/theme-config/theme-config";
import SecureLS from "secure-ls";
import _ from "lodash";

const ls = new SecureLS();
const ThemeLS = ls.get("themeData");
const Theme = !_.isEmpty(ThemeLS) && ThemeLS ? ThemeLS : ThemeObj;

const Label = styled.label`
  color: ${(props) =>
    props.colState === "hidden"
      ? Theme?.proposalHeader?.color
        ? Theme?.proposalHeader?.color
        : "#1a5105"
      : "#fff"};
  font-size: 16px;
  font-weight: 600;
`;

const FormGroupTag = styled(Form.Label)`
  font-size: 12px;
  font-weight: normal;
  ${({ mandatory }) =>
    mandatory
      ? `&::after {
         content:" *";
         color: red;
     }`
      : ""}
`;

const ButtonGroupTag = styled(ButtonGroup).attrs((props) => ({
  className: props.className,
}))`
  .btn-secondary {
    color: #6c757d;
    background-color: #fff;
    border-color: #6c757d;
    transition: ease-in-out 0.2s;
    border-radius: 0;
  }

  .btn-secondary:hover {
    color: #fff;
    background-image: ${({ theme }) =>
      theme?.genderProposal?.background ||
      "linear-gradient(100deg, rgba(16,82,3,1) 19%, rgba(34,113,4,1) 65%)"};
    border-color: #545b62;
    transition: ease-in-out 0.2s;
    box-shadow: ${({ theme }) =>
      theme?.genderProposal?.boxShadow || "6.994px 5.664px 21px #a4e88a"};
  }
  .btn-secondary:active {
    color: #fff;
    background-image: ${({ theme }) =>
      theme?.genderProposal?.background ||
      "linear-gradient(100deg, rgba(16,82,3,1) 19%, rgba(34,113,4,1) 65%)"};
    border-color: #545b62;
    transition: ease-in-out 0.2s;
    box-shadow: ${({ theme }) =>
      theme?.genderProposal?.boxShadow || "6.994px 5.664px 21px #a4e88a"};
  }

  .btn-secondary:not(:disabled):not(.disabled).active,
  .btn-secondary:not(:disabled):not(.disabled):active,
  .show > .btn-secondary.dropdown-toggle {
    color: #fff;
    background-image: ${({ theme }) =>
      theme?.genderProposal?.background ||
      "linear-gradient(100deg, rgba(16,82,3,1) 19%, rgba(34,113,4,1) 65%)"};
    border-color: #545b62;
    transition: ease-in-out 0.2s;
    box-shadow: ${({ theme }) =>
      theme?.genderProposal?.boxShadow || "6.994px 5.664px 21px #a4e88a"};
  }
`;

const H4Tag2 = styled.h4`
  display: none;
  @media (max-width: 992px) {
    display: flex;
    text-align: center;
    width: 100%;
    justify-content: center;
    margin: 20px 30px 0px 30px;
  }
  @media (max-width: 600px) {
    margin: 20px 0px 0px 0px;
  }
`;

//title shift -proposal cards
const ShiftingLabel = styled.label`
  color: #1a5105;
  font-size: 16px;
  font-weight: 600;
  @media (max-width: 992px) {
    display: none;
  }
`;

const ColDiv = styled(Col)`
  display: none;
  @media (max-width: 992px) {
    display: flex;
  }
`;

const SubmitDiv = styled.div`
  .checkbox-container {
    display: block;
    position: relative;
    padding-left: 35px;
    cursor: pointer;
    font-size: 22px;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    margin-bottom: 0.5rem;
  }
  .checkbox-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
  }
  .checkbox-container input:checked ~ .checkmark,
  .plan-card .checkbox-container input:checked ~ .checkmark {
    background-color: #2edd2e;
  }
  .checkbox-container .checkmark {
    position: absolute;
    top: 0 !important;
    left: 0 !important;
    height: 20px;
    width: 20px;
    background-color: #eee;
    border: 1px solid #ddd;
    border-radius: 0;
  }
  .checkbox-container input:checked ~ .checkmark:after {
    display: block;
  }
  .checkbox-container .checkmark:after {
    content: url(${import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ""}/assets/images/checkbox-select.png);
    left: 1px;
    top: -10px;
    width: 17px;
    height: 16px;
    position: absolute;
  }
  .privacyPolicy {
    padding-left: 40px;
    font-size: 13px;
    color: #545151;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `sans-serif`};
    text-align: justify;
    text-justify: inter-word;
  }

  @media screen and (max-width: 993px) {
    .checkbox-container .checkmark:after {
      content: url(${import.meta.env.VITE_BASENAME !== "NA"
        ? `/${import.meta.env.VITE_BASENAME}`
        : ""}/assets/images/checkbox-select.png);
      left: 1px;
      width: 17px;
      height: 16px;
      position: absolute;
      color: #0000;
    }
  }
`;

//payment-confirmation
const DashboardCard = styled.div`
  background: ${({ theme }) => (theme.dark ? "#2a2a2a" : "#ffffff")};
  box-shadow: 2px 10px 20px rgba(0, 0, 0, 0.1);
  border-radius: 7px;
  text-align: center;
  position: relative;
  overflow: hidden;
  padding: 40px 25px 20px;
  height: 100%;
  width: auto;
  & + h4,
  h5 {
    color: #323c43;
    font-size: 1.4em;
  }
  & + h5 {
    display: block;
  }
  p {
    color: #6c6c6c;
    font-weight: 600;
    font-size: 1em;
  }
  h6 {
    font-weight: 600;
    font-size: 2.5em;
    line-height: 64px;
    color: ${({ theme }) => (theme.dark ? "#FAFAFA" : "#323c43")};
  }
  &:after {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 10px;
    content: "";
    background: ${({ theme }) =>
      theme?.paymentConfirmation?.headers
        ? theme?.paymentConfirmation?.headers
        : `linear-gradient(81.67deg, #b3ffb3 0%, #ffffcc 100%)`};
  }
`;

const Div = styled.div`
  margin: 35px;
  @media (max-width: 767px) {
    margin: 15px 10px;
  }
`;

const DivHeader = styled.div`
  font-size: 16px;
`;

const DivValue = styled.div`
  font-size: 14px;
  white-space: pre-wrap;
  word-wrap: break-word;
  font-weight: 600;
  padding: ${({ icName }) => (icName ? "0px 30px 0px 0px" : "")};
`;

const H3 = styled.h4`
  color: rgba(48, 68, 80, 0.6);
  border-bottom: 0.5px solid transparent;
  border-image: linear-gradient(to right, #d0d0d0, #fff);
  border-image-slice: 1;
`;

const H4Tag = styled.h4`
  margin-bottom: -20px;
  margin-top: -8px;
  text-align: center;
  color: ${({ theme }) =>
    import.meta.env.VITE_BROKER === "TATA"
      ? "#fff"
      : theme.regularFont?.fontColor || "rgb(74, 74, 74)"};
  ${import.meta.env.VITE_BROKER === "TATA" &&
  ` background: linear-gradient(to right, #00bcd4 0%, #ae15d4 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;`}
  font-size: 24px;
  @media (max-width: 992px) {
    display: none;
  }
  @media (max-width: 767px) {
    display: none;
  }
`;

const DivTag1 = styled.div`
  color: ${({ theme }) => theme.regularFont?.textColor || ""};
  @media (min-width: 890px) {
    width: 28.5%;
    max-width: 28.5%;
    flex: 0 0 28.5%;
  }
  @media screen (min-width: 300px) {
    padding: 0;
  }
`;

const DivTag2 = styled.div`
  color: ${({ theme }) => theme.regularFont?.textColor || ""};
  @media (min-width: 890px) {
    width: 71.5%;
    max-width: 71.5%;
    flex: 0 0 71.5%;
  }
  @media screen (min-width: 300px) {
    padding: 0;
  }
`;

const RowTag = styled(Row)`
  margin: 15px
    ${import.meta.env?.VITE_BROKER === "ABIBL"
      ? "45px 20px 45px"
      : "60px 20px 30px"} !important;
  @media (max-width: 600px) {
    margin: 10px 0 20px 0 !important;
    width: 100%;
  }
`;

const StyledDiv = styled.div`
  width: ${({ isMobileIOS, innerWidth }) =>
    isMobileIOS ? innerWidth + "px" : "100%"};
  max-width: ${({ isMobileIOS, innerWidth }) =>
    isMobileIOS ? innerWidth + "px" : "100%"};
  overflow-x: ${({ isMobileIOS }) => isMobileIOS && "hidden !important"};
`;

export {
  Label,
  FormGroupTag,
  ButtonGroupTag,
  H4Tag2,
  ShiftingLabel,
  ColDiv,
  SubmitDiv,
  DashboardCard,
  Div,
  DivHeader,
  DivValue,
  H3,
  H4Tag,
  DivTag1,
  DivTag2,
  RowTag,
  StyledDiv,
};
