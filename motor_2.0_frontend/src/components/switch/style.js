import styled from "styled-components";

export const InputBorder = styled.div`
  box-sizing: border-box;
  background-color: tranparent;
  border-radius: 5px;
  max-height: 54px;
  margin-bottom: 30px;
  position: relative;
  min-width: ${({ consent }) => (consent ? "auto" : "210px")};
  max-height: 53px;
  margin: 1rem 0px;
`;

export const CustomControl = styled.div`
  position: relative;
  display: block;
  min-height: 30px;
  padding: 0px 1.5rem;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `Arial`}!important;
  .toggleTextYes {
    color: ${({ theme, gst_text_color }) =>
      gst_text_color
        ? gst_text_color
        : theme?.FilterConatiner?.clearAllTextColor
        ? theme?.FilterConatiner?.clearAllTextColor
        : "black"}!important;
    position: relative;
    top: -9px;
    font-size: 12px;
    color: #000;
    right: -82px;
    zindex: -1;
    cursor: pointer;
  }
  .toggleTextNo {
    color: ${({ theme, gst_text_color }) =>
      gst_text_color
        ? gst_text_color
        : theme?.FilterConatiner?.clearAllTextColor
        ? theme?.FilterConatiner?.clearAllTextColor
        : "black"}!important;
    position: relative;
    top: -9px;
    right: -64px;
    font-size: 12px;
    color: #000;
    zindex: -1;
    cursor: pointer;
  }
  @media (max-width: 1400px) {
    .toggleTextNo {
      right: -59px;
    }
  }
  @media (max-width: 993px) {
    .toggleTextYes {
      top: 2px;
    }
    .toggleTextNo {
      top: 2px;
    }
  }
  @media (max-width: 993px) {
    .toggleTextYes {
      top: -4px;
      right: -6px;
      left: unset;
      position: absolute;
      font-size: 9px;
    }
    .toggleTextNo {
      top: -4px;
      right: 11px;
      left: unset;
      position: absolute;
      font-size: 9px;
    }
  }
`;
export const SwitchContainer = styled.div`
  position: absolute;
  top: 40%;
  left: 40%;
  -webkit-transform: translate3d(-50%, -50%, 0);
  transform: translate3d(-50%, -50%, 0);

  @media (max-width: 993px) {
    right: -60px;
    left: unset;
  }
`;

export const SwitchInput = styled.input`
  position: absolute;
  opacity: 0;
  height: 0px;
  & + div {
    vertical-align: middle;
    border-radius: 50px;
    background-color: ${({ dark, theme, gst_color_no, checked, consent }) =>
      !checked && consent
        ? "gray"
        : gst_color_no
        ? gst_color_no
        : dark
        ? "#cbcbcb"
        : theme.FilterConatiner?.lightColor || "#f3ff91"};
    -webkit-transition-duration: 0.4s;
    transition-duration: 0.4s;
    /* -webkit-transition-property: background-color, box-shadow;
    transition-property: background-color, box-shadow; */
    cursor: pointer;
    width: ${({ consent }) => (consent ? "45px" : "57px")};
    height: ${({ consent }) => (consent ? "20px" : "25px")};
  }
  & + div span {
    position: absolute;
    font-size: 1.6rem;
    color: white;
    margin-top: 12px;
  }
  & + div span:nth-child(1) {
    margin-left: 15px;
  }
  & + div span:nth-child(2) {
    margin-left: 57px;
  }
  &:checked + div {
    width: ${({ consent }) => (consent ? "45px" : "57px")};
    background-position: 0 0;
    background-color: ${({ dark, theme, gst_color }) =>
      gst_color
        ? gst_color
        : dark
        ? "#000000"
        : theme.FilterConatiner?.lightColor || "#f3ff91"};
  }
  & + div > div {
    float: left;
    width: ${({ consent }) => (consent ? "18px" : "23px")};
    height: ${({ consent }) => (consent ? "18px" : "23px")};
    border-radius: inherit;
    background: white !important;
    border: ${({ theme, gst_color_no }) =>
      gst_color_no
        ? `1px solid ${gst_color_no}`
        : theme.QuoteCard?.border || "1px solid #bdd400"};
    -webkit-transition-timing-function: cubic-bezier(1, 0, 0, 1);
    transition-timing-function: cubic-bezier(1, 0, 0, 1);
    -webkit-transition-duration: 0.4s;
    transition-duration: 0.4s;
    /* -webkit-transition-property: transform, background-color;
    transition-property: transform, background-color; */
    pointer-events: none;
    margin-top: 1px;
    margin-left: 1px;
  }
  &:checked + div > div {
    -webkit-transform: translate3d(20px, 0, 0);
    transform: translate3d(20px, 0, 0);
    background: white !important;
    border: ${({ theme, gst_color }) =>
      gst_color
        ? `1px solid ${gst_color}`
        : theme.QuoteCard?.border || "1px solid #bdd400"};
  }
  &:checked + div > div {
    -webkit-transform: ${({ consent }) =>
      consent ? "translate3d(25px, 0, 0)" : "translate3d(32px, 0, 0)"};
    transform: ${({ consent }) =>
      consent ? "translate3d(25px, 0, 0)" : "translate3d(32px, 0, 0)"};
  }
`;

