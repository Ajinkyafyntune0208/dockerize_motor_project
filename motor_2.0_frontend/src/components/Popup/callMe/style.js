import styled from "styled-components";

export const CallText = styled.div`
  width: 340px;
  margin: 37px auto 37px;
  text-align: center;
  font-family: ${({ theme }) =>
    theme?.mediumFont?.fontFamily
      ? theme?.mediumFont?.fontFamily
      : "basier_squaremedium"};
  font-size: 16px;
  color: #000000;
`;
export const MessageContainer = styled.div`
  padding: 10px;
  & svg {
    margin: 0 auto;
    width: 100%;
  }
`;

export const Content1 = styled.div`
  height: 90%;
  padding: 30px 50px;
`;

export const ContactText = styled.h4`
  text-align: center;
  padding: 10px;
  font-family: ${({ theme }) =>
    theme?.regularFont?.fontFamily
      ? theme?.regularFont?.fontFamily
      : "basier_squareregular"};
  font-size: 15px;
  color: #111;
`;
export const Button = styled.button`
  &:disabled {
    background-color: #dfe3e8;
    color: #111;
    border: solid 1px #d2d3d4;
  }
  &:focus {
    outline: none;
  }
  display: inline-block;
  padding: 0px 25px;
  box-sizing: content-box;
  font-size: 17px;
  background-color: ${({ theme }) => theme.Header?.color || "#bdd400"};
  font-weight: 600;
  border: none;
  color: white;
  height: 60px;
`;

export const Heading = styled.p`
  margin-bottom: 0px;
  font-size: 25px;
  text-align: center;
`;

export const ORLine = styled.h6`
  font-size: 20px;
  overflow: hidden;
  text-align: center;
  :before,
  :after {
    background-color: #000;
    content: "";
    display: inline-block;
    height: 0.5px;
    position: relative;
    vertical-align: middle;
    width: 100px;
  }
  :before {
    right: 0.5em;
  }
  :after {
    left: 0.5em;
  }
`;

export default {
  CallText,
  MessageContainer,
  Content1,
  ContactText,
  Button,
  Heading,
  ORLine,
};
