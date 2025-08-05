import styled, { createGlobalStyle } from "styled-components";

export const GlobalStyle = createGlobalStyle`
${({ lessthan767 }) =>
  lessthan767
    ? `
body::after {
    content: '';
    display: block;
    height: 1613px; /* Set same as footer's height */
  }`
    : `
body::after {
    content: '';
    display: block;
    height: 520px; /* Set same as footer's height */
  }
`}
.tickLogo{
  filter:  brightness(0) invert(1);
}
.socialIcon{
  width: 30px;
  background: white;
  height: 30px;
  border-radius: 5px;
  padding: 5px;
  margin-right: 15px;
}

`;

export const Logo = styled.img`
  height: 60px;
  width: 171px;
  object-fit: contain;
  margin-bottom: 1.25rem;
  vertical-align: middle;
  border-style: none;
  // filter: brightness(0) invert(1);
`;
export const Content = styled.div`
  // max-width: 100%;
  color: #fff;
  /* padding-top: 1rem !important;
  padding-bottom: 1rem !important; */
  padding: 16px 50px;
  // line-height: 32px;
  font-size: 12px;
  font-weight: normal;
  text-align: center;
  a {
    color: inherit;
    font-size: 14px;
  }
`;

export const MediaContainer = styled.div`
  display: flex;
  flex-direction: column;
`;

export const FooterTag = styled.footer`
  text-align: left !important;
  font-size: 1rem;
  font-weight: 500;
`;

export const FooterContainer = styled.div`
  display: flex;
  gap: 4px;
  margin-bottom: 4px;
  a {
    text-decoration: none;
    &:hover {
      color: "";
      text-decoration: none;
    }
  }
`;

export const ContactText = styled.p`
  margin-top: 0;
  margin-bottom: 1rem;
  color: #fff;
`;

export const FooterTitle = styled.h4`
  font-size: 16px;
  font-weight: bold;
  color: #fff;
  position: relative;
  padding-bottom: 12px;
  font-family: "Roboto", sans-serif;
`;

export const Line = styled.hr`
  background: #fff;
`;

export const MiddleFooter = styled.div`
  background-color: rgb(30, 42, 168);
  @media only screen and (max-width: 768px) {
    padding: 1rem 2rem 3rem 2rem !important;
  }
`;

export const TollText = styled.p`
  margin-bottom: 0px !important;
  line-height: 20px;
  span {
    a {
      cursor: pointer;
      font-size: 12px;
      &:hover {
        text-decoration: none;
      }
    }
  }
`;

export const InsideText = styled.div`
  margin: 0px 0px 10px 20px;
  font-size: 14px;
  font-family: ${({ theme }) =>
    theme?.fontFamily ? theme?.fontFamily : `"Roboto", sans-serif`};
  display: flex;
  flex-direction: column;
  a {
    color: #fff;
    margin: 5px;
    text-decoration: none;
  }
`;

export const Main = styled.div`
  padding: 1rem 4rem 3rem 4rem !important;
  @media only screen and (max-width: 768px) {
    padding: 1rem 2rem 3rem 2rem !important;
  }
`;

export const CopyrightText = styled.img`
  width: 420px;
  margin-bottom: 15px;
  margin-top: 8px;
`;