export const FormLabel = styled.label`
  position: absolute;
  transition: 0.25s ease;
  font-size: 14px !important;
  text-align: center;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"Titillium Web", sans-serif`};
  width: 100%;
  font-weight: 500;
  display: inline-block;
  top: -12px;
  color: #000;
  left: -0px;
`;

export const SpanLabel = styled.span`
  background: ${({ theme }) => (theme.dark ? "#2a2a2a" : "#fff")};
  padding: 2px 1px;
  font-weight: 600;
  letter-spacing: 1px;
  font-size: 12px;
  color: ${({ theme }) => (theme.dark ? "#FAFAFA" : "#606060")};
  left: 5px;
`;
export const CustomLabel = styled.label`
  position: relative;
  cursor: pointer;
  margin-top: 10px;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
  color: ${({ theme }) =>
    theme.dark ? "#FAFAFA" : "rgb(131, 109, 109)"} !important;
  font-weight: 500;
  font-size: 12px;
  input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
  }
  &:hover input ~ span {
    background-color: rgb(204, 198, 198);
    transition: all 0.2s;
  }
  input:checked ~ span {
    background-color: rgb(243, 73, 206) !important;
  }
  input:checked ~ span:after {
    display: block;
  }
  span {
    position: absolute;
    left: 0;
    top: -4px;
    height: 23px;
    width: 23px;
    background-color: rgb(228, 228, 228);
    border-radius: 50%;
    box-sizing: border-box;
  }
  span:after {
    content: "";
    position: absolute;
    display: none;
  }
  span:after {
    left: 8px;
    top: 4px;
    width: 5px;
    height: 10px;
    transition: all 1s;
    border: solid rgb(255, 255, 255);
    border-width: 0 2px 2px 0;
    -webkit-transform: rotate(45deg);
    -ms-transform: rotate(45deg);
    transform: rotate(45deg);
  }
  p {
    font-weight: 600;
    padding-left: 27px;
  }
`;
export const Img = styled.img`
  height: 8px;
`;

export const ToggleValues = styled.label`
  position: relative;
  bottom: 12px !important;
  margin: 5px;
  font-weight: 400;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"basier_squareregular"`};
  cursor: pointer;

  @media (max-width: 993px) {
    font-size: 12px;
  }
  @media (max-width: 768px) {
    font-size: 11px;
  }
  @media (max-width: 600px) {
    font-size: 12px;
    bottom: 13px !important;
    font-weight: 500;
  }
  @media (max-width: 360px) {
    font-size: 10px;
    bottom: 15px !important;
    font-weight: 500;
  }
`;
