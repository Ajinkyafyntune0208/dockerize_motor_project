import styled, { createGlobalStyle } from "styled-components";

export const GlobalStyle = createGlobalStyle`
body {
	.MuiDrawer-paperAnchorBottom {
		border-radius: 3% 3% 0px 0px;
		z-index: 999999999 !important;
	}
	.css-1u2w381-MuiModal-root-MuiDrawer-root {
    z-index: 1000000 !important;
  }
	.css-i9fmh8-MuiBackdrop-root-MuiModal-backdrop {
    pointer-events: ${({ disabledBackdrop }) =>
      disabledBackdrop ? "none !important" : ""};
  }
  .MuiDrawer-root MuiDrawer-modal MuiModal-root  {
    z-index: 99999 !important;
  }
}
`;

export const LaxmiWrapper = styled.div`
  float: left;
  margin-right: 28px;
`;

export const Laxmi = styled.img`
  height: 100px;
  width: 100px;
  border-radius: 20px;
  box-shadow: 0px 4px 13px rgba(41, 41, 41, 0.35);
  border: ${({ theme }) => theme.Header?.borderLogo || "2.5px solid #bdd400"};
  background: ${({ theme }) => theme.links?.color || "#bdd400"};
`;

export const MainWrapper = styled.div`
  margin: 40px 0 30px;
  padding: 0px 20px;
  display: flex;
  justify-content: center;
  flex-wrap: wrap;

  .tabWrappers {
    position: unset;
    margin-left: -12px;
    margin-bottom: 12px;
    box-shadow: rgba(149, 157, 165, 0.2) 0px 8px 24px;
    width: max-content;
  }
  .shareTab {
    border: none !important;
    border-radius: 20px;
  }

  .tableConatiner {
    max-height: 600px;
    overflow: auto;
    margin-top: 10px;
    padding: 10px;
    & td {
      border: none;
    }
    & th {
      border: none;
    }
    & tr {
      border-bottom: 0.3px solid #bdd400;
      display: flex;
      justify-content: space-around;
      align-items: center;
    }
  }
`;

export const Wrapper = styled.div`
  width: 100%;
  & > div {
    margin-top: 10px;
    position: relative;
  }
`;

export const Text = styled.p`
  font-size: 14px;
  line-height: 20px;
  color: #666666;
  font-family: ${({ theme }) =>
    theme?.fontFamily
      ? theme?.fontFamily
      : `"Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif`};
  & strong {
    color: #000000;
    font-weight: 700;
  }
`;

export const Content2 = styled.div`
  & p {
    color: #000000;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"basier_squaremedium"`};
    font-size: 14px;
    font-weight: 500;
    margin: 0;
  }
  height: 20%;
  padding: 23px 0;
  background-color: #f1f2f6;
  text-align: center;
`;

export const MessageContainer = styled.div`
  padding: 10px;
  & svg {
    margin: 0 auto;
    width: 100%;
  }
`;

export const FlexDiv = styled.div`
  padding-top: 8px;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 0px 60px;
  @media only screen and (max-width: 600px) {
    margin: 0px 0px;
  }
`;

export const ContactImg = styled.img`
  float: left;
  margin-right: 10px;
  height: 70px;
`;

export const ContactText = styled.div`
  padding: 1rem;
  font-weight: 400;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"basier_squareregular"`};
  font-size: 16px;
  margin-bottom: -10px;
  color: #111;
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

export const ShareCheckBox = styled.i`
  background-color: ${({ theme }) => theme.CheckBox?.color || "#bdd400"};
  border: ${({ theme }) => theme.CheckBox?.border || "1px solid #bdd400"};
  width: 15px;
  height: 15px;
  font-size: 10px;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
`;

export const TabContainer = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
`;

export const QrCodeText = styled.p`
  margin-top: 10px !important;
  margin-bottom: 0px !important;
`;

export const QrCode = styled.span`
  color: ${({ theme }) => theme?.comparePage?.color || "#bdd400"};
  cursor: pointer;
`;

export const NextContainer = styled.div`
  text-align: center;
`;

export const NextBtn = styled.button`
  margin-right: 5px;
  font-size: 16px;
  border-radius: 5px;
  padding: 8px 40px;
  font-weight: 700;
  border: ${({ theme, themeDisable }) =>
    themeDisable
      ? "1px solid #787878"
      : theme.QuoteCard?.border || "1px solid #bdd400"};
  color: #fff !important;
  background-color: ${({ theme, themeDisable }) =>
    `${
      themeDisable
        ? "#787878"
        : theme.QuoteCard?.color3
        ? theme.QuoteCard?.color3
        : "#bdd400 !important"
    } `};
  &:hover {
    ${(props) =>
      props?.themeDisable ? "" : `background-color: #fff !important`};
    color: ${({ theme, themeDisable }) =>
     `${
        themeDisable
          ? "#787878"
          : theme.QuoteCard?.color3
          ? theme.QuoteCard?.color3
          : "#bdd400"
      } `} !important;
    ${(props) =>
      props?.themeDisable
        ? ""
        : `border: ${({ theme }) =>
            theme.QuoteCard?.border || "1px solid #bdd400 !important"};`};
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
  }
`;

export const Container = styled.div`
  .breadcrumb-item {
    a {
      text-decoration: none;
      &:hover {
        color: ${({ theme }) => theme.QuoteCard?.color};
      }
    }
  }
  @media only screen and (max-width: 768px) {
    .breadcrumb {
      font-size: 12px;
    }
  }
  .breadcrumb-item.active {
    color: ${({ theme }) => theme.QuoteCard?.color};
    font-weight: bold;
  }
`;

export const SubmitButton = styled.button`
  margin-right: 5px;
  margin-top: 15px;
  font-size: 16px;
  border-radius: 5px;
  padding: 8px 40px;
  width: 100%;
  font-weight: 700;
  border: ${({ theme, themeDisable }) =>
    themeDisable
      ? "1px solid #787878"
      : theme.QuoteCard?.border || "1px solid #bdd400"};
  color: #fff !important;
  background-color: ${({ theme, themeDisable }) =>
    `${
      themeDisable
        ? "#787878"
        : theme.QuoteCard?.color3
        ? theme.QuoteCard?.color3
        : "#bdd400 !important"
    } `};
  &:hover {
    ${(props) =>
      props?.themeDisable ? "" : `background-color: #fff !important`};
    color: ${({ theme, themeDisable }) =>
      `${
        themeDisable
          ? "#787878"
          : theme.QuoteCard?.color3
          ? theme.QuoteCard?.color3
          : "#bdd400 !important"
      } `};
    ${(props) =>
      props?.themeDisable
        ? ""
        : `border: ${({ theme }) =>
            theme.QuoteCard?.border || "1px solid #bdd400 !important"};`};
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
  }
`;
