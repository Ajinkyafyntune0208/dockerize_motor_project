import styled, { createGlobalStyle } from "styled-components";
const GlobalStyle = createGlobalStyle`
body {
	.MuiDrawer-paperAnchorBottom {
		border-radius: 3% 3% 0px 0px;
		z-index: 99999 !important;
	}
	.css-1u2w381-MuiModal-root-MuiDrawer-root {
    z-index: 100000	!important;
  }
 ${({ theme }) =>
   theme?.fontFamily &&
   `.paymentTermRadioWrap .radioCheckedColor .checkBoxTextIdv ,.txtCheckedBold{
	font-family: ${theme?.fontFamily};
	}
 `};
}
`;

const Conatiner = styled.div`
  padding: 20px 30px;
  .checkBoxTextIdv {
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  }
`;

const PaymentTermTitle = styled.div`
  float: left;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 16px;
  line-height: 20px;
  color: #333;
  padding-bottom: 10px;
  border-bottom: solid 1px #e3e4e8;
`;

const PopupSubTitle = styled.div`
  float: left;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  font-size: 14px;
  line-height: 20px;
  color: #333;
  margin-top: 16px;
  margin-bottom: 16px;
`;

const PopupSubHead = styled.div`
  float: left;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 14px;
  line-height: 17px;
  color: #333;
  margin-bottom: 12px;
`;

const ApplyButton = styled.button`
  width: 117px;
  height: 32px;
  border-radius: ${({ theme }) =>
    theme.QuoteBorderAndFont?.borderRadius || "50px"};
  background-color: ${({ theme }) => theme.QuotePopups?.color || "#bdd400"};
  border: ${({ theme }) => theme.QuotePopups?.border || "1px solid #bdd400"};
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 15px;
  line-height: 20px;
  color: ${({ theme }) => theme?.FilterConatiner?.clearAllTextColor || " #000"};
  /* text-transform: uppercase; */
  margin: 0;
  float: right;
  &:hover {
    ${import.meta.env.VITE_BROKER === "TATA" &&
    `
    background: transparent;
    color: #0099f2;
  `}
  }
`;

const InputFieldSmall = styled.div`
  margin-top: 6px;
  margin-bottom: 12px;
  .form-control {
    display: block;
    font-size: 12px;
    width: 95%;

    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #999;
    border-radius: 50px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  }
  .form-control:active,
  .form-control:focus {
    border: solid 2px #000;
    border-radius: 50px !important;
  }
`;

const MobileDrawerBody = styled.div`
  width: 100%;
  border-radius: 3px 3px 0px 0px;
`;
const CloseButton = styled.div`
  display: ${({ hiddenClose }) => (hiddenClose ? "none" : "block")};
  position: absolute;
  top: 10px;
  right: 10px;
  cursor: pointer;
  z-index: 1111;
  &:hover {
    text-decoration: none;
    color: #363636;
  }
`;
export default {
  GlobalStyle,
  Conatiner,
  PaymentTermTitle,
  PopupSubTitle,
  PopupSubHead,
  ApplyButton,
  InputFieldSmall,
  MobileDrawerBody,
  CloseButton,
};
