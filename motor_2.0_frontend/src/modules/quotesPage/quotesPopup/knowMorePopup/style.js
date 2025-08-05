import { Col } from "react-bootstrap";
import styled from "styled-components";

// know more popup styles
export const ContentWrap = styled.div`
  padding: 0px 0px 0px 14px;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  font-size: 12px;
  line-height: 22px;
  position: relative;
  overflow-x: clip;
  @media (max-width: 993px) {
    padding: 0px 0px 0px 0px;
  }
  .div-body-tag {
    box-shadow: rgb(0 0 0 / 10%) 0px 8px 25px -5px,
      rgb(0 0 0 / 4%) 0px 10px 10px -5px;
    width: 100%;
    height: 100%;
    th {
      border: 1px solid #fff !important;
    }
  }
  .t-body-tag td {
    border: 1px solid #fff !important;
    color: red !important;
  }
`;

export const Colum = styled(Col)`
  display: block;
  position: sticky;
  top: 0;
  height: 600px;
  overflow: auto;
  @media (max-width: 993px) {
    display: none;
  }
  @media (max-width: 1300px) {
    height: 500px;
  }
  &::-webkit-scrollbar {
    display: none;
  }
`;

export const DetailsPopHeadWrap = styled.div`
  float: left;
  width: 294px;
  padding: 0 0px;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: ${({ theme }) => theme.regularFont?.textColor || ""};
  @media (max-width: 992px) {
    width: 100% !important;
  }
`;
export const CardTopRightCenter = styled.div`
  margin: 0 auto;
  display: inline-block;
  width: 96%;
  @media (max-width: 992px) {
    width: 99% !important;
  }
`;
export const BuyButton = styled.button`
  position: relative;
  float: left;
  display: flex;
  justify-content: center !important;
  align-items: center !important;
  margin-top: 6px;
  background-color: ${({ theme }) => theme.QuotePopups?.color || "#bdd400"};
  border: ${({ theme }) => theme.QuotePopups?.border || "1px solid #bdd400"};
  color: #fff !important;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-SemiBold"};
  font-size: 12px;
  line-height: 40px;
  border-radius: ${({ theme }) =>
    theme.QuoteBorderAndFont?.borderRadius || "50px"};
  margin-left: 0;
  outline: none;
  // width: ${import.meta.env.VITE_BROKER === "UIB" ? `245px` : `255px`};
  width: 100%;
  margin-top: 20px;
  line-height: 24px;
  height: 50px;
  font-weight: bold;
  .div {
    width: 106px;
    position: relative;
    margin: 0 auto;
    height: 28px;
  }
  .span {
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamily || "Inter-SemiBold"};
    font-size: 14px;
    line-height: 2;
    display: contents;
    float: left;
  }
  @media (max-width: 992px) {
    width: 100% !important;
  }
  &:hover {
    ${(props) =>
      props?.themeDisable ? "" : `background-color: #fff !important`};
    color: ${({ theme, themeDisable }) =>
      `${
        themeDisable
          ? "#787878"
          : `${theme.QuoteCard?.color} !important`
          ? `${theme.QuoteCard?.color} !important`
          : `${theme.QuotePopups?.color3} !important`
          ? `${theme.QuotePopups?.color3} !important`
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
export const DetailPopTabs = styled.div`
  float: left;
  border-left: 1px solid #e3e4e8;
  width: 100%;
  min-width: 777px;
  @media (max-width: 992px) {
    margin-top: 0px;
    float: right;
    border-left: 0px solid #e3e4e8;
    width: 100%;
    min-width: 320px;
  }

  .nav-tabs > li.active > a {
    color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "UIB" && theme.QuotePopups?.color
        ? theme.QuotePopups?.color
        : "#333"} !important;
    border-bottom: 1px solid #333 !important;
    box-shadow: ${({ theme }) =>
      theme.boldBorder?.boxShadow || "#f3ff916b 0px -50px 36px -28px inset"};
    height: 50px !important;
  }
  .nav-tabs > li > a,
  .nav-tabs > li > a:hover,
  .nav-tabs > li > a:focus {
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  }
`;

export const TabContet = styled.div`
  padding: 0;
  .premBreakup {
    .table td,
    .table th {
      padding: 5px;
      vertical-align: top;
      border-top: 1px solid #dee2e6;
      color: ${({ theme }) => theme.regularFont?.textColor || "#212529"};
    }
  }
