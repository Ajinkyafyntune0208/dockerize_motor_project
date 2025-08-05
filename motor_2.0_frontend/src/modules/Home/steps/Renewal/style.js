import styled, { createGlobalStyle } from 'styled-components';

export const GlobalStyle = createGlobalStyle`
body {
	.inputStyle{
    margin-right: 10px;
    text-align: center;
  }
  .proceedBtnStyle{
    width: 170px;
  }
   @media (max-width: 767px) {
    .inputStyle{
      margin-right: 0px;
      height: 60px;
      border: 2px solid rgb(210,211,212);
    }
    .proceedBtnStyle{
      margin-top: 15px;
    }
  }
}
`;

export const ORLine = styled.h6`
  margin: 36px 100px 24px 100px;
  font-size: 20px;
  overflow: hidden;
  text-align: center;
  :before,
  :after {
    background-color: #000;
    content: "";
    display: inline-block;
    height: 1px;
    position: relative;
    vertical-align: middle;
    width: 50%;
  }
  :before {
    right: 0.5em;
    margin-left: -50%;
  }
  :after {
    left: 0.5em;
    margin-right: -50%;
  }
`;

export const Container = styled.div`
  width: 600px;
  margin: 50px auto;
  background-color: white;
  // box-shadow: rgba(0, 0, 0, 0.07) 0px 1px 2px, rgba(0, 0, 0, 0.07) 0px 2px 4px,
  //   rgba(0, 0, 0, 0.07) 0px 4px 8px, rgba(0, 0, 0, 0.07) 0px 8px 16px,
  //   rgba(0, 0, 0, 0.07) 0px 16px 32px, rgba(0, 0, 0, 0.07) 0px 32px 64px;
  padding-bottom: 80px;
  border-radius: 10px;

  @media (max-width: 767px) {
    width: 100%;
  }
`;

export const Header = styled.div`
  display: flex;
`;

export const Logo = styled.div`
  margin-left: 20px;
  width: 76px;
  padding: 10px;
  @media (max-width: 767px) {
    width: 125px;
  }
`;

export const HeaderContent = styled.div`
  padding: 10px 10px 0px 10px;
`;

export const HeadText = styled.h4`
  font-weight: bold;
`;

export const HeaderBody = styled.p`
  color: grey;
`;

export const HrLine = styled.hr`
  margin-top: 0 !important;
`;

export const Body = styled.div`
  margin-top: 25px;
`;

export const MoreContent = styled.div`
  text-align: center;
`;

export const InputContainer = styled.div`
  width: 90%;
  margin: auto;
  display: flex;
  @media (max-width: 767px) {
    flex-direction: column;
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
export const ColorText = styled.text`
  color: ${({ theme }) => theme.LandingPage?.color || "green"};
  font-weight: 600;
  @media (max-width: 1030px) {
    color: ${({ theme }) => theme.LandingPage?.color3 || "green"};
  }
`;
