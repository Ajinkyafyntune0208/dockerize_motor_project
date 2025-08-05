import styled, { keyframes } from "styled-components";

export const FilterContainerMain = styled.div`
  margin-bottom: 16px;
  height: auto;
  display: flex;
  align-items: end;
  justify-content: center;
  position: ${({ scroll, blockLayout }) =>
    blockLayout ? "fixed" : scroll ? "fixed" : "absolute"};
  top: ${({ theme, scroll, blockLayout }) =>
    blockLayout || scroll
      ? "0px"
      : theme?.QuoteBorderAndFont?.headerTopQuotesPage || "60px"};

  background: white;
  width: 100vw;
  left: 1px;
  overflow-x: clip;
  box-shadow: ${({ theme, scroll, highlighted }) =>
    import.meta.env.VITE_BROKER === "TATA"
      ? "none"
      : highlighted
      ? "none"
      : scroll
      ? " 0 9px 13px #f7f7fa"
      : theme?.QuoteBorderAndFont?.filterShadow || "0 9px 13px #f7f7fa"};

  z-index: ${(props) => (props.highlighted ? "9999999" : "1000")};

  @media (max-width: 993px) {
    width: 100%;
    position: relative !important;
    top: 0px;
  }
  .blueIcon {
    max-height: 17px !important;
    font-size: 17px !important;
    margin-left: 2px !important;
    margin-bottom: 5px !important;
    color: ${({ theme }) =>
      theme?.FilterConatiner?.editIconColor
        ? theme?.FilterConatiner?.editIconColor
        : "none"};
  }
`;
export const FilterMenuWrap = styled.div`
  margin: 10px 0px 0px 20px;

  padding: 8px 0;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  color: #333;
  @media (max-width: 992px) {
    margin: 10px 20px 0px 20px;
  }
`;

export const FilterMenuRow = styled.div`
  float: left;
  width: 100%;
  margin-bottom: 23px;
  text-transform: capitalize;
  &:last-child {
    margin-bottom: 0;
    margin-top: -10px;
    margin-left: 80px;
    @media (max-width: 992px) {
      margin-left: 0px;
    }
  }
`;

export const FilterMenuOpenWrap = styled.div`
  padding-left: ${(props) => (props.highlighted ? "" : "")};
  &:nth-child(2) {
    width: 291px;
    padding-left: 26px;
    margin-right: 16px;
  }
  float: left;
  position: relative;
  padding-bottom: 10px;
  margin-top: 8px;

  z-index: ${(props) => (props.highlighted ? "5000000000 !important" : "")};
  background-color: ${(props) => (props.highlighted ? " white" : "")};
  padding-top: ${(props) => (props.highlighted ? "" : "")};

  @media (max-width: 992px) {
    display: flex !important;
    min-height: 50px;
    width: 100%;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap !important;
  }
`;

export const FilterMenuOpenEdit = styled.div`
  position: absolute;
  top: 25px;
  line-height: 15px;
  padding: 2px 0px;
  cursor: pointer;
  color: #000000;
  font-weight: 600;
  font-size: 12 px;
  line-height: 17px;
  color: ${({ theme }) => theme.regularFont?.textColor || "#707070"};
  min-width: 250px;

  @media (max-width: 992px) {
    margin-bottom: 15px;
    min-width: auto;
  }
  @media only screen and (max-width: 1200px) {
    font-size: 12px !important;
  }
`;

export const FilterMenuOpenTitle = styled.div`
  float: left;
  font-size: 12px;
  line-height: 20px;
  margin-bottom: 8px;
  padding-right: 7px;
  text-overflow: ellipsis;
  white-space: nowrap;
  overflow: hidden;
  max-width: 300px;
  user-select: none;

  @media (max-width: 992px) {
    margin-bottom: 15px;
  }
  @media only screen and (max-width: 1200px) {
    font-size: 10px !important;
  }
  .mmvTexts {
    font-weight: 600;
    float: left;
    line-height: 17px;
    color: ${({ theme }) => theme.regularFont?.textColor || "#707070"};
  }
`;

export const FilterMenuOpenSub = styled.div`
  color: #000000;
  font-weight: 600;
  float: left;
  font-size: 12px;
  line-height: 17px;
  color: ${({ theme }) => theme.regularFont?.textColor || "#707070"};
  min-width: 250px;
  user-select: none;
  cursor: pointer;

  @media (max-width: 992px) {
    margin-bottom: 15px;
    min-width: auto;
  }
  @media only screen and (max-width: 1200px) {
    font-size: 10px !important;
  }
  .subTypeName {
    max-width: 90px;
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
  }
  .subTypeContainer {
    display: flex;
    top: 3px;
    position: relative;
    cursor: pointer;
    @media only screen and (max-width: 993px) {
      top: 0px;
    }
  }
`;