`;

export const Body = styled.div`
  width: 100%;
  position: relative;
  padding: 15px;
  .cashlessHeading {
    font-weight: 800;
    fontsize: "1.5rem";
  }
  .cashless_input {
    margin-left: 2%;
  }

  @media (max-width: 768px) {
    .review-back-btn {
      top: 340px !important;
    }
    .cashless_row {
      width: 100%;
    }
    .search_div {
      width: 100% !important;
    }
    .cashless_ui {
      display: flex;
      align-items: center;
      flex-direction: column;
    }
    .cashless_input {
      margin-left: 0px !important;
    }
  }
  @media (max-width: 993px) {
    .cashlessHeading {
      display: flex;
      justify-content: center;
      font-weight: 800 !important;
      margin-bottom: 20px;
      font-size: 20px !important;
    }
  }
`;
export const PdfMail = styled.div`
  ${({ hide }) => (hide ? `visibility: hidden;` : ``)}}
  margin-top: 13px;
  font-size: 16px;
  margin-left: 50px;
  @media (max-width: 992px) {
    margin-top: 20px;
    font-size: 8px;
    margin-left: 10px;
  }
  &:hover {
    color: ${({ theme }) => theme.QuoteCard?.color || "#bdd400"};
  }
`;
export const DetailRow = styled.div`
  width: 100%;
  justify-content: space-between;
  display: flex;
  .amount {
    width: 20%;
    text-align: end;
  }
  .boldText {
    font-weight: 600;
  }
  white-space: ${(nowrap) => (nowrap ? "nowrap" : "unset")};
`;
export const FilterMenuBoxCheckConatiner = styled.div`
  .filterMenuBoxCheck input[type="checkbox"]:checked + label:before {
    background-color: ${({ theme }) => theme.CheckBox?.color || "#bdd400"};
    border: ${({ theme }) => theme.CheckBox?.border || "1px solid #bdd400"};
    box-shadow: ${({ theme }) =>
      theme.QuoteBorderAndFont?.shadowCheck || "none"};
    filter: ${({ theme }) =>
      theme.QuoteBorderAndFont?.filterPropertyCheckBox || "none"};
    .borderless {
      border: none !important;
    }
  }
`;

export const BuyContainer = styled.div`
width: 100%;
  @media (max-width: 992px) {
    width: 99% !important;
  }
`;

export const AddonInfo = styled.div`
  width: 258px;
  float: left;
  position: relative;
  border-radius: 12px;
  padding: 24px 15px 30px 15px;
  margin-top: 10px;
  @media (max-width: 992px) {
    width: 100% !important;
    display: none;
  }
  .addonHead {
    text-align: center;
    font-size: 16px;
    margin-bottom: 15px;
  }
`;

export const CityButton = styled.button`
  background: ${({ theme }) => theme.City?.background || "rgb(243 255 145)"};
  border: ${({ theme }) => theme.City?.border || "1px solid rgb(189 212 0)"};
`;

export const RowTag = styled.div`
  .form-control {
    width: 100% !important;
    margin: auto;
    border: 2px solid #c0c0c0;
  }
  .form-control:focus {
    border: ${({ theme }) =>
      `2px solid ${
        theme.primaryColor?.color || theme?.primaryColor || "#000000"
      }`};
  }
  .search-icon {
    color: ${({ theme }) =>
      theme?.primaryColor?.color || theme?.primaryColor || "#000000"};
  }
  @media (max-width: 1160px) {
    width: 90%;
  }
  @media (max-width: 1050px) {
    width: 80%;
  }
  @media (max-width: 980px) {
    width: 100%;
  }

  @media (max-width: 576px) {
    .city_button {
      padding: 5px 8px !important;
      font-size: 0.75rem !important;
      p {
        margin: 0 !important;
      }
      i {
        margin-top: 4px !important;
      }
    }
    .search_input {
      padding: 26px 15px !important;
      font-size: 0.75rem;
    }
    .i-tag {
      margin-top: 10px !important;
      font-size: 15px !important;
    }
    .my-form {
      padding: 5px 15px !important;
      font-size: 0.75rem !important;
    }
  }
