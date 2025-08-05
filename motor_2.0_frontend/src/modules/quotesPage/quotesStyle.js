import styled, { createGlobalStyle } from "styled-components";

export const MainContainer = styled.div`
  width: 100%;
  background: #fff;
  color: ${({ theme }) => theme.regularFont?.textColor || "#707070"};

  overflow: hidden;
  .quoteConatinerCards {
    bottom: 100px !important;
    @media (max-width: 990px) {
      bottom: 0px !important;
    }
  }
  @media (max-width: 767px) {
    .comprehensive_tab {
      font-size: 11px !important;
    }
    .tp_tab {
      font-size: 11px !important;
    }
  }
  @media (max-width: 450px) {
    .comprehensive_tab {
      font-size: 10px !important;
      letter-spacing: 0;
    }
    .tp_tab {
      font-size: 10px !important;
      letter-spacing: 0;
    }
  }
`;
export const NoQuote = styled.div`
  width: 100%;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  margin-top: 100px;
`;
export const ErrorContainer = styled.div`
  width: 100%;
  margin-top: 30px;
  margin-bottom: 30px;
`;

export const ErrorContainer1 = styled.div`
  border-radius: 10px;
  border: 1px dashed black;
  font-size: 14px;
  max-width: fit-content;
  display: flex;
  flex-flow: column wrap;
  -webkit-box-pack: center;
  place-content: center;
  -webkit-box-align: center;
  align-items: center;
  margin: 5rem auto 0px;
  padding: 35px 35px;
  @media (max-width: 993px) {
    padding: 35px 10px;
  }
  .is__getquote__title {
    font-size: 18px;
    @media (max-width: 993px) {
      font-size: 12px;
    }
  }
  .is__getquote__logos {
    display: flex;
    flex-flow: row wrap;
    -webkit-box-pack: center;
    place-content: flex-start center;
    align-items: flex-start;
    margin-top: 24px;
    margin-bottom: 20px;
    width: 100%;
  }
  .is__getquote__info_label {
    font-size: 16px;
    margin-bottom: 10px;
    @media (max-width: 993px) {
      font-size: 14px;
    }
  }
  .is__getquote__info {
    width: 100%;
    text-align: justify;
    padding-right: 40px;
    color: rgba(49, 68, 81, 0.7);
    @media (max-width: 993px) {
      font-size: 11px;
    }
  }
  .is__getquote__logos .img-responsive {
    display: block;
    max-width: 100%;
  }
  .is__getquote__logos img {
    margin: 5px;
    max-height: 50px;
    max-width: 110px !important;
  }
`;
export const NonStickyRows = styled.div`
  margin-top: 100px;
  width: 100%;
  @media (max-width: 993px) {
    position: relative !important;
    top: 0px;
    width: 100%;
    margin-top: 0px;
  }
  .tabWrappers {
    position: relative;
    bottom: 70px;
    left: 175%;
  }

  @media (max-width: 993px) {
    .tabWrappers {
      position: relative;
      top: -50px !important;
      left: 22px;
      z-index: 1000;
    }
  }
  @media (max-width: 767px) {
    .tabWrappers {
      top: -60px !important;
    }
  }
`;

export const ProgrssBarContainer = styled.div`
  .progress {
    height: 5px;
    left: 15px;
    top: 40px;
    border-radius: 15px;
    background-color: #f7f7fa !important;
    width: 91%;
    @media (max-width: 993px) {
      width: 100%;
      top: 50px;
      left: 0px;
    }
    @media (max-width: 670px) {
      top: 30px;
    }
  }
  .bg-info {
    border-radius: 8px;
    background-color: ${({ theme }) =>
      `${
        theme.QuoteCard?.color
          ? `${theme.QuoteCard?.color} !important`
          : "#bdd400 !important"
      }`};
  }
`;

export const FilterTopBoxTitle = styled.div`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  position: absolute;
  top: -20px;
  font-size: ${(props) => (props.exp ? "15px" : "14px")};
  ${({show}) => (show ? `visibility: hidden;` : ``)}
  line-height: 20px;
  margin-bottom: 6px;
  padding-top: 6px;
  float: initial;
  white-space: nowrap;
  text-align: "left";
  .quoteLen {
    color: black;
  }
  .foundMessageQuote {
    color: ${({ theme }) => theme.regularFont?.textColor || "#707070"};
  }
  .expiryText {
    color: ${({ theme }) => theme.regularFont?.textColor || "#707070"};
  }
  @media (max-width: 1200px) {
    font-size: 12px;
  }
  @media (max-width: 993px) {
    top: 30px;
    width: 95%;
    display: flex;
    justify-content: flex-start;
    margin-left: 0px;
  }
  @media (max-width: 768px) {
    top: 5px !important;
    left: 0px !important;
  }
`;

