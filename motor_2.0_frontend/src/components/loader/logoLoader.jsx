import React from "react";
import styled, { createGlobalStyle } from "styled-components";
import { LogoFn } from "components";
import LinearProgress from "@mui/material/LinearProgress";
import { parseFormulas } from "modules/quotesPage/calculations/parser";
export const LogoLoader = () => {

  return (
    <div style={{ display: "flex", height: "100vh" }}>
      <div className="wrapper">
        <h1 className="brand">
          <Logo src={LogoFn()} alt="logo" />
        </h1>
        <ProgressBar>
          <LinearProgress className="custom-progress" />
        </ProgressBar>
        {/* <div className="loading-bar"></div> */}
      </div>
      <GlobalStyle />
    </div>
  );
};

const GlobalStyle = createGlobalStyle`

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Poppins', sans-serif;
}
/*use flexbox to centered element*/
div.wrapper {
    margin: auto;
   background: ${({ theme }) => (theme?.dark ? "#00000078" : "#fffdfd78")};
   z-index: 10 !important;
}
h1.brand {
  font-size: 30px;
}
h1.brand span:nth-child(1) {
  background-color: transparent;
}
div.loading-bar {
  width: 160px;
  height: 6px;
  background-color: #d6cec2;
  border-radius: 10px;
  margin-top: 5px;
  overflow: hidden;
  position: relative;
}
div.loading-bar::after {
  content: '';
  width: 50px;
  height: 6px;
  position: absolute;
  background-color: #0074b4;
  transform: translateX(-20px);
  animation: loop 2s ease infinite;
}
@keyframes loop {
  0%,100% {
    transform: translateX(-28px);
  }
  50% {
    transform: translateX(78px)
  }
}
`;

const ProgressBar = styled.div`
  .custom-progress > * {
    background-color: #0074b4 !important;
  }
  .custom-progress {
    background-color: #d3d3d3 !important;
  }
`;

const Logo = styled.img`
  //   transform: scale(2)
  width: ${import.meta.env.VITE_BROKER === "ACE"
    ? "187.5px"
    : import.meta.env.VITE_BROKER === "SRIYAH"
    ? "157.5px"
    : import.meta.env.VITE_BROKER === "RB"
    ? "auto"
    : "auto"};
  height: ${import.meta.env.VITE_BROKER !== "FYNTUNE"
    ? import.meta.env.VITE_BROKER === "ACE" ||
      import.meta.env.VITE_BROKER === "SRIYAH"
      ? "76.5px"
      : import.meta.env.VITE_BROKER === "BAJAJ"
      ? "35px"
      : import.meta.env.VITE_BROKER === "RB"
      ? "81px"
      : "70px"
    : "38px"};
  @media (max-width: 768px) {
    width: ${import.meta.env.VITE_BROKER === "ACE"
      ? "115px"
      : import.meta.env.VITE_BROKER === "SRIYAH"
      ? "85px"
      : import.meta.env.VITE_BROKER === "RB"
      ? "auto"
      : "auto"};
    height: ${import.meta.env.VITE_BROKER !== "FYNTUNE"
      ? "45px"
      : import.meta.env.VITE_BROKER === "BAJAJ"
      ? "30px" 
      : import.meta.env.VITE_BROKER === "RB"
      ? "70px"
      : "32px"};
  }
  @media (max-width: 415px) {
    width: ${import.meta.env.VITE_BROKER === "ACE"
      ? "115px"
      : import.meta.env.VITE_BROKER === "SRIYAH"
      ? "85px"
      : import.meta.env.VITE_BROKER === "RB"
      ? "auto"
      : "auto"};
    height: ${import.meta.env.VITE_BROKER !== "FYNTUNE"
      ? import.meta.env.VITE_BROKER === "ACE" ||
        import.meta.env.VITE_BROKER === "SRIYAH"
        ? "51px"
        : import.meta.env.VITE_BROKER === "BAJAJ"
        ? "30px"
        : import.meta.env.VITE_BROKER === "RB"
        ? "70px"
        : "35px"
      : "38px"};
  }
`;
