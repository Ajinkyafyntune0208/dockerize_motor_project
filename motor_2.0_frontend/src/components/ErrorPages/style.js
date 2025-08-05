import styled from "styled-components";

const NotFoundContainer = styled.div`
  position: relative;
  height: 100vh;
`;

const NotFound = styled.div`
  position: absolute;
  left: 50%;
  top: 50%;
  max-width: 520px;
  width: 100%;
  line-height: 1.4;
  text-align: center;
  -webkit-transform: translate(-50%, -50%);
  -ms-transform: translate(-50%, -50%);
  transform: translate(-50%, -50%);
`;

const NotFound404 = styled.div`
  position: relative;
  height: 200px;
  margin: 0px auto 20px;
  z-index: -1;
  @media only screen and (max-width: 480px) {
    height: 148px;
    margin: 0px auto 10px;
  }
`;

const H1Tag = styled.h1`
  font-family: ${({ theme }) =>
    theme?.regularFont?.fontFamily
      ? theme?.regularFont?.fontFamily
      : "'Montserrat', sans-serif"};
  font-size: 236px;
  font-weight: 200;
  margin: 0px;
  color: #211b19;
  text-transform: uppercase;
  position: absolute;
  left: 50%;
  top: ${({ str }) => (str ? "30%" : "50%")};
  -webkit-transform: translate(-50%, -50%);
  -ms-transform: translate(-50%, -50%);
  transform: translate(-50%, -50%);
  @media only screen and (max-width: 767px) {
    font-size: 148px;
  }
  @media only screen and (max-width: 480px) {
    font-size: 86px;
  }
`;

const H2Tag = styled.h2`
  font-family: ${({ theme }) =>
    theme?.regularFont?.fontFamily
      ? theme?.regularFont?.fontFamily
      : "'Montserrat', sans-serif"};
  font-size: 28px;
  font-weight: 400;
  text-transform: uppercase;
  color: #211b19;
  background: #f8f8f8;
  padding: 10px 5px;
  margin: auto;
  display: inline-block;
  position: absolute;
  bottom: 0px;
  left: 0;
  right: 0;
  @media only screen and (max-width: 480px) {
    font-size: 16px;
  }
`;

const AnchorTag = styled.a`
  font-family: ${({ theme }) =>
    theme?.regularFont?.fontFamily
      ? theme?.regularFont?.fontFamily
      : "'Montserrat', sans-serif"};
  display: inline-block;
  font-weight: 700;
  text-decoration: none;
  color: #fff;
  text-transform: uppercase;
  padding: 13px 23px;
  background: #006600;
  border: 1px solid #006600;
  font-size: 18px;
  -webkit-transition: 0.2s all;
  transition: 0.2s all;
  &:hover {
    color: #006600;
    background: #fff;
    border: 1px solid #006600;
  }
  @media only screen and (max-width: 480px) {
    padding: 7px 15px;
    font-size: 14px;
  }
`;

export { NotFoundContainer, NotFound, NotFound404, H1Tag, H2Tag, AnchorTag };
