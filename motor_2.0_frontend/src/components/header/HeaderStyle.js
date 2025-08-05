import styled from "styled-components";

export const Navbar = styled.div`
  background-color: #ffffff;
  position: ${({ sticky }) =>
    sticky ? " fixed !important" : `relative !important`};
  z-index: ${({ sticky }) => (sticky ? "1000" : `0`)};

  width: 100%;
  border-bottom: solid 1.5px #e3e4e8;
  margin: 0;

  height: 60px;

  display: flex;
  justify-content: space-between;
  align-items: center;
  @media (max-width: 768px) {
    padding: 18px 25px 18px 15px;
  }

  @media (max-width: 993px) {
    position: relative !important;
    height: 72px;
  }
  @media (max-width: 767px) {
    position: relative !important;
    height: 48px;
    z-index: ${({ RegistrationRoute, location }) =>
      import.meta.env.VITE_BROKER === "RB" &&
      RegistrationRoute.includes(location.pathname)
        ? "9999 !important"
        : "0"};
  }
  #profileDetailBox {
    ${(props) => (!props?.visiblityLogin ? `display: none !important;` : ``)};
  }
  .jav_widgetxtR {
    ${(props) => (!props?.visiblityLogin ? `display: none !important;` : ``)};
  }
  .circleStyle {
    ${(props) =>
      !props?.lessthan767
        ? `position: relative !important; left: -15px !important; top: -5px !important;`
        : `position: relative !important; top: -12px !important;`};
    ${(props) => (!props?.visiblityLogin ? `display: none !important;` : ``)};
  }
  .loginStyle1 {
    ${(props) =>
      !props?.lessthan767
        ? `position: relative !important;
        left: -15px !important;
        height: 34px;
        top: -4.5px !important;
        width: 135px;
        margin: 0 30px 0 0;
        position: relative;
        border-radius: 4px;
        border: 1px;`
        : ``};
    ${(props) => (!props?.visiblityLogin ? `display: none !important;` : ``)};
  }
  #jsWidgetlogout {
    ${(props) => (props?.wevView ? `display: none !important;` : ``)};
  }
  padding: ${({ quotes }) =>
    quotes ? "18px 68px 18px 75px" : `18px 30px 18px 45px`};
`;
export const CallButton = styled.span`
  display: ${({ show }) => (show ? "visible" : "none")};
  ${({ marginR }) => marginR && `margin-right: ${marginR}`};
  @media (max-width: 767px) {
    margin-top: -12.3px !important;
    display: inline-block;
    padding-top: 5px;
    margin-right: 0px;
    & > a > svg {
      width: 38px;
      height: 38px;
      border: 1px solid #777;
      padding: 4px;
      border-radius: 50%;
    }
  }
`;

export const ConfirmButton = styled.button`
  display: ${({ hide }) => (hide ? "none" : "")} !important;
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  position: relative;
  top: -4.5px;
  transition: 0.2s ease-in-out;
  background-color: ${({ broker }) => (broker ? "#D0D0D0D0" : "#ffffff")};
  border: ${({ theme, broker }) =>
    broker ? "#A8A8A8" : theme.Header?.border || "1px solid #bdd400"};
  padding: 11px 0;
  border-radius: 4px;
  z-index: 2;
  width: ${({ btnwidth }) => (btnwidth ? btnwidth : "135px")};
  height: 34px;
  font-size: 16px;
  color: ${({ theme }) => theme.regularFont?.textColor || "#000000"};

  font-weight: 400;
  outline: none;
  margin-right: ${({ marginR }) => (marginR ? marginR : "30px")};
  cursor: pointer;
  &:disabled {
    cursor: not-allowed !important;
  }
  .mdoutline {
    font-size: 20px;
  }
  &:focus {
    outline: none;
  }
  &:hover {
    background: ${({ theme }) =>
      theme?.proposalProceedBtn?.hex1 || "#bdd400"}!important;
    label,
    i {
      color: #fff !important;
    }
    .mdoutline {
      color: #fff;
    }
    img {
      filter: invert(97%) sepia(83%) saturate(3866%) hue-rotate(256deg)
        brightness(150%) contrast(98%);
    }
  }
  @media (max-width: 767px) {
    margin-top: -12.3px !important;
    display: inline-block;
    padding-top: 5px;
    margin-right: 15px;
    & > a > svg {
      width: 38px;
      height: 38px;
      border: 1px solid #777;
      padding: 4px;
      border-radius: 50%;
    }
    & svg {
      width: 12px;
      height: 8px;
      margin-right: 6px;
    }
    &:hover {
      background-color: ${({ theme, broker }) =>
        broker ? "#D0D0D0D0" : theme.Header?.color || "#bdd400"};
      color: ${({ theme, broker }) => (broker ? "#000" : "#ffffff")};
      .box-decoration {
        filter: brightness(0) invert(1);
      }
    }
  }
  @media (max-width: 993px) {
    margin-top: 6px;
    margin-right: 15px;
  }
`;

