import styled, { createGlobalStyle } from "styled-components";

export const GlobleStyle = createGlobalStyle`
 
 ${({ theme }) =>
   theme?.fontFamily &&
   `
      .label-text {
	font-family: ${theme?.fontFamily} !important;
}`};
`;

export const StyledH3 = styled.h3`
  color: ${({ theme }) => theme.regularFont?.fontColor || "rgb(74, 74, 74)"};
  font-size: 30px;
  font-family: ${({ theme }) =>
    theme.regularFont?.headerFontFamily || "sans-serif"};
  @media (max-width: 767px) {
    font-size: 20px;
  }
`;

export const StyledDiv = styled.div`
  ${import.meta.env.VITE_BROKER === "RB" &&
  `
.btn-outline-danger {
  color: #F27F21 !important;
  border-color: #F27F21 !important;

  &:active, &.active, &:hover  {
    color: #fff !important;
    background: #F27F21 !important;
  }
}

.btn-danger {
  color: #fff !important;
  background: #F27F21 !important;

    &:active, &.active, &:hover  {
      color: #fff !important;
      background: #F27F21 !important;
    }
}
`}
  ${import.meta.env.VITE_BROKER === "SPA" &&
  `
.btn-outline-danger {
  color: #3877D6 !important;
  border-color: #3877D6 !important;

  &:active, &.active, &:hover  {
    color: #fff !important;
    background: #3877D6 !important;
  }
}

.btn-danger {
  color: #fff !important;
  background: #3877D6 !important;

    &:active, &.active, &:hover  {
      color: #fff !important;
      background: #3877D6 !important;
    }
}
`}

${import.meta.env.VITE_BROKER === "BAJAJ" &&
  `
.btn-outline-danger {
  color: #ED1C24 !important;
  border-color: #ED1C24 !important;

  &:active, &.active, &:hover  {
    color: #fff !important;
    background: #ED1C24 !important;
  }
}

.btn-danger {
  color: #fff !important;
  background: #ED1C24 !important;

    &:active, &.active, &:hover  {
      color: #fff !important;
      background: #ED1C24 !important;
    }
}
`}
${import.meta.env.VITE_BROKER === "UIB" &&
  `
.btn-outline-danger {
  color: #006BFA !important;
  border-color: #006BFA !important;

  &:active, &.active, &:hover  {
    color: #fff !important;
    background: #006BFA !important;
  }
}

.btn-danger {
  color: #fff !important;
  background: #006BFA !important;

    &:active, &.active, &:hover  {
      color: #fff !important;
      background: #006BFA !important;
    }
}
`}
${import.meta.env.VITE_BROKER === "ACE" &&
  `
.btn-outline-danger {
  color: #23974e !important;
  border-color: #23974e !important;

  &:active, &.active, &:hover {
    color: #fff !important;
    background: #23974e !important;
    border-color: #23974e !important;
  }
  &:focus{
      box-shadow: 0px 0px 0px 0.2rem rgba(35,151,78,0.5)!important;
    }
}

.btn-danger {
  color: #fff !important;
  background: #23974e !important;
   border-color: #23974e !important;

    &:active, &.active, &:hover  {
      color: #fff !important;
      background: #23974e !important;
      border-color: #23974e !important;
    }
    &:focus{
      box-shadow: 0px 0px 0px 0.2rem  rgba(35,151,78,0.5)!important;
    }
}
`}
${import.meta.env.VITE_BROKER === "TATA" &&
  `
.btn-outline-danger {
  color: #0099f2  !important;
  border-color: #0099f2  !important;

  &:active, &.active, &:hover {
    color: #fff !important;
    background: #0099f2  !important;
    border: 1px solid #0099f2 !important;
  }
}

.btn-danger {
  color: #fff !important;
  background: #0099f2  !important;
  border: 1px solid #0099f2 !important;

    &:active, &.active, &:hover  {
      color: #fff !important;
      background: #0099f2  !important;
      border: 1px solid #0099f2 !important;
    }
}
`}
${import.meta.env.VITE_BROKER === "KMD" &&
  `
.btn-outline-danger {
  color: #812115  !important;
  border-color: #812115  !important;

  &:active, &.active, &:hover {
    color: #fff !important;
    background: #812115  !important;
    border: 1px solid #812115 !important;
  }
}

.btn-danger {
  color: #fff !important;
  background: #812115  !important;
  border: 1px solid #812115 !important;

    &:active, &.active, &:hover  {
      color: #fff !important;
      background: #812115  !important;
      border: 1px solid #812115 !important;
    }
}
`}
${import.meta.env.VITE_BROKER === "FYNTUNE" &&
  `
.btn-outline-danger {
  color: #00B8F5  !important;
  border-color: #00B8F5  !important;

  &:active, &.active, &:hover {
    color: #fff !important;
    background: #00B8F5  !important;
    border: 1px solid #00B8F5 !important;
  }
}

.btn-danger {
  color: #fff !important;
  background: #00B8F5  !important;
  border: 1px solid #00B8F5 !important;

    &:active, &.active, &:hover  {
      color: #fff !important;
      background: #00B8F5  !important;
      border: 1px solid #00B8F5 !important;
    }
}
`}

  .filter-green {
    filter: ${({ theme }) =>
      theme?.VehicleType?.filterIconCol ||
      `invert(42%) sepia(93%) saturate(1352%) hue-rotate(87deg)
			brightness(90%) contrast(119%)}`};
  }

  .btn-filter:hover .filter-green {
    filter: brightness(0) invert(1);
  }

  .filter-white {
    filter: brightness(0) invert(1);
  }
`;

export const StyledBack = styled.div`
  padding-bottom: 30px;
  ${({ hide }) => (hide ? `display: none;` : ``)}
  margin-top: -20px;
  z-index: 999;
  ${import.meta.env.VITE_BROKER === "ABIBL"
    ? `@media (max-width: 780px) {
    position: relative;
    top: -120px;
    left: -10%;
  }
  @media (max-width: 769px) {
    position: relative;
    top: -125px;
    left: -11%;
  }
  @media (max-width: 600px) {
    position: relative;
    top: -120px;
    left: -10%;
  }`
    : `@media (max-width: 780px) {
      position: relative;
      top: -73px;
      left: -10%;
    }
    @media (max-width: 769px) {
      position: relative;
      top: -125px;
      left: -11%;
    }
    @media (max-width: 600px) {
      position: relative;
      top: -73px;
      left: -10%;
    }`}
`;
