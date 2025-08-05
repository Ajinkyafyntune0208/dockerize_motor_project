import styled, { createGlobalStyle } from "styled-components";

export const FooterContainer = styled.div`
  font-size: 16px;
  a {
    font: 300 16px/20px sans-serif;
    color: #1f1f1f;
    position: relative;
    transition: color 0.2s linear;
    display: inline-block;
    :hover {
      color: #f30909;
      text-decoration: none;
      outline: 0 !important;
      ::after {
        transform: scale(1, 1);
      }
    }
    ::after {
      content: "";
      position: absolute;
      width: 100%;
      height: 2px;
      left: 0;
      bottom: 0;
      background: red;
      transform: scale(0, 1);
      transform-origin: 0% 100%;
      transition: transform 0.3s ease;
    }
  }
  h3 {
    font-family: ${({ theme }) =>
      theme?.fontFamily
        ? theme?.fontFamily
        : `"basiersquaremed", Arial, sans-serif`};
    margin-top: 5px;
    text-shadow: none;
    color: inherit;
  }
  .color_grey_f {
    color: #1f1f1f;
  }
  .mb_40 {
    margin-bottom: 40px;
  }
  .f_600 {
    font-weight: 600;
  }
  .f_400 {
    font-weight: 400;
  }
  .f_p {
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `sans-serif`};
  }
  .l_height34 {
    line-height: 34px;
  }
  .f_size_15 {
    font-size: 15px;
  }
  .f_subscribe {
    position: relative;
    margin-top: 13px;
    margin-bottom: 12px;
  }
  .f_subscribe .form-control {
    font: 300 14px sans-serif;
    color: #333;
    border: 1px solid #e2e7f3;
    border-radius: 3px;
    background: #e9ecf3;
    padding-left: 25px;
    height: 54px;
    -webkit-box-shadow: none;
    box-shadow: none;
  }
  .f_size_18 {
    font-size: 18px;
  }
  .f_widget.about-widget .f_list {
    margin-bottom: 0px;
  }
  .f_widget.about-widget .f_list li {
    margin-bottom: 15px;
  }

  .f_subscribe button {
    position: absolute;
    right: 25px;
    background: transparent;
    padding: 0;
    color: #222d39;
    font-size: 16px;
    top: 52%;
    transform: translateY(-50%);
  }
`;

export const TopContainer = styled.div`
  border-bottom: 1px solid #e9ecf3;
  padding: 120px 0px 70px;
  background: #e0e0e0;
  display: flex;
  text-align: left;

  .logo_ic {
    width: 220px;
    height: auto;
  }
  .pl_40 {
    padding-left: 40px;
    @media screen and (max-width: 992px) {
      padding-left: 0px;
    }
  }
  .f_social_icon a {
    width: 46px;
    height: 46px;
    border-radius: 50% !important;
    font-size: 14px;
    line-height: 45px;
    color: #858da8;
    display: inline-block;
    background: #ebeef5;
    text-align: center;
    transition: all 0.2s linear;
    :hover {
      background: #515eb8;
      color: #fff;
    }
  }
  .f_social_icon a + a {
    margin-left: 10px;
  }
  p {
    font-size: 16px;
  }
  .f_widget .widget-wrap {
    margin-top: 35px;
  }
  input[type="text"] {
    -webkit-appearance: none;
    outline: none;
  }
  .f-title {
    color: #051441;
    font-family: ${({ theme }) =>
      theme?.fontFamily ? theme?.fontFamily : `"Poppins", sans-serif`};
  }
`;

export const LeftContainer = styled.div``;

export const RightContainer = styled.div``;

export const BottomContainer = styled.div`
  font-size: 14px;
  font-weight: 300;
  line-height: 20px;
  color: #7f88a6;
  padding: 27px 0px;
  background: #252525;
  p {
    color: #ccc;
    font-weight: 400;
  }
`;

export const GlobalStyle = createGlobalStyle`
${({ lessthan767 }) =>
  lessthan767
    ? `
body::after {
    content: '';
    display: block;
    height: 1150px; /* Set same as footer's height */
  }`
    : `
body::after {
    content: '';
    display: block;
    height: 720px; /* Set same as footer's height */
  }
`}`;
export const FooterTag = styled.footer`
  padding: unset !important;
`;