`;

// know More Info Style

export const FormLeftCont = styled.div`
  width: 258px;
  float: left;
  position: relative;
  border-radius: 12px;
  padding: 24px 15px 0 15px;
  @media (max-width: 992px) {
    width: 100% !important;
  }
`;

export const FormLeftLogoNameWrap = styled.div`
  float: left;
  width: 100%;
  margin-bottom: 0;
`;

export const FormLeftLogo = styled.div`
  margin: 0px 0px 15px;
  display: flex;
  justify-content: center;
  & img {
    width: auto;

    height: 56px;
  }

  @media (max-width: 993px) {
    display: flex;
    justify-content: center;
  }
`;

export const FormLeftNameWrap = styled.div`
  float: left;
  width: 100%;
  margin-bottom: 12px;
  text-align: center;
`;

export const FormLeftPlanName = styled.div`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 14px;
  line-height: 18px;
  max-height: 38px;
  overflow: hidden;
`;

export const FormLeftWrap = styled.div`
  float: left;
  width: 100%;
  margin-bottom: 13px;
`;

export const FormleftTerm = styled.div`
  float: left;
  width: 134px;
  margin-right: 0;
`;

export const FormRightTerm = styled.div`
  float: left;
  text-align: right;
`;

export const FormTermDataRow = styled.div`
  float: left;
  margin-bottom: 12px;
  @media (max-width: 993px) {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
  }
