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
	.css-i9fmh8-MuiBackdrop-root-MuiModal-backdrop {
    pointer-events: ${({ disabledBackdrop }) =>
      disabledBackdrop ? "none !important" : ""};
  }
}
`;
export const Body = styled.div`
  padding: 0 15px 15px;
  position: relative;
  margin-top: 30px;
`;
export const ModelWrap = styled.div`
  float: left;
  width: 100%;
  padding: 10px 22px 22px 14px;
  min-height: 480px;
  height: 440px;
  max-height: 480px;
  @media (max-width: 993px) {
    max-height: none;
    height: auto;
  }
  .react-datepicker-wrapper {
    display: inline-block;
    padding: 0;
    border: 0;
    width: 82%;
    z-index: -2;
    left: 9%;
    position: relative;
  }
  .react-datepicker-popper {
    left: -46px !important;
  }
  .cr__form-proceed .greetings-wrapper .greetings-text {
    text-align: inherit;
    margin-bottom: 32px;
  }
  .greetings-wrapper .greetings-text {
    font-size: 24px;
    color: #bdd400;
    background: ${({ theme }) =>
      theme.QuotePopups?.lg ||
      "-webkit-linear-gradient(-134deg, #bbd300, #5dae40)"};

    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    display: flex;
    justify-content: center;
    margin-bottom: 15px;
    font-weight: 700;
    margin-top: -15px;
  }

  .dateTimeFour input,
  .dateTimeFour input:focus,
  .dateTimeFour input:active,
  .dateTimeFour input:focus-within {
    display: none;
  }
  .DateInput {
    display: none;
  }
  .DayPicker__withBorder {
    bottom: 40px;
  }
  .DayPickerKeyboardShortcuts_showSpan__bottomRight {
    bottom: 0;
    right: 5px;
    display: none !important;
  }
  .DayPickerKeyboardShortcuts_show__bottomRight::before {
    display: none;
  }
  .CalendarDay__selected,
  .CalendarDay__selected:active,
  .CalendarDay__selected:hover {
    background: ${({ theme }) =>
      `${theme.QuoteCard?.color} !important` || "#bdd400 !important"};
    border: ${({ theme }) => theme.QuoteCard?.border || "1px solid #bdd400"};
    color: #fff;
  }

  .DayPickerKeyboardShortcuts_show__bottomRight::before {
    border-top: 26px solid transparent;
    border-right: ${({ theme }) =>
      theme.QuotePopups?.prevpopBorder || "33px solid #bdd400"};
    bottom: 0;
    right: 0;
  }
  @media (max-width: 768px) {
    .greetings-wrapper .greetings-text {
      font-size: 20px;
    }
  }
`;
export const RegiHeading = styled.div`
  text-align: center !important;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-weight: 600;
  font-size: ${(props) => (props.page3 ? "16px" : "19px")};
  line-height: 24px;
  color: #333;
  width: 100%;
  text-align: left;
  // margin-top: 8px;
  margin-bottom: ${(props) => (props.page3 ? "-10px" : "	-20px")};
  @media (max-width: 768px) {
    font-size: 14.5px;
  }
`;

export const TabContinueWrap = styled.div`
  position: absolute;
  z-index: 10000;
  bottom: 15px;

  left: 0;
  width: 100%;
  text-align: center;
  margin-top: 0;
  border-radius: 10px;
  & div {
    font-size: 15px;
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
    font-weight: 550;
    color: ${({ theme }) => theme.QuoteBorderAndFont?.linkColor || "#00a2ff"};
    cursor: pointer;
    margin-top: 8px;
    &:hover {
      color: ${({ theme }) => theme.QuoteBorderAndFont?.linkColor || "#00a2ff"};
      text-decoration: underline;
    }
  }
`;

export const Page3 = styled.div`
  display: ${(props) => (props.display ? "block" : "none")};
`;

export const Page2 = styled.div`
  display: ${(props) => (props.display ? "block" : "none")};
`;

export const PrevOdTypeContainer = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 0px;
  flex-direction: column;
  width: 100%;
`;

export const OptionsOdType = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 10px;
  flex-direction: column;
  width: 100%;
  @media (max-width: 767px) {
    padding: 5px;
  }
`;

export const OptionCard = styled.div`
  cursor: pointer;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-direction: column;
  padding: 10px;
  width: 100%;
  box-shadow: 0px 6px 16px #3469cb29;
  width: 100%;
  font-weight: 400;
  margin: 0 0 10px 0;
  border-radius: 6px;
  margin-top: 10px;
  .heading {
    font-size: 12.5px;
    font-weight: 600;
    @media (max-width: 767px) {
      font-size: 11.5px;
    }
    @media (max-width: 410px) {
      font-size: 11.5px;
    }
  }
  .subHeading {
    margin-top: 8px;
    font-size: 12.5px;
    font-weight: 400;
    padding: 0px 40px;

    @media (max-width: 767px) {
      text-align: center;
      font-size: 13px;
    }
    @media (max-width: 410px) {
      font-size: 12.5px;
    }
  }
  :hover {
    transform: scale(1.01);
  }
  @media (max-width: 767px) {
    padding: 5px;
  }
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
