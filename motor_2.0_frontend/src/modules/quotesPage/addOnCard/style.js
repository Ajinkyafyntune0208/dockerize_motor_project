import { Form } from "react-bootstrap";
import styled from "styled-components";

export const AddOnTitle = styled.div`
  float: left;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "basier_squareregular"};
  border-bottom: ${({ lessthan767 }) =>
    lessthan767 ? "none" : "1px solid rgba(0, 0, 0, 0.125)"};
  font-size: 16px;
  line-height: 20px;
  color: #333;
  padding-bottom: 13px;
  @media (max-width: 767px) {
    display: none !important;
  }
`;
export const CardOtherItem = styled.div`
  display: inline-block;
  margin-top: "20px";
  position: relative;
  margin-right: 16px;
  border-radius: 4px;
  background-color: #ffffff;
  text-align: center;
  width: 100%;
  position: relative;
  bottom: 90px;
  overflow-x: hidden;

  @media (max-width: 990px) {
    bottom: 0px;
  }
  @media (max-width: 767px) {
    bottom: -12px;
  }
  .hideAddon {
    display: none;
  }
  .showAddon {
    display: block;
  }
`;

export const AccordionTab = styled.div`
  text-align: left;
  margin-top: 40px;
  .accordion > .card > .card-header {
    border-radius: 0;
    margin-bottom: -1px;
    max-height: 38px;
  }
  .arrow {
    color: #6b6e71;
    font-size: 20px;
  }
  @media (max-width: 767px) {
    margin-top: 0px;
  }
`;

export const CardBlock = styled.div`
  -moz-box-flex: 1;
  flex: 1 1 auto;
  color: #232323;
  padding: 10px 10px;
  border-top: 1px soild #000;
  border-radius: 0;
  .hideAddon {
    display: none;
  }
  .showAddon {
    display: block;
  }
`;
export const InputFieldSmall = styled.div`
  margin-top: 6px;
  margin-bottom: 12px;
  .form-control {
    display: block;
    font-size: 12px;
    width: ${({ fullWidth }) => (fullWidth ? `100%` : `85%`)};
    margin-left: ${({ fullWidth }) => (fullWidth ? `0px` : `35px`)};
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #999;
    border-radius: 50px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  }
  .form-control:active,
  .form-control:focus {
    border: solid 1px #000;
    border-radius: 50px;
  }
`;

export const ButtonContainer = styled.div`
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
`;
export const ButtonSub = styled.button`
  margin-top: 6px;
  background-color: #fff !important;
  border: ${({ theme }) => theme.QuoteCard?.border2 || "1px solid #060"};
  color: #000 !important;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 12px;
  line-height: 40px;
  border-radius: ${({ theme }) =>
    theme.QuoteBorderAndFont?.borderRadius || "30px"};
  margin-left: 0;
  outline: none;
  width: 100px;
  margin-top: 20px;
  line-height: 24px;
  height: 34px;

  &:hover {
    background-color: ${({ theme }) =>
      theme.QuoteCard?.color2 || "#060"} !important;
    border: ${({ theme }) => theme.QuoteCard?.border2 || "1px solid #060"};
    color: ${({ theme }) => theme.QuoteCard?.border2 || "#fff"} !important;
  }
  @media (max-width: 767px) {
    display: none;
  }
`;

export const ColllapseAllContainer = styled.div`
  position: absolute;
  right: 20px;
  top: 12px;
  .badge {
    margin-right: 10px;
    padding: 2px;
    border-radius: 50%;
  }
  @media (max-width: 767px) {
    display: none;
  }
`;

export const FilterMenuBoxCheckConatiner = styled.div`
  ${({ hide }) => (hide ? "display: none;" : "")}
  .filterMenuBoxCheck input[type="checkbox"]:checked + label:before {
    background-color: ${({ theme }) => theme.CheckBox?.color || "#bdd400"};
    border: ${({ theme }) => theme.CheckBox?.border || "1px solid #bdd400"};
    box-shadow: ${({ theme }) =>
      theme.QuoteBorderAndFont?.shadowCheck || "none"};
    filter: ${({ theme }) =>
      theme.QuoteBorderAndFont?.filterPropertyCheckBox || "none"};
  }
`;

export const SubCheckBox = styled.div`
  width: 95%;
  padding: 20px;
`;

export const ClearAllButton = styled.button`
  background: ${({ theme }) => theme.FilterConatiner?.lightColor || " #f3ff91"};
  color: ${({ theme }) =>
    theme?.FilterConatiner?.clearAllTextColor
      ? theme?.FilterConatiner?.clearAllTextColor
      : "black"};
  font-weight: bold;
  width: max-content;
  padding: 8px 12px;
  border-radius: ${({ theme }) =>
    theme.QuoteBorderAndFont?.borderRadius || "24px"};
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 400;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"basier_squareregular"`};
  position: relative;
  bottom: 35px;
  margin-bottom: 10px;
  border: none;
  left: 15px;
  border: 1px solid transparent;
  .clearImage {
    margin-left: 10px;
    color: ${({ theme }) =>
      theme?.FilterConatiner?.clearAllTextColor
        ? theme?.FilterConatiner?.clearAllTextColor
        : "black"};
  }
  @media (max-width: 993px) {
    bottom: -6px;
    left: 2px;
    display: none;
  }
  &:hover {
    ${(props) =>
      props?.themeDisable ? "" : `background-color: #fff !important`};
    color: ${({ theme }) => theme.QuoteCard?.color3};
    border: ${({ theme }) =>
      theme.QuoteCard?.border || "1px solid #bdd400 !important"};
    &:before {
      transform: translateX(300px) skewX(-15deg);
      opacity: 0.6;
      transition: 0.7s;
    }
    &:after {
      transform: translateX(300px) skewX(-15deg);
      opacity: 1;
      transition: 0.7s;
    }
    .clearImage {
      color: ${({ theme }) => theme.QuoteCard?.color};
    }
  }
`;

export const Dropdown = styled.select`
  margin: 5px 0 5px 35px;
  width: 100px;
  padding: 2px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 16px;
  cursor: pointer;
  background-color: #fff;

  &:hover {
    border-color: #888;
  }
  &:focus {
    border-color: ${({ theme }) => theme.QuoteCard?.color};
    outline: none;
  }
`;

export const ToggleSwitch = styled(Form.Check)`
  .custom-switch-1 .custom-control-label::after {
    background-color: white;
  }
  .custom-switch .custom-control-label::after {
    background-color: white;
  }
  .custom-control-input:checked ~ .custom-control-label::before {
    background-color: ${(props) =>
      props?.theme1?.leadPageBtn?.background1 || "rgb(189, 212, 0)"};
    border-color: ${(props) =>
      props?.theme1?.leadPageBtn?.background1 || "rgb(189, 212, 0)"};
  }
  .custom-control-input:not(:disabled):active ~ .custom-control-label::before {
    background-color: #fff;
  }
`;
export const ToggleContainer = styled.div`
  border-bottom: ${({ lessthan767 }) =>
    lessthan767 ? "1px solid rgba(0, 0, 0, 0.125)" : "none"};
  margin: 15px;
  padding-bottom: ${({ lessthan767 }) => (lessthan767 ? "10px" : "")};
  .toggleBtn {
    user-select: none;
    padding-top: 10px;
  }
  .toggleBtn-noborder {
    user-select: none;
    border: none;
    padding-top: 10px;
  }
  .custom-control-label {
    border: 1px solid transparent !important;
  }
  .label-text {
    font-size: 16px;
    font-weight: 400;
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamily ||
      "basier_squareregular"} !important;
  }
`;
