import styled from "styled-components";

export const FormGroupSelect = styled.div`
  margin-bottom: 30px;
  position: relative;
  min-width: 210px;
  max-height: 54px !important;
  margin: 1rem 0px;
`;

export const SelectInput = styled.select`
  ${({ theme }) =>
    theme.dark
      ? `
option {
  background-color: #383838;  
}`
      : ""}

  background-color: rgb(255, 255, 255);
  box-sizing: border-box;
  max-height: 54px !important;
  width: 100%;
  font-family: ${({ theme }) =>
    theme.fontFamily ? theme.fontFamily : `'Titillium Web', sans-serif`};
  color: ${({ theme }) => (theme.dark ? "#FAFAFA" : "#000000")};
  cursor: pointer;
  border: 1px solid #cae9ff;
  display: inline-block;
  border-radius: 5px;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  font-size: 12px !important;
  outline: none;
  -moz-appearance: none;
  -webkit-appearance: none;
  appearance: none;
  background: transparent
    url("data:image/gif;base64,R0lGODlhBgAGAKEDAFVVVX9/f9TU1CgmNyH5BAEKAAMALAAAAAAGAAYAAAIODA4hCDKWxlhNvmCnGwUAOw==")
    no-repeat right 10px center;
  border: 1px solid #cae9ff;
  padding: 16px !important;
  min-height: 52px;
  &:focus {
    border-color: #80bdff !important;
  }
  &:focus {
    box-shadow: none !important;
    border: 1px solid #7cbae7;
  }
  &&.error {
    border: 1px solid red;
  }
  &:not([size]):not([multiple]) {
    text-align-last: center;
    font-size: 12px;
  }
`;
export const SelectLabel = styled.label`
  position: absolute;
  transition: 0.25s ease;
  font-size: 14px !important;
  text-align: center;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `'Titillium Web', sans-serif`};
  width: 100%;
  font-weight: 500;
  display: inline-block;
  top: -12px;
  color: #000;
  left: -0px;
`;

export const SelectSpan = styled.span`
  background: ${({ theme }) => (theme.dark ? "#2a2a2a" : "#fff")};
  padding: 2px 4px;
  font-weight: 600;
  letter-spacing: 1px;
  font-size: 13px;
  color: ${({ theme }) => (theme.dark ? "#FAFAFA" : "#606060")};
  left: 5px;
  margin-bottom: 6px;
`;
export const Img = styled.img`
  height: 8px;
`;
