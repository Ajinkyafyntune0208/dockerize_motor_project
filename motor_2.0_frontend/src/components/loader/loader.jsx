import React from "react";
import styled from "styled-components";

const Spinner = styled.div`
  margin: auto 0;
  width: 70px;
  text-align: center;
  height: 200px;
  padding-top: 0px;
  position: absolute;
  z-index: 99;
  top: 50%;
  left: 48%;
  width: 100%;
  height: 104%;
  position: fixed;
  top: 0;
  left: 0;
  background: ${({ theme, proposal }) =>
    proposal ? "#fff" : theme.dark ? "#00000078" : "#fffdfd78"};
  z-index: 20000000 !important;
  display: flex;
  align-items: center;
  justify-content: center;
  & > div {
    width: 18px;
    height: 18px;
    background-color: #464646;
    border-radius: 100%;
    display: inline-block;
    -webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;
    animation: sk-bouncedelay 1.4s infinite ease-in-out both;
  }
  & .bounce1 {
    -webkit-animation-delay: -0.32s;
    animation-delay: -0.32s;
  }
  & .bounce2 {
    -webkit-animation-delay: -0.16s;
    animation-delay: -0.16s;
  }
  @-webkit-keyframes sk-bouncedelay {
    0%,
    80%,
    100% {
      -webkit-transform: scale(0);
    }
    40% {
      -webkit-transform: scale(1);
    }
  }
  @keyframes sk-bouncedelay {
    0%,
    80%,
    100% {
      -webkit-transform: scale(0);
      transform: scale(0);
    }
    40% {
      -webkit-transform: scale(1);
      transform: scale(1);
    }
  }
`;

export const Loader = ({ proposal, scaleHeight }) => {
  return (
    <Spinner style={{ cursor: "wait" }} proposal={proposal}>
      <div className="bounce1"></div>
      <div className="bounce2"></div>
      <div className="bounce3"></div>
    </Spinner>
  );
};
