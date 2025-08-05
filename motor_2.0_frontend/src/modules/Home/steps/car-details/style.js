import styled, { createGlobalStyle } from "styled-components";

export const StyledDiv = styled.div`
  height: ${({ Step }) => (Number(Step) === 6 ? "710px" : "650px")};
  @media (max-width: 768px) {
    height: 900px;
  }
  @media (max-width: 600px) {
    height: unset;
  }
  @media (max-width: 400px) {
    height: unset;
  }
  ${({ lessthan600 }) =>
    lessthan600
      ? `::-webkit-scrollbar {
    width: 0;
    height: 0;
}`
      : ``}
`;

export const ListDiv = styled.div`
  .wrapper-progressBar {
    width: 100%;
  }

  .progressBar {
    width: 100%;
    margin: 0;
    padding: 0;
  }

  .progressBar .general {
    list-style-type: none;
    float: left;
    width: 16.61%;
    position: relative;
    text-align: center;
  }

  .progressBar .customStep {
    list-style-type: none;
    float: left;
    width: 20%;
    position: relative;
    text-align: center;
  }

  .progressBar .customStep2 {
    list-style-type: none;
    float: left;
    width: 25%;
    position: relative;
    text-align: center;
  }
`;

export const ListItem = styled.li`
  &::before {
    content: " ";
    line-height: 30px;
    border-radius: 50%;
    width: 17px;
    height: 17px;
    // border: 1px solid #bdd400;
    border-left: none;
    display: block;
    text-align: center;
    transition: ${import.meta.env.VITE_BROKER === "TATA"
      ? "0.1s ease-in"
      : "0.5s ease-in"};
    margin: 8.5px auto 0px;
    background: #eee;
  }
  &::after {
    content: "";
    position: absolute;
    width: 97%;
    height: 5px;
    background: #eee;
    transition: 0.5s ease-in;
    // border: 1px solid #bdd400;
    border-right: none;
    top: 15px;
    left: -50%;
    z-index: -1;
  }

  &:first-child:after {
    content: none;
  }

  &.active {
    color: ${({ theme }) =>
      theme?.Stepper?.stepperColor?.background || "#bdd400"};
  }

  &.active:before {
    border-color: ${({ theme }) =>
      theme?.Stepper?.stepperColor?.background || "#bdd400"};
    background: ${({ theme, gradient }) =>
      gradient
        ? gradient[0]
        : theme?.Stepper?.stepperColor?.background || "#bdd400"};
    transition: ${import.meta.env.VITE_BROKER === "TATA"
      ? "0.1s ease-in"
      : "0.5s ease-in"};
  }

  &.active:after {
    background: ${({ theme, gradient }) =>
      gradient
        ? gradient[1]
        : theme?.Stepper?.stepperColor?.background || "#bdd400"};
    transition: 0.5s ease-in;
  }
`;

