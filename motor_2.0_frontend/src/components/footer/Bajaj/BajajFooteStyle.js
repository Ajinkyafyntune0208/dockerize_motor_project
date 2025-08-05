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
`}`;

export const Logo = styled.img`
  height: 42px;
  width: 181px;
  margin-bottom: 1.25rem;
  vertical-align: middle;
  border-style: none; ;
`;
export const Content = styled.div`
  // max-width: 100%;
  color: #00000087;
  padding: 1rem 0;
  line-height: 32px;
  font-size: 14px;
  font-weight: normal;
  margin-bottom: 3rem;
  span {
    color: #1c75bc;
    cursor: pointer;
    &:hover {
      color: #ed1b24;
    }
  }
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
  padding: 4rem 0 0 0 !important;
  .underline-on-hover {
    // text-decoration: underline;
    cursor: pointer;
    color: inherit;
    font-size: 14px;
    margin-bottom: 0.5rem !important;
    &:hover {
      color: #ed1b24;
      text-decoration: none;
    }
  }
  .cursorChange {
    cursor: pointer;
  }
  //   text-align: left !important;
`;

export const FooterTitle = styled.div`
  margin: 8px 0 10px;
  line-height: inherit;
  color: #1c75bc;
  font-size: 16px;
  line-height: 20px;
`;

export const Line = styled.hr`
  height: 3px;
`;

export const BottomFooter = styled.div`
  border-top: 2px solid rgba(0, 0, 0, 0.1);
  margin-top: 2rem;
  p {
    font-size: 14px;
    line-height: 20px;
    font-weight: normal;
    color: #00000087;
    max-width: 100%;
    margin: 0.7rem 0;
    color: #00000087;
  }
  // display: flex;
  // justify-content: space-between;
  // align-items: center;
`;

export const Address = styled.p`
  font-size: 14px;
  line-height: 20px;
  font-weight: normal;
  margin-bottom: 0.5rem;
  color: #1e2833;
  max-width: 300px;
`;

export const Link = styled.a`
  color: inherit;
  font-size: 14px;
  cursor: pointer;
  margin-bottom: 0.5rem;
  &:hover {
    color: #ed1b24;
    text-decoration: none;
  }
`;

export const MiddleFooter = styled.div``;