export const FilterMenuOpenSubBold = styled.span`
  color: #000000;
  font-weight: 600;
  cursor: pointer;
  :hover {
    color: ${({ theme }) =>
      theme?.FilterConatiner?.color &&
      (theme?.FilterConatiner?.color || "#bdd400")};
  }
`;

export const FilterMenuQuoteBoxWrap = styled.button`
  width: 85%;

  margin-left: -75px;
  background: ${(props) => (props?.compare ? "#bdd400" : "#fff")};
  border-radius: 8px;
  padding-top: 10px;
  line-height: 27px;

  border-bottom: 2px solid #e0e0e0;
  border-bottom: 2px solid #e0e0e0;
  border: ${(props) =>
    props.exp ? "0px solid #e0e0e0;" : "1px solid #e0e0e0;"};

  float: right;
  position: relative;
  left: ${(props) => (props.exp ? "" : "")};

  padding: 5px 16px 7px;
  z-index: 2;
  @media only screen and (max-width: 992px) {
    width: 90%;
    margin: 10px 30px;
    float: initial;
  }
`;

export const FilterTopBoxChange = styled.div`
  float: right;
  font-size: 12px;
  line-height: 15px;
  color: #000;
  border-radius: 4px;
  padding: 6px 6px;
  cursor: pointer;
  margin-right: -5px;
`;