export const SortContainer = styled.div`
  width: 100%;
  z-index: 999;
  left: 350%;
  bottom: 12px;
  position: relative;
  text-align: right;
  .isActive {
    color: ${({ theme }) => theme.QuoteCard?.color2 || "#060 !important"};
  }
  @media only screen and (max-width: 993px) {
    width: 90%;
    margin: 10px 30px;
    position: relative;
    left: 145px;
    display: flex;
    justify-content: center;
  }
  @media only screen and (max-width: 993px) {
    margin: -32px 30px;
  }
  @media only screen and (max-width: 400px) {
    margin: -35px 30px;
  }
`;
export const ViewContainer = styled.div`
  display: flex;
  align-items: center;
  justify-content: flex-end;
  cursor: pointer;
  width: 100%;
  z-index: 999;
  left: 200%;
  bottom: 12px;
  position: relative;
  text-align: right;
  @media only screen and (max-width: 993px) {
    width: 90%;
    margin: 10px 30px;
    position: relative;
    left: 0px;
    display: flex;
    justify-content: center;
  }
  @media only screen and (max-width: 993px) {
    margin: -32px 30px;
  }
  @media only screen and (max-width: 400px) {
    margin: -35px 30px;
  }
`;

export const IconTab = styled.div`
  cursor: pointer;
  color: ${({ isActive, theme }) =>
    isActive ? theme.FilterConatiner?.lightColor : "#686868"};
`;

export const MobileFilterButtons = styled.button`
  background-color: white;
  height: 50px;
  width: 50px;
  z-index: 10000;
  border: none;
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  .disabled {
    color: gray;
    text-decoration: none;
  }
`;

export const AddonDrawerContent = styled.div`
  display: flex;
  flex-direction: column;
  border-bottom: none;
  z-index: 10000;
  max-width: 285px;
  min-width: 280px;
  .addonMobileTitle {
    color: ${({ theme }) => theme.QuoteCard?.color2 || "#060 !important"};
  }
`;
export const AddonDrawerHeader = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px;
  font-size: 22px;
  box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 12px;
`;

export const AddonDrawerFooter = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  position: sticky;

  bottom: 0;
  width: 100%;
  background: white;
  width: 99%;

  .addonDrawerFooterClear {
    & u {
      border-bottom: 1px dotted #000;
      text-decoration: none;
    }
  }
  .addonDrawerFooterApply {
    height: 40px;
    width: 100px;
    border-radius: 20px;
    background-color: ${({ theme }) =>
      `${
        theme.QuoteCard?.color
          ? `${theme.QuoteCard?.color} !important`
          : "#bdd400 !important"
      }`};
    color: #ffffff;
    display: flex;
    justify-content: center;
    align-items: center;
  }
`;

export const MobileAddonButtonsContainer = styled.div`
  display: flex;
  width: 70%;
  justify-content: space-between;
  align-items: center;
  position: relative;
  bottom: 55px;
  padding: 0px 10px 0px 20px;
`;
export const MobileAddonButton = styled.button`
  padding: 5px 3px;
  border-radius: 20px;
  background: white;
  border: 1px solid white;
  font-size: ${({ min, lessthan360 }) =>
    min ? (lessthan360 ? "10px" : "10.5px") : "10.5px"};
  font-weight: 600;
  min-width: 60px;
  box-shadow: ${({ checked, theme }) =>
    checked
      ? `${
          theme.QuoteCard?.color ? `${theme.QuoteCard?.color}` : "#bdd400"
        } 0px 2px 8px`
      : "rgba(0, 0, 0, 0.15) 0px 2px 8px"};
  @media only screen and (max-width: 330px) {
    font-size: 8px;
    min-width: 60px;
  }
  @media only screen and (max-width: 400px) {
    min-width: ${({ min, lessthan360 }) => (min ? "66px" : "60px")};
  }
`;

export const MobileMoreAddonButton = styled.button`
  padding: 0px 0px;
  border-radius: 20px;
  background: white;
  border: 1px solid white;
  box-shadow: rgba(0, 0, 0, 0.15) 0px 2px 8px;
  font-size: 16.5px;
  font-weight: 600;
  min-width: 25px;
  @media only screen and (max-width: 400px) {
    min-width: 20px;
  }
  @media only screen and (max-width: 360px) {
    display: none;
  }
`;

export const BottomTabsContainer = styled.div`
  display: none;
  @media only screen and (max-width: 992px) {
    display: flex;
    position: fixed;
    bottom: 0;
    background: white;
    box-shadow: rgba(0, 0, 0, 0.16) 0px 10px 36px 0px,
      rgba(0, 0, 0, 0.06) 0px 0px 0px 1px;
    justify-content: space-around;
    align-items: center;
    height: 50px;
    width: 100%;
    padding: 10px 10px;
  }
  z-index: 500;
`;
export const GlobalStyle = createGlobalStyle`
 ${({ theme }) =>
   theme?.fontFamily &&
   `.switchMini-label { font-family:${theme?.fontFamily};
}`};
`;

export default {
  MainContainer,
  NoQuote,
  ErrorContainer,
  ErrorContainer1,
  NonStickyRows,
  ProgrssBarContainer,
  FilterTopBoxTitle,
  SortContainer,
  ViewContainer,
  IconTab,
  MobileFilterButtons,
  AddonDrawerContent,
  AddonDrawerHeader,
  AddonDrawerFooter,
  MobileAddonButtonsContainer,
  MobileAddonButton,
  MobileMoreAddonButton,
  BottomTabsContainer,
  GlobalStyle,
};