export const SideMenu = styled.nav`
  & > div {
    background: #f6f6f6;
  }
  .checkbox {
    display: none !important;
  }
  .button {
    cursor: pointer;
    margin: 0;
  }
  .button svg {
    cursor: pointer;
  }
  .nav {
    height: 100%;
    background-color: black;
    width: 0;
    position: fixed;
    right: 0;
    top: 0;
    opacity: 0;
    z-index: 1500;
    transition: all 0.2s;
    visibility: hidden;
    transition: width 500ms ease-in-out, visibility 250ms ease-in-out,
      opacity 200ms ease-in-out;
  }
  .list {
    list-style-type: none;
    padding: 0;
    margin-top: 30px;
    li a {
      padding: 8px 8px 8px 32px;
      text-decoration: none;
      font-family: ${({ theme }) =>
        theme?.fontFamily ? theme?.fontFamily : `"basier_squaremedium"`};
      font-size: 20px;
      line-height: 24px;
      color: #ffffff;
      display: block;
    }
  }

  li a:link,
  li a:visited {
    text-decoration: none;
  }
  li a:hover {
    background-color: #056b88;
  }

  .checkbox:checked ~ .nav {
    width: 280px;
    opacity: 1;
    visibility: visible;
    display: flex;
    flex-direction: column;
  }

  .close {
    opacity: 1;
    float: right;
    margin-right: 20px;
    margin-top: 20px;
    padding: 5px;
    font-size: 0;
    cursor: pointer;
    align-self: flex-end;
  }
  .close:hover {
    background-color: #056b88;
    opacity: 1;
  }
  .close svg {
    width: 20px;
    height: 20px;
  }
  .close:not(:disabled):not(.disabled):focus,
  .close:not(:disabled):not(.disabled):hover {
    opacity: 1;
  }
`;
export const PosWrapper = styled.div`
  display: flex;
  position: relative;
  top: ${({ lessthan767 }) => (lessthan767 ? `1px` : `-5px`)};
  .circle {
    padding: ${({ lessthan767 }) => (lessthan767 ? `5px` : `3px`)};
    margin: 0px 8px 10px;
    border-radius: ${({ lessthan767 }) => (lessthan767 ? `6px` : `100%`)};
    background: ${(props) =>
      props?.theme?.Stepper?.stepperColor?.background || "#bdd400"};
  }

  @media (max-width: 768px) {
    display: unset !important;
  }
`;
export const PosId = styled.span`
  font-size: ${({ small, latter, lessthan767 }) =>
    lessthan767 ? "14px" : small ? "9.5px" : latter ? "18px" : "12px"};
  padding: ${({ latter, lessthan767 }) =>
    lessthan767 ? "5px" : latter && "8.5px 9px"};
`;
export const PosDiv = styled.div`
  display: flex;
  flex-direction: column;
  align-items: left;
  position: relative;
  top: -4.5;
  margin-top: 0.4px;
`;
export const Wrap = styled.div`
  display: flex;
  align-items: center;
  margin-right: 10px;
`;

