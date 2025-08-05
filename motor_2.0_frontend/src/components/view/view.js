import styled from "styled-components";

export let Head = styled.p`
  font-size: ${({ fontSize }) => fontSize || "13px"};
  font-weight: 600;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `'Titillium Web', sans-serif`};
  line-height: 18px;
  color: ${({ theme }) => (theme.dark ? "#FAFAFA" : "#444444")};
  margin-bottom: 8px;
  text-align: left;
  letter-spacing: 1px;
`;

export let Text = styled.p`
  color: ${({ theme }) => (theme.dark ? "#FAFAFA" : "#606060")};
  font-size: ${({ fontSize }) => fontSize || "12px"};
  font-weight: 500;
  line-height: 18px;
  margin-bottom: 25px;
  letter-spacing: 1px;
  overflow-wrap: break-word;
  font-family: ${({ theme }) =>
    theme?.fontFamily
      ? theme?.fontFamily
      : `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen,
    Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif`};
`;
// margin : 1em 0.5em 0.5em 0.5em;
export const Typography = styled.div`
  cursor: pointer;
  font-size: 1.2em;
  font-weight: 600;
  color: ${({ theme }) => (theme.dark ? "#FAFAFA" : "#606060")};
  display: inline-block;
`;

export const Marker = styled.div`
  display: inline-block;
  height: 10px;
  width: 10px;
  border-radius: 50%;
  background-color: #d757f6;
  opacity: 0.5;
`;
export const Error = styled.p`
  margin-top: ${({ top }) => top || "-10px"};
  font-size: 13px;
  text-align: center;
  color: red;
`;
