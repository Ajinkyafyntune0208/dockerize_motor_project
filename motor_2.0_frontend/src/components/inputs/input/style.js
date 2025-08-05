import styled from "styled-components";

export const FormGroupInput = styled.div`
  margin-bottom: 30px;
  position: relative;
  min-width: 210px;
  max-height: 54px !important;
  margin: 1rem 0px;
`;

export const Input = styled.input`
  box-sizing: border-box;
  width: 100%;
  display: block;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `'Titillium Web', sans-serif`};
  color: ${({ theme }) => (theme.dark ? "#FAFAFA" : "#000000")};
  background: transparent;
  cursor: text !important;
  border: 1px solid #cae9ff;
  display: inline-block;
  text-align: center;
  margin: 0em;
  padding: 1px 0px;
  border-radius: 5px;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
  font-size: 14px !important;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `'Titillium Web'`};
  padding: 8px 8px;
  height: 52px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 400;
  overflow-wrap: break-word;

  &:disabled {
    background-color: ${({ theme }) => (theme.dark ? "#ffffff00" : "#dadada")};
    cursor: not-allowed !important;
  }
  &:focus {
    outline: 0;
    border-color: #80bdff !important;
    box-shadow: #80bdff 0px 0px 2px;
  }

  &&.error {
    border: 1px solid red;
  }
`;

export const FormLabel = styled.label`
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

export const SpanLabel = styled.span`
  white-space: nowrap;
  background: ${({ theme }) => (theme.dark ? "#2a2a2a" : "#fff")};
  padding: 2px 1px;
  font-weight: 600;
  letter-spacing: 1px;
  font-size: 13px;
  color: ${({ theme }) => (theme.dark ? "#FAFAFA" : "#606060")};
  left: 5px;
`;

export const Img = styled.img`
  height: 8px;
`;
