import styled, { createGlobalStyle } from "styled-components";
// Edit Details css

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

export const HeaderPopup = styled.div`
  text-align: center !important;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `Merriweather, Georgia, serif`};
  font-weight: 600;
  font-size: 19px;
  line-height: 24px;
  color: #333;
  width: 100%;
`;

export const StyledDatePicker = styled.div`
  .dateTimeOne .date-header {
    background: ${({ Theme1 }) =>
      Theme1
        ? `${Theme1?.reactCalendar?.background} !important`
        : "#4ca729 !important"};
    border: ${({ Theme1 }) =>
      Theme1
        ? `1px solid ${Theme1?.reactCalendar?.background} !important`
        : "1px solid #4ca729 !important"};
  }
  .dateTimeOne .react-datepicker__day:hover {
    background: ${({ Theme1 }) =>
      Theme1
        ? `${Theme1?.reactCalendar?.background} !important`
        : "#4ca729 !important"};
    border: ${({ Theme1 }) =>
      Theme1
        ? `1px solid ${Theme1?.reactCalendar?.background} !important`
        : "1px solid #4ca729 !important"};
  }
  .dateTimeOne input {
    border: ${({ errors }) =>
      errors
        ? "1px solid red !important"
        : "1px solid rgb(153, 153, 153) !important"};
  }
  .dateTimeOne .react-datepicker__day--keyboard-selected,
  .dateTimeOne .react-datepicker__month-text--keyboard-selected,
  .dateTimeOne .react-datepicker__quarter-text--keyboard-selected,
  .dateTimeOne .react-datepicker__year-text--keyboard-selected,
  .dateTimeThree .react-datepicker__day--keyboard-selected,
  .dateTimeThree .react-datepicker__month-text--keyboard-selected,
  .dateTimeThree .react-datepicker__quarter-text--keyboard-selected,
  .dateTimeThree .react-datepicker__year-text--keyboard-selected {
    border-radius: 0.3rem;
    background-color: ${({ Theme1 }) =>
      Theme1
        ? `${Theme1?.reactCalendar?.background} !important`
        : "#4ca729 !important"};
    color: #fff;
  }
`;
export const DetailsSection = styled.div`
  margin: 25px 0;
  display: table;
  width: 100%;
  min-height: 34px;

  .detail {
    float: left;
    width: 64%;
    float: right;
    margin-top: 15px;
  }
  .detail button {
    width: 44%;
    height: 21px;
    border-radius: 10.5px;
    border: solid 1px #212121;
    color: #212121;
    font-size: 14px;
    line-height: 12px;
    margin-right: 20px;
    padding: 0 12px;
    background: #fff;
  }
  .detail button.selected {
    color: #fff;
    border: solid #1596fe 1px;
    background: #1596fe;
  }
  .editDate {
    position: relative !important;
    z-index: 1000 !important;
  }
  .DetailsSection {
    width: 67%;
  }
  .vehicleCategory {
    height: 40px !important;
  }
  @media (max-width: 993px) {
    width: 100%;
  }
  @media (max-width: 767px) {
    margin: 0;
  }
`;

// edit details 2

// edit 2
export const MobileDrawerBody2 = styled.div`
  width: 100%;
  border-radius: 3px 3px 0px 0px;
  overflow-x: ${({ isMobileIOS }) => isMobileIOS && "hidden !important"};
`;

export const ContentWrap = styled.div`
  padding: 0px 32px 20px 32px;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  font-size: 14px;
  line-height: 22px;
  color: #333;
  position: relative;
  margin-top: 30px;
  overflow-x: ${({ isMobileIOS }) => isMobileIOS && "hidden !important"};
`;
export const ContentBox = styled.div`
  height: auto;
  width: 562px;
  margin: 12px auto 24px;
  background-color: #fff;
  .hiddenInput {
    display: none;
  }
  @media screen and (max-width: 993px) {
    width: 100%;
    margin-top: 50px;
  }
`;
export const DetailsWrapper = styled.div`
  font-size: 16px;
  text-align: center;
  line-height: 25px;
  margin-top: -15px;
  max-height: 45px !important;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  .vehicleDetails {
    max-width: 100%;
    overflow: hidden;
    font-weight: 900;
  }
`;

export const DetailsSectionLabel = styled.div`
  float: left;
  width: 120px;
  font-size: 14px;
  color: #696969;
  font-weight: 300;
  margin-top: 15px !important;
  white-space: pre;
`;

export const UpdateButton = styled.button`
  background: ${({ theme }) => theme.QuotePopups?.color || "#bdd400"};
  color: #fff;
  border: ${({ theme }) => theme.QuotePopups?.border || "1px solid #bdd400"};
  width: 134px;
  height: 36px;
  border-radius: 3px;
  font-size: 12px;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: auto;
  margin-bottom: 0;
  margin-top: 30px;
  &:hover {
    ${import.meta.env.VITE_BROKER === "TATA" &&
    `
    background: transparent;
    color: #0099f2;
  `}
  }
`;

export const PremChangeWarning = styled.div`
  display: flex;
  justify-content: center;
  align-content: center;
  align-items: center;
  width: 100%;
  .ncb_msg {
    line-height: normal;
    padding-left: 52px;
    display: flex;
    align-items: center;
    margin-top: 20px;
    margin-left: auto;
    margin-right: auto;
    margin-top: 20px;
    width: 90%;
    height: 48px;
  }
  .ncb_msg .image {
    background-image: url(${import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ""}/assets/images/icon/bulb.png);
    width: 93px;
    height: 92px;
    left: -28px;
  }
  .newpopup_wrapper .ncb_msg p {
    color: ${({ theme }) => theme.QuotePopups?.color || "#bdd400"};
    line-height: normal;
    font-size: 12px;
  }
  .messagetxt {
    margin-top: 10px;
    margin-left: 10px;
  }
  @media screen and (max-width: 993px) {
    display: none;
  }
`;

export const CarLogo = styled.img`
  height: 100px;
  width: 100px;
  max-wodth: 100px;
  max-height: 100px;
  margin-right: 10px;
  position: relative;
  @media (max-width: 767px) {
    width: 60px;
    height: 60px;
    margin-top: 2px;
  }
`;