export const StyledH3 = styled.h3`
  color: ${({ theme }) => theme.regularFont?.fontColor || "rgb(74, 74, 74)"};
  ${import.meta.env.VITE_BROKER === "TATA" &&
  ` background: linear-gradient(to right, #00bcd4 0%, #ae15d4 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;`}
  font-size: 30px;
  font-family: ${({ theme }) =>
    theme.regularFont?.headerFontFamily || "sans-serif"};
  @media (max-width: 767px) {
    font-size: 20px;
  }
  @media (max-width: 600px) {
    position: relative;
    top: -41px;
  }
  @media (max-width: 330px) {
    position: unset;
  }
  /* ${import.meta.env.VITE_BROKER === "RB" &&
  `
    background: -webkit-linear-gradient(45deg,#339AEE,#387FB9);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  `} */
`;

export const StyledP = styled.p`
  color: rgb(74, 74, 74);
  @media (max-width: 767px) {
    font-size: 12px;
  }
`;

export const StyledBack = styled.div`
  padding-bottom: 30px;
  margin-top: -20px;
  z-index: 999;
  ${import.meta.env.VITE_BROKER === "ABIBL"
    ? `@media (max-width: 780px) {
    position: relative;
    top: -120px;
    left: -8.5%;
  }
  @media (max-width: 769px) {
    position: relative;
    top: -125px;
    left: -11%;
  }
  @media (max-width: 600px) {
    position: relative;
    top: -120px;
    left: -8.5%;
  }`
    : `@media (max-width: 780px) {
      position: relative;
      top: -73px;
      left: -8.5%;
    }
    @media (max-width: 769px) {
      position: relative;
      top: -125px;
      left: -11%;
    }
    @media (max-width: 600px) {
      position: relative;
      top: -73px;
      left: -8.5%;
    }`}
`;

export const GlobalStyle = createGlobalStyle`
.btn-link{
  color: ${({ theme }) => theme.Stepper?.stepperColor?.background}!important;

}
${
  import.meta.env.VITE_BROKER === "BAJAJ" &&
  ` 
.btn-light,.btn-light.focus, .btn-light:focus, .btn-light:hover{
background: transparent !important;
border: 1px solid transparent !important;
};

`
};
`;

export const BtnDiv = styled.div`
  .btn-outline-danger {
    color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "UIB"
        ? "#999"
        : theme?.Stepper?.stepperColor?.background &&
          theme?.Stepper?.stepperColor?.background}!important;
    border-color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "UIB"
        ? "#999"
        : theme?.Stepper?.stepperColor?.background &&
          theme?.Stepper?.stepperColor?.background}!important;
    &:active,
    &.active,
    &:hover,
    &.focus,
    &:focus {
      color: #fff !important;
      background: ${({ theme }) =>
        theme?.Stepper?.stepperColor?.background &&
        theme?.Stepper?.stepperColor?.background}!important;
      box-shadow: ${({ theme }) =>
        import.meta.env.VITE_BROKER === "UIB"
          ? "0px 0px 7px 0px #999"
          : theme?.Tile?.boxShadow
          ? theme?.Tile?.boxShadow
          : "0px 0px 7px 0px #33cc33 "} !important;
    }
  }

  .btn-danger {
    color: #fff !important;
    background: ${({ theme }) =>
      theme?.Stepper?.stepperColor?.background &&
      theme?.Stepper?.stepperColor?.background}!important;
    border-color: ${({ theme }) =>
      theme?.Stepper?.stepperColor?.background &&
      theme?.Stepper?.stepperColor?.background}!important;

    &:active,
    &.active,
    &:hover,
    &.focus,
    &:focus {
      color: #fff !important;
      background: ${({ theme }) =>
        theme?.Stepper?.stepperColor?.background &&
        theme?.Stepper?.stepperColor?.background}!important;
      box-shadow: ${({ theme }) =>
        import.meta.env.VITE_BROKER === "UIB"
          ? "0px 0px 7px 0px #999"
          : theme?.Tile?.boxShadow
          ? theme?.Tile?.boxShadow
          : "0px 0px 7px 0px #33cc33 "}!important;
    }
  }
`;

export const BtnDiv2 = styled.div`
  .btn-outline-danger {
    color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "UIB"
        ? "#999"
        : theme?.Stepper?.stepperColor?.background &&
          theme?.Stepper?.stepperColor?.background}!important;
    border-color: ${({ theme }) =>
      import.meta.env.VITE_BROKER === "UIB"
        ? "#999"
        : theme?.Stepper?.stepperColor?.background &&
          theme?.Stepper?.stepperColor?.background}!important;

    &:active,
    &.active,
    &:hover,
    &.focus,
    &:focus {
      color: #fff !important;
      background: ${({ theme }) =>
        theme?.Stepper?.stepperColor?.background &&
        theme?.Stepper?.stepperColor?.background}!important;
      box-shadow: ${({ theme }) =>
        import.meta.env.VITE_BROKER === "UIB"
          ? "0px 0px 7px 0px #999"
          : theme?.Tile?.boxShadow
          ? theme?.Tile?.boxShadow
          : "0px 0px 7px 0px #33cc33  "}!important;
    }
  }

  .btn-danger {
    color: #fff !important;
    background: ${({ theme }) =>
      theme?.Stepper?.stepperColor?.background &&
      theme?.Stepper?.stepperColor?.background}!important;
    border-color: ${({ theme }) =>
      theme?.Stepper?.stepperColor?.background &&
      theme?.Stepper?.stepperColor?.background}!important;

    &:active,
    &.active,
    &:hover,
    &.focus,
    &:focus {
      color: #fff !important;
      background: ${({ theme }) =>
        theme?.Stepper?.stepperColor?.background &&
        theme?.Stepper?.stepperColor?.background}!important;
      box-shadow: ${({ theme }) =>
        import.meta.env.VITE_BROKER === "UIB"
          ? "0px 0px 7px 0px #999"
          : theme?.Tile?.boxShadow
          ? theme?.Tile?.boxShadow
          : "0px 0px 7px 0px #33cc33 "} !important;
    }
  }
`;