export const Logo = styled.img`
  width: ${import.meta.env.VITE_BROKER === "ACE"
    ? "125px"
    : import.meta.env.VITE_BROKER === "SRIYAH" ||
      import.meta.env.VITE_BROKER === "RB" ||
      import.meta.env.VITE_BROKER === "BAJAJ" ||
      import.meta.env.VITE_BROKER === "UIB" ||
      import.meta.env.VITE_BROKER === "SRIDHAR" ||
      import.meta.env.VITE_BROKER === "POLICYERA" ||
      import.meta.env.VITE_BROKER === "KMD" ||
      import.meta.env.VITE_BROKER === "TATA"
    ? "auto"
    : import.meta.env.VITE_BROKER === "SPA"
    ? "260px"
    : import.meta.env.VITE_BROKER === "HEROCARE" ||
      import.meta.env.VITE_BROKER === "VCARE" ||
      import.meta.env.VITE_BROKER === "WOMINGO" ||
      import.meta.env.VITE_BROKER === "FYNTUNE" ||
      import.meta.env.VITE_BROKER === "KAROINSURE" ||
      import.meta.env.VITE_BROKER === "ONECLICK"
    ? "auto"
    : "160px"};
  height: ${import.meta.env.VITE_BROKER !== "FYNTUNE"
    ? import.meta.env.VITE_BROKER === "ACE" ||
      import.meta.env.VITE_BROKER === "SRIYAH"
      ? "51px"
      : import.meta.env.VITE_BROKER === "BAJAJ"
      ? "25px"
      : import.meta.env.VITE_BROKER === "RB"
      ? "81px"
      : import.meta.env.VITE_BROKER === "SPA"
      ? "60px"
      : import.meta.env.VITE_BROKER === "TATA"
      ? "57px"
      : import.meta.env.VITE_BROKER === "UIB"
      ? "42px"
      : import.meta.env.VITE_BROKER === "HEROCARE"
      ? "55px"
      : import.meta.env.VITE_BROKER === "SRIDHAR" ||
        import.meta.env.VITE_BROKER === "KMD"
      ? "55px"
      : "45px"
    : "38px"};
  @media (max-width: 768px) {
    width: ${import.meta.env.VITE_BROKER === "ACE"
      ? "115px"
      : import.meta.env.VITE_BROKER === "SRIYAH"
      ? "85px"
      : import.meta.env.VITE_BROKER === "HEROCARE"
      ? "auto"
      : import.meta.env.VITE_BROKER === "RB" ||
        import.meta.env.VITE_BROKER === "SPA" ||
        import.meta.env.VITE_BROKER === "BAJAJ" ||
        import.meta.env.VITE_BROKER === "UIB" ||
        import.meta.env.VITE_BROKER === "SRIDHAR" ||
        import.meta.env.VITE_BROKER === "POLICYERA" ||
        import.meta.env.VITE_BROKER === "KMD" ||
        import.meta.env.VITE_BROKER === "TATA" ||
        import.meta.env.VITE_BROKER === "PAYTM" ||
        import.meta.env.VITE_BROKER === "VCARE" ||
        import.meta.env.VITE_BROKER === "WOMINGO" ||
        import.meta.env.VITE_BROKER === "KAROINSURE"
      ? "auto"
      : "130px"};
    height: ${import.meta.env.VITE_BROKER === "RB"
      ? "60px"
      : import.meta.env.VITE_BROKER === "BAJAJ"
      ? "23px"
      : import.meta.env.VITE_BROKER === "UIB"
      ? "35px"
      : import.meta.env.VITE_BROKER === "POLICYERA"
      ? "35px"
      : import.meta.env.VITE_BROKER === "TATA"
      ? "45px"
      : import.meta.env.VITE_BROKER === "HEROCARE"
      ? "41px"
      : import.meta.env.VITE_BROKER !== "FYNTUNE"
      ? "45px"
      : "32px"};
  }
  @media (max-width: 415px) {
    width: ${import.meta.env.VITE_BROKER === "ACE"
      ? "115px"
      : import.meta.env.VITE_BROKER === "SRIYAH"
      ? "85px"
      : import.meta.env.VITE_BROKER === "HEROCARE"
      ? "auto"
      : import.meta.env.VITE_BROKER === "RB" ||
        import.meta.env.VITE_BROKER === "SPA" ||
        import.meta.env.VITE_BROKER === "BAJAJ" ||
        import.meta.env.VITE_BROKER === "UIB" ||
        import.meta.env.VITE_BROKER === "SRIDHAR" ||
        import.meta.env.VITE_BROKER === "POLICYERA" ||
        import.meta.env.VITE_BROKER === "KMD" ||
        import.meta.env.VITE_BROKER === "TATA" ||
        import.meta.env.VITE_BROKER === "PAYTM" ||
        import.meta.env.VITE_BROKER === "VCARE" ||
        import.meta.env.VITE_BROKER === "WOMINGO" ||
        import.meta.env.VITE_BROKER === "KAROINSURE"
      ? "auto"
      : "130px"};
    height: ${import.meta.env.VITE_BROKER !== "FYNTUNE"
      ? import.meta.env.VITE_BROKER === "ACE" ||
        import.meta.env.VITE_BROKER === "SRIYAH"
        ? "auto"
        : import.meta.env.VITE_BROKER === "RB"
        ? "60px"
        : import.meta.env.VITE_BROKER === "WOMINGO"
        ? "25px"
        : import.meta.env.VITE_BROKER === "SPA"
        ? "34px"
        : import.meta.env.VITE_BROKER === "BAJAJ"
        ? "18px"
        : import.meta.env.VITE_BROKER === "POLICYERA"
        ? "30px"
        : import.meta.env.VITE_BROKER === "TATA"
        ? "38px"
        : import.meta.env.VITE_BROKER === "HEROCARE"
        ? "41px"
        : import.meta.env.VITE_BROKER === "UIB"
        ? "35px"
        : import.meta.env.VITE_BROKER === "PAYTM"
        ? "37px"
        : "45px"
      : "38px"};
  }
`;

export const SendQuery = styled.i`
  color: #656565;
  max-height: 38px;
  font-size: 25px;
  border: 1px solid #777777;
  border-radius: 50px;
  padding: 6px 8px 7px 7px;
  cursor: pointer;
`;

export const QuoteId = styled.button`
  font-family: ${({ theme }) =>
    theme.QuoteBorderAndFont?.fontFamily || "Inter-Regular"};
  background-color: #ffffff;
  border: none;
  padding: 11px 0;
  border-radius: 4px;
  z-index: 2;
  width: 161px;
  height: 48px;
  font-size: 12px;
  color: #000000;
  margin-right: 0px;
  font-weight: 600;
  outline: none;
  margin-right: 30px;
  cursor: pointer;
  &:focus {
    outline: none;
  }
  @media (max-width: 768px) {
    width: auto;
  }
  & svg {
    width: 27px;
    height: 24px;
    margin-right: 6px;
  }
  &:hover {
    background-color: #bdd400;
    color: #ffffff;
  }
`;