`;

export const FormleftTermTxt = styled.div`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamilyBold || "Inter-SemiBold"};
  font-size: 12px;
  line-height: 18px;
  margin-left: 10px;
  & div {
    font-size: 10px !important;
    font-family: ${({ theme }) =>
      theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  }
`;

export const FormleftTermAmount = styled.div`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  font-size: 13px;
  line-height: 18px;
`;

// mobile premium breakup style

export const Container = styled.div`
  display: none;
  height: ${({ innerHeight }) => (innerHeight ? innerHeight + "px" : "100vh")};
  min-height: calc(100vh - 100%);

  @media (max-width: 993px) {
    display: flex;
    flex-direction: column;
    font-size: 10px;
  }
`;
export const Header = styled.div`
  display: flex;
  box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 12px;
  padding: 5px 10px;
  position: relative;
`;
export const LogoContainer = styled.div`
  width: 20%;
`;
export const DataContainer = styled.div`
  width: 40%;
  flex-direction: column;
  .premBreakupHeading {
    color: ${({ theme }) => theme.QuotePopups?.color2 || "#060"};
    padding: 0px 0px 5px 0px;
    font-size: 10px;
    font-weight: 600;
    display: flex;
    justify-content: center;
    align-items: center;
  }
  @media (max-width: 400px) {
    max-width: 100px;
  }
`;
export const PdfEmailContainer = styled.div`
  .mailAndPdfContainer {
    display: flex;
    justify-content: space-evenly;
    align-self: center;
    margin-left: 20px;
  }
  .mailAndPdf {
    display: flex;
    justify-content: center;
    align-self: center;
  }
  .logoWrapper {
    border-radius: 50%;
    background-color: ${({ color, theme }) =>
      theme.FilterConatiner?.color || "#f3ff91"};
    width: 30px;
    justify-content: center;
    display: flex;
    align-items: center;
    height: 30px;
  }
  .emailText {
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 9px;
    margin-left: 5px;
    margin-right: 10px;
  }
`;
export const MBody = styled.div`
  display: flex;
  height: 100%;
  flex-direction: column;
  position: relative;
  overflow-x: hidden;
`;
export const BodyDetails = styled.div`
  display: flex;
  flex-direction: column;
  width: 100%;
  background-color: #ffffff;
  padding: 10px 20px 10px 20px;
  .vehicleDetails {
    display: flex;
    flex-direction: column;
    font-size: 9px;
    background-color: #ebeef3;
    padding: 10px 10px 10px 10px;
    font-weight: 600;
  }
  .addonsAndCpa {
    padding: 2px 0px;
  }
`;
export const BodyPremiumBreakup = styled.div`
  display: flex;
  width: 100%;
  flex-direction: column;
  padding: 10px 0px;
`;

export const MAddonInfo = styled.div`
  margin-top: 10px;
  .addonHead {
    font-size: 11px;
    margin-bottom: 15px;
    text-align: left;
  }
`;
export const MFilterMenuBoxCheckConatiner = styled.div`
  .filterMenuBoxCheck label:before {
    margin: -10px 0px 0 5px;
    right: -25px;
    left: unset;
    height: 14px;
    width: 14px;
    border-radius: 3px;
  }
  .filterMenuBoxCheck label {
    font-size: 10px !important;
    padding-left: 0px;
    border: 1px solid #686868;
  }
  .filterMenuBoxCheck input[type="checkbox"]:checked + label:before {
    background-color: ${({ theme }) => theme.CheckBox?.color || "#bdd400"};
    border: ${({ theme }) => theme.CheckBox?.border || "1px solid #bdd400"};
    box-shadow: ${({ theme }) =>
      theme.QuoteBorderAndFont?.shadowCheck || "none"};
    filter: ${({ theme }) =>
      theme.QuoteBorderAndFont?.filterPropertyCheckBox || "none"};
  }
`;
export const PremiumBreakupMobSection = styled.div`
  padding: 7px 0px;
  margin: 0px 15px;
  border-bottom: 1px solid #ebeef3;
  .premiumBreakupMobSection__header {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 5px;
    display: flex;
    justify-content: space-between;
    .premText {
      font-weight: 600;
      white-space: nowrap;
      margin-left: 4px;
    }
  }
  .premiumBreakupMobSection__content {
    font-size: 10px;
    font-weight: 400;
    padding: 0px 5px;
    display: flex;
    justify-content: space-between;
    .premText {
      font-weight: 400;
      white-space: nowrap;
      display: flex;
      margin-left: 4px;
      align-items: center;
    }
  }
`;

export const BuyButtonMobile = styled.div`
  position: relative;
  padding: 10px 0px;
  display: flex;
  justify-content: center;
  align-items: center;
  position: sticky;
  bottom: 0px;
  font-size: 13px;
  background-color: ${({ theme }) => theme.QuotePopups?.color || "#bdd400"};
  height: 50px;
  color: white;
  font-weight: 600;
  width: 100%;
  .amount {
    font-size: 17px;
    margin-left: 10px;
  }
`;
export const InclText = styled.small`
  position: absolute;
  right: 30%;
  top: -3px;
`;

export const GarageLength = styled.small`
  color: gray;
  font-size: 12px;
  display: block;
`;
export const WithGstText = styled.small`
  position: absolute;
  left: 0;
  right: 0;
  color: black;
  top: -24px;
  font-size: 12px;
  font-weight: 700;
`;

export default {
  ContentWrap,
  DetailsPopHeadWrap,
  CardTopRightCenter,
  BuyButton,
  DetailPopTabs,
  TabContet,
  Body,
  PdfMail,
  DetailRow,
  FilterMenuBoxCheckConatiner,
  BuyContainer,
  AddonInfo,
  CityButton,
  RowTag,
  FormLeftCont,
  FormLeftLogoNameWrap,
  FormLeftLogo,
  FormLeftNameWrap,
  FormLeftPlanName,
  FormLeftWrap,
  FormleftTerm,
  FormRightTerm,
  FormTermDataRow,
  FormleftTermTxt,
  FormleftTermAmount,
  Container,
  Header,
  LogoContainer,
  DataContainer,
  PdfEmailContainer,
  MBody,
  BodyDetails,
  BodyPremiumBreakup,
  MAddonInfo,
  MFilterMenuBoxCheckConatiner,
  PremiumBreakupMobSection,
  BuyButtonMobile,
  InclText,
  GarageLength,
  WithGstText,
  Colum,
};