export const FilterTopBoxTitle = styled.div`
  font-size: ${(props) => (props.exp ? "15px" : "14px")};
  line-height: 20px;
  margin-bottom: 6px;
  border-bottom: ${({ theme }) =>
    theme.FilterConatiner?.lightBorder1 || "1px solid #bdd400"};

  padding: 5px;
  float: initial;
  white-space: nowrap;
  text-align: ${(props) => props.align || "left"};
  color: ${({ theme }) => theme.regularFont?.textColor || "#707070"};
  .quoteLen {
    color: black;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"basier_squareregular"`};
  }
  .foundMessageQuote {
    color: ${({ theme }) => theme.regularFont?.textColor || "#707070"};
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"basier_squareregular"`};
  }
  .expiryText {
    color: ${({ theme }) => theme.regularFont?.textColor || "#707070"};
  }
  @media (max-width: 1200px) {
    font-size: 12px;
  }
`;

export const AlertCover = styled.div`
  width: 100%;
  position: ${(props) => (props.scroll ? "fixed" : "absolute")};
  top: ${(props) => (props.scroll ? "70px" : "126px")};

  top: ${({ theme, scroll }) =>
    scroll ? "70px" : theme?.QuoteBorderAndFont?.alertTop || "126px"};

  z-index: 1000;
  border-bottom-right-radius: 15px;
  border-bottom-left-radius: 15px;
  left: 0px;
  background: ${({ theme }) => theme.FilterConatiner?.color || "#bdd400"};
  display: flex;
  justify-content: center;
  align-items: center;
  color: ${({ theme }) =>
    ["BAJAJ", "HEROCARE"].includes(import.meta.env.VITE_BROKER)
      ? "white"
      : theme.QuoteBorderAndFont?.fontColor || " #000"};
  @media only screen and (max-width: 992px) {
    position: absolute;
    top: 320px;
  }
  @media only screen and (max-width: 993px) {
    position: absolute;
    display: none;
  }
`;

export const FilterCont = styled.span`
  .filterPadding {
    padding: 0px 12px 0px 0px !important;

    @media only screen and (max-width: 1401px) {
      padding: 0px 35px 0px 0px !important;
    }
  }
  .blueIcon {
    max-height: 17px !important;
    font-size: 17px;
    margin-left: 2px !important;
    color: ${({ theme }) =>
      theme?.FilterConatiner?.editIconColor
        ? theme?.FilterConatiner?.editIconColor
        : "none"};
  }
`;
export const FilterContainerMobile = styled.div`
  display: flex;
  flex-direction: column;
  width: 100%;
`;
export const FilterMobileTop = styled.div`
  background: white;
  padding: 2px 20px;
  box-shadow: rgba(50, 50, 93, 0.25) 0px 50px 100px -20px,
    rgba(0, 0, 0, 0.3) 0px 30px 60px -30px;
  z-index: ${(props) => (props.highlighted ? "5000000000 !important" : "")};
`;
export const FilterMobileBottom = styled.div`
  display: flex;
  background: #dddddd;
`;
export const FilterMobileTopItem = styled.div`
  display: flex;
  align-items: center;
  padding: 3px 10px;
  width: 100%;
  justify-content: space-between;
  .rtoNameMobile {
    font-size: ${({ isMobileIOS }) => (isMobileIOS ? "10.5px" : "11px")};
    font-weight: 600;
    ${({ mask }) =>
      mask
        ? `text-overflow: ellipsis;
  white-space: nowrap;
  overflow: hidden;`
        : ``}
    @media only screen and (max-width: 400px) {
      font-size: ${({ isMobileIOS }) => (isMobileIOS ? "10px" : "10.5px")};
      font-weight: 600;
    }
    @media only screen and (max-width: 330px) {
      font-size: 9px;
      font-weight: 600 !important;
    }
  }
  .editImageMobile {
    height: ${({ isMobileIOS }) => (isMobileIOS ? "8.5px" : "9.5px")};
    bottom: 2px;
    position: relative;
    color: ${({ theme }) =>
      theme?.FilterConatiner?.editIconColor
        ? theme?.FilterConatiner?.editIconColor
        : "none"};
    @media only screen and (max-width: 400px) {
      font-size: 7px;
    }
    @media only screen and (max-width: 360px) {
      height: 8.5px;
    }
  }
  .noWrapExpiry {
    white-space: nowrap;
  }
  @media only screen and (max-width: 400px) {
    padding: 3px 4px;
  }
`;
export const FilterMobileBottomItem = styled.div`
  display: flex;
  width: 33.33%;
  flex-direction: column;
  padding: 0px 5px;
  margin: 5px 0px 5px 0px;
  border-right: 1px solid #cccccc;
  .caption {
    font-size: ${({ isMobileIOS }) => (isMobileIOS ? "10.5px" : "11.5px")};
    display: flex;
    font-weight: 600 !important;
    justify-content: center;
    align-items: center;
    @media only screen and (max-width: 400px) {
      font-size: ${({ isMobileIOS }) => (isMobileIOS ? "10px" : "11px")};
      font-weight: 600 !important;
    }
    @media only screen and (max-width: 330px) {
      font-size: 9.5px;
      font-weight: 600 !important;
    }
  }

  .selection {
    font-size: 11.5px;
    font-weight: 600;
    display: flex;
    justify-content: center;
    align-items: center;
    @media only screen and (max-width: 400px) {
      font-size: ${({ isMobileIOS }) => (isMobileIOS ? "10.5px" : "11px")};
    }
    @media only screen and (max-width: 330px) {
      font-size: 9.5px;
      font-weight: 600 !important;
    }
  }
  .arrowDown {
    margin-left: 8px;
    font-size: ${["SRIDHAR", "ACE", "BAJAJ"].includes(
      import.meta.env.VITE_BROKER
    )
      ? "15px"
      : "18px"};
    color: #909090;
  }
  .selectionText {
    font-size: 11.5px;
    font-weight: 600;
    margin-left: 7px;
    @media only screen and (max-width: 400px) {
      font-size: ${({ isMobileIOS }) => (isMobileIOS ? "10.5px" : "11px")};
    }
    @media only screen and (max-width: 330px) {
      font-size: 9.5px;
      font-weight: 600 !important;
    }
  }
`;

export const AlertCoverMobile = styled.div`
  text-align: center;
  font-size: 11px;
  font-weight: bold;
  border-radius: 0px 0px 15px 15px;
  background: ${({ theme }) => theme.FilterConatiner?.color || "#bdd400"};
  color: ${({ theme }) =>
    ["BAJAJ", "HEROCARE"].includes(import.meta.env.VITE_BROKER)
      ? "white"
      : theme.QuoteBorderAndFont?.fontColor || "#000"};
`;
const spin = keyframes`
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
`;
export const SpinnerWrapper = styled.div`
  width: 20px;
  height: 20px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid
    ${(theme) =>
      theme?.FilterConatiner?.editIconColor
        ? theme?.FilterConatiner?.editIconColor
        : `#3498db`};
  border-radius: 50%;
  position: relative;
  z-index: 22;
  animation: ${spin} 2s linear infinite;
`;
export default {
  FilterContainerMain,
  FilterMenuWrap,
  FilterMenuRow,
  FilterMenuOpenWrap,
  FilterMenuOpenEdit,
  FilterMenuOpenTitle,
  FilterMenuOpenSub,
  FilterMenuOpenSubBold,
  FilterMenuQuoteBoxWrap,
  FilterTopBoxChange,
  FilterTopBoxTitle,
  AlertCover,
  FilterCont,
  FilterContainerMobile,
  FilterMobileTop,
  FilterMobileBottom,
  FilterMobileTopItem,
  FilterMobileBottomItem,
  AlertCoverMobile,
  SpinnerWrapper,
};
