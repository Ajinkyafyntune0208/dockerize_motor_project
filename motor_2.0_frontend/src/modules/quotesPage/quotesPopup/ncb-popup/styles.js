import styled, { createGlobalStyle } from "styled-components";
export const GlobalStyle = createGlobalStyle`
body {
	.MuiDrawer-paperAnchorBottom {
		border-radius: 3% 3% 0px 0px;
		z-index: 99999 !important;
	}
	.css-1u2w381-MuiModal-root-MuiDrawer-root {
    z-index: 100000 !important;
  }
}
`;

export const Conatiner = styled.div`
  padding: 30px;
  .vehRadioWrap input:checked + label {
    // font-family: "Inter-SemiBold";
    background-color: ${({ theme }) =>
      theme.QuoteBorderAndFont?.journeyCategoryButtonColor
    || "#000"};
    color: #fff;
    box-shadow: none;
    border: none;
  }
`;

export const PaymentTermTitle = styled.div`
  float: left;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 16px;
  line-height: 20px;
  color: #333;
  padding-bottom: 10px;
  border-bottom: solid 1px #e3e4e8;
  font-weight: 900;
`;

export const PopupSubTitle = styled.div`
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

export const PopupSubHead = styled.div`
  float: left;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 14px;
  line-height: 17px;
  color: #333;
  margin-bottom: 12px;
`;

export const ApplyButton = styled.button`
  width: 117px;
  height: 32px;
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
  border-radius: ${({ theme }) =>
    theme.QuoteBorderAndFont?.borderRadius || "50px"};
  &:hover {
    ${import.meta.env.VITE_BROKER === "TATA" &&
    `
    background: transparent;
    color: #0099f2;
  `}
  }
`;

export const EligText = styled.div`
  float: left;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  width: 100%;
  border: dashed 1px #000;
  padding: 11px 13px;
  font-size: 14px;
  line-height: 20px;
  color: #000;
  margin-bottom: 24px;
  div {
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
    font-size: 14px;
    line-height: 17px;
    margin-top: 0px;
  }
`;

export const PaymentTermRadioWrap = styled.div`
  float: left;
  width: 100%;
`;

export const MobileDrawerBody = styled.div`
  width: 100%;
  border-radius: 3px 3px 0px 0px;
`;
export const CloseButton = styled.div`
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
